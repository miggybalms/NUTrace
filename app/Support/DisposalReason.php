<?php

namespace App\Support;

/**
 * The reason recorded on a disposal record.
 *
 * A disposal request carries a single free-text note (requests.Note) — there is
 * no separate reason column on the request form. The reason therefore travels
 * inside the note, and this class reads it back out so the Admin never has to
 * re-type information the requester already submitted.
 */
class DisposalReason
{
    /** The values the disposals.disposal_reason column accepts. */
    public const VALUES = ['Beyond Repair', 'Replace', 'Obsolete', 'Lost', 'Damage'];

    /**
     * Most specific/serious first: "damaged beyond repair" must record as
     * Beyond Repair rather than Damage.
     */
    private const KEYWORDS = [
        'Beyond Repair' => [
            'beyond repair', 'cannot be repaired', "can't be repaired", 'not repairable',
            'irreparable', 'unrepairable', 'no longer repairable', 'totally damaged',
        ],
        'Lost' => [
            'lost', 'missing', 'cannot be found', "can't be found", 'stolen', 'theft',
        ],
        'Replace' => [
            'replace', 'replacement', 'upgrade', 'upgraded', 'newer model',
        ],
        'Damage' => [
            'damage', 'damaged', 'broken', 'cracked', 'faulty', 'defective', 'not working',
        ],
        'Obsolete' => [
            'obsolete', 'outdated', 'end of life', 'end of lifespan', 'end of its lifespan',
            'no longer needed', 'no longer used', 'unused', 'surplus',
        ],
    ];

    /**
     * Read the reason out of the requester's note.
     */
    public static function derive(?string $note): string
    {
        $haystack = strtolower(trim((string) $note));

        if ($haystack === '') {
            return 'Obsolete';
        }

        foreach (self::KEYWORDS as $reason => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $reason;
                }
            }
        }

        return 'Obsolete';
    }

    /**
     * Accept an explicit reason when it is one of the stored values, otherwise
     * fall back to the reason derived from the request note.
     */
    public static function normalize(?string $value, ?string $note = null): string
    {
        $value = trim((string) $value);

        foreach (self::VALUES as $candidate) {
            if (strcasecmp($candidate, $value) === 0) {
                return $candidate;
            }
        }

        return self::derive($note);
    }
}
