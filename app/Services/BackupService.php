<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Backup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    protected string $dir = 'backups';

    /**
     * Create a compressed SQL dump of the MySQL database using mysqldump.
     */
    public function create(string $type = 'manual', ?int $userId = null): Backup
    {
        $config = config('database.connections.'.config('database.default'));

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Database backup currently supports MySQL only.');
        }

        Storage::disk('local')->makeDirectory($this->dir);

        $timestamp = now()->format('Y_m_d_His');
        $filename = "supershop_backup_{$timestamp}.sql";
        $absolute = Storage::disk('local')->path($this->dir.'/'.$filename);

        $binary = $this->findBinary('mysqldump');

        $args = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--skip-lock-tables',
            $config['database'],
        ];

        $env = [];
        if (! empty($config['password'])) {
            $env['MYSQL_PWD'] = $config['password'];
        }

        $output = fopen($absolute, 'w');
        $process = new Process($args, base_path(), $env + $this->baseEnv());
        $process->setTimeout(600);
        // Stream only stdout (the SQL dump) to the file; stderr is captured for diagnostics.
        $process->run(function ($type, $buffer) use ($output) {
            if ($type === Process::OUT) {
                fwrite($output, $buffer);
            }
        });
        fclose($output);

        if (! $process->isSuccessful()) {
            @unlink($absolute);
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()));
        }

        $backup = Backup::create([
            'filename' => $filename,
            'disk' => 'local',
            'size' => filesize($absolute) ?: 0,
            'type' => $type,
            'created_by' => $userId,
        ]);

        ActivityLog::record('backup', "Created {$type} database backup {$filename}", $backup);

        return $backup;
    }

    /**
     * Restore the database from a stored backup file.
     */
    public function restore(Backup $backup): void
    {
        $config = config('database.connections.'.config('database.default'));
        $path = $this->path($backup);

        if (! is_file($path)) {
            throw new RuntimeException('Backup file not found on disk.');
        }

        $binary = $this->findBinary('mysql');

        $args = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.$config['username'],
            $config['database'],
        ];

        $env = [];
        if (! empty($config['password'])) {
            $env['MYSQL_PWD'] = $config['password'];
        }

        $input = fopen($path, 'r');
        $process = new Process($args, base_path(), $env + $this->baseEnv());
        $process->setTimeout(600);
        $process->setInput($input);
        $process->run();
        fclose($input);

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore failed: '.trim($process->getErrorOutput()));
        }

        ActivityLog::record('restore', "Restored database from backup {$backup->filename}", $backup);
    }

    public function delete(Backup $backup): void
    {
        Storage::disk($backup->disk)->delete($this->dir.'/'.$backup->filename);
        ActivityLog::record('backup', "Deleted backup {$backup->filename}");
        $backup->delete();
    }

    public function path(Backup $backup): string
    {
        return Storage::disk($backup->disk)->path($this->dir.'/'.$backup->filename);
    }

    protected function baseEnv(): array
    {
        // Ensure Homebrew / common install paths are searchable.
        $path = getenv('PATH') ?: '';
        $extra = '/opt/homebrew/bin:/opt/homebrew/opt/mysql-client/bin:/usr/local/bin:/usr/local/mysql/bin:/usr/bin';

        return ['PATH' => $path.PATH_SEPARATOR.$extra];
    }

    protected function findBinary(string $name): string
    {
        $candidates = [
            $name,
            '/opt/homebrew/bin/'.$name,
            '/opt/homebrew/opt/mysql-client/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/usr/local/mysql/bin/'.$name,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === $name || is_executable($candidate)) {
                return $candidate;
            }
        }

        return $name;
    }
}
