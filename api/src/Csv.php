<?php

declare(strict_types=1);

namespace JobTracker;

use JobTracker\Http\HttpException;

/**
 * CSV in the format spreadsheets expect (comma, UTF-8 with BOM so Excel shows Cyrillic),
 * so people can move their old tracking sheet in and out.
 */
final class Csv
{
    public const COLUMNS = ['company', 'position', 'status', 'url', 'salary_from', 'salary_to', 'currency', 'next_action_at', 'resume', 'notes'];

    /** @param list<array<string, scalar|null>> $rows */
    public static function export(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new \RuntimeException('Cannot open temp stream');
        }
        fwrite($out, "\u{FEFF}");
        fputcsv($out, self::COLUMNS, escape: '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn(string $c) => $row[$c] ?? '', self::COLUMNS), escape: '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * Parses a CSV with at least company and position columns (any order, extra columns ignored).
     *
     * @return list<ApplicationInput>
     */
    public static function import(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($csv, "\u{FEFF}")) ?: [];
        $header = str_getcsv((string) array_shift($lines), escape: '');
        $index = array_flip(array_map(static fn(?string $h) => strtolower(trim((string) $h)), $header));
        if (!isset($index['company'], $index['position'])) {
            throw HttpException::validation(['file' => 'Нужны колонки company и position']);
        }

        $inputs = [];
        foreach ($lines as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, escape: '');
            $data = [];
            foreach (self::COLUMNS as $column) {
                $value = isset($index[$column]) ? trim((string) ($cells[$index[$column]] ?? '')) : '';
                if ($value !== '') {
                    $data[$column] = in_array($column, ['salary_from', 'salary_to'], true) && ctype_digit($value) ? (int) $value : $value;
                }
            }
            try {
                $inputs[] = ApplicationInput::fromArray($data);
            } catch (HttpException $e) {
                throw HttpException::validation(['line ' . ($n + 2) => implode('; ', array_map(static fn($k, $v) => "$k: $v", array_keys($e->errors), $e->errors))]);
            }
        }

        return $inputs;
    }
}
