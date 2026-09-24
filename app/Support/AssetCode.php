<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * How an asset gets its Asset Code.
 *
 * Auto mode keeps the original random generator (AST-XXXXXXXXXX). Custom mode
 * lets the Admin key in the code the office already keeps on its own records,
 * so the printed QR label matches the client's existing numbering.
 *
 * A custom code is only safe if it identifies exactly one asset, so every code
 * is checked against `assets.Asset_code` before it is accepted. The comparison
 * is case-insensitive: Postgres' UNIQUE index would happily store both
 * "NU-001" and "nu-001", and the scan endpoint already resolves codes with
 * LOWER(), so two assets would both answer to the same scan.
 */
class AssetCode
{
    /** Prefix of a generated code. */
    public const PREFIX = 'AST';

    /** `assets.Asset_code` is VARCHAR(50). */
    public const MAX_LENGTH = 50;

    /** A fresh random code, e.g. AST-K3F9ZA2Q1M. */
    public static function generate(): string
    {
        return self::PREFIX . '-' . strtoupper(Str::random(10));
    }

    /** Uppercase, trimmed, spaces collapsed to dashes. */
    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        $code = preg_replace('/\s+/', '-', $code) ?? '';

        return $code;
    }

    /**
     * Letters, numbers, dot, dash, slash and underscore, 2-50 characters.
     * The code is normalised first, so a caller never has to upper-case it.
     */
    public static function isValid(string $code): bool
    {
        $code = self::normalize($code);

        return (bool) preg_match('/^[A-Z0-9][A-Z0-9._\-\/]{1,' . (self::MAX_LENGTH - 1) . '}$/', $code);
    }

    /**
     * Read a textarea or comma-separated list into normalised codes,
     * preserving the order they were typed in.
     *
     * @return array<int, string>
     */
    public static function parseList(?string $text): array
    {
        $parts = preg_split('/[\r\n,;]+/', (string) $text) ?: [];
        $codes = [];

        foreach ($parts as $part) {
            $code = self::normalize($part);

            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Existing assets keyed by UPPER(codes), for the codes given.
     *
     * @param  array<int, string>  $codes
     * @return array<string, array{id: int, code: string, name: string|null}>
     */
    public static function taken(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => self::normalize($code),
            $codes
        ))));

        if (empty($codes)) {
            return [];
        }

        $rows = DB::table('assets')
            ->where(function ($query) use ($codes) {
                $query->whereIn('Asset_code', $codes)
                    ->orWhereIn(DB::raw('UPPER("Asset_code")'), $codes);
            })
            ->get(['id', 'Asset_code', 'Asset_name']);

        $taken = [];

        foreach ($rows as $row) {
            $taken[strtoupper((string) $row->Asset_code)] = [
                'id'   => (int) $row->id,
                'code' => (string) $row->Asset_code,
                'name' => $row->Asset_name,
            ];
        }

        return $taken;
    }

    /** Is this code already used by another asset? */
    public static function exists(string $code): bool
    {
        return self::taken([$code]) !== [];
    }

    /**
     * Availability report used by the live check on the registration form.
     *
     * @param  array<int, string>  $codes
     * @return array<int, array{code: string, valid: bool, exists: bool, asset: array|null}>
     */
    public static function availability(array $codes): array
    {
        $codes = self::parseList(implode("\n", $codes));
        $taken = self::taken($codes);
        $report = [];

        foreach ($codes as $code) {
            $report[] = [
                'code'   => $code,
                'valid'  => self::isValid($code),
                'exists' => isset($taken[$code]),
                'asset'  => $taken[$code] ?? null,
            ];
        }

        return $report;
    }

    /**
     * The first reason a batch of custom codes cannot be used, or null when the
     * batch is good: right number of codes, each well-formed, none repeated, and
     * none already taken by another asset.
     *
     * @param  array<int, string>  $codes
     */
    public static function firstProblem(array $codes, int $quantity): ?string
    {
        $codes = array_values(array_filter(array_map(
            fn ($code) => self::normalize($code),
            $codes
        ), fn ($code) => $code !== ''));

        if (empty($codes)) {
            return 'Enter the asset code exactly as it appears on the office records.';
        }

        if (count($codes) !== $quantity) {
            return 'A quantity of ' . $quantity . ' needs ' . $quantity . ' asset code(s), but '
                . count($codes) . ' ' . (count($codes) === 1 ? 'was' : 'were') . ' provided.';
        }

        $seen = [];

        foreach ($codes as $code) {
            if (! self::isValid($code)) {
                return '“' . $code . '” is not a usable asset code. Use letters, numbers, dot, dash, slash or underscore (2–' . self::MAX_LENGTH . ' characters).';
            }

            if (isset($seen[$code])) {
                return '“' . $code . '” is listed more than once — every asset needs its own code.';
            }

            $seen[$code] = true;
        }

        foreach (self::taken($codes) as $match) {
            return '“' . $match['code'] . '” is already used by ' . ($match['name'] ?: 'another asset')
                . '. Choose a different code so both assets keep a unique identification.';
        }

        return null;
    }
}
