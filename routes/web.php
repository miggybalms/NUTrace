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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\UserRequestController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ExecutiveReviewController;
use App\Http\Controllers\ReportController;
use App\Support\Media;
use App\Support\AssetLabel;
use App\Support\AssetDetails;
use App\Support\AssetListing;
use App\Support\DepartmentAssets;
use App\Support\AssetCode;
use App\Support\AssetQr;
use App\Support\QrSigner;
use App\Support\QrVerification;
use App\Support\AssetTransfer;
use App\Support\Disposals;
use App\Support\DisposalReason;
use App\Support\DisposalForms;
use App\Support\DisposalDocuments;
use App\Support\AssetAgeReview;
use App\Support\Inventory;
use App\Support\Access;
use App\Support\AccountAdmin;
use App\Support\EmployeeDirectory;
use App\Support\Reviewers;
use App\Support\Approval;
use App\Support\TransferReviews;
use App\Support\RequestFilters;
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

if (!function_exists('createNotification')) {
    /**
     * Create a notification for a specific user.
     * type: REQUEST | REPAIR | REPLACEMENT
     * reference_type: request | repair | replacement
     */
    function createNotification(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): void {
        \App\Support\Notifications::create($userId, $title, $message, $type, $referenceId, $referenceType);
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

if (!function_exists('passwordIsBasedOnIdentity')) {
    /**
     * Blocks passwords built out of the person's own details (name words, email
     * address, employee number) - the easiest thing for someone else to guess.
     * Any word of 4+ characters found inside the password is rejected.
     */
    function passwordIsBasedOnIdentity(string $password, array $identityFragments): bool
    {
        $password = Str::lower($password);

        if ($password === '') {
            return false;
        }

        foreach ($identityFragments as $fragment) {
            if (!is_string($fragment) || $fragment === '') {
                continue;
            }

            $words = preg_split('/[^a-z0-9]+/i', Str::lower($fragment)) ?: [];

            foreach ($words as $word) {
                if (Str::length($word) >= 4 && Str::contains($password, $word)) {
                    return true;
                }
            }
        }

        return false;
    }
}

// Handle login form submission
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    if (Auth::attempt($credentials)) {
        $user = Auth::user();

        // A deactivated account cannot sign in at all. Checked after the
        // password is verified - not before - so a wrong password never reveals
        // whether an account exists. (An account deactivated while it already
        // had a session open is signed out by EnforceActiveAccount instead.)
        if (Access::isInactive($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This account has been deactivated. Please contact the Asset Management Office.',
            ])->withInput(['email' => $request->email]);
        }

        $request->session()->regenerate();

        // The Asset Management Office goes to the admin area. Asked through
        // Access, so the sign-in redirect and the /admin/* gate give the same
        // answer. The Facilities role used to be sent here too and was refused
        // at the door, because the department an account sits in was never what
        // granted it the area.
        if (Access::isAssetOffice($user)) {
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

// Landing page that explains what the system does (linked from the welcome page)
Route::get('/learn-more', function () {
    return view('learn-more');
});

// Auth views (simple GET routes so header buttons load the pages)
// Named so Laravel's auth redirects can never fail with "Route [login] not defined".
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

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
        'password' => ['required', 'confirmed', Password::default()],
        'profile_photo' => 'nullable|image|max:2048',
    ]);

    // Look up the employee record on the masterlist.
    //
    // Matched case-insensitively and trimmed: the office writes the number in
    // the masterlist and the applicant types it by hand, so 'emp-1' and
    // 'EMP-1 ' are the same person and must not become two answers.
    $employeeNumber = Str::upper(trim((string) $validated['unit_heads_number']));

    $employee = DB::table('employee_numbers')
        ->whereRaw('UPPER(' . EmployeeDirectory::column('Employee_number') . ') = ?', [$employeeNumber])
        ->first();

    if (!$employee) {
        return back()->withErrors(['unit_heads_number' => 'Employee number not found in the system. Please contact the administrator.'])->withInput();
    }

    // An employee record the office has marked Inactive is not eligible: that
    // person is no longer on the active masterlist (resigned, retired, long
    // leave), so an account cannot be activated against their number.
    if (($employee->status ?? 'Active') !== 'Active') {
        return back()->withErrors(['unit_heads_number' => 'This employee number is not active in the employee masterlist, so an account cannot be activated for it. Please contact the administrator.'])->withInput();
    }

    // Knowing an employee number is not proof of identity. When the office has
    // recorded an official address for this employee, the account has to be
    // activated with that address and no other - which is also what makes the
    // masterlist worth keeping.
    $recordEmail = trim((string) ($employee->Email ?? ''));
    if ($recordEmail !== '' && strcasecmp($recordEmail, trim((string) $validated['email'])) !== 0) {
        return back()->withErrors(['email' => 'The email you entered does not match the official email on your employee record. Please use that address, or contact the administrator.'])->withInput();
    }

    // The password must not be built out of the employee's own details
    if (passwordIsBasedOnIdentity($validated['password'], [
        $employee->Full_Name,
        Str::before($validated['email'], '@'),
        $validated['unit_heads_number'],
    ])) {
        return back()->withErrors([
            'password' => 'Your password must not contain your name, email address, or employee number.',
        ])->withInput();
    }

    // Check if this employee number is already registered
    $existingUser = User::where('employee_numbers_id', $employee->id)->first();
    if ($existingUser) {
        return back()->withErrors(['unit_heads_number' => 'This employee number is already registered. Please use a different employee number or contact the administrator.'])->withInput();
    }

    // Get or verify department
    $departmentId = $employee->Department_id;
    $fullName = $employee->Full_Name;

    // Check if profile photo exists (stored on the media disk)
    $photoPath = null;
    if ($request->hasFile('profile_photo')) {
        $photoPath = Media::storeUploadedFile($request->file('profile_photo'), 'profile_photos');
    }

    // Determine the role for the new account.
    //
    // Only the very first account - the one that has to exist before anybody
    // can use the system at all - becomes an Administrator. Everyone else is an
    // Employee. A role is granted deliberately, by the office, on the Employee
    // Management page: registration used to make the first person to sign up in
    // a department that department's Head, which quietly handed approval
    // authority to whoever happened to register first.
    $role = User::count() === 0 ? 'Admin' : 'Employee';

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

// ============================================================================
// Forgot Password (self-service account recovery)
// Flow: enter university email -> verify account -> send 6-digit code ->
//       validate code (expiry + single use) -> set new password -> return to login
// ============================================================================
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
});

// Step 1: email submitted -> check the account -> generate + email a 6-digit code
Route::post('/forgot-password', function (Request $request) {
    $validated = $request->validate([
        'email' => 'required|email|max:100',
    ]);

    // If the email is not registered, show an error and do not continue recovery.
    $user = User::where('email', $validated['email'])->first();
    if (!$user || ($user->status ?? 'Active') === 'Inactive') {
        return back()
            ->withErrors(['email' => 'No account is registered with that email address. Please use the email you used to register.'])
            ->withInput(['email' => $request->email]);
    }

    // Generate a fresh single-use 6-digit code (previous codes for this email are invalidated)
    $code = (string) random_int(100000, 999999);
    try {
        DB::table('password_resets')->where('email', $user->email)->delete();
        DB::table('password_resets')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'used'       => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Could not store password reset code: ' . $e->getMessage());
        return back()->withErrors(['error' => 'We could not start a password reset right now. If this keeps happening, make sure the database is reachable and the "password_resets" table exists (run: php artisan migrate).']);
    }

    // Send the code to the user's registered email address. A password reset
    // must never report "code sent" over an empty inbox — the report is the
    // only thing the person on the other side can see — so a refused send is
    // reported as a failure and the page stops promising that email.
    $delivered = true;
    try {
        \Illuminate\Support\Facades\Mail::raw(
            "Hello,\n\n" .
            "You requested to reset your NU TRACE password.\n\n" .
            "Your verification code is: " . $code . "\n\n" .
            "This code is valid for 15 minutes and can only be used once.\n" .
            "If you did not request a password reset, you can safely ignore this email.\n\n" .
            "— NU TRACE Asset Management System",
            function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('NU TRACE - Password Reset Verification Code');
            }
        );
    } catch (\Throwable $e) {
        $delivered = false;
        \Illuminate\Support\Facades\Log::warning('Could not send password reset email to ' . $user->email . ': ' . $e->getMessage());

        // Name the setting that is actually missing for the mailer in use. The
        // live site (Railway) blocks outbound SMTP on its Free/Trial/Hobby
        // plans, so production sends through an HTTPS mail API (Resend),
        // configured with RESEND_API_KEY — not MAIL_HOST / MAIL_PASSWORD.
        $mailer      = (string) config('mail.default');
        $appPassword = (string) config('mail.mailers.smtp.password');

        if ($mailer === 'resend' && (string) config('services.resend.key') === '') {
            $hint = 'The mail provider is not configured on this server yet: RESEND_API_KEY is missing. Add it to the hosting environment variables, then click "Resend code".';
        } elseif ($mailer === 'smtp' && ($appPassword === '' || str_contains($appPassword, 'PASTE-YOUR'))) {
            $hint = 'Your Gmail App Password is not set yet. Set MAIL_USERNAME and the 16-character MAIL_PASSWORD (Google Account → Security → 2-Step Verification → App passwords), then click "Resend code".';
        } else {
            $hint = 'The email could not be delivered (' . class_basename($e) . '). Please check the ' . strtoupper($mailer) . ' mail settings on the server, then click "Resend code".';
        }

        $request->session()->flash('mail_error', $hint);

        // Local dev fallback: if no mail server is reachable, still show the code
        // so the flow can be tested. Never displayed outside the local environment.
        if (app()->environment('local')) {
            $request->session()->flash('dev_code', $code);
        }
    }

    $request->session()->put('password_reset_email', $user->email);

    if (! $delivered) {
        // The verify page reads the mail_error notice and asks for the mail
        // settings to be fixed; it must not also claim the mail is on its way.
        return redirect('/forgot-password/verify');
    }

    return redirect('/forgot-password/verify')
        ->with('success', 'A 6-digit verification code has been sent to ' . $user->email . '. It expires in 15 minutes.');
});

// Step 2a: show the code-entry form
Route::get('/forgot-password/verify', function () {
    if (!session('password_reset_email')) {
        return redirect('/forgot-password');
    }
    return view('auth.verify-code', ['email' => session('password_reset_email')]);
});

// Step 2b: validate the code (correct, not expired, not already used)
Route::post('/forgot-password/verify', function (Request $request) {
    $email = session('password_reset_email');
    if (!$email) {
        return redirect('/forgot-password');
    }

    $validated = $request->validate([
        'code' => 'required|digits:6',
    ]);

    $record = DB::table('password_resets')->where('email', $email)->orderByDesc('id')->first();

    if (!$record || !Hash::check($validated['code'], $record->token)) {
        return back()->withErrors(['code' => 'The verification code you entered is incorrect. Please try again.']);
    }

    if ((int) $record->used === 1) {
        return back()->withErrors(['code' => 'This verification code has already been used. Please request a new code.']);
    }

    if (!$record->expires_at || Carbon::parse($record->expires_at)->isPast()) {
        return back()->withErrors(['code' => 'This verification code has expired. Please request a new code.']);
    }

    // Remember which code was validated so the reset step re-checks it before saving
    $request->session()->put('password_reset_code', $validated['code']);

    return redirect('/forgot-password/reset');
});

// Step 3a: show the new-password form
Route::get('/forgot-password/reset', function () {
    if (!session('password_reset_email') || !session('password_reset_code')) {
        return redirect('/forgot-password');
    }
    return view('auth.reset-password', ['email' => session('password_reset_email')]);
});

// Step 3b: validate + confirm the new password, update it, invalidate the code
Route::post('/forgot-password/reset', function (Request $request) {
    $email = session('password_reset_email');
    $code  = session('password_reset_code');
    if (!$email || !$code) {
        return redirect('/forgot-password');
    }

    $validated = $request->validate([
        'password' => ['required', 'confirmed', Password::default()],
    ]);

    $record = DB::table('password_resets')->where('email', $email)->orderByDesc('id')->first();

    $invalid = !$record
        || !Hash::check($code, $record->token)
        || (int) $record->used === 1
        || ($record->expires_at && Carbon::parse($record->expires_at)->isPast());

    if ($invalid) {
        $request->session()->forget(['password_reset_email', 'password_reset_code']);
        return redirect('/forgot-password')
            ->withErrors(['email' => 'Your verification session is no longer valid. Please start the password reset again.']);
    }

    $user = User::where('email', $email)->first();
    if (!$user) {
        $request->session()->forget(['password_reset_email', 'password_reset_code']);
        return redirect('/forgot-password')
            ->withErrors(['email' => 'Your account could not be found. Please contact the administrator.']);
    }

    // The new password must not be built out of the account's own details
    if (passwordIsBasedOnIdentity($validated['password'], [
        $user->display_name,
        Str::before($user->email, '@'),
    ])) {
        return back()->withErrors([
            'password' => 'Your password must not contain your name or email address.',
        ]);
    }

    try {
        DB::transaction(function () use ($user, $validated, $email) {
            // Securely update the password
            $user->password = Hash::make($validated['password']);
            $user->save();

            // Invalidate the code so it cannot be reused
            DB::table('password_resets')->where('email', $email)->delete();

            // Keep an audit trail of the account change
            DB::table('audit_logs')->insert([
                'user_id'             => $user->id,
                'action_type'         => 'UPDATE',
                'action_description'  => 'User reset their password through the forgot-password flow',
                'notes'               => 'Password was changed for account ' . $user->email,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        });
    } catch (\Exception $e) {
        return back()->withErrors(['error' => 'Password reset failed: ' . $e->getMessage()]);
    }

    $request->session()->forget(['password_reset_email', 'password_reset_code']);

    return redirect('/login')
        ->with('success', 'Your password has been changed successfully. You can now sign in with your new password.');
});

/*
|--------------------------------------------------------------------------
| Executive Director transfer review (public, token only)
|--------------------------------------------------------------------------
|
| The Executive Director has no NU Trace account, so these four routes are
| deliberately reachable without a session — and they are the only ones that
| are. Nothing here lists an asset, searches an inventory or takes an
| identifier the visitor could change: the token in the URL is the whole of
| what may be reached, the request it points at is read from the database,
| and the review it opens is one transfer request and nothing else.
|
| The area is absent from RedirectGuestToLanding's PRIVATE_AREAS on purpose,
| so a guest with a link lands on the review rather than the landing page.
|
| CSRF protection, the session cookie and the security headers all apply as
| they do everywhere else in the app.
|
*/
Route::get('/executive-review/{token}', [ExecutiveReviewController::class, 'show'])
    ->name('executive.review');

Route::post('/executive-review/{token}/code', [ExecutiveReviewController::class, 'sendCode'])
    ->name('executive.review.code');

Route::post('/executive-review/{token}/verify', [ExecutiveReviewController::class, 'verify'])
    ->name('executive.review.verify');

Route::post('/executive-review/{token}/decide', [ExecutiveReviewController::class, 'decide'])
    ->name('executive.review.decide');

// Users area -> render users.dashboard and show assigned assets when logged in
// User assets page (My Assets) - show assigned assets for authenticated user
Route::get('/users/assets', function (Request $request) {
    $user = Auth::user();

    $filter = (string) $request->query('filter', 'all');
    $search = trim((string) $request->query('q', ''));

    // What the holder still has. The summary cards count this, not the filtered
    // page, so they do not move when a tab is clicked.
    $holdings = AssetListing::onlyVisible(
        Inventory::excludeRemoved(DB::table('assets'))->where('assets.user_id', $user?->id)
    );

    $totalAssets = (clone $holdings)->count();
    $activeAssets = (clone $holdings)->where('assets.Lifecycle_Status', 'Active')->count();

    $pendingRequests = $user
        ? DB::table('requests')->where('user_id', $user->id)->where('status', 'Pending')->count()
        : 0;

    // Filtering and paging both happen in the database: a tab or a search covers
    // every asset the holder has, not just the page on screen.
    $assignedAssets = AssetListing::paginate(
        (clone $holdings)
            ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
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
                'assets.created_at',
                DB::raw('MAX(asset_files.url) as image_url')
            )
            ->groupBy('assets.id', 'assets.user_id', 'assets.Asset_code', 'assets.Asset_name', 'assets.Category', 'assets.Condition', 'assets.Lifecycle_Status', 'assets.accusion_date', 'assets.asset_location', 'assets.next_maintenance_date', 'assets.qr_code_path', 'assets.created_at'),
        $filter,
        $search,
        $user?->id
    );

    return view('users.asset.asset', [
        'assignedAssets'  => $assignedAssets,
        'totalAssets'     => $totalAssets,
        'activeAssets'    => $activeAssets,
        'pendingRequests' => $pendingRequests,
        'currentFilter'   => $filter,
        'currentSearch'   => $search,
    ]);
});

// User-facing asset detail (only accessible to the assigned user or admins)
Route::get('/users/assets/{id}', function ($id) {
    $user = Auth::user();
    if (!$user) {
        abort(403);
    }

    $asset = AssetDetails::load((int) $id);
    if (!$asset) {
        abort(404);
    }
    if ((int) $asset->user_id !== (int) $user->id && ($user->role ?? '') !== 'Admin') {
        abort(403);
    }

    $query = request()->getQueryString();

    return view('users.asset.show', [
        'asset'   => $asset,
        'details' => AssetDetails::build($asset),
        'backUrl' => '/users/assets' . ($query ? '?' . $query : ''),
    ]);
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

    $assets = Asset::where('user_id', $user->id)->inInventory()->get();
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
                // Dashboard preview only — the full note lives on the request itself.
                'description' => \Illuminate\Support\Str::limit((string) ($r->Note ?? $r->asset_name ?? ''), 100),
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

    // Assets belonging to the department. Archived disposals are out of the
    // inventory, so they no longer count towards the department's holdings.
    $assets = Asset::with('user')
        ->inInventory()
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
                // Dashboard preview only — the full note lives on the request itself.
                'description' => \Illuminate\Support\Str::limit(
                    $r->Note ? $r->Note : ($r->asset_name ? ($r->asset_name . ' — ' . ($r->request_user_name ?? '')) : ($r->request_user_name ?? '')),
                    100
                ),
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

    $filter = (string) $request->query('filter', 'all');
    $search = trim((string) $request->query('q', ''));

    // Everything the department still holds, which is what the summary cards
    // count — not the filtered page.
    $holdings = AssetListing::onlyVisible(
        Inventory::excludeRemoved(DB::table('assets'))
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)
    );

    $totalAssets = (clone $holdings)->count();
    $activeAssets = (clone $holdings)->where('assets.Lifecycle_Status', 'Active')->count();

    $pendingRequests = DB::table('requests')
        ->join('users', 'requests.user_id', '=', 'users.id')
        ->where('users.department_id', $user->department_id)
        ->where('requests.status', 'Pending')
        ->count();

    // "My Personal Assets" is the tab this page has always had, now applied in
    // the database so it searches the whole department rather than one page.
    $assignedAssets = AssetListing::paginate(
        (clone $holdings)
            ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
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
                'assets.created_at',
                'users.department_id',
                DB::raw('MAX(asset_files.url) as image_url')
            )
            ->groupBy('assets.id', 'assets.user_id', 'assets.Asset_code', 'assets.Asset_name', 'assets.Category', 'assets.Condition', 'assets.Lifecycle_Status', 'assets.accusion_date', 'assets.asset_location', 'assets.next_maintenance_date', 'assets.qr_code_path', 'assets.created_at', 'users.department_id'),
        $filter,
        $search,
        $user->id
    );

    return view('department_head.asset.asset', [
        'assignedAssets'  => $assignedAssets,
        'totalAssets'     => $totalAssets,
        'activeAssets'    => $activeAssets,
        'pendingRequests' => $pendingRequests,
        'currentFilter'   => $filter,
        'currentSearch'   => $search,
    ]);
});

// Department Head: view a single asset (must belong to their department)
Route::get('/department-head/assets/{id}', function ($id) {
    $user = Auth::user();
    if (!$user) {
        return redirect('/login');
    }
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    $asset = AssetDetails::load((int) $id);
    if (!$asset) return abort(404);
    if ((int) ($asset->department_id ?? 0) !== (int) $user->department_id) return abort(403);

    $query = request()->getQueryString();

    return view('department_head.asset.show', [
        'asset'   => $asset,
        'details' => AssetDetails::build($asset),
        'backUrl' => '/department-head/assets' . ($query ? '?' . $query : ''),
    ]);
})->where('id', '[0-9]+');

