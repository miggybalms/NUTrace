<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

/**
 * Reports: one engine behind every report the office and its department heads
 * read.
 *
 * Two roles read these reports and they read different amounts of the same
 * thing. The Asset Management Office reads the whole university (and may narrow
 * it to one department); a Department Head reads their own department and
 * nothing else. Both go through the queries in this class, so a report can
 * never be filtered one way on screen and another way in the CSV: the page, the
 * summary cards, the breakdowns, the export and the print sheet are all built
 * from one definition per category.
 *
 * What the schema can and cannot answer is written down rather than smoothed
 * over. Every category carries its notes — which date it filters on, what one
 * row is, what could not be shown and why — and the page prints them beside the
 * numbers. Nothing here invents a status, a date, a department or a quantity
 * the database does not record.
 *
 * Two things this engine deliberately does NOT do:
 *
 *   - it never counts a physical unit. `assets` stores one row per asset and no
 *     quantity column, so every total here is a count of records, labelled as
 *     such. A row count is not a number of machines;
 *   - it never treats a workflow status as an event. "Approved" on a disposal
 *     request does not mean the asset was disposed of, and a transfer request
 *     has no handover date until the handover actually happened.
 */
class Reports
{
    /* ═════════════════════════════ catalogue ═════════════════════════ */

    public const CATEGORY_INVENTORY    = 'inventory';
    public const CATEGORY_ACQUISITIONS = 'acquisitions';
    public const CATEGORY_REPAIRS      = 'repairs';
    public const CATEGORY_TRANSFERS    = 'transfers';
    public const CATEGORY_DISPOSALS    = 'disposals';
    public const CATEGORY_REQUESTS     = 'requests';

    /** Rows per page on screen. The export and the print sheet are never capped. */
    public const PER_PAGE = 25;

    /**
     * Current asset lifecycle statuses — exactly what the column's CHECK allows.
     *
     * @see database/migrations/2026_05_13_000000_add_for_checking_and_maintenance_interval.php
     */
    public const ASSET_STATUSES = [
        'Acquired', 'Active', 'For Checking', 'For Repair', 'For Replacement', 'Pullout', 'Disposal',
    ];

    /** Repair workflow statuses — the repairs table's own enum. */
    public const REPAIR_STATUSES = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    /** Request workflow statuses — the requests table's own enum. */
    public const REQUEST_STATUSES = ['Pending', 'Approved', 'Rejected'];

    /** Request types — the requests table's own enum. */
    public const REQUEST_TYPES = ['Repair', 'Disposal', 'Transfer', 'Replacement', 'Pullout', 'Other'];

    /**
     * How a disposal record stands today: still on the Disposal page, or moved
     * to Archived Disposal Assets (which is also the moment its asset leaves the
     * inventory). Both are recorded facts, not derived ones.
     */
    public const DISPOSAL_STATES = ['Recorded', 'Archived'];

    /* ───────────────────────────── presets ───────────────────────────── */

    public const PRESET_ALL        = 'all';
    public const PRESET_THIS_MONTH = 'this_month';
    public const PRESET_LAST_MONTH = 'last_month';
    public const PRESET_THIS_YEAR  = 'this_year';
    public const PRESET_LAST_YEAR  = 'last_year';
    public const PRESET_CUSTOM     = 'custom';

    public const PRESETS = [
        self::PRESET_ALL,
        self::PRESET_THIS_MONTH,
        self::PRESET_LAST_MONTH,
        self::PRESET_THIS_YEAR,
        self::PRESET_LAST_YEAR,
        self::PRESET_CUSTOM,
    ];

    public const PRESET_LABELS = [
        self::PRESET_ALL        => 'All dates',
        self::PRESET_THIS_MONTH => 'This Month',
        self::PRESET_LAST_MONTH => 'Last Month',
        self::PRESET_THIS_YEAR  => 'This Year',
        self::PRESET_LAST_YEAR  => 'Last Year',
        self::PRESET_CUSTOM     => 'Custom Date Range',
    ];

    /** Where the last successfully generated report is remembered. */
    private const SESSION_KEY = 'reports.last_success';

    /**
     * Every report category: what it shows and what it filters on.
     *
     * Admin and Department Head are offered the same list — the difference
     * between them is the records inside each report, never the list itself.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogue(): array
    {
        return [
            self::CATEGORY_INVENTORY => [
                'label'          => 'Current Asset Inventory',
                'singular'       => 'asset',
                'icon'           => 'ri-archive-2-line',
                'description'    => 'What the institution holds right now, and who is accountable for it.',
                'event'          => false,
                'date_label'     => 'Date acquired',
                'date_note'      => 'Inventory is a snapshot of the records as they stand when the report is generated. The acquisition-date filter is optional and is not the report\'s reporting period.',
                'status_label'   => 'Current asset status',
                'status_options' => self::options(self::ASSET_STATUSES),
                'type_filter'    => false,
                'undated_note'   => 'An asset with no recorded acquisition date is listed after the dated ones and cannot appear in a date-filtered result.',
            ],
            self::CATEGORY_ACQUISITIONS => [
                'label'          => 'Asset Acquisitions',
                'singular'       => 'asset',
                'icon'           => 'ri-add-circle-line',
                'description'    => 'Assets by the acquisition date recorded on the asset record.',
                'event'          => true,
                'date_label'     => 'Date acquired',
                'date_note'      => 'Filtered on the recorded acquisition date (assets.accusion_date).',
                'status_label'   => 'Current asset status',
                'status_options' => self::options(self::ASSET_STATUSES),
                'type_filter'    => false,
                'undated_note'   => 'An asset with no recorded acquisition date is listed after the dated ones and is left out of a date-filtered result.',
            ],
            self::CATEGORY_REPAIRS => [
                'label'          => 'Repairs and Maintenance',
                'singular'       => 'repair record',
                'icon'           => 'ri-tools-line',
                'description'    => 'Repair records with their own workflow status and recorded cost.',
                'event'          => true,
                'date_label'     => 'Repair date',
                'date_note'      => 'Filtered on the repair date recorded on the record (repairs.Repair_Date).',
                'status_label'   => 'Repair status',
                'status_options' => self::options(self::REPAIR_STATUSES),
                'type_filter'    => false,
                'undated_note'   => 'A repair date is required on every record, so this report has no undated rows.',
            ],
            self::CATEGORY_TRANSFERS => [
                'label'          => 'Asset Transfers',
                'singular'       => 'transfer line',
                'icon'           => 'ri-swap-line',
                'description'    => 'Each asset handed from one accountable employee to another.',
                'event'          => true,
                'date_label'     => 'Transfer date',
                'date_note'      => 'Filtered on the date the handover was carried out, read from the asset\'s own transfer history entry — not from the date the request was submitted.',
                'status_label'   => 'Request status',
                'status_options' => self::options(self::REQUEST_STATUSES),
                'type_filter'    => false,
                'undated_note'   => 'A transfer that has not been carried out has no transfer date: those rows are listed after the dated ones and are left out of a date-filtered result.',
            ],
            self::CATEGORY_DISPOSALS => [
                'label'          => 'Asset Disposals',
                'singular'       => 'disposal record',
                'icon'           => 'ri-delete-bin-line',
                'description'    => 'Assets with a recorded disposal, and what became of the record.',
                'event'          => true,
                'date_label'     => 'Disposal date',
                'date_note'      => 'Filtered on the disposal date recorded on the record (disposals.disposal_date).',
                'status_label'   => 'Disposal record state',
                'status_options' => self::options(self::DISPOSAL_STATES),
                'type_filter'    => false,
                'undated_note'   => 'A disposal date is recorded with every disposal record, so this report has no undated rows.',
            ],
            self::CATEGORY_REQUESTS => [
                'label'          => 'Requests and Workflow Statuses',
                'singular'       => 'request',
                'icon'           => 'ri-mail-line',
                'description'    => 'Every request with the workflow status it carries, and what followed it.',
                'event'          => true,
                'date_label'     => 'Date submitted',
                'date_note'      => 'Filtered on the date the request was submitted (requests.created_at). A decision date is not stored separately.',
                'status_label'   => 'Request status',
                'status_options' => self::options(self::REQUEST_STATUSES),
                'type_filter'    => true,
                'undated_note'   => 'Every request carries its submission date, so this report has no undated rows.',
            ],
        ];
    }

    /** A status: value => label, for the filter select. */
    private static function options(array $values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = $value;
        }

