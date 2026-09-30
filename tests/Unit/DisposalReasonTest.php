<?php

namespace Tests\Unit;

use App\Support\DisposalReason;
use PHPUnit\Framework\TestCase;

/**
 * A disposal request only carries a free-text note, so the reason recorded on
 * the disposal record is read back out of it. These cases fix the reading so
 * the Admin's approval records the requester's actual reason instead of a
 * hard-coded default.
 */
class DisposalReasonTest extends TestCase
{
    public function test_every_derived_reason_is_one_the_column_accepts(): void
    {
        $notes = [
            'The unit is damaged beyond repair after the flood.',
            'This laptop was lost during the field trip.',
            'Please replace this with a newer model.',
            'The casing is broken and the screen is cracked.',
            'No longer needed by the department.',
            '',
            'nothing recognisable here',
        ];

        foreach ($notes as $note) {
            $this->assertContains(DisposalReason::derive($note), DisposalReason::VALUES);
        }
    }

    public function test_specific_reasons_win_over_general_ones(): void
    {
        // "damaged beyond repair" must not be recorded as plain Damage.
        $this->assertSame('Beyond Repair', DisposalReason::derive('It is damaged beyond repair.'));
        // A replacement request that mentions damage is still a replacement.
        $this->assertSame('Replace', DisposalReason::derive('Screen is broken, please replace it.'));
        $this->assertSame('Lost', DisposalReason::derive('The tablet is missing since last week.'));
    }

    public function test_an_explicit_reason_is_used_when_it_is_valid(): void
    {
        $this->assertSame('Damage', DisposalReason::normalize('Damage', 'lost forever'));
        $this->assertSame('Beyond Repair', DisposalReason::normalize('  beyond repair ', null));
    }

    public function test_an_unusable_reason_falls_back_to_the_note(): void
    {
        $this->assertSame('Lost', DisposalReason::normalize('something else', 'it went missing'));
        $this->assertSame('Obsolete', DisposalReason::normalize('', ''));
        $this->assertSame('Obsolete', DisposalReason::normalize(null, null));
    }
}
