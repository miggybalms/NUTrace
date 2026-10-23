<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssetLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The print settings on the label sheet (web only).
 *
 * The panel's recommendation asks for sticker dimensions, code size, page
 * layout and asset selection. Asset selection already comes from the page
 * filters and the codes a sheet is opened with; this file covers the other three:
 *
 *   1. the sheet starts from the configured stock when nothing has been chosen
 *   2. an office choice applies to the page that is printed and survives to the
 *      next printout in the same browser
 *   3. a size that would print an unreadable code is raised to the scannable
 *      minimum and the office is told what changed - never silently printed
 *   4. only the Asset Management Office may change it, and one account's choice
 *      does not change anyone else's
 *   5. resetting goes back to the configured stock
 */
class AssetLabelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('departments')->insert([
            ['id' => 1, 'Name' => 'Demo Department', 'status' => 'Active', 'Create_at' => now(), 'Update_at' => now()],
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

        DB::table('assets')->insert([
            'user_id'          => $this->employee->id,
            'Asset_code'       => 'AST-0001',
            'Asset_name'       => 'Demo asset',
            'Category'         => 'General and Office Equipment',
            'Condition'        => 'Good',
            'Lifecycle_Status' => 'Active',
            'asset_location'   => 'Faculty Room',
            'accusion_date'    => '2026-10-08',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** The sheet's CSS carries the sizes that will actually be printed. */
    private function sheet(?string $query = 'codes=AST-0001'): string
    {
        return $this->actingAs($this->admin)
            ->get('/admin/assets/labels?'.$query)
            ->assertOk()
            ->getContent();
    }

    private function saveSettings(array $values, string $returnTo = '/admin/assets/labels?codes=AST-0001')
    {
        return $this->actingAs($this->admin)->post('/admin/assets/labels/settings', $values + [
            'return_to' => $returnTo,
        ]);
    }

    public function test_the_sheet_starts_from_the_configured_label_stock(): void
    {
        $html = $this->sheet();

        $this->assertStringContainsString('--label-w: 50mm;', $html);
        $this->assertStringContainsString('--label-h: 25mm;', $html);
        $this->assertStringContainsString('--qr-size: 21mm;', $html);

        // The panel is offered, collapsed, with the stock values in its fields.
        $this->assertStringContainsString('Print settings — sticker size, QR size and layout', $html);
        $this->assertStringContainsString('name="width_mm"', $html);
        $this->assertStringContainsString('name="qr_mm"', $html);
    }

    public function test_the_office_choice_is_printed_and_kept_for_the_next_sheet(): void
    {
        $this->saveSettings([
            'width_mm'  => '70',
            'height_mm' => '30',
            'qr_mm'     => '25',
            'layout'    => 'sheet',
        ])
            ->assertRedirect('/admin/assets/labels?codes=AST-0001&layout=sheet')
            ->assertSessionHas('label_settings.ok', true)
            ->assertSessionHas('label_settings.warnings', []);

        $html = $this->sheet('codes=AST-0001');

        $this->assertStringContainsString('--label-w: 70mm;', $html);
        $this->assertStringContainsString('--label-h: 30mm;', $html);
        $this->assertStringContainsString('--qr-size: 25mm;', $html);
        $this->assertStringContainsString('Print settings saved for this browser: 70 × 30 mm sticker, 25 mm QR', $html);
    }

    public function test_a_size_that_cannot_scan_is_raised_and_explained_instead_of_printed(): void
    {
        $this->saveSettings([
            'width_mm'  => '10',
            'height_mm' => '10',
            'qr_mm'     => '5',
        ])->assertRedirect('/admin/assets/labels?codes=AST-0001');

        $html = $this->sheet();

        // 17mm is the smallest square that still gives 2.5 dots per module at 203
        // dpi; the sticker is raised around it rather than clipping the code.
        $this->assertStringContainsString('--qr-size: 17mm;', $html);
        $this->assertStringContainsString('--label-w: 31mm;', $html);
        $this->assertStringContainsString('--label-h: 19.5mm;', $html);

        $this->assertStringContainsString('Sticker width raised to 31 mm', $html);
        $this->assertStringContainsString('Sticker height raised to 19.5 mm', $html);
        $this->assertStringContainsString('QR size raised to 17 mm', $html);
    }

    public function test_a_qr_too_large_for_the_sticker_is_lowered_but_stays_scannable(): void
    {
        $this->saveSettings([
            'width_mm'  => '50',
            'height_mm' => '25',
            'qr_mm'     => '60',
        ])->assertRedirect();

        $html = $this->sheet();

        // The height leaves 25 - 2 (inset) - 0.5 = 22.5mm; the width leaves 36mm.
        $this->assertStringContainsString('--qr-size: 22.5mm;', $html);
        $this->assertStringContainsString('QR size lowered to 22.5 mm', $html);
    }

    public function test_only_the_asset_management_office_can_change_print_settings(): void
    {
        $this->actingAs($this->employee)
            ->post('/admin/assets/labels/settings', [
                'width_mm'  => '90',
                'height_mm' => '40',
                'qr_mm'     => '30',
                'return_to' => '/admin/assets/labels?codes=AST-0001',
            ])
            ->assertForbidden();

        // The label sheet is part of the admin area, so an employee cannot open
        // it at all, let alone change what it prints.
        $this->actingAs($this->employee)
            ->get('/admin/assets/labels?codes=AST-0001')
            ->assertForbidden();

        // And the attempt changed nothing for the office either.
        $html = $this->sheet();

        $this->assertStringContainsString('--label-w: 50mm;', $html);
        $this->assertStringContainsString('--qr-size: 21mm;', $html);
    }

    public function test_resetting_returns_to_the_configured_stock(): void
    {
        $this->saveSettings(['width_mm' => '70', 'height_mm' => '30', 'qr_mm' => '25'])->assertRedirect();

        $this->actingAs($this->admin)
            ->post('/admin/assets/labels/settings', [
                'reset'     => '1',
                'return_to' => '/admin/assets/labels?codes=AST-0001',
            ])
            ->assertRedirect('/admin/assets/labels?codes=AST-0001')
            ->assertSessionHas('label_settings.message', 'Print settings reset to the configured label stock.');

        $html = $this->sheet();

        $this->assertStringContainsString('--label-w: 50mm;', $html);
        $this->assertStringContainsString('--qr-size: 21mm;', $html);
    }

    public function test_a_foreign_return_url_is_not_followed(): void
    {
        $this->saveSettings([
            'width_mm'  => '60',
            'height_mm' => '30',
            'qr_mm'     => '24',
            'return_to' => 'https://example.com/steal',
        ])->assertRedirect('/admin/assets/registry');
    }
}
