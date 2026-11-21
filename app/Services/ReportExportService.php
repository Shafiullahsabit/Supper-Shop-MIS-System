<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function download(string $filename, array $headers, iterable $rows, string $format): StreamedResponse
    {
        if (! in_array($format, ['csv', 'xls'], true)) {
            abort(404);
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                echo "\xEF\xBB\xBF";
                $output = fopen('php://output', 'w');

                if ($output === false) {
                    throw new RuntimeException('Unable to open the report output stream.');
                }

                fputcsv($output, $headers, ',', '"', '');

                foreach ($rows as $row) {
                    fputcsv($output, array_map([$this, 'csvValue'], $row), ',', '"', '');
                }

                fclose($output);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($headers, $rows): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="Report"><Table>';
            $this->writeExcelRow($headers);

            foreach ($rows as $row) {
                $this->writeExcelRow($row);
            }

            echo '</Table></Worksheet></Workbook>';
        }, $filename.'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function csvValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && preg_match('/^[\t\r\n ]*[=+\-@]/u', $value)) {
            return "'".$value;
        }

        return $value ?? '';
    }

    private function writeExcelRow(array $values): void
    {
        echo '<Row>';

        foreach ($values as $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $type = is_int($value) || is_float($value) ? 'Number' : 'String';
            $content = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            echo '<Cell><Data ss:Type="'.$type.'">'
                .$content
                .'</Data></Cell>';
        }

        echo '</Row>';
    }
}
