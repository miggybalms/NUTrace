<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * The period filter and the sort control on the Asset Management Office's
 * Requests page.
 *
 * The panel asked for "monthly filtering and sorting for request records". Both
 * mean the same thing everywhere the requests are read from, so they live here
 * instead of in the page: `/admin/requests` lists a page of them, and
 * `/admin/requests/export` writes all of them as CSV. If the export built its
 * own WHERE and ORDER BY (it used to), a filtered list would export rows the
 * page was not showing — the same bug the page's Export button once had.
 *
 * The filter is on when the request was *filed* (`requests.created_at`), not on
 * when its asset was acquired. "September's requests" means requests submitted
 * in September, whatever the age of the asset they name.
 *
 * Ranges are half-open — [start, end) — and are built in the application's
 * timezone (UTC, config/app.php). A half-open range needs no "23:59:59"
 * guesswork, so a request filed at 23:59:59.999 on the last day of the month is
 * inside it and one filed at 00:00:00 the next day is not.
 */
class RequestFilters
{
    /** Periods offered by the select, in the order they appear. */
    public const PERIODS = ['all', 'this_month', 'last_month', 'this_year', 'last_year', 'custom'];

    /**
     * Sort orders offered by the select.
     *
     * `pending` is the default and is the order the page has always used: the
     * requests still waiting on the office come first, longest wait first,
     * because those are the only rows carrying Approve / Reject. `newest` and
     * `oldest` order strictly by submission date — the person chose them, so
     * they get exactly that, even if a pending row ends up on a later page.
     */
    public const SORTS = ['pending', 'newest', 'oldest'];

    /**
     * Normalise the query string into the filter set the page and the export
     * both use. Anything unknown falls back to the default instead of erroring:
     * a hand-edited URL should show the list, not a 500.
     *
     * @param  array<string, mixed>  $query
     * @return array{period: string, from: ?Carbon, to: ?Carbon, sort: string}
     */
    public static function fromQuery(array $query): array
    {
        $period = strtolower(trim((string) ($query['period'] ?? 'all')));

        if (! in_array($period, self::PERIODS, true)) {
            $period = 'all';
        }

        $sort = strtolower(trim((string) ($query['sort'] ?? 'pending')));

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'pending';
        }

        $from = self::date($query['from'] ?? null);
        $to   = self::date($query['to'] ?? null);

        // Two dates the wrong way round is a slip, not a request for nothing:
        // read them as the range the person meant.
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'period' => $period,
            'from'   => $from,
            'to'     => $to,
            'sort'   => $sort,
        ];
    }

    /**
     * Apply the chosen period to a requests query.
     *
     * Only the period narrows the query; the status tab is the caller's job,
     * because it also decides which tab is highlighted.
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        [$start, $end] = self::bounds($filters);

        if ($start) {
            $query->where('requests.created_at', '>=', $start);
        }

        if ($end) {
            $query->where('requests.created_at', '<', $end);
        }

        return $query;
    }

    /**
     * Order the rows the way the sort control asks for.
     *
     * Every order ends on `requests.id` so that two requests filed in the same
     * second keep a stable position between pages — otherwise a row can appear
     * twice (or vanish) while someone pages through the list.
     */
    public static function order(Builder $query, array $filters): Builder
    {
        if ($filters['sort'] === 'newest') {
            return $query
                ->orderByDesc('requests.created_at')
                ->orderByDesc('requests.id');
        }

        if ($filters['sort'] === 'oldest') {
            return $query
                ->orderBy('requests.created_at')
                ->orderBy('requests.id');
        }

        return $query
            ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN 0 ELSE 1 END ASC', ['pending'])
            ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN requests.created_at END ASC', ['pending'])
            ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN requests.id END ASC', ['pending'])
            ->orderByDesc('requests.created_at')
            ->orderByDesc('requests.id');
    }

    /**
     * The half-open range the period describes, or [null, null] for all time.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function bounds(array $filters): array
    {
        $now = Carbon::now();

        return match ($filters['period']) {
            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->startOfMonth()->addMonth(),
            ],
            'last_month' => [
                $now->copy()->startOfMonth()->subMonthNoOverflow(),
                $now->copy()->startOfMonth(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->startOfYear()->addYear(),
            ],
            'last_year' => [
                $now->copy()->startOfYear()->subYearNoOverflow(),
                $now->copy()->startOfYear(),
            ],
            'custom' => [
                $filters['from']?->copy()->startOfDay(),
                $filters['to']?->copy()->startOfDay()->addDay(),
            ],
            default => [null, null],
        };
    }

    /** Is anything narrowing the list? */
    public static function active(array $filters): bool
    {
        return $filters['period'] !== 'all' || $filters['sort'] !== 'pending';
    }

    /**
     * How the chosen period is named on screen — "October 2026", "1–15 Sep
     * 2026", "2025" — or null for all time.
     */
    public static function periodLabel(array $filters): ?string
    {
        $now = Carbon::now();

        return match ($filters['period']) {
            'this_month' => $now->format('F Y'),
            'last_month' => $now->copy()->subMonthNoOverflow()->format('F Y'),
            'this_year'  => $now->format('Y'),
            'last_year'  => (string) ($now->year - 1),
            'custom'     => self::customLabel($filters['from'], $filters['to']),
            default      => null,
        };
    }

    /**
     * The query parameters that reproduce this view, defaults omitted so a
     * filtered link stays readable and an unfiltered one carries nothing.
     *
     * @return array<string, string>
     */
    public static function toQuery(array $filters): array
    {
        $query = [];

        if ($filters['period'] !== 'all') {
            $query['period'] = $filters['period'];
        }

        if ($filters['period'] === 'custom') {
            if ($filters['from']) {
                $query['from'] = $filters['from']->toDateString();
            }

            if ($filters['to']) {
                $query['to'] = $filters['to']->toDateString();
            }
        }

        if ($filters['sort'] !== 'pending') {
            $query['sort'] = $filters['sort'];
        }

        return $query;
    }

    private static function customLabel(?Carbon $from, ?Carbon $to): ?string
    {
        if ($from && $to) {
            // Same month: name it once ("1–15 September 2026").
            if ($from->isSameMonth($to)) {
                return $from->format('j') . '–' . $to->format('j F Y');
            }

            return $from->format('j M Y') . ' – ' . $to->format('j M Y');
        }

        if ($from) {
            return 'from ' . $from->format('j M Y');
        }

        if ($to) {
            return 'up to ' . $to->format('j M Y');
        }

        return null;
    }

    /** A `Y-m-d` value from the query string, or null when it is not one. */
    private static function date(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        // createFromFormat returns false (not null) for a date it cannot read,
        // such as 2026-02-31.
        return $date instanceof Carbon ? $date->startOfDay() : null;
    }
}
