<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UserRequestController;

// Handle login form submission
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        $user = Auth::user();
        $isAdmin = ($user->role === 'Admin' || $user->role === 'Facilities' || ($user->department ?? '') === 'Facilities');
        if ($isAdmin) {
            return redirect('/admin');
        }
        return redirect('/users');
    }

    return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->withInput(['email' => $request->email]);
});

Route::get('/', function () {
    return view('welcome');
});

// Auth views (simple GET routes so header buttons load the pages)
Route::get('/login', function () {
    return view('auth.login');
});

Route::get('/register', function () {
    return view('auth.register');
});

// Handle registration form submission
Route::post('/register', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:50',
        'unit_heads_number' => 'required|string|max:20',
        'department' => 'required|in:Facilities,IT,LRC,Admission,SDAO,Marketing',
        'email' => 'required|email|max:100|unique:users,email',
        'password' => 'required|confirmed|min:6',
        'profile_photo' => 'nullable|image|max:2048',
    ]);

    $photoPath = null;
    if ($request->hasFile('profile_photo')) {
        $photoPath = $request->file('profile_photo')->store('profile_photos', 'public');
    }

    $isFirst = User::count() === 0;
    $isFacilities = isset($validated['department']) && $validated['department'] === 'Facilities';
    $role = ($isFirst || $isFacilities) ? 'Admin' : 'Employee';

    try {
        $user = User::create([
            'unit_heads_number' => $validated['unit_heads_number'],
            'full_name' => $validated['name'],
            'department' => $validated['department'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'profile_photo' => $photoPath,
            'role' => $role,
        ]);
    } catch (QueryException $e) {
        // Fallback in case DB enum doesn't include 'Admin' (e.g., migrations already run)
        if ($isFirst) {
            $user = User::create([
                'unit_heads_number' => $validated['unit_heads_number'],
                'full_name' => $validated['name'],
                'department' => $validated['department'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'profile_photo' => $photoPath,
                'role' => 'Employee',
            ]);
        } else {
            throw $e;
        }
    }

    return redirect('/login')->with('success', 'Registration successful. Please login.');
});

// Users area -> render users.dashboard and show assigned assets when logged in
// User assets page (My Assets) - show assigned assets for authenticated user
Route::get('/users/assets', function () {
    $user = Auth::user();
    $assignedAssets = collect([]);
    if ($user) {
        $assignedAssets = Asset::where('user_id', $user->id)->get();
    }
    return view('users.asset.asset', compact('assignedAssets'));
});

// User-facing asset detail (only accessible to assigned user or admins)
Route::get('/users/assets/{id}', function ($id) {
    $asset = Asset::with('user')->find($id);
    if (!$asset) {
        abort(404);
    }
    $user = Auth::user();
    if (!$user) {
        abort(403);
    }
    if ($asset->user_id !== $user->id && ($user->role ?? '') !== 'Admin') {
        abort(403);
    }
    return view('users.asset.show', compact('asset'));
})->where('id', '[0-9]+');

Route::get('/users', function () {
    $user = Auth::user();

    if (!$user) {
        $totalAssets = 0;
        $stats = [
            'acquired' => ['count' => 0, 'percent' => 0],
            'active' => ['count' => 0, 'percent' => 0],
            'for_repair' => ['count' => 0, 'percent' => 0],
            'pulled_out' => ['count' => 0, 'percent' => 0],
            'disposed' => ['count' => 0, 'percent' => 0],
        ];
        $recentRequests = collect([]);
        $assignedAssets = collect([]);

        return view('users.dashboard', compact('totalAssets', 'stats', 'recentRequests', 'assignedAssets'));
    }

    $assets = Asset::where('user_id', $user->id)->get();
    $totalAssets = $assets->count();

    $acquired = $assets->where('Lifecycle_Status', 'Acquired')->count();
    $active = $assets->where('Lifecycle_Status', 'Active')->count();
    $forRepair = $assets->where('Lifecycle_Status', 'For Repair')->count();
    $pulledOut = $assets->where('Lifecycle_Status', 'Pullout')->count();
    $disposed = $assets->where('Lifecycle_Status', 'Disposal')->count();

    $calcPercent = fn($c) => $totalAssets > 0 ? round(($c / $totalAssets) * 100) : 0;

    $stats = [
        'acquired' => ['count' => $acquired, 'percent' => $calcPercent($acquired)],
        'active' => ['count' => $active, 'percent' => $calcPercent($active)],
        'for_repair' => ['count' => $forRepair, 'percent' => $calcPercent($forRepair)],
        'pulled_out' => ['count' => $pulledOut, 'percent' => $calcPercent($pulledOut)],
        'disposed' => ['count' => $disposed, 'percent' => $calcPercent($disposed)],
    ];

    $recentRequests = collect([]);

    return view('users.dashboard', [
        'totalAssets' => $totalAssets,
        'stats' => $stats,
        'recentRequests' => $recentRequests,
        'assignedAssets' => $assets,
    ]);
});

