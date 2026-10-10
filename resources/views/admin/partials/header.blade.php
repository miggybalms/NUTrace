{{-- ============================================================
     Shared Admin Page Header
     Renders the page topbar (title, badge, description) plus a
     per-page action set. Each page passes its own $adminHeaderPage,
     so only that page's buttons appear on that page.

     Usage:
     @include('admin.partials.header', [
         'adminHeaderPage'     => 'requests',
         'adminHeaderTitle'    => 'Requests',
         'adminHeaderIcon'     => 'ri-file-list-3-line',   // optional
         'adminHeaderBadge'    => 'Admin',                  // optional
         'adminHeaderBackUrl'  => '/admin/assets',           // optional: shows back arrow instead of hamburger
     ])

     Download buttons:
       - assets              -> /admin/inventory-download   (server CSV)
       - audit logs          -> /admin/audit-logs/export    (server CSV)
       - requests            -> exportRequests()            (page's own exporter)
     ============================================================ --}}
@php
    $adminUser = Auth::user();
    $adminName = $adminUser?->display_name ?? 'User';
    $adminInitials = $adminUser?->initials ?? 'U';
    $adminPhoto = $adminUser?->profile_photo_url;

    // Department label for the profile menu; the Admin account is not tied to a
    // teaching department, so an unknown value simply hides the row.
    $adminDepartment = null;
    if ($adminUser && $adminUser->department_id) {
        $adminDepartment = \Illuminate\Support\Facades\DB::table('departments')
            ->where('id', $adminUser->department_id)
            ->value('Name');
    }
@endphp

<style>
    .admin-topbar{ background:#fff; border-bottom:1px solid var(--line,#E4DCC6); position:relative; }
    .admin-topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500,#C9A227) 20%, var(--gold-500,#C9A227) 80%, transparent); opacity:.7; }
    .admin-topbar .search-input{ border:1px solid var(--line,#E4DCC6); background:#fff; transition:border-color .15s, box-shadow .15s; }
    .admin-topbar .search-input:focus{ outline:none; border-color:var(--gold-500,#C9A227); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .admin-topbar .avatar-badge{ background:var(--navy-950,#0A1830); color:var(--gold-500,#C9A227); border:1px solid var(--gold-500,#C9A227); }
    .admin-topbar .badge-role{ background:var(--gold-100,#F3E7C4); color:var(--navy-900,#0F2143); }
    .admin-topbar .btn-gold{ background:var(--gold-500,#C9A227); color:#0A1830; font-weight:600; padding:.625rem 1.1rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; transition:background .15s; font-size:.875rem; }
    .admin-topbar .btn-gold:hover{ background:var(--gold-400,#E0BC44); }
    .admin-topbar .btn-ghost{ background:#fff; color:var(--ink-600,#5B6678); border:1px solid var(--gold-500,#C9A227); font-weight:600; padding:.625rem 1.1rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; transition:background .15s; font-size:.875rem; }
    .admin-topbar .btn-ghost:hover{ background:var(--paper-2,#EFE9D8); }
</style>

<div class="admin-topbar sticky top-0 z-10">
    <div class="px-4 sm:px-8 py-5">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div class="flex items-center min-w-0">
                @if(!empty($adminHeaderBackUrl))
                    <a href="{{ $adminHeaderBackUrl }}" class="text-[#5C6474] hover:text-[#33425C] mr-3.5 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-[#EFE9D8] transition-colors flex-shrink-0" title="Back">
                        <i class="ri-arrow-left-line text-xl"></i>
                    </a>
                @else
                    <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-400,#5C6474);" title="Menu">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                @endif
                <div class="flex items-center gap-3 min-w-0">
                    <span class="hidden sm:flex w-10 h-10 rounded-xl items-center justify-center flex-shrink-0" style="background:var(--gold-100,#F3E7C4);" aria-hidden="true">
                        <i class="{{ $adminHeaderIcon ?? 'ri-shield-user-line' }} text-lg" style="color:var(--navy-900,#0F2143);"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-xl sm:text-2xl font-semibold tracking-tight" style="color:var(--navy-900,#0F2143);">{{ $adminHeaderTitle }}</h2>
                        <div class="flex items-center gap-2 mt-1 min-w-0">
                            <span class="avatar-badge w-[22px] h-[22px] rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0">{{ $adminInitials }}</span>
                            <p class="text-sm truncate">
                                <span class="font-semibold" style="color:var(--navy-900,#0F2143);">{{ $adminName }}</span>
                                <span class="mx-1.5" style="color:var(--ink-400,#5C6474);">•</span>
                                <span style="color:var(--ink-600,#5B6678);">{{ $adminHeaderBadge ?? 'Admin' }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @switch($adminHeaderPage ?? '')
                    {{-- Dashboard: page-specific actions come via @section('admin_header_actions') --}}
                    @case('dashboard')
                        @break

                    {{-- Requests: export the visible request rows --}}
                    @case('requests')
                        <button type="button" onclick="exportRequests()" class="btn-gold">
                            <i class="ri-file-copy-line mr-2"></i>
                            Export Report
                        </button>
                        @break

                    {{-- Assets: download inventory + register new asset --}}
                    @case('assets')
                        <a href="/admin/inventory-download" class="btn-ghost" download>
                            <i class="ri-download-line mr-1.5"></i>
                            <span class="whitespace-nowrap">Download Inventory</span>
                        </a>
                        <a href="/admin/assets/registry" class="btn-gold">
                            <i class="ri-add-line mr-1.5"></i>
                            <span class="whitespace-nowrap">Add New Asset</span>
                        </a>
                        @break

                    {{-- Asset Registry form --}}
                    @case('asset_registry')
                        <button type="button" class="btn-ghost" onclick="openRegistryHelp()" title="Guided walkthrough: how to fill in the Asset Registry form">
                            <i class="ri-question-line mr-2"></i>
                            Help
                        </button>
                        @break

                    {{-- Repair: search the queue + create repair --}}
                    @case('repair')
                        <div class="relative flex-1 sm:flex-none">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400,#5C6474);" aria-hidden="true"></i>
                            <input type="text" id="searchRepairs" placeholder="Search repairs..."
                                aria-label="Search repair requests by asset, code, requester or issue"
                                autocomplete="off"
                                class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-full sm:w-56"/>
                        </div>
                        <button type="button" onclick="openNewRepairModal()" class="btn-gold">
                            <i class="ri-add-line mr-1.5 text-base"></i>
                            New Repair Request
                        </button>
                        @break

                    {{-- Replacement: search + profile --}}
                    @case('replacement')
                        <div class="relative flex-1 sm:flex-none">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400,#5C6474);"></i>
                            <input type="text" id="searchInput" placeholder="Search replacements..."
                                class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-full sm:w-56"/>
                        </div>
                        @break

                    {{-- Audit logs: search + date filter + server CSV export --}}
                    @case('audit_logs')
                        <div class="relative">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400,#5C6474);"></i>
                            <input type="text" id="searchInput" placeholder="Search logs..." value="{{ $search ?? '' }}"
                                aria-label="Search audit log entries"
                                class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-56"/>
                        </div>
                        <input type="date" id="dateFilter" value="{{ $date ?? '' }}"
                            aria-label="Filter audit log entries by date"
                            class="search-input px-3 py-2.5 rounded-lg text-sm" style="color:var(--ink-600,#46536B);"/>
                        <a href="{{ url('/admin/audit-logs/export') }}" class="btn-ghost" download>
                            <i class="ri-download-line mr-2"></i>
                            Export
                        </a>
                        @break

                    {{-- Transfer: search the employee list and the transfer history --}}
                    @case('transfer')
                        <div class="relative flex-1 sm:flex-none">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400,#5C6474);" aria-hidden="true"></i>
                            <input type="text" id="transferSearch" placeholder="Search employees..."
                                aria-label="Search employees by name, employee number or department"
                                autocomplete="off"
                                class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-full sm:w-64"/>
                        </div>
                        @break

                    {{-- Pullout --}}
                    @case('pullout')
                        {{-- The visible label is hidden below the sm breakpoint, so the
                             button carries a name that does not depend on the viewport. --}}
                        <button type="button" onclick="openScannerAuto()" class="btn-gold" aria-label="Record pullout">
                            <i class="ri-add-line sm:mr-2" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Record Pullout</span>
                        </button>
                        @break

                    {{-- Department assets: view-only list, no action buttons --}}
                    @case('department_assets')
                        @break

                    {{-- Disposal: view-only. Disposal records are created by
                         approving a Disposal request on the Requests page, so
                         there is no "record a disposal" action here. --}}
                    @case('disposal')
                        @break
                @endswitch

                {{-- Optional page-specific actions (e.g. dashboard alert icons) --}}
                @yield('admin_header_actions')


                {{-- Profile chip + dropdown --}}
                {{-- Same behaviour as the employee and department-head headers: the
                     chip opens a menu with the signed-in account and logout. --}}
                <div class="relative" id="admin-profile-wrapper">
                    <button type="button"
                            id="admin-profile-btn"
                            class="flex items-center space-x-2 cursor-pointer rounded-lg px-2 py-1 focus:outline-none"
                            style="transition:background .15s;"
                            onmouseover="this.style.background='var(--paper-2,#EFE9D8)'"
                            onmouseout="this.style.background='transparent'"
                            aria-haspopup="true"
                            aria-expanded="false">
                        <span class="avatar-badge w-8 h-8 rounded-full flex items-center justify-center overflow-hidden">
                            @if($adminPhoto)
                                <img src="{{ $adminPhoto }}" class="w-8 h-8 object-cover" alt="Profile">
                            @else
                                <span class="text-xs font-semibold">{{ $adminInitials }}</span>
                            @endif
                        </span>
                        <i class="ri-arrow-down-s-line" style="color:var(--ink-400,#5C6474);"></i>
                    </button>

                    <div id="admin-profile-dropdown"
                         class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl z-50 overflow-hidden"
                         style="border:1px solid var(--line,#E4DCC6);">
                        <div class="px-4 py-4" style="background:linear-gradient(to right,#F8F1DE,#EFE5C9); border-bottom:1px solid #E3D6B0;">
                            <div class="flex items-center gap-3">
                                <span class="avatar-badge w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    @if($adminPhoto)
                                        <img src="{{ $adminPhoto }}" class="w-12 h-12 object-cover" alt="Profile">
                                    @else
                                        <span class="text-lg font-semibold">{{ $adminInitials }}</span>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold truncate" style="color:var(--navy-900,#0F2143);">{{ $adminName }}</p>
                                    <p class="text-xs truncate" style="color:var(--ink-600,#5B6678);">{{ $adminUser?->email ?? 'No email' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="px-4 py-3 space-y-2.5 text-sm">
                            <div class="flex items-center gap-2.5">
                                <i class="ri-user-3-line text-base" style="color:var(--ink-400,#5C6474);"></i>
                                <div>
                                    <p class="text-xs" style="color:var(--ink-400,#5C6474);">Role</p>
                                    <p class="font-medium" style="color:var(--navy-800,#15305B);">{{ $adminUser?->role ?? ($adminHeaderBadge ?? 'Admin') }}</p>
                                </div>
                            </div>
                            @if($adminDepartment)
                            <div class="flex items-center gap-2.5">
                                <i class="ri-building-2-line text-base" style="color:var(--ink-400,#5C6474);"></i>
                                <div>
                                    <p class="text-xs" style="color:var(--ink-400,#5C6474);">Department</p>
                                    <p class="font-medium" style="color:var(--navy-800,#15305B);">{{ $adminDepartment }}</p>
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="px-2 py-2" style="border-top:1px solid #EFE9D8;">
                            <a href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('admin-logout-form').submit();"
                               class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium"
                               style="color:#A23B32;">
                                <i class="ri-logout-box-r-line text-base"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>

                <form id="admin-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__adminProfileInit) return;
    window.__adminProfileInit = true;

    const btn  = document.getElementById('admin-profile-btn');
    const menu = document.getElementById('admin-profile-dropdown');
    const wrap = document.getElementById('admin-profile-wrapper');
    if (!btn || !menu || !wrap) return;

    function closeMenu() {
        menu.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const willOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden', !willOpen);
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        // The dashboard's alert dropdowns (requests / maintenance / lifespan)
        // share this corner of the topbar, so only one of them stays open.
        if (willOpen && typeof window.closeAllAlertDropdowns === 'function') {
            window.closeAllAlertDropdowns();
        }
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closeMenu();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });
})();
</script>

