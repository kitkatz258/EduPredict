<?php

declare(strict_types=1);

namespace App\Services\Profile;

final class SkillsListParser
{
    /**
     * @return list<string>
     */
    public function strings(string $text): array
    {
        $lines = [];
        foreach ($this->lines($text) as $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  list<mixed>  $rows
     */
    public function stringsToText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            if (is_string($row) && trim($row) !== '') {
                $lines[] = trim($row);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{name: string, detail: string}>
     */
    public function pairs(string $text): array
    {
        $pairs = [];
        foreach ($this->lines($text) as $line) {
            $parts = array_map(trim(...), explode('|', $line, 2));
            $name = $parts[0] ?? '';
            if ($name === '') {
                continue;
            }
            $pairs[] = [
                'name' => $name,
                'detail' => $parts[1] ?? '',
            ];
        }

        return $pairs;
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $nameKeys
     * @param  list<string>  $detailKeys
     */
    public function pairsToText(array $rows, array $nameKeys, array $detailKeys): string
    {
        $lines = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $this->firstValue($row, $nameKeys);
            $detail = $this->firstValue($row, $detailKeys);
            if ($name === '' && $detail === '') {
                continue;
            }
            $lines[] = $detail === '' ? $name : $name.' | '.$detail;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $rows = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $lines = [];
        foreach ($rows as $row) {
            $row = trim($row);
            if ($row !== '') {
                $lines[] = $row;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function firstValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }
}
