<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
        // Admin or Facilities users go to admin area
        $facilitiesDeptId = DB::table('departments')->where('Name', 'Facilities')->value('id');
        $isAdmin = ($user->role === 'Admin' || $user->role === 'Facilities' || $user->department_id === $facilitiesDeptId);
        if ($isAdmin) {
            return redirect('/admin');
        }

        // Department Head users go to department head dashboard (uses department_head views)
        if (($user->role ?? '') === 'Department Head') {
            return redirect('/department-head');
        }

        // Regular users go to the users area
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
    $isFirstUser = User::count() === 0;
    $departments = DB::table('departments')->orderBy('Name')->pluck('Name')->toArray();
    return view('auth.register', ['isFirstUser' => $isFirstUser, 'departments' => $departments]);
});

// Handle registration form submission
Route::post('/register', function (Request $request) {
    // Check if this is the first user
    $isFirst = User::count() === 0;
    
    $rules = [
        'name' => 'required|string|max:50',
        'unit_heads_number' => 'required|string|max:20',
        'email' => 'required|email|max:100|unique:users,email',
        'password' => 'required|confirmed|min:6',
        'profile_photo' => 'nullable|image|max:2048',
    ];
    
    // First user types their own department, others select from existing
    if ($isFirst) {
        $rules['department'] = 'required|string|max:100';
    } else {
        // Get departments from database
        $existingDepts = DB::table('departments')->pluck('Name')->toArray();
        $deptList = implode(',', $existingDepts);
        $rules['department'] = 'required|in:' . $deptList;
    }
    
    $validated = $request->validate($rules);

    $departmentName = $validated['department'];
    
    // Look up or create department
    $department = DB::table('departments')->where('Name', $departmentName)->first();
    if (!$department) {
        // Create new department if it doesn't exist
        $departmentId = DB::table('departments')->insertGetId([
            'Name' => $departmentName,
            'status' => 'Active',
            'Create_at' => now(),
            'Update_at' => now(),
        ]);
    } else {
        $departmentId = $department->id;
    }

    $photoPath = null;
    if ($request->hasFile('profile_photo')) {
        $photoPath = $request->file('profile_photo')->store('profile_photos', 'public');
    }

    $isFacilities = strtolower($departmentName) === strtolower('Facilities');

    // First user is always Admin, subsequent Facilities users are Admin
    if ($isFirst) {
        $role = 'Admin';
    } elseif ($isFacilities) {
        $role = 'Admin';
    } else {
        // If department has no Department Head yet, make this user Department Head
        $hasDeptHead = User::where('department_id', $departmentId)->where('role', 'Department Head')->exists();
        $role = $hasDeptHead ? 'Employee' : 'Department Head';
    }

    try {
        $user = User::create([
            'unit_heads_number' => $validated['unit_heads_number'],
            'full_name' => $validated['name'],
            'department_id' => $departmentId,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'profile_photo' => $photoPath,
            'role' => $role,
        ]);
        // Record audit log for new user registration
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'notes' => 'User registered: ' . ($user->full_name ?? $user->email),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // ignore logging errors
        }
    } catch (QueryException $e) {
        // Fallback in case creation fails
        $fallbackRole = 'Employee';
        if ($isFirst) {
            $fallbackRole = 'Admin';
        } elseif ($isFacilities) {
            $fallbackRole = 'Admin';
        } else {
            $hasDeptHead = User::where('department_id', $departmentId)->where('role', 'Department Head')->exists();
            $fallbackRole = $hasDeptHead ? 'Employee' : 'Department Head';
        }

        try {
            $user = User::create([
                'unit_heads_number' => $validated['unit_heads_number'],
                'full_name' => $validated['name'],
                'department_id' => $departmentId,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'profile_photo' => $photoPath,
                'role' => $fallbackRole,
            ]);

            try {
                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'notes' => 'User registered (fallback): ' . ($user->full_name ?? $user->email),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e2) {
                // ignore audit log errors
            }
        } catch (\Exception $e2) {
            // If fallback creation fails, rethrow original exception
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

    // If the authenticated user is a Department Head, redirect to the department head dashboard
    if ($user && ($user->role ?? '') === 'Department Head') {
        return redirect('/department-head');
    }

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
        $currentUser = null;

        return view('users.dashboard', compact('totalAssets', 'stats', 'recentRequests', 'assignedAssets', 'user', 'currentUser'));
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

    try {
        $raw = DB::table('requests')
            ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
            ->select('requests.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code')
            ->where('requests.user_id', $user->id)
            ->orderByDesc('requests.created_at')
            ->limit(5)
            ->get();

        $recentRequests = $raw->map(function ($r) {
            return (object) [
                'type' => strtolower(str_replace(' ', '_', $r->request_type ?? 'other')),
                'description' => $r->Note ?? ($r->asset_name ?? ''),
                'status' => strtolower($r->status ?? 'pending'),
                'created_at' => \Illuminate\Support\Carbon::parse($r->created_at),
            ];
        });
    } catch (\Exception $e) {
        $recentRequests = collect([]);
    }

    return view('users.dashboard', [
        'totalAssets' => $totalAssets,
        'stats' => $stats,
        'recentRequests' => $recentRequests,
        'assignedAssets' => $assets,
        'user' => $user,
        'currentUser' => $user,
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

// Department Head dashboard (uses department_head views and department-scoped data)
Route::get('/department-head', function () {
    $user = Auth::user();
    if (!$user) {
        return redirect('/login');
    }

    // Assets belonging to the department
    $assets = Asset::with('user')
        ->whereHas('user', function ($q) use ($user) {
            $q->where('department_id', $user->department_id);
        })
        ->get();

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

    try {
        $raw = DB::table('requests')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
            ->select('requests.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code', 'users.full_name as request_user_name', 'users.id as request_user_id')
            ->where('users.department_id', $user->department_id)
            ->orderByDesc('requests.created_at')
            ->limit(5)
            ->get();

        $recentRequests = $raw->map(function ($r) {
            return (object) [
                'type' => strtolower(str_replace(' ', '_', $r->request_type ?? 'other')),
                'description' => ($r->Note ? $r->Note : ($r->asset_name ? ($r->asset_name . ' — ' . ($r->request_user_name ?? '')) : ($r->request_user_name ?? ''))),
                'status' => strtolower($r->status ?? 'pending'),
                'created_at' => \Illuminate\Support\Carbon::parse($r->created_at),
                'user_id' => $r->request_user_id ?? null,
                'user_name' => $r->request_user_name ?? null,
            ];
        });
    } catch (\Exception $e) {
        $recentRequests = collect([]);
    }

    try {
        $departmentUsers = DB::table('users')
            ->where('department_id', $user->department_id)
            ->select('id', 'full_name', 'email', 'role')
            ->orderBy('full_name')
            ->limit(10)
            ->get();
    } catch (\Exception $e) {
        $departmentUsers = collect([]);
    }

    return view('department_head.dashboard', [
        'totalAssets' => $totalAssets,
        'stats' => $stats,
        'recentRequests' => $recentRequests,
        'assignedAssets' => $assets,
        'user' => $user,
        'currentUser' => $user,
        'departmentUsers' => $departmentUsers,
    ]);
});

// Department Head: list all assets in their department
Route::get('/department-head/assets', function (Request $request) {
    $user = Auth::user();
    if (!$user) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        return redirect('/login');
    }
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    $assets = Asset::with('user')
        ->whereHas('user', function ($q) use ($user) {
            $q->where('department_id', $user->department_id);
        })
        ->orderBy('Asset_name')
        ->get();

    $totalAssets = $assets->count();
    $activeAssets = $assets->where('Lifecycle_Status', 'Active')->count();
    $pendingRequests = 0;

    return view('department_head.asset.asset', [
        'assignedAssets' => $assets,
        'totalAssets' => $totalAssets,
        'activeAssets' => $activeAssets,
        'pendingRequests' => $pendingRequests,
    ]);
});

// Department Head: view single asset (must belong to their department)
Route::get('/department-head/assets/{id}', function ($id) {
    $user = Auth::user();
    if (!$user) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        return redirect('/login');
    }
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    $asset = Asset::with('user')->find($id);
    if (!$asset) return abort(404);
    if (($asset->user?->department_id ?? null) !== $user->department_id) return abort(403);

    return view('department_head.asset.show', compact('asset'));
})->where('id', '[0-9]+');

// Department Head: take accountability for an asset (sets asset.user_id to current user)
Route::post('/department-head/assets/{id}/accountable', function (Request $request, $id) {
    $user = Auth::user();
    if (!$user) return redirect('/login');
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    $asset = Asset::with('user')->find($id);
    if (!$asset) return abort(404);
    if (($asset->user?->department_id ?? null) !== $user->department_id) return abort(403);

    $asset->user_id = $user->id;
    $asset->save();

    return redirect('/department-head/assets')->with('success', 'You are now accountable for this asset.');
})->where('id', '[0-9]+');

// Admin area -> render admin.dashboard with safe defaults
Route::get('/admin', function () {
    // Metrics for admin dashboard
    try {
        $acquiredThisMonth = Asset::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $acquiredLastMonth = Asset::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        // compute percent change vs last month (positive if more this month)
        if ($acquiredLastMonth === 0) {
            $acquiredChangePercent = $acquiredThisMonth > 0 ? 100 : 0;
        } else {
            $acquiredChangePercent = (int) round((($acquiredThisMonth - $acquiredLastMonth) / $acquiredLastMonth) * 100);
        }

        $activeAssets = Asset::where('Lifecycle_Status', 'Active')->count();
        $forRepairAssets = Asset::where('Lifecycle_Status', 'For Repair')->count();
        // pending requests (case-insensitive)
        $pendingRequests = DB::table('requests')->whereRaw('LOWER(status) = ?', [strtolower('pending')])->count();

        $overviewMetrics = [
            'total_assets' => Asset::count(),
            'active_assets' => $activeAssets,
            'pending_requests' => $pendingRequests,
            'assets_for_repair' => $forRepairAssets,
        ];
    } catch (\Exception $e) {
        // If DB not available or query fails, fall back to zeros
        $acquiredThisMonth = 0;
        $acquiredLastMonth = 0;
        $acquiredChangePercent = 0;
        $activeAssets = 0;
        $forRepairAssets = 0;
        $pendingRequests = 0;
        $overviewMetrics = [
            'total_assets' => 0,
            'active_assets' => 0,
            'pending_requests' => 0,
            'assets_for_repair' => 0,
        ];
    }
    // Load recent audit log activities for dashboard
    $recentActivities = collect([]);
    try {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->leftJoin('assets', 'audit_logs.asset_id', '=', 'assets.id')
            ->leftJoin('requests', 'audit_logs.request_id', '=', 'requests.id')
            ->select('audit_logs.*', 'users.full_name as user_name', 'assets.Asset_code as asset_code', 'assets.Asset_name as asset_name', 'requests.request_type as request_type', 'requests.status as request_status')
            ->orderByDesc('audit_logs.created_at')
            ->limit(2)
            ->get();

        $recentActivities = $logs->map(function ($l) {
            $time = $l->created_at ? \Illuminate\Support\Carbon::parse($l->created_at)->diffForHumans() : '';
            $desc = $l->notes ?: '';
            $type = 'Activity';
            $status = null;

            if ($l->asset_id && str_contains(strtolower($l->notes ?? ''), 'registered')) {
                $type = 'Asset Registered';
                $desc = $l->notes ?: ('Registered asset ' . ($l->asset_code ?? ''));
            } elseif ($l->request_id && str_contains(strtolower($l->notes ?? ''), 'submitted')) {
                $type = 'Request Submitted';
                $desc = $l->notes ?: ('Request for ' . ($l->asset_code ?? ''));
            } elseif ($l->request_id && str_contains(strtolower($l->notes ?? ''), 'approved')) {
                $type = 'Request Approved';
                $desc = $l->notes ?: ('Approved ' . ($l->request_type ?? 'request'));
                $status = 'approved';
            } elseif ($l->request_id && str_contains(strtolower($l->notes ?? ''), 'rejected')) {
                $type = 'Request Rejected';
                $desc = $l->notes ?: ('Rejected ' . ($l->request_type ?? 'request'));
                $status = 'rejected';
            } elseif ($l->user_id && str_contains(strtolower($l->notes ?? ''), 'user registered')) {
                $type = 'New User Registered';
                $desc = $l->notes ?: ('New user: ' . ($l->user_name ?? ''));
            }

            return (object) [
                'type' => $type,
                'description' => $desc,
                'time' => $time,
                'status' => $status,
            ];
        });
    } catch (\Exception $e) {
        $recentActivities = collect();
    }
    $pulledOutAssets = 0;
    $disposedAssets = 0;

    return view('admin.dashboard', compact(
        'acquiredThisMonth', 'activeAssets', 'forRepairAssets', 'pendingRequests',
        'overviewMetrics', 'recentActivities', 'pulledOutAssets', 'disposedAssets'
    ));
});

// API: lookup asset by Asset_code (used by admin scanner modal)
Route::get('/admin/assets/scan', function (Request $request) {
    $code = $request->query('code');
    if (!$code) {
        return response()->json(['success' => false, 'message' => 'Missing code'], 400);
    }
    // Try a few safe ways to query the asset code to be compatible with different DB identifier casing
    $asset = null;
    $codeLower = strtolower($code);
    try {
        // Try quoting the column name first (handles case-sensitive column names)
        $asset = Asset::with('user')->whereRaw('LOWER("Asset_code") = ?', [$codeLower])->first();
    } catch (\Exception $e) {
        // ignore and try unquoted
        try {
            $asset = Asset::with('user')->whereRaw('LOWER(asset_code) = ?', [$codeLower])->first();
        } catch (\Exception $e) {
            // final fallback: try direct equality on possible column names
            try {
                $asset = Asset::with('user')->where('Asset_code', $code)->orWhere('asset_code', $code)->first();
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Lookup failed (DB error).'], 500);
            }
        }
    }

    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    // Get department name from user's department_id
    $departmentName = null;
    if ($asset->user && $asset->user->department_id) {
        $dept = DB::table('departments')->where('id', $asset->user->department_id)->first();
        $departmentName = $dept?->Name ?? $dept?->name ?? null;
    }

    $data = [
        'id' => $asset->id,
        'asset_code' => $asset->Asset_code ?? $asset->asset_code ?? null,
        'name' => $asset->Asset_name ?? $asset->name ?? null,
        'status' => $asset->Lifecycle_Status ?? $asset->lifecycle_status ?? null,
        'department' => $departmentName,
        'owner' => $asset->user?->full_name ?? null,
        'serial' => $asset->serial_Number ?? $asset->serial_number ?? null,
        'location' => $asset->asset_location ?? null,
        'purchase_price' => isset($asset->purchase_Price) ? number_format($asset->purchase_Price, 2) : (isset($asset->purchase_price) ? number_format($asset->purchase_price, 2) : null),
        'image_url' => $asset->url ?? null,
    ];

    return response()->json(['success' => true, 'data' => $data]);
});

// Create Department Endpoint
Route::post('/admin/create-department', function (Request $request) {
    $request->validate([
        'department_name' => 'required|string|max:100|unique:departments,Name',
    ]);

    try {
        $departmentId = DB::table('departments')->insertGetId([
            'Name' => $request->input('department_name'),
            'status' => 'Active',
            'Create_at' => now(),
            'Update_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'id' => $departmentId
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create department: ' . $e->getMessage()
        ], 422);
    }
});

// Get All Departments Endpoint
Route::get('/admin/get-departments', function () {
    try {
        $departments = DB::table('departments')->orderBy('Name')->get();
        return response()->json([
            'success' => true,
            'departments' => $departments
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch departments'
        ], 422);
    }
});

// Get all users in a department
Route::get('/admin/department/{deptId}/users', function ($deptId) {
    try {
        $users = DB::table('users')
            ->where('department_id', $deptId)
            ->select('id', 'full_name', 'email', 'unit_heads_number', 'role')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch users'
        ], 422);
    }
});

// Assign a user as Department Head for a department
Route::post('/admin/department/{deptId}/assign-head', function (Request $request, $deptId) {
    $request->validate([
        'user_id' => 'required|integer|exists:users,id',
    ]);

    try {
        $userId = $request->input('user_id');
        
        // Verify user belongs to this department
        $user = DB::table('users')->where('id', $userId)->where('department_id', $deptId)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User does not belong to this department'], 422);
        }

        // Remove Department Head role from all users in this department
        DB::table('users')
            ->where('department_id', $deptId)
            ->where('role', 'Department Head')
            ->update(['role' => 'Employee']);

        // Assign Department Head role to the selected user
        DB::table('users')
            ->where('id', $userId)
            ->update(['role' => 'Department Head']);

        return response()->json([
            'success' => true,
            'message' => 'Department head assigned successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to assign department head: ' . $e->getMessage()
        ], 422);
    }
});

