<?php

namespace App\Services\Ai;

final class Deidentifier
{
    /**
     * @var list<string>
     */
    private array $blockedKeys = [
        'name', 'first_name', 'last_name', 'email', 'student_number', 'birthdate',
        'birthday', 'phone', 'address', 'ip', 'ip_address',
    ];

    /**
     * Keep only lines that look like subject rows. Names, student numbers, emails, and headers are dropped.
     */
    public function gradeRowLines(string $text): string
    {
        $kept = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($this->containsPii($line)) {
                continue;
            }
            if (! preg_match('/[A-Z]{2,8}\s?\d{1,3}[A-Z]?/', strtoupper($line))) {
                continue;
            }
            if (preg_match('/\b(student\s*no|student number|name|email|birthdate)\b/i', $line)) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function scrub(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $this->blockedKeys, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->scrub($value);
            } elseif (is_string($value) && $this->containsPii($value)) {
                continue;
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    public function containsPii(string $value): bool
    {
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value)) {
            return true;
        }
        if (preg_match('/\b(?:20\d{2}-\d{5,}|\bSYN-\d+)\b/i', $value)) {
            return true;
        }
        if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $value)) {
            return true;
        }

        return false;
    }
}
