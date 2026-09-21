<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the read-only, user-facing view of a single asset.
 *
 * Used by both the employee and department-head asset detail pages so the two
 * sides can never drift apart. Everything here is monitoring information —
 * administrative control (maintenance completion, disposal, replacement,
 * pullout, accountability changes) stays in the admin area.
 */
class AssetDetails
{
    /** Lifecycle statuses a user still cares about. Pullout/Disposal drop off. */
    public const USER_VISIBLE_STATUSES = ['Acquired', 'Active', 'For Checking', 'For Repair', 'For Replacement'];

    /**
     * Load a single asset with its owner/assignment info and primary photo.
     */
    public static function load(int $assetId): ?object
    {
        $asset = DB::table('assets')->where('id', $assetId)->first();
        if (! $asset) {
            return null;
        }

        $owner = null;
        if (! empty($asset->user_id)) {
            $owner = DB::table('users')
                ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
                ->where('users.id', $asset->user_id)
                ->select('employee_numbers.Full_Name as full_name', 'users.department_id')
                ->first();
        }

        $asset->full_name     = $owner->full_name ?? null;
        $asset->department_id = $owner->department_id ?? null;
        $asset->department_name = $asset->department_id
            ? DB::table('departments')->where('id', $asset->department_id)->value('Name')
            : null;

        $asset->image_url = Media::url(
            DB::table('asset_files')->where('Asset_id', $assetId)->orderBy('Asset_file_ID')->value('url')
        );

        return $asset;
    }

    /**
     * Derive every panel the detail page needs for a loaded asset.
     *
     * @return array{
     *     maintenance: array, lifespan: array, warranty: array,
     *     repairs: \Illuminate\Support\Collection, openRepair: ?object,
     *     repairHistory: array, maintenanceHistory: array, timeline: array,
     *     requests: \Illuminate\Support\Collection, qrUrl: ?string,
     *     lifecycle: array
     * }
     */
    public static function build(object $asset): array
    {
        $today = Carbon::today();

        $repairs = DB::table('repairs')
            ->where('Assets_id', $asset->id)
            ->orderByDesc('Repair_Date')
            ->get();

        $evaluations = DB::table('repair_evaluations')
            ->whereIn('repair_id', $repairs->pluck('Repair_id')->map(fn ($id) => (int) $id)->all() ?: [0])
            ->get()
            ->keyBy('repair_id');

        $requests = DB::table('request_items')
            ->join('requests', 'request_items.request_id', '=', 'requests.id')
            ->where('request_items.asset_id', $asset->id)
            ->orderBy('requests.created_at')
            ->get([
                'requests.id',
                'requests.request_type',
                'requests.status',
                'requests.Note',
                'requests.created_at',
                'requests.updated_at',
            ]);

        $maintenanceLogs = DB::table('audit_logs')
            ->where('asset_id', $asset->id)
            ->whereRaw('lower(coalesce(action_description, \'\')) like ?', ['%preventive maintenance%'])
            ->orderBy('created_at')
            ->get(['id', 'action_description', 'created_at']);

        $openRepair = $repairs->first(
            fn ($r) => in_array($r->status, ['Pending', 'In Progress'], true)
        );

        return [
            'maintenance'         => self::maintenance($asset, $today),
            'lifespan'            => self::lifespan($asset, $today),
            'warranty'            => self::warranty($asset, $today),
            'lifecycle'           => self::lifecycle($asset),
            'repairs'             => $repairs,
            'openRepair'          => $openRepair,
            'repairHistory'       => self::repairHistory($repairs, $evaluations),
            'maintenanceHistory'  => $maintenanceLogs,
            'timeline'            => self::timeline($asset, $requests, $repairs, $maintenanceLogs),
            'requests'            => $requests,
            'qrUrl'               => Media::url($asset->qr_code_path),
        ];
    }

