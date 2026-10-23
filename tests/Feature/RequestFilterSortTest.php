<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Monthly filtering and sorting for request records (panel recommendation #2).
 *
 * What has to hold:
 *
 *   1. A period narrows the list in SQL, before the page is cut, so a page of
 *      15 rows only ever holds requests filed inside the chosen month.
 *   2. Ranges are half-open: the 1st at 00:00:00 is inside, the 1st of the
 *      next month is not, and the last day at 23:59:59 is inside.
 *   3. The status tab and the period combine instead of replacing each other.
 *   4. Pending-first stays the default. "Newest" and "Oldest" are strict date
 *      orders — the person chose them, so a pending row may fall on a later
 *      page rather than being pinned.
 *   5. The CSV export covers exactly the rows the screen shows, filters and
 *      order included.
 *   6. Filters survive paging (withQueryString), and junk in the query string
 *      shows the list rather than failing.
 */
class RequestFilterSortTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private int $assetId;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed "now" so "this month" and "last month" mean something stable.
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00:00'));

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Facilities', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        DB::table('employee_numbers')->insert([
            ['id' => 1, 'Employee_number' => 'EMP-1', 'Full_Name' => 'Sir Alex', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'Employee_number' => 'EMP-2', 'Full_Name' => 'Aling Nena', 'Department_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => 1, 'department_id' => 1, 'email' => 'alex@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Admin', 'status' => 'Active',
        ]);

        $this->employee = User::create([
            'employee_numbers_id' => 2, 'department_id' => 1, 'email' => 'nena@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'), 'role' => 'Employee', 'status' => 'Active',
        ]);

        $this->assetId = (int) DB::table('assets')->insertGetId([
            'user_id' => $this->employee->id, 'Asset_code' => 'AST-0001', 'Asset_name' => 'Laptop',
            'Category' => 'Info and Equipment', 'Condition' => 'Good', 'Lifecycle_Status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A request plus the request_items row the forms always write. */
    private function makeRequest(string $status, string $createdAt, string $type = 'Repair'): int
    {
        $when = Carbon::parse($createdAt);

        $id = (int) DB::table('requests')->insertGetId([
            'user_id' => $this->employee->id, 'asset_id' => null, 'request_type' => $type,
            'status' => $status, 'Note' => 'Please look into this asset.',
            'created_at' => $when, 'updated_at' => $when,
        ]);

        DB::table('request_items')->insert([
            'request_id' => $id, 'asset_id' => $this->assetId,
            'created_at' => $when, 'updated_at' => $when,
        ]);

        return $id;
    }

    /** The request ids in the order the table renders them. */
    private function rowIds(string $html): array
    {
        preg_match_all('/data-request-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    private function page(string $query = ''): string
    {
        return $this->actingAs($this->admin)
            ->get('/admin/requests' . ($query !== '' ? '?' . $query : ''))
            ->assertOk()
            ->getContent();
    }

    /* ═══════════ 1. no filter: everything, in the familiar order ═══════════ */

    public function test_all_time_shows_every_request_in_pending_first_order(): void
    {
        $older = $this->makeRequest('Pending', '2026-09-20 09:00:00');
        $newer = $this->makeRequest('Pending', '2026-10-01 09:00:00');
        $approvedOld = $this->makeRequest('Approved', '2026-08-01 09:00:00');
        $approvedNew = $this->makeRequest('Approved', '2026-10-05 09:00:00');        $html = $this->page();

        $this->assertSame([$older, $newer, $approvedNew, $approvedOld], $this->rowIds($html));

        // The controls are on the page, and the custom dates stay out of the
        // way until "Custom range" is chosen.
        $this->assertStringContainsString('name="period"', $html);
        $this->assertStringContainsString('name="sort"', $html);
        $this->assertStringContainsString('id="request-custom-range"', $html);
        $this->assertMatchesRegularExpression('/id="request-custom-range"[^>]*\bhidden\b/', $html);
        $this->assertStringNotContainsString('Clear filters', $html);
    }

    public function test_a_period_filter_exposes_the_clear_link_and_keeps_the_tab(): void
    {
        $this->makeRequest('Pending', '2026-10-02 10:00:00');

        $html = $this->page('tab=pending&period=this_month&sort=oldest');

        $this->assertStringContainsString('Clear filters', $html);
        // Clear keeps the status tab and drops only the period and sort.
        $this->assertStringContainsString('tab=pending&amp;page=1', $html);
    }

    /* ═══════════ 2. month filters, half-open on both ends ═══════════ */

    public function test_this_month_shows_only_requests_filed_this_month(): void
    {
        $september = $this->makeRequest('Pending', '2026-09-30 23:59:59');
        $firstInstant = $this->makeRequest('Pending', '2026-10-01 00:00:00');
        $later = $this->makeRequest('Approved', '2026-10-22 16:30:00');

        $html = $this->page('period=this_month');

        $this->assertSame([$firstInstant, $later], $this->rowIds($html));
        $this->assertStringContainsString('in October 2026', $html);
        $this->assertStringNotContainsString('data-request-id="' . $september . '"', $html);
    }

    public function test_last_month_includes_both_ends_of_the_month(): void
    {
        $august = $this->makeRequest('Pending', '2026-08-31 23:59:59');
        $septemberFirst = $this->makeRequest('Pending', '2026-09-01 00:00:00');
        $septemberLast = $this->makeRequest('Approved', '2026-09-30 23:59:59');
        $october = $this->makeRequest('Pending', '2026-10-01 00:00:00');

        $html = $this->page('period=last_month');

        $this->assertSame([$septemberFirst, $septemberLast], $this->rowIds($html));
        $this->assertStringContainsString('in September 2026', $html);
        $this->assertStringNotContainsString('data-request-id="' . $august . '"', $html);
        $this->assertStringNotContainsString('data-request-id="' . $october . '"', $html);
    }

    public function test_a_custom_range_covers_the_whole_first_and_last_day(): void
    {
        $before = $this->makeRequest('Pending', '2026-09-01 00:00:00'); // earlier than the 2nd
        $early = $this->makeRequest('Pending', '2026-09-02 08:00:00');
        $late = $this->makeRequest('Approved', '2026-09-15 23:59:59');
        $after = $this->makeRequest('Pending', '2026-09-16 00:00:00');

        $html = $this->page('period=custom&from=2026-09-02&to=2026-09-15');

        $this->assertSame([$early, $late], $this->rowIds($html));
        $this->assertStringNotContainsString('data-request-id="' . $before . '"', $html);
        $this->assertStringNotContainsString('data-request-id="' . $after . '"', $html);
    }

    public function test_a_custom_range_given_backwards_is_read_as_the_range_meant(): void
    {
        $inside = $this->makeRequest('Pending', '2026-09-10 10:00:00');
        $outside = $this->makeRequest('Pending', '2026-10-10 10:00:00');

        $html = $this->page('period=custom&from=2026-09-15&to=2026-09-02');

        $this->assertSame([$inside], $this->rowIds($html));
        $this->assertStringNotContainsString('data-request-id="' . $outside . '"', $html);
    }

    /* ═══════════ 3. the status tab and period combine ═══════════ */

    public function test_the_status_tab_and_the_period_combine(): void
    {
        $pendingInMonth = $this->makeRequest('Pending', '2026-10-02 10:00:00');
        $approvedInMonth = $this->makeRequest('Approved', '2026-10-03 10:00:00');
        $pendingBefore = $this->makeRequest('Pending', '2026-09-03 10:00:00');

        $html = $this->page('tab=pending&period=this_month');

        $this->assertSame([$pendingInMonth], $this->rowIds($html));
        $this->assertStringNotContainsString('data-request-id="' . $approvedInMonth . '"', $html);
        $this->assertStringNotContainsString('data-request-id="' . $pendingBefore . '"', $html);
    }

    /* ═══════════ 4. sort orders ═══════════ */

    public function test_newest_first_is_a_strict_date_order(): void
    {
        $pendingOlder = $this->makeRequest('Pending', '2026-09-20 09:00:00');
        $pendingNewer = $this->makeRequest('Pending', '2026-10-01 09:00:00');
        $approvedOldest = $this->makeRequest('Approved', '2026-08-01 09:00:00');
        $approvedNewest = $this->makeRequest('Approved', '2026-10-05 09:00:00');

        $this->assertSame(
            [$approvedNewest, $pendingNewer, $pendingOlder, $approvedOldest],
            $this->rowIds($this->page('sort=newest'))
        );
    }

    public function test_oldest_first_puts_the_oldest_decision_above_the_newest_pending_request(): void
    {
        $pendingOlder = $this->makeRequest('Pending', '2026-09-20 09:00:00');
        $pendingNewer = $this->makeRequest('Pending', '2026-10-01 09:00:00');
        $approvedOldest = $this->makeRequest('Approved', '2026-08-01 09:00:00');
        $approvedNewest = $this->makeRequest('Approved', '2026-10-05 09:00:00');

        $this->assertSame(
            [$approvedOldest, $pendingOlder, $pendingNewer, $approvedNewest],
            $this->rowIds($this->page('sort=oldest'))
        );
    }

    public function test_requests_filed_in_the_same_second_keep_a_stable_position(): void
    {
        $first = $this->makeRequest('Pending', '2026-10-10 09:00:00');
        $second = $this->makeRequest('Pending', '2026-10-10 09:00:00');

        // Same second: newest-first shows the later row (higher id) first and
        // oldest-first the lower id, so neither order can swap rows between
        // pages.
        $this->assertSame([$second, $first], $this->rowIds($this->page('sort=newest')));
        $this->assertSame([$first, $second], $this->rowIds($this->page('sort=oldest')));
    }

    /* ═══════════ 5. the export matches the screen ═══════════ */

    public function test_the_csv_export_uses_the_same_period_and_sort(): void
    {
        $september = $this->makeRequest('Pending', '2026-09-15 09:00:00');
        $earlier = $this->makeRequest('Approved', '2026-10-02 09:00:00');
        $later = $this->makeRequest('Pending', '2026-10-20 09:00:00');

        $response = $this->actingAs($this->admin)
            ->get('/admin/requests/export?period=this_month&sort=newest')
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('#REQ-' . str_pad((string) $later, 4, '0', STR_PAD_LEFT), $csv);
        $this->assertStringContainsString('#REQ-' . str_pad((string) $earlier, 4, '0', STR_PAD_LEFT), $csv);
        $this->assertStringNotContainsString('#REQ-' . str_pad((string) $september, 4, '0', STR_PAD_LEFT), $csv);

        // Newest first: October 20 before October 2.
        $this->assertLessThan(
            strpos($csv, '#REQ-' . str_pad((string) $earlier, 4, '0', STR_PAD_LEFT)),
            strpos($csv, '#REQ-' . str_pad((string) $later, 4, '0', STR_PAD_LEFT))
        );
    }

    /* ═══════════ 6. paging and junk input ═══════════ */

    public function test_the_period_and_sort_survive_the_pagination_links(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->makeRequest('Approved', '2026-10-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . ' 09:00:00');
        }

        $html = $this->page('period=this_month&sort=newest');

        $this->assertSame(15, count($this->rowIds($html)));
        $this->assertStringContainsString('period=this_month', $html);
        $this->assertStringContainsString('sort=newest', $html);
        $this->assertStringContainsString('of 16 requests in October 2026', $html);
    }

    public function test_an_unknown_period_or_sort_falls_back_to_the_default(): void
    {
        $a = $this->makeRequest('Pending', '2026-09-01 09:00:00');
        $b = $this->makeRequest('Pending', '2026-10-01 09:00:00');

        $html = $this->page('period=nonsense&sort=sideways');

        // Both rows are listed, and neither a period label nor a filter prompt
        // appears: the page behaves as if no filter had been asked for.
        $this->assertSame([$a, $b], $this->rowIds($html));
        $this->assertStringNotContainsString('in October 2026', $html);
        $this->assertStringNotContainsString('Clear filters', $html);
    }

    public function test_an_invalid_date_does_not_break_the_list(): void
    {
        $this->makeRequest('Pending', '2026-10-01 09:00:00');

        $html = $this->page('period=custom&from=2026-02-31&to=not-a-date');

        $this->assertCount(1, $this->rowIds($html));
    }

    public function test_an_employee_cannot_open_the_office_requests_page(): void
    {
        $this->actingAs($this->employee)
            ->get('/admin/requests?period=this_month')
            ->assertForbidden();
    }
}