// Department Head: take accountability for an asset (sets asset.user_id to current user)
Route::post('/department-head/assets/{id}/accountable', function (Request $request, $id) {
    $user = Auth::user();
    if (!$user) return redirect('/login');
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    // Only assets still in the department's holdings can change hands.
    $asset = Asset::with('user')->inInventory()->find($id);
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
        // Every metric below counts the inventory the institution still holds:
        // a disposed asset whose record is archived is no longer part of it.
        $acquiredThisMonth = Asset::inInventory()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $acquiredLastMonth = Asset::inInventory()->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        // compute percent change vs last month (positive if more this month)
        if ($acquiredLastMonth === 0) {
            $acquiredChangePercent = $acquiredThisMonth > 0 ? 100 : 0;
        } else {
            $acquiredChangePercent = (int) round((($acquiredThisMonth - $acquiredLastMonth) / $acquiredLastMonth) * 100);
        }

        $activeAssets = Asset::inInventory()->where('Lifecycle_Status', 'Active')->count();
        $forRepairAssets = Asset::inInventory()->where('Lifecycle_Status', 'For Repair')->count();
        // pending requests (case-insensitive)
        $pendingRequests = DB::table('requests')->whereRaw('LOWER(status) = ?', [strtolower('pending')])->count();

        $overviewMetrics = [
            'total_assets' => Asset::inInventory()->count(),
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
            // Sign-ins and sign-outs are shown as a plain notice - any address an
            // older row carries is removed here.
            $desc = \App\Support\AuditLogText::redact($l->notes) ?: '';
            $type = 'Activity';
            $status = null;

            if ($l->asset_id && str_contains(strtolower($l->notes ?? ''), 'registered')) {
                $type = 'Asset Registered';
                $desc = $desc ?: ('Registered asset ' . ($l->asset_code ?? ''));
            } elseif ($l->request_id && str_contains(strtolower($l->notes ?? ''), 'submitted')) {
                $type = 'Request Submitted';
                $desc = $desc ?: ('Request for ' . ($l->asset_code ?? ''));
            } elseif ($l->request_id && str_contains(strtolower($l->notes ?? ''), 'approved')) {
                $type = 'Request Approved';
                $desc = $desc ?: ('Approved ' . ($l->request_type ?? 'request'));
                $status = 'approved';
            } elseif ($l->request_id && str_contains(strtolower($l->notes ?? ''), 'rejected')) {
                $type = 'Request Rejected';
                $desc = $desc ?: ('Rejected ' . ($l->request_type ?? 'request'));
                $status = 'rejected';
            } elseif ($l->user_id && str_contains(strtolower($l->notes ?? ''), 'user registered')) {
                $type = 'New User Registered';
                $desc = $desc ?: ('New user: ' . ($l->user_name ?? ''));
            } elseif (str_contains(strtolower($l->notes ?? ''), 'logged in') || str_contains(strtolower($l->notes ?? ''), 'logged out')) {
                $type = 'Sign In / Out';
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

// API: lookup asset by scanned code (used by admin scanner modal)
//
// What arrives here is whatever the camera decoded, and since stickers are
// signed that is a payload - NUT2-<key>-<sealed body>-<signature> for a current
// sticker, NUT1-<code>-<key>-<signature> for one printed before the code was
// sealed - not a bare code. Every shape is answered, but not alike: a signed
// payload is verified (and a sealed body opened) before any asset is reported,
// a plain code is reported as the unsigned legacy sticker it is, and an altered
// or revoked sticker is refused outright rather than dressed up with genuine
// asset details.
Route::get('/admin/assets/scan', function (Request $request) {
    $scanned = trim((string) $request->query('code', ''));

    if ($scanned === '') {
        return response()->json(['success' => false, 'message' => 'code missing'], 422);
    }

    $verification = QrVerification::verify($scanned, Auth::user());

    if (in_array($verification['status'], QrVerification::BLOCKING, true)) {
        return response()->json([
            'success'      => false,
            'message'      => $verification['detail'],
            'verification' => $verification,
        ], 422);
    }

    if ($verification['asset'] === null) {
        return response()->json([
            'success'      => false,
            'message'      => $verification['status'] === QrVerification::UNKNOWN_ASSET
                ? 'Asset not found'
                : $verification['detail'],
            'verification' => $verification,
        ], 404);
    }

    return response()->json([
        'success'      => true,
        'data'         => $verification['asset'],
        'verification' => $verification,
    ]);
})->middleware('auth');

// API: verify one scanned sticker.
//
// Used by the screens that scan and need to know what they are looking at
// without pulling asset details (pullout, transfer, the request forms). Every
// outcome answers 200 with its own status, so the interface shows a state
// rather than guessing one from a status code; only a missing payload is an
// error. "Verification unavailable" is its own status on purpose - a server
// without keys must never make a genuine sticker look fake.
Route::get('/qr/verify', function (Request $request) {
    $scanned = trim((string) $request->query('payload', (string) $request->query('code', '')));

    if ($scanned === '') {
        return response()->json(['message' => 'payload missing'], 422);
    }

    return response()->json(QrVerification::verify($scanned, Auth::user()));
})->middleware('auth');

// API: the verifying half of the QR keyring.
//
// The mobile app scans stickers without any Laravel session, so it cannot call
// /qr/verify; it fetches these public keys once and checks signatures on the
// device instead. Only public halves are ever returned - a seed or anything
// that could sign stays on the server (see QrSigner::publicKeyring). Public
// keys are not sensitive, which is why this route needs no session; a
// deployment with no keyring answers `configured: false` so the app reports
// "verification unavailable" rather than guessing at a sticker's authenticity.
Route::get('/qr/public-keys', function () {
    return response()->json([
        'configured' => QrSigner::keys() !== [],
        'keys' => QrSigner::publicKeyring(),
    ]);
});

// API: open a sealed sticker for the phone app.
//
// Version 2 stickers carry the asset code sealed, so the phone cannot read it
// by itself the way it reads a printed v1 payload; it checks the signature on
// the device (public keys) and asks here for the code behind the blob. Only
// the identifier and the sticker's verdict come back - never asset details,
// which the phone still resolves and scopes for itself.
//
// This is the one place a sticker's text can be turned into a code without a
// session. It is not what stops a photocopy (nothing can): a printed copy of
// the same blob resolves here just as well, which is why the same scan is
// fingerprinted and checked for a second source. (No throttle middleware here:
// this deployment's cache store is the database and has no `cache` table, so a
// rate limiter would answer 500 instead of the sticker's verdict.)
Route::post('/qr/resolve', function (Request $request) {
    $scanned = trim((string) $request->input('payload', ''));

    if ($scanned === '') {
        return response()->json(['message' => 'payload missing'], 422);
    }

    $verification = QrVerification::verify($scanned, null);

    return response()->json([
        'status'    => $verification['status'],
        'label'     => $verification['label'],
        'tone'      => $verification['tone'],
        'detail'    => $verification['detail'],
        'signed'    => $verification['signed'],
        'sealed'    => $verification['sealed'],
        'key_id'    => $verification['key_id'],
        'code'      => $verification['code'],
        'duplicate' => $verification['duplicate'],
    ]);
});

// API: the signed payload for asset codes, so the screens that draw a QR in the
// browser (the registry form and its QR modal) encode exactly what the stored
// sticker encodes. Admin-only: this is the one place that mints a signature for
// a code that does not exist yet, and registering codes is the office's job.
Route::get('/admin/assets/qr-payload', function (Request $request) {
    $user = Auth::user();

    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['message' => 'Not allowed'], 403);
    }

    $codes = AssetCode::parseList((string) $request->query('codes', (string) $request->query('code', '')));

    if (empty($codes)) {
        return response()->json(['message' => 'codes missing'], 422);
    }

    return response()->json([
        'signed'   => QrSigner::isConfigured(),
        'payloads' => AssetQr::payloadsFor($codes),
    ]);
})->middleware('auth');

// Revoke or reissue one asset's sticker. Admin-only: this is the Asset
// Management Office saying "that tag is no longer ours", which is also what a
// scan of it will then report.
Route::post('/admin/assets/{id}/qr/revoke', function (Request $request, $id) {
    $user = Auth::user();

    if (!$user || ($user->role ?? '') !== 'Admin') {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => 'Not allowed'], 403)
            : abort(403);
    }

    $asset = DB::table('assets')->where('id', $id)->first();

    if (!$asset) {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => 'Asset not found'], 404)
            : abort(404);
    }

    AssetQr::revoke((int) $asset->id, (int) $user->id);

    return $request->expectsJson()
        ? response()->json(['success' => true, 'revoked' => true])
        : back()->with('qr_status', 'Sticker for ' . ($asset->Asset_code ?? 'asset') . ' was revoked. Scans of it will report the revocation.');
})->middleware('auth');

Route::post('/admin/assets/{id}/qr/reissue', function (Request $request, $id) {
    $user = Auth::user();

    if (!$user || ($user->role ?? '') !== 'Admin') {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => 'Not allowed'], 403)
            : abort(403);
    }

    $asset = DB::table('assets')->where('id', $id)->first();

    if (!$asset) {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => 'Asset not found'], 404)
            : abort(404);
    }

    $path = AssetQr::reissue((int) $asset->id);

    $message = $path === null
        ? 'Revocation cleared, but a fresh sticker image could not be produced. Print the sticker from the label sheet.'
        : 'Sticker reissued for ' . ($asset->Asset_code ?? 'asset') . '. It verifies again.';

    return $request->expectsJson()
        ? response()->json(['success' => true, 'revoked' => false, 'stored' => $path !== null])
        : back()->with('qr_status', $message);
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

// Admin assets page — one summary card per department, each linking to that
// department's own paged asset list.
//
// The page never lists assets itself, so it must not read them either: it used
// to load every asset in the inventory and count them in PHP, which is a full
// table scan in memory to draw nine numbers per department. The counts are now
// one GROUP BY — the page costs the same whether the registry holds 60 assets
// or 60,000, and the cards are unchanged. (The whole registry as a file is what
// "Download Inventory" is for.)
Route::get('/admin/assets', function () {
    // Every count on the page in one query: assets per department, split by
    // lifecycle status. An asset belongs to its own department first — an
    // imported asset can name one while its accountable person has no account
    // yet — and to its holder's department otherwise. Assets with neither are
    // left out, exactly as the PHP grouping left them out.
    $countsQuery = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->selectRaw('COALESCE(assets.department_id, users.department_id) AS dept_id')
        ->selectRaw('assets."Lifecycle_Status" AS status')
        ->selectRaw('COUNT(*) AS total')
        ->whereRaw('COALESCE(assets.department_id, users.department_id) IS NOT NULL')
        ->groupByRaw('COALESCE(assets.department_id, users.department_id), assets."Lifecycle_Status"');

    // A disposed asset leaves the inventory; it must not be counted here either.
    Inventory::excludeRemoved($countsQuery, 'assets');

    $byDepartment = [];

    foreach ($countsQuery->get() as $row) {
        $deptId = (int) $row->dept_id;

        // Same statuses the cards group by. Anything unknown keeps its own
        // key, so a status added later still counts toward the department's
        // total instead of vanishing from it.
        $key = match ((string) $row->status) {
            'Acquired'        => 'acquired',
            'Active'          => 'active',
            'For Checking'    => 'for_checking',
            'For Repair'      => 'for_repair',
            'For Replacement' => 'for_replacement',
            'Pullout'         => 'pulled_out',
            'Disposal',
            'Disposed'        => 'disposed',
            default           => strtolower(str_replace(' ', '_', (string) $row->status)),
        };

        $byDepartment[$deptId]['counts'][$key] = ($byDepartment[$deptId]['counts'][$key] ?? 0) + (int) $row->total;
        $byDepartment[$deptId]['total'] = ($byDepartment[$deptId]['total'] ?? 0) + (int) $row->total;
    }

    // Heads in one query too, first account per department (the old per-card
    // lookup took the lowest id as well).
    $heads = DB::table('users')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('users.role', 'Department Head')
        ->orderBy('users.id')
        ->select('users.id', 'users.email', 'users.department_id', 'employee_numbers.Full_Name as full_name')
        ->get()
        ->unique('department_id')
        ->keyBy('department_id');

    $departments = DB::table('departments')
        ->orderBy('Name')
        ->get()
        ->map(function ($dept) use ($byDepartment, $heads) {
            $deptId = (int) $dept->id;
            $counts = $byDepartment[$deptId]['counts'] ?? [];
            $head   = $heads->get($deptId);

            return (object) [
                'id'           => $deptId,
                'name'         => $dept->Name,
                'head_id'      => $head->id ?? null,
                'head_email'   => $head->email ?? '',
                'head'         => $head->full_name ?? '',
                'total_assets' => $byDepartment[$deptId]['total'] ?? 0,
                'counts'       => [
                    'acquired'        => $counts['acquired'] ?? 0,
                    'active'          => $counts['active'] ?? 0,
                    'for_checking'    => $counts['for_checking'] ?? 0,
                    'for_repair'      => $counts['for_repair'] ?? 0,
                    'for_replacement' => $counts['for_replacement'] ?? 0,
                    'pulled_out'      => $counts['pulled_out'] ?? 0,
                    'disposed'        => $counts['disposed'] ?? 0,
                ],
            ];
        })
        ->values();

    return view('admin.assets.asset', ['departments' => $departments]);
});