    /**
     * Scheduled maintenance state. Maintenance due is NOT a repair trigger —
     * the asset stays usable while upkeep is pending.
     */
    protected static function maintenance(object $asset, Carbon $today): array
    {
        $months = (int) ($asset->maintenance_interval ?? 0);
        $last   = $asset->last_maintenance_date ? Carbon::parse($asset->last_maintenance_date) : null;
        $next   = $asset->next_maintenance_date ? Carbon::parse($asset->next_maintenance_date) : null;

        $status = 'Not scheduled';
        if ($next) {
            if ($next->isSameDay($today)) {
                $status = 'Due';
            } elseif ($next->lessThan($today)) {
                $status = 'Overdue';
            } else {
                $status = 'Upcoming';
            }
        }

        return [
            'interval_months' => $months ?: null,
            'interval_label'  => self::monthsLabel($months),
            'last'            => $last,
            'next'            => $next,
            'status'          => $status,
            'days_until'      => $next ? (int) $today->diffInDays($next, false) : null,
        ];
    }

    /**
     * Expected lifespan. Expiry triggers evaluation (For Checking) — never an
     * automatic disposal.
     */
    protected static function lifespan(object $asset, Carbon $today): array
    {
        $months     = (int) ($asset->lifespan_months ?? 0);
        $acquired   = $asset->accusion_date ? Carbon::parse($asset->accusion_date) : null;
        $expiration = $asset->expiration_date ? Carbon::parse($asset->expiration_date) : null;

        $status = 'Not set';
        if ($expiration) {
            $status = $expiration->lessThan($today) ? 'Expired' : 'Within Lifespan';
        }

        return [
            'months'      => $months ?: null,
            'label'       => self::monthsLabel($months),
            'acquired'    => $acquired,
            'expires'     => $expiration,
            'status'      => $status,
            'days_until'  => $expiration ? (int) $today->diffInDays($expiration, false) : null,
            'needs_review' => $status === 'Expired',
        ];
    }

    /**
     * Warranty window, derived from the acquisition date when it is configured.
     */
    protected static function warranty(object $asset, Carbon $today): array
    {
        $months   = (int) ($asset->warranty_months ?? 0);
        $acquired = $asset->accusion_date ? Carbon::parse($asset->accusion_date) : null;
        $expires  = null;

        if ($months > 0 && $acquired) {
            $expires = $acquired->copy()->addMonths($months);
        }

        $status = 'Not specified';
        if ($expires) {
            $status = $expires->lessThan($today) ? 'Expired' : 'Active';
        } elseif ($months > 0) {
            $status = 'Not started';
        }

        return [
            'months'  => $months ?: null,
            'label'   => self::monthsLabel($months),
            'expires' => $expires,
            'status'  => $status,
        ];
    }

    /**
     * How the current lifecycle status should read to a regular user.
     */
    protected static function lifecycle(object $asset): array
    {
        $status = $asset->Lifecycle_Status ?? 'Unknown';

        $explain = [
            'Acquired'         => 'Recorded in the inventory and being prepared for assignment.',
            'Active'           => 'Assigned to you and in normal use.',
            'For Checking'     => 'Scheduled for evaluation by the Asset Management Office (maintenance due or lifespan reached).',
            'For Repair'       => 'Reported with a problem — the Asset Management Office is handling the repair.',
            'For Replacement'  => 'Evaluated as better replaced than repaired. Replacement is in process.',
            'Pullout'          => 'No longer assigned to you. The record is retained in the Asset Management Office inventory.',
            'Disposal'         => 'Retired from service. The record is retained for audit and reporting.',
        ];

        return [
            'status'  => $status,
            'explain' => $explain[$status] ?? 'Status recorded by the Asset Management Office.',
            'active'  => in_array($status, self::USER_VISIBLE_STATUSES, true),
        ];
    }

