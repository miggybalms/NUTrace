<?php

namespace App\Http\Controllers;

use App\Support\AssetTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transfer / Employee Relocation.
 *
 * The page answers one question: "Juan is leaving his post — which of his
 * assets go with him, and who takes over?" The Admin picks the employee, the
 * system lists everything currently assigned to them, the Admin picks the
 * receiving employee, confirms, and the assets change custodian without ever
 * becoming new assets.
 */
class TransferController extends Controller
{
    /** The transfer page: employees on one tab, transfer history on the other. */
    public function index(Request $request)
    {
        $labels = AssetTransfer::userLabels();
        $custody = AssetTransfer::custodyByUser();
        $held = AssetTransfer::heldCounts();
        $reassigned = array_flip(AssetTransfer::reassignedUserIds());

        $employees = [];

        foreach ($labels as $userId => $label) {
            $assets = ($custody->get($userId) ?? collect())->map(fn ($asset) => [
                'id'        => (int) $asset->id,
                'code'      => $asset->Asset_code,
                'name'      => $asset->Asset_name,
                'category'  => $asset->Category,
                'condition' => $asset->Condition,
                'lifecycle' => $asset->Lifecycle_Status,
                'location'  => $asset->asset_location,
                'acquired'  => $asset->accusion_date,
            ])->values()->all();

            $totalHeld = (int) ($held[$userId] ?? 0);
            $assigned = count($assets);

            $employees[] = $label + [
                'status'                 => isset($reassigned[$userId]) ? 'reassigned' : 'current',
                'assigned_count'         => $assigned,
                'held_count'             => $totalHeld,
                'not_transferable_count' => max($totalHeld - $assigned, 0),
                'assets'                 => $assets,
            ];
        }

        $transfers = AssetTransfer::history()->all();

        $stats = [
            'employees_with_assets' => count(array_filter($employees, fn ($e) => $e['assigned_count'] > 0)),
            'assets_assigned'       => array_sum(array_column($employees, 'assigned_count')),
            'transfers_completed'   => count($transfers),
            'assets_transferred'    => array_sum(array_column($transfers, 'asset_count')),
        ];

        $departments = collect($employees)
            ->pluck('department')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('admin.transfer.transfer', [
            'employees'       => $employees,
            'transfers'       => $transfers,
            'departments'     => $departments,
            'reasons'         => AssetTransfer::REASONS,
            'stats'           => $stats,
            'openTransferId'  => (int) $request->query('transfer', 0),
        ]);
    }

    /** Confirm the relocation. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_user_id' => 'required|integer|exists:users,id',
            'to_user_id'   => 'required|integer|exists:users,id|different:from_user_id',
            'asset_ids'    => 'required|array|min:1',
            'asset_ids.*'  => 'integer|exists:assets,id',
            'reason'       => 'required|string|max:100',
            'notes'        => 'nullable|string|max:1000',
        ], [
            'to_user_id.different' => 'The receiving employee must be someone other than the employee handing the assets over.',
            'asset_ids.required'   => 'Select at least one asset to transfer.',
        ]);

        try {
            $result = AssetTransfer::create(
                (int) $validated['from_user_id'],
                (int) $validated['to_user_id'],
                $validated['asset_ids'],
                $validated['reason'],
                $validated['notes'] ?? null,
                Auth::id()
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            Log::error('Asset transfer failed: ' . $e->getMessage(), [
                'from_user_id' => $validated['from_user_id'],
                'to_user_id'   => $validated['to_user_id'],
                'assets'       => count($validated['asset_ids']),
            ]);

            return back()->withErrors([
                'transfer' => 'The transfer could not be completed. Please reload the page and try again.',
            ])->withInput();
        }

        // The same confirmation reached the server twice (a double click, a
        // retried POST, a tab submitted again). Nothing was changed the second
        // time because there was nothing left to change — say so plainly rather
        // than reporting a failure for work that is already done.
        if ($result['replayed']) {
            return redirect('/admin/transfer')
                ->with('notice', 'This transfer was already completed, so nothing was changed — the assets are already with the receiving employee.')
                ->with('transfer_receipt', ['id' => $result['id']]);
        }

        return redirect('/admin/transfer')
            ->with('success', 'Transfer completed. The assets now belong to the receiving employee — their asset codes, QR codes and history are unchanged.')
            ->with('transfer_receipt', ['id' => $result['id']]);
    }
}
