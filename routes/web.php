<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UserRequestController;
use Carbon\Carbon;

if (!function_exists('normalizePulloutAssetIds')) {
    function normalizePulloutAssetIds($assetIds): array
    {
        if ($assetIds instanceof \Illuminate\Support\Collection) {
            $assetIds = $assetIds->all();
        }

        if (!is_array($assetIds)) {
            $assetIds = [$assetIds];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($assetId) => (int) $assetId,
            $assetIds
        ))));
    }
}

if (!function_exists('createPulloutTransaction')) {
    function createPulloutTransaction(array $data, array $assetIds): int
    {
        $assetIds = normalizePulloutAssetIds($assetIds);

        if (empty($assetIds)) {
            throw new \InvalidArgumentException('At least one asset must be selected.');
        }

        return DB::transaction(function () use ($data, $assetIds) {
            $pulloutId = DB::table('pullouts')->insertGetId([
                'request_id' => $data['request_id'] ?? null,
                'asset_id' => $data['asset_id'] ?? ($assetIds[0] ?? null),
                'Approve_by' => $data['Approve_by'] ?? null,
                'Description' => $data['Description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'pullout_date' => $data['pullout_date'] ?? now()->toDateString(),
                'destination' => $data['destination'] ?? null,
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($assetIds as $assetId) {
                DB::table('pullout_items')->insert([
                    'pullout_id' => $pulloutId,
                    'asset_id' => $assetId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $pulloutId;
        });
    }
}

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
    // Validate form inputs
    $validated = $request->validate([
        'unit_heads_number' => 'required|string|max:20',
        'email' => 'required|email|max:100|unique:users,email',
        'password' => 'required|confirmed|min:6',
        'profile_photo' => 'nullable|image|max:2048',
    ]);

    // Look up employee in employee_numbers table
    $employee = DB::table('employee_numbers')
        ->where('Employee_number', $validated['unit_heads_number'])
        ->first();
    
    if (!$employee) {
        return back()->withErrors(['unit_heads_number' => 'Employee number not found in the system. Please contact the administrator.'])->withInput();
    }

    // Check if this employee number is already registered
    $existingUser = User::where('employee_numbers_id', $employee->id)->first();
    if ($existingUser) {
        return back()->withErrors(['unit_heads_number' => 'This employee number is already registered. Please use a different employee number or contact the administrator.'])->withInput();
    }

    // Get or verify department
    $departmentId = $employee->Department_id;
    $fullName = $employee->Full_Name;

    // Check if profile photo exists
    $photoPath = null;
    if ($request->hasFile('profile_photo')) {
        $photoPath = $request->file('profile_photo')->store('profile_photos', 'public');
    }

    // Determine user role
    $isFirstUser = User::count() === 0;
    
    if ($isFirstUser) {
        $role = 'Admin';
    } else {
        // Check if department already has a Department Head
        $hasDeptHead = User::where('department_id', $departmentId)->where('role', 'Department Head')->exists();
        $role = $hasDeptHead ? 'Employee' : 'Department Head';
    }

    try {
        $user = User::create([
            'employee_numbers_id' => $employee->id,
            'department_id' => $departmentId,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'profile_photo' => $photoPath,
            'role' => $role,
            'status' => 'Active',
        ]);

        // Record audit log for new user registration
        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'action_type' => 'CREATE',
            'notes' => 'User registered: ' . ($fullName ?? $user->email),
            'action_description' => 'New user account created with email: ' . $user->email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Exception $e) {
        return back()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()])->withInput();
    }

    return redirect('/login')->with('success', 'Registration successful. Please login.');
});

// Users area -> render users.dashboard and show assigned assets when logged in
// User assets page (My Assets) - show assigned assets for authenticated user
Route::get('/users/assets', function () {
    $user = Auth::user();
    $assignedAssets = collect([]);
    if ($user) {
        // Fetch assets with their first attached image from asset_files table
        $assignedAssets = DB::table('assets')
            ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
            ->where('assets.user_id', $user->id)
            ->select(
                'assets.id',
                'assets.user_id',
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'assets.Condition',
                'assets.Lifecycle_Status',
                'assets.accusion_date',
                'assets.asset_location',
                'assets.next_maintenance_date',
                'assets.qr_code_path',
                DB::raw('MAX(asset_files.url) as image_url')
            )
            ->groupBy('assets.id', 'assets.user_id', 'assets.Asset_code', 'assets.Asset_name', 'assets.Category', 'assets.Condition', 'assets.Lifecycle_Status', 'assets.accusion_date', 'assets.asset_location', 'assets.next_maintenance_date', 'assets.qr_code_path')
            ->orderBy('assets.Asset_name')
            ->get();
    }
    return view('users.asset.asset', compact('assignedAssets'));
});

// User-facing asset detail (only accessible to assigned user or admins)
Route::get('/users/assets/{id}', function ($id) {
    // Fetch asset with its image from asset_files and user with employee_numbers
    $asset = DB::table('assets')
        ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('assets.id', $id)
        ->select(
            'assets.id',
            'assets.user_id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Category',
            'assets.Condition',
            'assets.Lifecycle_Status',
            'assets.accusion_date',
            'assets.purchase_Price',
            'assets.serial_Number',
            'assets.asset_location',
            'assets.next_maintenance_date',
            'asset_files.url as image_url',
            'employee_numbers.Full_Name as full_name'
        )
        ->first();
    
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
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
            ->select('requests.*', 'assets.Asset_name as asset_name', 'assets.Asset_code as asset_code', 'employee_numbers.Full_Name as request_user_name', 'users.id as request_user_id')
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
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.department_id', $user->department_id)
            ->select('users.id', 'employee_numbers.Full_Name as full_name', 'users.email', 'users.role')
            ->orderBy('employee_numbers.Full_Name')
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

    // Fetch all assets in the department with their images from asset_files
    $assets = DB::table('assets')
        ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->where('users.department_id', $user->department_id)
        ->select(
            'assets.id',
            'assets.user_id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Category',
            'assets.Condition',
            'assets.Lifecycle_Status',
            'assets.accusion_date',
            'assets.asset_location',
            'assets.next_maintenance_date',
            'assets.qr_code_path',
            'users.department_id',
            DB::raw('MAX(asset_files.url) as image_url')
        )
        ->groupBy('assets.id', 'assets.user_id', 'assets.Asset_code', 'assets.Asset_name', 'assets.Category', 'assets.Condition', 'assets.Lifecycle_Status', 'assets.accusion_date', 'assets.asset_location', 'assets.next_maintenance_date', 'assets.qr_code_path', 'users.department_id')
        ->orderBy('assets.Asset_name')
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
        return redirect('/login');
    }
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    // Fetch asset with its image and owner name
    $asset = DB::table('assets')
        ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('assets.id', $id)
        ->select(
            'assets.id',
            'assets.user_id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Category',
            'assets.Condition',
            'assets.Lifecycle_Status',
            'assets.accusion_date',
            'assets.purchase_Price',
            'assets.serial_Number',
            'assets.asset_location',
            'assets.next_maintenance_date',
            'asset_files.url as image_url',
            'users.department_id',
            'employee_numbers.Full_Name as full_name'
        )
        ->first();

    if (!$asset) return abort(404);
    if (($asset->department_id ?? null) !== $user->department_id) return abort(403);

    // Repair history for this asset only
    $repairs = DB::table('repairs')
        ->where('Assets_id', $id)
        ->orderByDesc('Repair_Date')
        ->get();

    return view('department_head.asset.show', compact('asset', 'repairs'));
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
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('assets', 'audit_logs.asset_id', '=', 'assets.id')
            ->leftJoin('requests', 'audit_logs.request_id', '=', 'requests.id')
            ->select('audit_logs.*', 'employee_numbers.Full_Name as user_name', 'assets.Asset_code as asset_code', 'assets.Asset_name as asset_name', 'requests.request_type as request_type', 'requests.status as request_status')
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
    $code = trim((string) $request->query('code', ''));
    if ($code === '') {
        return response()->json(['success' => false, 'message' => 'code missing'], 422);
    }

    $codeLower = strtolower($code);

    $asset = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
        ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
        ->whereRaw('LOWER("Asset_code") = ?', [$codeLower])
        ->select(
            'assets.id',
            'assets.Asset_name',
            'assets.Asset_code',
            'assets.Lifecycle_Status',
            'assets.asset_location',
            'employee_numbers.Full_Name as owner_name',
            'departments.Name as department_name',
            DB::raw('MAX(asset_files.url) as image_url')
        )
        ->groupBy(
            'assets.id',
            'assets.Asset_name',
            'assets.Asset_code',
            'assets.Lifecycle_Status',
            'assets.asset_location',
            'employee_numbers.Full_Name',
            'departments.Name'
        )
        ->first();

    // Fallback if quoted column fails
    if (!$asset) {
        $asset = DB::table('assets')
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
            ->whereRaw('LOWER(Asset_code) = ?', [$codeLower])
            ->select(
                'assets.id',
                'assets.Asset_name',
                'assets.Asset_code',
                'assets.Lifecycle_Status',
                'assets.asset_location',
                'employee_numbers.Full_Name as owner_name',
                'departments.Name as department_name',
                DB::raw('MAX(asset_files.url) as image_url')
            )
            ->groupBy(
                'assets.id',
                'assets.Asset_name',
                'assets.Asset_code',
                'assets.Lifecycle_Status',
                'assets.asset_location',
                'employee_numbers.Full_Name',
                'departments.Name'
            )
            ->first();
    }

    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    // Build image URL
    $imageUrl = null;
    if (!empty($asset->image_url)) {
        $img = $asset->image_url;
        if (str_starts_with($img, 'http') || str_starts_with($img, '/storage')) {
            $imageUrl = $img;
        } else {
            $imageUrl = asset('storage/' . ltrim($img, '/'));
        }
    }

    return response()->json([
        'success' => true,
        'data' => [
            'id'         => $asset->id,
            'name'       => $asset->Asset_name ?? '-',
            'asset_code' => $asset->Asset_code ?? '-',
            'status'     => $asset->Lifecycle_Status ?? '-',
            'department' => $asset->department_name ?? '-',
            'owner'      => $asset->owner_name ?? 'Unassigned',
            'location'   => $asset->asset_location ?? '-',
            'image_url'  => $imageUrl,
        ],
    ]);
})->middleware('auth');

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
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.department_id', $deptId)
            ->select('users.id', 'employee_numbers.Full_Name as full_name', 'users.email', 'employee_numbers.Employee_number as employee_number', 'users.role')
            ->orderBy('employee_numbers.Full_Name')
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
    // Eager-load user + full name from employee_numbers
    $assets = Asset::with(['user' => function ($q) {
        $q->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
          ->addSelect(
              'users.id',
              'users.department_id',
              'users.email',
              'users.employee_numbers_id',
              'employee_numbers.Full_Name as full_name'
          );
    }])->get();

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
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.department_id', $dept->id)
            ->where('users.role', 'Department Head')
            ->select('users.id', 'users.email', 'employee_numbers.Full_Name as full_name')
            ->first();

        $dept->head_id = $headUser?->id ?? null;
        $dept->head_email = $headUser?->email ?? '';
        $dept->head = $headUser?->full_name ?? '';
    }

    // Group assets by user's department
    foreach ($assets as $asset) {
        $userDeptId = $asset->user?->department_id;
        if (!$userDeptId) {
            // Assets with no assigned user → skip or put in an "Unassigned" group if you want
            continue;
        }

        // Find department by id
        $deptObj = null;
        foreach ($departments as $dept) {
            if ($dept->id == $userDeptId) {
                $deptObj = $dept;
                break;
            }
        }
        if (!$deptObj) continue;

        // Map status for the view
        $statusRaw = $asset->Lifecycle_Status ?? '';
        switch ($statusRaw) {
            case 'Acquired':        $status = 'acquired'; break;
            case 'Active':          $status = 'active'; break;
            case 'For Checking':    $status = 'for_checking'; break;
            case 'For Repair':      $status = 'for_repair'; break;
            case 'For Replacement': $status = 'for_replacement'; break;
            case 'Pullout':         $status = 'pulled_out'; break;
            case 'Disposal':
            case 'Disposed':        $status = 'disposed'; break;
            default:                $status = strtolower(str_replace(' ', '_', $statusRaw));
        }

        $deptObj->assets[] = (object) [
            'id'               => $asset->id,
            'name'             => $asset->Asset_name ?? '',
            'asset_code'       => $asset->Asset_code ?? '',
            'status'           => $status,
            'accountable'      => $asset->user?->full_name ?? 'Unassigned',   // ← now works
            'acquisition_date' => $asset->accusion_date ? (string) $asset->accusion_date : null,
            'url'              => $asset->url ?? null,
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
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('assets', 'audit_logs.asset_id', '=', 'assets.id')
            ->leftJoin('requests', 'audit_logs.request_id', '=', 'requests.id')
            ->select(
                'audit_logs.*',
                'users.id as user_id',
                'employee_numbers.Full_Name as user_name',
                'users.role as user_role',
                'assets.id as asset_id',
                'assets.Asset_name as asset_name',
                'assets.Asset_code as asset_code',
                'requests.id as request_id',
                'requests.request_type as request_type'
            )
            ->orderByDesc('audit_logs.created_at')
            ->paginate(5);   
    } catch (\Exception $e) {
        $logs = collect([]);
    }

    $totalLogs = DB::table('audit_logs')->count();
    $todayLogs = DB::table('audit_logs')->whereDate('created_at', now()->toDateString())->count();
    $weekLogs = DB::table('audit_logs')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
    $activeUsers = DB::table('users')->count();

    return view('admin.audit-log.audit_logs', compact('logs', 'totalLogs', 'todayLogs', 'weekLogs', 'activeUsers'));
});