// Compatibility redirects: some links use singular '/user' paths — redirect to plural routes
Route::get('/user', function () {
    return redirect('/users');
});

Route::get('/user/dashboard', function () {
    return redirect('/users');
});

Route::get('/user/assets', function () {
    return redirect('/users/assets');
});

// Admin area -> render admin.dashboard with safe defaults
Route::get('/admin', function () {
    $acquiredThisMonth = 0;
    $activeAssets = 0;
    $forRepairAssets = 0;
    $pendingRequests = 0;
    $overviewMetrics = [
        'total_assets' => 0,
        'active_assets' => 0,
        'pending_requests' => 0,
        'assets_for_repair' => 0,
    ];
    $recentActivities = collect([]);
    $pulledOutAssets = 0;
    $disposedAssets = 0;

    return view('admin.dashboard', compact(
        'acquiredThisMonth', 'activeAssets', 'forRepairAssets', 'pendingRequests',
        'overviewMetrics', 'recentActivities', 'pulledOutAssets', 'disposedAssets'
    ));
});

// Admin assets page - load assets grouped by department
Route::get('/admin/assets', function () {
    $deptNames = ['IT','LRC','Admission','SDAO','Marketing','Facilities'];

    $assets = Asset::with('user')->get();

    // Initialize departments array with known departments
    $departments = [];
    $i = 1;
    foreach ($deptNames as $name) {
        $headUser = User::where('department', $name)->first();
        $departments[$name] = (object) [
            'id' => $i++,
            'name' => $name,
            'head' => $headUser?->full_name ?? '',
            'total_assets' => 0,
            'assets' => [],
        ];
    }

    // Group assets by user department (or 'Unassigned')
    foreach ($assets as $asset) {
        $dept = $asset->user?->department ?? 'Unassigned';
        if (!isset($departments[$dept])) {
            $departments[$dept] = (object) [
                'id' => $i++,
                'name' => $dept,
                'head' => $asset->user?->full_name ?? '',
                'total_assets' => 0,
                'assets' => [],
            ];
        }

        // map to view-friendly object
        $statusRaw = $asset->Lifecycle_Status ?? '';
        switch ($statusRaw) {
            case 'Acquired': $status = 'acquired'; break;
            case 'Active': $status = 'active'; break;
            case 'For Repair': $status = 'for_repair'; break;
            case 'Pullout': $status = 'pulled_out'; break;
            case 'Disposal': $status = 'disposed'; break;
            default: $status = strtolower(str_replace(' ', '_', $statusRaw));
        }

        $departments[$dept]->assets[] = (object) [
            'id' => $asset->id,
            'name' => $asset->Asset_name ?? $asset->Asset_name ?? '',
            'asset_code' => $asset->Asset_code ?? '',
            'status' => $status,
            'assigned_to' => $asset->user?->full_name ?? 'Unassigned',
            'acquisition_date' => $asset->accusion_date ? (string)$asset->accusion_date : null,
            'url' => $asset->url ?? null,
        ];
        $departments[$dept]->total_assets++;
    }

    // Convert associative to indexed array preserving order of initial deptNames
    $ordered = [];
    foreach ($deptNames as $d) {
        if (isset($departments[$d])) $ordered[] = $departments[$d];
    }
    // append any extra departments collected
    foreach ($departments as $k => $v) {
        if (!in_array($k, $deptNames)) $ordered[] = $v;
    }

    return view('admin.assets.asset', ['departments' => $ordered]);
});