// Admin assets page - load assets grouped by department
Route::get('/admin/assets', function () {
    $assets = Asset::with('user')->get();
    
    // Get all departments from database
    $allDepartments = DB::table('departments')->orderBy('Name')->get();
    
    // Initialize departments array with data from database
    $departments = [];
    foreach ($allDepartments as $dept) {
        $departments[$dept->Name] = (object) [
            'id' => $dept->id,
            'name' => $dept->Name,
            'head' => '',
            'total_assets' => 0,
            'assets' => [],
        ];
    }
    
    // Find department heads - users with Department Head role linked to each department
    foreach ($departments as $deptName => $dept) {
        $headUser = DB::table('users')
            ->where('department_id', $dept->id)
            ->where('role', 'Department Head')
            ->first();
        $dept->head_id = $headUser?->id ?? null;
        $dept->head_email = $headUser?->email ?? '';
        $dept->head = $headUser?->full_name ?? '';
    }

    // Group assets by user's department
    foreach ($assets as $asset) {
        $userDeptId = $asset->user?->department_id;
        if (!$userDeptId) continue;
        
        // Find department by id
        $deptObj = null;
        foreach ($departments as $dept) {
            if ($dept->id == $userDeptId) {
                $deptObj = $dept;
                break;
            }
        }
        if (!$deptObj) continue;

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

        $deptObj->assets[] = (object) [
            'id' => $asset->id,
            'name' => $asset->Asset_name ?? '',
            'asset_code' => $asset->Asset_code ?? '',
            'status' => $status,
            'assigned_to' => $asset->user?->full_name ?? 'Unassigned',
            'acquisition_date' => $asset->accusion_date ? (string)$asset->accusion_date : null,
            'url' => $asset->url ?? null,
        ];
        $deptObj->total_assets++;
    }

    // Convert to indexed array
    $ordered = array_values($departments);

    return view('admin.assets.asset', ['departments' => $ordered]);

});

