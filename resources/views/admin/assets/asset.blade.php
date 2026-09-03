<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Assets Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root{
            --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
            --line:#E6DFCD;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: var(--paper);
            color: var(--ink-900);
        }

        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-family:'Inter',sans-serif; font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }

        .sidebar-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background-color: #374151;
        }

        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }

        .badge-role{ background:var(--gold-100); color:var(--navy-900); }

        .btn-gold{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.62rem 1.15rem; background:var(--gold-500); color:var(--navy-950); display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-gold:hover{ filter:brightness(1.06); }
        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:500; border-radius:9px; padding:.6rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; transition:background .15s; }
        .btn-ghost:hover{ background:var(--paper-2); }

        .department-card {
            background:#fff; border:1px solid #DED2AE; border-radius:14px;
            box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28);
            transition: all 0.2s ease;
        }

        .department-card:hover {
            border-color: var(--gold-500);
            box-shadow: 0 2px 4px rgba(10,24,48,.06), 0 14px 30px -14px rgba(10, 24, 48, 0.3);
        }

        .stat-tile{ background:#fff; border:1px solid var(--line); border-radius:10px; transition: all .15s ease; }
        .stat-tile:hover{ border-color:var(--gold-500); box-shadow:0 6px 16px -10px rgba(10,24,48,.25); }

        .asset-row {
            transition: background-color 0.2s ease;
        }

        .asset-row:hover {
            background-color: var(--paper-2);
        }

        .rotate-transition {
            transition: transform 0.3s ease;
        }

        .rotate-180 {
            transform: rotate(180deg);
        }

        .collapse-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
        }

        .collapse-content.expanded {
            max-height: 2000px;
            transition: max-height 0.5s ease-in;
        }

        .status-badge {
            transition: all 0.2s ease;
        }

        .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
        .form-input{ width:100%; border:1px solid var(--line); border-radius:9px; padding:.6rem .9rem; font-size:.9rem; outline:none; transition:border-color .15s, box-shadow .15s; }
        .form-input:focus{ border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-panel {
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toast-notification {
            animation: toastIn 0.2s ease;
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
        <!-- Header -->
        <div class="topbar sticky top-0 z-10">
            <div class="px-4 sm:px-8 py-5">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div class="flex items-center">
                        <!-- Hamburger, mobile only -->
                        <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-400);">
                            <i class="ri-menu-line text-2xl"></i>
                        </button>
                        <div>
                            <h2 class="font-display text-xl sm:text-2xl font-semibold tracking-tight" style="color:var(--navy-900);">Assets</h2>
                            <div class="flex items-center mt-1.5">
                                <span class="badge-role inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-0.5 rounded-md">
                                    <i class="ri-computer-line"></i> Asset Officer
                                </span>
                                <p class="text-sm hidden sm:block ml-3" style="color:var(--ink-600);">Manage and track all university assets across departments</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/admin/inventory-download" class="btn-ghost flex-1 sm:flex-none justify-center inline-flex items-center text-sm" download>
                            <i class="ri-download-line mr-1.5"></i>
                            <span class="whitespace-nowrap">Download Inventory</span>
                        </a>
                        <a href="/admin/assets/registry" class="btn-gold flex-1 sm:flex-none justify-center inline-flex items-center text-sm">
                            <i class="ri-add-line mr-1.5"></i>
                            <span class="whitespace-nowrap">Add New Asset</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Status Legend -->
            <div class="px-4 sm:px-8 py-3.5" style="background:var(--paper-2); border-top:1px solid var(--line);">
                <p class="eyebrow mb-2.5">Asset Lifecycle Statuses</p>
                <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-x-5 gap-y-2">
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-blue-600 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Acquired</strong> — Newly registered</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-green-600 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Active</strong> — In use</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-purple-600 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Checking</strong> — Evaluation pending</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-amber-500 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Repair</strong> — Needs maintenance</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-pink-600 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Replace</strong> — Replacement in progress</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-slate-500 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Pullout</strong> — Transferred out</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-red-600 rounded-full flex-shrink-0"></div>
                        <span style="color:var(--ink-600);"><strong style="color:var(--ink-900);">Disposed</strong> — Removed from service</span>
                    </div>
                </div>
            </div>
        </div>

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <!-- Create Department Button -->
                <div class="mb-6 flex justify-end">
                    <button 
                        onclick="openCreateDepartmentModal()" 
                        class="btn-gold w-full sm:w-auto justify-center flex items-center text-sm"
                    >
                        <i class="ri-add-line mr-1.5"></i>
                        Create New Department
                    </button>
                </div>

                <!-- Department Cards -->
                <div class="space-y-5" id="departmentsContainer">
                    @if(count($departments) > 0)
                        @foreach($departments as $dept)
                    <div class="department-card overflow-hidden" data-department="{{ strtolower($dept->name) }}">
                        <!-- Department Header -->
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-3">
                                <div class="flex-1 cursor-pointer" onclick="toggleDepartment({{ $dept->id }})">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div>
                                            <h3 class="font-display text-lg sm:text-xl font-semibold" style="color:var(--navy-900);">{{ $dept->name }}</h3>
                                            <div class="flex items-center mt-1">
                                                @if($dept->head_email)
                                                    <p class="text-sm truncate" style="color:var(--ink-600);">{{ $dept->head_email }}</p>
                                                @else
                                                    <p class="text-sm italic" style="color:var(--ink-400);">No Department Head</p>
                                                @endif
                                                <button type="button" onclick="event.stopPropagation(); openAssignDeptHeadModal({{ $dept->id }}, '{{ $dept->name }}')" class="ml-2 transition-colors flex-shrink-0" style="color:var(--ink-400);" onmouseover="this.style.color='var(--gold-600)'" onmouseout="this.style.color='var(--ink-400)'">
                                                    <i class="ri-edit-line text-base"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-left sm:text-right">
                                            <p class="text-2xl font-bold" style="color:var(--navy-900);">{{ $dept->total_assets }}</p>
                                            <p class="text-xs font-medium" style="color:var(--ink-400);">Total Assets</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- View All + Expand row -->
                                <div class="flex items-center gap-2 justify-end lg:justify-start lg:ml-3">
                                    <a href="/admin/assets/department/{{ $dept->id }}" class="flex-1 lg:flex-none justify-center px-3.5 py-2 text-sm font-medium rounded-lg transition-colors flex items-center whitespace-nowrap" style="background:var(--gold-100); color:var(--navy-900);" onmouseover="this.style.background='var(--gold-500)'" onmouseout="this.style.background='var(--gold-100)'">
                                        <i class="ri-eye-line mr-1.5"></i>
                                        View All
                                    </a>
                                    <div class="cursor-pointer flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition-colors" style="color:var(--ink-400);" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'" onclick="toggleDepartment({{ $dept->id }})">
                                        <i class="ri-arrow-down-s-line text-xl rotate-transition" id="icon-{{ $dept->id }}"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Collapsible Content -->
                            <div id="content-{{ $dept->id }}" class="collapse-content -mx-4 sm:-mx-6 mt-0" style="border-top:1px solid var(--line);">
                                <div class="p-4 sm:p-6" style="background:var(--paper-2);">
                                    <!-- Status Summary Cards -->
                                    @php
                                        $acquired = 0;
                                        $active = 0;
                                        $forChecking = 0;
                                        $forRepair = 0;
                                        $forReplacement = 0;
                                        $pulledOut = 0;
                                        $disposed = 0;
                                        
                                        foreach($dept->assets as $asset) {
                                            switch($asset->status) {
                                                case 'acquired': $acquired++; break;
                                                case 'active': $active++; break;
                                                case 'for_checking': $forChecking++; break;
                                                case 'for_repair': $forRepair++; break;
                                                case 'for_replacement': $forReplacement++; break;
                                                case 'pulled_out': $pulledOut++; break;
                                                case 'disposed': $disposed++; break;
                                            }
                                        }
                                    @endphp

                                    <div class="mb-4">
                                        <p class="eyebrow mb-2">Asset Lifecycle Distribution</p>
                                        <div class="h-2.5 w-full rounded-full overflow-hidden flex" style="background:var(--line);">
                                            @if($dept->total_assets > 0)
                                                @if($acquired > 0)<div class="h-full bg-blue-600" style="width: {{ round(($acquired / $dept->total_assets) * 100) }}%" title="Acquired: {{ $acquired }}"></div>@endif
                                                @if($active > 0)<div class="h-full bg-green-600" style="width: {{ round(($active / $dept->total_assets) * 100) }}%" title="Active: {{ $active }}"></div>@endif
                                                @if($forChecking > 0)<div class="h-full bg-purple-600" style="width: {{ round(($forChecking / $dept->total_assets) * 100) }}%" title="Checking: {{ $forChecking }}"></div>@endif
                                                @if($forRepair > 0)<div class="h-full bg-amber-500" style="width: {{ round(($forRepair / $dept->total_assets) * 100) }}%" title="Repair: {{ $forRepair }}"></div>@endif
                                                @if($forReplacement > 0)<div class="h-full bg-pink-600" style="width: {{ round(($forReplacement / $dept->total_assets) * 100) }}%" title="Replace: {{ $forReplacement }}"></div>@endif
                                                @if($pulledOut > 0)<div class="h-full bg-slate-500" style="width: {{ round(($pulledOut / $dept->total_assets) * 100) }}%" title="Pullout: {{ $pulledOut }}"></div>@endif
                                                @if($disposed > 0)<div class="h-full bg-red-600" style="width: {{ round(($disposed / $dept->total_assets) * 100) }}%" title="Disposed: {{ $disposed }}"></div>@endif
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Assets newly registered into system">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Acquired</p>
                                            <p class="text-lg font-bold text-blue-600">{{ $acquired }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($acquired / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Operational assets in service">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Active</p>
                                            <p class="text-lg font-bold text-green-600">{{ $active }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($active / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Assets pending evaluation after expiration">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Checking</p>
                                            <p class="text-lg font-bold text-purple-600">{{ $forChecking }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($forChecking / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Assets requiring maintenance or repair">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Repair</p>
                                            <p class="text-lg font-bold text-amber-500">{{ $forRepair }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($forRepair / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Assets being replaced due to condition">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Replace</p>
                                            <p class="text-lg font-bold text-pink-600">{{ $forReplacement }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($forReplacement / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Assets transferred out of inventory">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Pullout</p>
                                            <p class="text-lg font-bold text-slate-500">{{ $pulledOut }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($pulledOut / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="stat-tile text-center p-3 cursor-pointer" title="Assets removed from service">
                                            <p class="text-xs mb-1" style="color:var(--ink-600);">Disposed</p>
                                            <p class="text-lg font-bold text-red-600">{{ $disposed }}</p>
                                            <p class="text-[11px]" style="color:var(--ink-400);">({{ $dept->total_assets > 0 ? round(($disposed / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center p-12" style="background:#fff; border-radius:14px; border:1px solid var(--line);">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--paper-2);">
                                <i class="ri-building-line text-2xl" style="color:var(--ink-400);"></i>
                            </div>
                            <p class="font-medium" style="color:var(--ink-900);">No departments created yet</p>
                            <p class="text-sm mt-1" style="color:var(--ink-400);">Click "Create New Department" to get started</p>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="text-center text-xs mt-10 pt-6" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Create Department Modal -->
    <div id="createDepartmentModal" class="fixed inset-0 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);" onclick="closeCreateDepartmentModal(event)">
        <div class="modal-panel rounded-2xl shadow-xl max-w-md w-full mx-4" style="background:#fff; overflow:hidden;" onclick="event.stopPropagation()">
            <div class="modal-head px-6 py-5 flex justify-between items-center">
                <h3 class="font-display text-lg font-semibold text-white">Create New Department</h3>
                <button onclick="closeCreateDepartmentModal()" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="createDeptForm">
                    @csrf
                    <div class="mb-4">
                        <label for="modalDeptName" class="eyebrow block mb-1.5">Department Name</label>
                        <input 
                            type="text" 
                            id="modalDeptName" 
                            name="department_name"
                            placeholder="Enter department name"
                            required
                            class="form-input"
                        />
                    </div>
                    
                    <div id="modalError" class="flex items-start gap-2 text-sm mb-4 rounded-lg px-3 py-2.5" style="display: none; color:#7E2E27; background:#F7E9E6; border:1px solid #E7C9C1;"></div>
                    <div id="modalSuccess" class="flex items-start gap-2 text-sm mb-4 rounded-lg px-3 py-2.5" style="display: none; color:#245C3B; background:#EAF4EE; border:1px solid #BFDEC7;"></div>
                    
                    <div class="flex gap-3 justify-end pt-2">
                        <button 
                            type="button" 
                            onclick="closeCreateDepartmentModal()"
                            class="btn-ghost text-sm"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="btn-gold flex items-center text-sm"
                        >
                            <i class="ri-add-line mr-1.5"></i>
                            Create Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Department Head Modal -->
    <div id="assignDeptHeadModal" class="fixed inset-0 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);" onclick="closeAssignDeptHeadModal(event)">
        <div class="modal-panel rounded-2xl shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;" onclick="event.stopPropagation()">
            <div class="modal-head px-6 py-5 flex justify-between items-center">
                <h3 id="assignDeptHeadTitle" class="font-display text-lg font-semibold text-white">Assign Department Head</h3>
                <button onclick="closeAssignDeptHeadModal()" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p class="text-sm mb-4" style="color:var(--ink-600);">Select a user from this department to assign as Department Head:</p>
                <div id="deptUsersList" class="max-h-96 overflow-y-auto divide-y" style="border:1px solid var(--line); border-radius:9px; border-color:var(--line);">
                    <p class="text-center py-6 text-sm" style="color:var(--ink-400);">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDepartment(deptId) {
            const content = document.getElementById(`content-${deptId}`);
            const icon = document.getElementById(`icon-${deptId}`);
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                icon.classList.remove('rotate-180');
            } else {
                content.classList.add('expanded');
                icon.classList.add('rotate-180');
            }
        }
        
        function openCreateDepartmentModal() {
            document.getElementById('createDepartmentModal').classList.remove('hidden');
        }
        
        function closeCreateDepartmentModal(event) {
            if (event && event.target.id !== 'createDepartmentModal') return;
            document.getElementById('createDepartmentModal').classList.add('hidden');
            document.getElementById('modalDeptName').value = '';
            document.getElementById('modalError').style.display = 'none';
            document.getElementById('modalSuccess').style.display = 'none';
        }
        
        document.getElementById('createDeptForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const deptName = document.getElementById('modalDeptName').value.trim();
            const errorEl = document.getElementById('modalError');
            const successEl = document.getElementById('modalSuccess');
            
            errorEl.style.display = 'none';
            successEl.style.display = 'none';
            
            if (!deptName) {
                errorEl.textContent = 'Department name is required';
                errorEl.style.display = 'block';
                return;
            }
            
            try {
                const response = await fetch('/admin/create-department', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ department_name: deptName })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Department created successfully!';
                    successEl.style.display = 'block';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    errorEl.textContent = data.message || 'Failed to create department';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });
        
        // Open first department by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstDeptCard = document.querySelector('.department-card');
            if (firstDeptCard) {
                const deptId = firstDeptCard.getAttribute('data-department');
                const content = document.querySelector(`#content-1`);
                const icon = document.querySelector(`#icon-1`);
                if (content && icon) {
                    content.classList.add('expanded');
                    icon.classList.add('rotate-180');
                }
            }
        });
        
        // Assign Department Head Modal Functions
        function openAssignDeptHeadModal(deptId, deptName) {
            document.getElementById('assignDeptHeadModal').classList.remove('hidden');
            document.getElementById('assignDeptHeadModal').setAttribute('data-dept-id', deptId);
            document.getElementById('assignDeptHeadTitle').textContent = `Assign Department Head - ${deptName}`;
            loadDepartmentUsers(deptId);
        }
        
        function closeAssignDeptHeadModal(event) {
            if (event && event.target.id !== 'assignDeptHeadModal') return;
            document.getElementById('assignDeptHeadModal').classList.add('hidden');
            document.getElementById('deptUsersList').innerHTML = '';
        }
        
        async function loadDepartmentUsers(deptId) {
            const usersList = document.getElementById('deptUsersList');
            usersList.innerHTML = '<p class="text-slate-500 text-center py-6 text-sm">Loading...</p>';
            
            try {
                const response = await fetch(`/admin/department/${deptId}/users`);
                const data = await response.json();
                
                if (data.success && data.users.length > 0) {
                    usersList.innerHTML = data.users.map(user => `
                        <button type="button" onclick="assignDepartmentHead(${deptId}, ${user.id}, '${user.full_name}')" class="w-full text-left p-3.5 hover:bg-blue-50 transition-colors flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-slate-900">${user.full_name}</p>
                                <p class="text-xs text-slate-500">${user.email}</p>
                                <p class="text-xs text-slate-400">Employee #${user.unit_heads_number}</p>
                            </div>
                            <i class="ri-arrow-right-line text-slate-400"></i>
                        </button>
                    `).join('');
                } else {
                    usersList.innerHTML = '<p class="text-slate-500 text-center py-6 text-sm">No users in this department</p>';
                }
            } catch (error) {
                usersList.innerHTML = `<p class="text-red-500 text-center py-6 text-sm">Error loading users: ${error.message}</p>`;
            }
        }
        
        async function assignDepartmentHead(deptId, userId, userName) {
            try {
                const response = await fetch(`/admin/department/${deptId}/assign-head`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ user_id: userId })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showToast(`✓ ${userName} assigned as Department Head!`, 'success');
                    closeAssignDeptHeadModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to assign department head', 'error');
                }
            } catch (error) {
                showToast(`Error: ${error.message}`, 'error');
            }
        }
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification fixed bottom-4 right-4 px-5 py-3 rounded-lg text-white text-sm font-medium ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} shadow-lg z-50 flex items-center gap-2`;
            toast.innerHTML = `<i class="${type === 'success' ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'}"></i><span>${message}</span>`;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>