// Admin asset detail view
Route::get('/admin/assets/{id}', function ($id) {
    $asset = Asset::with('user')->find($id);
    if (!$asset) {
        abort(404);
    }
    return view('admin.assets.show', compact('asset'));
})->where('id', '[0-9]+');

// Admin department assets page
Route::get('/admin/assets/department/{department}', function ($department) {
    $departmentName = urldecode($department);

    $categoryEnumValues = [
        'Furnitures and Fixtures',
        'General and Office Equipment',
        'Info and Equipment',
        'laboratory Apparatus and equipment',
        'library books',
        'Motor vehicles',
        'P.E Equipment',
        'Low value Asset',
    ];

    $categoryCodeMap = [
        'furnitures and fixtures' => 'furnitures_and_fixtures',
        'general and office equipment' => 'general_and_office_equipment',
        'info and equipment' => 'info_and_equipment',
        'laboratory apparatus and equipment' => 'laboratory_apparatus_and_equipment',
        'library books' => 'library_books',
        'motor vehicles' => 'motor_vehicles',
        'p.e equipment' => 'pe_equipment',
        'low value asset' => 'low_value_asset',
    ];

    $toCategoryCode = function (string $category): string {
        $normalized = strtolower(trim($category));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        $map = [
            'furnitures and fixtures' => 'furnitures_and_fixtures',
            'general and office equipment' => 'general_and_office_equipment',
            'info and equipment' => 'info_and_equipment',
            'laboratory apparatus and equipment' => 'laboratory_apparatus_and_equipment',
            'library books' => 'library_books',
            'motor vehicles' => 'motor_vehicles',
            'p.e equipment' => 'pe_equipment',
            'low value asset' => 'low_value_asset',
        ];

        return $map[$normalized] ?? 'other';
    };

    $assetsRaw = Asset::with('user')
        ->whereHas('user', function ($query) use ($departmentName) {
            $query->where('department', $departmentName);
        })
        ->orderBy('Asset_name')
        ->get();

    $assets = $assetsRaw
        ->map(function ($asset) use ($toCategoryCode) {
            $statusRaw = (string) ($asset->Lifecycle_Status ?? '');
            $status = match ($statusRaw) {
                'Acquired' => 'acquired',
                'Active' => 'active',
                'For Repair' => 'for_repair',
                'Pullout' => 'pulled_out',
                'Disposal' => 'disposed',
                default => 'active',
            };

            $category = (string) ($asset->Category ?? 'Other');
            $categoryCode = $toCategoryCode($category);

            return (object) [
                'db_id' => $asset->id,
                'id' => $asset->Asset_code ?? ('AST-' . $asset->id),
                'name' => $asset->Asset_name ?? 'Unnamed Asset',
                'category' => $category,
                'accountable' => $asset->user?->full_name ?? 'Unassigned',
                'date_acquired' => $asset->accusion_date ? (string) \Illuminate\Support\Carbon::parse($asset->accusion_date)->format('M d, Y') : '-',
                'location' => $asset->asset_location ?? '-',
                'status' => $status,
                'category_code' => $categoryCode,
            ];
        });

    $categoryOptions = collect($categoryEnumValues)
        ->map(function ($label) use ($toCategoryCode) {
            return [
                'value' => $toCategoryCode($label),
                'label' => $label,
            ];
        })
        ->concat(
            $assetsRaw->map(function ($asset) use ($toCategoryCode) {
                $label = trim((string) ($asset->Category ?? ''));
                return [
                    'value' => $toCategoryCode($label !== '' ? $label : 'Other'),
                    'label' => $label !== '' ? $label : 'Other',
                ];
            })
        )
        ->unique('value')
        ->values();

    return view('admin.assets.department_asset', compact('departmentName', 'assets', 'categoryOptions'));
})->name('admin.assets.department');

// Admin asset registry page
Route::get('/admin/assets/registry', function () {
    return view('admin.assets.asset_registry');
});