// Admin audit logs page
Route::get('/admin/audit-logs', function () {
    try {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->leftJoin('assets', 'audit_logs.asset_id', '=', 'assets.id')
            ->leftJoin('requests', 'audit_logs.request_id', '=', 'requests.id')
            ->select('audit_logs.*', 'users.id as user_id', 'users.full_name as user_name', 'users.role as user_role', 'assets.id as asset_id', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code', 'requests.id as request_id', 'requests.request_type as request_type')
            ->orderByDesc('audit_logs.created_at')
            ->paginate(50);
    } catch (\Exception $e) {
        $logs = collect([]);
    }

    $totalLogs = DB::table('audit_logs')->count();
    $todayLogs = DB::table('audit_logs')->whereDate('created_at', now()->toDateString())->count();
    $weekLogs = DB::table('audit_logs')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
    $activeUsers = DB::table('users')->count();

    return view('admin.audit-log.audit_logs', compact('logs', 'totalLogs', 'todayLogs', 'weekLogs', 'activeUsers'));
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
    // Check if parameter is numeric (ID) or string (name)
    $dept = null;
    if (is_numeric($department)) {
        $dept = DB::table('departments')->where('id', $department)->first();
    } else {
        $departmentName = urldecode($department);
        $dept = DB::table('departments')->where('Name', $departmentName)->first();
    }
    
    if (!$dept) {
        abort(404, 'Department not found');
    }
    
    $departmentId = $dept->id;
    $departmentName = $dept->Name;

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
        ->whereHas('user', function ($query) use ($departmentId) {
            $query->where('department_id', $departmentId);
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
                'qr_code_path' => $asset->qr_code_path ?? null,
                'qr_code_url' => $asset->qr_code_url ?? ($asset->qr_code_path ? Storage::url($asset->qr_code_path) : null),
                'serial_number' => $asset->serial_Number ?? null,
                'purchase_price' => isset($asset->purchase_Price) ? number_format($asset->purchase_Price, 2) : null,
                'warranty_months' => $asset->warranty_months ?? null,
                'condition' => $asset->Condition ?? null,
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

// Admin asset detail view - must come before wildcard routes
Route::get('/admin/assets/{id}', function ($id) {
    $asset = Asset::with('user')->find($id);
    if (!$asset) {
        abort(404);
    }
    return view('admin.assets.show', compact('asset'));
})->where('id', '[0-9]+');

// Download Inventory - Export all assets as CSV - IMPORTANT: place before catch-all routes
Route::get('/admin/inventory-download', function () {
    try {
        // Fetch all assets with user information (accountable person)
        $assets = DB::table('assets')
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->select(
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'users.full_name as accountable',
                'assets.accusion_date',
                'assets.asset_location',
                'assets.Lifecycle_Status',
                'assets.purchase_Price'
            )
            ->orderBy('assets.Asset_code')
            ->get();

        // Generate CSV in memory
        $filename = 'inventory_' . date('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://memory', 'r+');
        
        // Header row - matching the report format
        fputcsv($handle, ['ASSET ID', 'ASSET NAME', 'CATEGORY', 'ACCOUNTABLE', 'DATE ACQUIRED', 'CURRENT LOCATION', 'LIFECYCLE STAGE', 'VALUE']);
        
        // Data rows
        foreach ($assets as $asset) {
            fputcsv($handle, [
                $asset->Asset_code ?? 'N/A',
                $asset->Asset_name ?? 'N/A',
                $asset->Category ?? 'N/A',
                $asset->accountable ?? 'Unassigned',
                $asset->accusion_date ?? 'N/A',
                $asset->asset_location ?? 'N/A',
                $asset->Lifecycle_Status ?? 'N/A',
                '₱' . number_format($asset->purchase_Price ?? 0, 2)
            ]);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'no-cache')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Expires', '0');
    } catch (\Exception $e) {
        return redirect('/admin/assets')->with('error', 'Failed to download inventory: ' . $e->getMessage());
    }
});

// API: Notifications - user repair request statuses
Route::get('/api/notifications/user', function () {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['repairs' => []]);
    }

    $repairs = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->select('requests.id', 'requests.status', 'requests.updated_at', 'assets.Asset_name')
        ->where('requests.user_id', $user->id)
        ->where('requests.request_type', 'Repair')
        ->orderByDesc('requests.updated_at')
        ->limit(50)
        ->get();

    return response()->json(['repairs' => $repairs]);
})->middleware('auth');

// API: Notifications - department repair request statuses (for department head)
Route::get('/api/notifications/department', function () {
    $user = Auth::user();
    if (!$user || !$user->department_id) {
        return response()->json(['repairs' => []]);
    }

    $repairs = DB::table('requests')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->select('requests.id', 'requests.status', 'requests.updated_at', 'assets.Asset_name', 'users.full_name as submitted_by')
        ->where('requests.request_type', 'Repair')
        ->where('users.department_id', $user->department_id)
        ->orderByDesc('requests.updated_at')
        ->limit(50)
        ->get();

    return response()->json(['repairs' => $repairs]);
})->middleware('auth');

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
                        'Lifecycle_Status' => $a->Lifecycle_Status ?? '',
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
    $repairs = collect();

    if (\Illuminate\Support\Facades\Schema::hasTable('repairs')) {
        try {
            $repairs = DB::table('repairs')
                ->leftJoin('assets', 'repairs.asset_id', '=', 'assets.id')
                ->leftJoin('requests', 'repairs.request_id', '=', 'requests.id')
                ->leftJoin('users', 'requests.user_id', '=', 'users.id')
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->select(
                    'repairs.*',
                    'assets.Asset_name as asset_name',
                    'assets.Asset_code as asset_code',
                    'assets.serial_Number as serial_number',
                    'assets.purchase_Price as purchase_price',
                    'assets.warranty_months as warranty_months',
                    'assets.Condition as condition',
                    'assets.asset_location as asset_location',
                    'assets.supplier as supplier',
                    'assets.model as model',
                    'assets.manufacture as manufacture',
                    'users.full_name as requested_by',
                    'departments.Name as department'
                )
                ->orderByDesc('repairs.created_at')
                ->get()
                ->map(function ($r) {
                    return (object) [
                        'id' => $r->id,
                        'asset_name' => $r->asset_name ?? ('Asset #' . ($r->asset_id ?? '')),
                        'asset_code' => $r->asset_code ?? null,
                        'issue' => $r->Repair_Description ?? ($r->notes ?? ''),
                        'requested_by' => $r->requested_by ?? 'System',
                        'department' => $r->department ?? null,
                        'date_requested' => $r->Repair_Date ?? $r->created_at,
                        'priority' => $r->priority ?? 'low',
                        'status' => isset($r->status) ? str_replace(' ', '_', strtolower($r->status)) : 'pending',
                        'serial_number' => $r->serial_number ?? null,
                        'purchase_price' => isset($r->purchase_price) ? (float) $r->purchase_price : null,
                        'warranty_months' => isset($r->warranty_months) ? (int) $r->warranty_months : null,
                        'condition' => $r->condition ?? null,
                        'asset_location' => $r->asset_location ?? null,
                        'supplier' => $r->supplier ?? null,
                        'model' => $r->model ?? null,
                        'manufacture' => $r->manufacture ?? null,
                        'estimated_cost' => isset($r->Repair_Cost) ? (float) $r->Repair_Cost : null,
                        'technician' => $r->Approve_by ?? null,
                        'completion_date' => null,
                        'notes' => $r->notes ?? null,
                    ];
                });
        } catch (\Exception $e) {
            $repairs = collect();
        }
    }

    return view('admin.repair.repair', compact('repairs'));
});

// Admin update repair status endpoint
Route::post('/admin/repairs/{id}/status', function ($id, \Illuminate\Http\Request $request) {
    $newStatus = $request->input('status');
    $valid = ['pending', 'in_progress', 'completed', 'cancelled'];
    
    if (!in_array($newStatus, $valid)) {
        return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
    }
    
    try {
        $repair = DB::table('repairs')->where('id', $id)->first();
        if (!$repair) {
            return response()->json(['success' => false, 'message' => 'Repair not found'], 404);
        }
        
        // Format status for database (e.g., "in_progress" -> "In Progress")
        $statusMap = [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];
        $formattedStatus = $statusMap[$newStatus] ?? ucfirst($newStatus);
        
        // Update repair status
        DB::table('repairs')->where('id', $id)->update([
            'status' => $formattedStatus,
            'updated_at' => now(),
        ]);
        
        // If marking as completed, update the asset's Lifecycle_Status to Active
        if ($newStatus === 'completed' && $repair->asset_id) {
            DB::table('assets')->where('id', $repair->asset_id)->update([
                'Lifecycle_Status' => 'Active',
                'updated_at' => now(),
            ]);
        }
        
        return response()->json(['success' => true, 'message' => 'Repair status updated successfully']);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Repair status update error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to update repair status: ' . $e->getMessage()], 500);
    }
});

// Admin replacement page
Route::get('/admin/replacement', function () {
    $replacements = null;
    $totalReplacements = 0;
    $pendingReplacements = 0;
    $approvedReplacements = 0;
    $receivedReplacements = 0;

    if (\Illuminate\Support\Facades\Schema::hasTable('replacements')) {
        try {
            $base = DB::table('replacements')
                ->leftJoin('requests', 'replacements.request_id', '=', 'requests.id')
                ->leftJoin('users', 'requests.user_id', '=', 'users.id')
                ->leftJoin('assets as old', 'replacements.old_asset_id', '=', 'old.id')
                ->leftJoin('assets as nw', 'replacements.new_asset_id', '=', 'nw.id')
                ->select(
                    'replacements.*',
                    'old.Asset_name as old_asset_name',
                    'old.Asset_code as old_asset_code',
                    'old.qr_code_path as old_asset_qr',
                    'nw.Asset_name as new_asset_name',
                    'nw.Asset_code as new_asset_code',
                    'nw.qr_code_path as new_asset_qr',
                    'users.full_name as requested_by'
                );

            $replacements = $base->orderByDesc('replacements.created_at')->paginate(10);

            // summary counts
            $totalReplacements = DB::table('replacements')->count();
            $pendingReplacements = DB::table('replacements')->where('status', 'Pending')->count();
            $approvedReplacements = DB::table('replacements')->where('status', 'Approved')->count();
            $receivedReplacements = DB::table('replacements')->where('status', 'Received')->count();
        } catch (\Exception $e) {
            // fall back to empty paginator
            $replacements = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, ['path' => request()->url()]);
        }
    } else {
        $replacements = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, ['path' => request()->url()]);
    }

    return view('admin.replacement.replacement', compact('replacements', 'totalReplacements', 'pendingReplacements', 'approvedReplacements', 'receivedReplacements'));
});

