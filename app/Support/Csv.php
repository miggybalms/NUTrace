<?php

namespace App\Support;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reading and writing CSV files, the same way every time.
 *
 * Two details matter more than the parsing itself:
 *
 *   1. Every exported value is guarded against spreadsheet formula injection.
 *      A value such as `=HYPERLINK("http://evil","click")` is a formula to
 *      Excel, Sheets and LibreOffice the moment the file opens, so the text is
 *      prefixed with an apostrophe and stays text.
 *   2. A quoted field containing commas, quotes or newlines survives the round
 *      trip. fputcsv/fgetcsv handle that; nothing here ever builds a CSV row
 *      by hand with commas.
 *
 * Reading is deliberately strict: the header row decides the column order, so
 * the office can send the columns in any order (and with the headings their own
 * spreadsheet uses), and the first row of data is never mistaken for headers.
 */
class Csv
{
    /** A masterlist of a few hundred employees is a few kilobytes; 2 MB is generous. */
    public const MAX_BYTES = 2097152;

    /** Rows beyond this are refused rather than silently truncated. */
    public const MAX_ROWS = 2000;

    /** Characters Excel, Sheets and LibreOffice treat as the start of a formula. */
    private const FORMULA_STARTERS = ['=', '+', '-', '@', "\t", "\r"];

    /* ─────────────────────────── writing ─────────────────────────── */

    /**
     * Prefix a value that a spreadsheet would otherwise run as a formula.
     *
     * Only the guard character is added - what the office typed is untouched,
     * and the apostrophe is not shown by the spreadsheet.
     */
    public static function guard(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return in_array($value[0], self::FORMULA_STARTERS, true) ? "'" . $value : $value;
    }

    /**
     * Stream a CSV download.
     *
     * Streamed rather than assembled in memory so a long export cannot be cut
     * off by a memory limit, and every value passes through guard().
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return Response::stream(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel shows the accented names correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, array_map(
                    static fn ($value) => self::guard((string) $value),
                    array_values($row)
                ));
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /* ─────────────────────────── reading ─────────────────────────── */

    /**
     * Read a CSV file into [line number => [heading => value]].
     *
     * @return array{headers: string[], rows: array<int, array{line: int, values: array<string, string}>}
     */
    public static function read(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            return ['headers' => [], 'rows' => []];
        }

        // A BOM at the start of the file would otherwise become part of the
        // first heading ("\xEF\xBB\xBFEmployee Number"), and every required
        // column would then look missing.
        $first = fgets($handle);
        if ($first !== false) {
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            rewind($handle);
        }

        $headers = [];
        $rows    = [];
        $line    = 0;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;

            // Blank rows (a trailing newline, a stray blank line) carry nothing.
            if ($cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
                continue;
            }

            if ($headers === []) {
                $headers = array_map(
                    static fn ($cell) => trim((string) $cell),
                    $cells
                );
                continue;
            }

            $values = [];
            foreach ($headers as $index => $heading) {
                $values[$heading] = trim((string) ($cells[$index] ?? ''));
            }

            $rows[] = ['line' => $line, 'values' => $values];
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }
}
