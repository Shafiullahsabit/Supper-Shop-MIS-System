<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'supershop:backup {--type=scheduled}';

    protected $description = 'Create a database backup of the SuperShop system';

    public function handle(BackupService $service): int
    {
        $this->info('Starting database backup...');

        try {
            $backup = $service->create($this->option('type'));
            $this->info("Backup created: {$backup->filename} ({$backup->readable_size})");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