// Create & Link new asset for a replacement (accept POST or PATCH to be permissive)
Route::match(['post','patch'], '/admin/replacements/{id}/link', function (Request $request, $id) {
    $user = Auth::user();
    if (!$user) return redirect('/login');

    $replacement = DB::table('replacements')->where('id', $id)->first();
    if (!$replacement) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Replacement not found'], 404);
        }
        return abort(404);
    }

    // Get the old asset to inherit some fields (like user assignment)
    $old = DB::table('assets')->where('id', $replacement->old_asset_id)->first();

    $code = $request->input('Asset_code') ?: ('AST-' . Str::upper(Str::random(8)));

    try {
        Log::info('Linking new asset for replacement', ['replacement_id' => $id, 'code' => $code]);
        
        // Update the old asset with the new code and regenerated QR
        // This replaces the old asset's code/QR with the new one
        $oldAssetId = $replacement->old_asset_id;
        
        // Generate new QR code for the replacement
        $qrPath = null;
        $qrUrl = null;
        try {
            $qrSource = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($code);
            $contents = @file_get_contents($qrSource);
            if ($contents) {
                $qrPath = 'assets/qr/' . $code . '.png';
                Storage::disk('public')->put($qrPath, $contents);
                $qrUrl = Storage::url($qrPath);
            }
        } catch (\Throwable $e) {
            Log::warning('QR generation failed', ['code' => $code, 'error' => $e->getMessage()]);
        }

        // Update the old asset with the new code and QR path
        $updateData = ['Asset_code' => $code, 'updated_at' => now()];
        if ($qrPath) $updateData['qr_code_path'] = $qrPath;
        
        DB::table('assets')->where('id', $oldAssetId)->update($updateData);
        
        // Link replacement to old asset (since we updated it with new code)
        DB::table('replacements')->where('id', $id)->update(['new_asset_id' => $oldAssetId, 'updated_at' => now()]);

        Log::info('Updated old asset with new code for replacement', ['old_asset_id' => $oldAssetId, 'new_code' => $code]);

        // If the request expects JSON (AJAX), return the updated asset info
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'asset' => [
                    'id' => $oldAssetId,
                    'code' => $code,
                    'name' => $old->Asset_name ?? 'Asset',
                    'qr_url' => $qrUrl ?? '',
                ],
                'replacement_id' => $id,
            ]);
        }

        $redirectTo = url()->previous() ?: '/admin/replacement';
        return redirect($redirectTo)->with('success', 'Asset code and QR updated for replacement.');
    } catch (\Exception $e) {
        Log::error('Failed to update asset for replacement', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update asset: ' . $e->getMessage()
            ], 400);
        }
        return back()->with('error', 'Failed to update asset: ' . $e->getMessage());
    }
});

