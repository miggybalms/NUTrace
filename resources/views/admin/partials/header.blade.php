{{-- ============================================================
     Shared Admin Page Header
     Renders the page topbar (title, badge, description) plus a
     per-page action set. Each page passes its own $adminHeaderPage,
     so only that page's buttons appear on that page.

     Usage:
     @include('admin.partials.header', [
         'adminHeaderPage'     => 'requests',
         'adminHeaderTitle'    => 'Requests',
         'adminHeaderSubtitle' => 'Manage and process asset requests',
         'adminHeaderIcon'     => 'ri-file-list-3-line',   // optional
         'adminHeaderBadge'    => 'Admin',                  // optional
         'adminHeaderBackUrl'  => '/admin/assets',           // optional: shows back arrow instead of hamburger
     ])

     Download buttons:
       - assets              -> /admin/inventory-download   (server CSV)
       - audit logs          -> /admin/audit-logs/export    (server CSV)
       - requests            -> exportRequests()            (page's own exporter)
     ============================================================ --}}
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
                    <a href="{{ $adminHeaderBackUrl }}" class="text-[#8991A0] hover:text-[#33425C] mr-3.5 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-[#EFE9D8] transition-colors flex-shrink-0" title="Back">
                        <i class="ri-arrow-left-line text-xl"></i>
                    </a>
                @else
                    <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-400,#8991A0);" title="Menu">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                @endif
                <div class="min-w-0">
                    <h2 class="font-display text-xl sm:text-2xl font-semibold tracking-tight" style="color:var(--navy-900,#0F2143);">{{ $adminHeaderTitle }}</h2>
                    <div class="flex items-center mt-1.5 min-w-0">
                        <span class="badge-role inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-0.5 rounded-md flex-shrink-0">
                            <i class="{{ $adminHeaderIcon ?? 'ri-shield-user-line' }}"></i> {{ $adminHeaderBadge ?? 'Admin' }}
                        </span>
                        <p class="text-sm hidden sm:block ml-3 truncate" style="color:var(--ink-600,#5B6678);">{{ $adminHeaderSubtitle }}</p>
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

                    {{-- Repair: create repair --}}
                    @case('repair')
                        <button type="button" onclick="openNewRepairModal()" class="btn-gold">
                            <i class="ri-add-line mr-1.5 text-base"></i>
                            New Repair Request
                        </button>
                        @break

                    {{-- Replacement: search + profile --}}
                    @case('replacement')
                        <div class="relative flex-1 sm:flex-none">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400,#8991A0);"></i>
                            <input type="text" id="searchInput" placeholder="Search replacements..."
                                class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-full sm:w-56"/>
                        </div>
                        <div class="flex items-center space-x-2 cursor-pointer rounded-lg px-2 py-1 flex-shrink-0" onmouseover="this.style.background='var(--paper-2,#EAE2C9)'" onmouseout="this.style.background='transparent'">
                            <div class="avatar-badge w-8 h-8 rounded-full flex items-center justify-center">
                                <span class="text-xs font-semibold">{{ strtoupper(substr(Auth::user()->full_name ?? 'A', 0, 1)) }}</span>
                            </div>
                            <i class="ri-arrow-down-s-line hidden sm:block" style="color:var(--ink-400,#8991A0);"></i>
                        </div>
                        @break

                    {{-- Audit logs: search + date filter + server CSV export + profile --}}
                    @case('audit_logs')
                        <div class="relative">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400,#8991A0);"></i>
                            <input type="text" id="searchInput" placeholder="Search logs..." value="{{ $search ?? '' }}"
                                class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-56"/>
                        </div>
                        <input type="date" id="dateFilter" value="{{ $date ?? '' }}"
                            class="search-input px-3 py-2.5 rounded-lg text-sm" style="color:var(--ink-600,#46536B);"/>
                        <a href="{{ url('/admin/audit-logs/export') }}" class="btn-ghost" download>
                            <i class="ri-download-line mr-2"></i>
                            Export
                        </a>
                        <div class="flex items-center space-x-2 cursor-pointer rounded-lg px-2 py-1" onmouseover="this.style.background='var(--paper-2,#EFE9D8)'" onmouseout="this.style.background='transparent'">
                            <div class="avatar-badge w-8 h-8 rounded-full flex items-center justify-center">
                                <span class="text-xs font-semibold">{{ strtoupper(substr(Auth::user()->full_name ?? 'A', 0, 1)) }}</span>
                            </div>
                            <i class="ri-arrow-down-s-line" style="color:var(--ink-400,#8991A0);"></i>
                        </div>
                        @break

                    {{-- Pullout --}}
                    @case('pullout')
                        <button type="button" onclick="openScannerAuto()" class="btn-gold">
                            <i class="ri-add-line sm:mr-2"></i>
                            <span class="hidden sm:inline">Record Pullout</span>
                        </button>
                        @break

                    {{-- Department assets: view-only list, no action buttons --}}
                    @case('department_assets')
                        @break

                    {{-- Disposal --}}
                    @case('disposal')
                        <button type="button" onclick="openScannerAuto()" class="btn-gold">
                            <i class="ri-add-line sm:mr-2"></i>
                            <span class="hidden sm:inline">Record Disposal</span>
                        </button>
                        @break
                @endswitch

                {{-- Optional page-specific actions (e.g. dashboard alert icons) --}}
                @yield('admin_header_actions')
            </div>
        </div>
    </div>
</div>

