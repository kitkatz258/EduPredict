<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Escapes CSV cells against spreadsheet formula injection and sanitizes download names.
 */
final class SafeCsv
{
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $value = Carbon::parse($value)
                ->timezone((string) config('app.timezone'))
                ->toDateTimeString();
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);
        if ($text !== '' && preg_match('/^[=+\-@\t\n|]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function row(array $values): array
    {
        return array_map(self::cell(...), $values);
    }

    public static function filename(string $stem, string $extension = 'csv'): string
    {
        $safe = strtolower((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $stem));
        $safe = trim($safe, '.-');
        if ($safe === '') {
            $safe = 'edupredict-report';
        }

        $extension = strtolower(ltrim($extension, '.'));
        if ($extension === '' || preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
            $extension = 'csv';
        }

        return $safe.'.'.$extension;
    }
}
