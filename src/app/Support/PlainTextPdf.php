<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small text-only PDF so a data export does not need another package.
 */
class PlainTextPdf
{
    public static function render(string $title, string $body): string
    {
        $lines = self::wrap($title);
        $lines[] = '';
        foreach (self::wrap($body) as $line) {
            $lines[] = $line;
        }

        $pages = array_chunk($lines, 46);
        if ($pages === []) {
            $pages = [['(empty)']];
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pageIds = [];
        $next = 4;
        foreach ($pages as $pageLines) {
            $contentId = $next + 1;
            $pageIds[] = $next;
            $stream = self::stream($pageLines);
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentId} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
            $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $next += 2;
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[1] = '<< /Type /Pages /Count '.count($pageIds)." /Kids [ {$kids} ] >>";

        return self::assemble($objects);
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);
            $ascii = is_string($ascii) ? $ascii : $line;
            $ascii = preg_replace('/[^\x20-\x7E]/', '?', $ascii) ?? '';
            if ($ascii === '') {
                $lines[] = '';

                continue;
            }
            foreach (str_split($ascii, 90) as $chunk) {
                $lines[] = $chunk;
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function stream(array $lines): string
    {
        $commands = ["BT", "/F1 10 Tf", "48 760 Td", "14 TL"];
        foreach ($lines as $index => $line) {
            $text = self::escape($line);
            $commands[] = $index === 0 ? "({$text}) Tj" : "({$text}) '";
        }
        $commands[] = 'ET';

        return implode("\n", $commands);
    }

    private static function escape(string $line): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
    }

    /**
     * @param  list<string>  $objects
     */
    private static function assemble(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