// Admin disposal page
Route::get('/admin/disposal', function () {
    $totalDisposed = 0;
    $disposalRecords = collect([]);
    $availableAssets = collect([]);

    // If the disposals table exists, load recent disposals and join asset info
    if (\Illuminate\Support\Facades\Schema::hasTable('disposals')) {
        try {
            $query = DB::table('disposals')
                ->leftJoin('assets', 'disposals.asset_id', '=', 'assets.id')
                ->select('disposals.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code', 'assets.accusion_cost as original_value')
                ->orderByDesc('disposals.disposal_date');

            $disposalRecords = $query->get();
            $totalDisposed = $disposalRecords->count();
        } catch (\Exception $e) {
            // keep defaults if something goes wrong
            $disposalRecords = collect();
            $totalDisposed = 0;
        }
    }

    // Provide assets to the modal for recording new disposals (exclude already disposed)
    if (\Illuminate\Support\Facades\Schema::hasTable('assets')) {
        try {
            $availableAssets = Asset::where('Lifecycle_Status', '!=', 'Disposal')
                ->orderBy('Asset_name')
                ->get()
                ->map(function ($a) {
                    return (object) [
                        'id' => $a->id,
                        'name' => $a->Asset_name ?? '',
                        'asset_code' => $a->Asset_code ?? '',
                    ];
                });
        } catch (\Exception $e) {
            $availableAssets = collect();
        }
    }

    return view('admin.disposal.disposal', compact('totalDisposed', 'disposalRecords', 'availableAssets'));
});

// Admin repair page
Route::get('/admin/repair', function () {
    return view('admin.repair.repair');
});

// Admin pullout page
Route::get('/admin/pullout', function () {
    $totalPulledOut = 0;
    $pulloutRecords = collect([]);
    $availableAssets = collect([]);
    return view('admin.pullout.pullout', compact('totalPulledOut', 'pulloutRecords', 'availableAssets'));
});