// Admin audit logs page
Route::get('/admin/audit-logs', function (Request $request) {
    $filter = $request->query('filter', 'all');   // all | asset | request | auth
    $search = trim((string) $request->query('q', ''));
    $date   = $request->query('date', '');

    try {
        $query = DB::table('audit_logs')
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
            );

        // Tab filters
        if ($filter === 'asset') {
            $query->where(function ($q) {
                $q->whereNotNull('audit_logs.asset_id')
                  ->orWhere('audit_logs.action_type', 'ilike', '%ASSET%')
                  ->orWhere('audit_logs.notes', 'ilike', '%asset%');
            });
        } elseif ($filter === 'request') {
            $query->where(function ($q) {
                $q->whereNotNull('audit_logs.request_id')
                  ->orWhere('audit_logs.action_type', 'ilike', '%REQUEST%')
                  ->orWhere('audit_logs.notes', 'ilike', '%request%');
            });
        } elseif ($filter === 'auth') {
            // Matches both the current wording ("User logged in" / "User logged
            // out") and rows written before it.
            $query->where(function ($q) {
                $q->where('audit_logs.notes', 'ilike', '%login%')
                  ->orWhere('audit_logs.notes', 'ilike', '%logout%')
                  ->orWhere('audit_logs.notes', 'ilike', '%logged in%')
                  ->orWhere('audit_logs.notes', 'ilike', '%logged out%')
                  ->orWhere('audit_logs.notes', 'ilike', '%registered%')
                  ->orWhere('audit_logs.action_type', 'ilike', '%AUTH%')
                  ->orWhere('audit_logs.action_type', 'ilike', '%LOGIN%')
                  ->orWhere('audit_logs.action_type', 'ilike', '%LOGOUT%');
            });
        }

        // Search
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('audit_logs.notes', 'ilike', $term)
                  ->orWhere('audit_logs.action_description', 'ilike', $term)
                  ->orWhere('employee_numbers.Full_Name', 'ilike', $term)
                  ->orWhere('assets.Asset_name', 'ilike', $term)
                  ->orWhere('assets.Asset_code', 'ilike', $term);
            });
        }

        // Date
        if ($date !== '') {
            $query->whereDate('audit_logs.created_at', $date);
        }

        $logs = $query->orderByDesc('audit_logs.created_at')
            ->paginate(15)
            ->withQueryString();

    } catch (\Exception $e) {
        $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
    }

    // The admin sees who did what and when. Any address left over from an older
    // log row is stripped here, before it can reach the page or the modal.
    $logs->getCollection()->transform(function ($log) {
        $log->notes = \App\Support\AuditLogText::redact($log->notes ?? null);
        $log->action_description = \App\Support\AuditLogText::redact($log->action_description ?? null);

        return $log;
    });

    $totalLogs   = DB::table('audit_logs')->count();
    $todayLogs   = DB::table('audit_logs')->whereDate('created_at', now()->toDateString())->count();
    $weekLogs    = DB::table('audit_logs')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
    $activeUsers = DB::table('users')->count();

    return view('admin.audit-log.audit_logs', compact(
        'logs', 'totalLogs', 'todayLogs', 'weekLogs', 'activeUsers',
        'filter', 'search', 'date'
    ));
})->middleware('auth');

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
                // No addresses in the export either.
                \App\Support\AuditLogText::redact($log->notes ?? $log->action_description) ?? '—',
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
// NOTE: Disposal assets are excluded — they have been thrown away and will never be maintained again.
Route::get('/admin/api/maintenance-alerts', function () {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Get all assets where next_maintenance_date is today or in the past
    $maintenanceAlerts = Inventory::excludeRemoved(DB::table('assets'))
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->whereNotNull('assets.next_maintenance_date')
        ->whereDate('assets.next_maintenance_date', '<=', now()->toDateString())
        ->whereNotIn('assets.Lifecycle_Status', ['Disposal'])
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
// NOTE: Disposal assets are excluded — their lifespan no longer matters once thrown away.
Route::get('/admin/api/lifespan-alerts', function () {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Get all assets where expiration_date <= today (expired assets needing evaluation)
    // Shows both assets in "For Checking" status and assets that expired but haven't been auto-transitioned yet
    $lifespanAlerts = Inventory::excludeRemoved(DB::table('assets'))
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->whereNotNull('assets.expiration_date')
        ->whereDate('assets.expiration_date', '<=', now()->toDateString())
        ->whereNotIn('assets.Lifecycle_Status', ['Disposal'])
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
            // Disposal assets are excluded — they are thrown away and never evaluated again.
            $expiredAssets = DB::table('assets')
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->whereNotIn('Lifecycle_Status', ['Disposal'])
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
            // Disposal assets are excluded — no maintenance is ever performed on disposed assets.
            $maintenanceOverdueAssets = DB::table('assets')
                ->whereNotNull('next_maintenance_date')
                ->whereDate('next_maintenance_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->whereNotIn('Lifecycle_Status', ['Disposal'])
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

// Admin API: Evaluate asset (For Checking, or Pullout + expired lifespan actions)
Route::post('/admin/api/assets/{id}/evaluate', function ($id, \Illuminate\Http\Request $request) {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    $action = $request->input('action');
    $evaluationNotes = $request->input('evaluation_notes', '');
    $status = $asset->Lifecycle_Status ?? '';

    // Allow:
    // - For Checking → all normal evaluation actions
    // - Pullout → only extend_lifespan_pullout and proceed_disposal
    $pulloutOnlyActions = ['extend_lifespan_pullout', 'proceed_disposal'];

    if ($status === 'Pullout') {
        if (!in_array($action, $pulloutOnlyActions, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid action for Pullout asset. Only extend lifespan or disposal is allowed.',
            ], 422);
        }
    } elseif ($status !== 'For Checking') {
        return response()->json([
            'success' => false,
            'message' => 'Asset is not in "For Checking" status',
        ], 422);
    }

    try {
        DB::transaction(function () use ($id, $asset, $action, $evaluationNotes, $request, $status) {
            $adminEmail = Auth::user()?->email ?? 'Admin';

            switch ($action) {
                case 'return_active':
                    $extensionMonths = (int) $request->input('extension_months', 0);
                    $updateData = [
                        'Lifecycle_Status' => 'Active',
                        'updated_at' => now(),
                    ];
                    if ($extensionMonths > 0 && !empty($asset->expiration_date)) {
                        $updateData['expiration_date'] = \Carbon\Carbon::parse($asset->expiration_date)
                            ->addMonths($extensionMonths)
                            ->toDateString();
                    }
                    DB::table('assets')->where('id', $id)->update($updateData);
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'UPDATE',
                        'action_description' => 'Asset returned to Active status after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: RETURN TO ACTIVE'
                            . ($extensionMonths > 0 ? " (Extended by $extensionMonths months)" : '')
                            . "\nEvaluation Notes: " . ($evaluationNotes ?: 'Asset condition satisfactory'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'send_repair':
                    DB::table('assets')->where('id', $id)->update([
                        'Lifecycle_Status' => 'For Repair',
                        'updated_at' => now(),
                    ]);
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
                    DB::table('assets')->where('id', $id)->increment('repair_counts');
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'REPAIR',
                        'action_description' => 'Asset sent for repair after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: SEND FOR REPAIR\nIssues: ' . ($evaluationNotes ?: 'Maintenance required'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'recommend_replacement':
                    DB::table('assets')->where('id', $id)->update([
                        'Lifecycle_Status' => 'For Replacement',
                        'updated_at' => now(),
                    ]);
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
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'REPLACEMENT',
                        'action_description' => 'Asset recommended for replacement after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: RECOMMEND REPLACEMENT\nReason: ' . ($evaluationNotes ?: 'Beyond economic repair'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'proceed_disposal':
                    DB::table('assets')->where('id', $id)->update([
                        'Lifecycle_Status' => 'Disposal',
                        'updated_at' => now(),
                    ]);
                    DB::table('disposals')->insertOrIgnore([
                        'Asset_id' => $id,
                        'Approve_by' => $adminEmail,
                        'Description' => 'Asset disposal after lifespan evaluation',
                        'disposal_reason' => 'End of Lifespan',
                        'disposal_date' => now()->toDateString(),
                        'notes' => 'Disposed after lifespan evaluation. ' . ($evaluationNotes ?: 'No longer serviceable'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'DISPOSAL',
                        'action_description' => 'Asset disposed after lifespan evaluation',
                        'notes' => 'Lifespan Evaluation Decision: PROCEED WITH DISPOSAL\nReason: ' . ($evaluationNotes ?: 'End of life'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'extend_lifespan_pullout':
                    // Pullout only — status stays Pullout
                    if ($status !== 'Pullout') {
                        throw new \Exception('extend_lifespan_pullout is only allowed for Pullout assets');
                    }
                    $extensionMonths = (int) $request->input('extension_months', 0);
                    if ($extensionMonths < 1) {
                        throw new \Exception('Extension months must be at least 1');
                    }
                    $currentExpiration = !empty($asset->expiration_date)
                        ? \Carbon\Carbon::parse($asset->expiration_date)
                        : now();
                    $newExpiration = $currentExpiration->copy()->addMonths($extensionMonths);

                    DB::table('assets')->where('id', $id)->update([
                        'expiration_date' => $newExpiration->toDateString(),
                        // do NOT change Lifecycle_Status
                        'updated_at' => now(),
                    ]);

                    DB::table('audit_logs')->insert([
                        'asset_id' => $id,
                        'user_id' => Auth::id(),
                        'action_type' => 'UPDATE',
                        'action_description' => 'Lifespan extended while in Pullout status',
                        'notes' => 'Extended by ' . $extensionMonths . ' months. New expiration: '
                            . $newExpiration->toDateString()
                            . "\n" . ($evaluationNotes ?: 'Lifespan extended while in pullout'),
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
            'proceed_disposal' => 'Asset marked for disposal',
            'extend_lifespan_pullout' => 'Lifespan extended. Asset remains in Pullout.',
        ];

        return response()->json([
            'success' => true,
            'message' => $actionMessages[$action] ?? 'Asset evaluated successfully',
            'asset_id' => $id,
            'action' => $action,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Asset evaluation error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to evaluate asset: ' . $e->getMessage(),
        ], 500);
    }
});

// Admin API: Extend asset lifespan directly from the dashboard lifespan panel.
// Works for any asset that has an expiration_date and is NOT Disposal.
// If the asset was auto-transitioned to "For Checking" because its lifespan expired,
// and the new expiration date is now in the future, it is restored to Active.
Route::post('/admin/api/assets/{id}/extend-lifespan', function ($id, \Illuminate\Http\Request $request) {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $asset = DB::table('assets')->where('id', $id)->first();
    if (!$asset) {
        return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
    }

    if (($asset->Lifecycle_Status ?? '') === 'Disposal') {
        return response()->json(['success' => false, 'message' => 'Disposed assets cannot have their lifespan extended.'], 422);
    }

    $validated = $request->validate([
        'extension_months' => 'required|integer|min:1|max:120',
        'notes'            => 'nullable|string|max:1000',
    ]);

    $extensionMonths = (int) $validated['extension_months'];
    $notes = $validated['notes'] ?? '';

    try {
        $result = DB::transaction(function () use ($id, $asset, $extensionMonths, $notes) {
            $baseDate = !empty($asset->expiration_date)
                ? \Carbon\Carbon::parse($asset->expiration_date)
                : now();
            // Always extend from the later of (current expiration, today) so repeated
            // extensions after expiry accumulate from today instead of the past date.
            if ($baseDate->isPast()) {
                $baseDate = \Carbon\Carbon::now();
            }
            $newExpiration = $baseDate->copy()->addMonths($extensionMonths);

            $currentStatus = $asset->Lifecycle_Status ?? 'Active';
            $newStatus = $currentStatus;
            if ($currentStatus === 'For Checking' && $newExpiration->isFuture()) {
                // Restore to Active — the only reason this asset was flagged was its expired lifespan.
                $newStatus = 'Active';
            }

            DB::table('assets')->where('id', $id)->update([
                'expiration_date'  => $newExpiration->toDateString(),
                'Lifecycle_Status' => $newStatus,
                'updated_at'       => now(),
            ]);

            DB::table('audit_logs')->insert([
                'asset_id'           => $id,
                'user_id'            => Auth::id(),
                'action_type'        => 'UPDATE',
                'action_description' => 'Asset lifespan extended',
                'notes'              => 'Lifespan extended by ' . $extensionMonths . ' months. New expiration date: '
                    . $newExpiration->toDateString()
                    . ($newStatus !== $currentStatus ? '. Status restored to ' . $newStatus . '.' : '')
                    . ($notes ? "\nNotes: " . $notes : ''),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            return [
                'expiration_date'  => $newExpiration->toDateString(),
                'lifecycle_status' => $newStatus,
                'status_restored'  => $newStatus !== $currentStatus,
            ];
        });

        return response()->json([
            'success'          => true,
            'message'          => 'Lifespan extended by ' . $extensionMonths . ' months. New expiration: '
                . $result['expiration_date']
                . ($result['status_restored'] ? ' (asset returned to Active)' : ''),
            'asset_id'         => $id,
            'expiration_date'  => $result['expiration_date'],
            'lifecycle_status' => $result['lifecycle_status'],
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['success' => false, 'message' => $e->validator->errors()->first()], 422);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Lifespan extension error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to extend lifespan: ' . $e->getMessage()], 500);
    }
});

// Admin API: Request notifications feed for the admin notification bell.
// Shows recent requests submitted by users (employees / department heads),
// pending ones first, so the admin gets notified when someone submits a request.
Route::get('/admin/api/request-notifications', function () {
    $user = Auth::user();
    if (!$user || ($user->role ?? '') !== 'Admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $limit = min((int) request()->query('limit', 15), 50);

    $requests = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->select(
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.Note',
            'requests.created_at',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
            'employee_numbers.Full_Name as submitted_by'
        )
        ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN 0 ELSE 1 END ASC', ['pending'])
        ->orderByDesc('requests.created_at')
        ->limit($limit)
        ->get()
        ->map(function ($r) {
            return [
                'id'           => $r->id,
                'request_type' => $r->request_type,
                'status'       => $r->status,
                'note'         => $r->Note,
                'asset_name'   => $r->asset_name ?? 'General request',
                'asset_code'   => $r->asset_code,
                'submitted_by' => $r->submitted_by ?? 'Unknown',
                'time_ago'     => \Carbon\Carbon::parse($r->created_at)->diffForHumans(),
            ];
        });

    $pendingCount = DB::table('requests')
        ->whereRaw('LOWER(status) = ?', ['pending'])
        ->count();

    return response()->json([
        'success'       => true,
        'count'         => $pendingCount,
        'requests'      => $requests,
    ]);
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

    // The asset's photo. Read here because the view takes it off the model, and
    // photos live in asset_files rather than in a column of their own. Newest
    // first, so a photo uploaded from the edit form is the one on display.
    $asset->image_url = Media::url(
        DB::table('asset_files')->where('Asset_id', $asset->id)->orderByDesc('Asset_file_ID')->value('url')
    );

    return view('admin.assets.show', [
        'asset'       => $asset,
        'departments' => \App\Support\AssetEditing::departments(),
        'people'      => \App\Support\AssetEditing::people(),
        'conditions'  => \App\Support\AssetEditing::CONDITIONS,
        'completions' => \App\Support\AssetEditing::COMPLETION_STATUSES,
        'categories'  => \App\Support\Import\AssetImport::CATEGORIES,
    ]);
})->where('id', '[0-9]+');

// Correcting an asset's record — the Edit action on the asset page and in the
// Asset Scanner. The asset code is deliberately not editable here: it is printed
// on a sticker and inside that sticker's signed payload, so changing it is a
// reissue, not an edit.
Route::post('/admin/assets/{id}/details', function (Request $request, $id) {
    // The photo never appears in $request->all(); the file itself is handed to
    // the editor alongside the text fields so one call saves the whole record.
    $result = \App\Support\AssetEditing::update((int) $id, array_merge($request->all(), [
        'asset_photo' => $request->file('asset_photo'),
    ]), Auth::user());

    if (! ($result['ok'] ?? false)) {
        return back()->withErrors($result['errors'] ?? ['Asset_name' => 'That change could not be saved.'])->withInput();
    }

    return redirect('/admin/assets/' . $id)->with('success', $result['message'] ?? 'Asset record updated.');
})->where('id', '[0-9]+')->middleware('auth');


// Print sheet for department assets: one sticker for a single asset, or one per
// asset the filters match. Both the page and this sheet filter through
// DepartmentAssets, so the printout is exactly what was selected.
//
// The default layout is one sticker per page at the configured roll size —
// 50 × 25mm for the Zebra ZD230, see config/asset_label.php; ?layout=sheet puts
// the same stickers on A4 for an ordinary printer.
Route::get('/admin/assets/department/{departmentId}/labels', function (Request $request, $departmentId) {
    if (! Auth::check()) {
        return redirect('/login');
    }

    $department = DB::table('departments')->where('id', $departmentId)->first();
    $departmentName = $department->Name ?? 'Department';

    $filters = [
        'status'   => $request->query('status', 'all'),
        'search'   => trim((string) $request->query('search', '')),
        'category' => $request->query('category', 'all'),
        'location' => $request->query('location', 'all'),
        'code'     => trim((string) $request->query('asset', '')),
    ];

    $rows = DepartmentAssets::applyFilters(DepartmentAssets::baseQuery($departmentId), $filters)
        ->orderBy('assets.Asset_name')
        ->get();

    $labels = $rows->map(fn ($row) => AssetLabel::fromRow($row))->values()->all();

    return view('admin.assets.labels', [
        'labels'       => $labels,
        'single'       => count($labels) === 1,
        'layout'       => count($labels) !== 1 && $request->query('layout') === 'sheet' ? 'sheet' : 'roll',
        'subtitle'     => DepartmentAssets::describeFilters($departmentName, $filters, count($labels)),
        'emptyMessage' => 'No asset in '.$departmentName.' matches the selected filters, so there is nothing to print.',
    ]);
})->middleware('auth');

// Label sheet for values straight off a form (asset registry, replacement),
// where the asset may not have a row yet. Renders the same sticker.
Route::get('/admin/assets/label-preview', function (Request $request) {
    if (! Auth::check()) {
        return redirect('/login');
    }

    $data = $request->validate([
        'code'     => 'nullable|string|max:64',
        'name'     => 'nullable|string|max:120',
        'location' => 'nullable|string|max:120',
        'category' => 'nullable|string|max:120',
        'acquired' => 'nullable|string|max:40',
    ]);

    if (trim((string) ($data['code'] ?? '')) === '') {
        return view('admin.assets.labels', [
            'labels'       => [],
            'single'       => false,
            'layout'       => 'roll',
            'subtitle'     => '',
            'emptyMessage' => 'No asset code was supplied, so there is nothing to print.',
        ]);
    }

    return view('admin.assets.labels', [
        'labels'       => [AssetLabel::fromInput($data)],
        'single'       => true,
        'layout'       => 'roll',
        'subtitle'     => 'Preview from the form (not saved yet)',
        'emptyMessage' => 'No asset code was supplied, so there is nothing to print.',
    ]);
})->middleware('auth');

// Label sheet for saved assets named by code — the registry prints the batch it
// just registered this way. Same sticker as every other printout.
Route::get('/admin/assets/labels', function (Request $request) {
    if (! Auth::check()) {
        return redirect('/login');
    }

    $codes = collect(explode(',', (string) $request->query('codes', '')))
        ->map(fn ($code) => trim($code))
        ->filter()
        ->unique()
        ->take(200) // a bigger job than this is a mistake, not a label sheet
        ->values();

    $rows = $codes->isEmpty()
        ? collect()
        : Inventory::excludeRemoved(DB::table('assets'))
            ->whereIn('assets.Asset_code', $codes->all())
            ->select(
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'assets.asset_location',
                'assets.accusion_date',
                'assets.qr_code_path',
                // Reprint the sticker's existing signed payload, not a new one.
                'assets.qr_payload'
            )
            ->orderBy('assets.Asset_name')
            ->get();

    $labels = $rows->map(fn ($row) => AssetLabel::fromRow($row))->values()->all();
    $count = count($labels);

    return view('admin.assets.labels', [
        'labels'       => $labels,
        'single'       => $count === 1,
        'layout'       => $count !== 1 && $request->query('layout') === 'sheet' ? 'sheet' : 'roll',
        'subtitle'     => $count.' '.($count === 1 ? 'label' : 'labels').' · registered assets',
        'emptyMessage' => 'None of the selected assets could be found, so there is nothing to print.',
    ]);
})->middleware('auth');

// Printing settings for the label sheet (panel recommendation: sticker
// dimensions, code size, page layout, asset selection).
//
// Web only: the mobile app scans stickers, it never prints them. The values are
// kept in the office's own browser session, so a roll measured at one
// workstation does not silently change what every other administrator prints,
// and sizes that cannot carry a scannable code are clamped with a warning
// instead of printed unreadable. Asset selection stays where it already is: the
// filters on the department page and the codes a sheet is opened with.
Route::post('/admin/assets/labels/settings', function (Request $request) {
    if (! Access::isAssetOffice(Auth::user())) {
        abort(403);
    }

    if ($request->boolean('reset')) {
        AssetLabel::resetSettings();

        $result = [
            'ok'       => true,
            'message'  => 'Print settings reset to the configured label stock.',
            'warnings' => [],
        ];
    } else {
        $result = AssetLabel::saveSettings($request->only(['width_mm', 'height_mm', 'qr_mm']));
    }

    // Back to the sheet that was open, never to some other origin: only paths
    // inside the label pages are accepted.
    $target = (string) $request->input('return_to', '');

    if (! str_starts_with($target, '/admin/assets/') || str_starts_with($target, '//')) {
        $target = '/admin/assets/registry';
    }

    $layout = (string) $request->input('layout', '');

    if (in_array($layout, ['roll', 'sheet'], true)) {
        [$path, $query] = array_pad(explode('?', $target, 2), 2, '');
        parse_str($query, $params);
        $params['layout'] = $layout;

        $target = $path . '?' . http_build_query($params);
    }

    return redirect($target)->with('label_settings', $result);
})->middleware('auth');

// Admin department assets page
Route::get('/admin/assets/department/{departmentId?}', function (Request $request, $departmentId = null) {
    $departmentId = $departmentId ?? $request->query('department_id');
    $department = DB::table('departments')->where('id', $departmentId)->first();
    $departmentName = $department->Name ?? 'Department';

    $filters = [
        'status'   => $request->query('status', 'all'),   // all | active | acquired | for_repair | pulled_out | disposed
        'search'   => trim((string) $request->query('search', '')),
        'category' => $request->query('category', 'all'),
        'location' => $request->query('location', 'all'),
    ];

    // Filters run in the database, before pagination: one count, one page of
    // rows, and the same selection the label sheet prints.
    $paginator = DepartmentAssets::applyFilters(
        DepartmentAssets::baseQuery($departmentId),
        $filters
    )->orderBy('assets.Asset_name')->paginate(10)->withQueryString();

    // Normalize for the Blade
    $statusMap = [
        'Active'     => 'active',
        'Acquired'   => 'acquired',
        'For Repair' => 'for_repair',
        'Pullout'    => 'pulled_out',
        'Disposal'   => 'disposed',
    ];

    $assets = $paginator->getCollection()->map(function ($a) use ($statusMap) {
        $a->status = $statusMap[$a->status_raw] ?? strtolower(str_replace(' ', '_', $a->status_raw ?? ''));
        $a->date_acquired = $a->date_acquired ? \Carbon\Carbon::parse($a->date_acquired)->format('M d, Y') : '—';

        return $a;
    });

    $paginator->setCollection($assets);

    return view('admin.assets.department_asset', [
        'assets'          => $paginator,
        'departmentName'  => $departmentName,
        'departmentId'    => $departmentId,
        'categoryOptions' => DepartmentAssets::categoryOptions($departmentId),
        'locationOptions' => DepartmentAssets::locationOptions($departmentId),
        'currentStatus'   => $filters['status'],
        'currentSearch'   => $filters['search'],
        'currentCategory' => $filters['category'],
        'currentLocation' => $filters['location'],
        'hasFilters'      => DepartmentAssets::hasFilters($filters),
    ]);
})->middleware('auth');

// Admin asset registry page
Route::get('/admin/assets/registry', function () {
    $bulkQrLabels = session('bulk_qr_labels', []);
    $bulkRegisteredCount = session('bulk_registered_count', 0);

    return view('admin.assets.asset_registry', compact('bulkQrLabels', 'bulkRegisteredCount'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Existing asset import
//
// The office's Fixed Asset Registry is checked, corrected and imported in
// windows so a large workbook never has to fit in one request. The page is at
// /admin/assets/import; every step below refuses a batch it cannot find and
// re-checks the Admin area's access on the server.
//
// The page route is registered before the numeric /admin/assets/{id} routes for
// the reader's sake, although `import` can never match `[0-9]+`.
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/assets/import', [AssetImportController::class, 'index'])->name('admin.assets.import');
    Route::get('/admin/assets/import/template', [AssetImportController::class, 'template'])->name('admin.assets.import.template');
    Route::post('/admin/assets/import', [AssetImportController::class, 'upload'])->name('admin.assets.import.upload');
    Route::post('/admin/assets/import/part', [AssetImportController::class, 'part'])->name('admin.assets.import.part');

    Route::post('/admin/assets/import/{batch}/mapping', [AssetImportController::class, 'mapping'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/step', [AssetImportController::class, 'step'])->whereNumber('batch');
    Route::get('/admin/assets/import/{batch}/rows', [AssetImportController::class, 'rows'])->whereNumber('batch');
    Route::get('/admin/assets/import/{batch}/decisions', [AssetImportController::class, 'decisions'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/decisions', [AssetImportController::class, 'saveDecisions'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/codes', [AssetImportController::class, 'codes'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/person', [AssetImportController::class, 'person'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/propose', [AssetImportController::class, 'propose'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/commit', [AssetImportController::class, 'commit'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/retry', [AssetImportController::class, 'retry'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/qr', [AssetImportController::class, 'retryQr'])->whereNumber('batch');
    Route::post('/admin/assets/import/{batch}/discard', [AssetImportController::class, 'discard'])->whereNumber('batch');
});

// NOTE: the legacy /admin/assets/{id}/qr-sticker routes were removed.
// They returned view('admin.assets.qr-sticker'), which does not exist, and two
// identical copies were registered. QR stickers are rendered client-side
// (asset_registry / department_asset / replacement), and nothing linked to them.

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

    // Auto-transition to "For Checking" when lifespan expired or maintenance overdue
    $today = \Carbon\Carbon::now()->toDateString();
    $shouldTransition = false;
    $transitionReason = '';

        $currentStatus = $asset->Lifecycle_Status ?? '';

        // Only Active assets can move to For Checking when lifespan expires
        if ($currentStatus === 'Active'
            && !empty($asset->expiration_date)
            && \Carbon\Carbon::parse($asset->expiration_date)->toDateString() <= $today
        ) {
            $shouldTransition = true;
            $transitionReason = 'Asset lifespan expired on ' . $asset->expiration_date
                . '. Automatically transitioned to "For Checking" for evaluation.';
        }
        // Maintenance overdue — still only Active
        elseif ($currentStatus === 'Active'
            && !empty($asset->next_maintenance_date)
            && \Carbon\Carbon::parse($asset->next_maintenance_date)->toDateString() <= $today
        ) {
            $shouldTransition = true;
            $transitionReason = 'Asset maintenance is overdue (due date: ' . $asset->next_maintenance_date
                . '). Automatically transitioned to "For Checking" for maintenance evaluation.';
        }

    if ($shouldTransition) {
        try {
            DB::transaction(function () use ($asset, $transitionReason) {
                DB::table('assets')->where('id', $asset->id)->update([
                    'Lifecycle_Status' => 'For Checking',
                    'updated_at' => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'asset_id' => $asset->id,
                    'user_id' => Auth::id(),
                    'action_type' => 'UPDATE',
                    'action_description' => 'Automatic status change: Lifespan expired or maintenance overdue',
                    'notes' => $transitionReason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // Refresh so the view sees the new status
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
                ->groupBy('assets.id', 'employee_numbers.Full_Name')
                ->first();
        } catch (\Exception $e) {
            \Log::error('Failed to auto-transition asset', [
                'asset_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    return view('admin.assets.show', [
        'asset'       => $asset,
        'departments' => \App\Support\AssetEditing::departments(),
        'people'      => \App\Support\AssetEditing::people(),
        'conditions'  => \App\Support\AssetEditing::CONDITIONS,
        'completions' => \App\Support\AssetEditing::COMPLETION_STATUSES,
        'categories'  => \App\Support\Import\AssetImport::CATEGORIES,
    ]);
})->where('id', '[0-9]+');

// Download Inventory - Export all assets as CSV with full reporting data - IMPORTANT: place before catch-all routes
Route::get('/admin/inventory-download', function () {
    try {
        // Fetch all assets with full reporting data. This is the inventory, so
        // assets whose disposal is archived are not in it any more.
        $assets = Inventory::excludeRemoved(DB::table('assets'))
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

// ─────────────────────────────────────────────────────────────
// Notifications API (from notifications table)
// ─────────────────────────────────────────────────────────────

Route::get('/api/notifications', function (Request $request) {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['notifications' => [], 'unread_count' => 0], 401);
    }

    $limit = min((int) $request->query('limit', 20), 50);

    $notifications = DB::table('notifications')
        ->where('user_id', $user->id)
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get()
        ->map(function ($n) {
            return [
                'id'             => $n->id,
                'title'          => $n->title,
                'message'        => $n->message,
                'type'           => $n->type,
                'reference_id'   => $n->reference_id,
                'reference_type' => $n->reference_type,
                'is_read'        => (bool) $n->is_read,
                'created_at'     => $n->created_at,
                'time_ago'       => \Carbon\Carbon::parse($n->created_at)->diffForHumans(),
            ];
        });

    $unreadCount = DB::table('notifications')
        ->where('user_id', $user->id)
        ->where('is_read', false)
        ->count();

    return response()->json([
        'notifications' => $notifications,
        'unread_count'  => $unreadCount,
    ]);
})->middleware('auth');

Route::post('/api/notifications/{id}/read', function ($id) {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['success' => false], 401);
    }

    $updated = DB::table('notifications')
        ->where('id', $id)
        ->where('user_id', $user->id)
        ->update([
            'is_read'    => true,
            'updated_at' => now(),
        ]);

    return response()->json(['success' => (bool) $updated]);
})->middleware('auth');

Route::post('/api/notifications/read-all', function () {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['success' => false], 401);
    }

    DB::table('notifications')
        ->where('user_id', $user->id)
        ->where('is_read', false)
        ->update([
            'is_read'    => true,
            'updated_at' => now(),
        ]);

    return response()->json(['success' => true]);
})->middleware('auth');

// Compatibility endpoint used by the employee dashboard toast poller:
// returns the current user's repair request statuses so it can detect changes.
Route::get('/api/notifications/user', function () {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['repairs' => []], 401);
    }

    $repairs = DB::table('repairs')
        ->join('assets', 'repairs.Asset_id', '=', 'assets.id')
        ->where('assets.user_id', $user->id)
        ->orderByDesc('repairs.created_at')
        ->limit(20)
        ->get(['repairs.id', 'repairs.Repair_status', 'assets.Asset_name'])
        ->map(function ($r) {
            return [
                'id'         => $r->id,
                'status'     => strtolower((string) $r->Repair_status),
                'Asset_name' => $r->Asset_name,
            ];
        });

    return response()->json(['repairs' => $repairs]);
})->middleware('auth');

// Admin disposal page — the completed log of disposed assets.
//
// Approval happens on the Requests page, where a Disposal request is turned
// into the record automatically: the request is the source of the disposal
// transaction, so this page has no manual "record a disposal" form. It views,
// searches, explains and archives records — and only ever lists the records
// that have NOT been archived.
// The Disposal page has two tabs on one URL: the finished disposal records
// (default) and the official disposal forms being prepared, printed and filed.
// Keeping them on one page means the sidebar entry the office already knows is
// where the whole workflow lives.
Route::get('/admin/disposal', function (Request $request) {
    $requestedTab = $request->query('tab');
    $tab = in_array($requestedTab, ['forms', 'age'], true) ? $requestedTab : 'records';

    // The service-life review is advisory and can be switched off in
    // config/disposal.php. When it is off the tab does not exist at all.
    if ($tab === 'age' && ! AssetAgeReview::enabled()) {
        $tab = 'records';
    }

    $search = trim((string) $request->query('q', ''));

    // "Total disposed" counts every record, so the card does not shrink to the
    // rows of the page that happens to be open.
    $totalDisposed = Disposals::query(false)->count();

    $records = Disposals::query(false)->orderByDesc('disposals.disposal_date');

    // The search runs in the database, so it covers every disposal record and
    // not only the ten on screen.
    if ($search !== '') {
        $term = '%' . $search . '%';

        $records->where(function ($q) use ($term) {
            $q->whereLike('assets.Asset_name', $term)
              ->orWhereLike('assets.Asset_code', $term)
              ->orWhereLike('disposals.Description', $term)
              ->orWhereLike('disposals.disposal_reason', $term)
              ->orWhereLike('disposals.Approve_by', $term)
              ->orWhereLike('disposals.notes', $term)
              ->orWhereRaw('LOWER(CAST(disposals.disposal_date AS TEXT)) LIKE ?', [strtolower($term)]);
        });
    }

    $disposalRecords = $records
        ->paginate(10)
        ->withQueryString()
        ->through(fn ($row) => Disposals::present($row));

    $archivedCount = Disposals::archiveColumnsReady()
        ? DB::table('disposals')->where('is_archived', true)->count()
        : 0;

    $formCounts = DisposalForms::statusCounts();
    $formsReady = DisposalForms::tablesReady();

    $shared = [
        'disposalTab'  => $tab,
        'formCounts'   => $formCounts,
        'formsReady'   => $formsReady,
        'awaitingFormCount' => $formsReady ? DisposalForms::awaitingForm()->count() : 0,
        'ageReviewEnabled'  => AssetAgeReview::enabled(),
    ];

    if ($tab === 'forms') {
        return view('admin.disposal.forms', $shared + [
            'forms'    => $formsReady ? DisposalForms::queue() : collect(),
            'awaiting' => $formsReady ? DisposalForms::awaitingForm() : collect(),
        ]);
    }

    if ($tab === 'age') {
        $search = trim((string) $request->query('q', ''));
        $candidates = AssetAgeReview::candidates($search);

        return view('admin.disposal.age', $shared + [
            'candidates' => $candidates,
            'summary'    => AssetAgeReview::summary($candidates),
            'search'     => $search,
            'ready'      => AssetAgeReview::ready(),
        ]);
    }

    return view('admin.disposal.disposal', [
        'disposalRecords'  => $disposalRecords,
        'totalDisposed'    => $totalDisposed,
        'archivedCount'    => $archivedCount,
        'archiveReady'     => Disposals::archiveColumnsReady(),
        'search'           => $search,
    ] + $shared);
})->middleware('auth');

// Archived Disposal Assets — older records kept separately for reference.
Route::get('/admin/disposal/archived', function () {
    $archiveReady = Disposals::archiveColumnsReady();
    $records      = Disposals::query(true);

    // The archive stamp only exists once the migration has been run.
    if ($archiveReady) {
        $records->orderByDesc('disposals.archived_at');
    }

    $disposalRecords = $records
        ->orderByDesc('disposals.disposal_date')
        ->get()
        ->map(fn ($row) => Disposals::present($row));

    return view('admin.disposal.archived', [
        'disposalRecords' => $disposalRecords,
        'archiveReady'    => $archiveReady,
    ]);
})->middleware('auth');

// Archive a disposal record. This never deletes anything: the record simply
// leaves the main Disposal list and stays readable under Archived Disposal
// Assets, so the history of a retired asset is never lost.
//
// The Disposal page posts an ordinary HTML form here, so the result is a
// redirect back to the page with a flash message — a response the browser can
// always complete. Callers that ask for JSON (Accept: application/json) still
// get JSON.
Route::post('/admin/disposal/{id}/archive', function (Request $request, $id) {
    $wantsJson = $request->expectsJson();

    $fail = function (string $message, int $status) use ($wantsJson, $id) {
        \Log::info('Disposal archive refused for record #' . $id . ': ' . $message);

        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect('/admin/disposal')->with('error', $message);
    };

    $admin = Auth::user();
    if (!$admin || ! Access::isAssetOffice($admin)) {
        return $fail('Only the Asset Management Office can archive a disposal record.', 403);
    }

    $disposal = DB::table('disposals')->where('Disposal_ID', $id)->first();
    if (!$disposal) {
        return $fail('Disposal record not found.', 404);
    }

    $result = Disposals::archive((int) $id, (int) $admin->id);

    if (! $result['archived']) {
        return $fail($result['message'], 422);
    }

    // Archiving is the moment the asset leaves the inventory, so say so. The
    // asset row is kept either way — only its visibility changed.
    $message = $result['message'];
    if ($result['asset_removed']) {
        $message .= ' Asset ' . ($result['asset_code'] ?: ('#' . $disposal->Asset_id))
            . ' has been removed from the inventory — the record and its history are kept for reference.';
    }

    // Audit: the disposal record itself is untouched, only its visibility moved.
    try {
        $assetCode = $result['asset_code'] ?: 'N/A';

        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'request_id'         => $disposal->Request_id,
            'asset_id'           => $disposal->Asset_id,
            'action_type'        => 'UPDATE',
            'notes'              => $result['asset_removed']
                ? 'Disposal record archived and asset removed from the inventory'
                : 'Disposal record archived',
            'action_description' => $result['asset_removed']
                ? 'Disposal record #' . $id . ' for asset ' . $assetCode . ' was archived and the asset was removed from the inventory.'
                : 'Disposal record #' . $id . ' for asset ' . $assetCode . ' was archived.',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    } catch (\Throwable $e) {
        \Log::warning('Audit log skipped on disposal archive: ' . $e->getMessage());
    }

    return $wantsJson
        ? response()->json([
            'success'        => true,
            'message'        => $message,
            'archived'       => true,
            'asset_removed'  => $result['asset_removed'],
            'asset_code'     => $result['asset_code'],
        ])
        : redirect('/admin/disposal')->with('success', $message);
})->middleware('auth');

// What permanently deleting an archived record would remove — asked before the
// Admin is allowed to commit, so the consequences are visible up front instead
// of arriving as a surprise (or as a foreign-key error half way through).
Route::get('/admin/disposal/{id}/delete-impact', function ($id) {
    $admin = Auth::user();
    if (!$admin || ! Access::isAssetOffice($admin)) {
        return response()->json(['success' => false, 'message' => 'Only the Asset Management Office can delete an archived disposal record.'], 403);
    }

    $impact = Disposals::deletionImpact((int) $id);

    if (!$impact) {
        return response()->json(['success' => false, 'message' => 'Disposal record not found.'], 404);
    }

    return response()->json(['success' => true, 'impact' => $impact]);
})->middleware('auth');

// Permanently delete every archived disposal record, and with each of them the
// asset it names. Registered before the {id} routes: its literal second segment
// must win over the wildcard.
Route::post('/admin/disposal/archived/delete-all', function (Request $request) {
    $wantsJson = $request->expectsJson();

    $admin = Auth::user();
    if (!$admin || ! Access::isAssetOffice($admin)) {
        $message = 'Only the Asset Management Office can permanently delete archived disposal records.';

        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], 403)
            : redirect('/admin/disposal/archived')->with('error', $message);
    }

    $result = Disposals::destroyAllArchived();

    if ($result['deleted']) {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'request_id'         => null,
                'asset_id'           => null,
                'action_type'        => 'DISPOSAL',
                'notes'              => 'Archived disposal records permanently deleted',
                'action_description' => 'Permanently deleted ' . $result['deleted_count'] . ' archived disposal record(s)'
                    . ($result['asset_count'] > 0 ? ' and the ' . $result['asset_count'] . ' asset record(s) they named' : '')
                    . '.',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Audit log skipped on bulk archived disposal delete: ' . $e->getMessage());
        }
    }

    return $wantsJson
        ? response()->json(['success' => $result['deleted'], 'message' => $result['message']] + [
            'deleted_count' => $result['deleted_count'],
            'asset_count'   => $result['asset_count'],
            'failed'        => $result['failed'],
        ], $result['deleted'] ? 200 : 422)
        : redirect('/admin/disposal/archived')->with($result['deleted'] ? 'success' : 'error', $result['message']);
})->middleware('auth');

// Permanently delete one archived disposal record and the asset it names.
//
// Only reachable from Archived Disposal Assets: the record has to be archived
// first. Support\Disposals::destroy() clears, in one transaction, the rows
// PostgreSQL will not let an asset leave behind — request_items, repairs,
// replacements and disposal records are RESTRICT — while keeping any request
// that is shared with another asset.
//
// Like archiving, this is posted by an ordinary HTML form, so the browser always
// gets a redirect with a flash message. Callers that ask for JSON get JSON.
Route::post('/admin/disposal/{id}/delete-archived', function (Request $request, $id) {
    $wantsJson = $request->expectsJson();

    $fail = function (string $message, int $status) use ($wantsJson, $id) {
        \Log::info('Permanent delete of archived disposal #' . $id . ' refused: ' . $message);

        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect('/admin/disposal/archived')->with('error', $message);
    };

    $admin = Auth::user();
    if (!$admin || ! Access::isAssetOffice($admin)) {
        return $fail('Only the Asset Management Office can permanently delete an archived disposal record.', 403);
    }

    $disposal = DB::table('disposals')->where('Disposal_ID', $id)->first();
    if (!$disposal) {
        return $fail('Disposal record not found.', 404);
    }

    $result = Disposals::destroy((int) $id);

    if (! $result['deleted']) {
        return $fail($result['message'], 422);
    }

    // The rows are gone, so this is the only place the removal is recorded: the
    // audit entry names the asset code in its description.
    try {
        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'request_id'         => null,
            'asset_id'           => null,
            'action_type'        => 'DISPOSAL',
            'notes'              => 'Archived disposal record and asset permanently deleted',
            'action_description' => $result['asset_code']
                ? 'Archived disposal record #' . $id . ' for asset ' . $result['asset_code'] . ' was permanently deleted, together with the asset record.'
                : 'Archived disposal record #' . $id . ' was permanently deleted.',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    } catch (\Throwable $e) {
        \Log::warning('Audit log skipped on archived disposal delete: ' . $e->getMessage());
    }

    return $wantsJson
        ? response()->json([
            'success'            => true,
            'message'            => $result['message'],
            'asset_deleted'      => $result['asset_deleted'],
            'asset_code'         => $result['asset_code'],
            'removed'            => $result['removed'],
            'preserved_requests' => $result['preserved_requests'],
        ])
        : redirect('/admin/disposal/archived')->with('success', $result['message']);
})->middleware('auth');

// The old "permanently delete the asset" action used to live here, on the main
// Disposal page. It issued `delete from assets where id = …` straight away, so
// PostgreSQL refused it for any asset still referenced by request_items
// (SQLSTATE 23503) and, for the rows it did delete, silently destroyed the
// asset's repair / replacement / request / file history in an order nothing
// controlled. That action is gone: deleting now happens only from Archived
// Disposal Assets, only after the disposal is complete and archived, and it is
// dependency-ordered inside one transaction by Support\Disposals::destroy().

// Get single disposal details (for the View Details modal).
//
// The Admin should be able to understand why and when an asset was disposed
// without losing anything: the record, the request that produced it, the
// requester's own words and the asset's accountability / repair / replacement /
// disposal / audit history all come back together. Archived records open here
// just as well as active ones.
Route::get('/admin/disposal/{id}/details', function ($id) {
    $disposal = DB::table('disposals')->where('Disposal_ID', $id)->first();

    if (!$disposal) {
        return response()->json(['success' => false, 'message' => 'Disposal record not found'], 404);
    }

    $assetId = $disposal->Asset_id ? (int) $disposal->Asset_id : null;

    $asset = $assetId
        ? DB::table('assets')->where('id', $assetId)->first()
        : null;

    // The request is the source of this disposal, so its note is shown verbatim.
    $request = $disposal->Request_id
        ? DB::table('requests')
            ->leftJoin('users', 'requests.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('requests.id', $disposal->Request_id)
            ->select(
                'requests.id',
                'requests.request_type',
                'requests.status',
                'requests.Note',
                'requests.url',
                'requests.file_name',
                'requests.admin_remarks',
                'requests.created_at',
                DB::raw('requests."updated_at" as updated_at'),
                DB::raw('employee_numbers."Full_Name" as requester_name'),
                'users.email as requester_email'
            )
            ->first()
        : null;

    $archivedBy = null;
    if (Disposals::archiveColumnsReady() && !empty($disposal->archived_by)) {
        $archiver = DB::table('users')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.id', $disposal->archived_by)
            ->select('users.email', DB::raw('employee_numbers."Full_Name" as full_name'))
            ->first();

        $archivedBy = $archiver->full_name ?? $archiver->email ?? null;
    }

    return response()->json([
        // ── The disposal record itself ────────────────────────────────
        'id'                 => $disposal->Disposal_ID,
        'request_id'         => $disposal->Request_id,
        'disposal_date'      => $disposal->disposal_date,
        'reason'             => $disposal->disposal_reason ?? $disposal->Description ?? null,
        'description'        => $disposal->Description,
        'disposed_by'        => $disposal->Approve_by,
        'notes'              => $disposal->notes,
        'is_archived'        => (bool) ($disposal->is_archived ?? false),
        'archived_at'        => $disposal->archived_at ?? null,
        'archived_by'        => $archivedBy,

        // ── The asset ─────────────────────────────────────────────────
        'asset_still_exists'   => (bool) $asset,
        'inventory_removed'    => Inventory::isRemoved($asset),
        'inventory_removed_at' => $asset->{Inventory::REMOVED_AT} ?? null,
        'asset_name'         => $asset->Asset_name ?? $disposal->Description ?? null,
        'asset_code'         => $asset->Asset_code ?? null,
        'original_value'     => $asset->purchase_Price ?? null,
        'category'           => $asset->Category ?? null,
        'condition'          => $asset->Condition ?? null,
        'serial_number'      => $asset->serial_Number ?? null,
        'asset_location'     => $asset->asset_location ?? null,
        'supplier'           => $asset->supplier ?? null,
        'model'              => $asset->model ?? null,
        'manufacture'        => $asset->manufacture ?? null,
        'purchase_price'     => $asset->purchase_Price ?? null,
        'warranty_months'    => $asset->warranty_months ?? null,
        'lifespan_months'    => $asset->lifespan_months ?? null,
        'lifecycle_status'   => $asset->Lifecycle_Status ?? null,

        // ── The request that produced the record ──────────────────────
        'request' => $request ? [
            'id'              => $request->id,
            'type'            => $request->request_type,
            'status'          => $request->status,
            'note'            => $request->Note,
            'attachment_url'  => $request->url,
            'attachment_name' => $request->file_name,
            'admin_remarks'   => $request->admin_remarks,
            'created_at'      => $request->created_at,
            'requester_name'  => $request->requester_name,
            'requester_email' => $request->requester_email,
        ] : null,

        // ── Supporting documents kept with the request ────────────────
        // Still readable after finalization: the documents are part of the
        // record, served only through the authorized download route.
        'documents' => $request
            ? DisposalDocuments::forRequest((int) $request->id)->map(fn ($doc) => [
                'id'         => $doc->id,
                'title'      => $doc->title ?: $doc->file_name,
                'file_name'  => $doc->file_name,
                'asset_code' => $doc->asset_code,
                'size'       => $doc->size_label,
                'uploaded'   => $doc->uploaded_label,
                'uploader'   => $doc->uploader_name ?: $doc->uploader_email,
                'url'        => url('/admin/disposal/documents/' . $doc->id),
            ])->values()->all()
            : [],

        // ── Lifecycle history ─────────────────────────────────────────
        'history' => Disposals::history($assetId),
    ]);
})->middleware('auth');

/*
 |─────────────────────── Official disposal forms ───────────────────────
 |
 | The official NU Lipa Disposal Form: generated from an approved disposal
 | request, completed and validated, printed, filed once Accountancy returns
 | it signed, and only then finalized.
 |
 | The rule these routes exist to enforce: NONE of them disposes of an asset
 | except the last one, and that one refuses until the signed form has been
 | recorded and the Admin has confirmed the assets by name.
 |
 | This is the Asset Management Office's work, exactly like the rest of the
 | Disposal page: /admin is guarded by RequireAdminArea, and each route asks
 | Access again so the two can never drift apart.
 */

// Prepare (or reopen) the official form for a request.
//
// Approval already generates it, so the common path is "open it"; this action
// exists for the requests approved before the form existed, and it is
// idempotent either way — a second click reopens the same form instead of
// creating a second one.
Route::post('/admin/disposal/requests/{requestId}/form', function (Request $request, $requestId) {
    $wantsJson = $request->expectsJson();

    $fail = function (string $message, int $status) use ($wantsJson, $requestId) {
        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect('/admin/disposal?tab=forms')->with('error', $message);
    };

    $admin = Auth::user();
    if (! $admin || ! Access::isAssetOffice($admin)) {
        return $fail('Only the Asset Management Office can prepare an official disposal form.', 403);
    }

    if (! DisposalForms::tablesReady()) {
        return $fail('The disposal form tables have not been created yet — run the database migration first.', 503);
    }

    $requestRecord = DB::table('requests')->where('id', $requestId)->first();

    if (! $requestRecord) {
        return $fail('Request not found.', 404);
    }

    if (strtolower((string) $requestRecord->request_type) !== 'disposal') {
        return $fail('Only a disposal request has an official disposal form.', 422);
    }

    // Approval is what authorizes preparation. A pending or rejected request
    // never gets a form.
    if (strtolower((string) $requestRecord->status) !== 'approved') {
        return $fail('Only an approved disposal request can go to form preparation. This request is ' . $requestRecord->status . '.', 422);
    }

    $formId = DisposalForms::ensureForRequest((int) $requestId, $admin);

    if ($formId === null) {
        return $fail('The official disposal form could not be prepared for this request.', 422);
    }

    $message = 'Official disposal form #' . $formId . ' is ready to be completed.';

    return $wantsJson
        ? response()->json(['success' => true, 'message' => $message, 'form_id' => $formId])
        : redirect('/admin/disposal/forms/' . $formId)->with('success', $message);
})->middleware('auth');

// The preparation screen: every field the official form prints, prefilled from
// the records, with whatever is still missing called out.
Route::get('/admin/disposal/forms/{id}', function ($id) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        abort(403, 'Only the Asset Management Office can prepare the official disposal form.');
    }

    $form = DisposalForms::load([(int) $id])->first();

    if (! $form) {
        abort(404, 'Disposal form not found.');
    }

    return view('admin.disposal.form_edit', [
        'form'    => $form,
        'items'   => $form->items,
        'missing' => DisposalForms::missingFields($form, $form->items->all()),
        'formCounts' => DisposalForms::statusCounts(),
        // Supporting documentation belongs to the request, so it stays with
        // the disposal even if this form is viewed again after finalization.
        'documents'      => DisposalDocuments::forRequest((int) $form->request_id),
        'documentsReady' => DisposalDocuments::ready(),
        'documentLock'   => DisposalDocuments::lockReason((int) $form->request_id),
    ]);
})->middleware('auth');

// Save the office's completion of the form. Server-side validation decides
// whether the form is complete enough to print; printing itself is separate.
Route::post('/admin/disposal/forms/{id}/save', function (Request $request, $id) {
    $wantsJson = $request->expectsJson();

    $admin = Auth::user();
    if (! $admin || ! Access::isAssetOffice($admin)) {
        $message = 'Only the Asset Management Office can prepare the official disposal form.';

        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], 403)
            : redirect('/admin/disposal?tab=forms')->with('error', $message);
    }

    $result = DisposalForms::save((int) $id, $request->all(), $admin);

    if (! $result['ok']) {
        if ($wantsJson) {
            return response()->json([
                'success' => false,
                'message' => 'The form could not be saved. Please check the highlighted fields.',
                'errors'  => $result['errors'],
            ], 422);
        }

        return redirect('/admin/disposal/forms/' . $id)
            ->with('error', 'The form could not be saved. Please check the highlighted fields.')
            ->withErrors($result['errors'])
            ->withInput();
    }

    return $wantsJson
        ? response()->json(['success' => true, 'message' => $result['message'], 'status' => $result['status']])
        : redirect('/admin/disposal/forms/' . $id)->with($result['missing'] ? 'error' : 'success', $result['message']);
})->middleware('auth');

// Print one or several forms.
//
// Registered before the {id} routes so "print" is never read as a form id.
// Printing records the printing and nothing else: no signature is claimed and
// no asset changes status. The response is the printable document itself, which
// is why this is the one action that answers with a page instead of a redirect.
Route::post('/admin/disposal/forms/print', function (Request $request) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        return redirect('/admin/disposal?tab=forms')
            ->with('error', 'Only the Asset Management Office can print an official disposal form.');
    }

    $ids = (array) $request->input('ids', []);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));

    if (! $ids) {
        return redirect('/admin/disposal?tab=forms')->with('error', 'Select at least one form to print.');
    }

    $result = DisposalForms::recordPrint($ids, $admin);

    if (! $result['printed']) {
        return redirect('/admin/disposal?tab=forms')
            ->with('error', 'Nothing was printed. ' . implode(' ', $result['refused']));
    }

    return view('admin.disposal.form_print', [
        'forms'    => DisposalForms::load($result['printed']),
        'refused'  => $result['refused'],
        'preview'  => false,
        'forPrint' => true,
    ]);
})->middleware('auth');

// A read-only look at the form as it would print, without recording a print.
Route::get('/admin/disposal/forms/{id}/preview', function ($id) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        abort(403, 'Only the Asset Management Office can review an official disposal form.');
    }

    $forms = DisposalForms::load([(int) $id]);

    if ($forms->isEmpty()) {
        abort(404, 'Disposal form not found.');
    }

    return view('admin.disposal.form_print', [
        'forms'    => $forms,
        'refused'  => [],
        'preview'  => true,
        'forPrint' => false,
    ]);
})->middleware('auth');

// Record that the signed form came back from Accountancy, with an optional
// scanned copy. Accepted file types and size are enforced here, and the file
// never lands on a publicly readable disk.
Route::post('/admin/disposal/forms/{id}/signed', function (Request $request, $id) {
    $wantsJson = $request->expectsJson();

    $fail = function (string $message, int $status) use ($wantsJson, $id) {
        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect('/admin/disposal/forms/' . $id)->with('error', $message);
    };

    $admin = Auth::user();
    if (! $admin || ! Access::isAssetOffice($admin)) {
        return $fail('Only the Asset Management Office can record the signed disposal form.', 403);
    }

    $validated = $request->validate([
        'signed_notes' => 'nullable|string|max:1000',
        'received_date' => 'nullable|date',
        'signed_copy'  => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:' . DisposalForms::UPLOAD_MAX_KB,
    ], [
        'received_date.date' => 'The date the signed form was received is not a valid date.',
        'signed_copy.mimes'  => 'The signed copy must be a JPG, PNG, WEBP or PDF file.',
        'signed_copy.max'    => 'The signed copy may not be larger than ' . (DisposalForms::UPLOAD_MAX_KB / 1024) . ' MB.',
        'signed_notes.max'   => 'The notes cannot exceed 1000 characters.',
    ]);

    $result = DisposalForms::recordSigned((int) $id, $validated, $request->file('signed_copy'), $admin);

    if (! $result['ok']) {
        return $fail($result['message'], 422);
    }

    $message = $result['message'] . ($result['warning'] ? ' ' . $result['warning'] : '');

    return $wantsJson
        ? response()->json(['success' => true, 'message' => $message, 'uploaded' => $result['uploaded']])
        : redirect('/admin/disposal/forms/' . $id)->with($result['warning'] ? 'error' : 'success', $message);
})->middleware('auth');

// The scanned signed copy. Streamed through this authorized route — there is no
// public URL for it, and an account that is not the Asset Management Office
// never reaches the file.
Route::get('/admin/disposal/forms/{id}/signed-copy', function ($id) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        abort(403, 'Only the Asset Management Office can open a signed disposal form.');
    }

    $form = DB::table('disposal_forms')->where('id', $id)->first();

    if (! $form) {
        abort(404, 'Disposal form not found.');
    }

    $path = DisposalForms::signedCopyPath($form);

    if ($path === null) {
        abort(404, 'No scanned signed copy is stored for this form.');
    }

    $name = trim((string) $form->signed_file_name) ?: basename($path);

    return Storage::disk(DisposalForms::UPLOAD_DISK)->download($path, $name);
})->middleware('auth');

/*
 |─────────────────── Disposal supporting documents ───────────────────
 |
 | Inspection reports, appraisals, board approvals and photographs that the
 | office keeps with a disposal. Files live on the private disk and every read
 | goes through the authorized route below — there is no public URL.
 |
 | The signed official form has its own record on disposal_forms; it is not
 | uploaded twice here.
 */

// Where an action started from: the form page when the form belongs to this
// request, otherwise the forms queue. Never a caller-supplied path.
$disposalDocumentBackTo = function (Request $request, int $requestId): string {
    $formId = (int) $request->input('form_id', 0);

    if ($formId > 0 && DisposalForms::tablesReady()
        && DB::table('disposal_forms')->where('id', $formId)->where('request_id', $requestId)->exists()) {
        return '/admin/disposal/forms/' . $formId;
    }

    return '/admin/disposal?tab=forms';
};

Route::post('/admin/disposal/requests/{requestId}/documents', function (Request $request, $requestId) use ($disposalDocumentBackTo) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        abort(403, 'Only the Asset Management Office can attach a supporting document to a disposal.');
    }

    $requestRecord = DB::table('requests')->where('id', $requestId)->first();

    if (! $requestRecord) {
        abort(404, 'Disposal request not found.');
    }

    $backTo = $disposalDocumentBackTo($request, (int) $requestId);

    if (strtolower((string) $requestRecord->request_type) !== 'disposal') {
        return redirect($backTo)->with('error', 'Supporting documents can only be attached to a disposal request.');
    }

    // Documentation belongs to the processing stage. A pending or rejected
    // request has no disposal to document yet.
    if (strtolower((string) $requestRecord->status) !== 'approved') {
        return redirect($backTo)->with('error', 'Documents can be attached once the disposal request has been approved. This request is ' . $requestRecord->status . '.');
    }

    if (! DisposalDocuments::ready()) {
        return redirect($backTo)->with('error', 'The disposal documents table has not been created yet — run the database migration first.');
    }

    if ($lock = DisposalDocuments::lockReason((int) $requestId)) {
        return redirect($backTo)->with('error', $lock);
    }

    $request->validate([
        'document' => 'required|file|mimes:' . implode(',', DisposalDocuments::MIMES) . '|max:' . DisposalDocuments::MAX_KB,
        'title'    => 'nullable|string|max:' . DisposalDocuments::TITLE_MAX,
        'asset_id' => 'nullable|integer',
    ], [
        'document.required' => 'Choose the document to attach.',
        'document.mimes'    => 'The document must be a PDF, JPG, PNG or WEBP file.',
        'document.max'      => 'The document may not be larger than ' . (DisposalDocuments::MAX_KB / 1024) . ' MB.',
        'title.max'         => 'The document description cannot exceed ' . DisposalDocuments::TITLE_MAX . ' characters.',
    ]);

    $result = DisposalDocuments::store(
        (int) $requestId,
        $request->filled('asset_id') ? (int) $request->input('asset_id') : null,
        $request->input('title'),
        $request->file('document'),
        $admin
    );

    return redirect($backTo)->with($result['ok'] ? 'success' : 'error', $result['message']);
})->middleware('auth');

// Open a supporting document. Private disk, authorized readers only.
Route::get('/admin/disposal/documents/{id}', function ($id) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        abort(403, 'Only the Asset Management Office can open a disposal document.');
    }

    $document = DisposalDocuments::find((int) $id);

    if (! $document) {
        abort(404, 'That document no longer exists.');
    }

    $path = trim((string) ($document->file_path ?? ''));
    $disk = Storage::disk(DisposalDocuments::DISK);

    if ($path === '' || ! $disk->exists($path)) {
        abort(404, 'The stored file for this document is missing.');
    }

    $name = trim((string) ($document->file_name ?? '')) ?: basename($path);

    return $disk->download($path, $name);
})->middleware('auth');

// Remove a supporting document from a request that is still being processed.
// The audit entry stays; the file does not.
Route::post('/admin/disposal/documents/{id}/delete', function (Request $request, $id) use ($disposalDocumentBackTo) {
    $admin = Auth::user();

    if (! $admin || ! Access::isAssetOffice($admin)) {
        abort(403, 'Only the Asset Management Office can remove a disposal document.');
    }

    $document = DisposalDocuments::find((int) $id);

    if (! $document) {
        abort(404, 'That document no longer exists.');
    }

    $backTo = $disposalDocumentBackTo($request, (int) $document->request_id);
    $result = DisposalDocuments::delete((int) $id, $admin);

    return redirect($backTo)->with($result['ok'] ? 'success' : 'error', $result['message']);
})->middleware('auth');

// Finalize the disposal: the only action in the whole workflow that retires an
// asset. Refused unless the signed form has been recorded and the Admin has
// confirmed which assets this covers.
Route::post('/admin/disposal/forms/{id}/finalize', function (Request $request, $id) {
    $wantsJson = $request->expectsJson();

    $fail = function (string $message, int $status) use ($wantsJson, $id) {
        return $wantsJson
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect('/admin/disposal/forms/' . $id)->with('error', $message);
    };

    $admin = Auth::user();
    if (! $admin || ! Access::isAssetOffice($admin)) {
        return $fail('Only the Asset Management Office can finalize a disposal.', 403);
    }

    $request->validate([
        'actual_disposal_date' => 'nullable|date',
        'confirm'              => 'accepted',
    ], [
        'actual_disposal_date.date' => 'The date of disposal is not a valid date.',
        'confirm.accepted'          => 'Confirm the disposal first: check the assets listed and tick the confirmation box.',
    ]);

    $result = DisposalForms::finalize((int) $id, $request->all(), $admin);

    if (! $result['ok']) {
        return $fail($result['message'], 422);
    }

    return $wantsJson
        ? response()->json(['success' => true, 'message' => $result['message'], 'assets' => $result['assets']])
        : redirect('/admin/disposal?tab=forms')->with('success', $result['message']);
})->middleware('auth');

// Admin requests page – supports bulk requests via request_items
Route::get('/admin/requests', function () {
    $requests = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('users as assignee', 'requests.assign_to_user_id', '=', 'assignee.id')
        ->leftJoin('employee_numbers as assignee_emp', 'assignee.employee_numbers_id', '=', 'assignee_emp.id')
        ->leftJoin('users as remarks_admin', 'requests.admin_remarks_by', '=', 'remarks_admin.id')
        ->leftJoin('employee_numbers as remarks_admin_emp', 'remarks_admin.employee_numbers_id', '=', 'remarks_admin_emp.id')
        ->select([
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.Note',
            'requests.admin_remarks',
            'requests.admin_remarks_at',
            'requests.created_at',
            'requests.url',
            'requests.asset_id',
            'employee_numbers.Full_Name as submitted_by',
            'users.email',
            'assets.Asset_name as asset_name',
            'assets.Asset_code as asset_code',
            'assignee_emp.Full_Name as assigned_to',
            'remarks_admin_emp.Full_Name as admin_remarks_by_name',
            'remarks_admin.email as admin_remarks_by_email',
        ]);

    // The status tab is applied in SQL, so a page of 15 rows always shows the
    // status that was asked for (filtering only the rendered rows would hide
    // everything as soon as the wanted rows live on another page).
    $tab = strtolower(trim((string) request()->query('tab', 'all')));
    if (! in_array($tab, ['all', 'pending', 'approved', 'rejected'], true)) {
        $tab = 'all';
    }
    if ($tab !== 'all') {
        $requests->whereRaw('LOWER(requests.status) = ?', [$tab]);
    }

    // The period (this month, last month, a year, or a custom range) narrows
    // the same SQL query, before the page is cut, so a page of 15 rows always
    // contains requests that fall inside the chosen month. The sort control
    // then orders them: pending-first by default — the only rows carrying
    // Approve / Reject come first, longest wait first — or strictly by date.
    // Shared with the CSV export below, so both cover exactly the same rows.
    $filters = RequestFilters::fromQuery(request()->all());

    $requests = RequestFilters::apply($requests, $filters);
    $requests = RequestFilters::order($requests, $filters)
        ->paginate(15)
        ->withQueryString();

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

    // Disposal requests that already produced their disposal record. The
    // request stays Approved, but its Approve action must never run again.
    $recordedDisposalRequests = DB::table('disposals')
        ->whereNotNull('Request_id')
        ->pluck('Request_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    // `through()` keeps the paginator (the transformed page still knows its
    // page count and links); `map()` would collapse it into a plain collection.
    $requests = $requests->through(function ($request) use ($itemsByRequest, $recordedDisposalRequests) {
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

        $isDisposalRequest = strtolower((string) $request->request_type) === 'disposal';

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
            // The user's own note, shown in full and never edited by the Admin.
            'description'  => $request->Note ?: '',
            'admin_remarks'    => $request->admin_remarks ?: '',
            'admin_remarks_by' => $request->admin_remarks_by_name
                                    ?: ($request->admin_remarks_by_email ?: null),
            'admin_remarks_at' => $request->admin_remarks_at
                                    ? \Illuminate\Support\Carbon::parse($request->admin_remarks_at)->format('M d, Y h:i A')
                                    : null,
            'assigned_to'  => $request->assigned_to ?? null,
            'image'        => Media::url($request->url ?? null),

            // ── Disposal requests ─────────────────────────────────────
            // A disposal request carries its reason inside the requester's note.
            // Read it back out here so the Admin confirms the reason instead of
            // re-typing it, and flag requests whose disposal record already
            // exists so their Approve action can be locked.
            'disposal_reason'   => $isDisposalRequest ? DisposalReason::derive($request->Note) : null,
            'disposal_date'     => $isDisposalRequest ? now()->toDateString() : null,
            'disposal_recorded' => $isDisposalRequest
                                    && in_array((int) $request->id, $recordedDisposalRequests, true),

            // ── Transfer requests the Executive Director has been asked about ─
            // One array describing the invitation the office is waiting on (if
            // any), whether this request may be sent at all, and what was sent
            // before. Null for every other request type. No token is ever part
            // of this payload.
            'review' => strtolower((string) $request->request_type) === 'transfer'
                            ? TransferReviews::panel((int) $request->id)
                            : null,
        ];
    });

    // Stat cards describe the whole table, not just the visible page.
    $totalRequests    = DB::table('requests')->count();
    $pendingRequests  = DB::table('requests')->whereRaw('LOWER(requests.status) = ?', ['pending'])->count();
    $approvedRequests = DB::table('requests')->whereRaw('LOWER(requests.status) = ?', ['approved'])->count();
    $rejectedRequests = DB::table('requests')->whereRaw('LOWER(requests.status) = ?', ['rejected'])->count();

    return view('admin.request.request', array_merge(
        compact('requests', 'totalRequests', 'pendingRequests', 'approvedRequests', 'rejectedRequests'),
        [
            'disposalReasons'   => DisposalReason::VALUES,
            'filters'           => $filters,
            'filterPeriodLabel' => RequestFilters::periodLabel($filters),
        ]
    ));
});

// Export Requests – every request in the report, not only the page on screen.
// The page's Export button used to build the CSV inside the browser from the
// rows the paginator had loaded, so a 33-request history downloaded as its
// first 15 rows. The report is built here instead, from the whole table, with
// the same tabs and the same order the admin table uses (pending first,
// longest wait first, then the decided ones newest-first).
Route::get('/admin/requests/export', function () {
    $requests = DB::table('requests')
        ->leftJoin('assets', 'requests.asset_id', '=', 'assets.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->select([
            'requests.id',
            'requests.request_type',
            'requests.status',
            'requests.created_at',
            'requests.asset_id',
            'employee_numbers.Full_Name as submitted_by',
            'assets.Asset_name as asset_name',
        ]);

    // The tab that is open on screen is the one the report covers.
    $tab = strtolower(trim((string) request()->query('tab', 'all')));
    if (! in_array($tab, ['all', 'pending', 'approved', 'rejected'], true)) {
        $tab = 'all';
    }
    if ($tab !== 'all') {
        $requests->whereRaw('LOWER(requests.status) = ?', [$tab]);
    }

    // The period and sort chosen on screen apply here too: the file is the
    // screen's list, not a different list that happens to share its tabs.
    $filters = RequestFilters::fromQuery(request()->all());

    $requests = RequestFilters::order(RequestFilters::apply($requests, $filters), $filters)->get();

    // A bulk request names every asset it carries — the table's
    // "Demo asset +1 more" is shorthand for this full list.
    $itemsByRequest = DB::table('request_items')
        ->join('assets', 'request_items.asset_id', '=', 'assets.id')
        ->whereIn('request_items.request_id', $requests->pluck('id')->all())
        ->select('request_items.request_id', 'assets.Asset_name')
        ->get()
        ->groupBy('request_id');

    $filename = 'requests-report-' . now()->format('Y-m-d') . '.csv';

    $headers = [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ];

    $callback = function () use ($requests, $itemsByRequest) {
        $file = fopen('php://output', 'w');

        // UTF-8 BOM so Excel shows special characters correctly
        fwrite($file, "\xEF\xBB\xBF");

        fputcsv($file, ['Request ID', 'Asset Name', 'Type', 'Submitted By', 'Date', 'Status']);

        foreach ($requests as $request) {
            $assetNames = $itemsByRequest
                ->get($request->id, collect())
                ->pluck('Asset_name')
                ->filter()
                ->values();

            // Old single-asset requests still carry their asset on the row.
            if ($assetNames->isEmpty() && ! empty($request->asset_id)) {
                $assetNames = collect([$request->asset_name ?: 'Unknown Asset']);
            }
            if ($assetNames->isEmpty()) {
                $assetNames = collect(['Unknown Asset']);
            }

            fputcsv($file, [
                '#REQ-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT),
                $assetNames->implode(', '),
                ucwords(str_replace('_', ' ', strtolower((string) $request->request_type))),
                $request->submitted_by ?? '',
                $request->created_at
                    ? \Illuminate\Support\Carbon::parse($request->created_at)->format('M d, Y')
                    : '',
                ucwords(strtolower((string) $request->status)),
            ]);
        }

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
})->middleware('auth');


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

Route::get('/admin/repair', function (Request $request) {
    $statusFilter = strtolower(trim((string) $request->query('status', 'all')));
    $search = trim((string) $request->query('q', ''));

    // The page speaks in lowercase keys; repairs.status stores the label.
    $statusToDb = [
        'pending'     => 'Pending',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];

    // 1. Load repairs + asset info
    $queue = DB::table('repairs')
        ->leftJoin('assets', 'repairs.Assets_id', '=', 'assets.id')
        ->leftJoin('requests', 'repairs.Request_id', '=', 'requests.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id');

    // The stat cards count the whole queue, so they do not move when a tab is
    // opened or a search is typed.
    $statusTotals = (clone $queue)
        ->select('repairs.status', DB::raw('count(*) as total'))
        ->groupBy('repairs.status')
        ->pluck('total', 'status');

    $statusCounts = [
        'total'       => (int) $statusTotals->sum(),
        'pending'     => (int) ($statusTotals['Pending'] ?? 0),
        'in_progress' => (int) ($statusTotals['In Progress'] ?? 0),
        'completed'   => (int) ($statusTotals['Completed'] ?? 0),
    ];

    // Both filters run in the database, so a tab or a search covers the whole
    // queue and not only the ten rows that happen to be on this page.
    if (isset($statusToDb[$statusFilter])) {
        $queue->where('repairs.status', $statusToDb[$statusFilter]);
    }

    if ($search !== '') {
        $term = '%' . $search . '%';
        $hasEvaluations = Schema::hasTable('repair_evaluations');

        $queue->where(function ($q) use ($term, $hasEvaluations) {
            $q->whereLike('assets.Asset_name', $term)
              ->orWhereLike('assets.Asset_code', $term)
              ->orWhereLike('employee_numbers.Full_Name', $term)
              ->orWhereLike('repairs.Repair_Description', $term)
              ->orWhereLike('repairs.notes', $term);

            if ($hasEvaluations) {
                $q->orWhereExists(function ($sub) use ($term) {
                    $sub->select(DB::raw(1))
                        ->from('repair_evaluations')
                        ->whereColumn('repair_evaluations.repair_id', 'repairs.Repair_id')
                        ->whereLike('repair_evaluations.technician_provider', $term);
                });
            }
        });
    }

    $repairRows = $queue
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
        ->paginate(10)
        ->withQueryString();

    // Map status to what the JS expects (pending, in_progress, etc.)
    $statusMap = [
        'Pending'     => 'pending',
        'In Progress' => 'in_progress',
        'Completed'   => 'completed',
        'Cancelled'   => 'cancelled',
    ];

    // Load servicing/evaluation records keyed by repair id.
    // Guarded: if the table isn't migrated yet, the page still renders with
    // blank servicing fields instead of a 500.
    try {
        $evaluations = Schema::hasTable('repair_evaluations')
            ? DB::table('repair_evaluations')
                ->leftJoin('users', 'repair_evaluations.recorded_by', '=', 'users.id')
                ->select(
                    'repair_evaluations.*',
                    'users.email as recorded_by_email'
                )
                // Only the rows on this page: the modal reads one repair at a time.
                ->whereIn('repair_id', $repairRows->pluck('id')->all())
                ->get()
                ->keyBy('repair_id')
            : collect();
    } catch (\Throwable $e) {
        \Log::warning('Could not load repair evaluations: ' . $e->getMessage());
        $evaluations = collect();
    }

    $resultMap = [
        'Repairable'       => 'repaired',
        'Beyond Repair'    => 'beyond_repair',
        'For Replacement'  => 'for_replacement',
    ];

    $repairs = $repairRows->through(function ($r) use ($statusMap, $evaluations, $resultMap) {
        $ev = $evaluations->get($r->id);

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
            'notes'           => $r->notes,
            'serial_number'   => $r->serial_number,
            'condition'       => $r->condition,
            'purchase_price'  => $r->purchase_price ? (float) $r->purchase_price : null,
            'warranty_months' => $r->warranty_months,
            'asset_location'  => $r->asset_location,
            'supplier'        => null,
            'model'           => null,

            // Servicing & evaluation data (null when not yet documented)
            'technician'          => $ev->technician_provider ?? null,
            'actual_cost'         => ($ev && $ev->repair_cost !== null) ? (float) $ev->repair_cost : null,
            'repair_result'       => ($ev && $ev->repair_result) ? ($resultMap[$ev->repair_result] ?? strtolower(str_replace(' ', '_', $ev->repair_result))) : null,
            'expected_completion' => $ev->expected_completion ?? null,
            'parts_replaced'      => $ev->parts_replaced ?? null,
            'inspection_findings' => $ev->inspection_findings ?? null,
            'admin_remarks'       => $ev->admin_remarks ?? null,
            'recorded_by'         => $ev->recorded_by_email ?? null,

            // For completed repairs, the evaluation's last update is the completion moment
            'completion_date'     => ($r->status === 'Completed' && $ev && !empty($ev->updated_at))
                                        ? date('Y-m-d', strtotime($ev->updated_at))
                                        : null,
        ];
    });

    // 2. Active assets for the "Select Asset" dropdown
$availableAssets = Inventory::excludeRemoved(DB::table('assets'))
        ->where('Lifecycle_Status', 'Active')
        ->orderBy('Asset_name')
        ->get(['id', 'Asset_name', 'Asset_code', 'Lifecycle_Status']);

    return view('admin.repair.repair', [
        'repairs'       => $repairs,
        'availableAssets' => $availableAssets,
        'statusCounts'  => $statusCounts,
        'status'        => $statusFilter,
        'search'        => $search,
    ]);
})->middleware('auth');



// Admin update repair status endpoint
Route::post('/admin/repairs/{id}/status', function ($id, \Illuminate\Http\Request $request) {
    $newStatus = $request->input('status');
    $valid = ['pending', 'in_progress', 'cancelled'];

    if ($newStatus === 'completed') {
        return response()->json([
            'success' => false,
            'message' => 'Use “Complete Repair” to finish a repair — a documented servicing result is required.'
        ], 422);
    }

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

        // When servicing starts → notify the accountable user
        if ($newStatus === 'in_progress' && !empty($repair->Assets_id)) {
            $startedAsset = DB::table('assets')->where('id', $repair->Assets_id)->first();
            if ($startedAsset && $startedAsset->user_id) {
                $startedLabel = ($startedAsset->Asset_name ?? 'Asset')
                    . ($startedAsset->Asset_code ? ' (' . $startedAsset->Asset_code . ')' : '');
                createNotification(
                    (int) $startedAsset->user_id,
                    'Repair Started',
                    "Servicing has started on your {$startedLabel}. The Asset Management Office will notify you when it is finished.",
                    'REPAIR',
                    (int) $id,
                    'repair'
                );
            }
        }

        // When completed → set the asset back to Active + notify owner
        if ($newStatus === 'completed' && !empty($repair->Assets_id)) {
            DB::table('assets')
                ->where('id', $repair->Assets_id)
                ->update([
                    'Lifecycle_Status' => 'Active',
                    'updated_at'       => now(),
                ]);

            // Notify the current owner of the asset
            $asset = DB::table('assets')->where('id', $repair->Assets_id)->first();
            if ($asset && $asset->user_id) {
                $assetLabel = ($asset->Asset_name ?? 'Asset') . ($asset->Asset_code ? ' ' . $asset->Asset_code : '');
                createNotification(
                    (int) $asset->user_id,
                    'Repair Completed',
                    "Your {$assetLabel} has completed its repair and is ready for pickup.",
                    'REPAIR',
                    (int) $id,
                    'repair'
                );
            }
        }

        // When cancelled → restore asset to Active, save reason, notify owner
        if ($newStatus === 'cancelled' && !empty($repair->Assets_id)) {
            $cancellationReason = trim((string) $request->input('cancellation_reason', ''));

            // Restore asset lifecycle
            DB::table('assets')
                ->where('id', $repair->Assets_id)
                ->update([
                    'Lifecycle_Status' => 'Active',
                    'updated_at'       => now(),
                ]);

            // Persist cancellation reason on the repair record
            $notesUpdate = $cancellationReason !== ''
                ? 'Cancelled: ' . $cancellationReason
                : 'Cancelled';

            // Append to existing notes if any
            $existingNotes = trim((string) ($repair->notes ?? ''));
            $finalNotes = $existingNotes !== ''
                ? $existingNotes . ' | ' . $notesUpdate
                : $notesUpdate;

            DB::table('repairs')
                ->where('Repair_id', $id)
                ->update([
                    'notes'      => $finalNotes,
                    'updated_at' => now(),
                ]);

            // Notify the current owner of the asset
            $asset = DB::table('assets')->where('id', $repair->Assets_id)->first();
            if ($asset && $asset->user_id) {
                $assetLabel = ($asset->Asset_name ?? 'Asset') . ($asset->Asset_code ? ' (' . $asset->Asset_code . ')' : '');
                $reasonNote = $cancellationReason !== ''
                    ? ' Reason: ' . $cancellationReason . '.'
                    : '';
                createNotification(
                    (int) $asset->user_id,
                    'Repair Cancelled',
                    "The repair for your {$assetLabel} has been cancelled.{$reasonNote} The asset is now Active again.",
                    'REPAIR',
                    (int) $id,
                    'repair'
                );
            }
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

// =====================================================================
// Repair Servicing & Evaluation endpoints
// =====================================================================

// Save pre-repair servicing information (technician, expected completion,
// estimated cost, description, initial remarks) — allowed before/while Pending.
Route::post('/admin/repairs/{id}/servicing', function ($id, \Illuminate\Http\Request $request) {
    $repair = DB::table('repairs')->where('Repair_id', $id)->first();
    if (!$repair) {
        return response()->json(['success' => false, 'message' => 'Repair not found'], 404);
    }

    if (in_array($repair->status, ['Completed', 'Cancelled'], true)) {
        return response()->json(['success' => false, 'message' => 'This repair is already closed and can no longer be edited.'], 422);
    }

    $technician         = trim((string) $request->input('technician', ''));
    $expectedCompletion = trim((string) $request->input('expected_completion', ''));
    $estimatedCost      = $request->input('estimated_cost');
    $repairDescription  = trim((string) $request->input('repair_description', ''));
    $adminRemarks       = trim((string) $request->input('admin_remarks', ''));

    if ($technician === '') {
        return response()->json(['success' => false, 'message' => 'Technician / Provider is required before starting the repair.'], 422);
    }

    try {
        $now = now();
        $data = [
            'technician_provider' => $technician,
            'expected_completion' => $expectedCompletion !== '' ? $expectedCompletion : null,
            'repair_cost'         => ($estimatedCost !== null && $estimatedCost !== '') ? (float) $estimatedCost : null,
            'admin_remarks'       => $adminRemarks !== '' ? $adminRemarks : null,
            'recorded_by'         => Auth::id(),
            'updated_at'          => $now,
        ];

        $existing = DB::table('repair_evaluations')->where('repair_id', $id)->first();
        if ($existing) {
            DB::table('repair_evaluations')->where('repair_id', $id)->update($data);
        } else {
            $data['repair_id']  = $id;
            $data['created_at'] = $now;
            DB::table('repair_evaluations')->insert($data);
        }

        // Keep the repair description in sync with what was documented
        if ($repairDescription !== '' && $repairDescription !== $repair->Repair_Description) {
            DB::table('repairs')->where('Repair_id', $id)->update([
                'Repair_Description' => $repairDescription,
                'updated_at'         => $now,
            ]);
        }

        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'asset_id'           => $repair->Assets_id,
            'request_id'         => $repair->Request_id,
            'action_type'        => 'REPAIR',
            'notes'              => 'Pre-repair servicing information saved. Technician: ' . $technician,
            'action_description' => 'Repair #' . $id . ' servicing info recorded by ' . (Auth::user()?->email ?? 'admin'),
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicing information saved.',
            'servicing' => [
                'technician'          => $technician,
                'expected_completion' => $data['expected_completion'],
                'estimated_cost'      => $data['repair_cost'],
                'recorded_by'         => Auth::user()?->email ?? 'admin',
            ],
        ]);
    } catch (\Throwable $e) {
        \Log::error('Repair servicing save failed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to save servicing information: ' . $e->getMessage()], 500);
    }
})->middleware('auth');

// Complete the repair with final evaluation. The result drives the lifecycle:
//   repaired         → asset back to Active
//   for_replacement  → admin proceeds with the Replacement process
//   beyond_repair    → admin proceeds toward Disposal or Replacement
Route::post('/admin/repairs/{id}/complete', function ($id, \Illuminate\Http\Request $request) {
    $repair = DB::table('repairs')->where('Repair_id', $id)->first();
    if (!$repair) {
        return response()->json(['success' => false, 'message' => 'Repair not found'], 404);
    }

    if ($repair->status === 'Completed') {
        return response()->json(['success' => false, 'message' => 'This repair is already completed.'], 422);
    }
    if ($repair->status === 'Cancelled') {
        return response()->json(['success' => false, 'message' => 'A cancelled repair cannot be completed.'], 422);
    }

    $resultMap = [
        'repaired'        => 'Repairable',
        'beyond_repair'   => 'Beyond Repair',
        'for_replacement' => 'For Replacement',
    ];

    $resultKey = $request->input('repair_result');
    if (!$resultKey || !isset($resultMap[$resultKey])) {
        return response()->json(['success' => false, 'message' => 'A repair result is required to complete a repair.'], 422);
    }

    $actualCost          = $request->input('actual_cost');
    $partsReplaced       = trim((string) $request->input('parts_replaced', ''));
    $inspectionFindings  = trim((string) $request->input('inspection_findings', ''));
    $adminRemarks        = trim((string) $request->input('admin_remarks', ''));

    try {
        $now = now();
        $dbResult = $resultMap[$resultKey];

        // Upsert the evaluation with the final documentation
        $evaluationData = [
            'repair_result'       => $dbResult,
            'repair_cost'         => ($actualCost !== null && $actualCost !== '') ? (float) $actualCost : null,
            'parts_replaced'      => $partsReplaced !== '' ? $partsReplaced : null,
            'inspection_findings' => $inspectionFindings !== '' ? $inspectionFindings : null,
            'admin_remarks'       => $adminRemarks !== '' ? $adminRemarks : null,
            'recorded_by'         => Auth::id(),
            'updated_at'          => $now,
        ];

        $existing = DB::table('repair_evaluations')->where('repair_id', $id)->first();
        if ($existing) {
            // Keep the technician/expected completion already documented pre-repair
            unset($evaluationData['technician_provider'], $evaluationData['expected_completion']);
            DB::table('repair_evaluations')->where('repair_id', $id)->update($evaluationData);
        } else {
            $evaluationData['repair_id']  = $id;
            $evaluationData['created_at'] = $now;
            DB::table('repair_evaluations')->insert($evaluationData);
        }

        // Repair record: Completed + final result
        DB::table('repairs')->where('Repair_id', $id)->update([
            'status'        => 'Completed',
            'Repair_result' => $dbResult,
            'Repair_Cost'   => ($actualCost !== null && $actualCost !== '') ? (float) $actualCost : $repair->Repair_Cost,
            'updated_at'    => $now,
        ]);

        // Asset lifecycle: only a successfully repaired asset returns to Active
        $asset = DB::table('assets')->where('id', $repair->Assets_id)->first();
        if ($resultKey === 'repaired' && $asset) {
            DB::table('assets')->where('id', $asset->id)->update([
                'Lifecycle_Status' => 'Active',
                'updated_at'       => $now,
            ]);
        }

        // Notify the owner / requester
        if ($asset && !empty($asset->user_id)) {
            $assetLabel = ($asset->Asset_name ?? 'Asset') . ($asset->Asset_code ? ' (' . $asset->Asset_code . ')' : '');
            $resultMessages = [
                'repaired'        => "Your {$assetLabel} has been successfully repaired and is Active again.",
                'for_replacement' => "Your {$assetLabel} was evaluated For Replacement. The Asset Management Office will begin the replacement process.",
                'beyond_repair'   => "Your {$assetLabel} was evaluated as Beyond Repair and will proceed to disposal or replacement.",
            ];
            createNotification(
                (int) $asset->user_id,
                'Repair Completed',
                $resultMessages[$resultKey],
                'REPAIR',
                (int) $id,
                'repair'
            );
        }

        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'asset_id'           => $repair->Assets_id,
            'request_id'         => $repair->Request_id,
            'action_type'        => 'REPAIR',
            'notes'              => 'Repair completed. Result: ' . $dbResult . ($actualCost !== null && $actualCost !== '' ? '. Actual cost: ₱' . number_format((float) $actualCost, 2) : ''),
            'action_description' => 'Repair #' . $id . ' completed with result "' . $dbResult . '" by ' . (Auth::user()?->email ?? 'admin'),
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Repair completed. Result: ' . $dbResult,
            'result'  => $resultKey,
            'asset_status' => $resultKey === 'repaired' ? 'Active' : ($asset->Lifecycle_Status ?? null),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Repair completion failed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to complete repair: ' . $e->getMessage()], 500);
    }
})->middleware('auth');

// Delete a closed repair record.
//
// The page used to remove the card from the browser's array and announce
// "Repair request deleted successfully" without sending anything to the
// server, so the record came straight back on the next reload. A repair that
// is still open is never deleted, and neither is one whose outcome is the
// reason the asset is sitting in "For Repair" (waiting for replacement or
// disposal) - that history has to outlive the record it explains.
Route::post('/admin/repairs/{id}/delete', function ($id) {
    $repair = DB::table('repairs')->where('Repair_id', $id)->first();

    if (! $repair) {
        return response()->json(['success' => false, 'message' => 'Repair not found'], 404);
    }

    if (! in_array($repair->status, ['Completed', 'Cancelled'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Only a completed or cancelled repair can be deleted. Cancel it first if it will not be carried out.',
        ], 422);
    }

    $result = trim((string) ($repair->Repair_result ?? ''));

    if ($repair->status === 'Completed' && $result !== '' && $result !== 'Repairable') {
        return response()->json([
            'success' => false,
            'message' => 'This repair records why the asset is ' . $result . '. Keep it until the replacement or disposal is recorded.',
        ], 422);
    }

    try {
        DB::transaction(function () use ($id, $repair) {
            // One evaluation per repair; its foreign key cascades, but deleting
            // it explicitly keeps this correct on a database where the
            // constraint was created without the cascade.
            DB::table('repair_evaluations')->where('repair_id', $id)->delete();
            DB::table('repairs')->where('Repair_id', $id)->delete();

            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'asset_id'           => $repair->Assets_id,
                'request_id'         => $repair->Request_id,
                'action_type'        => 'REPAIR',
                'notes'              => 'Repair record deleted (' . $repair->status . ').',
                'action_description' => 'Repair #' . $id . ' deleted by ' . (Auth::user()?->email ?? 'admin'),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Repair request deleted successfully.',
        ]);
    } catch (\Throwable $e) {
        \Log::error('Repair delete failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete the repair: ' . $e->getMessage(),
        ], 500);
    }
})->middleware('auth');

Route::post('/admin/replacements/create', function (\Illuminate\Http\Request $request) {
    $requestId         = $request->input('request_id');
    $assetId           = $request->input('asset_id');
    $repairId          = $request->input('repair_id');
    $reason            = trim((string) $request->input('reason', ''));
    $replacementReason = $request->input('replacement_reason', 'Beyond Repair');

    if (!$assetId) {
        return response()->json(['success' => false, 'message' => 'Asset ID is required'], 400);
    }

    if ($reason === '') {
        return response()->json([
            'success' => false,
            'message' => 'A reason is required so the user knows why the asset is being replaced.'
        ], 422);
    }

    try {
        $asset = DB::table('assets')->where('id', $assetId)->first();
        if (!$asset) {
            return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
        }

        // Create replacement record
        $replacementId = DB::table('replacements')->insertGetId([
            'Request_id'          => $requestId,
            'old_assets_id'       => $assetId,
            'new_assets_id'       => $assetId, // placeholder until a real new asset is linked
            'Approve_by'          => Auth::user()?->full_name
                                    ?? Auth::user()?->email
                                    ?? 'Admin',
            'reason'              => $reason,
            'replacement_reason'  => $replacementReason,
            'notes'               => 'Created from repair request. Reason: ' . $reason,
            'Replacement_Date'    => now(),
            'status'              => 'Approved',   // ← was 'Pending' — skip approval step
            'created_at'          => now(),
            'updated_at'          => now(),
        ], 'Replacement_id');

        // Update asset lifecycle
        DB::table('assets')->where('id', $assetId)->update([
            'Lifecycle_Status' => 'For Replacement',
            'updated_at'       => now(),
        ]);

        // Optionally mark the related repair as cancelled
        if ($repairId) {
            // First try to read existing notes so we can append safely
            $existingNotes = DB::table('repairs')
                ->where('Repair_id', $repairId)
                ->value('notes');

            $newNotes = trim(($existingNotes ? $existingNotes . ' | ' : '') . 'Sent to replacement: ' . $reason);

            DB::table('repairs')
                ->where('Repair_id', $repairId)
                ->update([
                    'status'     => 'Cancelled',
                    'notes'      => $newNotes,
                    'updated_at' => now(),
                ]);
        }

        // Find the original requester and notify them
        $requesterId = null;

        if ($requestId) {
            $requesterId = DB::table('requests')->where('id', $requestId)->value('user_id');
        }

        // Fallback: owner of the asset
        if (!$requesterId && !empty($asset->user_id)) {
            $requesterId = (int) $asset->user_id;
        }

        if ($requesterId) {
            $assetLabel = ($asset->Asset_code ?? '') . ' – ' . ($asset->Asset_name ?? 'Asset');

            createNotification(
                $requesterId,
                'Repair Converted to Replacement',
                'Your repair request for ' . $assetLabel . ' cannot be repaired and has been sent for replacement.'
                    . "\n\nReason from Asset Management: " . $reason
                    . "\n\nYou will be notified again when a new asset is ready for pickup.",
                'REPLACEMENT',
                (int) $replacementId,
                'replacement'
            );
        }

        // Audit log
        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'asset_id'           => $assetId,
            'request_id'         => $requestId,
            'action_type'        => 'REPLACEMENT',
            'notes'              => 'Repair converted to replacement. Reason: ' . $reason,
            'action_description' => 'Asset #' . $assetId . ' sent to replacement from repair',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Replacement request created and user notified',
            'id'      => $replacementId,
        ]);
    } catch (\Exception $e) {
        \Log::error('Replacement creation error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to create replacement: ' . $e->getMessage(),
        ], 500);
    }
})->middleware('auth');


Route::post('/admin/disposals/create', function (\Illuminate\Http\Request $request) {
    $requestId      = $request->input('request_id');
    $assetId        = $request->input('asset_id');
    $repairId       = $request->input('repair_id');
    $reason         = trim((string) $request->input('reason', ''));
    $disposalReason = $request->input('disposal_reason', 'Beyond Repair');

    if (!$assetId) {
        return response()->json(['success' => false, 'message' => 'Asset ID is required'], 400);
    }

    if ($reason === '') {
        return response()->json([
            'success' => false,
            'message' => 'A reason is required so the user knows why the asset is being disposed.'
        ], 422);
    }

    try {
        $asset = DB::table('assets')->where('id', $assetId)->first();
        if (!$asset) {
            return response()->json(['success' => false, 'message' => 'Asset not found'], 404);
        }

        // Already disposed?
        if (in_array($asset->Lifecycle_Status ?? '', ['Disposal', 'Disposed'], true)) {
            return response()->json(['success' => false, 'message' => 'Asset is already disposed'], 422);
        }

        // Create disposal record (no pending/approval — done immediately from repair)
        $disposalId = DB::table('disposals')->insertGetId([
            'Request_id'      => $requestId,
            'Asset_id'        => $assetId,
            'Approve_by'      => Auth::user()?->full_name ?? Auth::user()?->email ?? 'Admin',
            'Description'     => $reason,
            'disposal_reason' => $disposalReason,
            'disposal_date'   => now()->toDateString(),
            'notes'           => 'Created from repair request. Reason: ' . $reason,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'Disposal_ID');

        // Mark asset as disposed
        DB::table('assets')->where('id', $assetId)->update([
            'Lifecycle_Status' => 'Disposal',
            'updated_at'       => now(),
        ]);

        // Cancel related repair if provided
        if ($repairId) {
            $existingNotes = DB::table('repairs')
                ->where('Repair_id', $repairId)
                ->value('notes');

            $newNotes = trim(($existingNotes ? $existingNotes . ' | ' : '') . 'Sent to disposal: ' . $reason);

            DB::table('repairs')
                ->where('Repair_id', $repairId)
                ->update([
                    'status'     => 'Cancelled',
                    'notes'      => $newNotes,
                    'updated_at' => now(),
                ]);
        }

        // Audit log
        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'asset_id'           => $assetId,
            'action_type'        => 'DISPOSAL',
            'notes'              => 'Disposed from repair. Reason: ' . $reason,
            'action_description' => 'Asset #' . $assetId . ' disposed via repair (no approval required)',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Notify the original requester / asset owner
        $requesterId = null;
        if ($requestId) {
            $requesterId = DB::table('requests')->where('id', $requestId)->value('user_id');
        }
        if (!$requesterId && !empty($asset->user_id)) {
            $requesterId = (int) $asset->user_id;
        }

        if ($requesterId) {
            $assetLabel = ($asset->Asset_code ?? '') . ' – ' . ($asset->Asset_name ?? 'Asset');

            createNotification(
                (int) $requesterId,
                'Asset Sent to Disposal',
                'Your asset ' . $assetLabel . ' could not be repaired and has been sent for disposal.'
                    . "\n\nReason from Asset Management: " . $reason
                    . "\n\nIf you have questions, please contact the Asset Management Office.",
                'DISPOSAL',
                (int) $disposalId,
                'disposal'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Asset disposed and user notified',
            'id'      => $disposalId,
        ]);
    } catch (\Exception $e) {
        \Log::error('Disposal creation error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to create disposal: ' . $e->getMessage(),
        ], 500);
    }
})->middleware('auth');

// Admin replacement page
Route::get('/admin/replacement', function (Request $request) {
    $statusFilter = (string) $request->query('status', 'all');
    $search = trim((string) $request->query('q', ''));

    $base = DB::table('replacements')
        ->leftJoin('assets as old_assets', 'replacements.old_assets_id', '=', 'old_assets.id')
        ->leftJoin('assets as new_assets', 'replacements.new_assets_id', '=', 'new_assets.id')
        ->leftJoin('requests', 'replacements.Request_id', '=', 'requests.id')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id');

    // The cards count every replacement, so they do not move with a filter.
    $statusTotals = (clone $base)
        ->select('replacements.status', DB::raw('count(*) as total'))
        ->groupBy('replacements.status')
        ->pluck('total', 'status');

    $query = clone $base;

    // Filtering and searching happen in the database: a tab or a search covers
    // every replacement, not only the rows that fit on one page.
    if (in_array($statusFilter, ['Pending', 'Approved', 'Ordered', 'Received', 'Complete', 'Cancelled'], true)) {
        $query->where('replacements.status', $statusFilter);
    }

    if ($search !== '') {
        $term = '%' . $search . '%';

        $query->where(function ($q) use ($term) {
            $q->whereLike('old_assets.Asset_name', $term)
              ->orWhereLike('old_assets.Asset_code', $term)
              ->orWhereLike('new_assets.Asset_name', $term)
              ->orWhereLike('new_assets.Asset_code', $term)
              ->orWhereLike('employee_numbers.Full_Name', $term)
              ->orWhereLike('departments.Name', $term)
              ->orWhereLike('replacements.reason', $term)
              ->orWhereLike('replacements.notes', $term);
        });
    }

    $replacements = $query
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
        ->paginate(10)
        ->withQueryString();

    $totalReplacements     = (int) $statusTotals->sum();
    $pendingReplacements   = (int) ($statusTotals['Pending'] ?? 0);
    $approvedReplacements  = (int) ($statusTotals['Approved'] ?? 0);
    $receivedReplacements  = (int) ($statusTotals['Received'] ?? 0);

    return view('admin.replacement.replacement', compact(  // ← change to your real view name
        'replacements',
        'totalReplacements',
        'pendingReplacements',
        'approvedReplacements',
        'receivedReplacements'
    ) + [
        'statusFilter' => $statusFilter,
        'search'       => $search,
    ]);
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
            
            // If marking as Complete, retire the old asset and create the disposal record
            if ($newStatus === 'Complete' && $replacement->old_assets_id) {
                $oldAsset = DB::table('assets')->where('id', $replacement->old_assets_id)->first();
                
                if ($oldAsset) {
                    // Retire the old asset. Disposals::DISPOSED_STATUS is the one
                    // value assets.Lifecycle_Status accepts for a retired asset:
                    // writing the UI's "Disposed" is refused by PostgreSQL's CHECK
                    // constraint, which rolled the whole transaction back and left
                    // the replacement un-completable.
                    DB::table('assets')->where('id', $replacement->old_assets_id)->update([
                        'Lifecycle_Status' => Disposals::DISPOSED_STATUS,
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

            // 1. Create NEW asset (still Acquired — becomes Active only when marked Received)
            $newAssetId = DB::table('assets')->insertGetId([
                'user_id'               => $old->user_id,
                'Asset_code'            => $validated['Asset_code'],
                'Asset_name'            => $validated['Asset_name'],
                'Category'              => $validated['Category'] ?? $old->Category,
                'Condition'             => 'New',
                'Lifecycle_Status'      => 'Acquired',          // ← keep Acquired until pickup
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

            // Save photo if uploaded
            $filePath = $request->hasFile('asset_photo')
                ? Media::storeUploadedFile($request->file('asset_photo'), 'assets')
                : null;

            if ($filePath) {
                $file = $request->file('asset_photo');
                DB::table('asset_files')->insert([
                    'Asset_id'    => $newAssetId,
                    'file_name'   => $file->getClientOriginalName(),
                    'file_path'   => $filePath,
                    'file_size'   => $file->getSize(),
                    'mime_type'   => $file->getClientMimeType(),
                    'url'         => Media::url($filePath),
                    'uploaded_at' => now()->toDateTimeString(),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // 2. Generate QR on the media disk so it resolves from any device
            $qrUrl = null;
            try {
                $qrPath = AssetQr::saveSticker((int) $newAssetId);
                if ($qrPath) {
                    $qrUrl = Media::url($qrPath);
                }
            } catch (\Throwable $e) {
                Log::warning('QR generation failed on replacement link', ['error' => $e->getMessage()]);
            }

            // 3. Link on replacement record (do NOT touch old asset status yet)
            DB::table('replacements')->where('Replacement_id', $id)->update([
                'new_assets_id' => $newAssetId,
                'updated_at'    => now(),
            ]);

            // 4. Audit
            DB::table('audit_logs')->insert([
                'user_id'            => Auth::id(),
                'asset_id'           => $newAssetId,
                'action_type'        => 'CREATE',
                'notes'              => 'New asset created via replacement #' . $id,
                'action_description' => 'Linked to old asset ' . ($old->Asset_code ?? $old->id) . '. Waiting for physical pickup.',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // 5. Notify the original requester
            $requesterId = null;

            // Prefer the request that created the replacement
            $reqId = $replacement->Request_id ?? $replacement->request_id ?? null;
            if ($reqId) {
                $requesterId = DB::table('requests')->where('id', $reqId)->value('user_id');
            }

            // Fallback to the owner of the old asset
            if (!$requesterId && !empty($old->user_id)) {
                $requesterId = (int) $old->user_id;
            }

            if ($requesterId) {
                createNotification(
                    $requesterId,
                    'New Asset Ready for Pickup',
                    'Your replacement request has been fulfilled. New asset '
                        . $validated['Asset_code'] . ' (' . $validated['Asset_name'] . ') '
                        . 'is ready for pickup at the Asset Management Office. '
                        . 'Please bring the old asset for exchange.',
                    'REPLACEMENT',
                    (int) $id,
                    'replacement'
                );
            }

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
                'status'       => 'approved',
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

// Approve a pending replacement
Route::match(['post', 'patch'], '/admin/replacements/{id}/approve', function (Request $request, $id) {
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

    if (($replacement->status ?? '') !== 'Pending') {
        return $request->wantsJson()
            ? response()->json(['success' => false, 'message' => 'Only Pending replacements can be approved'], 422)
            : back()->with('error', 'Only Pending replacements can be approved.');
    }

    $notes = trim((string) $request->input('notes', ''));

    try {
        DB::table('replacements')->where('Replacement_id', $id)->update([
            'status'     => 'Approved',
            'Approve_by' => $user->full_name ?? $user->email ?? 'Admin',
            'notes'      => $notes !== ''
                ? trim(($replacement->notes ? $replacement->notes . ' | ' : '') . $notes)
                : $replacement->notes,
            'updated_at' => now(),
        ]);

        // Audit log
        DB::table('audit_logs')->insert([
            'user_id'            => Auth::id(),
            'action_type'        => 'APPROVAL',
            'notes'              => 'Replacement #' . $id . ' approved' . ($notes ? ': ' . $notes : ''),
            'action_description' => 'Replacement request approved. Admin can now link a new asset.',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Optional: notify the original requester that their replacement was approved
        $requesterId = null;
        $reqId = $replacement->Request_id ?? $replacement->request_id ?? null;
        if ($reqId) {
            $requesterId = DB::table('requests')->where('id', $reqId)->value('user_id');
        }
        if (!$requesterId && !empty($replacement->old_assets_id)) {
            $requesterId = DB::table('assets')->where('id', $replacement->old_assets_id)->value('user_id');
        }

        if ($requesterId) {
            createNotification(
                (int) $requesterId,
                'Replacement Request Approved',
                'Your replacement request has been approved by Asset Management.'
                    . ($notes ? "\n\nNote: " . $notes : '')
                    . "\n\nA new asset will be prepared and you will be notified when it is ready for pickup.",
                'REPLACEMENT',
                (int) $id,
                'replacement'
            );
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Replacement approved']);
        }

        return redirect('/admin/replacement')->with('success', 'Replacement approved successfully.');
    } catch (\Throwable $e) {
        \Log::error('Approve replacement failed: ' . $e->getMessage());
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

Route::match(['post', 'patch'], '/admin/replacements/{id}/approve', function (Request $request, $id) {
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

    if (($replacement->status ?? '') !== 'Pending') {
        return back()->with('error', 'Only Pending replacements can be approved.');
    }

    $notes = trim((string) $request->input('notes', ''));

    DB::table('replacements')->where('Replacement_id', $id)->update([
        'status'     => 'Approved',
        'Approve_by' => $user->full_name ?? $user->email ?? 'Admin',
        'notes'      => $notes !== ''
            ? trim(($replacement->notes ? $replacement->notes . ' | ' : '') . $notes)
            : $replacement->notes,
        'updated_at' => now(),
    ]);

    DB::table('audit_logs')->insert([
        'user_id'            => Auth::id(),
        'action_type'        => 'APPROVAL',
        'notes'              => 'Replacement #' . $id . ' approved' . ($notes ? ': ' . $notes : ''),
        'action_description' => 'Replacement request approved',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    return redirect('/admin/replacement')->with('success', 'Replacement approved successfully.');
})->middleware('auth');

Route::match(['delete', 'post'], '/admin/replacements/{id}', function (Request $request, $id) {
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

    DB::table('replacements')->where('Replacement_id', $id)->delete();

    DB::table('audit_logs')->insert([
        'user_id'            => Auth::id(),
        'action_type'        => 'DELETE',
        'notes'              => 'Deleted replacement record #' . $id,
        'action_description' => 'Replacement record permanently deleted',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    if ($request->wantsJson() || $request->ajax()) {
        return response()->json(['success' => true, 'message' => 'Replacement deleted']);
    }

    return redirect('/admin/replacement')->with('success', 'Replacement record deleted.');
})->middleware('auth');

// Admin pullout page
Route::get('/admin/pullout', function (Request $request) {
    $search = trim((string) $request->query('q', ''));
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
                ->whereIn('pullouts.status', ['pending', 'approved']);   // ← only show active pullouts

            // The search is answered by the database, so it covers every pullout
            // record and not only the rows of the page that is open.
            if ($search !== '') {
                $term = '%' . $search . '%';
                $hasDestination = Schema::hasColumn('pullouts', 'destination');

                $query->where(function ($q) use ($term, $hasDestination) {
                    $q->whereLike('employee_numbers.Full_Name', $term)
                      ->orWhereLike('pullouts.Description', $term)
                      ->orWhereLike('pullouts.notes', $term)
                      ->orWhereLike('pullouts.Approve_by', $term);

                    if ($hasDestination) {
                        $q->orWhereLike('pullouts.destination', $term);
                    }

                    // The assets on a bulk pullout live in pullout_items…
                    $q->orWhereExists(function ($sub) use ($term) {
                        $sub->select(DB::raw(1))
                            ->from('pullout_items')
                            ->join('assets', 'pullout_items.asset_id', '=', 'assets.id')
                            ->whereColumn('pullout_items.pullout_id', 'pullouts.id')
                            ->where(function ($aq) use ($term) {
                                $aq->whereLike('assets.Asset_name', $term)
                                   ->orWhereLike('assets.Asset_code', $term);
                            });
                    });

                    // …and a record written before pullout_items existed keeps its
                    // one asset on pullouts.asset_id, so it is searched too.
                    $q->orWhereExists(function ($sub) use ($term) {
                        $sub->select(DB::raw(1))
                            ->from('assets')
                            ->whereColumn('assets.id', 'pullouts.asset_id')
                            ->where(function ($aq) use ($term) {
                                $aq->whereLike('assets.Asset_name', $term)
                                   ->orWhereLike('assets.Asset_code', $term);
                            });
                    });
                });
            }

            $pulloutRows = $query
                ->orderByDesc('pullouts.pullout_date')
                ->paginate(10)
                ->withQueryString();

            // Only the assets of the rows on this page.
            $pulloutItems = DB::table('pullout_items')
                ->leftJoin('assets', 'pullout_items.asset_id', '=', 'assets.id')
                ->select('pullout_items.pullout_id', 'pullout_items.asset_id', 'assets.Asset_name', 'assets.Asset_code')
                ->whereIn('pullout_items.pullout_id', $pulloutRows->pluck('id')->all())
                ->get()
                ->groupBy('pullout_id');

            $pulloutRecords = $pulloutRows->through(function ($r) use ($pulloutItems) {
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

            $availableAssets = Asset::with('user.employee_numbers')
                ->inInventory()
                ->where('Lifecycle_Status', '!=', 'Pullout')
                ->whereNotIn('id', $blockedAssetIds)
                ->orderBy('Asset_name')
                ->get()
                ->map(function ($a) {
                    return (object) [
                        'id'               => $a->id,
                        'name'             => $a->Asset_name ?? '',
                        'asset_code'       => $a->Asset_code ?? '',
                        'Lifecycle_Status' => $a->Lifecycle_Status ?? '',
                        'assignedUser'     => (object) ['name' => $a->user?->display_name ?? 'Unassigned'],
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
    ) + ['search' => $search]);
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

// ─── Transfer / Employee Relocation ─────────────────────────────────────
// One employee hands their assigned assets to another. The assets keep their
// Asset ID, Asset Code, QR Code and history; only the accountable employee
// changes. The page is only reachable by a signed-in user.
Route::get('/admin/transfer', [TransferController::class, 'index'])
    ->middleware('auth')
    ->name('admin.transfer');

Route::post('/admin/transfer', [TransferController::class, 'store'])
    ->middleware('auth')
    ->name('admin.transfer.store');

// ─── Reports ────────────────────────────────────────────────────────────
// One reporting module, two roles. The page, its CSV export and its print
// sheet all carry the report's criteria in the query string, so a report is a
// link: it can be bookmarked, exported and printed and always describes the
// same records. Apply Filters posts, and a successful attempt redirects to
// that link; a failed one explains itself and leaves the previous report on
// the screen.
//
// Who is asking decides which prefix is reachable at all — /admin/reports is
// the Asset Management Office (already guarded by RequireAdminArea), and
// /department-head/reports is a Department Head's own. The scope of the data
// inside a report is read from the signed-in account by Support\Reports, never
// from the query string, so no filter can widen it.
Route::get('/admin/reports', [ReportController::class, 'show'])
    ->middleware('auth')
    ->name('admin.reports');

Route::post('/admin/reports/generate', [ReportController::class, 'generate'])
    ->middleware('auth')
    ->name('admin.reports.generate');

Route::get('/admin/reports/export', [ReportController::class, 'export'])
    ->middleware('auth')
    ->name('admin.reports.export');

Route::get('/admin/reports/print', [ReportController::class, 'print'])
    ->middleware('auth')
    ->name('admin.reports.print');

Route::get('/department-head/reports', [ReportController::class, 'show'])
    ->middleware('auth')
    ->name('department_head.reports');

Route::post('/department-head/reports/generate', [ReportController::class, 'generate'])
    ->middleware('auth')
    ->name('department_head.reports.generate');

Route::get('/department-head/reports/export', [ReportController::class, 'export'])
    ->middleware('auth')
    ->name('department_head.reports.export');

Route::get('/department-head/reports/print', [ReportController::class, 'print'])
    ->middleware('auth')
    ->name('department_head.reports.print');

// Live availability check for a custom Asset Code (single code or a bulk list)
Route::get('/admin/assets/check-code', function (Request $request) {
    $raw = (string) $request->query('codes', (string) $request->query('code', ''));

    return response()->json([
        'codes' => AssetCode::availability(AssetCode::parseList($raw)),
    ]);
})->middleware('auth');

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
        'asset_code_mode' => 'nullable|in:auto,custom',
        'custom_codes_text' => 'nullable|string',
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

    // ─── Asset code: generated, or the office's existing code ────────────
    // A custom code is the client's own numbering, so it has to be verified
    // before anything is written: the same code may not identify a second
    // asset, and it may not be repeated inside one submission. Auto mode keeps
    // the original behaviour and quietly re-rolls a collision.
    $codeMode = $validated['asset_code_mode'] ?? 'auto';
    $customCodes = [];

    if ($codeMode === 'custom') {
        $customCodes = AssetCode::parseList(
            $validated['custom_codes_text'] ?? ($validated['asset_code'] ?? '')
        );

        $problem = AssetCode::firstProblem($customCodes, $quantity);

        if ($problem) {
            return back()->withErrors(['asset_code' => $problem])->withInput();
        }
    }

    // Handle file upload
    $fileName = null;
    $filePath = null;
    $fileSize = null;
    $mime = null;
    $url = null;
    if ($request->hasFile('asset_photo')) {
        $file = $request->file('asset_photo');
        $filePath = Media::storeUploadedFile($file, 'assets');
        $fileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mime = $file->getClientMimeType();
        $url = Media::url($filePath);
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
            $codeMode,
            $customCodes,
            $fileName,
            $filePath,
            $fileSize,
            $mime,
            $url,
            &$createdAssets
        ) {
            // The registry form always posts asset_code, but a request without it (or
            // with an empty one) must still register rather than 500 on a missing key.
            $baseAssetCode = ($validated['asset_code'] ?? '') ?: AssetCode::generate();

            $makeUniqueAssetCode = function (int $index) use ($baseAssetCode) {
                $candidateBase = $baseAssetCode;
                $candidate = $index === 1
                    ? $candidateBase
                    : $candidateBase . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);

                while (AssetCode::exists($candidate)) {
                    $candidateBase = AssetCode::generate();
                    $candidate = $index === 1
                        ? $candidateBase
                        : $candidateBase . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                }

                return $candidate;
            };

            $saveQrToAsset = function (Asset $asset, string $assetCode) {
                try {
                    // The sticker image is minted from the asset's own signed
                    // payload, and both halves - image path and the exact text
                    // it encodes - are recorded together.
                    $qrPath = AssetQr::saveSticker((int) $asset->id, 300);

                    if ($qrPath) {
                        return Media::url($qrPath);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to save QR image for bulk asset', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }

                return null;
            };

            for ($index = 1; $index <= $quantity; $index++) {
                $assetCode = $codeMode === 'custom'
                    ? $customCodes[$index - 1]
                    : $makeUniqueAssetCode($index);

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

    // A storage outage must not look like a successful registration with a
    // missing photo: the operator needs to know the image was dropped so they
    // can re-upload it once the disk is reachable again.
    $photoWarning = ($request->hasFile('asset_photo') && ! $filePath)
        ? ' The photo could not be saved to storage, so these assets have no image.'
        : '';

    return redirect('/admin/assets/registry')
        ->with('success', 'Successfully registered ' . count($createdAssets) . ' asset(s).' . $photoWarning)
        ->with('bulk_qr_labels', $createdAssets)
        ->with('bulk_registered_count', count($createdAssets));
})->name('admin.assets.store');

Route::post('/admin/requests/{id}/approve', function (Request $request, $id) {
    $actor        = Auth::user();
    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (!$requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    // ─── Who may decide a request ────────────────────────────────────────
    // Approving is the Asset Management Office's job, for every request type.
    // Before this, only a disposal was guarded: any signed-in account could
    // approve a repair, transfer, replacement or pullout by posting to this
    // URL. The check is here, on the server, for the whole route.
    if (! Access::canDecideRequests($actor)) {
        Approval::recordRefusal($actor, $requestRecord, 'approve');

        return response()->json(['message' => Access::decideMessage()], 403);
    }

    // ─── Nobody decides their own request ────────────────────────────────
    // Compared against requests.user_id - the submitter stored in the
    // database - so no crafted form, changed URL or hidden field can pretend
    // the request belongs to somebody else.
    if (Access::isSubmitter($actor, $requestRecord)) {
        Approval::recordRefusal($actor, $requestRecord, 'approve');

        return response()->json(['message' => Access::selfApprovalMessage()], 403);
    }

    if (strtolower((string) $requestRecord->status) !== 'pending') {
        return response()->json(['message' => 'Only pending requests can be approved.'], 422);
    }

    $approvedBy  = $actor?->email ?? $actor?->display_name ?? 'Admin';
    $requestType = strtolower((string) $requestRecord->request_type);
    $isDisposal  = $requestType === 'disposal';

    // ─── Is this approval also a request for the Executive Director? ─────
    // Only a transfer can be forwarded, and only when the office asks for it.
    // A forwarded transfer is approved here but deliberately NOT executed: the
    // assets change hands when the Executive Director decides, and until that
    // decision comes back they stay with the employee who holds them. That is
    // what stops "approval recorded" from quietly meaning "asset moved".
    $wantsForward = $requestType === 'transfer' && $request->boolean('forward_for_review');

    // Who the invitation goes to is the office's answer, not the deployment's:
    // a new Executive Director must not need an environment variable changed.
    // The address is validated before anything is written, so a typo cannot
    // leave an approval that was meant to go out for review with nowhere to go.
    $reviewRecipient = trim((string) $request->input('recipient_email', ''));

    if ($wantsForward && ! TransferReviews::validEmail($reviewRecipient ?: TransferReviews::defaultRecipientEmail())) {
        return response()->json([
            'message' => 'Enter the email address the Executive Director actually reads. That mailbox is where the review link goes, and the only place the verification code can be sent.',
        ], 422);
    }

    // A request that cannot be reviewed is refused *before* anything is
    // recorded, so the office is never left with an approval that was meant to
    // go out for review and a request that quietly moved the assets instead.
    if ($wantsForward && ($forwardProblem = TransferReviews::eligibilityProblem($requestRecord, false))) {
        return response()->json([
            'message' => $forwardProblem . ' Approve the transfer here instead, or correct the request first.',
        ], 422);
    }

    $forwardForReview = $wantsForward;

    // ─── A disposal approval records a real disposal ─────────────────
    // The reason and date are read from the request (a disposal request carries
    // its reason inside the requester's note), so the Admin confirms rather than
    // re-types what the requester already submitted.
    $disposalReason = $isDisposal
        ? DisposalReason::normalize($request->input('disposal_reason'), $requestRecord->Note)
        : null;

    $disposalDate = $isDisposal
        ? (trim((string) $request->input('disposal_date', '')) ?: now()->toDateString())
        : null;

    if ($isDisposal) {
        $dateCheck = $request->validate([
            'disposal_date' => 'nullable|date',
        ], [
            'disposal_date.date' => 'The disposal date is not a valid date.',
        ]);

        $disposalDate = ($dateCheck['disposal_date'] ?? null) ?: now()->toDateString();
    }

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

    // ─── Assets the Executive Director is already deciding on ────────────
    // A forwarded transfer is on hold: the office approved the request but
    // nothing has changed hands, and until that decision comes back no other
    // request may take those assets out of the employee's custody. Without this
    // the second door would quietly do what the first review is still weighing
    // up — and, once the assets had moved, the invitation would open onto an
    // "out of date" page while the transfer it asked about was already done.
    // Refused before anything is recorded, so the approval never half-exists.
    if (in_array($requestType, ['transfer', 'pullout'], true)) {
        if ($hold = TransferReviews::assetsOnHold($assetIds)) {
            return response()->json(['message' => TransferReviews::holdRefusal($hold)], 422);
        }
    }

    // Everything the audit entry and the notification below need to name.
    $assetCodes = [];
    $existingAssetIds = [];
    foreach (DB::table('assets')->whereIn('id', $assetIds)->get(['id', 'Asset_code']) as $row) {
        $assetCodes[(int) $row->id] = $row->Asset_code;
        $existingAssetIds[]        = (int) $row->id;
    }

    // ─── Assets the receiving employee already holds ──────────────────────
    // Approving a transfer executes it, and for an asset the receiving employee
    // already has there is nothing to execute. Named here so the answer the
    // office reads says what really happened — "nothing changed hands" — rather
    // than a blanket "approved successfully" over a handover that never took
    // place and a request the Executive Director would be asked about for nothing.
    $assigneeId = (int) ($requestRecord->assign_to_user_id ?? 0);
    $assignee   = $assigneeId > 0
        ? (AssetTransfer::userLabels()[$assigneeId]['name'] ?? ('user #' . $assigneeId))
        : 'the receiving employee';

    $alreadyHeld = ($requestType === 'transfer' && $assigneeId > 0)
        ? DB::table('assets')->whereIn('id', $assetIds)->where('user_id', $assigneeId)->pluck('Asset_code', 'id')->all()
        : [];

    $alreadyHeldIds = array_map('intval', array_keys($alreadyHeld));

    // ─── Disposal requests must be approvable ───────────────────────
    if ($isDisposal) {
        $missing = array_values(array_diff($assetIds, $existingAssetIds));

        if (! empty($missing)) {
            return response()->json([
                'message' => 'This disposal request refers to asset(s) that no longer exist (#'
                    . implode(', #', $missing)
                    . '), so there is nothing to retire. The request cannot be approved.',
            ], 422);
        }

        if (trim((string) $requestRecord->Note) === '' && trim((string) $request->input('disposal_reason', '')) === '') {
            return response()->json([
                'message' => 'This disposal request has neither a note nor a reason, so the official disposal form would have nothing to explain. Add a reason before approving.',
            ], 422);
        }
    }

    try {
        DB::transaction(function () use ($requestRecord, $approvedBy, $requestType, $assetIds, $isDisposal, $disposalReason, $disposalDate, $assetCodes, $forwardForReview) {

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
                // Approving a disposal request authorizes preparation of the
                // official NU Lipa Disposal Form. It does not dispose of
                // anything: the asset keeps the lifecycle status it had, no
                // disposal record is written, and nothing leaves the inventory.
                // The asset is retired only when an Admin finalizes the
                // disposal on the Disposal Forms tab, after the signed form has
                // come back from Accountancy.
                if ($isDisposal) {
                    if (! DB::table('assets')->where('id', $assetId)->exists()) {
                        throw new \RuntimeException(
                            'Asset #' . $assetId . ' no longer exists, so this disposal request cannot be approved.'
                        );
                    }
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
                // Nothing per-asset happens here: a transfer is executed once,
                // below, by the same helper the Executive Director's decision
                // runs. Keeping it in one place is what makes an approved
                // transfer look identical whoever released it.
            }

            // ─────────────── TRANSFER ───────────────
            // Approving a transfer request the ordinary way still executes it
            // here — the office releases the assets, exactly as before. A
            // request that is going to the Executive Director does not: it
            // waits for that decision, and until then every asset stays with
            // the employee who holds it.
            if ($requestType === 'transfer' && ! $forwardForReview) {
                AssetTransfer::applyRequestTransfer(
                    $requestRecord,
                    $assetIds,
                    Auth::id(),
                    'approved transfer request #' . $requestRecord->id
                );
            }

            // ─────────────── Official disposal form ───────────────
            // One form per approved request, prefilled from what is already
            // recorded. Generated inside this transaction, so an approval can
            // never exist without the form it authorizes.
            $disposalFormId = null;

            if ($isDisposal) {
                $disposalFormId = DisposalForms::ensureForRequest(
                    (int) $requestRecord->id,
                    Auth::user(),
                    (string) $disposalReason,
                    $disposalDate
                );

                if ($disposalFormId === null) {
                    throw new \RuntimeException(
                        'The official disposal form could not be prepared for this request, so the approval was not recorded. Nothing was changed.'
                    );
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

            // 3. Audit log — the approval is recorded against each asset on the
            //    request, and says plainly that the asset is not disposed of yet.
            if ($isDisposal) {
                foreach ($assetIds as $assetId) {
                    $code = $assetCodes[$assetId] ?? ('asset #' . $assetId);

                    DB::table('audit_logs')->insert([
                        'user_id'            => Auth::id(),
                        'request_id'         => $requestRecord->id,
                        'asset_id'           => $assetId,
                        'action_type'        => 'DISPOSAL',
                        'notes'              => 'Approved disposal request #' . $requestRecord->id
                            . ' (official disposal form #' . $disposalFormId . ', reason: ' . $disposalReason . ')',
                        'action_description' => 'Disposal request #' . $requestRecord->id
                            . ' was approved and official disposal form #' . $disposalFormId
                            . ' was generated for preparation. Asset ' . $code
                            . ' is not disposed of yet — it is retired only when the disposal is finalized.',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            } else {
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
            }

            // ─── Notify the requester ───────────────────────────────
            $assetNames = DB::table('assets')
                ->whereIn('id', $assetIds)
                ->pluck('Asset_name')
                ->filter()
                ->values()
                ->all();

            // List every asset name for bulk requests
            if (count($assetNames) === 0) {
                $assetLabel = 'your asset';
            } elseif (count($assetNames) === 1) {
                $assetLabel = $assetNames[0];
            } elseif (count($assetNames) === 2) {
                $assetLabel = $assetNames[0] . ' and ' . $assetNames[1];
            } else {
                $last = array_pop($assetNames);
                $assetLabel = implode(', ', $assetNames) . ', and ' . $last;
            }

            if ($isDisposal) {
                // A request can only be approved once (it has to be Pending), so
                // this notice is written exactly once per approved request. It
                // says what approval really means now: the office is preparing
                // the official form, and the asset has not been disposed of yet.
                $codes = array_values(array_map(
                    fn ($assetId) => $assetCodes[$assetId] ?? ('asset #' . $assetId),
                    $assetIds
                ));

                $approvedLabel = count($codes) === 1
                    ? $codes[0]
                    : (implode(', ', array_slice($codes, 0, -1)) . ' and ' . end($codes));

                createNotification(
                    (int) $requestRecord->user_id,
                    'Disposal Request Approved',
                    count($codes) === 1
                        ? "Your disposal request for asset {$approvedLabel} has been approved. The Asset Management Office is preparing the official disposal form."
                        : "Your disposal request for assets {$approvedLabel} has been approved. The Asset Management Office is preparing the official disposal forms.",
                    'DISPOSAL',
                    (int) $requestRecord->id,
                    'disposal'
                );

                // The other Asset Management Office accounts are the ones who
                // may prepare, print or file the form. The approver already
                // knows, so they are not notified about their own action.
                // Written inside the approval transaction, and the request can
                // only be approved once, so this notice cannot be duplicated.
                $otherOfficers = DB::table('users')
                    ->whereIn('role', Access::ASSET_OFFICE_ROLES)
                    ->where('status', Access::STATUS_ACTIVE)
                    ->where('id', '!=', (int) $requestRecord->user_id)
                    ->where('id', '!=', (int) Auth::id())
                    ->pluck('id');

                foreach ($otherOfficers as $officerId) {
                    createNotification(
                        (int) $officerId,
                        'Disposal Approved — Official Form to Prepare',
                        'Disposal request #' . $requestRecord->id . ' for ' . $approvedLabel
                            . ' was approved. The official NU Lipa disposal form is ready to be completed, printed and filed on the Disposal page.',
                        'DISPOSAL',
                        (int) $requestRecord->id,
                        'disposal'
                    );
                }
            } elseif ($forwardForReview) {
                // Approval here is not the end of a transfer any more: it is the
                // step that asks the Executive Director. The notice says so, and
                // promises nothing about an asset that has not moved yet.
                createNotification(
                    (int) $requestRecord->user_id,
                    'Transfer Approved — Awaiting the Executive Director',
                    "Your transfer request for {$assetLabel} has been approved by the Asset Management Office and sent to the Executive Director, who makes the final decision. The assets stay with their current custodian until that decision is recorded.",
                    'TRANSFER',
                    (int) $requestRecord->id,
                    'request'
                );
            } else {
                $typeLabel = ucfirst($requestRecord->request_type ?? 'request');

                createNotification(
                    (int) $requestRecord->user_id,
                    'Request Approved',
                    "Your {$typeLabel} request for {$assetLabel} has been approved.",
                    'REQUEST',
                    (int) $requestRecord->id,
                    'request'
                );
            }
        });

        // ─── Send it to the Executive Director ────────────────────────────
        // After the approval is committed, so a mail server that refuses the
        // message cannot roll back the office's own decision: the invitation is
        // simply sent again from the request page. Either way the assets have
        // not changed hands, and the response never says they have.
        if ($forwardForReview) {
            $invitation = TransferReviews::invite((int) $requestRecord->id, $actor, false, $reviewRecipient);

            // An asset the receiving employee already holds will not change
            // hands however the Executive Director decides. Saying so when the
            // invitation goes out is the difference between an office that knows
            // and one that finds out from an "out of date" page.
            $heldNote = $alreadyHeld
                ? ' Note: ' . implode(', ', array_values($alreadyHeld))
                    . (count($alreadyHeld) === 1 ? ' is' : ' are') . ' already held by ' . $assignee
                    . ', so nothing will change hands for ' . (count($alreadyHeld) === 1 ? 'it' : 'them') . '.'
                : '';

            return response()->json([
                'message' => ($invitation['ok']
                    ? 'Transfer request approved. ' . $invitation['message']
                    : 'The transfer request was approved, but the review invitation could not be sent. ' . $invitation['message']) . $heldNote,
                'forwarded' => $invitation['ok'],
                'delivered' => (bool) ($invitation['delivered'] ?? false),
            ], 200);
        }

        // A transfer the office releases itself: say which assets moved, and
        // which were already the receiving employee's to begin with.
        if ($requestType === 'transfer') {
            $movedCodes = [];

            foreach ($assetIds as $assetId) {
                if (! in_array((int) $assetId, $alreadyHeldIds, true)) {
                    $movedCodes[] = $assetCodes[(int) $assetId] ?? ('asset #' . $assetId);
                }
            }

            $heldCodes = array_values($alreadyHeld);

            if ($heldCodes && ! $movedCodes) {
                return response()->json(['message' =>
                    'Request approved. Nothing changed hands: ' . implode(', ', $heldCodes)
                    . (count($heldCodes) === 1 ? ' is' : ' are') . ' already held by ' . $assignee . ', the employee this request transfers to.',
                ]);
            }

            if ($heldCodes) {
                return response()->json(['message' =>
                    'Request approved. ' . implode(', ', $movedCodes) . ' moved to ' . $assignee . '; '
                    . implode(', ', $heldCodes) . (count($heldCodes) === 1 ? ' was' : ' were') . ' already held by ' . $assignee . '.',
                ]);
            }
        }

        return response()->json(['message' => 'Request approved successfully.']);
    } catch (\RuntimeException $e) {
        // The transaction rolled back, so the request is still Pending and no
        // disposal record, asset status or notification was written.
        \Log::warning('Approve request rejected: ' . $e->getMessage());

        return response()->json(['message' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
        \Log::error('Approve request failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to approve request: ' . $e->getMessage()
        ], 500);
    }
})->name('admin.requests.approve');

Route::post('/admin/requests/{id}/reject', function (Request $request, $id) {
    $admin = Auth::user();

    // Rejecting follows the same authority as approving: the Asset Management
    // Office decides. Both routes ask Access, so the two can no longer disagree
    // - they used to, which let the office approve a disposal it was not
    // allowed to refuse.
    if (! Access::canDecideRequests($admin)) {
        $existing = DB::table('requests')->where('id', $id)->first();

        if ($existing) {
            Approval::recordRefusal($admin, $existing, 'reject');
        }

        return response()->json(['message' => Access::decideMessage()], 403);
    }

    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (!$requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    // Same rule as approving: your own request is not yours to refuse either.
    if (Access::isSubmitter($admin, $requestRecord)) {
        Approval::recordRefusal($admin, $requestRecord, 'reject');

        return response()->json(['message' => Access::selfApprovalMessage()], 403);
    }

    if (strtolower((string) $requestRecord->status) !== 'pending') {
        return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
    }

    $validated = $request->validate([
        'reason' => 'nullable|string|max:500',
    ], [
        'reason.max' => 'The rejection reason cannot exceed 500 characters.',
    ]);

    $reason = trim((string) ($validated['reason'] ?? ''));

    // The rejection is the only place an Admin remark is recorded, so it lives in
    // admin_remarks — kept separate from the requester's own note (requests.Note).
    DB::table('requests')
        ->where('id', $requestRecord->id)
        ->update([
            'status'           => 'Rejected',
            'admin_remarks'    => $reason !== '' ? $reason : null,
            'admin_remarks_by' => $reason !== '' ? (int) $admin->id : null,
            'admin_remarks_at' => $reason !== '' ? now() : null,
            'updated_at'       => now(),
        ]);

    // A transfer that was already sent to the Executive Director and is refused
    // here must not leave a working link behind: the invitation would open a
    // request the office has just closed. Nothing is withdrawn unless one was
    // issued, and the link stops working the moment this runs.
    if (strtolower((string) $requestRecord->request_type) === 'transfer') {
        TransferReviews::withdrawOutstanding(
            (int) $requestRecord->id,
            'The transfer request was rejected by the Asset Management Office.',
            $admin
        );
    }

    // Audit: rejected. action_type is NOT NULL on audit_logs — omitting it (as this
    // route used to) made the insert throw and silently drop both this entry and the
    // requester's notification.
    try {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'request_id' => $requestRecord->id,
            'asset_id' => $requestRecord->asset_id,
            'action_type' => 'APPROVAL',
            'notes' => 'Rejected request (' . ($requestRecord->request_type ?? '') . ')'
                . ($reason !== '' ? ': ' . $reason : ''),
            'action_description' => 'Request #' . $requestRecord->id . ' rejected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

            // Resolve a friendly asset name
            $assetIds = DB::table('request_items')
                ->where('request_id', $requestRecord->id)
                ->pluck('asset_id')
                ->all();

            if (empty($assetIds) && !empty($requestRecord->asset_id)) {
                $assetIds = [(int) $requestRecord->asset_id];
            }

            $assetName = 'your asset';
            if (!empty($assetIds)) {
                $names = DB::table('assets')->whereIn('id', $assetIds)->pluck('Asset_name')->filter()->values();
                if ($names->isNotEmpty()) {
                    $assetName = $names->count() === 1
                        ? $names->first()
                        : $names->first() . ' +' . ($names->count() - 1) . ' more';
                }
            }

            $typeLabel = ucfirst($requestRecord->request_type ?? 'request');

            createNotification(
                (int) $requestRecord->user_id,
                'Request Rejected',
                "Your {$typeLabel} request for {$assetName} has been rejected."
                    . ($reason !== '' ? " Reason: {$reason}" : ' Please check your request for more details.'),
                'REQUEST',
                (int) $requestRecord->id,
                'request'
            );

    } catch (\Exception $e) {
        // Never let a logging failure block the rejection, but do not hide it either.
        \Log::warning('Reject audit/notification failed for request #' . $requestRecord->id . ': ' . $e->getMessage());
    }

    return response()->json(['message' => 'Request rejected successfully.']);
})->name('admin.requests.reject');

/*
|--------------------------------------------------------------------------
| Executive Director review — the office's two actions
|--------------------------------------------------------------------------
|
| Sending an invitation is the same authority as approving the request: it is
| the Asset Management Office's decision to ask. Withdrawing one is the same
| authority again. Both are refused for anybody else, and both are refused on
| a request that is not an approved transfer.
|
| A resend is a replacement, not a second link: whatever the office was
| waiting on is withdrawn inside the same transaction, so the previous email
| stops working the moment a new one is issued.
*/
Route::post('/admin/requests/{id}/review/resend', function (Request $request, $id) {
    $admin = Auth::user();

    if (! Access::canDecideRequests($admin)) {
        return response()->json(['message' => Access::decideMessage()], 403);
    }

    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (! $requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    // Asking the Executive Director is not the submitter's decision to make on
    // their own request, for the same reason approving it is not.
    if (Access::isSubmitter($admin, $requestRecord)) {
        return response()->json(['message' => Access::selfApprovalMessage()], 403);
    }

    // The request is already approved, so it is past the point where the office
    // could decide it: this is the request to ask again, and to say who to ask.
    // One live invitation at a time is kept by withdrawOutstanding inside
    // TransferReviews::invite — so a resend to a new director also kills the
    // link that went to the old one.
    $result = TransferReviews::resend(
        (int) $requestRecord->id,
        $admin,
        trim((string) $request->input('recipient_email', ''))
    );

    return response()->json([
        'message'   => $result['message'],
        'delivered' => (bool) ($result['delivered'] ?? false),
    ], $result['ok'] ? 200 : 422);
})->name('admin.requests.review.resend');

Route::post('/admin/requests/{id}/review/revoke', function (Request $request, $id) {
    $admin = Auth::user();

    if (! Access::canDecideRequests($admin)) {
        return response()->json(['message' => Access::decideMessage()], 403);
    }

    $requestRecord = DB::table('requests')->where('id', $id)->first();

    if (! $requestRecord) {
        return response()->json(['message' => 'Request not found.'], 404);
    }

    $validated = $request->validate([
        'reason' => 'nullable|string|max:255',
    ], [
        'reason.max' => 'The reason cannot exceed 255 characters.',
    ]);

    $result = TransferReviews::revoke(
        (int) $requestRecord->id,
        $admin,
        trim((string) ($validated['reason'] ?? '')) ?: 'Withdrawn by the Asset Management Office.'
    );

    return response()->json(['message' => $result['message']], $result['ok'] ? 200 : 422);
})->name('admin.requests.review.revoke');

// AJAX: find asset by scanned code (used by the pullout scanner fallback)
//
// Same rule as the scanner lookup: the payload is verified first, and a sticker
// that fails its authenticity check (or has been revoked) is refused instead of
// being turned into a pullout.
Route::get('/admin/assets/find-by-code', function (Request $request) {
    $scanned = trim((string) $request->query('code', ''));
    if ($scanned === '') {
        return response()->json(['message' => 'code missing'], 422);
    }

    $verification = QrVerification::verify($scanned, Auth::user());

    if (in_array($verification['status'], QrVerification::BLOCKING, true)) {
        return response()->json([
            'message'      => $verification['detail'],
            'verification' => $verification,
        ], 422);
    }

    $asset = $verification['code'] !== null ? AssetQr::findByCode($verification['code']) : null;

    if (!$asset) {
        return response()->json([
            'message'      => 'not found',
            'verification' => $verification,
        ], 404);
    }

    return response()->json([
        'id'           => $asset->id,
        'Asset_name'   => $asset->Asset_name ?? null,
        'Asset_code'   => $asset->Asset_code ?? null,
        'status'       => $asset->Lifecycle_Status ?? null,
        'verification' => $verification,
    ]);
})->middleware('auth');

// Legacy direct entry: records a disposal that is NOT tied to a request.
//
// The request-driven workflow is the normal path (approve a Disposal request on
// the Requests page), and nothing in the UI calls this any more — the Disposal
// page has no "record a disposal" action. It is kept because the repair and
// asset-detail tools still create disposals outside a request, and a stale page
// posting here must not 500.
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

    // Assigning a pulled-out asset to somebody is a handover like any other: an
    // asset the Executive Director is deciding on stays where it is until that
    // decision is recorded.
    if ($action === 'assign') {
        if ($hold = TransferReviews::assetsOnHold($assetIds)) {
            return response()->json(['success' => false, 'message' => TransferReviews::holdRefusal($hold)], 422);
        }
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
    // AJAX: validate a scanned sticker against the scanned-in account's own assets.
    //
    // The code typed in by hand still works; the text from a camera is verified
    // first. A sticker that fails its authenticity check (or was revoked) is
    // refused with the reason, and a genuine sticker whose signature could not
    // be checked on this server is accepted with that fact attached, so a
    // missing key never blocks the office's work.
    Route::get('/user/assets/check-code', function (Request $request) {
    $scanned = trim((string) $request->query('code', ''));
    $user = Auth::user();

    if (!$user || $scanned === '') {
        return response()->json(['exists' => false]);
    }

    $verification = QrVerification::verify($scanned, $user);

    if (in_array($verification['status'], QrVerification::BLOCKING, true)) {
        return response()->json([
            'exists'       => false,
            'message'      => $verification['detail'],
            'verification' => $verification,
        ]);
    }

    if ($verification['status'] === QrVerification::UNSUPPORTED) {
        return response()->json([
            'exists'       => false,
            'message'      => $verification['detail'],
            'verification' => $verification,
        ]);
    }

    // Codes are matched the same way the admin scanner matches them: trimmed
    // and case-insensitively, so a QR that carries "ast-..." still resolves.
    $code = (string) ($verification['code'] ?? AssetCode::normalize($scanned));

    $asset = DB::table('assets')
        ->whereRaw('LOWER("Asset_code") = ?', [mb_strtolower($code)])
        ->where('user_id', $user->id)
        ->select('id', 'Asset_code', 'Asset_name', 'Category', 'Lifecycle_Status', 'Condition')
        ->first();

    if ($asset) {
        return response()->json([
            'exists'       => true,
            'verification' => $verification,
            'asset'        => [
                'id'               => $asset->id,
                'code'             => $asset->Asset_code,
                'name'             => $asset->Asset_name,
                'category'         => $asset->Category,
                'lifecycle_status' => $asset->Lifecycle_Status,
                'condition'        => $asset->Condition,
            ],
        ]);
    }

    return response()->json(['exists' => false, 'verification' => $verification]);
})->name('user.assets.check');

// AJAX: validate a scanned sticker for a Department Head (own + department employees)
Route::get('/department-head/assets/check-code', function (Request $request) {
    $scanned = trim((string) $request->query('code', ''));
    $user = Auth::user();

    if (!$user || ($user->role ?? '') !== 'Department Head' || $scanned === '') {
        return response()->json(['exists' => false]);
    }

    $verification = QrVerification::verify($scanned, $user);

    if (in_array($verification['status'], array_merge(QrVerification::BLOCKING, [QrVerification::UNSUPPORTED]), true)) {
        return response()->json([
            'exists'       => false,
            'message'      => $verification['detail'],
            'verification' => $verification,
        ]);
    }

    $code = (string) ($verification['code'] ?? AssetCode::normalize($scanned));

    $asset = DB::table('assets')
        ->leftJoin('users', 'assets.user_id', '=', 'users.id')
        ->whereRaw('LOWER("assets"."Asset_code") = ?', [mb_strtolower($code)])
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
            'exists'       => true,
            'verification' => $verification,
            'asset'        => [
                'id'               => $asset->id,
                'code'             => $asset->Asset_code,
                'name'             => $asset->Asset_name,
                'category'         => $asset->Category,
                'lifecycle_status' => $asset->Lifecycle_Status,
                'condition'        => $asset->Condition,
            ],
        ]);
    }

    return response()->json(['exists' => false, 'verification' => $verification]);
})->name('department_head.assets.check');

Route::get('/user/requests', function (Request $request) {
    try {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }

        $status = $request->query('status', 'all');   // all | Pending | Approved | Rejected
        $search = trim((string) $request->query('q', ''));

        $query = DB::table('requests')
            ->where('requests.user_id', $user->id);

        // Server-side status filter
        if ($status !== 'all') {
            $query->where('requests.status', $status);
        }

        // Case-insensitive search (PostgreSQL)
        if ($search !== '') {
            $term = '%' . $search . '%';

            $query->where(function ($q) use ($term) {
                $q->where('requests.Note', 'ilike', $term)
                  ->orWhere('requests.request_type', 'ilike', $term)
                  ->orWhereExists(function ($sub) use ($term) {
                      $sub->select(DB::raw(1))
                          ->from('request_items')
                          ->join('assets', 'request_items.asset_id', '=', 'assets.id')
                          ->whereColumn('request_items.request_id', 'requests.id')
                          ->where(function ($aq) use ($term) {
                              $aq->where('assets.Asset_name', 'ilike', $term)
                                 ->orWhere('assets.Asset_code', 'ilike', $term);
                          });
                  });
            });
        }

        // Pending requests stay pinned to the top of every page — oldest
        // waiting first, so the one that has been pending longest is the first
        // row — and already-decided requests follow, newest first, so a request
        // waiting on the office is never buried under decided ones.
        $requests = $query
            ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN 0 ELSE 1 END ASC', ['pending'])
            ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN requests.created_at END ASC', ['pending'])
            ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN requests.id END ASC', ['pending'])
            ->orderByDesc('requests.created_at')
            ->orderByDesc('requests.id')
            // 10 a page, the same as the asset listing: each request card is tall,
            // and at 15 the list was one long scroll before the pager appeared.
            ->paginate(10)
            ->withQueryString();

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
                // The user's own submitted note — shown in full, never rewritten.
                'Note'         => $r->Note,
                'admin_remarks'    => $r->admin_remarks ?? null,
                'admin_remarks_at' => $r->admin_remarks_at
                                        ? \Illuminate\Support\Carbon::parse($r->admin_remarks_at)
                                        : null,
                'file_path'    => $r->file_path ?? null,
                'file_url'     => Media::url($r->file_path ?? null),
                'file_name'    => $r->file_name ?? null,
                'created_at'   => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at) : now(),
                'updated_at'   => $r->updated_at ? \Illuminate\Support\Carbon::parse($r->updated_at) : now(),
                'asset'        => (object)[
                    'Asset_name' => $displayName,
                    'Asset_code' => $count === 1 ? $assetList->first()->Asset_code : '',
                ],
                'assets'       => $assetList,
                'asset_count'  => $count,
            ];
        });

        $totalRequests    = DB::table('requests')->where('user_id', $user->id)->count();
        $pendingRequests  = DB::table('requests')->where('user_id', $user->id)->where('status', 'Pending')->count();
        $approvedRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Approved')->count();
        $rejectedRequests = DB::table('requests')->where('user_id', $user->id)->where('status', 'Rejected')->count();

        return view('users.request.request', compact(
            'requests',
            'totalRequests',
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests',
            'status',
            'search'
        ));
    } catch (\Throwable $e) {
        return redirect('/users')->withErrors(['error' => 'Unable to load requests.']);
    }
})->name('user.requests.index');
    // Employee: request form. A Department Head reaching this URL is sent to
    // the department-head form instead, so each role only ever uses its own page.
    Route::get('/user/request-asset', function () {
        $user = Auth::user();
        if (!$user) return redirect('/login');
        if (($user->role ?? '') === 'Department Head') {
            return redirect()->route('department_head.request-asset');
        }
        return app(UserRequestController::class)->create();
    })->name('user.request-asset');
    Route::post('/user/requests/store', [UserRequestController::class, 'store'])->name('user.requests.store');

    // Department Head: view requests submitted by users in the head's department
Route::get('/department-head/requests', function (Request $request) {
    $user = Auth::user();
    if (!$user) return redirect('/login');
    if (($user->role ?? '') !== 'Department Head') return abort(403);

    $status = $request->query('status', 'all');   // all | Pending | Approved | Rejected
    $search = trim((string) $request->query('q', ''));

    // Base query – all requests in the department head’s department
    $query = DB::table('requests')
        ->leftJoin('users', 'requests.user_id', '=', 'users.id')
        ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
        ->where('users.department_id', $user->department_id)
        ->select(
            'requests.*',
            'employee_numbers.Full_Name as requester_name'
        );

    // Server-side status filter
    if ($status !== 'all') {
        $query->where('requests.status', $status);
    }

// Optional server-side search (case-insensitive, PostgreSQL)
if ($search !== '') {
    $term = '%' . $search . '%';

    $query->where(function ($q) use ($term) {
        $q->where('requests.Note', 'ilike', $term)
          ->orWhere('requests.request_type', 'ilike', $term)
          ->orWhere('employee_numbers.Full_Name', 'ilike', $term)
          ->orWhereExists(function ($sub) use ($term) {
              $sub->select(DB::raw(1))
                  ->from('request_items')
                  ->join('assets', 'request_items.asset_id', '=', 'assets.id')
                  ->whereColumn('request_items.request_id', 'requests.id')
                  ->where(function ($aq) use ($term) {
                      $aq->where('assets.Asset_name', 'ilike', $term)
                         ->orWhere('assets.Asset_code', 'ilike', $term);
                  });
          });
    });
}

    // Pending requests first (they are the ones the head still has to act on),
    // oldest waiting first inside that group; decided requests follow, newest
    // first.
    $requests = $query
        ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN 0 ELSE 1 END ASC', ['pending'])
        ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN requests.created_at END ASC', ['pending'])
        ->orderByRaw('CASE WHEN LOWER(requests.status) = ? THEN requests.id END ASC', ['pending'])
        ->orderByDesc('requests.created_at')
        ->orderByDesc('requests.id')
        // 10 a page, the same as the asset listing and the employee's own list.
        ->paginate(10)
        ->withQueryString();   // ← keeps ?status= & ?q= on pagination links

    // Load assets from request_items (same as before)
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
            // The user's own submitted note — shown in full, never rewritten.
            'Note'           => $r->Note,
            'admin_remarks'    => $r->admin_remarks ?? null,
            'admin_remarks_at' => $r->admin_remarks_at
                                    ? \Illuminate\Support\Carbon::parse($r->admin_remarks_at)
                                    : null,
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

    // Summary counts (always total for the department, not filtered)
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
        'status'           => $status,   // ← pass current filter to the view
        'search'           => $search,
        'user'             => $user,
        'currentUser'      => $user,
    ]);
})->name('department_head.requests.index');

    // Department Head: request form on its own page. The controller builds the
    // Transfer target list from the head's own department, so the two roles
    // never share one form (or one "assign to" list).
    Route::get('/department-head/request-asset', function () {
        $user = Auth::user();
        if (!$user) return redirect('/login');
        if (($user->role ?? '') !== 'Department Head') return abort(403);
        return app(UserRequestController::class)->createDepartmentHead();
    })->name('department_head.request-asset');

    // Department Head: submit request (reuse controller)
    Route::post('/department-head/requests/store', [UserRequestController::class, 'store'])->name('department_head.requests.store');
});

// ════════════════════════════════════════════════════════════════════════════
// Employee & Account Management  (panel recommendation 2)
//
// One page holds two different things, and says so: the employee masterlist
// (who works here) and the NU Trace accounts (who may sign in). They are kept
// apart deliberately - importing the official list does not create logins and
// never grants a role.
//
// Every endpoint below re-checks Access::canManageEmployees() on the server.
// The menu entry and the buttons are convenience; this is the control.
// ════════════════════════════════════════════════════════════════════════════
Route::middleware(['auth'])->group(function () {

    Route::get('/admin/employees', function (Request $request) {
        if (! Access::canManageEmployees(Auth::user())) {
            abort(403, Access::manageEmployeesMessage());
        }

        $tabRaw = $request->query('tab', 'directory');
        $tab    = is_string($tabRaw) && in_array($tabRaw, ['directory', 'accounts', 'import'], true)
            ? $tabRaw
            : 'directory';

        $filters        = EmployeeDirectory::filters($request->query());
        $accountFilters = AccountAdmin::filters($request->query());

        return view('admin.employees.employees', [
            'tab'             => $tab,
            'employees'       => EmployeeDirectory::directory($filters),
            'accounts'        => AccountAdmin::accounts($accountFilters),
            'filters'         => $filters,
            'accountFilters'  => $accountFilters,
            'counts'          => EmployeeDirectory::counts(),
            'accountCounts'   => AccountAdmin::counts(),
            'departments'     => EmployeeDirectory::departments(),
            'roles'           => Access::ROLES,
            'statuses'        => EmployeeDirectory::STATUSES,
            // Requests that are waiting with nobody left allowed to decide them.
            // Shown, never reassigned: 3.6 asks for a flag, not a new policy.
            'orphanedPending' => AccountAdmin::pendingWithoutReviewer(),
        ]);
    })->name('admin.employees');

    // ─── The masterlist: add and edit one employee at a time ───

    Route::post('/admin/employees', function (Request $request) {
        if (! Access::canManageEmployees(Auth::user())) {
            return back()->withErrors(['employee_number' => Access::manageEmployeesMessage()]);
        }

        $result = EmployeeDirectory::store($request->all(), Auth::user());

        if (! $result['ok']) {
            return back()->withErrors($result['errors'])->withInput();
        }

        return redirect('/admin/employees')
            ->with('success', 'Employee record added to the masterlist. This does not create a NU Trace account - the employee still has to register with an active employee number.');
    })->name('admin.employees.store');

    Route::post('/admin/employees/{id}', function (Request $request, $id) {
        if (! Access::canManageEmployees(Auth::user())) {
            return back()->withErrors(['employee_number' => Access::manageEmployeesMessage()]);
        }

        $result = EmployeeDirectory::update((int) $id, $request->all(), Auth::user());

        if (! $result['ok']) {
            return back()->withErrors($result['errors'])->withInput();
        }

        return redirect('/admin/employees')->with('success', 'Employee record updated.');
    })->where('id', '[0-9]+')->name('admin.employees.update');

    // ─── The masterlist as a CSV ───

    Route::get('/admin/employees/template', function () {
        if (! Access::canManageEmployees(Auth::user())) {
            abort(403, Access::manageEmployeesMessage());
        }

        return EmployeeDirectory::template();
    })->name('admin.employees.template');

    // Check the uploaded file and write NOTHING. The page shows the result and
    // sends the same file again to import it, so the only thing that ever
    // decides what lands in the database is the server, never the browser.
    Route::post('/admin/employees/import/preview', function (Request $request) {
        if (! Access::canManageEmployees(Auth::user())) {
            return response()->json(['ok' => false, 'message' => Access::manageEmployeesMessage()], 403);
        }

        // No size ceiling: a faculty list is imported whole, not in parts.
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.mimes' => 'Only a CSV file can be imported. Download the template and fill that in.',
        ]);

        $preview = EmployeeDirectory::preview($validated['file']);

        // A file the office cannot use is worth a line in the trail - once per
        // file, not once per row, and with no employee data beyond the reason.
        if (! $preview['ok']) {
            EmployeeDirectory::audit(
                Auth::user(),
                'UPDATE',
                'Employee import file rejected before import: ' . ($preview['message'] ?? 'the file could not be read'),
                'Employee masterlist import rejected - nothing was written'
            );
        }

        return response()->json($preview, $preview['ok'] ? 200 : 422);
    })->name('admin.employees.import.preview');

    Route::post('/admin/employees/import', function (Request $request) {
        if (! Access::canManageEmployees(Auth::user())) {
            return response()->json(['ok' => false, 'message' => Access::manageEmployeesMessage()], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.mimes' => 'Only a CSV file can be imported. Download the template and fill that in.',
        ]);

        $result = EmployeeDirectory::commit($validated['file'], Auth::user());

        if (! $result['ok']) {
            EmployeeDirectory::audit(
                Auth::user(),
                'UPDATE',
                'Employee import was not completed: ' . ($result['message'] ?? 'the file could not be imported'),
                'Employee masterlist import rejected - nothing was written'
            );
        }

        return response()->json($result, $result['ok'] ? 200 : 422);
    })->name('admin.employees.import');

    // Export follows the filter that is on screen, and the server re-checks the
    // permission before a single row is written. Password hashes are never
    // part of this file.
    Route::get('/admin/employees/export', function (Request $request) {
        if (! Access::canManageEmployees(Auth::user())) {
            abort(403, Access::manageEmployeesMessage());
        }

        $filters = EmployeeDirectory::filters($request->query());
        $stamp   = now()->format('Y-m-d');

        EmployeeDirectory::audit(
            Auth::user(),
            'UPDATE',
            'Exported the employee masterlist to CSV (' . count(EmployeeDirectory::exportRows($filters)) . ' record(s)).',
            'Employee masterlist exported'
        );

        return \App\Support\Csv::download(
            'employee-masterlist-' . $stamp . '.csv',
            EmployeeDirectory::exportHeadings(),
            EmployeeDirectory::exportRows($filters)
        );
    })->name('admin.employees.export');

    // ─── Accounts: role, department and status ───

    Route::post('/admin/accounts/{id}/role', function (Request $request, $id) {
        if (! Access::canManageEmployees(Auth::user())) {
            return response()->json(['success' => false, 'message' => Access::manageEmployeesMessage()], 403);
        }

        $result = AccountAdmin::changeRole((int) $id, (string) $request->input('role', ''), Auth::user());

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    })->where('id', '[0-9]+')->name('admin.accounts.role');

    Route::post('/admin/accounts/{id}/department', function (Request $request, $id) {
        if (! Access::canManageEmployees(Auth::user())) {
            return response()->json(['success' => false, 'message' => Access::manageEmployeesMessage()], 403);
        }

        $result = AccountAdmin::changeDepartment((int) $id, (int) $request->input('department_id', 0), Auth::user());

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    })->where('id', '[0-9]+')->name('admin.accounts.department');

    Route::post('/admin/accounts/{id}/status', function (Request $request, $id) {
        if (! Access::canManageEmployees(Auth::user())) {
            return response()->json(['success' => false, 'message' => Access::manageEmployeesMessage()], 403);
        }

        $result = AccountAdmin::changeStatus((int) $id, (string) $request->input('status', ''), Auth::user());

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    })->where('id', '[0-9]+')->name('admin.accounts.status');

    // The account's history, read from the audit log that already exists.
    Route::get('/admin/accounts/{id}/history', function ($id) {
        if (! Access::canManageEmployees(Auth::user())) {
            return response()->json(['success' => false, 'message' => Access::manageEmployeesMessage()], 403);
        }

        $account = AccountAdmin::find((int) $id);

        if (! $account) {
            return response()->json(['success' => false, 'message' => 'That account no longer exists.'], 404);
        }

        return response()->json([
            'success' => true,
            'account' => [
                'name'       => AccountAdmin::displayName($account),
                'email'      => $account->email,
                'role'       => $account->role,
                'status'     => $account->status,
                'employee'   => $account->Employee_number,
            ],
            'history' => collect(AccountAdmin::history((int) $id))->map(fn ($entry) => [
                'action'      => $entry->action_type,
                'notes'       => \App\Support\AuditLogText::redact($entry->notes),
                'description' => \App\Support\AuditLogText::redact($entry->action_description),
                'at'          => $entry->created_at
                    ? \Illuminate\Support\Carbon::parse($entry->created_at)->format('M d, Y h:i A')
                    : '',
            ]),
        ]);
    })->where('id', '[0-9]+')->name('admin.accounts.history');
});

