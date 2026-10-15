<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;
use Throwable;

/**
 * The official NU Lipa Disposal Form: preparation, printing, the signed copy
 * that comes back from Accountancy, and the one explicit step that retires the
 * asset.
 *
 * The rule the whole class is built around: approving a disposal request,
 * generating a form, saving it, printing it or filing a signed copy NEVER marks
 * an asset Disposed. Only finalize() does, and only after the request is
 * approved and the signed form has been recorded — and it then writes exactly
 * what the rest of the system already understands (a disposals row per asset,
 * Lifecycle_Status = 'Disposal', an audit entry), so the Disposal page, the
 * archive and the asset's history keep working unchanged.
 *
 * Stages (disposal_forms.status):
 *
 *   draft      Awaiting preparation — generated from the approved request
 *   ready      Ready to print — every required field is filled in
 *   printed    Printed — awaiting the signed form from Accountancy
 *   signed     Signed form received (the attachment is optional evidence)
 *   finalized  Disposal finalized — the assets are now Disposed
 *
 * The code ships before `php artisan migrate` runs on the server, so every read
 * and write checks tablesReady() first instead of failing on a missing table.
 */
class DisposalForms
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_READY     = 'ready';
    public const STATUS_PRINTED   = 'printed';
    public const STATUS_SIGNED    = 'signed';
    public const STATUS_FINALIZED = 'finalized';

    /** All stages, in workflow order. */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY,
        self::STATUS_PRINTED,
        self::STATUS_SIGNED,
        self::STATUS_FINALIZED,
    ];

    /**
     * Where a scanned signed copy is kept.
     *
     * The private local disk, never the media disk: the media disk is a public
     * bucket (asset photos, QR images), and a signed disposal form must not be
     * fetchable by URL. Every download goes through the authorized route.
     */
    public const UPLOAD_DISK = 'local';
    public const UPLOAD_FOLDER = 'disposal-forms/signed';

    /** Accepted for a signed copy, and how large it may be. */
    public const UPLOAD_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    public const UPLOAD_MAX_KB = 5120;

    private static ?bool $tablesReady = null;

    /** Has the disposal-forms migration been run? */
    public static function tablesReady(): bool
    {
        if (self::$tablesReady === null) {
            self::$tablesReady = Schema::hasTable('disposal_forms')
                && Schema::hasTable('disposal_form_items');
        }

        return self::$tablesReady;
    }

    /* ─────────────────────────── stages ─────────────────────────── */

    public static function label(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT     => 'Awaiting preparation',
            self::STATUS_READY     => 'Ready to print',
            self::STATUS_PRINTED   => 'Printed — awaiting signed form',
            self::STATUS_SIGNED    => 'Signed form received',
            self::STATUS_FINALIZED => 'Disposal finalized',
            default                => 'Unknown',
        };
    }

    /** What the stage means, in one line, for the queue. */
    public static function hint(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT     => 'Missing details must be completed before the form can be printed.',
            self::STATUS_READY     => 'Complete and validated — select it for printing.',
            self::STATUS_PRINTED   => 'Printing is recorded. The signature does not exist yet: file the signed copy when Accountancy returns it.',
            self::STATUS_SIGNED    => 'The signed form is on file. The disposal can now be finalized.',
            self::STATUS_FINALIZED => 'The assets have been disposed of and recorded.',
            default                => '',
        };
    }

    public static function isFinalized(?string $status): bool
    {
        return $status === self::STATUS_FINALIZED;
    }

    /** Which stage a form may be printed from. */
    public static function isPrintable(?string $status): bool
    {
        return in_array($status, [self::STATUS_READY, self::STATUS_PRINTED, self::STATUS_SIGNED], true);
    }

    /* ─────────────────────────── reading ─────────────────────────── */

    /**
     * Every form, with the request and requester it belongs to.
     *
     * @return Collection<int, object>
     */
    public static function queue(): Collection
    {
        if (! self::tablesReady()) {
            return new Collection();
        }

        $forms = DB::table('disposal_forms')
            ->join('requests', 'disposal_forms.request_id', '=', 'requests.id')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id')
            ->leftJoin('users as preparer', 'disposal_forms.prepared_by', '=', 'preparer.id')
            ->leftJoin('employee_numbers as preparer_emp', 'preparer.employee_numbers_id', '=', 'preparer_emp.id')
            ->orderByRaw("CASE disposal_forms.status
                WHEN 'draft' THEN 0 WHEN 'ready' THEN 1 WHEN 'printed' THEN 2
                WHEN 'signed' THEN 3 ELSE 4 END")
            ->orderBy('disposal_forms.id')
            ->get([
                'disposal_forms.*',
                'requests.Note as request_note',
                'requests.status as request_status',
                'requests.created_at as request_date',
                DB::raw('requester.email as requester_email'),
                DB::raw('requester_emp."Full_Name" as requester_name'),
                DB::raw('preparer_emp."Full_Name" as prepared_by_name'),
                'preparer.email as prepared_by_email',
            ]);

        $items = self::itemsForForms($forms->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $forms->map(function ($form) use ($items) {
            $form = self::present($form);
            $form->items = $items[(int) $form->id] ?? new Collection();

            return $form;
        });
    }

    /**
     * Approved disposal requests that have no form yet.
     *
     * After this change every new approval generates its form, so this list is
     * about the requests approved before it existed, and about a form that could
     * not be written at approval time. They are offered for preparation here
     * instead of being stranded.
     *
     * @return Collection<int, object>
     */
    public static function awaitingForm(): Collection
    {
        if (! self::tablesReady()) {
            return new Collection();
        }

        return DB::table('requests')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id')
            ->whereRaw('LOWER(requests.request_type) = ?', ['disposal'])
            ->whereRaw('LOWER(requests.status) = ?', ['approved'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('disposal_forms')
                    ->whereColumn('disposal_forms.request_id', 'requests.id');
            })
            ->orderByDesc('requests.created_at')
            ->get([
                'requests.id',
                'requests.Note',
                'requests.created_at',
                'requests.updated_at',
                DB::raw('requester.email as requester_email'),
                DB::raw('requester_emp."Full_Name" as requester_name'),
                // A correlated count instead of a GROUP BY: the grouping wants a
                // quoted identifier ("Full_Name"), and every driver quotes those
                // differently. One row per request, no grouping to get wrong.
                DB::raw('(SELECT COUNT(*) FROM disposals WHERE disposals."Request_id" = requests.id) as disposal_count'),
            ])
            ->map(function ($row) {
                $row->already_recorded = ((int) $row->disposal_count) > 0;

                return $row;
            });
    }

    /**
     * How many forms sit at each stage. Used by the tab bar and the banner on
     * the Disposal Records tab, so both can point at the work waiting.
     *
     * @return array<string, int>
     */
    public static function statusCounts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);

        if (! self::tablesReady()) {
            return $counts;
        }

        foreach (DB::table('disposal_forms')->groupBy('status')->get(['status', DB::raw('COUNT(*) as total')]) as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * The forms with the given ids, in the order given, each with its lines, its
     * request and its requester.
     *
     * Used by the editor and by printing (single and batch), so one query
     * builder decides what a form page can see.
     *
     * @param  array<int, int>  $formIds
     * @return Collection<int, object>
     */
    public static function load(array $formIds): Collection
    {
        $formIds = array_values(array_unique(array_filter(array_map('intval', $formIds))));

        if (! self::tablesReady() || ! $formIds) {
            return new Collection();
        }

        $rows = DB::table('disposal_forms')
            ->leftJoin('requests', 'disposal_forms.request_id', '=', 'requests.id')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id')
            ->leftJoin('users as preparer', 'disposal_forms.prepared_by', '=', 'preparer.id')
            ->leftJoin('employee_numbers as preparer_emp', 'preparer.employee_numbers_id', '=', 'preparer_emp.id')
            ->leftJoin('users as finalizer', 'disposal_forms.finalized_by', '=', 'finalizer.id')
            ->leftJoin('employee_numbers as finalizer_emp', 'finalizer.employee_numbers_id', '=', 'finalizer_emp.id')
            ->whereIn('disposal_forms.id', $formIds)
            ->get([
                'disposal_forms.*',
                // A plain column string, never a raw fragment: `requests.Note`
                // is a mixed-case column, and the query builder is what quotes
                // it. Handed to DB::raw() unquoted, PostgreSQL folds it to
                // `requests.note` and refuses the query (42703) — which is
                // exactly how this broke in production while SQLite, matching
                // names case-insensitively, kept passing the suite.
                'requests.Note as request_note',
                'requests.status as request_status',
                'requests.created_at as request_date',
                DB::raw('requester.email as requester_email'),
                DB::raw('requester_emp."Full_Name" as requester_name'),
                DB::raw('preparer.email as prepared_by_email'),
                DB::raw('preparer_emp."Full_Name" as prepared_by_name'),
                DB::raw('finalizer.email as finalized_by_email'),
                DB::raw('finalizer_emp."Full_Name" as finalized_by_name'),
            ])
            ->keyBy('id');

        $items = self::itemsForForms($formIds);

        $ordered = new Collection();

        foreach ($formIds as $formId) {
            if (! isset($rows[$formId])) {
                continue;
            }

            $form = self::present($rows[$formId]);
            $form->items = $items[$formId] ?? new Collection();

            $ordered->push($form);
        }

        return $ordered;
    }

    /** A single form row. */
    public static function find(int $id): ?object
    {
        if (! self::tablesReady()) {
            return null;
        }

        $form = DB::table('disposal_forms')->where('id', $id)->first();

        return $form ? self::present($form) : null;
    }

    public static function findForRequest(int $requestId): ?object
    {
        if (! self::tablesReady()) {
            return null;
        }

        $form = DB::table('disposal_forms')->where('request_id', $requestId)->first();

        return $form ? self::present($form) : null;
    }

    /**
     * The lines of one form, joined to their asset and its current holder.
     *
     * @return Collection<int, object>
     */
    public static function items(int $formId): Collection
    {
        if (! self::tablesReady()) {
            return new Collection();
        }

        $items = DB::table('disposal_form_items')
            ->leftJoin('assets', 'disposal_form_items.asset_id', '=', 'assets.id')
            ->where('disposal_form_items.form_id', $formId)
            ->orderBy('disposal_form_items.id')
            ->get([
                'disposal_form_items.*',
                'assets.Asset_code as asset_code',
                'assets.Asset_name as asset_name',
                'assets.Condition as asset_condition',
                'assets.Lifecycle_Status as asset_lifecycle',
            ]);

        return $items->map(fn ($item) => self::presentItem($item));
    }

    /**
     * Items for several forms at once, keyed by form id.
     *
     * @param  array<int, int>  $formIds
     * @return array<int, Collection<int, object>>
     */
    private static function itemsForForms(array $formIds): array
    {
        if (! $formIds) {
            return [];
        }

        $rows = DB::table('disposal_form_items')
            ->leftJoin('assets', 'disposal_form_items.asset_id', '=', 'assets.id')
            ->whereIn('disposal_form_items.form_id', $formIds)
            ->orderBy('disposal_form_items.id')
            ->get([
                'disposal_form_items.*',
                'assets.Asset_code as asset_code',
                'assets.Lifecycle_Status as asset_lifecycle',
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->form_id][] = self::presentItem($row);
        }

        return array_map(static fn (array $items) => new Collection($items), $grouped);
    }

    /** Fill in the names the views read, whatever the database's column case. */
    public static function present(object $form): object
    {
        $form->status_label  = self::label($form->status);
        $form->status_hint   = self::hint($form->status);
        $form->method_text   = DisposalCodes::methodText($form->method_key ?? null);
        $form->is_finalized  = self::isFinalized($form->status);
        $form->has_signed_file = ! empty($form->signed_file_path);
        $form->request_id      = (int) ($form->request_id ?? 0);

        return $form;
    }

    public static function presentItem(object $item): object
    {
        $item->condition_label = DisposalCodes::label($item->condition_code ?? null);
        $item->reason_label    = DisposalCodes::label($item->reason_code ?? null);
        $item->quantity        = (int) ($item->quantity ?? 1) ?: 1;

        return $item;
    }

    /* ─────────────────────── preparing a form ─────────────────────── */

    /**
     * Generate (or return) the form for an approved disposal request.
     *
     * Idempotent on purpose: the unique index on request_id, plus this lookup,
     * mean a second click, a retry after a timeout or two Admins at once all
     * end with one form — never two.
     *
     * Returns the form id, or null when there is nothing to prepare from.
     */
    public static function ensureForRequest(
        int $requestId,
        ?User $actor = null,
        ?string $approvedReason = null,
        ?string $expectedDate = null
    ): ?int {
        if (! self::tablesReady() || $requestId <= 0) {
            return null;
        }

        $existing = DB::table('disposal_forms')->where('request_id', $requestId)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $request = DB::table('requests')->where('id', $requestId)->first();

        if (! $request) {
            return null;
        }

        $assetIds = self::assetIdsOfRequest($request);

        if (! $assetIds) {
            return null;
        }

        $reason = $approvedReason ?: DisposalReason::derive($request->Note ?? null);
        $prefill = self::prefill($request, $assetIds, $reason, $expectedDate);

        try {
            return (int) DB::transaction(function () use ($requestId, $actor, $reason, $prefill) {
                $formId = (int) DB::table('disposal_forms')->insertGetId([
                    'request_id'             => $requestId,
                    'status'                 => self::STATUS_DRAFT,
                    'office_department'      => $prefill['office_department'],
                    'expected_disposal_date' => $prefill['expected_disposal_date'],
                    'approved_reason'        => $reason,
                    'prepared_by'            => $actor?->id,
                    'prepared_at'            => now(),
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);

                foreach ($prefill['items'] as $item) {
                    DB::table('disposal_form_items')->insert($item + [
                        'form_id'    => $formId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $formId;
            });
        } catch (Throwable $e) {
            // Two requests racing for the same form: the loser reads the winner's.
            $existing = DB::table('disposal_forms')->where('request_id', $requestId)->value('id');

            if ($existing) {
                return (int) $existing;
            }

            Log::error('Generating the disposal form for request #' . $requestId . ' failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The asset ids a request covers: request_items first, then the legacy
     * single-asset column, exactly as the approval route reads them.
     *
     * @return array<int, int>
     */
    public static function assetIdsOfRequest(object $request): array
    {
        $ids = DB::table('request_items')
            ->where('request_id', $request->id)
            ->pluck('asset_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $ids && ! empty($request->asset_id)) {
            $ids = [(int) $request->asset_id];
        }

        return $ids;
    }

    /**
     * Everything that can be filled in from records, and nothing that has to be
     * asked again.
     *
     * A field with no recorded source is left empty for the Asset Management
     * Office to complete: the method of disposal, and the sold-item block, are
     * decisions nobody has made yet.
     *
     * @param  array<int, int>  $assetIds
     * @return array{office_department: ?string, expected_disposal_date: ?string, items: array<int, array<string, mixed>>}
     */
    public static function prefill(object $request, array $assetIds, ?string $reason, ?string $expectedDate = null): array
    {
        $assets = DB::table('assets')
            ->whereIn('id', $assetIds)
            ->orderBy('id')
            ->get(['id', 'Asset_code', 'Asset_name', 'serial_Number', 'accusion_date', 'asset_location', 'Condition', 'user_id']);

        $office = self::officeFor($request, $assets);

        $items = [];

        foreach ($assets as $asset) {
            $items[] = [
                'asset_id'       => (int) $asset->id,
                'tag_no'         => $asset->Asset_code,
                'quantity'       => 1,
                'description'    => $asset->Asset_name,
                'serial_number'  => $asset->serial_Number,
                'date_acquired'  => $asset->accusion_date,
                'location'       => $asset->asset_location,
                'custodian'      => self::custodianFor((int) $asset->id, $asset->user_id),
                'condition_code' => DisposalCodes::conditionCodeFor($asset->Condition),
                'reason_code'    => DisposalCodes::reasonCodeFor($reason),
            ];
        }

        return [
            'office_department'      => $office,
            'expected_disposal_date' => $expectedDate,
            'items'                  => $items,
        ];
    }

    /**
     * The office the form is made out to: the requester's department, because
     * that is the office it is disposing from. Falls back to the first asset's
     * owner department.
     */
    private static function officeFor(object $request, Collection $assets): ?string
    {
        $departmentId = DB::table('users')->where('id', $request->user_id ?? 0)->value('department_id');

        if (! $departmentId && $assets->isNotEmpty()) {
            $ownerId = $assets->first()->user_id ?? null;
            $departmentId = $ownerId ? DB::table('users')->where('id', $ownerId)->value('department_id') : null;
        }

        if (! $departmentId) {
            return null;
        }

        return DB::table('departments')->where('id', $departmentId)->value('Name') ?: null;
    }

    /**
     * Who holds the asset: the current accountability holder, or — when there
     * is no accountability row — the account the asset is assigned to.
     */
    private static function custodianFor(int $assetId, $ownerId): ?string
    {
        $holder = DB::table('asset_accountability')
            ->join('users', 'asset_accountability.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('asset_accountability.asset_id', $assetId)
            ->orderByDesc('asset_accountability.Is_Current')
            ->orderByDesc('asset_accountability.Assign_date')
            ->value(DB::raw('COALESCE(employee_numbers."Full_Name", users.email)'));

        if ($holder) {
            return $holder;
        }

        if (! $ownerId) {
            return null;
        }

        return DB::table('users')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.id', $ownerId)
            ->value(DB::raw('COALESCE(employee_numbers."Full_Name", users.email)'));
    }

    /* ──────────────────────── saving a form ──────────────────────── */

    /**
     * Save the office's completion of the form.
     *
     * Validated server-side: the method key has to be one the form prints, every
     * code has to be on the form's legend, and an item id that does not belong
     * to this form is ignored rather than written.
     *
     * When every required field is present the form becomes ready to print.
     * A form that has already been printed is never pushed back to draft: its
     * paper copy exists, so the stage it reached is the truth.
     *
     * @return array{ok: bool, errors?: array<string, string>, missing?: array<int, string>, status?: string, message?: string}
     */
    public static function save(int $formId, array $input, ?User $actor = null): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'errors' => ['form' => 'The disposal form tables have not been created yet — run the database migration.']];
        }

        $form = DB::table('disposal_forms')->where('id', $formId)->first();

        if (! $form) {
            return ['ok' => false, 'errors' => ['form' => 'That disposal form no longer exists.']];
        }

        if (self::isFinalized($form->status)) {
            return ['ok' => false, 'errors' => ['form' => 'This disposal has already been finalized. The form is part of the record now and cannot be edited.']];
        }

        $itemIds = DB::table('disposal_form_items')->where('form_id', $formId)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        $data = self::validated($input, $itemIds);

        if ($data instanceof ValidatorInstance) {
            return ['ok' => false, 'errors' => $data->errors()->toArray()];
        }

        try {
            DB::transaction(function () use ($formId, $form, $data, $itemIds, $actor, &$missing) {
                DB::table('disposal_forms')->where('id', $formId)->update([
                    'office_department'      => self::blankToNull($data['office_department'] ?? null),
                    'expected_disposal_date' => self::blankToNull($data['expected_disposal_date'] ?? null),
                    'method_key'             => self::blankToNull($data['method_key'] ?? null),
                    'method_code'            => DisposalCodes::methodCode($data['method_key'] ?? null),
                    'sold_amount'            => self::blankToNull($data['sold_amount'] ?? null),
                    'sold_or_no'             => self::blankToNull($data['sold_or_no'] ?? null),
                    'sold_date'              => self::blankToNull($data['sold_date'] ?? null),
                    'gate_pass_no'           => self::blankToNull($data['gate_pass_no'] ?? null),
                    'gate_pass_date'         => self::blankToNull($data['gate_pass_date'] ?? null),
                    'remarks'                => self::blankToNull($data['remarks'] ?? null),
                    'prepared_by'            => $form->prepared_by ?: $actor?->id,
                    'prepared_at'            => $form->prepared_at ?: now(),
                    'updated_at'             => now(),
                ]);

                foreach (($data['items'] ?? []) as $itemId => $item) {
                    DB::table('disposal_form_items')->where('id', $itemId)->whereIn('id', $itemIds)->update([
                        'tag_no'         => self::blankToNull($item['tag_no'] ?? null),
                        'quantity'       => max(1, (int) ($item['quantity'] ?? 1)),
                        'description'    => self::blankToNull($item['description'] ?? null),
                        'serial_number'  => self::blankToNull($item['serial_number'] ?? null),
                        'date_acquired'  => self::blankToNull($item['date_acquired'] ?? null),
                        'location'       => self::blankToNull($item['location'] ?? null),
                        'custodian'      => self::blankToNull($item['custodian'] ?? null),
                        'condition_code' => self::blankToNull(strtoupper((string) ($item['condition_code'] ?? ''))),
                        'reason_code'    => self::blankToNull(strtoupper((string) ($item['reason_code'] ?? ''))),
                        'updated_at'     => now(),
                    ]);
                }
            });
        } catch (Throwable $e) {
            Log::error('Saving disposal form #' . $formId . ' failed: ' . $e->getMessage());

            return ['ok' => false, 'errors' => ['form' => 'The form could not be saved. Nothing was changed — please try again.']];
        }

        $fresh = DB::table('disposal_forms')->where('id', $formId)->first();
        $items = self::items($formId)->all();
        $missing = self::missingFields($fresh, $items);

        $status = (string) $fresh->status;

        if (! $missing && $status === self::STATUS_DRAFT) {
            $status = self::STATUS_READY;
        } elseif ($missing && $status === self::STATUS_READY) {
            $status = self::STATUS_DRAFT;
        }

        if ($status !== $fresh->status) {
            DB::table('disposal_forms')->where('id', $formId)->update([
                'status'     => $status,
                'ready_at'   => $status === self::STATUS_READY ? now() : $fresh->ready_at,
                'updated_at' => now(),
            ]);
        }

        self::audit(
            actor: $actor,
            requestId: (int) $fresh->request_id,
            assetId: null,
            actionType: 'UPDATE',
            notes: 'Official disposal form #' . $formId . ' saved (' . self::label($status) . ')',
            description: 'Disposal form #' . $formId . ' for request #' . $fresh->request_id . ' was saved by '
                . self::actorName($actor) . '. Stage: ' . self::label($status) . '.'
                . ($missing ? ' Still missing: ' . implode(', ', $missing) . '.' : '')
        );

        return [
            'ok'      => true,
            'status'  => $status,
            'missing' => $missing,
            'message' => $missing
                ? 'Form saved. Still missing: ' . implode(', ', $missing) . '.'
                : 'Form saved and ready to print.',
        ];
    }

    /**
     * Validate a save.
     *
     * @param  array<int, int>  $itemIds  the items that really belong to this form
     * @return array<string, mixed>|ValidatorInstance the data, or the failed validator
     */
    private static function validated(array $input, array $itemIds)
    {
        $rules = [
            'office_department'      => 'nullable|string|max:150',
            'expected_disposal_date' => 'nullable|date',
            'method_key'             => ['nullable', 'string', Rule::in(array_keys(DisposalCodes::METHOD_CODES))],
            'sold_amount'            => 'nullable|numeric|min:0|max:99999999',
            'sold_or_no'             => 'nullable|string|max:60',
            'sold_date'              => 'nullable|date',
            'gate_pass_no'           => 'nullable|string|max:60',
            'gate_pass_date'         => 'nullable|date',
            'remarks'                => 'nullable|string|max:2000',
        ];

        // Only the items this form owns are read at all.
        $submitted = is_array($input['items'] ?? null) ? $input['items'] : [];
        $submitted = array_intersect_key($submitted, array_flip($itemIds));

        foreach (array_keys($submitted) as $itemId) {
            $rules['items.' . $itemId . '.tag_no']         = 'nullable|string|max:60';
            $rules['items.' . $itemId . '.quantity']       = 'nullable|integer|min:1|max:100000';
            $rules['items.' . $itemId . '.description']    = 'nullable|string|max:255';
            $rules['items.' . $itemId . '.serial_number']  = 'nullable|string|max:150';
            $rules['items.' . $itemId . '.date_acquired']  = 'nullable|date';
            $rules['items.' . $itemId . '.location']       = 'nullable|string|max:150';
            $rules['items.' . $itemId . '.custodian']      = 'nullable|string|max:150';
            $rules['items.' . $itemId . '.condition_code'] = 'nullable|string|max:4';
            $rules['items.' . $itemId . '.reason_code']    = 'nullable|string|max:4';
        }

        $data = array_merge($input, ['items' => $submitted]);

        $validator = \Illuminate\Support\Facades\Validator::make($data, $rules, [
            'method_key.in' => 'Choose a method of disposal from the official form.',
            'expected_disposal_date.date' => 'The expected date of disposal is not a valid date.',
            'sold_amount.numeric' => 'The amount must be a number.',
            'sold_date.date' => 'The date of sale is not a valid date.',
            'gate_pass_date.date' => 'The gate pass date is not a valid date.',
            'items.*.quantity.integer' => 'The quantity must be a whole number.',
            'items.*.date_acquired.date' => 'The date acquired is not a valid date.',
        ]);

        // The codes have to come from the form's own legend.
        foreach ($submitted as $itemId => $item) {
            foreach (['condition_code', 'reason_code'] as $field) {
                $code = trim((string) ($item[$field] ?? ''));

                if ($code !== '' && ! DisposalCodes::isCode($code)) {
                    $validator->after(function (ValidatorInstance $validator) use ($itemId, $field, $code) {
                        $validator->errors()->add(
                            'items.' . $itemId . '.' . $field,
                            '"' . $code . '" is not a code on the official disposal form.'
                        );
                    });
                }
            }
        }

        return $validator->fails() ? $validator : $validator->validated();
    }

    /**
     * Everything the form still needs before it may be printed.
     *
     * Only fields the official form prints are required, and only where the
     * office is expected to state something: serial number, date acquired,
     * location and custodian are recorded when the records have them.
     *
     * @param  array<int, object>  $items
     * @return array<int, string> human labels, in form order
     */
    public static function missingFields(object $form, array $items): array
    {
        $missing = [];

        if (! self::blankToNull($form->office_department ?? null)) {
            $missing[] = 'Office/Department';
        }

        if (! self::blankToNull($form->expected_disposal_date ?? null)) {
            $missing[] = 'Expected Date of Disposal';
        }

        if (! DisposalCodes::isMethod($form->method_key ?? null)) {
            $missing[] = 'Method of Disposal Code';
        } elseif (DisposalCodes::isSoldMethod($form->method_key ?? null)) {
            if ($form->sold_amount === null || $form->sold_amount === '') {
                $missing[] = 'Amount (sold items)';
            }

            if (! self::blankToNull($form->sold_or_no ?? null)) {
                $missing[] = 'OR No. (sold items)';
            }
        }

        foreach (array_values($items) as $index => $item) {
            $row = $index + 1;

            foreach ([
                'tag_no'         => 'Tag No.',
                'description'    => 'Description',
                'condition_code' => 'Condition Code',
                'reason_code'    => 'Reason for Disposal Code',
            ] as $field => $label) {
                if (! self::blankToNull($item->{$field} ?? null)) {
                    $missing[] = $label . ' (row ' . $row . ')';
                }
            }
        }

        return $missing;
    }

    /* ─────────────────────────── printing ─────────────────────────── */

    /**
     * Record that a form has been printed, and which forms to print.
     *
     * Printing is evidence of printing only. It is the point at which the paper
     * leaves the office, and it deliberately changes nothing about any asset:
     * the signature does not exist yet and the disposal has not happened.
     *
     * Re-printing is allowed (a copy for the file) and counted.
     *
     * @param  array<int, int>  $formIds
     * @return array{printed: array<int, int>, refused: array<int, string>}
     */
    public static function recordPrint(array $formIds, ?User $actor = null): array
    {
        $printed = [];
        $refused = [];

        if (! self::tablesReady()) {
            return ['printed' => [], 'refused' => ['The disposal form tables have not been created yet — run the database migration.']];
        }

        foreach (array_unique(array_map('intval', $formIds)) as $formId) {
            if ($formId <= 0) {
                continue;
            }

            $form = DB::table('disposal_forms')->where('id', $formId)->first();

            if (! $form) {
                $refused[] = 'Form #' . $formId . ' no longer exists.';

                continue;
            }

            if (self::isFinalized($form->status)) {
                $refused[] = 'Form #' . $formId . ' is finalized — the disposal is complete and the form is part of the record.';

                continue;
            }

            if (! self::isPrintable($form->status)) {
                $missing = self::missingFields($form, self::items($formId)->all());
                $refused[] = 'Form #' . $formId . ' is not ready to print yet'
                    . ($missing ? ' — still missing: ' . implode(', ', $missing) . '.' : '.');

                continue;
            }

            $alreadyPrinted = $form->status === self::STATUS_PRINTED || $form->status === self::STATUS_SIGNED;

            DB::table('disposal_forms')->where('id', $formId)->update([
                'status'      => $alreadyPrinted ? $form->status : self::STATUS_PRINTED,
                'printed_at'  => $form->printed_at ?: now(),
                'printed_by'  => $actor?->id ?: $form->printed_by,
                'print_count' => ((int) $form->print_count) + 1,
                'updated_at'  => now(),
            ]);

            $printed[] = $formId;

            self::audit(
                actor: $actor,
                requestId: (int) $form->request_id,
                assetId: null,
                actionType: 'UPDATE',
                notes: 'Official disposal form #' . $formId . ' printed',
                description: 'Disposal form #' . $formId . ' was printed by ' . self::actorName($actor)
                    . '. Printing does not confirm any signature and does not dispose of the asset.'
            );
        }

        return ['printed' => $printed, 'refused' => $refused];
    }

    /* ──────────────────────── signed form ──────────────────────── */

    /**
     * Record that the signed form came back from Accountancy.
     *
     * The action is the explicit record of receipt; the scanned copy is
     * optional evidence of it. Neither is a signature captured by this system,
     * and neither finalizes anything — the Admin still has to finalize the
     * disposal separately.
     *
     * @return array{ok: bool, message: string, uploaded?: bool, warning?: string}
     */
    public static function recordSigned(int $formId, array $input, ?UploadedFile $file = null, ?User $actor = null): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The disposal form tables have not been created yet — run the database migration.'];
        }

        $form = DB::table('disposal_forms')->where('id', $formId)->first();

        if (! $form) {
            return ['ok' => false, 'message' => 'That disposal form no longer exists.'];
        }

        if (self::isFinalized($form->status)) {
            return ['ok' => false, 'message' => 'This disposal has already been finalized.'];
        }

        if (! in_array($form->status, [self::STATUS_PRINTED, self::STATUS_SIGNED], true)) {
            return ['ok' => false, 'message' => 'Print the form first: the signed copy cannot come back before the form has been printed. Current stage: ' . self::label($form->status) . '.'];
        }

        $data = \Illuminate\Support\Facades\Validator::make($input, [
            'signed_notes'    => 'nullable|string|max:1000',
            'received_date'   => 'nullable|date',
        ], [
            'received_date.date' => 'The date the signed form was received is not a valid date.',
        ])->validate();

        $update = [
            'status'             => self::STATUS_SIGNED,
            'signed_received_at' => now(),
            'signed_received_by' => $actor?->id,
            'signed_notes'       => self::blankToNull($data['signed_notes'] ?? null),
            'updated_at'         => now(),
        ];

        if (! empty($data['received_date'])) {
            $update['signed_received_at'] = \Illuminate\Support\Carbon::parse($data['received_date'])->startOfDay();
        }

        $uploaded = false;
        $warning  = null;

        if ($file) {
            $stored = self::storeSignedCopy($formId, $file);

            if ($stored === null) {
                $warning = 'The signed copy could not be stored, so the receipt was recorded without a file. Check the storage configuration and upload it again.';
            } else {
                $uploaded = true;
                $update['signed_file_name'] = $stored['name'];
                $update['signed_file_path'] = $stored['path'];
                $update['signed_file_size'] = $stored['size'];
                $update['signed_file_mime'] = $stored['mime'];

                // Replacing an earlier scan: the old object is removed only
                // after the new path is recorded.
                if (! empty($form->signed_file_path) && $form->signed_file_path !== $stored['path']) {
                    self::deleteSignedCopy($form->signed_file_path);
                }
            }
        }

        DB::table('disposal_forms')->where('id', $formId)->update($update);

        self::audit(
            actor: $actor,
            requestId: (int) $form->request_id,
            assetId: null,
            actionType: 'UPDATE',
            notes: 'Signed disposal form #' . $formId . ' recorded as received',
            description: 'The signed official disposal form #' . $formId . ' for request #' . $form->request_id
                . ' was recorded as received by ' . self::actorName($actor)
                . ($uploaded ? ' with a scanned copy attached' : '')
                . '. The disposal itself has not been finalized yet.'
        );

        return [
            'ok'       => true,
            'uploaded' => $uploaded,
            'warning'  => $warning,
            'message'  => 'Signed form recorded as received.'
                . ($uploaded ? ' The scanned copy is attached.' : '')
                . ' The disposal can now be finalized.',
        ];
    }

    /**
     * Store a scanned signed copy on the private disk.
     *
     * @return array{name: string, path: string, size: int, mime: string}|null
     */
    private static function storeSignedCopy(int $formId, UploadedFile $file): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $name = 'signed-form-' . $formId . '-' . Str::random(16) . '.' . $extension;
        $path = self::UPLOAD_FOLDER . '/' . $name;

        try {
            $stored = $file->storeAs(self::UPLOAD_FOLDER, $name, self::UPLOAD_DISK);
        } catch (Throwable $e) {
            Log::error('Storing the signed disposal form #' . $formId . ' threw: ' . $e->getMessage());

            return null;
        }

        if (! is_string($stored) || $stored === '') {
            Log::error('The signed disposal form #' . $formId . ' could not be written to the "' . self::UPLOAD_DISK . '" disk.');

            return null;
        }

        return [
            'name' => $file->getClientOriginalName() ?: $name,
            'path' => $stored,
            'size' => (int) $file->getSize(),
            'mime' => (string) ($file->getClientMimeType() ?: 'application/octet-stream'),
        ];
    }

    /** Forget a stored signed copy (replaced by a newer scan). */
    public static function deleteSignedCopy(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '') {
            return;
        }

        try {
            Storage::disk(self::UPLOAD_DISK)->delete($path);
        } catch (Throwable $e) {
            Log::warning('Deleting a replaced signed disposal form failed: ' . $e->getMessage());
        }
    }

    /** The stored signed copy as a readable stream, or null. */
    public static function signedCopyPath(object $form): ?string
    {
        $path = trim((string) ($form->signed_file_path ?? ''));

        if ($path === '') {
            return null;
        }

        try {
            return Storage::disk(self::UPLOAD_DISK)->exists($path) ? $path : null;
        } catch (Throwable $e) {
            Log::warning('Reading the signed disposal form failed: ' . $e->getMessage());

            return null;
        }
    }

    /* ───────────────────────── finalization ───────────────────────── */

    /**
     * Retire the assets: the one and only step that makes them Disposed.
     *
     * Refused unless the request is approved and the signed form has been
     * recorded, and unless the Admin has confirmed which assets this covers.
     * Everything happens in one transaction — the disposal records, the asset
     * statuses, the audit entries and the form's own stage — so a failure leaves
     * the asset, the disposal history and the form agreeing with each other.
     *
     * @return array{ok: bool, message: string, assets?: array<int, string>, disposals?: array<int, int>}
     */
    public static function finalize(int $formId, array $input, ?User $actor = null): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The disposal form tables have not been created yet — run the database migration.'];
        }

        $form = DB::table('disposal_forms')->where('id', $formId)->first();

        if (! $form) {
            return ['ok' => false, 'message' => 'That disposal form no longer exists.'];
        }

        // Finalizing twice must be impossible: the assets would be re-stamped,
        // the history would be rewritten and the disposal would be recorded
        // again as if it had just happened.
        if (self::isFinalized($form->status)) {
            return ['ok' => false, 'message' => 'This disposal has already been finalized. Nothing was changed.'];
        }

        if ($form->status !== self::STATUS_SIGNED) {
            return [
                'ok'      => false,
                'message' => 'The disposal cannot be finalized yet. Current stage: ' . self::label($form->status)
                    . '. ' . self::hint($form->status),
            ];
        }

        if (empty($input['confirm'])) {
            return ['ok' => false, 'message' => 'Confirm the disposal first: check the assets listed and tick the confirmation box.'];
        }

        $request = DB::table('requests')->where('id', $form->request_id)->first();

        if (! $request || strtolower((string) $request->status) !== 'approved') {
            return ['ok' => false, 'message' => 'The disposal request behind this form is not approved any more, so nothing can be disposed of.'];
        }

        $items = self::items($formId)->all();

        if (! $items) {
            return ['ok' => false, 'message' => 'This form has no asset lines, so there is nothing to dispose of.'];
        }

        $missingAssets = [];

        foreach ($items as $item) {
            if (empty($item->asset_id) || ! DB::table('assets')->where('id', $item->asset_id)->exists()) {
                $missingAssets[] = $item->tag_no ?: ('row ' . $item->id);
            }
        }

        if ($missingAssets) {
            return [
                'ok'      => false,
                'message' => 'These assets no longer exist, so their disposal cannot be recorded: '
                    . implode(', ', $missingAssets) . '. Remove them from the form or ask the system administrator.',
            ];
        }

        $validated = \Illuminate\Support\Facades\Validator::make($input, [
            'actual_disposal_date' => 'nullable|date',
        ], [
            'actual_disposal_date.date' => 'The date of disposal is not a valid date.',
        ])->validate();

        $disposalDate = $validated['actual_disposal_date'] ?? now()->toDateString();
        $approvedBy   = $actor?->email ?? $actor?->display_name ?? 'Admin';
        $disposals    = [];
        $codes        = [];

        try {
            DB::transaction(function () use ($form, $formId, $request, $items, $actor, $approvedBy, $disposalDate, &$disposals, &$codes) {
                foreach ($items as $item) {
                    $assetId = (int) $item->asset_id;

                    $reason = DisposalCodes::storedReasonFor(
                        $item->reason_code ?? null,
                        $form->approved_reason ?: ($request->Note ?? null)
                    );

                    $disposalId = Disposals::createFromRequest(
                        (int) $request->id,
                        $assetId,
                        $approvedBy,
                        $reason,
                        $request->Note,
                        'Disposal request #' . $request->id . ' finalized under official disposal form #' . $formId,
                        $disposalDate
                    );

                    // null => the disposal of this asset was already recorded.
                    if ($disposalId !== null) {
                        $disposals[$assetId] = $disposalId;
                    }

                    DB::table('assets')->where('id', $assetId)->update([
                        'Lifecycle_Status' => Disposals::DISPOSED_STATUS,
                        'updated_at'       => now(),
                    ]);

                    $code = $item->tag_no ?: ('asset #' . $assetId);

                    self::audit(
                        actor: $actor,
                        requestId: (int) $request->id,
                        assetId: $assetId,
                        actionType: 'DISPOSAL',
                        notes: 'Disposal finalized — disposal record #' . ($disposalId ?? 'existing')
                            . ', form #' . $formId . ', reason code ' . ($item->reason_code ?: '—'),
                        description: 'Disposal form #' . $formId . ' finalized by ' . self::actorName($actor)
                            . ': asset ' . $code . ' was marked disposed of.',
                        // Inside the transaction opened above: an entry that
                        // cannot be written rolls the retirement back with it,
                        // rather than leaving a disposed asset and no trail.
                        ownTransaction: false
                    );

                    $codes[$assetId] = $code;
                }

                DB::table('disposal_forms')->where('id', $formId)->update([
                    'status'               => self::STATUS_FINALIZED,
                    'actual_disposal_date' => $disposalDate,
                    'finalized_at'         => now(),
                    'finalized_by'         => $actor?->id,
                    'updated_at'           => now(),
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Finalizing disposal form #' . $formId . ' failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'The disposal could not be finalized. Nothing was changed — the assets are not marked disposed of.'];
        }

        // Told only once: the transaction above rolls back on a repeated call,
        // so this can never send a second notice for the same disposal.
        $label = count($codes) === 1
            ? reset($codes)
            : (implode(', ', array_slice(array_values($codes), 0, -1)) . ' and ' . end($codes));

        Notifications::create(
            (int) $request->user_id,
            'Disposal Completed',
            'The disposal of ' . ($label ?: 'your asset') . ' has been finalized and recorded by the Asset Management Office.',
            'DISPOSAL',
            (int) $request->id,
            'disposal'
        );

        return [
            'ok'       => true,
            'assets'   => $codes,
            'disposals' => $disposals,
            'message'  => 'Disposal finalized. ' . implode(', ', $codes) . ' '
                . (count($codes) === 1 ? 'is' : 'are') . ' now marked as disposed of and recorded on the Disposal page.',
        ];
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    /**
     * One audit entry, in the existing log.
     *
     * Call sites name their arguments. This helper outgrew a positional call:
     * one argument landing in the wrong slot still writes a plausible-looking
     * row, which is how a finalization entry once recorded the asset id as its
     * action_type — accepted by SQLite, where the enum sits on the column, and
     * refused by PostgreSQL's check constraint (23514), which turned the whole
     * finalization into a 500. Named arguments make that shift impossible to
     * write by accident.
     *
     * @param  int|null  $assetId  the asset this entry is about, when it is about
     *                             one: the retirement entries name theirs.
     * @param  bool  $ownTransaction  false when already inside a transaction
     *                                whose outcome this entry must share. The
     *                                failure is then re-thrown, so the caller's
     *                                transaction rolls back instead of retiring
     *                                an asset with no trail left behind.
     */
    private static function audit(
        ?User $actor,
        int $requestId,
        ?int $assetId,
        string $actionType,
        string $notes,
        string $description,
        bool $ownTransaction = true
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => $requestId > 0 ? $requestId : null,
                'asset_id'           => $assetId,
                'action_type'        => $actionType,
                'notes'              => $notes,
                'action_description' => $description,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (Throwable $e) {
            // The workflow step already happened; losing the note must not undo
            // it. This is reported, never swallowed silently.
            Log::warning('Disposal form audit entry failed (action=' . $actionType . ', request=' . $requestId . '): ' . $e->getMessage());

            if (! $ownTransaction) {
                throw $e;
            }
        }
    }

    private static function actorName(?User $actor): string
    {
        if (! $actor) {
            return 'the system';
        }

        return trim((string) ($actor->display_name ?? '')) ?: (string) $actor->email;
    }

    private static function blankToNull($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === '' || $value === null) ? null : (string) $value;
    }
}
