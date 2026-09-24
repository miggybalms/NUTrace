<?php

namespace App\Support;

/**
 * Keeps personal network details out of the audit trail.
 *
 * Audit rows used to record the address someone signed in from ("User logged in
 * from IP: 127.0.0.1"). Admins only need to know that an account signed in or
 * signed out, never from where, so every audit string that reaches an admin
 * screen is passed through redact() first. That covers the rows already stored
 * in the database as well as anything written earlier.
 */
class AuditLogText
{
    /**
     * An address sitting right after "from" or "IP:", where it is certainly an
     * address. Requires a dot or a colon so ordinary words are never eaten.
     */
    private const CONTEXT_ADDRESS = '[\da-f]*[.:][\da-f:.]*';

    /**
     * 127.0.0.1 | ::1 | ::ffff:127.0.0.1 | 2001:db8:85a3::8a2e:370:7334
     * Complete forms only, so this is safe to look for anywhere in a line.
     * Full IPv6 needs at least three colons, leaving clock times such as
     * 12:34:56 alone.
     */
    private const BARE_ADDRESS = '(?:\d{1,3}(?:\.\d{1,3}){3}|::1|::ffff:\d{1,3}(?:\.\d{1,3}){3}|(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{0,4}|[0-9a-f]{0,4}::[0-9a-f:]{1,})';

    /**
     * Remove any address a log line still carries, tidying the wording it
     * leaves behind ("User logged in from IP: 1.2.3.4" -> "User logged in").
     */
    public static function redact(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        $clean = $text;

        // "from IP: 1.2.3.4", "from IP address 1.2.3.4", "from 1.2.3.4"
        $clean = preg_replace('/\s*\bfrom\s+(?:\bip(?:\s*address)?\s*:?\s*)?' . self::CONTEXT_ADDRESS . '/i', '', $clean) ?? $clean;

        // "(IP: 1.2.3.4)" or "IP 1.2.3.4" anywhere else in the line
        $clean = preg_replace('/[\(\[]?\s*\bip(?:\s*address)?\s*:?\s*' . self::CONTEXT_ADDRESS . '[\)\]]?/i', '', $clean) ?? $clean;

        // Any address that is still in there
        $clean = preg_replace('/\b' . self::BARE_ADDRESS . '\b/', '', $clean) ?? $clean;

        // Tidy the spacing and punctuation the removals left behind
        $clean = trim((string) preg_replace('/\s{2,}/', ' ', str_replace([' ,', ' .'], [',', '.'], $clean)));

        if ($clean === '') {
            return null;
        }

        $clean = rtrim($clean, " \t\n\r\0\x0B,.;:-");

        return $clean === '' ? null : $clean;
    }
}
