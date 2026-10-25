<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\TransferController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The Transfer page's two lists are paged ten at a time, and the filters that
 * produced a page follow it: the department chips and the header search run over
 * every employee, not only the ten rows that happen to be on screen.
 */
class TransferPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Admin office', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
            ['id' => 2, 'Name' => 'IT Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
        ]);

        $employeeId = 1;

        // Two dozen people, alternating departments, so a page boundary falls
        // between departments and a filter has something to hide.
        for ($i = 1; $i <= 24; $i++) {
            $name = sprintf('Employee %02d', $i);
            $department = $i % 2 === 0 ? 2 : 1;

            DB::table('employee_numbers')->insert([
                'id' => $employeeId,
                'Employee_number' => 'EMP-' . $employeeId,
                'Full_Name' => $name,
                'Department_id' => $department,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            User::create([
                'employee_numbers_id' => $employeeId,
                'department_id' => $department,
                'email' => 'employee' . $i . '@nu-lipa.edu.ph',
                'password' => Hash::make('secret-password'),
                'role' => 'Employee',
                'status' => 'Active',
            ]);

            $employeeId++;
        }

        // One name that sorts last, so it can only be found by searching the
        // whole list rather than the first page.
        DB::table('employee_numbers')->insert([
            'id' => $employeeId, 'Employee_number' => 'EMP-Z', 'Full_Name' => 'Zulu Tester',
            'Department_id' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->admin = User::create([
            'employee_numbers_id' => $employeeId,
            'department_id' => 1,
            'email' => 'amo@nu-lipa.edu.ph',
            'password' => Hash::make('secret-password'),
            'role' => 'Admin',
            'status' => 'Active',
        ]);
    }

    /** A completed transfer row, which is all the history list reads. */
    private function transfer(int $toUserId, string $note = 'Resignation'): void
    {
        DB::table('requests')->insert([
            'user_id'            => $toUserId,
            'asset_id'           => null,
            'request_type'       => 'Transfer',
            'status'             => 'Approved',
            'Note'               => $note,
            'assign_to_user_id'  => $this->admin->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function test_the_employee_list_pages_ten_at_a_time(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/transfer')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $employees */
        $employees = $page->viewData('employees');

        $this->assertSame(TransferController::PER_PAGE, $employees->perPage());
        $this->assertSame(10, TransferController::PER_PAGE);
        $this->assertSame(DB::table('users')->count(), $employees->total());
        $this->assertCount(10, $employees->items());
        $this->assertStringContainsString('employees_page=2', $employees->url(2));

        $second = $this->actingAs($this->admin)->get('/admin/transfer?employees_page=2')->assertOk();
        $secondPage = $second->viewData('employees');

        $this->assertSame($employees->total(), $secondPage->total());
        $this->assertCount(10, $secondPage->items());
        $this->assertNotSame(
            $employees->items()[0]['id'],
            $secondPage->items()[0]['id'],
            'page two must show the people page one did not'
        );

        // The page says where it is, so a pager is never a mystery.
        $page->assertSee('Showing 1–10 of ' . $employees->total() . ' employees', false);

        // The modals still receive everybody: an employee on page three can
        // still be opened from the Assets modal.
        $page->assertSee('Zulu Tester', false);
    }

    public function test_the_department_filter_covers_every_page(): void
    {
        $itCount = DB::table('users')->where('department_id', 2)->count();

        $page = $this->actingAs($this->admin)->get('/admin/transfer?department=IT%20Department')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $employees */
        $employees = $page->viewData('employees');

        $this->assertSame($itCount, $employees->total(), 'the filter counts the whole list, not the page');
        $this->assertStringContainsString('department=IT', urldecode($employees->url(2)));

        $second = $this->actingAs($this->admin)
            ->get('/admin/transfer?department=IT%20Department&employees_page=2')
            ->assertOk();

        foreach ($second->viewData('employees')->items() as $row) {
            $this->assertSame('IT Department', $row['department'], 'page two must not leak another department');
        }
    }

    public function test_the_search_finds_somebody_who_is_on_a_later_page(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/transfer?q=zulu')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $employees */
        $employees = $page->viewData('employees');

        $this->assertSame(1, $employees->total(), 'the search runs over the whole directory');
        $this->assertSame('Zulu Tester', $employees->items()[0]['name']);
        $this->assertStringContainsString('q=zulu', $employees->url(2));
    }

    public function test_the_transfer_history_pages_ten_at_a_time(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->transfer((int) DB::table('users')->value('id'), 'Reason ' . $i);
        }

        $page = $this->actingAs($this->admin)->get('/admin/transfer?tab=history')->assertOk();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $transfers */
        $transfers = $page->viewData('transfers');

        $this->assertSame(12, $transfers->total());
        $this->assertSame(TransferController::PER_PAGE, $transfers->perPage());
        $this->assertCount(10, $transfers->items());
        $this->assertStringContainsString('history_page=2', $transfers->url(2));

        $second = $this->actingAs($this->admin)->get('/admin/transfer?tab=history&history_page=2')->assertOk();

        $this->assertCount(2, $second->viewData('transfers')->items());
    }

    public function test_the_stats_still_describe_the_whole_page_not_the_visible_rows(): void
    {
        // Twelve transfers, ten of them on the first page: the card above the
        // table must still say twelve.
        for ($i = 0; $i < 12; $i++) {
            $this->transfer((int) DB::table('users')->value('id'), 'Reason ' . $i);
        }

        $page = $this->actingAs($this->admin)->get('/admin/transfer')->assertOk();

        $this->assertSame(12, $page->viewData('stats')['transfers_completed']);
        $this->assertCount(10, $page->viewData('transfers')->items());
    }
}