    /**
     * Repair history in the language of the employee: what went wrong, when,
     * where it stands, and what came out of it. Admin remarks and the
     * inspecting/recording administrator stay on the admin side.
     */
    protected static function repairHistory($repairs, $evaluations): array
    {
        return $repairs->map(function ($repair) use ($evaluations) {
            $evaluation = $evaluations->get($repair->Repair_id);

            return [
                'id'          => $repair->Repair_id,
                'description' => $repair->Repair_Description,
                'date'        => $repair->Repair_Date ? Carbon::parse($repair->Repair_Date) : null,
                'status'      => $repair->status,
                'result'      => $repair->Repair_result ?: ($evaluation->repair_result ?? null),
                'cost'        => $evaluation->repair_cost ?? $repair->Repair_Cost,
                'parts'       => $evaluation->parts_replaced ?? null,
                'has_evaluation' => (bool) $evaluation,
            ];
        })->all();
    }

    /**
     * Simplified, user-safe chronology: registration, assignment, requests,
     * repair progress, completed maintenance and the next scheduled upkeep.
     * Only action descriptions are exposed — never internal admin notes.
     */
    protected static function timeline(object $asset, $requests, $repairs, $maintenanceLogs): array
    {
        $events = [];

        $push = function (?Carbon $date, string $title, string $description, string $icon, string $tone) use (&$events) {
            if (! $date) {
                return;
            }
            $events[] = [
                'date'        => $date,
                'title'       => $title,
                'description' => $description,
                'icon'        => $icon,
                'tone'        => $tone,
            ];
        };

        $push(
            $asset->created_at ? Carbon::parse($asset->created_at) : null,
            'Asset Registered',
            'The asset was recorded in the institutional inventory.',
            'ri-add-circle-line',
            'gold'
        );

        $push(
            $asset->accusion_date ? Carbon::parse($asset->accusion_date) : null,
            'Assigned to ' . ($asset->full_name ?: 'an employee'),
            'Accountability for this asset was recorded.',
            'ri-user-follow-line',
            'navy'
        );

        foreach ($requests as $request) {
            $type = $request->request_type ?: 'Asset';
            $push(
                $request->created_at ? Carbon::parse($request->created_at) : null,
                $type . ' Request Submitted',
                'A ' . strtolower($type) . ' request was filed for this asset. Current status: '
                    . ($request->status ?: 'Pending') . '.',
                $type === 'Repair' ? 'ri-tools-line' : 'ri-file-list-3-line',
                'gold'
            );
        }

        foreach ($repairs as $repair) {
            $status = $repair->status ?: 'Pending';

            if ($status === 'In Progress') {
                $push(
                    $repair->created_at ? Carbon::parse($repair->created_at) : null,
                    'Repair In Progress',
                    'Servicing has started on this asset.',
                    'ri-tools-fill',
                    'amber'
                );
            } elseif ($status === 'Completed') {
                $push(
                    $repair->updated_at ? Carbon::parse($repair->updated_at) : null,
                    'Repair Completed',
                    'Servicing finished and the outcome was recorded.',
                    'ri-checkbox-circle-line',
                    'green'
                );
            } elseif ($status === 'Cancelled') {
                $push(
                    $repair->updated_at ? Carbon::parse($repair->updated_at) : null,
                    'Repair Cancelled',
                    'This repair request was closed without a repair.',
                    'ri-close-circle-line',
                    'muted'
                );
            }
        }

        foreach ($maintenanceLogs as $log) {
            $push(
                $log->created_at ? Carbon::parse($log->created_at) : null,
                'Preventive Maintenance Completed',
                'Scheduled upkeep was performed and recorded by the Asset Management Office.',
                'ri-shield-check-line',
                'green'
            );
        }

        $push(
            $asset->next_maintenance_date ? Carbon::parse($asset->next_maintenance_date) : null,
            'Scheduled Maintenance',
            'Next preventive maintenance is due on this date.',
            'ri-calendar-check-line',
            'muted'
        );

        usort($events, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return array_slice($events, -25);
    }

    protected static function monthsLabel(int $months): string
    {
        if ($months <= 0) {
            return 'Not set';
        }

        if ($months % 12 === 0) {
            $years = intdiv($months, 12);

            return $years . ' year' . ($years > 1 ? 's' : '');
        }

        return $months . ' month' . ($months > 1 ? 's' : '');
    }
}