// Admin pullout page
Route::get('/admin/pullout', function () {
    $totalPulledOut = 0;
    $pulloutRecords = collect([]);
    $availableAssets = collect([]);

    if (\Illuminate\Support\Facades\Schema::hasTable('pullouts')) {
        try {
            $query = DB::table('pullouts')
                ->leftJoin('assets', 'pullouts.asset_id', '=', 'assets.id')
                ->leftJoin('requests', 'pullouts.request_id', '=', 'requests.id')
                ->select('pullouts.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code', 'requests.request_type as request_type', 'requests.status as request_status')
                ->orderByDesc('pullouts.pullout_date');

            $pulloutRecords = $query->get()->map(function ($r) {
                return (object) [
                    'id' => $r->id,
                    'asset_name' => $r->asset_name ?? ('Asset #' . ($r->asset_id ?? '')),
                    'asset_code' => $r->asset_code ?? null,
                    'pullout_date' => $r->pullout_date ? (string) \Illuminate\Support\Carbon::parse($r->pullout_date)->format('M d, Y') : ($r->created_at ? (string) \Illuminate\Support\Carbon::parse($r->created_at)->format('M d, Y') : '-'),
                    'reason' => $r->Description ?? $r->notes ?? null,
                    'pulled_by' => $r->Approve_by ?? null,
                    'status' => isset($r->status) && $r->status ? strtolower($r->status) : (isset($r->request_status) ? strtolower($r->request_status) : (isset($r->request_type) ? strtolower($r->request_type) : 'approved')),
                    'destination' => $r->destination ?? null,
                    'raw' => $r,
                ];
            });
            $totalPulledOut = $pulloutRecords->count();
        } catch (\Exception $e) {
            $pulloutRecords = collect();
            $totalPulledOut = 0;
        }
    }

    // Provide assets to the modal for recording new pullouts (exclude already pulled out)
    if (\Illuminate\Support\Facades\Schema::hasTable('assets')) {
        try {
            $availableAssets = Asset::where('Lifecycle_Status', '!=', 'Pullout')
                ->orderBy('Asset_name')
                ->get()
                ->map(function ($a) {
                    return (object) [
                        'id' => $a->id,
                        'name' => $a->Asset_name ?? '',
                        'asset_code' => $a->Asset_code ?? '',
                        'Lifecycle_Status' => $a->Lifecycle_Status ?? '',
                        'assignedUser' => (object) ['name' => $a->user?->full_name ?? 'Unassigned'],
                    ];
                });
        } catch (\Exception $e) {
            $availableAssets = collect();
        }
    }

    return view('admin.pullout.pullout', compact('totalPulledOut', 'pulloutRecords', 'availableAssets'));
});

