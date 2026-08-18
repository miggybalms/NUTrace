<?php
// app/Http/Controllers/UserRequestController.php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UserRequestController extends Controller
{
    /**
     * Show the user request form
     */
    public function create()
    {
        // Exclude users with Admin role from the assign-to list
        $users = DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->select('users.id', 'employee_numbers.Full_Name', 'departments.Name as department', 'users.role')
            ->where(function ($q) {
                $q->whereNull('users.role')->orWhere('users.role', '!=', 'Admin');
            })
            ->orderBy('employee_numbers.Full_Name')
            ->get();

        return view('users.request.request_asset', compact('users'));
    }

    /**
     * Show the department head request form
     */
    public function createDepartmentHead()
    {
        $user = Auth::user();
        
        // Get users in the same department (for transfer assignments)
        $users = DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->select('users.id', 'employee_numbers.Full_Name', 'departments.Name as department', 'users.role')
            ->where('users.department_id', $user->department_id)
            ->where(function ($q) {
                $q->whereNull('users.role')->orWhere('users.role', '!=', 'Admin');
            })
            ->orderBy('employee_numbers.Full_Name')
            ->get();

        return view('department_head.request.request_asset', compact('users'));
    }

    /**
     * Store a new request (for both users and department heads)
     */
public function store(HttpRequest $request)
{
    $rules = [
        'request_type'      => 'required|in:Repair,Disposal,Transfer,Replacement,Pullout,Other',
        'asset_ids'         => 'required|array|min:1',
        'asset_ids.*'       => 'required|integer|exists:assets,id',
        'notes'             => 'required|string',
        'attachment'        => 'nullable|image|max:10240',
        'assign_to_user_id' => 'nullable|exists:users,id',
    ];

    if ($request->input('request_type') === 'Transfer') {
        $rules['assign_to_user_id'] = 'required|exists:users,id';
    }

    $validated = $request->validate($rules);

    $user     = Auth::user();
    $assetIds = array_values(array_unique(array_map('intval', $validated['asset_ids'])));

    // ─── Authorization check ───────────────────────────────
    if (($user->role ?? '') === 'Department Head') {
        // Department Head can request assets belonging to anyone in their department
        $allowedIds = DB::table('assets')
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->whereIn('assets.id', $assetIds)
            ->where('users.department_id', $user->department_id)
            ->pluck('assets.id')
            ->all();
    } else {
        // Regular Employee – only own assets
        $allowedIds = DB::table('assets')
            ->whereIn('id', $assetIds)
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();
    }

    if (count($allowedIds) !== count($assetIds)) {
        return back()->withErrors([
            'asset_ids' => 'One or more selected assets are not authorized for you.',
        ])->withInput();
    }

    // Handle optional attachment
    $fileName = $filePath = $fileSize = $mimeType = $url = null;
    $uploadedFile = $request->file('attachment');

    if ($uploadedFile) {
        $filePath = $uploadedFile->store('request_files', 'public');
        $fileName = $uploadedFile->getClientOriginalName();
        $fileSize = $uploadedFile->getSize();
        $mimeType = $uploadedFile->getClientMimeType();
        $url      = Storage::url($filePath);
    }

    try {
        $requestId = DB::transaction(function () use ($validated, $user, $assetIds, $fileName, $filePath, $fileSize, $mimeType, $url) {

            $insertData = [
                'user_id'      => $user->id,
                'asset_id'     => null,               // bulk
                'request_type' => $validated['request_type'],
                'status'       => 'Pending',
                'Note'         => $validated['notes'],
                'file_name'    => $fileName,
                'file_path'    => $filePath,
                'file_size'    => $fileSize,
                'mime_type'    => $mimeType,
                'url'          => $url,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            if (Schema::hasColumn('requests', 'assign_to_user_id')) {
                $insertData['assign_to_user_id'] = $validated['assign_to_user_id'] ?? null;
            }

            $requestId = DB::table('requests')->insertGetId($insertData);

            // Create request_items
            $now   = now();
            $items = [];
            foreach ($assetIds as $assetId) {
                $items[] = [
                    'request_id' => $requestId,
                    'asset_id'   => $assetId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('request_items')->insert($items);

            // Audit log
            DB::table('audit_logs')->insert([
                'user_id'            => $user->id,
                'request_id'         => $requestId,
                'asset_id'           => null,
                'action_type'        => 'CREATE',
                'notes'              => 'Submitted bulk ' . $validated['request_type'] . ' request with ' . count($assetIds) . ' asset(s)',
                'action_description' => 'Bulk request #' . $requestId . ' created',
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            return $requestId;
        });

        // Redirect back to the correct page depending on role
        $redirectRoute = ($user->role ?? '') === 'Department Head'
            ? 'department_head.request-asset'
            : 'user.request-asset';

        return redirect()
            ->route($redirectRoute)
            ->with('success', 'Bulk asset request submitted successfully (' . count($assetIds) . ' asset(s)).');

    } catch (\Throwable $e) {
        \Log::error('Bulk request failed: ' . $e->getMessage());
        return back()->withErrors(['error' => 'Failed to submit request. Please try again.'])->withInput();
    }
}

    /**
     * Check if an asset code exists and belongs to the user (for regular users)
     */
    public function checkUserAssetCode(HttpRequest $request)
    {
        $code = trim((string) $request->query('code', ''));
        $user = Auth::user();

        if (!$user || $code === '') {
            return response()->json(['exists' => false]);
        }

        $asset = DB::table('assets')
            ->where('Asset_code', $code)
            ->where('user_id', $user->id)
            ->select('id', 'Asset_code as code', 'Asset_name as name', 'Category as category', 'Lifecycle_Status as lifecycle_status')
            ->first();

        if ($asset) {
            return response()->json([
                'exists' => true,
                'asset'  => $asset,
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Check if an asset code exists and belongs to the department head's department
     */
    public function checkDepartmentHeadAssetCode(HttpRequest $request)
    {
        $code = trim((string) $request->query('code', ''));
        $user = Auth::user();

        if (!$user || $code === '' || ($user->role ?? '') !== 'Department Head') {
            return response()->json(['exists' => false]);
        }

        $asset = DB::table('assets')
            ->join('users', 'assets.user_id', '=', 'users.id')
            ->where('assets.Asset_code', $code)
            ->where('users.department_id', $user->department_id)
            ->select(
                'assets.id', 
                'assets.Asset_code as code', 
                'assets.Asset_name as name', 
                'assets.Category as category', 
                'assets.Lifecycle_Status as lifecycle_status'
            )
            ->first();

        if ($asset) {
            return response()->json([
                'exists' => true,
                'asset'  => $asset,
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Get assets for the current user (for the bulk selection UI)
     */
    public function getUserAssets(HttpRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $search = $request->query('search', '');
        
        $query = DB::table('assets')
            ->where('user_id', $user->id)
            ->whereIn('Lifecycle_Status', ['Active', 'Acquired'])
            ->select('id', 'Asset_code', 'Asset_name', 'Category', 'Condition', 'Lifecycle_Status');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('Asset_name', 'like', "%{$search}%")
                  ->orWhere('Asset_code', 'like', "%{$search}%");
            });
        }

        $assets = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'assets' => $assets
        ]);
    }

    /**
     * Get assets for the department head's department
     */
    public function getDepartmentAssets(HttpRequest $request)
    {
        $user = Auth::user();
        if (!$user || ($user->role ?? '') !== 'Department Head') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $search = $request->query('search', '');
        
        $query = DB::table('assets')
            ->join('users', 'assets.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)
            ->whereIn('assets.Lifecycle_Status', ['Active', 'Acquired'])
            ->select(
                'assets.id',
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'assets.Condition',
                'assets.Lifecycle_Status'
            );

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('assets.Asset_name', 'like', "%{$search}%")
                  ->orWhere('assets.Asset_code', 'like', "%{$search}%");
            });
        }

        $assets = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'assets' => $assets->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'code' => $asset->Asset_code,
                    'name' => $asset->Asset_name,
                    'category' => $asset->Category,
                    'condition' => $asset->Condition,
                    'lifecycle_status' => $asset->Lifecycle_Status,
                ];
            })
        ]);
    }
}