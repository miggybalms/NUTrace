<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Assets Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

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

        .department-card {
            transition: all 0.2s ease;
        }

        .department-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 14px -4px rgba(15, 23, 42, 0.10);
        }

        .asset-row {
            transition: background-color 0.2s ease;
        }

        .asset-row:hover {
            background-color: #f9fafb;
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
<body class="bg-slate-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-10">
            <div class="px-4 sm:px-8 py-5">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div class="flex items-center">
                        <!-- Hamburger, mobile only -->
                        <button onclick="toggleSidebar()" class="lg:hidden mr-3 text-slate-500 hover:text-slate-900">
                            <i class="ri-menu-line text-2xl"></i>
                        </button>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Assets</h2>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md">
                                    <i class="ri-computer-line"></i> Asset Officer
                                </span>
                                <p class="text-sm text-slate-500 hidden sm:block ml-3">Manage and track all university assets across departments</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/admin/inventory-download" class="flex-1 sm:flex-none justify-center inline-flex bg-white border border-slate-300 text-slate-700 px-4 py-2.5 rounded-lg hover:bg-slate-50 items-center transition-colors font-medium text-sm" download>
                            <i class="ri-download-line mr-1.5"></i>
                            <span class="whitespace-nowrap">Download Inventory</span>
                        </a>
                        <a href="/admin/assets/registry" class="flex-1 sm:flex-none justify-center inline-flex bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 items-center transition-colors font-medium text-sm shadow-sm shadow-blue-600/20">
                            <i class="ri-add-line mr-1.5"></i>
                            <span class="whitespace-nowrap">Add New Asset</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Status Legend -->
            <div class="px-4 sm:px-8 py-3.5 bg-slate-50 border-t border-slate-100">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2.5">Asset Lifecycle Statuses</p>
                <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-x-5 gap-y-2">
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-blue-600 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Acquired</strong> — Newly registered</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-green-600 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Active</strong> — In use</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-purple-600 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Checking</strong> — Evaluation pending</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-amber-500 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Repair</strong> — Needs maintenance</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-pink-600 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Replace</strong> — Replacement in progress</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-slate-500 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Pullout</strong> — Transferred out</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="w-2.5 h-2.5 bg-red-600 rounded-full flex-shrink-0"></div>
                        <span class="text-slate-600"><strong class="text-slate-800">Disposed</strong> — Removed from service</span>
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
                        class="w-full sm:w-auto bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center font-medium text-sm shadow-sm shadow-blue-600/20"
                    >
                        <i class="ri-add-line mr-1.5"></i>
                        Create New Department
                    </button>
                </div>

                <!-- Department Cards -->
                <div class="space-y-4" id="departmentsContainer">
                    @if(count($departments) > 0)
                        @foreach($departments as $dept)
                    <div class="department-card bg-white rounded-xl border border-slate-200 overflow-hidden" data-department="{{ strtolower($dept->name) }}">
                        <!-- Department Header -->
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-3">
                                <div class="flex-1 cursor-pointer" onclick="toggleDepartment({{ $dept->id }})">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div>
                                            <h3 class="text-lg sm:text-xl font-bold text-slate-900">{{ $dept->name }}</h3>
                                            <div class="flex items-center mt-1">
                                                @if($dept->head_email)
                                                    <p class="text-sm text-slate-500 truncate">{{ $dept->head_email }}</p>
                                                @else
                                                    <p class="text-sm text-slate-400 italic">No Department Head</p>
                                                @endif
                                                <button type="button" onclick="event.stopPropagation(); openAssignDeptHeadModal({{ $dept->id }}, '{{ $dept->name }}')" class="ml-2 text-slate-400 hover:text-blue-600 transition-colors flex-shrink-0">
                                                    <i class="ri-edit-line text-base"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-left sm:text-right">
                                            <p class="text-2xl font-bold text-slate-900">{{ $dept->total_assets }}</p>
                                            <p class="text-xs text-slate-400 font-medium">Total Assets</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- View All + Expand row -->
                                <div class="flex items-center gap-2 justify-end lg:justify-start lg:ml-3">
                                    <a href="/admin/assets/department/{{ $dept->id }}" class="flex-1 lg:flex-none justify-center px-3.5 py-2 bg-blue-50 text-blue-700 text-sm font-medium rounded-lg hover:bg-blue-600 hover:text-white transition-colors flex items-center whitespace-nowrap">
                                        <i class="ri-eye-line mr-1.5"></i>
                                        View All
                                    </a>
                                    <div class="cursor-pointer flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 transition-colors" onclick="toggleDepartment({{ $dept->id }})">
                                        <i class="ri-arrow-down-s-line text-xl text-slate-400 rotate-transition" id="icon-{{ $dept->id }}"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Collapsible Content -->
                            <div id="content-{{ $dept->id }}" class="collapse-content border-t border-slate-100 -mx-4 sm:-mx-6 mt-0">
                                <div class="p-4 sm:p-6 bg-slate-50/70">
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
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Asset Lifecycle Distribution</p>
                                        <div class="h-2.5 w-full rounded-full bg-slate-200 overflow-hidden flex">
                                            <!-- unchanged bar segments -->
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Assets newly registered into system">
                                            <p class="text-xs text-slate-500 mb-1">Acquired</p>
                                            <p class="text-lg font-bold text-blue-600">{{ $acquired }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($acquired / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Operational assets in service">
                                            <p class="text-xs text-slate-500 mb-1">Active</p>
                                            <p class="text-lg font-bold text-green-600">{{ $active }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($active / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Assets pending evaluation after expiration">
                                            <p class="text-xs text-slate-500 mb-1">Checking</p>
                                            <p class="text-lg font-bold text-purple-600">{{ $forChecking }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($forChecking / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Assets requiring maintenance or repair">
                                            <p class="text-xs text-slate-500 mb-1">Repair</p>
                                            <p class="text-lg font-bold text-amber-500">{{ $forRepair }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($forRepair / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Assets being replaced due to condition">
                                            <p class="text-xs text-slate-500 mb-1">Replace</p>
                                            <p class="text-lg font-bold text-pink-600">{{ $forReplacement }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($forReplacement / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Assets transferred out of inventory">
                                            <p class="text-xs text-slate-500 mb-1">Pullout</p>
                                            <p class="text-lg font-bold text-slate-500">{{ $pulledOut }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($pulledOut / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all cursor-pointer" title="Assets removed from service">
                                            <p class="text-xs text-slate-500 mb-1">Disposed</p>
                                            <p class="text-lg font-bold text-red-600">{{ $disposed }}</p>
                                            <p class="text-[11px] text-slate-400">({{ $dept->total_assets > 0 ? round(($disposed / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center p-12 bg-white rounded-xl border border-slate-200">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="ri-building-line text-2xl text-slate-400"></i>
                            </div>
                            <p class="text-slate-700 font-medium">No departments created yet</p>
                            <p class="text-slate-400 text-sm mt-1">Click "Create New Department" to get started</p>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="text-center text-xs text-slate-400 mt-10 pt-6 border-t border-slate-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Create Department Modal -->
    <div id="createDepartmentModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeCreateDepartmentModal(event)">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">Create New Department</h3>
                <button onclick="closeCreateDepartmentModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="createDeptForm">
                    @csrf
                    <div class="mb-4">
                        <label for="modalDeptName" class="block text-sm font-medium text-slate-700 mb-1.5">Department Name</label>
                        <input 
                            type="text" 
                            id="modalDeptName" 
                            name="department_name"
                            placeholder="Enter department name"
                            required
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-shadow"
                        />
                    </div>
                    
                    <div id="modalError" class="flex items-start gap-2 text-red-700 text-sm mb-4 bg-red-50 border border-red-100 rounded-lg px-3 py-2.5" style="display: none;"></div>
                    <div id="modalSuccess" class="flex items-start gap-2 text-green-700 text-sm mb-4 bg-green-50 border border-green-100 rounded-lg px-3 py-2.5" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end pt-2">
                        <button 
                            type="button" 
                            onclick="closeCreateDepartmentModal()"
                            class="px-4 py-2.5 text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors text-sm font-medium"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center text-sm font-medium shadow-sm shadow-blue-600/20"
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
    <div id="assignDeptHeadModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4" onclick="closeAssignDeptHeadModal(event)">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <h3 id="assignDeptHeadTitle" class="text-lg font-bold text-slate-900">Assign Department Head</h3>
                <button onclick="closeAssignDeptHeadModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p class="text-sm text-slate-500 mb-4">Select a user from this department to assign as Department Head:</p>
                <div id="deptUsersList" class="max-h-96 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
                    <p class="text-slate-500 text-center py-6 text-sm">Loading...</p>
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