Route::get('/admin/audit-logs/export', function () {
    $logs = DB::table('audit_logs')
        ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('assets', 'audit_logs.asset_id', '=', 'assets.id')
        ->leftJoin('requests', 'audit_logs.request_id', '=', 'requests.id')
        ->select(
            'audit_logs.id',
            'audit_logs.notes',
            'audit_logs.action_type',
            'audit_logs.action_description',
            'audit_logs.created_at',
            'employee_numbers.Full_Name as user_name',
            'users.role as user_role',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
            'requests.request_type',
            'audit_logs.request_id'
        )
        ->orderByDesc('audit_logs.created_at')
        ->get();

    $filename = 'audit-logs-' . now()->format('Y-m-d-His') . '.csv';

    $headers = [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ];

    $callback = function () use ($logs) {
        $file = fopen('php://output', 'w');

        // UTF-8 BOM so Excel shows special characters correctly
        fwrite($file, "\xEF\xBB\xBF");

        // Header row
        fputcsv($file, ['User', 'Role', 'Action', 'Asset', 'Asset Code', 'Request', 'Date']);

        foreach ($logs as $log) {
            fputcsv($file, [
                $log->user_name ?? '—',
                $log->user_role ?? '—',
                $log->notes ?? ($log->action_description ?? '—'),
                $log->asset_name ?? '—',
                $log->asset_code ?? '—',
                $log->request_type
                    ? ($log->request_type . ' (REQ-' . str_pad($log->request_id ?? 0, 5, '0', STR_PAD_LEFT) . ')')
                    : '—',
                $log->created_at
                    ? \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s')
                    : '—',
            ]);
        }

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
})->middleware('auth');

// Admin maintenance alerts API endpoint
Route::get('/admin/api/maintenance-alerts', function () {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Get all assets where next_maintenance_date is today or in the past
    $maintenanceAlerts = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->whereNotNull('assets.next_maintenance_date')
        ->whereDate('assets.next_maintenance_date', '<=', now()->toDateString())
        ->select(
            'assets.id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.next_maintenance_date',
            'assets.Lifecycle_Status',
            'employee_numbers.Full_Name as accountable'
        )
        ->orderBy('assets.next_maintenance_date', 'asc')
        ->get();

    $count = $maintenanceAlerts->count();

    return response()->json([
        'success' => true,
        'count' => $count,
        'alerts' => $maintenanceAlerts
    ]);
});

// Admin lifespan expiration alerts API endpoint
Route::get('/admin/api/lifespan-alerts', function () {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Get all assets where expiration_date <= today (expired assets needing evaluation)
    // Shows both assets in "For Checking" status and assets that expired but haven't been auto-transitioned yet
    $lifespanAlerts = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->whereNotNull('assets.expiration_date')
        ->whereDate('assets.expiration_date', '<=', now()->toDateString())
        ->select(
            'assets.id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.expiration_date',
            'assets.Lifecycle_Status',
            'assets.repair_counts',
            'employee_numbers.Full_Name as assigned_to'
        )
        ->orderBy('assets.expiration_date', 'asc')
        ->get();

    $count = $lifespanAlerts->count();

    return response()->json([
        'success' => true,
        'count' => $count,
        'alerts' => $lifespanAlerts
    ]);
});

// Admin API: Auto-transition assets that need evaluation (expired lifespan or maintenance overdue)
Route::post('/admin/api/assets/check-and-transition', function () {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    try {
        $today = \Carbon\Carbon::now()->toDateString();
        $transitionedCount = 0;

        DB::transaction(function () use ($today, &$transitionedCount) {
            // 1. Find and transition assets with expired lifespan
            $expiredAssets = DB::table('assets')
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->get();

            foreach ($expiredAssets as $asset) {
                DB::table('assets')->where('id', $asset->id)->update([
                    'Lifecycle_Status' => 'For Checking',
                    'updated_at' => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'asset_id' => $asset->id,
                    'action_type' => 'UPDATE',
                    'action_description' => 'Automatic status change: Asset lifespan expired',
                    'notes' => 'Asset lifespan expired on ' . $asset->expiration_date . '. Automatically transitioned to "For Checking" for evaluation.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $transitionedCount++;
            }

            // 2. Find and transition assets with overdue maintenance
            $maintenanceOverdueAssets = DB::table('assets')
                ->whereNotNull('next_maintenance_date')
                ->whereDate('next_maintenance_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->get();

            foreach ($maintenanceOverdueAssets as $asset) {
                DB::table('assets')->where('id', $asset->id)->update([
                    'Lifecycle_Status' => 'For Checking',
                    'updated_at' => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'asset_id' => $asset->id,
                    'action_type' => 'UPDATE',
                    'action_description' => 'Automatic status change: Maintenance overdue',
                    'notes' => 'Asset maintenance is overdue (due date: ' . $asset->next_maintenance_date . '). Automatically transitioned to "For Checking" for maintenance evaluation.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $transitionedCount++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Asset evaluation check completed. Transitioned {$transitionedCount} asset(s) to 'For Checking' status.",
            'transitioned_count' => $transitionedCount
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Asset transition error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to check and transition assets: ' . $e->getMessage()], 500);
    }
});

// Admin API: Mark maintenance as completed and recalculate next maintenance schedule
Route::post('/admin/api/assets/{id}/maintenance-complete', function ($id, \Illuminate\Http\Request $request) {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    if (!$asset->next_maintenance_date) {
        return response()->json(['success' => false, 'message' => 'Asset has no maintenance schedule'], 400);
    }

    $maintenanceNotes = $request->input('notes', 'Maintenance completed');
    $completionDate = $request->input('completion_date') ? \Carbon\Carbon::parse($request->input('completion_date')) : now();
    $currentStatus = $asset->Lifecycle_Status ?? 'Active';
    $newStatus = $currentStatus === 'Active' ? 'For Checking' : $currentStatus;

    try {
        DB::transaction(function () use ($id, $asset, $completionDate, $maintenanceNotes, $newStatus) {
            // Update last_maintenance_date to completion date
            $lastMaintenanceDate = $completionDate->toDateString();
            
            // Recalculate next_maintenance_date based on maintenance_interval
            $maintenanceInterval = (int)($asset->maintenance_interval ?? 0);
            $nextMaintenanceDate = null;
            
            if ($maintenanceInterval > 0) {
                $nextMaintenanceDate = $completionDate->addMonths($maintenanceInterval)->toDateString();
            }

            DB::table('assets')->where('id', $id)->update([
                'last_maintenance_date' => $lastMaintenanceDate,
                'next_maintenance_date' => $nextMaintenanceDate,
                'Lifecycle_Status' => $newStatus,
                'updated_at' => now(),
            ]);

            // Keep completed maintenance assets in review instead of automatically marking them Active.
            // They can be returned to Active explicitly after verification.

            // Log the maintenance completion in audit logs
            DB::table('audit_logs')->insert([
                'asset_id' => $id,
                'user_id' => Auth::id(),
                'action_type' => 'UPDATE',
                'notes' => 'Maintenance completed on ' . $lastMaintenanceDate,
                'action_description' => 'Asset preventive maintenance performed and completed. Lifecycle status set to ' . $newStatus . '. Next maintenance scheduled for ' . ($nextMaintenanceDate ?? 'No schedule'). '. ' . ($maintenanceNotes ? 'Notes: ' . $maintenanceNotes : ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Fetch updated asset for response
        $updatedAsset = DB::table('assets')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Maintenance completed successfully. Lifecycle status is now ' . ($updatedAsset->Lifecycle_Status ?? $newStatus) . '. Next maintenance scheduled for ' . ($updatedAsset->next_maintenance_date ?? 'Not scheduled'),
            'asset_id' => $id,
            'last_maintenance_date' => $updatedAsset->last_maintenance_date,
            'next_maintenance_date' => $updatedAsset->next_maintenance_date,
            'lifecycle_status' => $updatedAsset->Lifecycle_Status
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Maintenance completion error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to complete maintenance: ' . $e->getMessage()], 500);
    }
});

// Admin API: Update asset lifecycle status from the department asset details modal
Route::post('/admin/api/assets/{id}/status', function ($id, \Illuminate\Http\Request $request) {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'status' => 'required|string|in:Acquired,Active,For Checking,For Repair,For Replacement,Pullout,Disposal',
        'notes' => 'nullable|string|max:1000',
    ]);

    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    $currentStatus = $asset->Lifecycle_Status ?? '';
    $newStatus = $validated['status'];

    if ($currentStatus === $newStatus) {
        return response()->json(['success' => false, 'message' => 'Asset is already in that status'], 422);
    }

    try {
        DB::transaction(function () use ($id, $currentStatus, $newStatus, $validated) {
            DB::table('assets')->where('id', $id)->update([
                'Lifecycle_Status' => $newStatus,
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'asset_id' => $id,
                'user_id' => Auth::id(),
                'action_type' => 'UPDATE',
                'action_description' => 'Asset lifecycle status updated from ' . ($currentStatus ?: 'Unknown') . ' to ' . $newStatus,
                'notes' => ($validated['notes'] ?? 'Status updated from department asset card') . "\nPrevious Status: " . ($currentStatus ?: 'Unknown') . "\nNew Status: " . $newStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Asset status updated successfully',
            'asset_id' => $id,
            'lifecycle_status' => $newStatus,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Asset status update error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to update asset status: ' . $e->getMessage()], 500);
    }
});

// Admin API: Evaluate asset in "For Checking" status
Route::post('/admin/api/assets/{id}/evaluate', function ($id, \Illuminate\Http\Request $request) {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    if ($asset->Lifecycle_Status !== 'For Checking') {
        return response()->json(['success' => false, 'message' => 'Asset is not in "For Checking" status'], 422);
    }

    $action = $request->input('action');
    $evaluationNotes = $request->input('evaluation_notes', '');
    
    try {
        DB::transaction(function () use ($id, $asset, $action, $evaluationNotes, $request) {
            $adminEmail = Auth::user()?->email ?? 'Admin';
            
            switch($action) {
                case 'return_active':
                    // Return asset to Active status
                    $extensionMonths = (int)$request->input('extension_months', 0);
                    
                    $updateData = [
                        'Lifecycle_Status' => 'Active',
                        'updated_at' => now(),
                    ];
                    
                    // If extension is requested, recalculate expiration date
                    if ($extensionMonths > 0) {
                        $currentExpiration = \Carbon\Carbon::parse($asset->expiration_date);
                        $newExpiration = $currentExpiration->addMonths($extensionMonths);
                        $updateData['expiration_date'] = $newExpiration;
                    }
                    
                    DB::table('assets')->where('id', $id)->update($updateData);
                    
                    // Log evaluation
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'UPDATE',
                        'action_description' => 'Asset returned to Active status after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: RETURN TO ACTIVE' . ($extensionMonths > 0 ? " (Extended by $extensionMonths months)" : '') . "\nEvaluation Notes: " . ($evaluationNotes ?: 'Asset condition satisfactory, continues to meet operational requirements'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;
                    
                case 'send_repair':
                    // Send asset for repair
                    DB::table('assets')->where('id', $id)->update([
                        'Lifecycle_Status' => 'For Repair',
                        'updated_at' => now(),
                    ]);
                    
                    // Create repair record
                    DB::table('repairs')->insertOrIgnore([
                        'Asset_id' => $id,
                        'Request_id' => null,
                        'Repair_date' => now()->toDateString(),
                        'Repair_Description' => $evaluationNotes,
                        'Repair_status' => 'Pending',
                        'Repair_personnel' => null,
                        'Assigned_by' => $adminEmail,
                        'notes' => 'Repair initiated from lifespan evaluation',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Increment repair counter
                    DB::table('assets')->where('id', $id)->increment('repair_counts');
                    
                    // Log evaluation
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'REPAIR',
                        'action_description' => 'Asset sent for repair after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: SEND FOR REPAIR\nIssues Identified: ' . ($evaluationNotes ?: 'Maintenance required'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;
                    
                case 'recommend_replacement':
                    // Recommend replacement
                    DB::table('assets')->where('id', $id)->update([
                        'Lifecycle_Status' => 'For Replacement',
                        'updated_at' => now(),
                    ]);
                    
                    // Create replacement record
                    DB::table('replacements')->insertOrIgnore([
                        'Asset_id' => $id,
                        'Request_id' => null,
                        'Replacement_date' => now()->toDateString(),
                        'Replacement_reason' => $evaluationNotes,
                        'Replacement_status' => 'Pending',
                        'notes' => 'Replacement initiated from lifespan evaluation',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Log evaluation
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'REPLACEMENT',
                        'action_description' => 'Asset recommended for replacement after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: RECOMMEND REPLACEMENT\nReason: ' . ($evaluationNotes ?: 'Asset beyond economic repair'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;
                    
                case 'proceed_disposal':
                    // Proceed with disposal
                    DB::table('assets')->where('id', $id)->update([
                        'Lifecycle_Status' => 'Disposal',
                        'updated_at' => now(),
                    ]);
                    
                    // Create disposal record
                    DB::table('disposals')->insertOrIgnore([
                        'Asset_id' => $id,
                        'Approve_by' => $adminEmail,
                        'Description' => 'Asset disposal after lifespan evaluation',
                        'disposal_reason' => 'End of Lifespan',
                        'disposal_date' => now()->toDateString(),
                        'notes' => 'Asset disposed following comprehensive lifespan evaluation. ' . ($evaluationNotes ?: 'No longer serviceable'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Log evaluation
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'DISPOSAL',
                        'action_description' => 'Asset disposed after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: PROCEED WITH DISPOSAL\nReason: ' . ($evaluationNotes ?: 'End of life cycle'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;
                    
                default:
                    throw new \Exception('Invalid evaluation action: ' . $action);
            }
        });

        $actionMessages = [
            'return_active' => 'Asset returned to Active status',
            'send_repair' => 'Asset sent for repair evaluation',
            'recommend_replacement' => 'Asset recommended for replacement',
            'proceed_disposal' => 'Asset marked for disposal'
        ];

        return response()->json([
            'success' => true,
            'message' => $actionMessages[$action] ?? 'Asset evaluated successfully',
            'asset_id' => $id,
            'action' => $action
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Asset evaluation error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to evaluate asset: ' . $e->getMessage()], 500);
    }
});

// Admin asset detail view
Route::get('/admin/assets/{id}', function ($id) {
    $asset = Asset::with('user')->find($id);
    if (!$asset) {
        abort(404);
    }

    // Auto-transition asset to "For Checking" if conditions are met
    $today = \Carbon\Carbon::now()->toDateString();
    $shouldTransition = false;
    $transitionReason = '';

    // Check if asset is still Active
    if ($asset->Lifecycle_Status === 'Active') {
        // Check 1: Asset lifespan has expired
        if ($asset->expiration_date && \Carbon\Carbon::parse($asset->expiration_date)->toDateString() <= $today) {
            $shouldTransition = true;
            $transitionReason = 'Asset lifespan expired on ' . $asset->expiration_date . '. Automatically transitioned to "For Checking" for evaluation.';
        }
        // Check 2: Asset maintenance is overdue
        else if ($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->toDateString() <= $today) {
            $shouldTransition = true;
            $transitionReason = 'Asset maintenance is overdue (due date: ' . $asset->next_maintenance_date . '). Automatically transitioned to "For Checking" for maintenance evaluation.';
        }
    }

    // If auto-transition is needed, perform it
    if ($shouldTransition) {
        try {
            DB::transaction(function () use ($asset, $transitionReason) {
                // Update asset status
                DB::table('assets')->where('id', $asset->id)->update([
                    'Lifecycle_Status' => 'For Checking',
                    'updated_at' => now(),
                ]);

                // Log the automatic transition
                DB::table('audit_logs')->insert([
                    'asset_id' => $asset->id,
                    'action_type' => 'UPDATE',
                    'action_description' => 'Automatic status change: Lifespan expired or maintenance overdue',
                    'notes' => $transitionReason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // Refresh asset data to reflect changes
            $asset = Asset::with('user')->find($id);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to auto-transition asset', ['asset_id' => $id, 'error' => $e->getMessage()]);
        }
    }

    return view('admin.assets.show', compact('asset'));
})->where('id', '[0-9]+');

// Admin department assets page
Route::get('/admin/assets/department/{departmentId?}', function (Request $request, $departmentId = null) {
    // Resolve department (adjust to how you currently get it)
    $departmentId = $departmentId ?? $request->query('department_id');
    $department = DB::table('departments')->where('id', $departmentId)->first();
    $departmentName = $department->Name ?? 'Department';

    $status   = $request->query('status', 'all');      // all | active | acquired | for_repair | pulled_out | disposed
    $search   = trim((string) $request->query('search', ''));
    $category = $request->query('category', 'all');

    $query = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('users.department_id', $departmentId)   // or however you scope to the department
        ->select(
            'assets.id as db_id',
            'assets.Asset_code as id',
            'assets.Asset_name as name',
            'assets.Category as category',
            'assets.Category as category_code',
            'assets.Lifecycle_Status as status_raw',
            'assets.asset_location as location',
            'assets.accusion_date as date_acquired',
            'assets.serial_Number as serial_number',
            'assets.purchase_Price as purchase_price',
            'assets.warranty_months as warranty_months',   
            'assets.Condition as condition',               
            'assets.qr_code_path',
            'employee_numbers.Full_Name as accountable'
        );

        

    // Status filter
$status = request('status', 'all');
if ($status !== 'all') {
    $map = [
        'active'     => 'Active',
        'acquired'   => 'Acquired',
        'for_repair' => 'For Repair',
        'pulled_out' => 'Pullout',   // or whatever the real value is
        'disposed'   => 'Disposal',
    ];
    if (isset($map[$status])) {
        $query->where('assets.Lifecycle_Status', $map[$status]);
    }
}

    // Search
if ($search = request('search')) {
    $query->where(function ($q) use ($search) {
        $q->where('assets.Asset_name', 'like', "%{$search}%")
          ->orWhere('assets.Asset_code', 'like', "%{$search}%")
          ->orWhere('employee_numbers.Full_Name', 'like', "%{$search}%");
    });
}

$assets = $query
    ->orderBy('assets.Asset_name')
    ->paginate(10)
    ->withQueryString();

    // Category
    $category = request('category', 'all');
    if ($category && $category !== 'all') {
        $query->where('assets.Category', $category);
    }

    $paginator = $query->orderBy('assets.Asset_name')->paginate(10)->withQueryString();

    // Normalize for the Blade
    $assets = $paginator->getCollection()->map(function ($a) {
        $statusMap = [
            'Active'     => 'active',
            'Acquired'   => 'acquired',
            'For Repair' => 'for_repair',
            'Pullout'    => 'pulled_out',
            'Disposal'   => 'disposed',
        ];
        $a->status = $statusMap[$a->status_raw] ?? strtolower(str_replace(' ', '_', $a->status_raw ?? ''));
        $a->date_acquired = $a->date_acquired ? \Carbon\Carbon::parse($a->date_acquired)->format('M d, Y') : '—';
        return $a;
    });

    $paginator->setCollection($assets);

    // Category options for the dropdown (optional)
    $categoryOptions = DB::table('assets')
        ->whereIn('user_id', function ($q) use ($departmentId) {
            $q->select('id')->from('users')->where('department_id', $departmentId);
        })
        ->distinct()
        ->pluck('Category')
        ->filter()
        ->map(fn ($c) => ['value' => $c, 'label' => $c])
        ->values();

    return view('admin.assets.department_asset', [   // ← adjust view name
        'assets'          => $paginator,
        'departmentName'  => $departmentName,
        'categoryOptions' => $categoryOptions,
        'currentStatus'   => request('status', 'all'),
        'currentSearch'   => request('search', ''),
        'currentCategory' => request('category', 'all'),
    ]);
})->middleware('auth');

// Admin asset registry page
Route::get('/admin/assets/registry', function () {
    $bulkQrLabels = session('bulk_qr_labels', []);
    $bulkRegisteredCount = session('bulk_registered_count', 0);

    return view('admin.assets.asset_registry', compact('bulkQrLabels', 'bulkRegisteredCount'));
});

Route::get('/admin/assets/{id}/qr-sticker', function ($id) {
    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) abort(404);

    return view('admin.assets.qr-sticker', [
        'assetName'    => $asset->Asset_name,
        'assetCode'    => $asset->Asset_code,
        'acquiredDate' => $asset->accusion_date
            ? \Carbon\Carbon::parse($asset->accusion_date)->format('M d, Y')
            : '—',
        'qrUrl'        => $asset->qr_code_path
            ? Storage::url($asset->qr_code_path)
            : null,
    ]);
})->middleware('auth')->where('id', '[0-9]+');

Route::get('/admin/assets/{id}/qr-sticker', function ($id) {
    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) abort(404);

    return view('admin.assets.qr-sticker', [
        'assetName'    => $asset->Asset_name,
        'assetCode'    => $asset->Asset_code,
        'acquiredDate' => $asset->accusion_date
            ? \Carbon\Carbon::parse($asset->accusion_date)->format('M d, Y')
            : '—',
        'qrUrl'        => $asset->qr_code_path
            ? \Illuminate\Support\Facades\Storage::url($asset->qr_code_path)
            : null,
    ]);
})->middleware('auth')->where('id', '[0-9]+');



// Admin asset detail view - must come before wildcard routes
// Admin asset detail view - must come before wildcard routes
Route::get('/admin/assets/{id}', function ($id) {
    $asset = DB::table('assets')
        ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('assets.id', $id)
        ->select(
            'assets.*',
            'employee_numbers.Full_Name as full_name',
            DB::raw('MAX(asset_files.url) as image_url')
        )
        ->groupBy(
            'assets.id',
            'employee_numbers.Full_Name'
        )
        ->first();

    if (!$asset) {
        abort(404);
    }

    return view('admin.assets.show', compact('asset'));
})->where('id', '[0-9]+');

// Download Inventory - Export all assets as CSV with full reporting data - IMPORTANT: place before catch-all routes
Route::get('/admin/inventory-download', function () {
    try {
        // Fetch all assets with full reporting data
        $assets = DB::table('assets')
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->select(
                'assets.id',
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'departments.Name as department',
                'employee_numbers.Full_Name as accountable',
                'assets.accusion_date',
                'assets.asset_location',
                'assets.Lifecycle_Status',
                'assets.purchase_Price',
                'assets.warranty_months',
                'assets.lifespan_months',
                'assets.expiration_date',
                'assets.next_maintenance_date',
                'assets.repair_counts',
                'assets.Condition'
            )
            ->orderBy('departments.Name')
            ->orderBy('assets.Asset_code')
            ->get();

        // Calculate status distribution for summary
        $statusCounts = [
            'Acquired' => 0,
            'Active' => 0,
            'For Checking' => 0,
            'For Repair' => 0,
            'For Replacement' => 0,
            'Pullout' => 0,
            'Disposal' => 0,
        ];
        
        $deptStats = [];
        foreach ($assets as $asset) {
            if (isset($statusCounts[$asset->Lifecycle_Status])) {
                $statusCounts[$asset->Lifecycle_Status]++;
            }
            
            $dept = $asset->department ?? 'Unassigned';
            if (!isset($deptStats[$dept])) {
                $deptStats[$dept] = 0;
            }
            $deptStats[$dept]++;
        }

        // Generate CSV in memory
        $filename = 'inventory_' . date('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://memory', 'r+');
        
        // Export timestamp and summary
        fputcsv($handle, ['INVENTORY EXPORT REPORT']);
        fputcsv($handle, ['Generated on', date('Y-m-d H:i:s')]);
        fputcsv($handle, []);
        
        // Status summary section
        fputcsv($handle, ['ASSET LIFECYCLE STATUS SUMMARY']);
        fputcsv($handle, ['Status', 'Count', 'Percentage']);
        $totalAssets = count($assets);
        foreach ($statusCounts as $status => $count) {
            $percentage = $totalAssets > 0 ? round(($count / $totalAssets) * 100, 1) . '%' : '0%';
            fputcsv($handle, [$status, $count, $percentage]);
        }
        fputcsv($handle, []);
        
        // Department summary section
        fputcsv($handle, ['ASSETS BY DEPARTMENT']);
        fputcsv($handle, ['Department', 'Asset Count']);
        foreach ($deptStats as $dept => $count) {
            fputcsv($handle, [$dept, $count]);
        }
        fputcsv($handle, []);
        
        // Detailed inventory data
        fputcsv($handle, ['DETAILED ASSET INVENTORY']);
        fputcsv($handle, [
            'ASSET CODE',
            'ASSET NAME',
            'CATEGORY',
            'DEPARTMENT',
            'ACCOUNTABLE PERSON',
            'DATE ACQUIRED',
            'LIFECYCLE STATUS',
            'CONDITION',
            'LOCATION',
            'VALUE (PHP)',
            'WARRANTY (MONTHS)',
            'LIFESPAN (MONTHS)',
            'EXPIRATION DATE',
            'NEXT MAINTENANCE DATE',
            'REPAIR HISTORY'
        ]);
        
        // Data rows
        foreach ($assets as $asset) {
            fputcsv($handle, [
                $asset->Asset_code ?? 'N/A',
                $asset->Asset_name ?? 'N/A',
                $asset->Category ?? 'N/A',
                $asset->department ?? 'Unassigned',
                $asset->accountable ?? 'Unassigned',
                $asset->accusion_date ?? 'N/A',
                $asset->Lifecycle_Status ?? 'N/A',
                $asset->Condition ?? 'N/A',
                $asset->asset_location ?? 'N/A',
                number_format($asset->purchase_Price ?? 0, 2),
                $asset->warranty_months ?? 'N/A',
                $asset->lifespan_months ?? 'N/A',
                $asset->expiration_date ?? 'N/A',
                $asset->next_maintenance_date ?? 'N/A',
                $asset->repair_counts ?? 0
            ]);
        }
        
        fputcsv($handle, []);
        fputcsv($handle, ['TOTAL ASSETS', $totalAssets]);
        fputcsv($handle, ['TOTAL VALUE (PHP)', '₱' . number_format($assets->sum('purchase_Price') ?? 0, 2)]);

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
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->select('requests.id', 'requests.status', 'requests.updated_at', 'assets.Asset_name', 'employee_numbers.Full_Name as submitted_by')
        ->where('requests.request_type', 'Repair')
        ->where('users.department_id', $user->department_id)
        ->orderByDesc('requests.updated_at')
        ->limit(50)
        ->get();

    return response()->json(['repairs' => $repairs]);
})->middleware('auth');

// Admin disposal page
Route::get('/admin/disposal', function () {
    $disposalRecords = DB::table('disposals')
        ->leftJoin('assets', 'disposals.Asset_id', '=', 'assets.id')
        ->select(
            'disposals.*',
            DB::raw('COALESCE(assets."Asset_name", disposals."Description") as asset_name'),
            DB::raw('COALESCE(assets."Asset_code", \'N/A\') as asset_code'),
            DB::raw('assets."purchase_Price" as original_value'),
            DB::raw('CASE WHEN assets.id IS NULL THEN 0 ELSE 1 END as asset_still_exists')
        )
        ->orderByDesc('disposals.disposal_date')
        ->get()
        ->map(function ($r) {
            $r->id                 = $r->Disposal_ID;
            $r->asset_still_exists = (bool) $r->asset_still_exists;
            $r->disposed_by        = $r->Approve_by ?? '-';
            $r->reason             = $r->disposal_reason ?? $r->Description ?? $r->notes ?? '-';
            return $r;
        });

    $totalDisposed = $disposalRecords->count();

    $availableAssets = DB::table('assets')
        ->whereNotIn('Lifecycle_Status', ['Disposal', 'Disposed'])
        ->select(
            'id',
            DB::raw('"Asset_name" as name'),
            DB::raw('"Asset_code" as asset_code'),
            'Lifecycle_Status'
        )
        ->orderBy('Asset_name')
        ->get();

    return view('admin.disposal.disposal', [
        'disposalRecords' => $disposalRecords,
        'totalDisposed'   => $totalDisposed,
        'availableAssets' => $availableAssets,
    ]);
})->middleware('auth');

Route::post('/admin/disposal/{id}/permanent-delete', function (Request $request, $id) {
    $disposal = DB::table('disposals')->where('Disposal_ID', $id)->first();

    if (!$disposal) {
        return response()->json(['success' => false, 'message' => 'Disposal record not found'], 404);
    }

    $assetId = $disposal->Asset_id ?? null;

    if (!$assetId) {
        return response()->json([
            'success' => true,
            'message' => 'Asset was already permanently removed. Disposal record kept.',
            'already_deleted' => true,
        ]);
    }

    $asset = DB::table('assets')->where('id', $assetId)->first();

    if (!$asset) {
        try {
            DB::statement('ALTER TABLE disposals ALTER COLUMN "Asset_id" DROP NOT NULL');
            DB::table('disposals')->where('Disposal_ID', $id)->update([
                'Asset_id'   => null,
                'Request_id' => null,
                'notes'      => trim(($disposal->notes ? $disposal->notes . ' | ' : '') . 'Asset already gone; link cleared on ' . now()->toDateString()),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'success' => true,
            'message' => 'Asset was already permanently removed. Disposal record kept.',
            'already_deleted' => true,
        ]);
    }

    try {
        DB::beginTransaction();

        // 1. Allow NULL on Asset_id (and Request_id if needed)
        DB::statement('ALTER TABLE disposals ALTER COLUMN "Asset_id" DROP NOT NULL');
        try {
            DB::statement('ALTER TABLE disposals ALTER COLUMN "Request_id" DROP NOT NULL');
        } catch (\Throwable $e) {
            // already nullable – ignore
        }

        // 2. Unlink disposal FIRST (breaks FKs to asset + request)
        DB::table('disposals')
            ->where('Disposal_ID', $id)
            ->update([
                'Asset_id'   => null,
                'Request_id' => null,   // ← important: clear before deleting requests
                'notes'      => trim(($disposal->notes ? $disposal->notes . ' | ' : '') .
                    'Asset permanently deleted on ' . now()->toDateString() .
                    ' (was: ' . ($asset->Asset_name ?? '') . ' / ' . ($asset->Asset_code ?? '') . ')'),
                'updated_at' => now(),
            ]);

        // 3. Now safe to clean related data
        DB::table('asset_files')->where('Asset_id', $assetId)->delete();
        DB::table('pullout_items')->where('asset_id', $assetId)->delete();
        DB::table('requests')->where('asset_id', $assetId)->delete();
        try {
            DB::table('repairs')->where('Assets_id', $assetId)->delete();
        } catch (\Throwable $e) {
            // table may not exist
        }

        try {
            DB::table('audit_logs')->where('asset_id', $assetId)->update(['asset_id' => null]);
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::table('pullouts')->where('asset_id', $assetId)->update(['asset_id' => null]);
        } catch (\Throwable $e) {
            // ignore
        }

        // 4. Delete the asset
        DB::table('assets')->where('id', $assetId)->delete();

        // 5. Audit log
try {
    DB::table('audit_logs')->insert([
        'user_id'            => Auth::id(),
        'asset_id'           => null,
        'action_type'        => 'DISPOSAL',   // ← change DELETE to DISPOSAL
        'notes'              => 'Permanently deleted asset from disposal #' . $id,
        'action_description' => 'Asset permanently removed after disposal. Disposal record retained.',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
} catch (\Throwable $e) {
    \Log::warning('Audit log skipped on permanent delete: ' . $e->getMessage());
}
        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Asset permanently deleted. Disposal record kept for history.',
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        \Log::error('Permanent disposal delete failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to permanently delete asset',
            'error'   => $e->getMessage(),
        ], 500);
    }
})->middleware('auth');


// Admin requests page
// Admin requests page – supports bulk requests via request_items
Route::get('/admin/requests', function () {
    $requests = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('users as assignee', 'requests.assign_to_user_id', '=', 'assignee.id')
        ->leftJoin('employee_numbers as assignee_emp', 'assignee.employee_numbers_id', '=', 'assignee_emp.id')
        ->select([
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.Note',
            'requests.created_at',
            'requests.url',
            'requests.asset_id',
            'employee_numbers.Full_Name as submitted_by',
            'users.email',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
            'assignee_emp.Full_Name as assigned_to',
        ])
        ->orderByDesc('requests.created_at')
        ->get();

    // Load all related assets from request_items in one query
    $requestIds = $requests->pluck('id')->all();
    $itemsByRequest = collect();

    if (!empty($requestIds)) {
        $itemsByRequest = DB::table('request_items')
            ->join('assets', 'request_items.asset_id', '=', 'assets.id')
            ->whereIn('request_items.request_id', $requestIds)
            ->select(
                'request_items.request_id',
                'assets.id as asset_id',
                'assets.Asset_name',
                'assets.Asset_code',
                'assets.Category',
                'assets.Lifecycle_Status'
            )
            ->get()
            ->groupBy('request_id');
    }

    $requests = $requests->map(function ($request) use ($itemsByRequest) {
        $related = $itemsByRequest->get($request->id, collect());

        // Build clean list of assets
        $assetList = $related->map(function ($a) {
            return [
                'id'               => $a->asset_id,
                'name'             => $a->Asset_name,
                'code'             => $a->Asset_code,
                'category'         => $a->Category,
                'lifecycle_status' => $a->Lifecycle_Status,
            ];
        })->values()->all();

        // Fallback for old single-asset requests that still have asset_id set
        if (empty($assetList) && !empty($request->asset_id)) {
            $assetList = [[
                'id'               => $request->asset_id,
                'name'             => $request->asset_name ?: 'Unknown Asset',
                'code'             => $request->asset_code ?: '',
                'category'         => null,
                'lifecycle_status' => null,
            ]];
        }

        $count = count($assetList);

        if ($count === 0) {
            $displayName = 'Unknown Asset';
        } elseif ($count === 1) {
            $displayName = $assetList[0]['name'];
        } else {
            $displayName = $assetList[0]['name'] . ' +' . ($count - 1) . ' more';
        }

        return (object) [
            'id'           => $request->id,
            'asset_name'   => $displayName,   // shown in the table
            'assets'       => $assetList,      // full list for the details panel
            'asset_count'  => $count,
            'type'         => strtolower((string) $request->request_type),
            'submitted_by' => $request->submitted_by ?: 'Unknown User',
            'email'        => $request->email ?: '',
            'created_at'   => $request->created_at
                                ? \Illuminate\Support\Carbon::parse($request->created_at)
                                : now(),
            'status'       => strtolower((string) $request->status),
            'description'  => $request->Note ?: '',
            'assigned_to'  => $request->assigned_to ?? null,
            'image'        => $request->url ?? null,
        ];
    });

    $totalRequests    = $requests->count();
    $pendingRequests  = $requests->where('status', 'pending')->count();
    $approvedRequests = $requests->where('status', 'approved')->count();
    $rejectedRequests = $requests->where('status', 'rejected')->count();

    return view('admin.request.request', compact(
        'requests',
        'totalRequests',
        'pendingRequests',
        'approvedRequests',
        'rejectedRequests'
    ));
});


Route::post('/admin/repairs/create', function (Request $request) {
    $assetId = $request->input('asset_id');
    $issue = trim((string) $request->input('issue_description', ''));
    $priority = $request->input('priority', 'medium');
    $requestedBy = trim((string) $request->input('requested_by', '')) ?: (Auth::user()?->email ?? 'Admin');

    if (!$assetId || $issue === '') {
        return response()->json(['success' => false, 'message' => 'Asset and issue description are required'], 422);
    }

    $asset = DB::table('assets')->where('id', $assetId)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    try {
        DB::beginTransaction();

        $requestId = DB::table('requests')->insertGetId([
            'user_id'      => Auth::id(),
            'asset_id'     => $assetId,
            'request_type' => 'Repair',
            'status'       => 'Approved',
            'Note'         => $issue,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $repairId = DB::table('repairs')->insertGetId([
            'Assets_id'          => $assetId,
            'Request_id'         => $requestId,
            'Repair_Description' => $issue,
            'Repair_Date'        => now(),
            'Approve_by'         => $requestedBy,
            'Repair_Cost'        => 0,
            'status'             => 'Pending',
            'notes'              => 'Priority: ' . $priority,
            'created_at'         => now(),
            'updated_at'         => now(),
        ], 'Repair_id');

        DB::table('assets')->where('id', $assetId)->update([
            'Lifecycle_Status' => 'For Repair',
            'updated_at'       => now(),
        ]);

        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'asset_id'           => $assetId,
            'request_id'         => $requestId,
            'action_type'        => 'REPAIR',
            'notes'              => 'Repair request created for asset',
            'action_description' => 'Repair #' . $repairId . ' created: ' . $issue,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Repair request created successfully',
            'repair'  => [
                'id'             => $repairId,
                'asset_id'       => $assetId,
                'request_id'     => $requestId,
                'asset_name'     => $asset->Asset_name ?? 'Asset',
                'asset_code'     => $asset->Asset_code ?? '',
                'issue'          => $issue,
                'status'         => 'pending',
                'priority'       => $priority,
                'requested_by'   => $requestedBy,
                'department'     => '—',
                'date_requested' => now()->toDateString(),
            ],
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        \Log::error('Repair create failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to create repair: ' . $e->getMessage(),
        ], 500);
    }
})->middleware('auth');

Route::get('/admin/repair', function () {
    // 1. Load repairs + asset info
    $repairsRaw = DB::table('repairs')
        ->leftJoin('assets', 'repairs.Assets_id', '=', 'assets.id')
        ->leftJoin('requests', 'repairs.Request_id', '=', 'requests.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
        ->select(
            'repairs.Repair_id as id',
            'repairs.Assets_id as asset_id',
            'repairs.Request_id as request_id',
            'repairs.Repair_Description as issue',
            'repairs.status',
            'repairs.Repair_Date as date_requested',
            'repairs.Approve_by as requested_by',
            'repairs.Repair_Cost as estimated_cost',
            'repairs.notes',
            'repairs.created_at',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
            'assets.serial_Number as serial_number',
            'assets.Condition as condition',
            'assets.purchase_Price as purchase_price',
            'assets.warranty_months',
            'assets.asset_location',
            'employee_numbers.Full_Name as requester_name',
            'departments.Name as department'
        )
        ->orderByDesc('repairs.created_at')
        ->get();

    // Map status to what the JS expects (pending, in_progress, etc.)
    $statusMap = [
        'Pending'     => 'pending',
        'In Progress' => 'in_progress',
        'Completed'   => 'completed',
        'Cancelled'   => 'cancelled',
    ];

    $repairs = $repairsRaw->map(function ($r) use ($statusMap) {
        return [
            'id'              => $r->id,
            'asset_id'        => $r->asset_id,
            'request_id'      => $r->request_id,
            'asset_name'      => $r->asset_name ?? 'Unknown Asset',
            'asset_code'      => $r->asset_code ?? 'N/A',
            'issue'           => $r->issue ?? '',
            'status'          => $statusMap[$r->status] ?? strtolower(str_replace(' ', '_', $r->status ?? 'pending')),
            'priority'        => 'medium', // default (your table has no priority column)
            'requested_by'    => $r->requester_name ?? $r->requested_by ?? 'Admin',
            'department'      => $r->department ?? '—',
            'date_requested'  => $r->date_requested ? date('Y-m-d', strtotime($r->date_requested)) : date('Y-m-d'),
            'estimated_cost'  => $r->estimated_cost ? (float) $r->estimated_cost : null,
            'technician'      => null,
            'completion_date' => null,
            'notes'           => $r->notes,
            'serial_number'   => $r->serial_number,
            'condition'       => $r->condition,
            'purchase_price'  => $r->purchase_price ? (float) $r->purchase_price : null,
            'warranty_months' => $r->warranty_months,
            'asset_location'  => $r->asset_location,
            'supplier'        => null,
            'model'           => null,
        ];
    });

    // 2. Active assets for the "Select Asset" dropdown
$availableAssets = DB::table('assets')
        ->where('Lifecycle_Status', 'Active')
        ->orderBy('Asset_name')
        ->get(['id', 'Asset_name', 'Asset_code', 'Lifecycle_Status']);

    return view('admin.repair.repair', compact('repairs', 'availableAssets'));
})->middleware('auth');


// Admin update repair status endpoint
Route::post('/admin/repairs/{id}/status', function ($id, \Illuminate\Http\Request $request) {
    $newStatus = $request->input('status');
    $valid = ['pending', 'in_progress', 'completed', 'cancelled'];
    
    if (!in_array($newStatus, $valid)) {
        return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
    }
    
    try {
        // Primary key is Repair_id
        $repair = DB::table('repairs')->where('Repair_id', $id)->first();

        if (!$repair) {
            return response()->json(['success' => false, 'message' => 'Repair not found'], 404);
        }

        // Map frontend status → database enum values
        $statusMap = [
            'pending'     => 'Pending',
            'in_progress' => 'In Progress',
            'completed'   => 'Completed',
            'cancelled'   => 'Cancelled',
        ];
        $formattedStatus = $statusMap[$newStatus];

        // Update the repair
        DB::table('repairs')
            ->where('Repair_id', $id)
            ->update([
                'status'     => $formattedStatus,
                'updated_at' => now(),
            ]);

        // Audit log
        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'action_type'        => 'REPAIR',
            'notes'              => 'Repair status changed to ' . $formattedStatus,
            'action_description' => 'Repair #' . $id . ' status updated to ' . $formattedStatus,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // When completed → set the asset back to Active
        if ($newStatus === 'completed' && !empty($repair->Assets_id)) {
            DB::table('assets')
                ->where('id', $repair->Assets_id)
                ->update([
                    'Lifecycle_Status' => 'Active',
                    'updated_at'       => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Repair status updated successfully'
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Repair status update error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update repair status: ' . $e->getMessage()
        ], 500);
    }
});

// Admin API: Create replacement from repair
Route::post('/admin/replacements/create', function (\Illuminate\Http\Request $request) {
    $requestId = $request->input('request_id');
    $assetId = $request->input('asset_id');
    $reason = $request->input('reason', 'Created from repair request');
    $replacementReason = $request->input('replacement_reason', 'Beyond Repair');
    
    if (!$assetId || !$requestId) {
        return response()->json(['success' => false, 'message' => 'Asset ID and Request ID required'], 400);
    }
    
    try {
        $asset = DB::table('assets')->where('id', $assetId)->first();
        if (!$asset) {
            return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
        }
        
        // Create replacement record
        // Note: new_assets_id is set to the same as old_assets_id initially and should be updated when actual replacement asset is assigned
        $replacementId = DB::table('replacements')->insertGetId([
            'Request_id' => $requestId,
            'old_assets_id' => $assetId,
            'new_assets_id' => $assetId,  // Placeholder - will be updated when replacement asset is assigned
            'Approve_by' => Auth::user()?->email ?? 'Admin',
            'reason' => $reason,
            'replacement_reason' => $replacementReason,
            'notes' => 'Created from repair request. Awaiting replacement asset assignment.',
            'Replacement_Date' => now(),
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'Replacement_id');
        
        // Update asset lifecycle to "For Replacement" (from "For Repair")
        DB::table('assets')->where('id', $assetId)->update([
            'Lifecycle_Status' => 'For Replacement',
            'updated_at' => now(),
        ]);
        
        return response()->json(['success' => true, 'message' => 'Replacement request created successfully', 'id' => $replacementId]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Replacement creation error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to create replacement: ' . $e->getMessage()], 500);
    }
});

// Admin API: Create disposal from repair
Route::post('/admin/disposals/create', function (\Illuminate\Http\Request $request) {
    $requestId = $request->input('request_id');
    $assetId = $request->input('asset_id');
    $reason = $request->input('reason', 'Created from repair request');
    $disposalReason = $request->input('disposal_reason', 'Beyond Repair');
    
    if (!$assetId || !$requestId) {
        return response()->json(['success' => false, 'message' => 'Asset ID and Request ID required'], 400);
    }
    
    try {
        $asset = DB::table('assets')->where('id', $assetId)->first();
        if (!$asset) {
            return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
        }
        
        // Create disposal record
        $disposalId = DB::table('disposals')->insertGetId([
            'Request_id' => $requestId,
            'Asset_id' => $assetId,
            'Approve_by' => Auth::user()?->email ?? 'Admin',
            'Description' => $reason,
            'disposal_reason' => $disposalReason,
            'disposal_date' => now()->toDateString(),
            'notes' => 'Created from repair request',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'Disposal_ID');
        
        // Log the disposal creation
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'asset_id' => $assetId,
            'action_type' => 'DISPOSAL',
            'notes' => 'Disposal request created from repair #' . $assetId,
            'action_description' => 'Asset #' . $assetId . ' marked for disposal with reason: ' . $disposalReason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Update asset lifecycle to Disposal
        DB::table('assets')->where('id', $assetId)->update([
            'Lifecycle_Status' => 'Disposal',
            'updated_at' => now(),
        ]);
        
        return response()->json(['success' => true, 'message' => 'Disposal request created successfully', 'id' => $disposalId]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Disposal creation error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to create disposal: ' . $e->getMessage()], 500);
    }
});

// Admin replacement page
Route::get('/admin/replacement', function () {
    $replacements = DB::table('replacements')
        ->leftJoin('assets as old_assets', 'replacements.old_assets_id', '=', 'old_assets.id')
        ->leftJoin('assets as new_assets', 'replacements.new_assets_id', '=', 'new_assets.id')
        ->leftJoin('requests', 'replacements.Request_id', '=', 'requests.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
        ->select(
            'replacements.Replacement_id as id',
            'replacements.status',
            'replacements.reason',
            'replacements.notes',
            'replacements.Approve_by',
            'replacements.Replacement_Date',
            'replacements.created_at',
            'replacements.old_assets_id as old_asset_id',
            'replacements.new_assets_id as new_asset_id',
            'old_assets.Asset_code as old_asset_code',
            'old_assets.Asset_name as old_asset_name',
            'old_assets.Category as old_asset_category',
            'old_assets.Lifecycle_Status as old_asset_lifecycle_status',
            'new_assets.Asset_code as new_asset_code',
            'new_assets.Asset_name as new_asset_name',
            'new_assets.Category as new_asset_category',
            'new_assets.Lifecycle_Status as new_asset_lifecycle_status',
            'new_assets.qr_code_path as new_asset_qr',
            'employee_numbers.Full_Name as requested_by',
            'departments.Name as department'
        )
        ->orderByDesc('replacements.created_at')
        ->get();

    $totalReplacements     = $replacements->count();
    $pendingReplacements   = $replacements->where('status', 'Pending')->count();
    $approvedReplacements  = $replacements->where('status', 'Approved')->count();
    $receivedReplacements  = $replacements->where('status', 'Received')->count();

    return view('admin.replacement.replacement', compact(  // ← change to your real view name
        'replacements',
        'totalReplacements',
        'pendingReplacements',
        'approvedReplacements',
        'receivedReplacements'
    ));
})->middleware('auth');

// Admin API: Update replacement status and handle completion
Route::post('/admin/replacements/{id}/status', function ($id, \Illuminate\Http\Request $request) {
    $newStatus = $request->input('status');
    $valid = ['Pending', 'Approved', 'Ordered', 'Received', 'Complete', 'Cancelled'];
    
    if (!in_array($newStatus, $valid)) {
        return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
    }
    
    try {
        $replacement = DB::table('replacements')->where('Replacement_id', $id)->first();
        if (!$replacement) {
            return response()->json(['success' => false, 'message' => 'Replacement not found'], 404);
        }
        
        DB::transaction(function () use ($id, $replacement, $newStatus) {
            // Update replacement status
            DB::table('replacements')->where('Replacement_id', $id)->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);
            
            // Log the replacement status change
            DB::table('audit_logs')->insert([
                'user_id' => Auth::id(),
                'action_type' => 'REPLACEMENT',
                'notes' => 'Replacement status changed to ' . $newStatus,
                'action_description' => 'Replacement request #' . $id . ' status updated to ' . $newStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // If marking as Complete, transition old asset to Disposed and create disposal record
            if ($newStatus === 'Complete' && $replacement->old_assets_id) {
                $oldAsset = DB::table('assets')->where('id', $replacement->old_assets_id)->first();
                
                if ($oldAsset) {
                    // Transition old asset to Disposed
                    DB::table('assets')->where('id', $replacement->old_assets_id)->update([
                        'Lifecycle_Status' => 'Disposed',
                        'updated_at' => now(),
                    ]);
                    
                    // Create disposal record for the old asset
                    DB::table('disposals')->insertOrIgnore([
                        'Request_id' => $replacement->Request_id,
                        'Asset_id' => $replacement->old_assets_id,
                        'Approve_by' => Auth::user()?->email ?? 'Admin',
                        'Description' => 'Disposed as part of replacement (replaced by asset #' . $replacement->new_assets_id . ')',
                        'disposal_reason' => 'Replace',
                        'disposal_date' => now()->toDateString(),
                        'notes' => 'Automatically created when replacement was marked as complete',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        
        return response()->json(['success' => true, 'message' => 'Replacement status updated successfully']);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Replacement status update error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to update replacement status: ' . $e->getMessage()], 500);
    }
});

// Create & Link new asset for a replacement (accept POST or PATCH to be permissive)
Route::match(['post', 'patch'], '/admin/replacements/{id}/link', function (Request $request, $id) {
    $user = Auth::user();
    if (!$user) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        return redirect('/login');
    }

    $replacement = DB::table('replacements')->where('Replacement_id', $id)->first();
    if (!$replacement) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Replacement not found'], 404);
        }
        return abort(404);
    }

    $old = DB::table('assets')->where('id', $replacement->old_assets_id)->first();
    if (!$old) {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Old asset not found'], 404);
        }
        return back()->with('error', 'Old asset not found.');
    }

            $validated = $request->validate([
                'Asset_code'           => 'required|string|max:50|unique:assets,Asset_code',
                'Asset_name'           => 'required|string|max:150',
                'Category'             => 'required|string|max:150',
                'Condition'            => 'required|string|max:50',
                'accusion_date'        => 'required|date',
                'asset_location'       => 'nullable|string|max:255',
                'serial_Number'        => 'nullable|string|max:150',
                'purchase_Price'       => 'nullable|numeric',
                'supplier'             => 'nullable|string|max:150',
                'model'                => 'nullable|string|max:150',
                'manufacture'          => 'nullable|string|max:150',
                'warranty_months'      => 'nullable|integer|min:0',
                'lifespan_months'      => 'nullable|integer|min:1',
                'maintenance_interval' => 'nullable|integer|min:1',
                'asset_photo'          => 'nullable|image|max:10240',
            ]);

    try {
        $result = DB::transaction(function () use ($validated, $replacement, $old, $id, $user, $request) {
            // Expiration / next maintenance
            $expiration = null;
            if (!empty($validated['lifespan_months']) && !empty($validated['accusion_date'])) {
                $expiration = \Carbon\Carbon::parse($validated['accusion_date'])
                    ->addMonths((int) $validated['lifespan_months'])
                    ->toDateString();
            }
            $nextMaint = null;
            if (!empty($validated['maintenance_interval']) && !empty($validated['accusion_date'])) {
                $nextMaint = \Carbon\Carbon::parse($validated['accusion_date'])
                    ->addMonths((int) $validated['maintenance_interval'])
                    ->toDateString();
            }

            // 1. Create NEW asset (Active, same user as old)
            $newAssetId = DB::table('assets')->insertGetId([
                'user_id'               => $old->user_id,
                'Asset_code'            => $validated['Asset_code'],
                'Asset_name'            => $validated['Asset_name'],
                'Category'              => $validated['Category'] ?? $old->Category,
                'Condition'             => 'New',
                'Lifecycle_Status'      => 'Acquired',
                'accusion_date'         => $validated['accusion_date'] ?? now()->toDateString(),
                'purchase_Price'        => $validated['purchase_Price'] ?? null,
                'warranty_months'       => $validated['warranty_months'] ?? null,
                'supplier'              => $validated['supplier'] ?? ($old->supplier ?? null),
                'model'                 => $validated['model'] ?? ($old->model ?? null),
                'manufacture'           => $validated['manufacture'] ?? ($old->manufacture ?? null),
                'serial_Number'         => $validated['serial_Number'] ?? null,
                'asset_location'        => $validated['asset_location'] ?? $old->asset_location,
                'qr_code_path'          => null,
                'lifespan_months'       => $validated['lifespan_months'] ?? null,
                'expiration_date'       => $expiration,
                'maintenance_interval'  => $validated['maintenance_interval'] ?? null,
                'next_maintenance_date' => $nextMaint,
                'repair_counts'         => 0,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // Save photo to asset_files (same as registry)
            if ($request->hasFile('asset_photo')) {
                $file = $request->file('asset_photo');
                $filePath = $file->store('assets', 'public');
                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $mime = $file->getClientMimeType();
                $url = Storage::url($filePath);

                DB::table('asset_files')->insert([
                    'Asset_id'    => $newAssetId,
                    'file_name'   => $fileName,
                    'file_path'   => $filePath,
                    'file_size'   => $fileSize,
                    'mime_type'   => $mime,
                    'url'         => $url,
                    'uploaded_at' => now()->toDateTimeString(),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // 2. QR code
            $qrUrl = null;
            try {
                $qrSource = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($validated['Asset_code']);
                $contents = @file_get_contents($qrSource);
                if ($contents) {
                    $qrPath = 'assets/qr/' . $validated['Asset_code'] . '-' . time() . '.png';
                    Storage::disk('public')->put($qrPath, $contents);
                    DB::table('assets')->where('id', $newAssetId)->update(['qr_code_path' => $qrPath]);
                    $qrUrl = Storage::url($qrPath);
                }
            } catch (\Throwable $e) {
                Log::warning('QR generation failed on replacement link', ['error' => $e->getMessage()]);
            }

            // 3. Link on replacement
            DB::table('replacements')->where('Replacement_id', $id)->update([
                'new_assets_id' => $newAssetId,
                'updated_at'    => now(),
            ]);

            // 4. Move OLD asset to Pullout (reason: Replacement) — do NOT delete
            DB::table('assets')->where('id', $old->id)->update([
                'Lifecycle_Status' => 'Pullout',
                'updated_at'       => now(),
            ]);

            $pulloutId = DB::table('pullouts')->insertGetId([
                'request_id'   => $replacement->request_id ?? null,
                'asset_id'     => $old->id,
                'Approve_by'   => $user->email ?? 'Admin',
                'Description'  => 'Replacement',
                'notes'        => 'Old asset pulled out due to replacement. New asset: ' . $validated['Asset_code'],
                'pullout_date' => now()->toDateString(),
                'destination'  => 'Storage Room',
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('pullout_items')->insert([
                'pullout_id' => $pulloutId,
                'asset_id'   => $old->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Audit
            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'asset_id'           => $newAssetId,
                'action_type'        => 'CREATE',
                'notes'              => 'New asset created via replacement #' . $id,
                'action_description' => 'Linked to old asset ' . ($old->Asset_code ?? $old->id) . '; old asset moved to Pullout',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            return [
                'newAssetId' => $newAssetId,
                'qrUrl'      => $qrUrl,
            ];
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'        => true,
                'message'        => 'New asset created and linked. Old asset moved to Pullout.',
                'replacement_id' => (int) $id,
                'asset'          => [
                    'id'       => $result['newAssetId'],
                    'code'     => $validated['Asset_code'],
                    'name'     => $validated['Asset_name'],
                    'qr_url'   => $result['qrUrl'] ?? '',
                    'location' => $validated['asset_location'] ?? null,
                    'category' => $validated['Category'] ?? null,
                    'acquired' => $validated['accusion_date'] ?? null,
                ],
            ]);
        }

        return redirect(url()->previous() ?: '/admin/replacement')
            ->with('success', 'New asset created and linked. Old asset moved to Pullout.');
    } catch (\Exception $e) {
        Log::error('Failed to link new asset', ['error' => $e->getMessage()]);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset: ' . $e->getMessage(),
            ], 400);
        }
        return back()->with('error', 'Failed to create asset: ' . $e->getMessage());
    }
})->middleware('auth');

Route::get('/admin/replacements/{id}/old-asset', function ($id) {
    $replacement = DB::table('replacements')->where('Replacement_id', $id)->first();
    if (!$replacement) {
        return response()->json(['success' => false, 'message' => 'Not found'], 404);
    }

    $old = DB::table('assets')->where('id', $replacement->old_assets_id)->first();
    if (!$old) {
        return response()->json(['success' => false, 'message' => 'Old asset not found'], 404);
    }

    return response()->json(['success' => true, 'asset' => $old]);
})->middleware('auth');

// Mark replacement as Received
Route::match(['post', 'patch'], '/admin/replacements/{id}/received', function (Request $request, $id) {
    $user = Auth::user();
    if (!$user) {
        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => 'Unauthenticated'], 401)
            : redirect('/login');
    }

    $replacement = DB::table('replacements')->where('Replacement_id', $id)->first();
    if (!$replacement) {
        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => 'Replacement not found'], 404)
            : abort(404);
    }

    $newId = $replacement->new_assets_id ?? null;
    $oldId = $replacement->old_assets_id ?? null;

    if (!$newId || !$oldId || (int)$newId === (int)$oldId) {
        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => 'Link a new asset first'], 422)
            : back()->with('error', 'Link a new asset first.');
    }

    try {
        DB::transaction(function () use ($replacement, $newId, $oldId, $id, $user) {
            // New asset → Active (assigned user already set on create)
            DB::table('assets')->where('id', $newId)->update([
                'Lifecycle_Status' => 'Active',
                'updated_at'       => now(),
            ]);

            // Old asset → Pullout
            DB::table('assets')->where('id', $oldId)->update([
                'Lifecycle_Status' => 'Pullout',
                'updated_at'       => now(),
            ]);

            // Optional pullout record
            $pulloutId = DB::table('pullouts')->insertGetId([
                'request_id'   => $replacement->request_id ?? null,
                'asset_id'     => $oldId,
                'Approve_by'   => $user->email ?? 'Admin',
                'Description'  => 'Replacement',
                'notes'        => 'Old asset pulled out after replacement received. New asset ID: ' . $newId,
                'pullout_date' => now()->toDateString(),
                'destination'  => 'Storage Room',
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            DB::table('pullout_items')->insert([
                'pullout_id' => $pulloutId,
                'asset_id'   => $oldId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Replacement → Received
            DB::table('replacements')->where('Replacement_id', $id)->update([
                'status'             => 'Received', // if you have a `status` column instead
                'updated_at'         => now(),
            ]);

            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'asset_id'           => $newId,
                'action_type'        => 'REPLACEMENT',
                'notes'              => 'Replacement #' . $id . ' marked Received',
                'action_description' => 'New asset Active; old asset Pullout',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Marked as Received']);
        }
        return redirect('/admin/replacement')->with('success', 'Replacement marked as Received.');
    } catch (\Throwable $e) {
        \Log::error('Received failed: ' . $e->getMessage());
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
        return back()->with('error', $e->getMessage());
    }
})->middleware('auth');

Route::get('/admin/replacements/{id}/old-asset', function ($id) {
    $replacement = DB::table('replacements')->where('id', $id)->first();
    if (!$replacement) {
        return response()->json(['success' => false, 'message' => 'Not found'], 404);
    }

    $oldId = $replacement->old_asset_id ?? $replacement->asset_id ?? null;
    if (!$oldId) {
        return response()->json(['success' => false, 'message' => 'No old asset'], 404);
    }

    $asset = DB::table('assets')->where('id', $oldId)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Old asset not found'], 404);
    }

    return response()->json(['success' => true, 'asset' => $asset]);
})->middleware('auth');


// Admin pullout page
Route::get('/admin/pullout', function () {
    $totalPulledOut = 0;
    $pulloutRecords = collect([]);
    $availableAssets = collect([]);

    if (\Illuminate\Support\Facades\Schema::hasTable('pullouts')) {
        try {
            $query = DB::table('pullouts')
                ->leftJoin('requests', 'pullouts.request_id', '=', 'requests.id')
                ->leftJoin('users', 'requests.user_id', '=', 'users.id')
                ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
                ->select(
                    'pullouts.*',
                    'requests.request_type as request_type',
                    'requests.status as request_status',
                    'employee_numbers.Full_Name as requested_by'
                )
                ->whereIn('pullouts.status', ['pending', 'approved'])   // ← only show active pullouts
                ->orderByDesc('pullouts.pullout_date');

            $pulloutRows = $query->get();

            $pulloutItems = DB::table('pullout_items')
                ->leftJoin('assets', 'pullout_items.asset_id', '=', 'assets.id')
                ->select('pullout_items.pullout_id', 'pullout_items.asset_id', 'assets.Asset_name', 'assets.Asset_code')
                ->get()
                ->groupBy('pullout_id');

            $pulloutRecords = $pulloutRows->map(function ($r) use ($pulloutItems) {
                $items = $pulloutItems->get($r->id, collect());
                $assetNames = $items->pluck('Asset_name')->filter()->values();
                $assetCodes = $items->pluck('Asset_code')->filter()->values();

                if ($items->isEmpty() && !empty($r->asset_id)) {
                    $legacyAsset = DB::table('assets')
                        ->where('id', $r->asset_id)
                        ->select('Asset_name', 'Asset_code')
                        ->first();

                    if ($legacyAsset) {
                        $assetNames = collect([$legacyAsset->Asset_name]);
                        $assetCodes = collect([$legacyAsset->Asset_code]);
                    }
                }

                return (object) [
                    'id'           => $r->id,
                    'asset_count'  => max(1, $items->count() ?: ($r->asset_id ? 1 : 0)),
                    'asset_name'   => $assetNames->first() ?? ('Asset #' . ($r->asset_id ?? '')),
                    'asset_code'   => $assetCodes->first() ?? null,
                    'asset_names'  => $assetNames,
                    'asset_codes'  => $assetCodes,
                    'pullout_date' => $r->pullout_date
                        ? (string) \Illuminate\Support\Carbon::parse($r->pullout_date)->format('M d, Y')
                        : ($r->created_at ? (string) \Illuminate\Support\Carbon::parse($r->created_at)->format('M d, Y') : '-'),
                    'reason'       => $r->Description ?? $r->notes ?? null,
                    'pulled_by'    => $r->Approve_by ?? null,
                    'requested_by' => $r->requested_by ?? null,
                    'status'       => isset($r->status) && $r->status
                        ? strtolower($r->status)
                        : (isset($r->request_status) ? strtolower($r->request_status) : 'approved'),
                    'destination'  => $r->destination ?? null,
                    'raw'          => $r,
                ];
            });

            $totalPulledOut = DB::table('assets')
            ->where('Lifecycle_Status', 'Pullout')
            ->count();
        } catch (\Exception $e) {
            $pulloutRecords = collect();
            $totalPulledOut = 0;
        }
    }

    // Available assets for the "Record Pullout" modal
    if (\Illuminate\Support\Facades\Schema::hasTable('assets')) {
        try {
            $blockedAssetIds = DB::table('pullout_items')
                ->leftJoin('pullouts', 'pullout_items.pullout_id', '=', 'pullouts.id')
                ->whereRaw("lower(coalesce(pullouts.status, '')) in ('pending', 'approved')")
                ->pluck('pullout_items.asset_id')
                ->all();

            $availableAssets = Asset::where('Lifecycle_Status', '!=', 'Pullout')
                ->whereNotIn('id', $blockedAssetIds)
                ->orderBy('Asset_name')
                ->get()
                ->map(function ($a) {
                    return (object) [
                        'id'               => $a->id,
                        'name'             => $a->Asset_name ?? '',
                        'asset_code'       => $a->Asset_code ?? '',
                        'Lifecycle_Status' => $a->Lifecycle_Status ?? '',
                        'assignedUser'     => (object) ['name' => $a->user?->full_name ?? 'Unassigned'],
                    ];
                });
        } catch (\Exception $e) {
            $availableAssets = collect();
        }
    }

    $users = DB::table('users')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->select('users.id', 'users.email', 'employee_numbers.Full_Name as full_name')
        ->orderBy('employee_numbers.Full_Name')
        ->get();

    return view('admin.pullout.pullout', compact(
        'pulloutRecords',
        'availableAssets',
        'totalPulledOut',
        'users'
    ));
});

Route::get('/admin/pullout/{id}/assets', function ($id) {
    $items = DB::table('pullout_items')
        ->join('assets', 'pullout_items.asset_id', '=', 'assets.id')
        ->where('pullout_items.pullout_id', $id)
        ->select('assets.id', 'assets.Asset_name as name', 'assets.Asset_code as code')
        ->get();

    if ($items->isEmpty()) {
        $pullout = DB::table('pullouts')->where('id', $id)->first();
        if ($pullout && $pullout->asset_id) {
            $asset = DB::table('assets')->where('id', $pullout->asset_id)->first();
            if ($asset) {
                $items = collect([(object)[
                    'id'   => $asset->id,
                    'name' => $asset->Asset_name,
                    'code' => $asset->Asset_code,
                ]]);
            }
        }
    }

    return response()->json([
        'success' => true,
        'assets'  => $items,
    ]);
})->middleware('auth');

// Server-side user search for assigning assets (used by asset registry)
Route::get('/admin/users/search', function (Request $request) {
    $q = $request->query('q');
    $usersQuery = User::query()
        ->select('users.id', 'employee_numbers.Full_Name as full_name', 'users.email', 'departments.Name as department')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id');
    
    if ($q) {
        $usersQuery->where(function ($builder) use ($q) {
            $builder->where('employee_numbers.Full_Name', 'like', "%{$q}%")
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
        'supplier' => 'nullable|string|max:150',
        'location' => 'nullable|string|max:255',
        'acquisition_date' => 'nullable|date',
        'purchase_price' => 'nullable|numeric',
        'warranty_months' => 'nullable|integer',
        'serial_number' => 'nullable|string|max:150',
        'asset_photo' => 'nullable|image|max:10240',
        'qr_image' => 'nullable|string',
        'notes' => 'nullable|string',
        'lifespan_months' => 'nullable|integer|min:1',
        'expiration_date' => 'nullable|date',
        'repair_counts' => 'nullable|integer|min:0',
        'last_maintenance_date' => 'nullable|date',
        'maintenance_interval' => 'nullable|integer|min:1',
        'next_maintenance_date' => 'nullable|date',
        'quantity' => 'required|integer|min:1|max:100',
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

    $quantity = (int) $validated['quantity'];
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

    $createdAssets = [];

    try {
        DB::transaction(function () use (
            $request,
            $validated,
            $quantity,
            $category,
            $condition,
            $userId,
            $fileName,
            $filePath,
            $fileSize,
            $mime,
            $url,
            &$createdAssets
        ) {
            $baseAssetCode = $validated['asset_code'] ?: ('AST-' . strtoupper(Str::random(10)));

            $makeUniqueAssetCode = function (int $index) use ($baseAssetCode) {
                $candidateBase = $baseAssetCode;
                $candidate = $index === 1
                    ? $candidateBase
                    : $candidateBase . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);

                while (Asset::where('Asset_code', $candidate)->exists()) {
                    $candidateBase = 'AST-' . strtoupper(Str::random(10));
                    $candidate = $index === 1
                        ? $candidateBase
                        : $candidateBase . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                }

                return $candidate;
            };

            $saveQrToAsset = function (Asset $asset, string $assetCode) {
                try {
                    $api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($assetCode);
                    $binary = @file_get_contents($api);
                    if ($binary === false) {
                        return null;
                    }

                    $qrPath = 'assets/qr/' . $assetCode . '-' . time() . '.png';
                    if (Storage::disk('public')->put($qrPath, $binary)) {
                        DB::table('assets')->where('id', $asset->id)->update([
                            'qr_code_path' => $qrPath,
                            'updated_at' => now(),
                        ]);

                        return Storage::url($qrPath);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to save QR image for bulk asset', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }

                return null;
            };

            for ($index = 1; $index <= $quantity; $index++) {
                $assetCode = $makeUniqueAssetCode($index);

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
                    'supplier' => $validated['supplier'] ?? null,
                    'model' => null,
                    'manufacture' => null,
                    'serial_Number' => $validated['serial_number'] ?? null,
                    'asset_location' => $validated['location'] ?? null,
                    'qr_code_path' => null,
                    'lifespan_months' => $validated['lifespan_months'] ?? null,
                    'expiration_date' => $validated['expiration_date'] ?? null,
                    'repair_counts' => $validated['repair_counts'] ?? 0,
                    'last_maintenance_date' => $validated['last_maintenance_date'] ?? null,
                    'next_maintenance_date' => $validated['next_maintenance_date'] ?? null,
                    'maintenance_interval' => $validated['maintenance_interval'] ?? null,
                ]);

                if (!empty($validated['lifespan_months']) && empty($validated['expiration_date']) && !empty($validated['acquisition_date'])) {
                    $acquisitionDate = \Carbon\Carbon::parse($validated['acquisition_date']);
                    $asset->update([
                        'expiration_date' => $acquisitionDate->copy()->addMonths((int) $validated['lifespan_months'])->toDateString(),
                    ]);
                }

                if (!empty($validated['maintenance_interval']) && empty($validated['next_maintenance_date'])) {
                    $maintenanceMonths = (int) $validated['maintenance_interval'];

                    if (!empty($validated['last_maintenance_date'])) {
                        $baseDate = \Carbon\Carbon::parse($validated['last_maintenance_date']);
                    } elseif (!empty($validated['acquisition_date'])) {
                        $baseDate = \Carbon\Carbon::parse($validated['acquisition_date']);
                    } else {
                        $baseDate = $asset->created_at;
                    }

                    $asset->update([
                        'next_maintenance_date' => $baseDate->copy()->addMonths($maintenanceMonths)->toDateString(),
                    ]);
                }

                if ($fileName && $filePath && $fileSize && $mime) {
                    DB::table('asset_files')->insert([
                        'Asset_id' => $asset->id,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'file_size' => $fileSize,
                        'mime_type' => $mime,
                        'url' => $url,
                        'uploaded_at' => now()->toDateTimeString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('audit_logs')->insert([
                    'user_id' => Auth::id(),
                    'asset_id' => $asset->id,
                    'action_type' => 'CREATE',
                    'notes' => 'Registered asset ' . $asset->Asset_code . ' - ' . $asset->Asset_name,
                    'action_description' => 'New asset registered with code ' . $asset->Asset_code . ' assigned to user ID ' . $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $qrUrl = $saveQrToAsset($asset, $assetCode);
                $createdAssets[] = [
                    'id'       => $asset->id,
                    'code'     => $asset->Asset_code,
                    'name'     => $asset->Asset_name,
                    'qr_url'   => $qrUrl,
                    'location' => $validated['location'] ?? null,
                    'category' => $category,
                    'acquired' => $validated['acquisition_date'] ?? null,
                ];
            }
        });
    } catch (\Illuminate\Database\QueryException $e) {
        return back()->withErrors(['error' => 'Failed to save asset(s). DB error: ' . $e->getMessage()])->withInput();
    } catch (\Exception $e) {
        return back()->withErrors(['error' => 'Failed to register asset(s): ' . $e->getMessage()])->withInput();
    }

    return redirect('/admin/assets/registry')
        ->with('success', 'Successfully registered ' . count($createdAssets) . ' asset(s).')
        ->with('bulk_qr_labels', $createdAssets)
        ->with('bulk_registered_count', count($createdAssets));
})->name('admin.assets.store');

Route::post('/admin/requests/{id}/approve', function ($id) {
    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (!$requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    if (strtolower((string) $requestRecord->status) !== 'pending') {
        return response()->json(['message' => 'Only pending requests can be approved.'], 422);
    }

    $approvedBy  = Auth::user()?->email ?? Auth::user()?->full_name ?? 'Admin';
    $requestType = strtolower((string) $requestRecord->request_type);

    // ─── Get all asset IDs for this request ─────────────────
    $assetIds = DB::table('request_items')
        ->where('request_id', $requestRecord->id)
        ->pluck('asset_id')
        ->map(fn($id) => (int) $id)
        ->unique()
        ->values()
        ->all();

    // Fallback for old single-asset requests
    if (empty($assetIds) && !empty($requestRecord->asset_id)) {
        $assetIds = [(int) $requestRecord->asset_id];
    }

    if (empty($assetIds)) {
        return response()->json(['message' => 'No assets found for this request.'], 422);
    }

    try {
        DB::transaction(function () use ($requestRecord, $approvedBy, $requestType, $assetIds) {

            // 1. Mark the request as Approved
            DB::table('requests')
                ->where('id', $requestRecord->id)
                ->update([
                    'status'     => 'Approved',
                    'updated_at' => now(),
                ]);

            // 2. Process each asset according to request type
            foreach ($assetIds as $assetId) {

                // ─────────────── REPAIR ───────────────
                if ($requestType === 'repair') {
                    $exists = DB::table('repairs')
                        ->where('Request_id', $requestRecord->id)
                        ->where('Assets_id', $assetId)
                        ->exists();

                    if (!$exists) {
                        DB::table('repairs')->insert([
                            'Request_id'         => $requestRecord->id,
                            'Assets_id'          => $assetId,
                            'Approve_by'         => $approvedBy,
                            'Repair_Description' => $requestRecord->Note,
                            'Repair_Cost'        => 0.00,
                            'status'             => 'Pending',
                            'notes'              => $requestRecord->Note,
                            'Repair_Date'        => now(),
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }

                    DB::table('assets')->where('id', $assetId)->update([
                        'Lifecycle_Status' => 'For Repair',
                        'updated_at'       => now(),
                    ]);
                }

                // ─────────────── DISPOSAL ───────────────
                if ($requestType === 'disposal') {
                    $exists = DB::table('disposals')
                        ->where('Request_id', $requestRecord->id)
                        ->where('Asset_id', $assetId)
                        ->exists();

                    if (!$exists) {
                        DB::table('disposals')->insert([
                            'Request_id'      => $requestRecord->id,
                            'Asset_id'        => $assetId,
                            'Approve_by'      => $approvedBy,
                            'Description'     => 'Approved disposal request',
                            'disposal_reason' => 'Obsolete',
                            'disposal_date'   => now()->toDateString(),
                            'notes'           => $requestRecord->Note,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }

                    DB::table('assets')->where('id', $assetId)->update([
                        'Lifecycle_Status' => 'Disposal',
                        'updated_at'       => now(),
                    ]);
                }

                // ─────────────── REPLACEMENT ───────────────
                if ($requestType === 'replacement') {
                    $exists = DB::table('replacements')
                        ->where('Request_id', $requestRecord->id)
                        ->where('old_assets_id', $assetId)
                        ->exists();

                    if (!$exists) {
                        DB::table('replacements')->insert([
                            'Request_id'         => $requestRecord->id,
                            'old_assets_id'      => $assetId,
                            'new_assets_id'      => $assetId, // temporary placeholder
                            'Approve_by'         => $approvedBy,
                            'reason'             => 'Approved replacement request',
                            'notes'              => $requestRecord->Note,
                            'Replacement_Date'   => now(),
                            'replacement_reason' => 'Obsolete',
                            'status'             => 'Approved',
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }

                    DB::table('assets')->where('id', $assetId)->update([
                        'Lifecycle_Status' => 'For Replacement',
                        'updated_at'       => now(),
                    ]);
                }

                // ─────────────── TRANSFER ───────────────
                if ($requestType === 'transfer') {
                    $targetUserId = $requestRecord->assign_to_user_id ?? null;

                    if ($targetUserId) {
                        DB::table('assets')->where('id', $assetId)->update([
                            'user_id'          => $targetUserId,
                            'Lifecycle_Status' => 'Active',
                            'updated_at'       => now(),
                        ]);

                        $assigneeName = DB::table('users')
                            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
                            ->where('users.id', $targetUserId)
                            ->value('employee_numbers.Full_Name');

                        DB::table('audit_logs')->insert([
                            'user_id'            => Auth::id(),
                            'request_id'         => $requestRecord->id,
                            'asset_id'           => $assetId,
                            'action_type'        => 'TRANSFER',
                            'notes'              => 'Transferred asset to ' . ($assigneeName ?? 'User #' . $targetUserId),
                            'action_description' => 'Asset ownership transferred via approved bulk transfer request #' . $requestRecord->id,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }
                }
            }

            // ─────────────── PULLOUT (one transaction for all assets) ───────────────
            if ($requestType === 'pullout') {
                $exists = DB::table('pullouts')
                    ->where('request_id', $requestRecord->id)
                    ->exists();

                if (!$exists) {
                    $pulloutId = createPulloutTransaction([
                        'request_id'   => $requestRecord->id,
                        'Approve_by'   => $approvedBy,
                        'Description'  => 'Approved pullout request',
                        'notes'        => $requestRecord->Note,
                        'pullout_date' => now()->toDateString(),
                        'status'       => 'approved',
                    ], $assetIds);

                    // Update lifecycle of all assets
                    DB::table('assets')->whereIn('id', $assetIds)->update([
                        'Lifecycle_Status' => 'Pullout',
                        'updated_at'       => now(),
                    ]);
                }
            }

            // 3. Audit log
            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'request_id'         => $requestRecord->id,
                'asset_id'           => null,
                'action_type'        => 'APPROVAL',
                'notes'              => 'Approved bulk ' . ($requestRecord->request_type ?? '') . ' request with ' . count($assetIds) . ' asset(s)',
                'action_description' => ucfirst($requestRecord->request_type ?? 'Unknown') . ' request #' . $requestRecord->id . ' approved',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });

        return response()->json(['message' => 'Request approved successfully.']);
    } catch (\Throwable $e) {
        \Log::error('Approve request failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to approve request: ' . $e->getMessage()
        ], 500);
    }
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

    $codeLower = strtolower($code);

    $asset = DB::table('assets')
        ->whereRaw('LOWER("Asset_code") = ?', [$codeLower])
        ->orWhereRaw('LOWER(asset_code) = ?', [$codeLower])
        ->first();

    if (!$asset) {
        // fallback
        $asset = DB::table('assets')
            ->where('Asset_code', $code)
            ->orWhere('asset_code', $code)
            ->first();
    }

    if (!$asset) {
        return response()->json(['message' => 'not found'], 404);
    }

    return response()->json([
        'id'         => $asset->id,
        'Asset_name' => $asset->Asset_name ?? null,
        'Asset_code' => $asset->Asset_code ?? null,
        'status'     => $asset->Lifecycle_Status ?? null,
    ]);
})->middleware('auth');

// Record disposal (called by scanner auto-submit or manual form)
Route::post('/admin/disposal/record', function (Request $request) {
    $data = $request->only(['asset_id', 'disposal_date', 'reason', 'disposed_by', 'notes']);

    if (empty($data['asset_id'])) {
        return response()->json(['success' => false, 'message' => 'asset_id required'], 422);
    }

    $asset = DB::table('assets')->where('id', $data['asset_id'])->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    // Already disposed?
    $assetStatus = $asset->Lifecycle_Status ?? '';
    if (in_array($assetStatus, ['Disposal', 'Disposed'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'This asset is already disposed (status: ' . $assetStatus . ')'
        ], 422);
    }

    // Already has a disposal record?
    $existing = DB::table('disposals')->where('Asset_id', $data['asset_id'])->exists();
    if ($existing) {
        return response()->json([
            'success' => false,
            'message' => 'This asset already has a disposal record and cannot be disposed again.'
        ], 422);
}

    $disposalDate = $data['disposal_date'] ?: date('Y-m-d');

    // Map free-text → valid enum
    $reasonMap = [
        'damaged'          => 'Damage',
        'beyond repair'    => 'Beyond Repair',
        'obsolete'         => 'Obsolete',
        'lost'             => 'Lost',
        'stolen'           => 'Lost',
        'upgraded'         => 'Replace',
        'replace'          => 'Replace',
        'scanned disposal' => 'Obsolete',
        'other'            => 'Obsolete',
    ];
    $rawReason = strtolower(trim($data['reason'] ?? 'Obsolete'));
    $disposalReason = $reasonMap[$rawReason] ?? 'Obsolete';

    try {
        DB::beginTransaction();

        $id = DB::table('disposals')->insertGetId([
            'Asset_id'        => $asset->id,
            'Request_id'      => null,
            'Approve_by'      => $data['disposed_by'] ?? (Auth::user()?->email ?? 'Admin'),
            'Description'     => $data['reason'] ?? 'Scanned Disposal',
            'disposal_date'   => $disposalDate,
            'disposal_reason' => $disposalReason,
            'notes'           => $data['notes'] ?? null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'Disposal_ID');

        // Force asset status to Disposed
        $updated = DB::table('assets')
            ->where('id', $asset->id)
            ->update([
                'Lifecycle_Status' => 'Disposal',
                'updated_at'       => now(),
            ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Disposal recorded successfully',
            'id'      => $id,
            'asset_updated' => $updated > 0
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        \Log::error('Disposal record failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to record disposal',
            'error'   => $e->getMessage()   // ← now visible in frontend
        ], 500);
    }
})->middleware('auth');

// Record pullout (called by scanner auto-submit or manual form)
Route::post('/admin/pullout/record', function (Request $request) {
    $assetIds = normalizePulloutAssetIds($request->input('asset_ids', $request->input('asset_id')));

    if (empty($assetIds)) {
        return response()->json(['success' => false, 'message' => 'At least one asset is required.'], 422);
    }

    $reason = trim((string) $request->input('reason', ''));
    if ($reason === '') {
        return response()->json(['success' => false, 'message' => 'Reason is required.'], 422);
    }

    $assets = DB::table('assets')->whereIn('id', $assetIds)->get()->keyBy('id');
    if ($assets->count() !== count($assetIds)) {
        return response()->json(['success' => false, 'message' => 'One or more assets were not found.'], 404);
    }

    foreach ($assetIds as $assetId) {
        $asset = $assets->get($assetId);
        $assetStatus = $asset->Lifecycle_Status ?? null;

        if (in_array($assetStatus, ['Pullout', 'pulled_out', 'Disposal', 'For Repair'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more selected assets are not available for pullout.'
            ], 422);
        }
    }

    try {
        $id = createPulloutTransaction([
            'pullout_date'         => $request->input('pullout_date') ?: date('Y-m-d'),
            'Description'          => $reason,
            'Approve_by'           => $request->input('pulled_by') ?? (Auth::user()?->email ?? 'Admin'),
            'notes'                => $request->input('notes'),
            'destination'          => $request->input('destination'),
            'expected_return_date' => $request->input('expected_return_date') ?: null,
            'status'               => 'pending',
        ], $assetIds);

        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'action_type'        => 'CREATE',
            'action_description' => 'Created bulk pullout #' . $id . ' for ' . count($assetIds) . ' asset(s). Reason: ' . $reason,
            'notes'              => $request->input('notes'),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pullout recorded successfully.',
            'id'      => $id,
        ]);
    } catch (\Throwable $e) {
        \Log::error('Bulk pullout failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to record pullout',
            'error'   => $e->getMessage(),
        ], 500);
    }
})->middleware('auth');

Route::post('/admin/pullout/approve/{id}', function ($id) {
    $pullout = DB::table('pullouts')->where('id', $id)->first();

    if (!$pullout) {
        return response()->json(['message' => 'Pullout record not found.'], 404);
    }

    $items = DB::table('pullout_items')->where('pullout_id', $id)->pluck('asset_id')->all();

    if (empty($items) && !empty($pullout->asset_id)) {
        $items = [$pullout->asset_id];
    }

    DB::table('pullouts')->where('id', $id)->update([
        'status' => 'approved',
        'Approve_by' => Auth::user()?->name ?? Auth::user()?->email ?? 'Admin',
        'updated_at' => now(),
    ]);

    if (!empty($items)) {
        DB::table('assets')->whereIn('id', $items)->update([
            'Lifecycle_Status' => 'Pullout',
            'updated_at' => now(),
        ]);
    }

    DB::table('audit_logs')->insert([
        'user_id' => Auth::id(),
        'action_type' => 'APPROVAL',
        'action_description' => 'Approved pullout request #' . $id . ' for ' . count($items) . ' asset(s).',
        'notes' => 'Pullout request approved.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['message' => 'Pullout approved.']);
})->middleware('auth');

Route::delete('/admin/pullout/delete/{id}', function ($id) {
    $pullout = DB::table('pullouts')->where('id', $id)->first();

    if (!$pullout) {
        return response()->json(['message' => 'Pullout record not found.'], 404);
    }

    DB::table('pullouts')->where('id', $id)->delete();

    DB::table('audit_logs')->insert([
        'user_id' => Auth::id(),
        'action_type' => 'UPDATE',
        'action_description' => 'Deleted pullout request #' . $id . '.',
        'notes' => 'Pullout record deleted.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['message' => 'Pullout deleted.']);
})->middleware('auth');

Route::post('/admin/pullout/{id}/resolve', function (Request $request, $id) {
    $pullout = DB::table('pullouts')->where('id', $id)->first();
    if (!$pullout) {
        return response()->json(['success' => false, 'message' => 'Pullout not found'], 404);
    }

    $action          = $request->input('action');
    $assignToUserId  = $request->input('assign_to_user_id');
    $repairNotes     = trim((string) $request->input('repair_notes', ''));
    $notes           = $request->input('notes');
    $selectedAssetIds = $request->input('asset_ids', []);   // ← new

    if (!is_array($selectedAssetIds)) {
        $selectedAssetIds = [];
    }
    $selectedAssetIds = array_map('intval', $selectedAssetIds);

    // Get all assets currently linked to this pullout
    $allAssetIds = DB::table('pullout_items')
        ->where('pullout_id', $id)
        ->pluck('asset_id')
        ->all();

    // Fallback for old single-asset pullouts
    if (empty($allAssetIds) && !empty($pullout->asset_id)) {
        $allAssetIds = [(int) $pullout->asset_id];
    }

    if (empty($allAssetIds)) {
        return response()->json(['success' => false, 'message' => 'No assets linked to this pullout'], 422);
    }

    // Only process the ones the user selected (if any were sent)
    $assetIds = !empty($selectedAssetIds)
        ? array_values(array_intersect($allAssetIds, $selectedAssetIds))
        : $allAssetIds;

    if (empty($assetIds)) {
        return response()->json(['success' => false, 'message' => 'No valid assets selected'], 422);
    }

    try {
        DB::beginTransaction();

        // ─────────────────────────────────────────────
        // ASSIGN
        // ─────────────────────────────────────────────
        if ($action === 'assign') {
            if (empty($assignToUserId)) {
                return response()->json(['success' => false, 'message' => 'User is required'], 422);
            }

            $newLocation = trim((string) $request->input('new_location', ''));
            if ($newLocation === '') {
                return response()->json(['success' => false, 'message' => 'New location is required'], 422);
            }

            $ownerName = DB::table('users')
                ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
                ->where('users.id', $assignToUserId)
                ->value('employee_numbers.Full_Name') ?? ('User #' . $assignToUserId);

            foreach ($assetIds as $assetId) {
                DB::table('assets')->where('id', $assetId)->update([
                    'user_id'          => $assignToUserId,
                    'Lifecycle_Status' => 'Active',
                    'asset_location'   => $newLocation,
                    'updated_at'       => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'user_id'            => Auth::id(),
                    'asset_id'           => $assetId,
                    'action_type'        => 'TRANSFER',
                    'notes'              => 'Assigned from pullout to ' . $ownerName . ' at ' . $newLocation,
                    'action_description' => 'Pullout #' . $id . ' resolved: assigned to ' . $ownerName,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            // Remove the processed assets from this pullout
            DB::table('pullout_items')
                ->where('pullout_id', $id)
                ->whereIn('asset_id', $assetIds)
                ->delete();

            // If no assets left → mark the whole pullout as completed
            $remaining = DB::table('pullout_items')->where('pullout_id', $id)->count();
            if ($remaining === 0) {
                DB::table('pullouts')->where('id', $id)->update([
                    'status'     => 'completed',
                    'notes'      => trim(($pullout->notes ? $pullout->notes . ' | ' : '') . ($notes ?: 'Assigned to ' . $ownerName)),
                    'updated_at' => now(),
                ]);
            } else {
                // Still has assets → keep it open, just add a note
                DB::table('pullouts')->where('id', $id)->update([
                    'notes'      => trim(($pullout->notes ? $pullout->notes . ' | ' : '') . 'Partially assigned (' . count($assetIds) . ' asset(s)). ' . ($notes ?? '')),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => count($assetIds) . ' asset(s) assigned to ' . $ownerName . ' and released from pullout.',
            ]);
        }

        // ─────────────────────────────────────────────
        // REPAIR
        // ─────────────────────────────────────────────
        if ($action === 'repair') {
            foreach ($assetIds as $assetId) {
                DB::table('assets')->where('id', $assetId)->update([
                    'Lifecycle_Status' => 'For Repair',
                    'updated_at'       => now(),
                ]);

                $requestId = DB::table('requests')->insertGetId([
                    'user_id'      => Auth::id(),
                    'asset_id'     => $assetId,
                    'request_type' => 'Repair',
                    'status'       => 'Approved',
                    'Note'         => $repairNotes !== '' ? $repairNotes : 'Sent from pullout #' . $id,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                DB::table('repairs')->insert([
                    'Assets_id'          => $assetId,
                    'Request_id'         => $requestId,
                    'Repair_Description' => $repairNotes !== '' ? $repairNotes : 'Repair from pullout',
                    'Repair_Date'        => now(),
                    'Approve_by'         => Auth::user()?->email ?? 'Admin',
                    'Repair_Cost'        => 0,
                    'status'             => 'Pending',
                    'notes'              => 'From pullout #' . $id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'user_id'            => Auth::id(),
                    'asset_id'           => $assetId,
                    'request_id'         => $requestId,
                    'action_type'        => 'REPAIR',
                    'notes'              => 'Sent to repair from pullout #' . $id,
                    'action_description' => 'Asset sent to repair via pullout resolve',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            // Remove the processed assets from this pullout
            DB::table('pullout_items')
                ->where('pullout_id', $id)
                ->whereIn('asset_id', $assetIds)
                ->delete();

            $remaining = DB::table('pullout_items')->where('pullout_id', $id)->count();
            if ($remaining === 0) {
                DB::table('pullouts')->where('id', $id)->update([
                    'status'     => 'completed',
                    'notes'      => trim(($pullout->notes ? $pullout->notes . ' | ' : '') . 'All assets sent to repair. ' . ($notes ?? '')),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('pullouts')->where('id', $id)->update([
                    'status'     => $pullout->status === 'pending' ? 'approved' : $pullout->status,
                    'notes'      => trim(($pullout->notes ? $pullout->notes . ' | ' : '') . 'Partially sent to repair (' . count($assetIds) . ' asset(s)). ' . ($notes ?? '')),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => count($assetIds) . ' asset(s) sent to repair.',
            ]);
        }

        DB::rollBack();
        return response()->json(['success' => false, 'message' => 'Invalid action'], 422);

    } catch (\Throwable $e) {
        DB::rollBack();
        \Log::error('Pullout resolve failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to resolve pullout',
            'error'   => $e->getMessage(),
        ], 500);
    }
})->middleware('auth');

Route::post('/admin/pullout/{id}/dispose', function (Request $request, $id) {
    $pullout = DB::table('pullouts')->where('id', $id)->first();
    if (!$pullout) {
        return response()->json(['success' => false, 'message' => 'Pullout not found'], 404);
    }

    $selectedAssetIds = $request->input('asset_ids', []);
    if (!is_array($selectedAssetIds)) {
        $selectedAssetIds = [];
    }
    $selectedAssetIds = array_map('intval', array_filter($selectedAssetIds));

    // All assets currently in this pullout
    $allAssetIds = DB::table('pullout_items')
        ->where('pullout_id', $id)
        ->pluck('asset_id')
        ->all();

    // Fallback for old single-asset pullouts
    if (empty($allAssetIds) && !empty($pullout->asset_id)) {
        $allAssetIds = [(int) $pullout->asset_id];
    }

    if (empty($allAssetIds)) {
        return response()->json(['success' => false, 'message' => 'No assets linked to this pullout'], 422);
    }

    // If nothing selected → dispose everything
    $assetIds = !empty($selectedAssetIds)
        ? array_values(array_intersect($allAssetIds, $selectedAssetIds))
        : $allAssetIds;

    if (empty($assetIds)) {
        return response()->json(['success' => false, 'message' => 'No valid assets selected'], 422);
    }

    $reason        = trim((string) $request->input('reason', 'Disposed from pullout'));
    $notes         = $request->input('notes');
    $disposalDate  = $request->input('disposal_date') ?: date('Y-m-d');
    $disposedBy    = $request->input('disposed_by') ?? (Auth::user()?->email ?? 'Admin');

    // Map free-text reason → valid enum (same as your existing disposal route)
    $reasonMap = [
        'damaged'          => 'Damage',
        'beyond repair'    => 'Beyond Repair',
        'obsolete'         => 'Obsolete',
        'lost'             => 'Lost',
        'stolen'           => 'Lost',
        'upgraded'         => 'Replace',
        'replace'          => 'Replace',
        'scanned disposal' => 'Obsolete',
        'other'            => 'Obsolete',
        'disposed from pullout' => 'Obsolete',
    ];
    $rawReason = strtolower(trim($reason));
    $disposalReason = $reasonMap[$rawReason] ?? 'Obsolete';

    try {
        DB::beginTransaction();

        foreach ($assetIds as $assetId) {
            $asset = DB::table('assets')->where('id', $assetId)->first();
            if (!$asset) continue;

            // Skip if already disposed
            if (in_array($asset->Lifecycle_Status ?? '', ['Disposal', 'Disposed'], true)) {
                continue;
            }

            // Already has a disposal record?
            $existing = DB::table('disposals')->where('Asset_id', $assetId)->exists();
            if ($existing) continue;

            // Create disposal record
            DB::table('disposals')->insert([
                'Asset_id'        => $assetId,
                'Request_id'      => null,
                'Approve_by'      => $disposedBy,
                'Description'     => $reason,
                'disposal_date'   => $disposalDate,
                'disposal_reason' => $disposalReason,
                'notes'           => $notes ?: ('Disposed from pullout #' . $id),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Update asset status
            DB::table('assets')->where('id', $assetId)->update([
                'Lifecycle_Status' => 'Disposal',
                'updated_at'       => now(),
            ]);

            // Audit log
            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'asset_id'           => $assetId,
                'action_type'        => 'DISPOSAL',
                'notes'              => 'Disposed from pullout #' . $id,
                'action_description' => 'Asset disposed via pullout trash action',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // Remove disposed assets from this pullout
        DB::table('pullout_items')
            ->where('pullout_id', $id)
            ->whereIn('asset_id', $assetIds)
            ->delete();

        // If no assets left → mark pullout completed
        $remaining = DB::table('pullout_items')->where('pullout_id', $id)->count();
        if ($remaining === 0) {
            DB::table('pullouts')->where('id', $id)->update([
                'status'     => 'completed',
                'notes'      => trim(($pullout->notes ? $pullout->notes . ' | ' : '') . 'All assets disposed. ' . ($notes ?? '')),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('pullouts')->where('id', $id)->update([
                'notes'      => trim(($pullout->notes ? $pullout->notes . ' | ' : '') . 'Partially disposed (' . count($assetIds) . ' asset(s)). ' . ($notes ?? '')),
                'updated_at' => now(),
            ]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => count($assetIds) . ' asset(s) disposed successfully.',
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        \Log::error('Pullout dispose failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to dispose assets',
            'error'   => $e->getMessage(),
        ], 500);
    }
})->middleware('auth');

Route::get('/admin/pullout/{id}/details', function ($id) {
    $pullout = DB::table('pullouts')->where('id', $id)->first();

    if (!$pullout) {
        return response()->json(['success' => false, 'message' => 'Pullout not found'], 404);
    }

    // Get assets linked to this pullout
    $assets = DB::table('pullout_items')
        ->join('assets', 'pullout_items.asset_id', '=', 'assets.id')
        ->where('pullout_items.pullout_id', $id)
        ->select(
            'assets.id',
            'assets.Asset_name as name',
            'assets.Asset_code as code',
            'assets.Lifecycle_Status as status'
        )
        ->get();

    // Fallback for old single-asset records
    if ($assets->isEmpty() && !empty($pullout->asset_id)) {
        $asset = DB::table('assets')->where('id', $pullout->asset_id)->first();
        if ($asset) {
            $assets = collect([(object)[
                'id'     => $asset->id,
                'name'   => $asset->Asset_name,
                'code'   => $asset->Asset_code,
                'status' => $asset->Lifecycle_Status,
            ]]);
        }
    }

    return response()->json([
        'success' => true,
        'pullout' => [
            'id'            => $pullout->id,
            'pullout_date'  => $pullout->pullout_date,
            'status'        => $pullout->status,
            'reason'        => $pullout->Description ?? $pullout->notes,
            'Description'   => $pullout->Description,
            'pulled_by'     => $pullout->Approve_by,
            'Approve_by'    => $pullout->Approve_by,
            'destination'   => $pullout->destination,
            'notes'         => $pullout->notes,
        ],
        'assets' => $assets,
    ]);
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
        ->select('id', 'Asset_code', 'Asset_name', 'Category', 'Lifecycle_Status', 'Condition')
        ->first();

    if ($asset) {
        return response()->json([
            'exists' => true,
            'asset'  => [
                'id'               => $asset->id,
                'code'             => $asset->Asset_code,
                'name'             => $asset->Asset_name,
                'category'         => $asset->Category,
                'lifecycle_status' => $asset->Lifecycle_Status,
                'condition'        => $asset->Condition,
            ],
        ]);
    }

    return response()->json(['exists' => false]);
})->name('user.assets.check');

// AJAX: validate asset code for Department Head (own + department employees)
Route::get('/department-head/assets/check-code', function (Request $request) {
    $code = trim((string) $request->query('code', ''));
    $user = Auth::user();

    if (!$user || ($user->role ?? '') !== 'Department Head' || $code === '') {
        return response()->json(['exists' => false]);
    }

    $asset = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->where('assets.Asset_code', $code)
        ->where('users.department_id', $user->department_id)
        ->select(
            'assets.id',
            'assets.Asset_code',
            'assets.Asset_name',
            'assets.Category',
            'assets.Lifecycle_Status',
            'assets.Condition'
        )
        ->first();

    if ($asset) {
        return response()->json([
            'exists' => true,
            'asset'  => [
                'id'               => $asset->id,
                'code'             => $asset->Asset_code,
                'name'             => $asset->Asset_name,
                'category'         => $asset->Category,
                'lifecycle_status' => $asset->Lifecycle_Status,
                'condition'        => $asset->Condition,
            ],
        ]);
    }

    return response()->json(['exists' => false]);
})->name('department_head.assets.check');

Route::get('/user/requests', function () {
    try {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }

        $requests = DB::table('requests')
            ->where('requests.user_id', $user->id)
            ->orderByDesc('requests.created_at')
            ->paginate(10);

        // Load assets from request_items
        $requestIds = $requests->pluck('id')->all();
        $itemsByRequest = collect();

        if (!empty($requestIds)) {
            $itemsByRequest = DB::table('request_items')
                ->join('assets', 'request_items.asset_id', '=', 'assets.id')
                ->whereIn('request_items.request_id', $requestIds)
                ->select(
                    'request_items.request_id',
                    'assets.id as asset_id',
                    'assets.Asset_name',
                    'assets.Asset_code'
                )
                ->get()
                ->groupBy('request_id');
        }

        $requests->getCollection()->transform(function ($r) use ($itemsByRequest) {
            $related = $itemsByRequest->get($r->id, collect());

            $assetList = $related->map(fn($a) => (object)[
                'Asset_name' => $a->Asset_name,
                'Asset_code' => $a->Asset_code,
            ])->values();

            // Fallback for old single-asset requests
            if ($assetList->isEmpty() && $r->asset_id) {
                $old = DB::table('assets')->where('id', $r->asset_id)->first();
                if ($old) {
                    $assetList = collect([(object)[
                        'Asset_name' => $old->Asset_name,
                        'Asset_code' => $old->Asset_code,
                    ]]);
                }
            }

            $count = $assetList->count();
            $displayName = $count === 0
                ? '—'
                : ($count === 1
                    ? $assetList->first()->Asset_name
                    : $assetList->first()->Asset_name . ' +' . ($count - 1) . ' more');

            return (object) [
                'id'           => $r->id,
                'request_type' => $r->request_type,
                'status'       => $r->status,
                'Note'         => $r->Note,
                'file_path'    => $r->file_path ?? null,
                'file_name'    => $r->file_name ?? null,
                'created_at'   => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at) : now(),
                'updated_at'   => $r->updated_at ? \Illuminate\Support\Carbon::parse($r->updated_at) : now(),
                'asset'        => (object)[          // for backward compatibility
                    'Asset_name' => $displayName,
                    'Asset_code' => $count === 1 ? $assetList->first()->Asset_code : '',
                ],
                'assets'       => $assetList,        // full list for modal
                'asset_count'  => $count,
            ];
        });

        $totalRequests    = DB::table('requests')->where('user_id', $user->id)->count();
        $pendingRequests  = DB::table('requests')->where('user_id', $user->id)->where('status', 'Pending')->count();
        $approvedRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Approved')->count();
        $rejectedRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Rejected')->count();

        return view('users.request.request', compact(
            'requests', 'totalRequests', 'pendingRequests', 'approvedRequests', 'rejectedRequests'
        ));
    } catch (\Throwable $e) {
        return redirect('/users')->withErrors(['error' => 'Unable to load requests.']);
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
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('users.department_id', $user->department_id)
        ->select(
            'requests.*',
            'employee_numbers.Full_Name as requester_name'
        )
        ->orderByDesc('requests.created_at')
        ->paginate(10);

    // Load assets from request_items
    $requestIds = $requests->pluck('id')->all();
    $itemsByRequest = collect();

    if (!empty($requestIds)) {
        $itemsByRequest = DB::table('request_items')
            ->join('assets', 'request_items.asset_id', '=', 'assets.id')
            ->whereIn('request_items.request_id', $requestIds)
            ->select(
                'request_items.request_id',
                'assets.id as asset_id',
                'assets.Asset_name',
                'assets.Asset_code'
            )
            ->get()
            ->groupBy('request_id');
    }

    $requests->getCollection()->transform(function ($r) use ($itemsByRequest) {
        $related = $itemsByRequest->get($r->id, collect());

        $assetList = $related->map(fn($a) => (object)[
            'Asset_name' => $a->Asset_name,
            'Asset_code' => $a->Asset_code,
        ])->values();

        // Fallback for old single-asset
        if ($assetList->isEmpty() && $r->asset_id) {
            $old = DB::table('assets')->where('id', $r->asset_id)->first();
            if ($old) {
                $assetList = collect([(object)[
                    'Asset_name' => $old->Asset_name,
                    'Asset_code' => $old->Asset_code,
                ]]);
            }
        }

        $count = $assetList->count();
        $displayName = $count === 0
            ? '—'
            : ($count === 1
                ? $assetList->first()->Asset_name
                : $assetList->first()->Asset_name . ' +' . ($count - 1) . ' more');

        return (object) [
            'id'             => $r->id,
            'request_type'   => $r->request_type,
            'status'         => $r->status,
            'Note'           => $r->Note,
            'file_path'      => $r->file_path ?? null,
            'file_name'      => $r->file_name ?? null,
            'created_at'     => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at) : now(),
            'updated_at'     => $r->updated_at ? \Illuminate\Support\Carbon::parse($r->updated_at) : now(),
            'requester_name' => $r->requester_name ?? null,
            'asset'          => (object)[
                'Asset_name' => $displayName,
                'Asset_code' => $count === 1 ? $assetList->first()->Asset_code : '',
            ],
            'assets'         => $assetList,
            'asset_count'    => $count,
        ];
    });

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
        'requests'         => $requests,
        'totalRequests'    => $totalRequests,
        'pendingRequests'  => $pendingRequests,
        'approvedRequests' => $approvedRequests,
        'rejectedRequests' => $rejectedRequests,
        'user'             => $user,
        'currentUser'      => $user,
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