// Server-side user search for assigning assets (used by asset registry)
Route::get('/admin/users/search', function (Request $request) {
    $q = $request->query('q');
    $usersQuery = User::query()
        ->select('users.id', 'users.full_name', 'users.email', 'departments.Name as department')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id');
    
    if ($q) {
        $usersQuery->where(function ($builder) use ($q) {
            $builder->where('users.full_name', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%")
                    ->orWhere('departments.Name', 'like', "%{$q}%");
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
        'qr_image' => 'nullable|string',
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
        Log::info('Asset created', ['id' => $asset->id ?? null, 'code' => $assetCode]);
        try {
            DB::table('audit_logs')->insert([
                'user_id' => Auth::id(),
                'asset_id' => $asset->id,
                'notes' => 'Registered asset ' . ($asset->Asset_code ?? $assetCode) . ' - ' . ($asset->Asset_name ?? ($validated['name'] ?? '')), 
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // ignore audit failures
        }
    } catch (\Illuminate\Database\QueryException $e) {
        // If enum mismatch or other DB error, fallback to simpler fields and return error message
        return back()->withErrors(['error' => 'Failed to save asset. DB error: ' . $e->getMessage()])->withInput();
    }

    // Ensure QR is saved to storage and recorded on the asset.
    $qrSaved = false;
    $qrData = $request->input('qr_image');
    Log::info('Received qr_image input', ['has_qr' => !empty($qrData), 'length' => is_string($qrData) ? strlen($qrData) : 0]);

    $saveQrToAsset = function ($binary, $ext) use ($asset, $assetCode, &$qrSaved) {
        try {
            $ext = $ext ?: 'png';
            $qrPath = 'assets/qr/' . ($assetCode ?: 'asset') . '-' . time() . '.' . $ext;
            $ok = Storage::disk('public')->put($qrPath, $binary);
            if ($ok) {
                $asset->qr_code_path = $qrPath;
                $asset->qr_code_url = Storage::url($qrPath);
                try {
                    $asset->save();
                } catch (\Exception $e) {
                    // fallback to direct DB update if model save fails
                    try {
                        DB::table('assets')->where('id', $asset->id)->update([
                            'qr_code_path' => $qrPath,
                            'qr_code_url' => Storage::url($qrPath),
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        // give up
                    }
                }
                Log::info('Saved QR image and updated asset record', ['asset_id' => $asset->id, 'qr_path' => $qrPath]);
                $qrSaved = true;
            } else {
                Log::warning('Failed to write QR image to storage', ['asset_id' => $asset->id ?? null, 'qr_path' => $qrPath]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while saving QR image', ['error' => $e->getMessage()]);
        }
    };

    // If client sent a data URL, decode and save
    if ($qrData && is_string($qrData) && preg_match('/^data:image\/([a-zA-Z]+);base64,/', $qrData, $m)) {
        try {
            $base64 = preg_replace('#^data:image/[^;]+;base64,#', '', $qrData);
            $binary = base64_decode($base64);
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $saveQrToAsset($binary, $ext);
        } catch (\Exception $e) {
            // ignore and continue to server-side generation
        }
    }

    // If not saved yet, try server-side generation using external API
    if (!$qrSaved) {
        try {
            $api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($asset->Asset_code);
            $binary = @file_get_contents($api);
            if ($binary !== false) {
                $saveQrToAsset($binary, 'png');
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    return redirect('/admin/assets/registry')->with('success', 'Asset registered successfully.');
})->name('admin.assets.store');

// Admin requests page
Route::get('/admin/requests', function () {
    $requests = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('users as assignee', 'requests.assign_to_user_id', '=', 'assignee.id')
        ->select([
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.Note',
            'requests.created_at',
            'requests.url',
            'users.full_name as submitted_by',
            'users.email',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
            'assignee.full_name as assigned_to',
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
                'assigned_to' => $request->assigned_to ?? null,
                'image' => $request->url ?? null,
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

        if ($requestType === 'transfer') {
            // If migration added assign_to_user_id, transfer ownership
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('requests', 'assign_to_user_id') && !empty($requestRecord->assign_to_user_id)) {
                    DB::table('assets')->where('id', $requestRecord->asset_id)->update([
                        'user_id' => $requestRecord->assign_to_user_id,
                        'updated_at' => now(),
                    ]);

                    // Optionally set lifecycle to Active
                    DB::table('assets')->where('id', $requestRecord->asset_id)->update([
                        'Lifecycle_Status' => 'Active',
                        'updated_at' => now(),
                    ]);

                    // Record audit mentioning the assignee
                    $assigneeName = DB::table('users')->where('id', $requestRecord->assign_to_user_id)->value('full_name');
                    DB::table('audit_logs')->insert([
                        'user_id' => Auth::id(),
                        'request_id' => $requestRecord->id,
                        'asset_id' => $requestRecord->asset_id,
                        'notes' => 'Transferred asset to ' . ($assigneeName ?? 'User #' . $requestRecord->assign_to_user_id),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                // ignore transfer errors but don't rollback approval
                Log::error('Transfer ownership failed for request ' . $requestRecord->id . ': ' . $e->getMessage());
            }
        }
    });

    // Audit: approved
    try {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'request_id' => $requestRecord->id,
            'asset_id' => $requestRecord->asset_id,
            'notes' => 'Approved request (' . ($requestRecord->request_type ?? '') . ') by ' . $approvedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Exception $e) {
        // ignore
    }

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

    // Audit: rejected
    try {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'request_id' => $requestRecord->id,
            'asset_id' => $requestRecord->asset_id,
            'notes' => 'Rejected request (' . ($requestRecord->request_type ?? '') . ')',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Exception $e) {
        // ignore
    }

    return response()->json(['message' => 'Request rejected successfully.']);
})->name('admin.requests.reject');

// AJAX: find asset by code (used by scanner fallback)
Route::get('/admin/assets/find-by-code', function (Request $request) {
    $code = trim((string) $request->query('code', ''));
    if ($code === '') {
        return response()->json(['message' => 'code missing'], 422);
    }
    // Case-insensitive lookup
    $codeLower = strtolower($code);
    $asset = DB::table('assets')->whereRaw('LOWER("Asset_code") = ?', [$codeLower])
        ->orWhereRaw('LOWER(asset_code) = ?', [$codeLower])
        ->first();
    
    if (!$asset) {
        // Fallback to direct comparison
        $asset = DB::table('assets')->where('Asset_code', $code)->orWhere('asset_code', $code)->first();
    }
    
    if (!$asset) {
        return response()->json(['message' => 'not found'], 404);
    }
    return response()->json([
        'id' => $asset->id, 
        'Asset_name' => $asset->Asset_name ?? null, 
        'Asset_code' => $asset->Asset_code ?? null,
        'status' => $asset->Lifecycle_Status ?? null
    ]);
})->middleware('auth');

// Record disposal (called by scanner auto-submit or manual form)
Route::post('/admin/disposal/record', function (Request $request) {
    $data = $request->only(['asset_id', 'disposal_date', 'reason', 'disposed_by', 'notes']);

    if (empty($data['asset_id'])) {
        return response()->json(['message' => 'asset_id required'], 422);
    }

    $asset = DB::table('assets')->where('id', $data['asset_id'])->first();
    if (!$asset) {
        return response()->json(['message' => 'asset not found'], 404);
    }

    // Check if asset is already disposed
    $assetStatus = $asset->Lifecycle_Status ?? null;
    if ($assetStatus === 'Disposal' || $assetStatus === 'Disposed') {
        return response()->json(['message' => 'This asset is already disposed and cannot be recorded again.'], 422);
    }

    // Check if a disposal record already exists for this asset
    $existing = DB::table('disposals')->where('asset_id', $data['asset_id'])->exists();
    if ($existing) {
        return response()->json(['message' => 'A disposal record already exists for this asset.'], 422);
    }

    $disposalDate = $data['disposal_date'] ?: date('Y-m-d');

    try {
        $id = DB::table('disposals')->insertGetId([
            'asset_id' => $asset->id,
            'disposal_date' => $disposalDate,
            'Description' => $data['reason'] ?? 'Scanned Disposal',
            'Approve_by' => $data['disposed_by'] ?? (Auth::user()?->full_name ?? Auth::user()?->email ?? 'Admin'),
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // mark asset lifecycle as disposed
        try {
            DB::table('assets')->where('id', $asset->id)->update([
                'Lifecycle_Status' => 'Disposed',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json(['message' => 'disposal recorded', 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'failed to record disposal', 'error' => $e->getMessage()], 500);
    }
})->middleware('auth');

// Record pullout (called by scanner auto-submit or manual form)
Route::post('/admin/pullout/record', function (Request $request) {
    $data = $request->only(['asset_id', 'pullout_date', 'reason', 'pulled_by', 'notes']);

    if (empty($data['asset_id'])) {
        return response()->json(['message' => 'asset_id required'], 422);
    }

    $asset = DB::table('assets')->where('id', $data['asset_id'])->first();
    if (!$asset) {
        return response()->json(['message' => 'asset not found'], 404);
    }

    // Check if asset is already pulled out
    $assetStatus = $asset->Lifecycle_Status ?? null;
    if ($assetStatus === 'Pullout' || $assetStatus === 'pulled_out') {
        return response()->json(['message' => 'This asset is already pulled out and cannot be recorded again.'], 422);
    }

    // Check if a pullout record already exists for this asset
    $existing = DB::table('pullouts')->where('asset_id', $data['asset_id'])->exists();
    if ($existing) {
        return response()->json(['message' => 'A pullout record already exists for this asset.'], 422);
    }

    $pulloutDate = $data['pullout_date'] ?: date('Y-m-d');

    try {
        $id = DB::table('pullouts')->insertGetId([
            'asset_id' => $asset->id,
            'pullout_date' => $pulloutDate,
            'Description' => $data['reason'] ?? 'Scanned Pullout',
            'Approve_by' => $data['pulled_by'] ?? (Auth::user()?->full_name ?? Auth::user()?->email ?? 'Admin'),
            'notes' => $data['notes'] ?? null,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // mark asset lifecycle as pullout
        try {
            DB::table('assets')->where('id', $asset->id)->update([
                'Lifecycle_Status' => 'Pullout',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json(['message' => 'pullout recorded', 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'failed to record pullout', 'error' => $e->getMessage()], 500);
    }
})->middleware('auth');

// Logout route - logs out user and redirects to welcome page
Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
});

// Also accept POST logout (named) so `route('logout')` works from forms
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('logout');

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
        try {
            $user = Auth::user();
            
            if (!$user) {
                return redirect('/login');
            }

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
        } catch (\Throwable $e) {
            return redirect('/users')->withErrors(['error' => 'Unable to load requests. Please try again later.']);
        }
    })->name('user.requests.index');
    Route::get('/user/request-asset', [UserRequestController::class, 'create'])->name('user.request-asset');
    Route::post('/user/requests/store', [UserRequestController::class, 'store'])->name('user.requests.store');

    // Department Head: view requests submitted by users in the head's department
    Route::get('/department-head/requests', function () {
        $user = Auth::user();
        if (!$user) return redirect('/login');
        if (($user->role ?? '') !== 'Department Head') return abort(403);

        $requests = DB::table('requests')
            ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->select('requests.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code', 'users.full_name as requester_name')
            ->where('users.department_id', $user->department_id)
            ->orderByDesc('requests.created_at')
            ->paginate(10);

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
                'requester_name' => $r->requester_name ?? null,
            ];
        });

        // Totals for department
        $totalRequests = DB::table('requests')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)
            ->count();
        $pendingRequests = DB::table('requests')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)
            ->where('requests.status', 'Pending')
            ->count();
        $approvedRequests = DB::table('requests')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)
            ->where('requests.status', 'Approved')
            ->count();
        $rejectedRequests = DB::table('requests')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)
            ->where('requests.status', 'Rejected')
            ->count();

        return view('department_head.request.request', [
            'requests' => $requests,
            'totalRequests' => $totalRequests,
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
            'user' => $user,
            'currentUser' => $user,
        ]);
    })->name('department_head.requests.index');

    // Department Head: request form (uses department_head view)
    Route::get('/department-head/request-asset', function () {
        $user = Auth::user();
        if (!$user) return redirect('/login');
        if (($user->role ?? '') !== 'Department Head') return abort(403);
        return view('department_head.request.request_asset');
    })->name('department_head.request-asset');

    // Department Head: submit request (reuse controller)
    Route::post('/department-head/requests/store', [UserRequestController::class, 'store'])->name('department_head.requests.store');
});

