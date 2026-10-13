<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The filter tabs, the search box and the paging on the employee and
 * department-head asset pages.
 *
 * Both pages used to load every asset the holder had and then filter the cards
 * in JavaScript, which only ever looked at the cards already in the browser.
 * The filtering now happens in the database and the listing is paged 10 at a
 * time, so a tab or a search covers the holder's whole set of assets instead of
 * a single page of it.
 *
 * The tab values are the ones these pages have always put in their URL
 * (?filter=...&q=...), so existing links and bookmarks keep working.
 */
class AssetListing
{
    /** Assets per page. */
    public const PER_PAGE = 10;

    /** "Recently Added" means assigned/registered within the last 7 days. */
    public const RECENT_DAYS = 7;

    /** URL value of the "Recently Added" tab. */
    public const RECENT_FILTER = 'recent';

    /** URL value of the department head's "My Personal Assets" tab. */
    public const PERSONAL_FILTER = 'department';

    /**
     * The statuses these pages list. Pullout and Disposal assets are no longer
     * held by the employee or the department — the Asset Management Office keeps
     * those records for audit and reporting.
     */
    public static function visibleStatuses(): array
    {
        return AssetDetails::USER_VISIBLE_STATUSES;
    }

    /**
     * Assets the institution still holds and the holder is still shown.
     *
     * A record with no lifecycle status has always been displayed as Acquired,
     * so it stays visible here too.
     */
    public static function onlyVisible(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('assets.Lifecycle_Status', self::visibleStatuses())
                ->orWhereNull('assets.Lifecycle_Status');
        });
    }

    /**
     * Apply one tab and the search box to a listing.
     *
     * @param  string  $filter  'all' or one of the tab values
     * @param  string  $search  what was typed in the search box
     * @param  int|null  $viewerId  who is looking, for the "My Personal Assets" tab
     */
    public static function applyFilters(Builder $query, string $filter, string $search, ?int $viewerId = null): Builder
    {
        if (in_array($filter, self::visibleStatuses(), true)) {
            $query->where('assets.Lifecycle_Status', $filter);
        } elseif ($filter === self::RECENT_FILTER) {
            $cutoff = now()->subDays(self::RECENT_DAYS)->startOfDay();

            // The date the asset landed on this account, falling back to when the
            // record was registered — the rule the page has always used.
            $query->where(function ($q) use ($cutoff) {
                $q->where('assets.accusion_date', '>=', $cutoff->toDateString())
                    ->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('assets.accusion_date')
                            ->where('assets.created_at', '>=', $cutoff);
                    });
            });
        } elseif ($filter === self::PERSONAL_FILTER && $viewerId) {
            $query->where('assets.user_id', $viewerId);
        }

        if ($search !== '') {
            // The fields the card shows, so anything visible on a card is
            // findable from the search box.
            $query->where(function ($q) use ($search) {
                $q->where('assets.Asset_name', 'like', "%{$search}%")
                    ->orWhere('assets.Asset_code', 'like', "%{$search}%")
                    ->orWhere('assets.Category', 'like', "%{$search}%")
                    ->orWhere('assets.asset_location', 'like', "%{$search}%")
                    ->orWhere('assets.Lifecycle_Status', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /** Apply the filters and hand back one page of the result. */
    public static function paginate(Builder $query, string $filter, string $search, ?int $viewerId = null): LengthAwarePaginator
    {
        return self::applyFilters($query, $filter, $search, $viewerId)
            ->orderBy('assets.Asset_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * The tabs, in the order the pages show them.
     *
     * @param  bool  $withPersonal  the department head page also has "My Personal Assets"
     * @return array<string, string>
     */
    public static function tabs(bool $withPersonal = false): array
    {
        $tabs = [
            'all'               => 'All Assets',
            'Active'            => 'Active',
            'For Checking'      => 'For Checking',
            'For Repair'        => 'For Repair',
            'For Replacement'   => 'For Replacement',
            self::RECENT_FILTER => 'Recently Added',
        ];

        if ($withPersonal) {
            $tabs[self::PERSONAL_FILTER] = 'My Personal Assets';
        }

        return $tabs;
    }

    /** Is this tab the one being shown? */
    public static function isActive(string $filter, ?string $current): bool
    {
        $current = $current === null || $current === '' ? 'all' : $current;

        return $current === $filter;
    }

    /**
     * Link for one tab: the same page, keeping the search box and dropping the
     * page number, so switching tabs never lands on an empty page 3.
     */
    public static function tabUrl(string $baseUrl, string $filter, string $search): string
    {
        $params = [];

        if ($filter !== '' && $filter !== 'all') {
            $params['filter'] = $filter;
        }

        if (trim($search) !== '') {
            $params['q'] = $search;
        }

        return $baseUrl.($params !== [] ? '?'.http_build_query($params) : '');
    }

    /** Is anything narrowing the listing? */
    public static function hasFilters(?string $filter, ?string $search): bool
    {
        $filter = $filter === null || $filter === '' ? 'all' : $filter;

        return $filter !== 'all' || trim((string) $search) !== '';
    }
}
