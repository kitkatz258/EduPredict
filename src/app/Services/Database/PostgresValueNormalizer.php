<?php

declare(strict_types=1);

namespace App\Services\Database;

final class PostgresValueNormalizer
{
    public function normalize(mixed $value, string $dataType, bool $nullable): mixed
    {
        $type = strtolower($dataType);

        if ($value === null) {
            return null;
        }

        if (is_string($value) && in_array($value, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            if (! $nullable) {
                throw new PostgresCopyRejected('A required column contains a zero date, which PostgreSQL rejects.');
            }

            return null;
        }

        if ($type === 'boolean') {
            return $this->boolean($value);
        }

        if (in_array($type, ['json', 'jsonb'], true)) {
            return $this->json($value);
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (int) $value === 1;
        }

        throw new PostgresCopyRejected('Unexpected boolean value of type '.get_debug_type($value).'.');
    }

    private function json(mixed $value): string
    {
        try {
            if (is_string($value)) {
                json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                return $value;
            }

            if (is_array($value)) {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
        } catch (\JsonException $exception) {
            throw new PostgresCopyRejected('A JSON column contains invalid JSON.', previous: $exception);
        }

        throw new PostgresCopyRejected('Unexpected JSON value of type '.get_debug_type($value).'.');
    }
}
