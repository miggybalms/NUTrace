<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assets that have reached the proposed service-life mark, offered for review.
 *
 * This class only reads. It never creates a disposal request, never approves
 * one, and never writes an asset's lifecycle status: reaching the age mark is a
 * prompt for the Asset Management Office to look at the asset, not a decision
 * that it should be disposed of.
 *
 * The mark is the acquisition date plus the number of years in
 * config/disposal.php. That figure is proposed policy, not a confirmed
 * university rule, which is why it lives in configuration and why this screen
 * is labelled as advisory throughout.
 */
class AssetAgeReview
{
    /**
     * The column acquisition dates are actually stored in.
     *
     * assets.accusion_date is the schema's spelling (see the create_assets
     * migration); using the real column means no asset is silently left out
     * because a differently-spelled field looked right.
     */
    public const DATE_COLUMN = 'accusion_date';

    /** Lifecycle values that mean the asset has left service already. */
    public const RETIRED_STATUSES = ['Disposal', 'Pullout'];

    /** Is the review list switched on? */
    public static function enabled(): bool
    {
        return (bool) config('disposal.age_review.enabled', true);
    }

    /** The proposed age mark, in whole years (never below one). */
    public static function years(): int
    {
        return max(1, (int) config('disposal.age_review.years', 3));
    }

    /** "3 years" — how the screen names the mark. */
    public static function thresholdLabel(): string
    {
        $years = self::years();

        return $years . ' ' . ($years === 1 ? 'year' : 'years');
    }

    /** The first acquisition date that counts as having reached the mark. */
    public static function cutoff(): Carbon
    {
        return now()->startOfDay()->subYears(self::years());
    }

    /**
     * The assets to review, oldest first.
     *
     * Excludes assets already retired (Disposal / Pullout), assets removed from
     * the inventory, and assets with no acquisition date — an age cannot be
     * calculated from a date that was never recorded, and guessing one would
     * put a fiction in front of the office.
     *
     * Assets that already have a pending or approved disposal request are still
     * listed, marked as such: the office needs to see that the review is
     * already in hand, not have the row silently disappear.
     *
     * @return Collection<int, object>
     */
    public static function candidates(?string $search = null): Collection
    {
        $query = DB::table('assets')
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->whereNotNull('assets.' . self::DATE_COLUMN)
            ->where('assets.' . self::DATE_COLUMN, '<=', self::cutoff()->toDateString())
            ->whereNotIn('assets.Lifecycle_Status', self::RETIRED_STATUSES)
            ->orderBy('assets.' . self::DATE_COLUMN)
            ->orderBy('assets.Asset_code');

        if (Inventory::columnsReady()) {
            $query->whereNull('assets.' . Inventory::REMOVED_AT);
        }

        $search = trim((string) $search);

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($q) use ($like) {
                $q->where('assets.Asset_code', 'like', $like)
                    ->orWhere('assets.Asset_name', 'like', $like)
                    ->orWhere('assets.Category', 'like', $like)
                    ->orWhere('employee_numbers.Full_Name', 'like', $like)
                    ->orWhere('departments.Name', 'like', $like);
            });
        }

        $rows = $query->get([
            'assets.id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Category',
            'assets.Condition',
            'assets.Lifecycle_Status',
            'assets.' . self::DATE_COLUMN . ' as date_acquired',
            'assets.asset_location',
            'assets.purchase_Price',
            DB::raw('employee_numbers."Full_Name" as custodian_name'),
            'departments.Name as department',
            // Already asked about? Counted, not filtered: the office should see
            // that a review is in hand rather than wonder where the asset went.
            DB::raw('(SELECT COUNT(*)
                FROM request_items ri
                JOIN requests r ON r.id = ri.request_id
                WHERE (ri.asset_id = assets.id OR r.asset_id = assets.id)
                  AND LOWER(r.request_type) = \'disposal\'
                  AND LOWER(r.status) IN (\'pending\', \'approved\')) as open_disposal_requests'),
        ]);

        return $rows->map(function ($row) {
            $row->age = self::ageOf($row->date_acquired);
            $row->age_months = $row->age['months'];
            $row->age_label = $row->age['label'];
            $row->custodian = trim((string) ($row->custodian_name ?? '')) ?: 'Unassigned';
            $row->has_open_disposal = (int) ($row->open_disposal_requests ?? 0) > 0;

            return $row;
        });
    }

    /**
     * How old an asset is, and how to say it.
     *
     * @return array{months: int, label: string}
     */
    public static function ageOf(?string $date): array
    {
        if (! $date) {
            return ['months' => 0, 'label' => 'Unknown'];
        }

        try {
            $acquired = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return ['months' => 0, 'label' => 'Unknown'];
        }

        $months = max(0, (int) $acquired->diffInMonths(now()->startOfDay()));
        $years  = intdiv($months, 12);
        $rest   = $months % 12;

        $parts = [];

        if ($years > 0) {
            $parts[] = $years . ' ' . ($years === 1 ? 'yr' : 'yrs');
        }

        if ($rest > 0 || $years === 0) {
            $parts[] = $rest . ' ' . ($rest === 1 ? 'mo' : 'mos');
        }

        return ['months' => $months, 'label' => implode(' ', $parts)];
    }

    /**
     * The counts the review tab's header cards show.
     *
     * @param  Collection<int, object>  $rows
     * @return array{total: int, oldest: ?object, already_requested: int, departments: int}
     */
    public static function summary(Collection $rows): array
    {
        return [
            'total'             => $rows->count(),
            'oldest'            => $rows->sortByDesc('age_months')->first(),
            'already_requested' => $rows->where('has_open_disposal', true)->count(),
            'departments'       => $rows->pluck('department')->filter()->unique()->count(),
        ];
    }

    /** Whether the age columns this class reads exist in this database. */
    public static function ready(): bool
    {
        return Schema::hasTable('assets')
            && Schema::hasColumn('assets', self::DATE_COLUMN);
    }
}
