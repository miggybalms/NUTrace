@extends('layouts.admin_sidebar')

@section('title', 'Employee Management')

@section('content')

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root{
        --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B;
        --gold-500:#C9A227; --gold-100:#F3E7C4;
        --paper:#F3EEE0; --paper-2:#EAE2C9;
        --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#5C6474;
        --line:#DED2AE;
        --forest:#2F7A4D; --forest-tint:#EAF4EE;
        --bronze:#B4791E; --bronze-tint:#FBF1DE;
        --brick:#A23B32; --brick-tint:#F7E9E6;
        --steel:#2E5C8A; --steel-tint:#E9F0F7;
    }
    body{ background:var(--paper) !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif !important; color:var(--ink-900); }
    .font-display{ font-family:'Fraunces',Georgia,serif; }
    .font-mono{ font-family:'IBM Plex Mono',monospace; }
    .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }
    .card{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
    .field{ border:1px solid var(--line); background:#fff; border-radius:10px; padding:.55rem .75rem; font-size:.875rem; color:var(--ink-900); }
    .field:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .btn-gold{ background:var(--gold-500); color:#0A1830; font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; transition:background .15s; }
    .btn-gold:hover{ background:#E0BC44; }
    .btn-ghost{ background:#fff; color:var(--ink-600); border:1px solid var(--gold-500); font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; transition:background .15s; }
    .btn-ghost:hover{ background:var(--paper-2); }
    .tab{ padding:.7rem 1rem; font-size:.875rem; font-weight:600; border-bottom:2px solid transparent; color:var(--ink-600); }
    .tab:hover{ color:var(--navy-900); }
    .tab-active{ color:var(--navy-900); border-bottom-color:var(--gold-500); }
    .badge{ font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:.2rem .5rem; border-radius:999px; display:inline-flex; align-items:center; gap:.25rem; }
    .badge-active{ background:var(--forest-tint); color:var(--forest); }
    .badge-inactive{ background:var(--brick-tint); color:var(--brick); }
    .badge-none{ background:var(--paper-2); color:var(--ink-600); }
    .badge-role{ background:var(--gold-100); color:var(--navy-900); }
    .badge-import{ background:var(--forest-tint); color:var(--forest); }
    .badge-skip{ background:var(--bronze-tint); color:var(--bronze); }
    .badge-error{ background:var(--brick-tint); color:var(--brick); }
    .modal-backdrop{ background:rgba(10,24,48,.55); }
</style>

@include('admin.partials.header', [
    'adminHeaderPage'  => 'employees',
    'adminHeaderTitle' => 'Employee Management',
    'adminHeaderIcon'  => 'ri-group-line',
    'adminHeaderBadge' => 'Admin',
])

<div class="p-4 sm:p-8">

    {{-- ── Messages ─────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="card p-4 mb-5 border-l-4" style="border-left-color:var(--forest);">
            <p class="text-sm text-[#245C3B]"><i class="ri-checkbox-circle-line mr-1.5"></i>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-4 mb-5 border-l-4" style="border-left-color:var(--brick);">
            <p class="text-sm text-[#7E2E27]"><i class="ri-error-warning-line mr-1.5"></i>{{ session('error') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="card p-4 mb-5 border-l-4" style="border-left-color:var(--brick);">
            <p class="eyebrow mb-1">That could not be saved</p>
            <ul class="text-sm text-[#7E2E27] list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── 3.6: waiting requests with nobody left to decide them ── --}}
    @if($orphanedPending > 0)
        <div class="card p-4 mb-5 border-l-4" style="border-left-color:var(--bronze);">
            <p class="text-sm text-[#8F5F16]">
                <i class="ri-alert-line mr-1.5"></i>
                <strong>{{ $orphanedPending }} pending request(s)</strong> are waiting, and there is currently no
                <em>active</em> Asset Management Office account that can decide them. The requests are preserved and
                unchanged. Give an account the Administrator role, or reactivate one, to clear this.
            </p>
        </div>
    @endif

    {{-- ── Summary ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-[#5B6678]">On the masterlist</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--gold-100);">
                    <i class="ri-group-line text-[#7E5E0E]"></i>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#0F2143]">{{ $counts['employees'] }}</p>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-[#5B6678]">Active employees</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--forest-tint);">
                    <i class="ri-user-follow-line text-[#2F7A4D]"></i>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#2F7A4D]">{{ $counts['active'] }}</p>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-[#5B6678]">Registered accounts</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--steel-tint);">
                    <i class="ri-shield-user-line text-[#2E5C8A]"></i>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#2E5C8A]">{{ $counts['registered'] }}</p>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-[#5B6678]">Not registered yet</p>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--bronze-tint);">
                    <i class="ri-user-unfollow-line text-[#B4791E]"></i>
                </div>
            </div>
            <p class="text-3xl font-bold text-[#B4791E]">{{ $counts['unregistered'] }}</p>
        </div>
    </div>

    <div class="card overflow-hidden">

        {{-- ── Tabs ─────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center justify-between gap-x-3 px-4 sm:px-6 pt-4 border-b border-[#EFE9D8]">
            <div class="flex overflow-x-auto">
                @php
                    $tabs = [
                        'directory' => ['Directory', 'ri-list-check-2'],
                        'accounts'  => ['Accounts', 'ri-shield-keyhole-line'],
                        'import'    => ['Import CSV', 'ri-upload-2-line'],
                    ];
                    $tabParams = array_filter([
                        'q'               => $filters['q'] ?? '',
                        'department'      => $filters['department'] ?? '',
                        'status'          => $filters['status'] ?? '',
                        'account'         => ($filters['account'] ?? 'all') !== 'all' ? $filters['account'] : '',
                        // The Accounts tab's own filter. Without it, clicking the
                        // tab you are already on rebuilt the URL without the role
                        // and quietly showed every account again.
                        'role'            => $accountFilters['role'] ?? '',
                    ], fn ($value) => $value !== '' && $value !== null);
                @endphp
                @foreach($tabs as $key => $meta)
                    <a href="{{ url('/admin/employees') }}?{{ http_build_query(array_merge($tabParams, ['tab' => $key])) }}"
                       class="tab {{ $tab === $key ? 'tab-active' : '' }}">
                        <i class="{{ $meta[1] }} mr-1.5"></i>{{ $meta[0] }}
                    </a>
                @endforeach
            </div>
            <p class="text-sm text-[#5C6474] pb-3">
                @if($tab === 'accounts')
                    {{ $accounts->total() }} account(s)
                @else
                    {{ $counts['employees'] }} record(s)
                @endif
            </p>
        </div>

        {{-- ══════════════════════ DIRECTORY ══════════════════════ --}}
        @if($tab === 'directory')
            <div class="px-4 sm:px-6 py-4 border-b border-[#EFE9D8]">
                <form method="GET" action="/admin/employees" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="tab" value="directory">

                    <div class="flex-1 min-w-[180px]">
                        <label class="eyebrow block mb-1.5" for="dir-q">Search</label>
                        <input id="dir-q" type="text" name="q" value="{{ $filters['q'] }}" class="field w-full"
                               placeholder="Employee number, name, email or department">
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5" for="dir-dept">Department</label>
                        <select id="dir-dept" name="department" class="field">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) $filters['department'] === (int) $department->id)>{{ $department->Name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5" for="dir-status">Record status</label>
                        <select id="dir-status" name="status" class="field">
                            <option value="">Any</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5" for="dir-account">Account</label>
                        <select id="dir-account" name="account" class="field">
                            <option value="all" @selected($filters['account'] === 'all')>Any</option>
                            <option value="registered" @selected($filters['account'] === 'registered')>Registered</option>
                            <option value="unregistered" @selected($filters['account'] === 'unregistered')>Not registered</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-ghost"><i class="ri-filter-3-line mr-1.5"></i>Filter</button>

                    <button type="button" class="btn-gold" onclick="openEmployeeModal()">
                        <i class="ri-add-line mr-1.5"></i>Add Employee
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-[#F5F0E2] text-[#5B6678] uppercase text-xs tracking-wider">
                            <th class="px-5 py-3.5 text-left font-semibold">Employee ID</th>
                            <th class="px-5 py-3.5 text-left font-semibold">Full name</th>
                            <th class="px-5 py-3.5 text-left font-semibold hidden md:table-cell">Email</th>
                            <th class="px-5 py-3.5 text-left font-semibold">Department</th>
                            <th class="px-5 py-3.5 text-left font-semibold">Status</th>
                            <th class="px-5 py-3.5 text-left font-semibold hidden lg:table-cell">Account / registration</th>
                            <th class="px-5 py-3.5 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0EADB]">
                        @forelse($employees as $employee)
                            <tr class="hover:bg-[#FBF7EC]">
                                <td class="px-5 py-3.5 font-mono text-xs text-[#0F2143]">{{ $employee->Employee_number }}</td>
                                <td class="px-5 py-3.5 font-medium text-[#0F2143]">{{ $employee->Full_Name }}</td>
                                {{-- The masterlist's official email wins when it is recorded; an
                                     employee who registered with a personal address shows the
                                     address their account actually uses instead of a dash. --}}
                                <td class="px-5 py-3.5 hidden md:table-cell text-[#5B6678]">{{ $employee->Email ?: ($employee->account_email ?: '—') }}</td>
                                <td class="px-5 py-3.5 text-[#5B6678]">{{ $employee->department ?: '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="badge {{ $employee->status === 'Active' ? 'badge-active' : 'badge-inactive' }}">{{ $employee->status }}</span>
                                </td>
                                <td class="px-5 py-3.5 hidden lg:table-cell">
                                    @php $eligibility = \App\Support\EmployeeDirectory::eligibility($employee); @endphp
                                    @if($employee->account_id)
                                        <span class="badge badge-role">{{ $employee->account_role }}</span>
                                        @if(strtolower((string) $employee->account_status) !== 'active')
                                            <span class="badge badge-inactive ml-1">Account inactive</span>
                                        @endif
                                        <p class="text-xs text-[#5B6678] mt-1">Already registered</p>
                                    @elseif($eligibility['eligible'])
                                        <span class="badge badge-active" title="{{ $eligibility['detail'] }}">
                                            <i class="ri-user-add-line"></i>Eligible to register
                                        </span>
                                    @else
                                        <span class="badge badge-inactive" title="{{ $eligibility['detail'] }}">
                                            <i class="ri-forbid-line"></i>Not eligible
                                        </span>
                                        <p class="text-xs text-[#5B6678] mt-1">{{ $eligibility['detail'] }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    {{-- The record travels in data attributes rather than in
                                         a JSON blob inside the onclick: a name with an
                                         apostrophe in it is escaped by Blade instead of
                                         breaking the JavaScript around it. --}}
                                    <button type="button" class="text-[#5B6678] hover:text-[#0F2143] px-2"
                                            title="Edit this employee record"
                                            data-id="{{ $employee->id }}"
                                            data-employee-number="{{ $employee->Employee_number }}"
                                            data-full-name="{{ $employee->Full_Name }}"
                                            data-department-id="{{ $employee->department_id }}"
                                            data-status="{{ $employee->status }}"
                                            onclick="openEmployeeModal(this)">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-[#5C6474]">
                                    No employee record matches this filter.
                                    @if($filters['q'] || $filters['status'] || $filters['account'] !== 'all' || $filters['department'])
                                        <a href="/admin/employees" class="text-[#8F5F16] underline ml-1">Clear the filter</a>.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 border-t border-[#EFE9D8]">
                {{ $employees->links() }}
            </div>
        @endif

        {{-- ══════════════════════ ACCOUNTS ══════════════════════ --}}
        @if($tab === 'accounts')
            <div class="px-4 sm:px-6 py-4 border-b border-[#EFE9D8]">
                <form method="GET" action="/admin/employees" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="tab" value="accounts">

                    <div class="flex-1 min-w-[180px]">
                        <label class="eyebrow block mb-1.5" for="acc-q">Search</label>
                        <input id="acc-q" type="text" name="q" value="{{ $accountFilters['q'] }}" class="field w-full"
                               placeholder="Name, email, employee number or department">
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5" for="acc-role">Role</label>
                        <select id="acc-role" name="role" class="field">
                            <option value="">All roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" @selected($accountFilters['role'] === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5" for="acc-dept">Department</label>
                        <select id="acc-dept" name="department" class="field">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) $accountFilters['department'] === (int) $department->id)>{{ $department->Name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5" for="acc-status">Status</label>
                        <select id="acc-status" name="status" class="field">
                            <option value="">Any</option>
                            <option value="Active" @selected($accountFilters['status'] === 'Active')>Active</option>
                            <option value="Inactive" @selected($accountFilters['status'] === 'Inactive')>Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-ghost"><i class="ri-filter-3-line mr-1.5"></i>Filter</button>
                </form>

                <p class="text-xs text-[#5C6474] mt-3">
                    A role is granted here, deliberately, one account at a time. Changing someone's department moves
                    them without changing what they are allowed to do.
                </p>

                @if(($accountCounts['unmatched'] ?? 0) > 0 || ($accountCounts['inactive_employee'] ?? 0) > 0)
                    <div class="mt-3 p-3 rounded-lg text-sm" style="background:var(--brick-tint); color:var(--brick);">
                        <i class="ri-error-warning-line mr-1.5"></i>
                        @if(($accountCounts['unmatched'] ?? 0) > 0)
                            {{ $accountCounts['unmatched'] }} account(s) are not linked to any employee masterlist record.
                        @endif
                        @if(($accountCounts['inactive_employee'] ?? 0) > 0)
                            {{ $accountCounts['inactive_employee'] }} account(s) belong to an employee record that is no longer Active.
                        @endif
                        Review the flagged rows below; nothing has been unlinked or deactivated automatically.
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-[#F5F0E2] text-[#5B6678] uppercase text-xs tracking-wider">
                            <th class="px-5 py-3.5 text-left font-semibold">Account</th>
                            <th class="px-5 py-3.5 text-left font-semibold hidden md:table-cell">Employee ID</th>
                            <th class="px-5 py-3.5 text-left font-semibold">Department</th>
                            <th class="px-5 py-3.5 text-left font-semibold">Role</th>
                            <th class="px-5 py-3.5 text-left font-semibold">Status</th>
                            <th class="px-5 py-3.5 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0EADB]">
                        @forelse($accounts as $account)
                            <tr class="hover:bg-[#FBF7EC]">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-[#0F2143]">{{ $account->Full_Name ?: '—' }}</p>
                                    <p class="text-xs text-[#5B6678]">{{ $account->email }}</p>
                                </td>
                                <td class="px-5 py-3.5 hidden md:table-cell">
                                    @php $recordState = \App\Support\AccountAdmin::employeeRecordState($account); @endphp
                                    @if($recordState['key'] === 'matched')
                                        <span class="font-mono text-xs text-[#0F2143]">{{ $recordState['label'] }}</span>
                                    @else
                                        <span class="badge badge-error">{{ $recordState['label'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <select class="field py-1.5 text-xs" onchange="changeDepartment({{ $account->id }}, this)"
                                            aria-label="Department for {{ $account->email }}">
                                        <option value="">— no department —</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}" @selected((int) $account->department_id === (int) $department->id)>{{ $department->Name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-5 py-3.5">
                                    {{-- The list is the roles that can be assigned. An account
                                         still holding a retired one (Facilities) shows it
                                         rather than being displayed as a role it does not
                                         have; picking one of the current roles replaces it,
                                         and the retired value can never be chosen for
                                         anybody else. --}}
                                    @php
                                        $accountRoles = \App\Support\Access::isRetiredRole($account->role)
                                            ? array_merge([$account->role], $roles)
                                            : $roles;
                                    @endphp
                                    <select class="field py-1.5 text-xs" onchange="changeRole({{ $account->id }}, this)"
                                            aria-label="Role for {{ $account->email }}">
                                        @foreach($accountRoles as $role)
                                            <option value="{{ $role }}" @selected($account->role === $role)>{{ $role }}@if(\App\Support\Access::isRetiredRole($role)) (retired)@endif</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="badge {{ strtolower((string) $account->status) === 'active' ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $account->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <button type="button" class="btn-ghost !py-1.5 !px-3 !text-xs"
                                            onclick="showHistory({{ $account->id }})">
                                        <i class="ri-history-line mr-1"></i>History
                                    </button>
                                    <button type="button" class="btn-ghost !py-1.5 !px-3 !text-xs ml-1"
                                            onclick="toggleAccount({{ $account->id }}, '{{ strtolower((string) $account->status) === 'active' ? 'Inactive' : 'Active' }}')">
                                        {{ strtolower((string) $account->status) === 'active' ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-[#5C6474]">No account matches this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 border-t border-[#EFE9D8]">
                {{ $accounts->links() }}
            </div>
        @endif

        {{-- ══════════════════════ IMPORT ══════════════════════ --}}
        @if($tab === 'import')
            <div class="px-4 sm:px-6 py-5">
                <div class="max-w-3xl">
                    <h3 class="font-display text-lg font-semibold text-[#0F2143] mb-1">Import the employee masterlist</h3>
                    <p class="text-sm text-[#5C6474] mb-4">
                        Download the template, fill it in, and check the file before importing. Nothing is written
                        until you press Import, and an employee who is already on the masterlist is skipped rather
                        than overwritten. Importing never creates a NU Trace account and never grants a role.
                    </p>

                    <a href="/admin/employees/template" class="btn-ghost mb-5" download>
                        <i class="ri-download-2-line mr-1.5"></i>Download CSV template
                    </a>

                    <div class="card p-5">
                        <label class="eyebrow block mb-2" for="import_file">CSV file (max 2 MB, up to {{ \App\Support\Csv::MAX_ROWS }} rows)</label>
                        <input id="import_file" type="file" accept=".csv,.txt,text/csv" class="field w-full mb-3">

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-ghost" onclick="checkImport()">
                                <i class="ri-search-eye-line mr-1.5"></i>Check file
                            </button>
                            <button type="button" class="btn-gold" id="import-run" onclick="runImport()" disabled>
                                <i class="ri-upload-2-line mr-1.5"></i><span id="import-run-label">Import</span>
                            </button>
                        </div>

                        <p class="text-xs text-[#5C6474] mt-3">
                            Required columns: <span class="font-mono">Employee Number</span>,
                            <span class="font-mono">Full Name</span>, <span class="font-mono">Department</span>.
                            Optional: <span class="font-mono">Email</span>, <span class="font-mono">Status</span>.
                        </p>
                    </div>

                    <div id="import-notice" class="hidden card p-4 mt-4"></div>
                    <div id="import-result" class="hidden mt-4"></div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- ═══════════════ Add / edit an employee record ═══════════════ --}}
<div id="employee-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#EFE9D8]">
            <h3 id="employee-modal-title" class="font-display text-lg font-semibold text-[#0F2143]">Add employee</h3>
            <button type="button" onclick="closeEmployeeModal()" class="text-[#5C6474] hover:text-[#0F2143]" aria-label="Close">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>

        <form id="employee-form" method="POST" action="/admin/employees" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_method" id="employee-method" value="">

            <div>
                <label class="eyebrow block mb-1.5" for="employee_number">Employee number <span class="text-[#A23B32]">*</span></label>
                <input id="employee_number" name="employee_number" type="text" maxlength="20" required
                       value="{{ old('employee_number') }}" class="field w-full font-mono" placeholder="EMP-0001">
                <p class="text-xs text-[#5C6474] mt-1">Must match what the employee types when they register.</p>
            </div>

            <div>
                <label class="eyebrow block mb-1.5" for="full_name">Full name <span class="text-[#A23B32]">*</span></label>
                <input id="full_name" name="full_name" type="text" maxlength="100" required
                       value="{{ old('full_name') }}" class="field w-full" placeholder="Juan Dela Cruz">
            </div>

            <div>
                <label class="eyebrow block mb-1.5" for="department_id">Department <span class="text-[#A23B32]">*</span></label>
                <select id="department_id" name="department_id" required class="field w-full">
                    <option value="">— choose a department —</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                            {{ $department->Name }}@if($department->status !== 'Active') (inactive)@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="eyebrow block mb-1.5" for="record_status">Employee record status</label>
                <select id="record_status" name="status" class="field w-full">
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', 'Active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-[#5C6474] mt-1">An Inactive employee number cannot be used to register.</p>
            </div>

            <p class="text-xs text-[#5C6474] bg-[#FBF7EC] border border-[#EFE9D8] rounded-lg p-3">
                This creates an employee record only. It does not create a NU Trace account, and it does not give
                anyone a role — accounts are managed on the Accounts tab.
            </p>

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn-ghost" onclick="closeEmployeeModal()">Cancel</button>
                <button type="submit" class="btn-gold">Save employee</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════ Account history ═══════════════ --}}
<div id="history-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#EFE9D8]">
            <div>
                <h3 class="font-display text-lg font-semibold text-[#0F2143]">Account history</h3>
                <p id="history-subject" class="text-xs text-[#5C6474]"></p>
            </div>
            <button type="button" onclick="closeHistory()" class="text-[#5C6474] hover:text-[#0F2143]" aria-label="Close">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>
        <div id="history-body" class="p-6 text-sm text-[#5B6678]">Loading…</div>
    </div>
</div>

<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    /* ── Add / edit ───────────────────────────────────────────── */
    /* Called with the row's own button (edit) or with nothing (add). */
    function openEmployeeModal(button) {
        const record = button ? button.dataset : null;
        const isEdit = !!(record && record.id);

        document.getElementById('employee-modal-title').textContent = isEdit ? 'Edit employee record' : 'Add employee';
        document.getElementById('employee-form').action = isEdit ? `/admin/employees/${record.id}` : '/admin/employees';

        document.getElementById('employee_number').value = isEdit ? record.employeeNumber : '';
        document.getElementById('full_name').value = isEdit ? record.fullName : '';
        document.getElementById('department_id').value = isEdit && record.departmentId ? record.departmentId : '';
        document.getElementById('record_status').value = isEdit ? record.status : 'Active';

        document.getElementById('employee-modal').classList.remove('hidden');
    }

    function closeEmployeeModal() {
        document.getElementById('employee-modal').classList.add('hidden');
    }

    /* ── Role, department, status ─────────────────────────────── */
    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({ message: 'The server did not answer. Please reload the page.' }));

        return { ok: response.ok, data };
    }

    async function changeRole(userId, select) {
        const previous = select.dataset.previous || '';
        const { ok, data } = await post(`/admin/accounts/${userId}/role`, { role: select.value });

        if (!ok) {
            alert(data.message || 'That role could not be changed.');
            select.value = previous;
            return;
        }

        select.dataset.previous = select.value;
        alert(data.message);
        window.location.reload();
    }

    async function changeDepartment(userId, select) {
        const { ok, data } = await post(`/admin/accounts/${userId}/department`, { department_id: select.value });

        if (!ok) {
            alert(data.message || 'That department could not be changed.');
            return;
        }

        alert(data.message);
        window.location.reload();
    }

    async function toggleAccount(userId, next) {
        const wording = next === 'Inactive'
            ? 'Deactivate this account? They will be signed out and will not be able to sign in again until it is reactivated. Their records are kept.'
            : 'Reactivate this account? They will be able to sign in again.';

        if (!confirm(wording)) return;

        const { ok, data } = await post(`/admin/accounts/${userId}/status`, { status: next });

        if (!ok) {
            alert(data.message || 'That change could not be saved.');
            return;
        }

        alert(data.message);
        window.location.reload();
    }

    /* ── History ──────────────────────────────────────────────── */
    async function showHistory(userId) {
        document.getElementById('history-modal').classList.remove('hidden');
        document.getElementById('history-body').textContent = 'Loading…';
        document.getElementById('history-subject').textContent = '';

        try {
            const response = await fetch(`/admin/accounts/${userId}/history`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                document.getElementById('history-body').textContent = data.message || 'That history could not be loaded.';
                return;
            }

            document.getElementById('history-subject').textContent =
                `${data.account.name} · ${data.account.email} · ${data.account.role}`;

            if (!data.history.length) {
                document.getElementById('history-body').innerHTML =
                    '<p class="text-[#5C6474]">Nothing has been recorded against this account yet.</p>';
                return;
            }

            document.getElementById('history-body').innerHTML = `<ul class="divide-y divide-[#F0EADB]">
                ${data.history.map(entry => `
                    <li class="py-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="badge badge-role">${entry.action}</span>
                            <span class="text-xs text-[#5C6474]">${entry.at}</span>
                        </div>
                        <p class="mt-1.5 text-[#0F2143]">${entry.notes || entry.description || ''}</p>
                    </li>`).join('')}
            </ul>`;
        } catch (e) {
            document.getElementById('history-body').textContent = 'That history could not be loaded.';
        }
    }

    function closeHistory() {
        document.getElementById('history-modal').classList.add('hidden');
    }

    /* ── CSV import ───────────────────────────────────────────── */
    const importNotice = document.getElementById('import-notice');
    const importResult = document.getElementById('import-result');
    const runButton = document.getElementById('import-run');
    const importFile = document.getElementById('import_file');
    let lastPreviewRows = [];

    // The import controls only exist on the Import tab. Without this guard the
    // listener throws on the other two tabs, which stops the rest of this
    // script - including the form that reopens with a rejected record.
    if (importFile) {
        importFile.addEventListener('change', function () {
            importNotice.classList.add('hidden');
            importResult.classList.add('hidden');
            lastPreviewRows = [];
            runButton.disabled = true;
            document.getElementById('import-run-label').textContent = 'Import';
        });
    }

    function fileForImport() {
        if (!importFile || !importFile.files.length) {
            alert('Choose the CSV file first.');
            return null;
        }

        const file = importFile.files[0];

        // Checked here as well as on the server, so an obvious mistake is
        // answered without a round trip. The server check remains the one that
        // decides; this is only a convenience.
        if (!/\.(csv|txt)$/i.test(file.name)) {
            notice(false, 'Only a CSV file can be imported. Download the template and fill that in.');
            return null;
        }

        if (!file.size) {
            notice(false, 'That file is empty. Download the template and fill it in.');
            return null;
        }

        if (file.size > 2 * 1024 * 1024) {
            notice(false, 'That file is larger than 2 MB. Split the list and import it in parts.');
            return null;
        }

        return file;
    }

    function notice(ok, message) {
        importNotice.classList.remove('hidden');
        importNotice.style.borderLeftColor = ok ? 'var(--forest)' : 'var(--brick)';
        importNotice.innerHTML = `<p class="text-sm" style="color:${ok ? 'var(--forest)' : 'var(--brick)'};">
            <i class="ri-${ok ? 'checkbox-circle' : 'error-warning'}-line mr-1.5"></i>${escapeHtml(message)}</p>`;
    }

    // A rejected-rows file the office can correct and upload again. Values are
    // quoted, and a value a spreadsheet would run as a formula is prefixed with
    // an apostrophe - the same guard CSV exports use on the server.
    function csvCell(value) {
        let text = String(value ?? '');
        if (/^[=+\-@\t\r]/.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function downloadRejected() {
        const rows = lastPreviewRows.filter(row => row.action !== 'import');

        if (!rows.length) {
            notice(false, 'There are no rejected rows to download.');
            return;
        }

        const header = ['Line', 'Employee Number', 'Full Name', 'Email', 'Department', 'Status', 'Outcome', 'Errors'];
        const lines = [header.map(csvCell).join(',')];

        rows.forEach(row => {
            lines.push([
                row.line,
                row.employee_number,
                row.full_name,
                row.email,
                row.department,
                row.status,
                row.action === 'skip' ? 'Skipped' : 'Rejected',
                (row.errors || []).join(' | '),
            ].map(csvCell).join(','));
        });

        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'employee-import-rejected-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        URL.revokeObjectURL(link.href);
        link.remove();
    }

    function badgeClass(action) {
        return action === 'import' ? 'badge-import' : (action === 'skip' ? 'badge-skip' : 'badge-error');
    }

    function badgeLabel(row) {
        if (row.action === 'import') return 'Will import';
        if (row.action === 'skip') return 'Skipped';
        return 'Not imported';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[character]));
    }

    function renderRows(rows) {
        if (!rows.length) {
            return '<p class="text-sm text-[#5C6474] p-4">The file has a header row but no data rows.</p>';
        }

        return `<div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-[#F5F0E2] text-[#5B6678] uppercase text-xs tracking-wider">
                        <th class="px-4 py-3 text-left font-semibold">Line</th>
                        <th class="px-4 py-3 text-left font-semibold">Employee ID</th>
                        <th class="px-4 py-3 text-left font-semibold">Name</th>
                        <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Email</th>
                        <th class="px-4 py-3 text-left font-semibold">Department</th>
                        <th class="px-4 py-3 text-left font-semibold">What happens</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F0EADB]">
                    ${rows.map(row => `
                        <tr class="hover:bg-[#FBF7EC]">
                            <td class="px-4 py-3 text-[#5C6474]">${row.line}</td>
                            <td class="px-4 py-3 font-mono text-xs">${escapeHtml(row.employee_number) || '—'}</td>
                            <td class="px-4 py-3">${escapeHtml(row.full_name) || '—'}</td>
                            <td class="px-4 py-3 hidden md:table-cell text-[#5B6678]">${escapeHtml(row.email) || '—'}</td>
                            <td class="px-4 py-3 text-[#5B6678]">${escapeHtml(row.department) || '—'}</td>
                            <td class="px-4 py-3">
                                <span class="badge ${badgeClass(row.action)}">${badgeLabel(row)}</span>
                                ${row.errors.length ? `<p class="text-xs mt-1.5 text-[#5C6474]">${row.errors.map(escapeHtml).join('<br>')}</p>` : ''}
                            </td>
                        </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
    }

    async function checkImport() {
        const file = fileForImport();
        if (!file) return;

        const body = new FormData();
        body.append('file', file);

        importResult.classList.add('hidden');

        try {
            const response = await fetch('/admin/employees/import/preview', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body,
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                notice(false, data.message || 'That file could not be checked.');
                return;
            }

            const summary = data.summary;
            lastPreviewRows = data.rows || [];
            notice(true, `${summary.total} row(s) read: ${summary.ready} ready to import, ${summary.skipped} already on the masterlist, ${summary.invalid} with an error.`);

            const rejected = lastPreviewRows.filter(row => row.action !== 'import');

            document.getElementById('import-result').classList.remove('hidden');
            document.getElementById('import-result').innerHTML = renderRows(lastPreviewRows)
                + (rejected.length
                    ? `<div class="mt-3"><button type="button" class="btn-ghost" onclick="downloadRejected()">
                        <i class="ri-download-2-line mr-1.5"></i>Download rejected rows (${rejected.length})
                       </button></div>`
                    : '');

            runButton.disabled = summary.ready === 0;
            document.getElementById('import-run-label').textContent = `Import ${summary.ready} record(s)`;
        } catch (e) {
            notice(false, 'That file could not be checked. Please try again.');
        }
    }

    async function runImport() {
        const file = fileForImport();
        if (!file) return;

        if (!confirm('Import the checked rows into the employee masterlist? Employees already on the list are skipped.')) return;

        const body = new FormData();
        body.append('file', file);

        try {
            const response = await fetch('/admin/employees/import', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body,
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                notice(false, data.message || 'The import did not go through, so nothing was written.');
                return;
            }

            const parts = [`${data.imported} record(s) imported`];
            if (data.skipped) parts.push(`${data.skipped} skipped as already on the masterlist`);
            if (data.invalid) parts.push(`${data.invalid} row(s) had an error and were not imported`);

            notice(true, parts.join(' · ') + '.');

            if (data.errors && data.errors.length) {
                document.getElementById('import-result').classList.remove('hidden');
                document.getElementById('import-result').innerHTML = `
                    <div class="card p-4">
                        <p class="eyebrow mb-2">Rows that were not imported</p>
                        <ul class="text-sm text-[#5B6678] list-disc pl-5">
                            ${data.errors.map(row => `<li>Line ${row.line}: ${row.errors.map(escapeHtml).join(' ')}</li>`).join('')}
                        </ul>
                        ${lastPreviewRows.some(row => row.action !== 'import')
                            ? `<button type="button" class="btn-ghost mt-3" onclick="downloadRejected()">
                                <i class="ri-download-2-line mr-1.5"></i>Download rejected rows
                               </button>`
                            : ''}
                    </div>`;
            } else {
                document.getElementById('import-result').classList.add('hidden');
            }

            runButton.disabled = true;
            document.getElementById('import-run-label').textContent = 'Import';
        } catch (e) {
            notice(false, 'The import could not be completed. Nothing was written.');
        }
    }

    /* A rejected add or edit comes back as a normal page load with old input:
       reopen the form so the message is next to the fields it belongs to. */
    @if($errors->any() && old('employee_number'))
        openEmployeeModal();
    @endif
</script>

@endsection
