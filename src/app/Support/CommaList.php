<?php

declare(strict_types=1);

namespace App\Support;

class CommaList
{
    /**
     * @return list<string>
     */
    public static function parse(string $value): array
    {
        $parts = preg_split('/[\n,]+/', $value) ?: [];
        $clean = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param  list<string>|null  $values
     */
    public static function display(?array $values): string
    {
        return implode(', ', $values ?? []);
    }
}
