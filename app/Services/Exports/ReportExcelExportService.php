<?php

namespace App\Services\Exports;

use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExcelExportService
{
    public function download(
        iterable $rows,
        array $columns,
        array $filters = [],
        string $filename = 'report.xls',
        ?string $title = null
    ): StreamedResponse {
        $columns = $this->normalizeColumns($columns);
        $filename = $this->normalizeFilename($filename);

        return response()->streamDownload(function () use ($rows, $columns, $filters, $title) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';

            if ($title) {
                echo '<table><tr><th colspan="' . count($columns) . '">' . $this->escape($title) . '</th></tr></table>';
            }

            if (!empty($filters)) {
                echo '<table>';
                echo '<tr><th colspan="2">Ֆիլտրեր</th></tr>';

                foreach ($filters as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    echo '<tr>';
                    echo '<td>' . $this->escape((string) $key) . '</td>';
                    echo '<td>' . $this->escape($this->stringValue($value)) . '</td>';
                    echo '</tr>';
                }

                echo '</table><br>';
            }

            echo '<table border="1">';
            echo '<thead><tr>';

            foreach ($columns as $column) {
                echo '<th>' . $this->escape($column['title']) . '</th>';
            }

            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';

                foreach ($columns as $column) {
                    echo '<td>' . $this->escape($this->columnValue($row, $column)) . '</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    protected function normalizeColumns(array $columns): array
    {
        return collect($columns)
            ->map(function (array $column) {
                return [
                    'key' => $column['key'] ?? null,
                    'title' => (string) ($column['title'] ?? $column['label'] ?? $column['key'] ?? ''),
                    'value' => $column['value'] ?? null,
                ];
            })
            ->filter(fn (array $column) => $column['title'] !== '' && ($column['key'] || is_callable($column['value'])))
            ->values()
            ->all();
    }

    protected function columnValue(mixed $row, array $column): string
    {
        if (is_callable($column['value'])) {
            return $this->stringValue($column['value']($row));
        }

        if (is_array($row)) {
            return $this->stringValue(Arr::get($row, $column['key']));
        }

        return $this->stringValue(data_get($row, $column['key']));
    }

    protected function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || $value instanceof \JsonSerializable) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function normalizeFilename(string $filename): string
    {
        $filename = trim($filename) ?: 'report.xls';

        if (!str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        return $filename;
    }
}
