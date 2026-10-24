<?php

namespace App\Http\Controllers;

use App\Support\Import\AssetImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Existing Asset Import page.
 *
 * Every endpoint is a small step of one workflow: upload, confirm the column
 * mapping, check a window of rows, save corrections, import a window, retry.
 * The page drives them one after another, and each one re-reads state from the
 * database, so a page reload never loses an import that is halfway done.
 *
 * Access is the Admin area's: /admin/* is already restricted to the Asset
 * Management Office by RequireAdminArea, and each route here is behind `auth`.
 */
class AssetImportController extends Controller
{
    public function index()
    {
        $batch = AssetImport::latest(auth()->user());
        $batchId = $batch ? (int) $batch->id : null;

        return view('admin.assets.import', [
            'batch'       => AssetImport::payload($batch),
            'decisions'   => $batchId ? AssetImport::decisions($batchId) : null,
            'rows'        => $batchId ? AssetImport::rows($batchId) : null,
            'codes'       => $batchId ? AssetImport::importedCodes($batchId) : [],
            'departments' => DB::table('departments')->orderBy('Name')->get(['id', 'Name', 'status']),
            'categories'  => AssetImport::CATEGORIES,
            'fields'      => AssetImport::fieldLabels(),
            'labels'      => AssetImport::CATEGORIES,
            'assignable'  => $this->assignablePeople(),
            'partBytes'   => AssetImport::partBytes(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        // No max: rule — the file may be of any size. A file too large for one
        // request is sent in parts to part() instead.
        $validated = $request->validate([
            'file' => 'required|file',
        ], [
            'file.required' => 'Choose the inventory file first.',
        ]);

        $result = AssetImport::start($validated['file'], $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * One slice of a file too large for one request.
     *
     * The page splits the workbook into slices of AssetImport::partBytes(), so
     * the import is bounded by disk, not by PHP's upload_max_filesize. The last
     * slice to arrive joins them and starts the import exactly as a single
     * upload would.
     */
    public function part(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[0-9a-f]{32}$/'],
            'index'     => 'required|integer|min:0',
            'total'     => 'required|integer|min:1|max:100000',
            'name'      => 'required|string|max:255',
            'file'      => 'required|file',
        ], [
            'file.required' => 'That part of the file did not reach the server. If it is very large, try again — the parts already sent are kept.',
            'upload_id.*'   => 'That upload could not be identified. Reload the page and choose the file again.',
        ]);

        if ((int) $validated['index'] >= (int) $validated['total']) {
            return response()->json(['ok' => false, 'message' => 'That upload arrived out of step. Reload the page and try again.'], 422);
        }

        $result = AssetImport::storePart(
            (string) $validated['upload_id'],
            (int) $validated['index'],
            (int) $validated['total'],
            $validated['file'],
            (string) $validated['name'],
            $request->user()
        );

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function template()
    {
        return AssetImport::template();
    }

    public function mapping(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::saveMapping($batch, $request->input('mapping', []), $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function step(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::step($batch, $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function rows(Request $request, int $batch): JsonResponse
    {
        return response()->json(AssetImport::rows(
            $batch,
            (string) $request->query('status', 'all'),
            max(1, (int) $request->query('page', 1))
        ));
    }

    public function decisions(int $batch): JsonResponse
    {
        return response()->json(AssetImport::decisions($batch));
    }

    public function saveDecisions(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::saveDecisions($batch, $request->all(), $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function codes(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::saveCodes($batch, (array) $request->input('codes', []), $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** Choose the accountable person for one line of the staged file. */
    public function person(Request $request, int $batch): JsonResponse
    {
        $validated = $request->validate([
            'line'    => 'required|integer|min:1',
            'user_id' => 'nullable|integer|min:0',
        ]);

        $userId = $validated['user_id'] ?? null;

        $result = AssetImport::assignPerson($batch, (int) $validated['line'], $userId === null ? null : (int) $userId, $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function propose(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::proposeCodes($batch, (string) $request->input('pattern', ''), $request->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function commit(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::commit($batch, $request->user());

        if ($result['ok'] ?? false) {
            $result['codes'] = AssetImport::importedCodes($batch);
        }

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function retry(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::retryFailed($batch, $request->user());

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function retryQr(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::retryQr($batch, $request->user());

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function discard(Request $request, int $batch): JsonResponse
    {
        $result = AssetImport::cancel($batch, $request->user());

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    /** People who could actually hold an asset: an active masterlist record with an active account. */
    private function assignablePeople(): array
    {
        return DB::table('users')
            ->join('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.status', 'Active')
            ->orderBy('employee_numbers.Full_Name')
            ->get([
                'users.id',
                'employee_numbers.Full_Name as name',
                'employee_numbers.Employee_number as number',
            ])
            ->map(fn ($person) => [
                'id'    => (int) $person->id,
                'label' => $person->name . ' (' . $person->number . ')',
            ])
            ->all();
    }
}