// Server-side user search for assigning assets (used by asset registry)
Route::get('/admin/users/search', function (Request $request) {
    $q = $request->query('q');
    $usersQuery = User::query()->select('id', 'full_name', 'department', 'email');
    if ($q) {
        $usersQuery->where(function ($builder) use ($q) {
            $builder->where('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('department', 'like', "%{$q}%");
        });
    }

    $users = $usersQuery->limit(10)->get()->map(function ($u) {
        return [
            'id' => $u->id,
            'name' => $u->full_name,
            'department' => $u->department,
            'email' => $u->email,
        ];
    });

    return response()->json($users);
});

// Handle asset registry form submission
Route::post('/admin/assets', function (Request $request) {
    $validated = $request->validate([
        'asset_code' => 'nullable|string|max:50',
        'name' => 'required|string|max:150',
        'category' => 'nullable|string|max:150',
        'condition' => 'required|string',
        'assigned_to' => 'nullable|integer|exists:users,id',
        'location' => 'nullable|string|max:255',
        'acquisition_date' => 'nullable|date',
        'purchase_price' => 'nullable|numeric',
        'warranty_months' => 'nullable|integer',
        'serial_number' => 'nullable|string|max:150',
        'asset_photo' => 'nullable|image|max:10240',
        'notes' => 'nullable|string',
    ]);

    // Map form categories to migration enum values where possible
    $categoryMap = [
        'computer' => 'Info and Equipment',
        'peripheral' => 'Info and Equipment',
        'furniture' => 'Furnitures and Fixtures',
        'electronic' => 'Info and Equipment',
        'other' => 'Low value Asset',
    ];

    $condMap = [
        'new' => 'New',
        'excellent' => 'Excellent',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
    ];

    $assetCode = $validated['asset_code'] ?? ('AST-' . strtoupper(Str::random(10)));
    $category = isset($validated['category']) ? ($categoryMap[strtolower($validated['category'])] ?? $validated['category']) : 'Low value Asset';
    $condition = $condMap[strtolower($validated['condition'])] ?? ucfirst($validated['condition']);
    $userId = $validated['assigned_to'] ?? Auth::id();

    // Handle file upload
    $fileName = null;
    $filePath = null;
    $fileSize = null;
    $mime = null;
    $url = null;
    if ($request->hasFile('asset_photo')) {
        $file = $request->file('asset_photo');
        $filePath = $file->store('assets', 'public');
        $fileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mime = $file->getClientMimeType();
        $url = Storage::url($filePath);
    }

    try {
        $asset = Asset::create([
            'user_id' => $userId,
            'Asset_code' => $assetCode,
            'Asset_name' => $validated['name'],
            'Category' => $category,
            'Condition' => $condition,
            'Lifecycle_Status' => 'Acquired',
            'accusion_date' => $validated['acquisition_date'] ?? null,
            'accusion_cost' => null,
            'purchase_Price' => $validated['purchase_price'] ?? null,
            'warranty_months' => $validated['warranty_months'] ?? null,
            'supplier' => null,
            'model' => null,
            'manufacture' => null,
            'serial_Number' => $validated['serial_number'] ?? null,
            'asset_location' => $validated['location'] ?? null,
            'qr_code_path' => null,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mime,
            // Save the public URL for the uploaded file (if any)
            'url' => $url,
        ]);
    } catch (\Illuminate\Database\QueryException $e) {
        // If enum mismatch or other DB error, fallback to simpler fields and return error message
        return back()->withErrors(['error' => 'Failed to save asset. DB error: ' . $e->getMessage()])->withInput();
    }

    return redirect('/admin/assets/registry')->with('success', 'Asset registered successfully.');
})->name('admin.assets.store');

// Admin requests page
Route::get('/admin/requests', function () {
    $requests = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->select([
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.Note',
            'requests.created_at',
            'users.full_name as submitted_by',
            'users.email',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
        ])
        ->orderByDesc('requests.created_at')
        ->get()
        ->map(function ($request) {
            return (object) [
                'id' => $request->id,
                'asset_name' => $request->asset_name ?: ($request->asset_code ?: 'Unknown Asset'),
                'type' => strtolower((string) $request->request_type),
                'submitted_by' => $request->submitted_by ?: 'Unknown User',
                'email' => $request->email ?: '',
                'created_at' => $request->created_at ? \Illuminate\Support\Carbon::parse($request->created_at) : now(),
                'status' => strtolower((string) $request->status),
                'description' => $request->Note ?: '',
            ];
        });

    $totalRequests = $requests->count();
    $pendingRequests = collect($requests)->where('status', 'pending')->count();
    $approvedRequests = collect($requests)->where('status', 'approved')->count();
    $rejectedRequests = collect($requests)->where('status', 'rejected')->count();

    return view('admin.request.request', compact(
        'requests', 'totalRequests', 'pendingRequests', 'approvedRequests', 'rejectedRequests'
    ));
});

Route::post('/admin/requests/{id}/approve', function ($id) {
    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (!$requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    if (strtolower((string) $requestRecord->status) !== 'pending') {
        return response()->json(['message' => 'Only pending requests can be approved.'], 422);
    }

    $approvedBy = Auth::user()?->full_name ?? 'Admin';
    $requestType = strtolower((string) $requestRecord->request_type);

    DB::transaction(function () use ($requestRecord, $approvedBy, $requestType) {
        DB::table('requests')
            ->where('id', $requestRecord->id)
            ->update([
                'status' => 'Approved',
                'updated_at' => now(),
            ]);

        if ($requestType === 'repair') {
            $exists = DB::table('repairs')->where('request_id', $requestRecord->id)->exists();
            if (!$exists) {
                DB::table('repairs')->insert([
                    'request_id' => $requestRecord->id,
                    'asset_id' => $requestRecord->asset_id,
                    'Approve_by' => $approvedBy,
                    'Repair_Description' => $requestRecord->Note,
                    'Repair_Cost' => null,
                    'status' => 'Pending',
                    'notes' => $requestRecord->Note,
                    'Repair_Date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('assets')->where('id', $requestRecord->asset_id)->update([
                'Lifecycle_Status' => 'For Repair',
                'updated_at' => now(),
            ]);
        }

        if ($requestType === 'disposal') {
            $exists = DB::table('disposals')->where('request_id', $requestRecord->id)->exists();
            if (!$exists) {
                DB::table('disposals')->insert([
                    'request_id' => $requestRecord->id,
                    'asset_id' => $requestRecord->asset_id,
                    'Approve_by' => $approvedBy,
                    'Description' => 'Approved disposal request',
                    'notes' => $requestRecord->Note,
                    'disposal_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('assets')->where('id', $requestRecord->asset_id)->update([
                'Lifecycle_Status' => 'Disposal',
                'updated_at' => now(),
            ]);
        }

        if ($requestType === 'pullout') {
            $exists = DB::table('pullouts')->where('request_id', $requestRecord->id)->exists();
            if (!$exists) {
                DB::table('pullouts')->insert([
                    'request_id' => $requestRecord->id,
                    'asset_id' => $requestRecord->asset_id,
                    'Approve_by' => $approvedBy,
                    'Description' => 'Approved pullout request',
                    'notes' => $requestRecord->Note,
                    'pullout_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('assets')->where('id', $requestRecord->asset_id)->update([
                'Lifecycle_Status' => 'Pullout',
                'updated_at' => now(),
            ]);
        }

        if ($requestType === 'replacement') {
            $exists = DB::table('replacements')->where('request_id', $requestRecord->id)->exists();
            if (!$exists) {
                DB::table('replacements')->insert([
                    'request_id' => $requestRecord->id,
                    'old_asset_id' => $requestRecord->asset_id,
                    'new_asset_id' => null,
                    'Approve_by' => $approvedBy,
                    'reason' => 'Approved replacement request',
                    'notes' => $requestRecord->Note,
                    'status' => 'Approved',
                    'replacement_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    });

    return response()->json(['message' => 'Request approved successfully.']);
})->name('admin.requests.approve');

Route::post('/admin/requests/{id}/reject', function ($id) {
    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (!$requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    if (strtolower((string) $requestRecord->status) !== 'pending') {
        return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
    }

    DB::table('requests')
        ->where('id', $requestRecord->id)
        ->update([
            'status' => 'Rejected',
            'updated_at' => now(),
        ]);

    return response()->json(['message' => 'Request rejected successfully.']);
})->name('admin.requests.reject');

// Logout route - logs out user and redirects to welcome page
Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
});

// User Request Routes
Route::middleware(['auth'])->group(function () {
    // AJAX: validate asset code belongs to authenticated user
    Route::get('/user/assets/check-code', function (Request $request) {
        $code = trim((string) $request->query('code', ''));
        $user = Auth::user();

        if (!$user || $code === '') {
            return response()->json(['exists' => false]);
        }

        $asset = DB::table('assets')
            ->where('Asset_code', $code)
            ->where('user_id', $user->id)
            ->first();

        if ($asset) {
            return response()->json([
                'exists' => true,
                'asset' => [
                    'id' => $asset->id,
                    'name' => $asset->Asset_name ?? null,
                ],
            ]);
        }

        return response()->json(['exists' => false]);
    })->name('user.assets.check');
    Route::get('/user/requests', function () {
        $user = Auth::user();

        // Use a paginator so the view's pagination helpers work correctly
        $requests = DB::table('requests')
            ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
            ->select('requests.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code')
            ->where('requests.user_id', $user->id)
            ->orderByDesc('requests.created_at')
            ->paginate(10);

        // Transform paginator items to match view expectations (attach asset object, parse dates)
        $requests->getCollection()->transform(function ($r) {
            return (object) [
                'id' => $r->id,
                'request_type' => $r->request_type,
                'status' => $r->status,
                'Note' => $r->Note,
                    'file_path' => $r->file_path ?? null,
                    'file_name' => $r->file_name ?? null,
                'created_at' => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at) : now(),
                'updated_at' => $r->updated_at ? \Illuminate\Support\Carbon::parse($r->updated_at) : now(),
                'asset' => (object) [
                    'Asset_name' => $r->asset_name,
                    'Asset_code' => $r->asset_code,
                ],
            ];
        });

        // Totals (global counts) for the user's requests
        $totalRequests = DB::table('requests')->where('user_id', $user->id)->count();
        $pendingRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Pending')->count();
        $approvedRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Approved')->count();
        $rejectedRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Rejected')->count();

        return view('users.request.request', compact('requests', 'totalRequests', 'pendingRequests', 'approvedRequests', 'rejectedRequests'));
    })->name('user.requests.index');
    Route::get('/user/request-asset', [UserRequestController::class, 'create'])->name('user.request-asset');
    Route::post('/user/requests/store', [UserRequestController::class, 'store'])->name('user.requests.store');
});