        return $options;
    }

    /** The category keys, in the order the page lists them. */
    public static function categoryKeys(): array
    {
        return array_keys(self::catalogue());
    }

    public static function isValidCategory(?string $category): bool
    {
        return $category !== null && array_key_exists($category, self::catalogue());
    }

    /** One category's metadata; the first category when the key is unknown. */
    public static function category(?string $category): array
    {
        $catalogue = self::catalogue();

        return $catalogue[$category] ?? $catalogue[self::CATEGORY_INVENTORY];
    }

    /** A category's description for the catalogue strip on the page. */
    public static function catalogueCards(): array
    {
        $cards = [];

        foreach (self::catalogue() as $key => $meta) {
            $cards[] = [
                'key'         => $key,
                'label'       => $meta['label'],
                'icon'        => $meta['icon'],
                'description' => $meta['description'],
                'event'       => $meta['event'],
            ];
        }

        return $cards;
    }

    /* ═════════════════════════ who may read reports ═════════════════════════ */

    /**
     * Who may read reports at all: the Asset Management Office and Department
     * Heads.
     *
     * An employee holds no reporting authority. The retired Facilities role
     * never gains any either — it is not Admin, so it is refused here exactly
     * like an employee account, and nothing in this feature can hand it back.
     */
    public static function canReadReports(?User $viewer): bool
    {
        return Access::isAssetOffice($viewer) || Access::isDepartmentHead($viewer);
    }

    /**
     * The department this viewer's reports are fixed to, or null for the
     * university-wide view.
     *
     * Read from the account, never from the request: a Department Head cannot
     * widen their own scope by editing a query string, and cannot reach another
     * department by changing a filter.
     */
    public static function scopeDepartmentId(?User $viewer): ?int
    {
        if (! Access::isDepartmentHead($viewer)) {
            return null;
        }

        $id = (int) ($viewer->department_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * A Department Head whose account records no department can be told so
     * plainly instead of being shown an empty page that looks like a bug.
     */
    public static function scopeProblem(?User $viewer): ?string
    {
        if (! Access::isDepartmentHead($viewer) || self::scopeDepartmentId($viewer) !== null) {
            return null;
        }

        return 'Your account has no department recorded, so no department records can be listed. '
            . 'Ask the Asset Management Office to set your department on your account, then open this page again.';
    }

    public static function departmentName(?int $departmentId): ?string
    {
        if (! $departmentId) {
            return null;
        }

        return DB::table('departments')->where('id', $departmentId)->value('Name');
    }

    /** Every department, for the Admin's department filter. */
    public static function departmentOptions(): array
    {
        return DB::table('departments')
            ->orderBy('Name')
            ->get(['id', 'Name'])
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => (string) $row->Name])
            ->all();
    }

    /* ═════════════════════════ filters ═════════════════════════ */

    /**
     * Read the filters out of an incoming request.
     *
     * Everything the user can send is validated against the category's own
     * vocabulary, and the department is taken from the viewer — an Admin's
     * deliberate choice, a Department Head's account — rather than trusted from
     * the query string.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function filtersFrom(array $input, ?User $viewer): array
    {
        $category = trim((string) ($input['category'] ?? self::CATEGORY_INVENTORY));

        if (! self::isValidCategory($category)) {
            $category = self::CATEGORY_INVENTORY;
        }

        $meta = self::category($category);

        // Anything a reader sent that cannot apply to the report they chose is
        // refused rather than quietly reinterpreted — and the page says which,
        // so a filter never appears to have been honoured when it was not.
        $dropped = [];

        $preset = trim((string) ($input['preset'] ?? self::PRESET_ALL));

        if (! in_array($preset, self::PRESETS, true)) {
            $preset = self::PRESET_ALL;
        }

        [$from, $to, $preset] = self::range(
            $preset,
            trim((string) ($input['from'] ?? '')),
            trim((string) ($input['to'] ?? ''))
        );

        $rawStatus = trim((string) ($input['status'] ?? 'all'));
        $status    = $rawStatus;

        if ($status !== 'all' && ! array_key_exists($status, $meta['status_options'])) {
            $status = 'all';

            if ($rawStatus !== '' && $rawStatus !== 'all') {
                $dropped[] = 'The status “' . $rawStatus . '” does not apply to the ' . $meta['label']
                    . ' report — ' . strtolower($meta['status_label']) . ' is what it records — so it was not used, and every recorded status is shown.';
            }
        }

        $rawType = trim((string) ($input['type'] ?? 'all'));
        $type    = $rawType;

        if (! $meta['type_filter'] || ($type !== 'all' && ! in_array($type, self::REQUEST_TYPES, true))) {
            $type = 'all';

            if ($meta['type_filter'] === false && $rawType !== '' && $rawType !== 'all') {
                $dropped[] = 'The request type “' . $rawType . '” only applies to the Requests report, so it was not used here.';
            }
        }

        $lockedId = self::scopeDepartmentId($viewer);

        $department      = $lockedId;
        $departmentLabel = $lockedId ? (self::departmentName($lockedId) ?? ('Department #' . $lockedId)) : null;

        // An Admin may narrow the whole university to one department. The value
        // is checked against the departments table, so a number that names
        // nothing filters nothing rather than emptying the report.
        if ($lockedId === null && Access::isAssetOffice($viewer)) {
            $raw = trim((string) ($input['department'] ?? 'all'));

            if (ctype_digit($raw) && (int) $raw > 0) {
                if (DB::table('departments')->where('id', (int) $raw)->exists()) {
                    $department      = (int) $raw;
                    $departmentLabel = self::departmentName((int) $raw) ?? ('Department #' . $raw);
                } else {
                    $dropped[] = 'No department matches #' . $raw . ', so every department is included instead.';
                }
            }
        }

        return [
            'dropped'          => $dropped,
            'category'         => $category,
            'preset'           => $preset,
            'from'             => $from,
            'to'               => $to,
            'from_date'        => $from?->toDateString(),
            'to_date'          => $to?->toDateString(),
            'status'           => $status,
            'type'             => $type,
            'department'       => $department,
            'department_label' => $departmentLabel,
            'locked'           => $lockedId !== null,
        ];
    }

    /**
     * Turn a preset and two custom dates into a real range.
     *
     * The boundaries are the whole first and last day, in the application's own
     * timezone, so "This Month" means the month the office is working in rather
     * than a month shifted by a server clock set to UTC.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string}
     */
    private static function range(string $preset, string $fromInput, string $toInput): array
    {
        $now = Carbon::now(config('app.timezone'));

        switch ($preset) {
            case self::PRESET_THIS_MONTH:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $preset];

            case self::PRESET_LAST_MONTH:
                $month = $now->copy()->subMonthNoOverflow();

                return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $preset];

            case self::PRESET_THIS_YEAR:
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $preset];

            case self::PRESET_LAST_YEAR:
                $year = $now->copy()->subYear();

                return [$year->copy()->startOfYear(), $year->copy()->endOfYear(), $preset];

            case self::PRESET_CUSTOM:
                $from = self::parseDate($fromInput);
                $to   = self::parseDate($toInput);

                // A range typed backwards is read the way it was meant, rather
                // than returning nothing at all.
                if ($from && $to && $from->greaterThan($to)) {
                    [$from, $to] = [$to, $from];
                }

                // "Custom" with no dates at all is not a filter: it means all.
                if (! $from && ! $to) {
                    return [null, null, self::PRESET_ALL];
                }

                return [$from, $to, self::PRESET_CUSTOM];

            default:
                return [null, null, self::PRESET_ALL];
        }
    }

    private static function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Has a date filter been applied at all? */
    public static function hasDateFilter(array $filters): bool
    {
        return $filters['from'] !== null || $filters['to'] !== null;
    }

    /**
     * The applied criteria in words — shown on the page, printed on the report
     * and carried into the export's file name.
     *
     * @return array<string, string>
     */
    public static function describe(array $filters): array
    {
        $meta = self::category($filters['category']);

        $lines = [
            'Report'     => $meta['label'],
            'Dates'      => self::rangeLabel($filters),
            'Date field' => $meta['date_label'],
            'Status'     => $filters['status'] === 'all'
                ? 'All recorded statuses'
                : $filters['status'],
        ];

        if ($meta['type_filter']) {
            $lines['Request type'] = $filters['type'] === 'all' ? 'All request types' : $filters['type'];
        }

        $lines['Department'] = $filters['department_label'] ?? 'All departments';

        return $lines;
    }

    /** "This Month (Oct 1, 2026 – Oct 31, 2026)", or just "All dates". */
    public static function rangeLabel(array $filters): string
    {
        if (! self::hasDateFilter($filters)) {
            return self::PRESET_LABELS[self::PRESET_ALL];
        }

        $from = $filters['from']?->format('M j, Y');
        $to   = $filters['to']?->format('M j, Y');

        $range = $from && $to
            ? ($from === $to ? $from : $from . ' – ' . $to)
            : ($from ? 'from ' . $from : 'up to ' . $to);

        $preset = self::PRESET_LABELS[$filters['preset']] ?? self::PRESET_LABELS[self::PRESET_ALL];

        return $preset === self::PRESET_LABELS[self::PRESET_ALL]
            ? $range
            : $preset . ' (' . $range . ')';
    }

    /**
     * The applied filters as a query string, so a link, a redirect and the
     * export all describe the same report.
     *
     * The filter controls post their own fields; this is only ever built from
     * filters that have already been validated and applied.
     */
    public static function queryFor(array $filters): array
    {
        $query = ['category' => $filters['category']];

        // "All dates" is the default, so it is not spelled out.
        if ($filters['preset'] !== self::PRESET_ALL) {
            $query['preset'] = $filters['preset'];
        }

        // Only a custom range writes its dates down. A named preset means the
        // period it is read in — "This Month" is this month — and pinning its
        // resolved dates would freeze a report that is meant to move with the
        // calendar.
        if ($filters['preset'] === self::PRESET_CUSTOM) {
            if ($filters['from']) {
                $query['from'] = $filters['from']->toDateString();
            }

            if ($filters['to']) {
                $query['to'] = $filters['to']->toDateString();
            }
        }

        if ($filters['status'] !== 'all') {
            $query['status'] = $filters['status'];
        }

        if ($filters['type'] !== 'all') {
            $query['type'] = $filters['type'];
        }

        // A Department Head's department is their account, not a filter: it is
        // never written into a URL.
        if ($filters['department'] && ! $filters['locked']) {
            $query['department'] = (string) $filters['department'];
        }

        return $query;
    }

    /* ═════════════════════════ building ═════════════════════════ */

    /**
     * The whole report: the page's rows plus the summary cards and breakdowns.
     *
     * @return array<string, mixed>
     */
    public static function build(?User $viewer, array $filters, int $perPage = self::PER_PAGE): array
    {
        $definition = self::definition($viewer, $filters);

        $rows = $definition['query'];

        foreach ($definition['order'] as $expression) {
            $rows->orderByRaw($expression);
        }

        $rows = $rows->paginate($perPage)->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map($definition['map'])->values()
        );

        return [
            'category'     => $filters['category'],
            'meta'         => self::category($filters['category']),
            'filters'      => $filters,
            'columns'      => $definition['columns'],
            'rows'         => $rows,
            'total'        => $rows->total(),
            'summary'      => $definition['summary'],
            'generated_at' => Carbon::now(config('app.timezone')),
            'stale'        => false,
        ];
    }

    /**
     * Every matching record, mapped exactly as the page maps them.
     *
     * The export and the print sheet go through here, so neither is ever held
     * back by the page size on screen: the whole result is included.
     *
     * @return array{meta: array, columns: array, rows: Collection<int, array<string, mixed>>, summary: array}
     */
    public static function sheet(?User $viewer, array $filters): array
    {
        $definition = self::definition($viewer, $filters);

        $rows = $definition['query'];

        foreach ($definition['order'] as $expression) {
            $rows->orderByRaw($expression);
        }

        return [
            'meta'    => self::category($filters['category']),
            'columns' => $definition['columns'],
            'rows'    => $rows->get()->map($definition['map'])->values(),
            'summary' => $definition['summary'],
        ];
    }

    /**
     * The CSV of a report: the same columns, in the same order, with every
     * matching record and the criteria the report was generated with.
     *
     * @return array{filename: string, meta: array<int, array<int, string>>, header: array<int, string>, rows: Collection<int, array<int, string>>}
     */
    public static function csv(?User $viewer, array $filters): array
    {
        $sheet      = self::sheet($viewer, $filters);
        $definition = ['columns' => $sheet['columns']];
        $meta       = self::category($filters['category']);
        $generated  = Carbon::now(config('app.timezone'))->format('M j, Y g:i A');

        $header = array_merge(['Report generated at'], array_column($definition['columns'], 'label'));

        $rows = $sheet['rows']->map(function ($row) use ($definition, $generated) {
            $cells = [$generated];

            foreach ($definition['columns'] as $column) {
                $cells[] = self::flatten($row[$column['key']] ?? '');
            }

            return $cells;
        });

        // The criteria travel in the file itself: a CSV that has been emailed
        // around can no longer say which report it was, otherwise.
        $criteria = [['Generated ' . $generated]];

        foreach (self::describe($filters) as $label => $value) {
            $criteria[] = [$label, $value];
        }

        $criteria[] = ['Records', 'every matching record, not only the page on screen'];

        return [
            'filename' => 'nu-trace-' . $filters['category'] . '-report-' . Carbon::now()->format('Y-m-d') . '.csv',
            'meta'     => $criteria,
            'header'   => $header,
            'rows'     => $rows,
            'title'    => $meta['label'],
            'columns'  => $definition['columns'],
        ];
    }

    /**
     * A stored date, in the one format every report uses.
     *
     * The page, the CSV and the print sheet all render through here, so no two
     * of them can ever disagree about what a date looks like.
     */
    public static function date($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * A stored timestamp, in the one format every report uses.
     *
     * Only the columns that record a time of day use this — a repair date, a
     * request's submission, a handover. Everything else is a date.
     */
    public static function datetime($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('M j, Y g:i A');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /** One cell as plain single-line text, so a value never breaks the CSV. */
    private static function flatten($value): string
    {
        if ($value instanceof Carbon) {
            $value = $value->format('M j, Y');
        }

        if (is_array($value)) {
            $value = implode('; ', $value);
        }

        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    /* ═════════════════════════ remembering ═════════════════════════ */

    /**
     * Remember the report that was just generated successfully.
     *
     * The page uses this to answer one question honestly: when an attempt
     * fails, which report is still on the screen? It is the one recorded here,
     * with its own criteria and its own generation time.
     */
    public static function remember(array $filters, ?Carbon $generatedAt = null): void
    {
        Session::put(self::SESSION_KEY, [
            'filters'      => $filters,
            'category'     => $filters['category'],
            'criteria'     => self::describe($filters),
            'generated_at' => ($generatedAt ?? Carbon::now(config('app.timezone')))->toIso8601String(),
        ]);
    }

    /** The last successful report, as it was applied. */
    public static function lastSuccess(): ?array
    {
        $stored = Session::get(self::SESSION_KEY);

        return is_array($stored) && ! empty($stored['filters']) ? $stored : null;
    }

    /** Forget it (a different account signing in on this browser, and tests). */
    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * A generation attempt that failed.
     *
     * Returns the error to show, the previous report to keep on the screen (if
     * it can still be drawn), and nothing else. It deliberately does not return
     * the report that was asked for: there is none, and the page must not
     * present the previous report as though the new criteria had been applied.
     *
     * @return array{error: string, report: ?array, previous: ?array}
     */
    public static function failed(?User $viewer, array $filters, \Throwable $exception): array
    {
        Log::error('Generating the ' . $filters['category'] . ' report failed: ' . $exception->getMessage(), [
            'viewer'     => $viewer?->id,
            'department' => $filters['department'],
            'filters'    => self::queryFor($filters),
        ]);

        $previous = self::lastSuccess();
        $report   = null;

        // The report that stays on the screen is the last one that worked. If
        // even that cannot be redrawn the page says so rather than showing a
        // blank panel that looks like "no records".
        if ($previous && ! empty($previous['filters']['category'])) {
            try {
                $report = self::build($viewer, $previous['filters']);
                $report['stale']        = true;
                $report['generated_at'] = Carbon::parse($previous['generated_at']);
            } catch (\Throwable $e) {
                Log::error('Redrawing the previous report also failed: ' . $e->getMessage());
                $report = null;
            }
        }

        $requested = self::category($filters['category'])['label'];

        $error = 'The ' . $requested . ' report could not be generated, so it is not shown. Nothing was changed and no '
            . 'partial result is displayed.'
            . ($report
                ? ' The report shown below is the last one that was generated successfully, with the criteria and time '
                    . 'it was generated with — it is not the report you just asked for. Generate it again once the problem '
                    . 'is resolved.'
                : ' There is no earlier report to show in its place either, so no records are listed. Generate it again '
                    . 'once the problem is resolved.');

        return [
            'error'    => $error,
            'report'   => $report,
            'previous' => $previous,
        ];
    }

    /* ═════════════════════════ definitions ═════════════════════════ */

    /**
     * One definition per category: an already-filtered and already-selected
     * query, the columns the page and the CSV render, the row mapper, the
     * ordering, and the summaries.
     *
     * A summary is always computed from the same WHERE clauses as the table —
     * it is built from a clone of the same query — so the cards and the rows can
     * never describe two different sets of records.
     *
     * @return array{query: Builder, columns: array, map: callable, order: array<int, string>, summary: array}
     */
    private static function definition(?User $viewer, array $filters): array
    {
        return match ($filters['category']) {
            self::CATEGORY_ACQUISITIONS => self::acquisitionsDefinition($viewer, $filters),
            self::CATEGORY_REPAIRS      => self::repairsDefinition($viewer, $filters),
            self::CATEGORY_TRANSFERS    => self::transfersDefinition($viewer, $filters),
            self::CATEGORY_DISPOSALS    => self::disposalsDefinition($viewer, $filters),
            self::CATEGORY_REQUESTS     => self::requestsDefinition($viewer, $filters),
            default                     => self::inventoryDefinition($viewer, $filters),
        };
    }

    /* ─────────────────────── current inventory ─────────────────────── */

    private static function inventoryDefinition(?User $viewer, array $filters): array
    {
        $base = self::assetBase($filters);
        $meta = self::category(self::CATEGORY_INVENTORY);

        $query = (clone $base);
        self::applyDate($query, 'assets.accusion_date', $filters);
        $query->select(self::assetSelect());

        $total  = (clone $query)->count();
        $undated = self::undatedCount($base, 'assets.accusion_date', $filters);

        $summary = self::summarise($viewer, $query, [
            ['label' => 'Assets in this report', 'value' => number_format($total), 'note' => 'Asset records, one row each'],
            ['label' => 'Not shown (no recorded date)', 'value' => $undated > 0 ? number_format($undated) : '—', 'note' => self::hasDateFilter($filters)
                ? 'Assets with no recorded acquisition date cannot be inside a date range'
                : 'None are excluded: no date filter is applied'],
        ], [
            ['title' => 'Assets by current status', 'rows' => self::groupCount(clone $query, 'assets.Lifecycle_Status'), 'note' => 'One asset record counts once, in the status it carries today.'],
        ], $filters, 'holder_dept.Name');

        $summary['notes'] = array_values(array_filter([
            'One row is one asset record. No quantity is stored per asset, so this report never states a number of physical units.',
            $meta['date_note'],
            $meta['undated_note'],
            'Assets whose disposal record has been archived have left the inventory and are not listed here; they remain visible in the Asset Disposals report.',
            'The department shown is the department of the employee who holds the asset today.',
            self::departmentScopeNote($filters, $viewer),
        ]));

        return [
            'query'   => $query,
            'columns' => self::assetColumns(withCost: false),
            'map'     => fn ($row) => self::assetRow($row, withCost: false),
            'order'   => [
                'CASE WHEN "assets"."accusion_date" IS NULL THEN 1 ELSE 0 END ASC',
                '"assets"."accusion_date" DESC',
                '"assets"."Asset_code" ASC',
            ],
            'summary' => $summary,
        ];
    }

    /* ───────────────────────── acquisitions ───────────────────────── */

    private static function acquisitionsDefinition(?User $viewer, array $filters): array
    {
        $base = self::assetBase($filters);
        $meta = self::category(self::CATEGORY_ACQUISITIONS);

        $query = (clone $base);
        self::applyDate($query, 'assets.accusion_date', $filters);
        $query->select(self::assetSelect());

        $total   = (clone $query)->count();
        $dated   = (clone $query)->whereNotNull('assets.accusion_date')->count();
        $undated = self::undatedCount($base, 'assets.accusion_date', $filters);

        $priced    = (clone $query)->whereNotNull('assets.purchase_Price');
        $costCount = (clone $priced)->count();
        $cost      = (float) (clone $priced)->sum('assets.purchase_Price');

        $summary = self::summarise($viewer, $query, [
            ['label' => 'Assets acquired', 'value' => number_format($total), 'note' => 'Asset records, one row each'],
            ['label' => 'With a recorded date', 'value' => number_format($dated), 'note' => 'Inside the applied filters'],
            ['label' => 'Recorded purchase price', 'value' => number_format($cost, 2), 'note' => $costCount === $total
                ? 'Every matching asset has a recorded price'
                : ($total - $costCount) . ' asset(s) have no recorded price and are not in this total'],
            ['label' => 'Not shown (no recorded date)', 'value' => $undated > 0 ? number_format($undated) : '—', 'note' => self::hasDateFilter($filters)
                ? 'Assets with no recorded acquisition date cannot be inside a date range'
                : 'None are excluded: no date filter is applied'],
        ], [
            ['title' => 'Assets by current status (as the records stand today)', 'rows' => self::groupCount(clone $query, 'assets.Lifecycle_Status'), 'note' => 'The status is the asset\'s status now, not the status it had on its acquisition date — that history is not recorded.'],
        ], $filters, 'holder_dept.Name');

        $summary['notes'] = array_values(array_filter([
            'One row is one asset record. No quantity is stored per asset, so no unit total is shown.',
            $meta['date_note'],
            'The department recorded against a row is the current custodian\'s department: the acquisition event itself does not store one, so an asset that has since moved appears under its present department.',
            $meta['undated_note'],
            self::departmentScopeNote($filters, $viewer),
        ]));

        return [
            'query'   => $query,
            'columns' => self::assetColumns(withCost: true),
            'map'     => fn ($row) => self::assetRow($row, withCost: true),
            'order'   => [
                'CASE WHEN "assets"."accusion_date" IS NULL THEN 1 ELSE 0 END ASC',
                '"assets"."accusion_date" DESC',
                '"assets"."Asset_code" ASC',
            ],
            'summary' => $summary,
        ];
    }

    /**
     * The assets query both asset-centric reports share: the institution's
     * holdings, narrowed by the current-status and department filters only. Each
     * report adds its own date filter, columns, ordering and summaries.
     */
    private static function assetBase(array $filters): Builder
    {
        $query = DB::table('assets')
            ->leftJoin('users as holder', 'assets.user_id', '=', 'holder.id')
            ->leftJoin('employee_numbers as holder_emp', 'holder.employee_numbers_id', '=', 'holder_emp.id')
            ->leftJoin('departments as holder_dept', 'holder.department_id', '=', 'holder_dept.id');

        Inventory::excludeRemoved($query, 'assets');

        if ($filters['status'] !== 'all') {
            $query->where('assets.Lifecycle_Status', $filters['status']);
        }

        self::applyDepartment($query, $filters, 'holder.department_id');

        return $query;
    }

    private static function assetSelect(): array
    {
        return [
            'assets.id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Category',
            'assets.Condition',
            'assets.Lifecycle_Status',
            'assets.accusion_date',
            'assets.purchase_Price',
            'assets.supplier',
            'assets.asset_location',
            'holder_emp.Full_Name as holder_name',
            'holder.email as holder_email',
            'holder_dept.Name as holder_department',
        ];
    }

    private static function assetColumns(bool $withCost): array
    {
        $columns = [
            ['key' => 'asset_code',  'label' => 'Asset code'],
            ['key' => 'asset_name',  'label' => 'Asset name'],
            ['key' => 'category',    'label' => 'Category'],
            ['key' => 'holder',      'label' => 'Accountable employee'],
            ['key' => 'department',  'label' => 'Department'],
            ['key' => 'acquired',    'label' => 'Date acquired'],
            ['key' => 'location',    'label' => 'Location'],
            ['key' => 'condition',   'label' => 'Condition'],
        ];

        if ($withCost) {
            $columns[] = ['key' => 'purchase_price', 'label' => 'Purchase price'];
            $columns[] = ['key' => 'supplier',       'label' => 'Supplier'];
        }

        $columns[] = ['key' => 'status', 'label' => 'Current status'];

        return $columns;
    }

    private static function assetRow(object $row, bool $withCost): array
    {
        $values = [
            'asset_code'     => $row->Asset_code ?: ('asset #' . $row->id),
            'asset_name'     => $row->Asset_name,
            'category'       => $row->Category,
            'holder'         => $row->holder_name ?: ($row->holder_email ?: 'Unassigned'),
            'department'     => $row->holder_department ?: 'No department',
            'acquired'       => self::date($row->accusion_date),
            'location'       => $row->asset_location,
            'condition'      => $row->Condition,
            'status'         => $row->Lifecycle_Status ?: 'Acquired',
        ];

        if (! $withCost) {
            return $values;
        }

        return $values + [
            'purchase_price' => $row->purchase_Price === null ? '' : number_format((float) $row->purchase_Price, 2),
            'supplier'       => $row->supplier,
        ];
    }

    /* ──────────────────────────── repairs ──────────────────────────── */

    private static function repairsDefinition(?User $viewer, array $filters): array
    {
        $meta = self::category(self::CATEGORY_REPAIRS);

        $hasEvaluations = Schema::hasTable('repair_evaluations');

        $base = DB::table('repairs')
            ->leftJoin('assets', 'repairs.Assets_id', '=', 'assets.id')
            ->leftJoin('users as holder', 'assets.user_id', '=', 'holder.id')
            ->leftJoin('employee_numbers as holder_emp', 'holder.employee_numbers_id', '=', 'holder_emp.id')
            ->leftJoin('departments as holder_dept', 'holder.department_id', '=', 'holder_dept.id')
            ->leftJoin('requests', 'repairs.Request_id', '=', 'requests.id')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id');

        if ($hasEvaluations) {
            $base->leftJoin('repair_evaluations as eval', 'eval.repair_id', '=', 'repairs.Repair_id');
        }

        if ($filters['status'] !== 'all') {
            $base->where('repairs.status', $filters['status']);
        }

        self::applyDepartment($base, $filters, 'holder.department_id');

        $query = (clone $base);
        self::applyDate($query, 'repairs.Repair_Date', $filters);
        $query->select(array_merge([
            'repairs.Repair_id',
            'repairs.Repair_Date',
            'repairs.status',
            'repairs.Repair_result',
            'repairs.Repair_Description',
            'repairs.Repair_Cost',
            'repairs.Approve_by',
            'repairs.updated_at',
            'assets.id as asset_id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Lifecycle_Status',
            'holder_emp.Full_Name as holder_name',
            'holder_dept.Name as holder_department',
            'requester_emp.Full_Name as requester_name',
        ], $hasEvaluations ? ['eval.technician_provider'] : []));

        $records = (clone $query)->count();
        $assets  = (clone $query)->distinct()->count('repairs.Assets_id');
        $cost    = (float) (clone $query)->sum('repairs.Repair_Cost');

        $summary = self::summarise($viewer, $query, [
            ['label' => 'Repair records', 'value' => number_format($records), 'note' => 'One row per repair record'],
            ['label' => 'Assets repaired', 'value' => number_format($assets), 'note' => 'Distinct assets among those records'],
            ['label' => 'Recorded repair cost', 'value' => number_format($cost, 2), 'note' => 'Total of the repair cost recorded on each record'],
        ], [
            ['title' => 'Repair records by status', 'rows' => self::groupCount(clone $query, 'repairs.status'), 'note' => 'The repair record\'s own workflow status — not the asset\'s lifecycle status.'],
        ], $filters, 'holder_dept.Name');

        $summary['notes'] = array_values(array_filter([
            'One row is one repair record, and several records can name the same asset. "Assets repaired" counts those assets once, which is why it can be smaller than the row count.',
            $meta['date_note'],
            'A repair record stores no separate completion date: the "Last updated" column is when the record last changed, and is not offered as a date filter for that reason.',
            'Repair records are kept after an asset is retired, so a repair on a since-disposed asset still appears here.',
            self::departmentScopeNote($filters, $viewer),
        ]));

        return [
            'query'   => $query,
            'columns' => [
                ['key' => 'repair_date', 'label' => 'Repair date'],
                ['key' => 'asset_code',  'label' => 'Asset code'],
                ['key' => 'asset_name',  'label' => 'Asset name'],
                ['key' => 'department',  'label' => 'Department'],
                ['key' => 'holder',      'label' => 'Accountable employee'],
                ['key' => 'status',      'label' => 'Repair status'],
                ['key' => 'result',      'label' => 'Repair result'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'cost',        'label' => 'Recorded cost'],
                ['key' => 'technician',  'label' => 'Technician / provider'],
                ['key' => 'requested_by', 'label' => 'Requested by'],
                ['key' => 'updated',     'label' => 'Last updated'],
            ],
            'map' => function ($row) use ($hasEvaluations) {
                return [
                    'repair_date'  => self::datetime($row->Repair_Date),
                    'asset_code'   => $row->Asset_code ?: ($row->asset_id ? 'asset #' . $row->asset_id : '—'),
                    'asset_name'   => $row->Asset_name ?: 'Asset record no longer present',
                    'department'   => $row->holder_department ?: 'No department',
                    'holder'       => $row->holder_name ?: 'Unassigned',
                    'status'       => $row->status ?: 'Pending',
                    'result'       => $row->Repair_result ?: '',
                    'description'  => $row->Repair_Description,
                    'cost'         => $row->Repair_Cost === null ? '' : number_format((float) $row->Repair_Cost, 2),
                    'technician'   => $hasEvaluations ? ($row->technician_provider ?: '') : '',
                    'requested_by' => $row->requester_name ?: '',
                    'updated'      => self::datetime($row->updated_at),
                ];
            },
            'order'   => [
                'CASE WHEN "repairs"."Repair_Date" IS NULL THEN 1 ELSE 0 END ASC',
                '"repairs"."Repair_Date" DESC',
                '"repairs"."Repair_id" DESC',
            ],
            'summary' => $summary,
        ];
    }

    /* ──────────────────────────── transfers ──────────────────────────── */

    private static function transfersDefinition(?User $viewer, array $filters): array
    {
        $meta = self::category(self::CATEGORY_TRANSFERS);

        $assetId = 'COALESCE("request_items"."asset_id", "requests"."asset_id")';

        $base = DB::table('requests')
            ->where('requests.request_type', 'Transfer')
            ->leftJoin('request_items', 'request_items.request_id', '=', 'requests.id')
            ->leftJoin('assets', 'assets.id', '=', DB::raw($assetId))
            ->leftJoin('users as giver', 'requests.user_id', '=', 'giver.id')
            ->leftJoin('employee_numbers as giver_emp', 'giver.employee_numbers_id', '=', 'giver_emp.id')
            ->leftJoin('departments as giver_dept', 'giver.department_id', '=', 'giver_dept.id')
            ->leftJoin('users as taker', 'requests.assign_to_user_id', '=', 'taker.id')
            ->leftJoin('employee_numbers as taker_emp', 'taker.employee_numbers_id', '=', 'taker_emp.id')
            ->leftJoin('departments as taker_dept', 'taker.department_id', '=', 'taker_dept.id');

        // The day the handover was carried out, per asset: the asset's own
        // TRANSFER entry in the history log writes it. A transfer that has not
        // been carried out has no such entry, and therefore no transfer date —
        // the request's submission date is deliberately not used in its place.
        $executed = DB::table('audit_logs')
            ->select('request_id', 'asset_id', DB::raw('MAX(created_at) as executed_at'))
            ->where('action_type', 'TRANSFER')
            ->whereNotNull('request_id')
            ->whereNotNull('asset_id')
            ->groupBy('request_id', 'asset_id');

        $base->leftJoinSub($executed, 'executed', function ($join) {
            $join->on('executed.request_id', '=', 'requests.id')
                ->on('executed.asset_id', '=', DB::raw('COALESCE("request_items"."asset_id", "requests"."asset_id")'));
        });

        if ($filters['status'] !== 'all') {
            $base->where('requests.status', $filters['status']);
        }

        // Either employee on the transfer can be the department's own: the one
        // handing over, or the one receiving. Both are recorded on the transfer
        // itself, which is what "a transfer involving this department" means.
        self::applyDepartment($base, $filters, ['giver.department_id', 'taker.department_id']);

        $query = (clone $base);
        self::applyDate($query, 'executed.executed_at', $filters);
        $query->select([
            'requests.id as request_id',
            'requests.status as request_status',
            'requests.Note as reason',
            'requests.created_at as submitted_at',
            'executed.executed_at',
            'assets.id as asset_id',
            'assets.Asset_code',
            'assets.Asset_name',
            'giver_emp.Full_Name as giver_name',
            'giver_dept.Name as giver_department',
            'taker_emp.Full_Name as taker_name',
            'taker_dept.Name as taker_department',
        ]);

        $lines     = (clone $query)->count();
        $transfers = (clone $query)->distinct()->count('requests.id');
        $assets    = (clone $query)->distinct()->count('assets.id');
        $carried   = (clone $query)->whereNotNull('executed.executed_at')->count();

        $summary = self::summarise($viewer, $query, [
            ['label' => 'Assets in matching transfers', 'value' => number_format($lines), 'note' => 'One row per asset on a transfer'],
            ['label' => 'Transfers', 'value' => number_format($transfers), 'note' => 'Distinct transfers among those rows'],
            ['label' => 'Distinct assets', 'value' => number_format($assets), 'note' => 'An asset can appear in more than one transfer'],
            ['label' => 'Carried out', 'value' => number_format($carried), 'note' => $lines - $carried > 0
                ? ($lines - $carried) . ' row(s) have no recorded transfer date'
                : 'Every matching row has a recorded transfer date'],
        ], [
            ['title' => 'Transfer rows by request status', 'rows' => self::groupCount(clone $query, 'requests.status'), 'note' => 'The request\'s workflow status. "Approved" on its own does not mean the assets have moved — the transfer date does.'],
        ], $filters);

        $summary['notes'] = array_values(array_filter([
            'One row is one asset on a transfer; a transfer of three assets is three rows and one transfer.',
            $meta['date_note'],
            $meta['undated_note'],
            'A transfer approved in the office but still waiting on the Executive Director is listed without a transfer date, and is left out of a date-filtered result.',
            self::departmentScopeNote($filters, $viewer),
        ]));

        return [
            'query'   => $query,
            'columns' => [
                ['key' => 'transfer_date', 'label' => 'Transfer date'],
                ['key' => 'reference',     'label' => 'Transfer'],
                ['key' => 'asset_code',    'label' => 'Asset code'],
                ['key' => 'asset_name',    'label' => 'Asset name'],
                ['key' => 'from',          'label' => 'From'],
                ['key' => 'to',            'label' => 'To'],
                ['key' => 'reason',        'label' => 'Reason'],
                ['key' => 'status',        'label' => 'Request status'],
                ['key' => 'submitted',     'label' => 'Submitted'],
            ],
            'map' => fn ($row) => [
                'transfer_date' => $row->executed_at ? self::datetime($row->executed_at) : 'Not carried out',
                'reference'     => 'TR-' . str_pad((string) $row->request_id, 4, '0', STR_PAD_LEFT),
                'asset_code'    => $row->Asset_code ?: '—',
                'asset_name'    => $row->Asset_name ?: '—',
                'from'          => trim(($row->giver_name ?: 'Unknown') . ($row->giver_department ? ' (' . $row->giver_department . ')' : '')),
                'to'            => trim(($row->taker_name ?: 'Unknown') . ($row->taker_department ? ' (' . $row->taker_department . ')' : '')),
                'reason'        => $row->reason,
                'status'        => $row->request_status ?: 'Pending',
                'submitted'     => self::datetime($row->submitted_at),
            ],
            'order'   => [
                'CASE WHEN "executed"."executed_at" IS NULL THEN 1 ELSE 0 END ASC',
                '"executed"."executed_at" DESC',
                '"requests"."id" ASC',
                '"assets"."Asset_code" ASC',
            ],
            'summary' => $summary,
        ];
    }

    /* ──────────────────────────── disposals ──────────────────────────── */

    private static function disposalsDefinition(?User $viewer, array $filters): array
    {
        $meta = self::category(self::CATEGORY_DISPOSALS);

        $hasArchive = Disposals::archiveColumnsReady();
        $hasForms   = Schema::hasTable('disposal_forms');

        $base = DB::table('disposals')
            ->leftJoin('assets', 'disposals.Asset_id', '=', 'assets.id')
            ->leftJoin('users as holder', 'assets.user_id', '=', 'holder.id')
            ->leftJoin('employee_numbers as holder_emp', 'holder.employee_numbers_id', '=', 'holder_emp.id')
            ->leftJoin('departments as holder_dept', 'holder.department_id', '=', 'holder_dept.id')
            ->leftJoin('requests', 'disposals.Request_id', '=', 'requests.id')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id');

        if ($hasForms) {
            $base->leftJoin('disposal_forms as form', 'form.request_id', '=', 'disposals.Request_id');
        }

        if ($filters['status'] !== 'all') {
            if ($filters['status'] === 'Archived') {
                $base->where('disposals.is_archived', true);
            } else {
                // Rows written before the archive migration hold no value at all.
                $base->where(function (Builder $query) {
                    $query->where('disposals.is_archived', false)->orWhereNull('disposals.is_archived');
                });
            }
        }

        self::applyDepartment($base, $filters, 'holder.department_id');

        $query = (clone $base);
        self::applyDate($query, 'disposals.disposal_date', $filters);
        $query->select(array_merge([
            'disposals.Disposal_ID',
            'disposals.disposal_date',
            'disposals.disposal_reason',
            'disposals.Description',
            'disposals.Approve_by',
            'disposals.notes',
            'assets.id as asset_id',
            'assets.Asset_code',
            'assets.Asset_name',
            'holder_emp.Full_Name as holder_name',
            'holder_dept.Name as holder_department',
            'requester_emp.Full_Name as requester_name',
        ], $hasArchive
            ? ['disposals.is_archived', 'disposals.archived_at']
            : [DB::raw('0 as is_archived'), DB::raw('NULL as archived_at')], $hasForms
            ? ['form.status as form_status', 'form.actual_disposal_date']
            : [DB::raw('NULL as form_status'), DB::raw('NULL as actual_disposal_date')]));

        $records  = (clone $query)->count();
        $assets   = (clone $query)->distinct()->count('disposals.Asset_id');
        $archived = $hasArchive ? (clone $query)->where('disposals.is_archived', true)->count() : 0;

        $summary = self::summarise($viewer, $query, [
            ['label' => 'Disposal records', 'value' => number_format($records), 'note' => 'One row per recorded disposal'],
            ['label' => 'Distinct assets', 'value' => number_format($assets), 'note' => 'Among those records'],
            ['label' => 'Archived records', 'value' => $hasArchive ? number_format($archived) : 'Not available', 'note' => $hasArchive
                ? 'Moved to Archived Disposal Assets; their assets have left the inventory'
                : 'The archive database migration has not been run on this server'],
        ], [
            ['title' => 'Disposal records by recorded reason', 'rows' => self::groupCount(clone $query, 'disposals.disposal_reason'), 'note' => 'The reason recorded on the disposal record.'],
        ], $filters, 'holder_dept.Name');

        $summary['notes'] = array_values(array_filter([
            'One row is one disposal record for one asset. No quantity is stored per record, so no unit total is shown.',
            $meta['date_note'],
            'A disposal record exists only once an approved disposal request has been finalized. An approved request with no record yet appears in the Requests report, not here.',
            $hasArchive
                ? 'Archiving a record takes its asset out of the inventory; the record itself is kept, which is why archived disposals still appear here.'
                : 'The archive columns are not present on this installation, so no record can be classified as Archived.',
            'A disposal recorded before this system kept the asset row (a legacy record) has no accountable employee left to name, so it carries no department and is not listed for a department-scoped viewer.',
            self::departmentScopeNote($filters, $viewer),
        ]));

        return [
            'query'   => $query,
            'columns' => [
                ['key' => 'disposal_date', 'label' => 'Disposal date'],
                ['key' => 'asset_code',    'label' => 'Asset code'],
                ['key' => 'asset_name',    'label' => 'Asset name'],
                ['key' => 'reason',        'label' => 'Reason'],
                ['key' => 'department',    'label' => 'Department'],
                ['key' => 'holder',        'label' => 'Last accountable employee'],
                ['key' => 'state',         'label' => 'Record state'],
                ['key' => 'approve_by',    'label' => 'Recorded approver'],
                ['key' => 'form_status',   'label' => 'Disposal form'],
                ['key' => 'archived_at',   'label' => 'Archived on'],
                ['key' => 'description',   'label' => 'Description'],
            ],
            'map' => fn ($row) => [
                'disposal_date' => self::date($row->disposal_date),
                'asset_code'    => $row->Asset_code ?: 'N/A',
                'asset_name'    => $row->Asset_name ?: 'Asset record no longer present',
                'reason'        => $row->disposal_reason ?: '',
                'department'    => $row->holder_department ?: 'No department',
                'holder'        => $row->holder_name ?: '—',
                'state'         => ! empty($row->is_archived) ? 'Archived' : 'Recorded',
                'approve_by'    => $row->Approve_by ?: '',
                'form_status'   => self::formStage($row->form_status ?? null),
                'archived_at'   => $row->archived_at ? self::date($row->archived_at) : '',
                'description'   => $row->Description ?: (string) ($row->notes ?? ''),
            ],
            'order'   => [
                'CASE WHEN "disposals"."disposal_date" IS NULL THEN 1 ELSE 0 END ASC',
                '"disposals"."disposal_date" DESC',
                '"disposals"."Disposal_ID" DESC',
            ],
            'summary' => $summary,
        ];
    }

    /** The official disposal form's stage, in words. */
    private static function formStage(?string $status): string
    {
        return match ($status) {
            'draft'     => 'Draft',
            'ready'     => 'Ready to print',
            'printed'   => 'Printed',
            'signed'    => 'Signed copy received',
            'finalized' => 'Finalized',
            'cancelled' => 'Cancelled',
            default     => 'No form on record',
        };
    }

    /* ───────────────────────────── requests ───────────────────────────── */

    private static function requestsDefinition(?User $viewer, array $filters): array
    {
        $meta = self::category(self::CATEGORY_REQUESTS);

        $hasDisposals = Schema::hasTable('disposals');

        $base = DB::table('requests')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id')
            ->leftJoin('departments as requester_dept', 'requester.department_id', '=', 'requester_dept.id')
            ->leftJoin('users as assignee', 'requests.assign_to_user_id', '=', 'assignee.id')
            ->leftJoin('employee_numbers as assignee_emp', 'assignee.employee_numbers_id', '=', 'assignee_emp.id');

        if ($filters['status'] !== 'all') {
            $base->where('requests.status', $filters['status']);
        }

        if ($filters['type'] !== 'all') {
            $base->where('requests.request_type', $filters['type']);
        }

        // A Department Head reads their own department's requests: the same rule
        // the department's request page already uses, read from the submitter.
        self::applyDepartment($base, $filters, 'requester.department_id');

        $query = (clone $base);
        self::applyDate($query, 'requests.created_at', $filters);

        $query->select([
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.Note',
            'requests.admin_remarks',
            'requests.admin_remarks_at',
            'requests.created_at',
            'requests.updated_at',
            'requester_emp.Full_Name as requester_name',
            'requester_dept.Name as requester_department',
            'assignee_emp.Full_Name as assignee_name',
        ]);

        // What the assets a request names look like, without a query per row:
        // two correlated counts the database answers alongside the row itself.
        $query->selectSub(function ($sub) {
            $sub->from('request_items')
                ->join('assets as named', 'named.id', '=', 'request_items.asset_id')
                ->whereColumn('request_items.request_id', 'requests.id')
                ->select(DB::raw('count(*)'));
        }, 'item_count');

        $query->selectSub(function ($sub) {
            $sub->from('request_items')
                ->join('assets as named', 'named.id', '=', 'request_items.asset_id')
                ->whereColumn('request_items.request_id', 'requests.id')
                ->select(DB::raw('MIN("named"."Asset_code")'));
        }, 'first_code');

        // Whether anything actually followed the request. Read from the records
        // that would exist if it had — a disposal record, a carried-out transfer
        // — never inferred from the workflow status.
        if ($hasDisposals) {
            $query->selectSub(function ($sub) {
                $sub->from('disposals')
                    ->whereColumn('disposals.Request_id', 'requests.id')
                    ->select(DB::raw('count(*)'));
            }, 'disposal_records');
        } else {
            $query->addSelect(DB::raw('0 as disposal_records'));
        }

        $query->selectSub(function ($sub) {
            $sub->from('audit_logs')
                ->whereColumn('audit_logs.request_id', 'requests.id')
                ->where('audit_logs.action_type', 'TRANSFER')
                ->whereNotNull('audit_logs.asset_id')
                ->select(DB::raw('count(*)'));
        }, 'transfer_events');

        $total = (clone $query)->count();

        $summary = self::summarise($viewer, $query, [
            ['label' => 'Requests matching', 'value' => number_format($total), 'note' => 'One row per request'],
            ['label' => 'Pending', 'value' => number_format(self::countStatus(clone $query, 'Pending')), 'note' => 'Still waiting on the Asset Management Office'],
            ['label' => 'Approved', 'value' => number_format(self::countStatus(clone $query, 'Approved')), 'note' => 'Approved requests — the work may or may not have followed'],
            ['label' => 'Rejected', 'value' => number_format(self::countStatus(clone $query, 'Rejected')), 'note' => 'Rejected requests'],
        ], [
            ['title' => 'Requests by workflow status', 'rows' => self::groupCount(clone $query, 'requests.status'), 'note' => 'The request\'s recorded status. Approval is not the same as the work being carried out.'],
            ['title' => 'Requests by type', 'rows' => self::groupCount(clone $query, 'requests.request_type'), 'note' => 'The type the request was submitted as.'],
        ], $filters, 'requester_dept.Name');

        $summary['notes'] = array_values(array_filter([
            'One row is one request, whatever number of assets it names.',
            $meta['date_note'],
            'A decision time is not stored separately from the request\'s last change, so no approval date is reported.',
            'An approved disposal or transfer request is not proof that the asset was disposed of or handed over: the "Recorded outcome" column says what followed it, and only where that is recorded.',
            self::departmentScopeNote($filters, $viewer),
        ]));

        return [
            'query'   => $query,
            'columns' => [
                ['key' => 'request',     'label' => 'Request'],
                ['key' => 'type',        'label' => 'Type'],
                ['key' => 'status',      'label' => 'Request status'],
                ['key' => 'submitted',   'label' => 'Date submitted'],
                ['key' => 'requester',   'label' => 'Submitted by'],
                ['key' => 'department',  'label' => 'Department'],
                ['key' => 'assets',      'label' => 'Assets named'],
                ['key' => 'outcome',     'label' => 'Recorded outcome'],
                ['key' => 'note',        'label' => 'Requester\'s note'],
                ['key' => 'remarks',     'label' => 'Office remarks'],
                ['key' => 'last_change', 'label' => 'Last changed'],
            ],
            'map' => fn ($row) => [
                'request'     => 'REQ-' . str_pad((string) $row->id, 4, '0', STR_PAD_LEFT),
                'type'        => $row->request_type ?: 'Other',
                'status'      => $row->status ?: 'Pending',
                'submitted'   => self::datetime($row->created_at),
                'requester'   => $row->requester_name ?: '—',
                'department'  => $row->requester_department ?: 'No department',
                'assets'      => self::assetSummary($row->item_count ?? 0, $row->first_code ?? null),
                'outcome'     => self::outcomeLabel($row),
                'note'        => $row->Note,
                'remarks'     => trim((string) ($row->admin_remarks ?? '')
                    . ($row->admin_remarks_at ? ' (' . self::datetime($row->admin_remarks_at) . ')' : '')),
                'last_change' => self::datetime($row->updated_at),
            ],
            'order'   => [
                '"requests"."created_at" DESC',
                '"requests"."id" DESC',
            ],
            'summary' => $summary,
        ];
    }

    /** How many rows of the filtered result carry one request status. */
    private static function countStatus(Builder $query, string $status): int
    {
        return (clone $query)->whereRaw('LOWER("requests"."status") = ?', [strtolower($status)])->count();
    }

    /** "AST-0001 +2 more" — the way the request pages already name a request's assets. */
    private static function assetSummary($count, ?string $firstCode): string
    {
        $count = (int) $count;

        if ($count <= 0) {
            return '—';
        }

        $label = $firstCode ?: 'asset';

        return $count === 1 ? $label : $label . ' +' . ($count - 1) . ' more';
    }

    /**
     * What followed a request, where the database records it.
     *
     * A disposal request is answered by a disposal record; a transfer request by
     * the asset's own transfer history entries. Everything else says so rather
     * than dressing the workflow status up as an outcome.
     */
    private static function outcomeLabel(object $row): string
    {
        $type = strtolower(trim((string) $row->request_type));

        if ($type === 'disposal') {
            return ((int) ($row->disposal_records ?? 0)) > 0
                ? 'Disposal record created'
                : 'No disposal record';
        }

        if ($type === 'transfer') {
            return ((int) ($row->transfer_events ?? 0)) > 0
                ? 'Transfer carried out'
                : 'Not carried out';
        }

        return '—';
    }

    /* ═════════════════════════ shared helpers ═════════════════════════ */

    /**
     * Apply the date filter to a dated column.
     *
     * Two behaviours, deliberately different:
     *
     *   - with no date filter, every row is included, dated or not: this is an
     *     "all dates" report, not a "no dates" one;
     *   - with a date filter, a row whose relevant event date is not recorded
     *     cannot be inside a range it has no date for, so it is left out — and
     *     the report says how many were left out.
     *
     * `whereDate` compares whole days, so the final day of a range is always
     * included, whichever database is behind it.
     */
    private static function applyDate(Builder $query, string $column, array $filters): void
    {
        if (! self::hasDateFilter($filters)) {
            return;
        }

        $query->whereNotNull($column);

        if ($filters['from']) {
            $query->whereDate($column, '>=', $filters['from']->toDateString());
        }

        if ($filters['to']) {
            $query->whereDate($column, '<=', $filters['to']->toDateString());
        }
    }

    /** How many rows the date filter had to leave out because their date is unknown. */
    private static function undatedCount(Builder $base, string $column, array $filters): int
    {
        if (! self::hasDateFilter($filters)) {
            return 0;
        }

        return (clone $base)->whereNull($column)->count();
    }

    /**
     * Apply the department scope.
     *
     * For a Department Head this is always their own department, taken from
     * their account — any department value the request carried was discarded
     * while the filters were read. For an Admin it is the department they chose,
     * when they chose one.
     *
     * @param  string|array<int, string>  $columns  one column, or several joined with OR
     */
    private static function applyDepartment(Builder $query, array $filters, string|array $columns): void
    {
        $department = $filters['department'] ?? null;

        if (! $department) {
            return;
        }

        $columns = array_values((array) $columns);

        if (count($columns) === 1) {
            $query->where($columns[0], $department);

            return;
        }

        // Several departments can own one record — a transfer involves two
        // employees. They are ORed so the record appears once, for whoever is
        // looking, instead of being duplicated.
        $query->where(function (Builder $query) use ($columns, $department) {
            foreach ($columns as $column) {
                $query->orWhere($column, $department);
            }
        });
    }

    /** One line about the scope a report was generated under. */
    private static function departmentScopeNote(array $filters, ?User $viewer): ?string
    {
        if ($filters['locked']) {
            return 'Department Head view: every row belongs to ' . ($filters['department_label'] ?? 'your department')
                . ', and no filter can widen it.';
        }

        if ($filters['department']) {
            return 'Narrowed to department: ' . $filters['department_label'] . '.';
        }

        return Access::isAssetOffice($viewer)
            ? 'University-wide: every department is included.'
            : null;
    }

    /**
     * Group a query's rows by one column, for a breakdown.
     *
     * @return array<int, array{label: string, total: int}>
     */
    private static function groupCount(Builder $query, string $column, string $fallback = 'Not recorded'): array
    {
        return $query
            ->select($column . ' as label', DB::raw('count(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($fallback) {
                $label = trim((string) ($row->label ?? ''));

                return [
                    'label' => $label !== '' ? $label : $fallback,
                    'total' => (int) $row->total,
                ];
            })
            ->all();
    }

    /**
     * Assemble a summary: the cards, the breakdowns, and — for the Asset
     * Management Office reading the whole university — the same records broken
     * down by department.
     *
     * The department breakdown is added only when it says something: it is the
     * office's own view, and only while the report spans more than one
     * department. Inside a single department it would be a one-row breakdown,
     * which is noise, and a Department Head never needs to be told their own
     * department again.
     */
    private static function summarise(
        ?User $viewer,
        Builder $query,
        array $cards,
        array $breakdowns,
        array $filters,
        ?string $departmentColumn = null
    ): array {
        if ($departmentColumn && Access::isAssetOffice($viewer) && ! $filters['department']) {
            $breakdowns[] = [
                'title' => 'By department',
                'rows'  => self::groupCount(clone $query, $departmentColumn, 'No department'),
                'note'  => 'Counted with the same filters as the table above.',
            ];
        }

        return [
            'cards'      => $cards,
            'breakdowns' => $breakdowns,
            'notes'      => [],
        ];
    }
}
