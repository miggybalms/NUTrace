<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The asset listing on the admin department page — and the same listing behind
 * the stickers printed from that page.
 *
 * The page and its label sheet go through this one builder and one set of
 * filters on purpose: whatever the filters selected is exactly what gets
 * printed, so the two can never disagree.
 */
class DepartmentAssets
{
    /** Status chip value => the value stored in assets.Lifecycle_Status. */
    public const STATUS_MAP = [
        'active'     => 'Active',
        'acquired'   => 'Acquired',
        'for_repair' => 'For Repair',
        'pulled_out' => 'Pullout',
        'disposed'   => 'Disposal',
    ];

    /**
     * Assets the institution still holds that are held by one department.
     *
     * The column list is the shape the department page renders plus the raw
     * acquisition date the sticker formats itself; nothing is loaded that the
     * page or the sticker does not show.
     */
    public static function baseQuery(int|string|null $departmentId): Builder
    {
        return Inventory::excludeRemoved(DB::table('assets'))
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            // The department the asset belongs to, not only the holder's. An
            // imported asset whose accountable person has no account yet has
            // no holder to read a department from, and must still appear on
            // its department's page and label sheet.
            ->where(function ($query) use ($departmentId) {
                $query->where('assets.department_id', $departmentId)
                    ->orWhere(function ($fallback) use ($departmentId) {
                        $fallback->whereNull('assets.department_id')
                            ->where('users.department_id', $departmentId);
                    });
            })
            ->select(
                'assets.id as db_id',
                'assets.Asset_code as id',
                'assets.Asset_name as name',
                'assets.Category as category',
                'assets.Category as category_code',
                'assets.Lifecycle_Status as status_raw',
                'assets.asset_location as location',
                'assets.accusion_date',
                'assets.accusion_date as date_acquired',
                'assets.serial_Number as serial_number',
                'assets.purchase_Price as purchase_price',
                'assets.warranty_months as warranty_months',
                'assets.Condition as condition',
                'assets.qr_code_path',
                // The payload this asset's sticker already carries, so a reprint
                // prints the same signed code the record holds instead of
                // minting a second one only this printout knows about.
                'assets.qr_payload',
                'employee_numbers.Full_Name as accountable'
            );
    }

    /**
     * Apply the page's filters to a query.
     *
     * Pure server-side filtering (no client-side hiding), so page and print
     * sheets stay in step and only the rows that are shown are ever loaded.
     *
     * @param  array{status?:string,category?:string,location?:string,search?:string,code?:string}  $filters
     */
    public static function applyFilters(Builder $query, array $filters): Builder
    {
        $status = (string) ($filters['status'] ?? 'all');

        if ($status !== '' && $status !== 'all' && isset(self::STATUS_MAP[$status])) {
            $query->where('assets.Lifecycle_Status', self::STATUS_MAP[$status]);
        }

        $category = (string) ($filters['category'] ?? 'all');

        if ($category !== '' && $category !== 'all') {
            $query->where('assets.Category', $category);
        }

        // The room / office an asset is registered in.
        $location = (string) ($filters['location'] ?? 'all');

        if ($location !== '' && $location !== 'all') {
            $query->where('assets.asset_location', $location);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('assets.Asset_name', 'like', "%{$search}%")
                    ->orWhere('assets.Asset_code', 'like', "%{$search}%")
                    ->orWhere('employee_numbers.Full_Name', 'like', "%{$search}%");
            });
        }

        // A single asset, used by the per-row label button.
        $code = trim((string) ($filters['code'] ?? ''));

        if ($code !== '') {
            $query->where('assets.Asset_code', $code);
        }

        return $query;
    }

    /** Categories present in this department, for the filter dropdown. */
    public static function categoryOptions(int|string|null $departmentId): Collection
    {
        return self::departmentColumn($departmentId, 'Category');
    }

    /** Locations (rooms / offices) present in this department. */
    public static function locationOptions(int|string|null $departmentId): Collection
    {
        return self::departmentColumn($departmentId, 'asset_location');
    }

    /** True when any filter is narrowing the listing. */
    public static function hasFilters(array $filters): bool
    {
        foreach (['category', 'location'] as $key) {
            if (($filters[$key] ?? 'all') !== 'all' && ($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        return ($filters['status'] ?? 'all') !== 'all'
            || trim((string) ($filters['search'] ?? '')) !== ''
            || trim((string) ($filters['code'] ?? '')) !== '';
    }

    /**
     * Plain-language summary of what a label sheet covers. Shown on screen only
     * (never printed), so the selections behind a printout are auditable.
     */
    public static function describeFilters(string $departmentName, array $filters, int $count): string
    {
        $parts = [$departmentName];
        $status = (string) ($filters['status'] ?? 'all');

        if ($status !== '' && $status !== 'all') {
            $parts[] = ucfirst(str_replace('_', ' ', $status));
        }

        foreach (['category' => 'Category', 'location' => 'Location'] as $key => $label) {
            $value = (string) ($filters[$key] ?? 'all');

            if ($value !== '' && $value !== 'all') {
                $parts[] = $label.': '.$value;
            }
        }

        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $parts[] = 'Search: “'.trim((string) $filters['search']).'”';
        }

        if (trim((string) ($filters['code'] ?? '')) !== '') {
            $parts[] = 'Asset '.trim((string) $filters['code']);
        }

        $parts[] = $count === 1 ? '1 label' : $count.' labels';

        return implode(' · ', $parts);
    }

    /**
     * Distinct, non-empty values of one column across a department's assets.
     */
    private static function departmentColumn(int|string|null $departmentId, string $column): Collection
    {
        return Inventory::excludeRemoved(DB::table('assets'))
            ->where(function ($query) use ($departmentId) {
                $query->where('assets.department_id', $departmentId)
                    ->orWhere(function ($fallback) use ($departmentId) {
                        $fallback->whereNull('assets.department_id')
                            ->whereIn('user_id', function ($q) use ($departmentId) {
                                $q->select('id')->from('users')->where('department_id', $departmentId);
                            });
                    });
            })
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->map(fn ($value) => ['value' => $value, 'label' => $value])
            ->values();
    }
}
