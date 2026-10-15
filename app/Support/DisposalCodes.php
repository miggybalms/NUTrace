<?php

namespace App\Support;

/**
 * The codes printed on the official NU Lipa Disposal Form.
 *
 * The legend lives here, in the order and wording the supplied form uses, so
 * the printed document, the preparation form and the validation all read from
 * one list. Nothing here renames or "fixes" an official label: the two
 * ambiguities the form itself carries are recorded as data instead.
 *
 *  - The method-of-disposal code T is used TWICE on the form, for Tender/sold
 *    and for Trade-in. A single letter cannot tell the two rows apart, so a
 *    method is stored as a key ('tender-sold' / 'trade-in') plus the letter the
 *    form actually prints. The letter is never silently changed.
 *
 *  - The condition/state words (P, F, G) and the reason phrases (B, O, R, S, T)
 *    are presented on the form as one code list. Both columns therefore accept
 *    any code from that list, and CONDITION_CODES only tells the preparation
 *    screen which of them describe condition. A client decision on whether
 *    "Condition Code" should be limited to P/F/G is recorded in the summary
 *    rather than assumed here.
 */
class DisposalCodes
{
    /**
     * The form's code list, exactly as supplied: code => label.
     *
     * Order is the form's own order.
     */
    public const REASON_CODES = [
        'P' => 'Poor',
        'F' => 'Fair',
        'G' => 'Good',
        'B' => 'Beyond economic repair',
        'O' => 'Obsolete',
        'R' => 'Replaced by upgrade',
        'S' => 'Surplus to requirement',
        'T' => 'Theft',
    ];

    /**
     * The codes that describe an item's condition. Shown as guidance on the
     * preparation form; not enforced, because the official legend is one list.
     */
    public const CONDITION_CODES = ['P', 'F', 'G'];

    /**
     * Method of disposal: key => [letter printed on the form, official label].
     *
     * The keys exist only to tell the two T rows apart; both record the letter
     * T, which is what the paper form says.
     */
    public const METHOD_CODES = [
        'donated'     => ['code' => 'D', 'label' => 'Donated'],
        'tender-sold' => ['code' => 'T', 'label' => 'Tender/sold'],
        'scrap'       => ['code' => 'S', 'label' => 'Scrap'],
        'trade-in'    => ['code' => 'T', 'label' => 'Trade-in'],
    ];

    /** The methods that mean the item was sold, so the sold-item block applies. */
    public const SOLD_METHODS = ['tender-sold'];

    /** Every code the form's legend accepts, in form order. */
    public static function legend(): array
    {
        return array_keys(self::REASON_CODES);
    }

    public static function isCode(?string $code): bool
    {
        return in_array(strtoupper(trim((string) $code)), self::legend(), true);
    }

    /** "P — Poor" (or just the code when it is not on the legend). */
    public static function label(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return '';
        }

        return isset(self::REASON_CODES[$code])
            ? $code . ' — ' . self::REASON_CODES[$code]
            : $code;
    }

    public static function isMethod(?string $key): bool
    {
        return array_key_exists(trim((string) $key), self::METHOD_CODES);
    }

    /** The letter the form prints for a method key. */
    public static function methodCode(?string $key): ?string
    {
        $key = trim((string) $key);

        return self::METHOD_CODES[$key]['code'] ?? null;
    }

    /** The official label for a method key. */
    public static function methodLabel(?string $key): ?string
    {
        $key = trim((string) $key);

        return self::METHOD_CODES[$key]['label'] ?? null;
    }

    /** "T — Tender/sold" as it reads on the form. */
    public static function methodText(?string $key): string
    {
        $code  = self::methodCode($key);
        $label = self::methodLabel($key);

        if ($code === null) {
            return '';
        }

        return $code . ' — ' . $label;
    }

    /** Does this method mean the item was sold? */
    public static function isSoldMethod(?string $key): bool
    {
        return in_array(trim((string) $key), self::SOLD_METHODS, true);
    }

    /**
     * An asset's recorded Condition, as a code from the form's legend — or null
     * when the recorded condition has no code on the form.
     *
     * assets.Condition allows New / Excellent / Good / Fair / Existing; the
     * legend only names Poor, Fair and Good. Anything else is left for the
     * Asset Management Office to complete rather than guessed at.
     */
    public static function conditionCodeFor(?string $condition): ?string
    {
        $condition = strtolower(trim((string) $condition));

        foreach (self::REASON_CODES as $code => $label) {
            if (! in_array($code, self::CONDITION_CODES, true)) {
                continue;
            }

            if (strtolower($label) === $condition) {
                return $code;
            }
        }

        return null;
    }

    /**
     * The disposal reason recorded on a request (Support\DisposalReason values)
     * as a code from the form's legend.
     *
     * Only the codes that mean the same thing are mapped. 'Lost' and 'Damage'
     * have no matching row on the form, so they are left blank for the office
     * to complete. Mapping 'Damage' onto "Beyond economic repair" or 'Lost' onto
     * "Theft" would be inventing a decision the requester did not make.
     */
    public static function reasonCodeFor(?string $reason): ?string
    {
        return match (trim((string) $reason)) {
            'Beyond Repair' => 'B',
            'Obsolete'      => 'O',
            'Replace'       => 'R',
            default         => null,
        };
    }

    /**
     * The reverse: a legend code as a value disposals.disposal_reason accepts
     * (Beyond Repair / Replace / Obsolete / Lost / Damage).
     *
     * "Surplus to requirement" and the condition codes have no column value of
     * their own, so they record as Obsolete — the value the rest of the system
     * already uses for surplus and end-of-life items. Theft records as Lost,
     * which is how an unrecoverable item has always been recorded here.
     */
    public static function storedReasonFor(?string $code, ?string $fallback = null): string
    {
        $mapped = match (strtoupper(trim((string) $code))) {
            'B' => 'Beyond Repair',
            'O', 'S', 'P', 'F', 'G' => 'Obsolete',
            'R' => 'Replace',
            'T' => 'Lost',
            default => null,
        };

        return $mapped ?? DisposalReason::normalize($fallback);
    }
}
