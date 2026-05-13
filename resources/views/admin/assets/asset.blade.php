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
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .department-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Assets</h2>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-blue-600 font-medium">Asset Officer</span>
                                <span class="mx-2 text-gray-300">•</span>
                                <p class="text-sm text-gray-500">Manage and track all university assets across departments</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="/admin/inventory-download" class="inline-flex bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center transition-all hover:scale-105" download>
                                <i class="ri-download-line mr-2"></i>
                                Download Inventory
                            </a>
                            <a href="/admin/assets/registry" class="inline-flex bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center transition-all hover:scale-105">
                                <i class="ri-add-line mr-2"></i>
                                Add New Asset
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Status Legend -->
                <div class="px-8 py-3 bg-gray-50 border-t border-gray-100">
                    <p class="text-xs font-medium text-gray-600 mb-2">Asset Lifecycle Statuses:</p>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-blue-600 rounded-full"></div>
                            <span class="text-gray-600"><strong>Acquired</strong> - Newly registered</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-green-600 rounded-full"></div>
                            <span class="text-gray-600"><strong>Active</strong> - In use</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-purple-600 rounded-full"></div>
                            <span class="text-gray-600"><strong>Checking</strong> - Evaluation pending</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-amber-500 rounded-full"></div>
                            <span class="text-gray-600"><strong>Repair</strong> - Needs maintenance</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-pink-600 rounded-full"></div>
                            <span class="text-gray-600"><strong>Replace</strong> - Replacement in progress</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-slate-500 rounded-full"></div>
                            <span class="text-gray-600"><strong>Pullout</strong> - Transferred out</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <div class="w-3 h-3 bg-red-600 rounded-full"></div>
                            <span class="text-gray-600"><strong>Disposed</strong> - Removed from service</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Create Department Button -->
                <div class="mb-6 flex justify-end">
                    <button 
                        onclick="openCreateDepartmentModal()" 
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-all flex items-center"
                    >
                        <i class="ri-add-line mr-2"></i>
                        Create New Department
                    </button>
                </div>

                <!-- Department Cards -->
                <div class="space-y-4" id="departmentsContainer">
                    @if(count($departments) > 0)
                        @foreach($departments as $dept)
                        <div class="department-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" data-department="{{ strtolower($dept->name) }}">
                            <!-- Department Header -->
                            <div class="p-6">
                                <div class="flex justify-between items-center">
                                    <div class="flex-1 cursor-pointer" onclick="toggleDepartment({{ $dept->id }})">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900">{{ $dept->name }}</h3>
                                                <div class="flex items-center mt-1">
                                                    @if($dept->head_email)
                                                        <p class="text-sm text-gray-500">{{ $dept->head_email }}</p>
                                                    @else
                                                        <p class="text-sm text-gray-400 italic">No Department Head</p>
                                                    @endif
                                                    <button type="button" onclick="event.stopPropagation(); openAssignDeptHeadModal({{ $dept->id }}, '{{ $dept->name }}')" class="ml-2 text-blue-600 hover:text-blue-700 transition">
                                                        <i class="ri-edit-line text-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-2xl font-bold text-blue-600">{{ $dept->total_assets }}</p>
                                                <p class="text-xs text-gray-500">Total Assets</p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- View All Button -->
                                    <a href="/admin/assets/department/{{ $dept->id }}" class="ml-3 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all flex items-center whitespace-nowrap shadow-sm hover:shadow-md">
                                        <i class="ri-eye-line mr-1"></i>
                                        View All
                                    </a>
                                    <!-- Expand/Collapse Icon -->
                                    <div class="ml-3 cursor-pointer" onclick="toggleDepartment({{ $dept->id }})">
                                        <i class="ri-arrow-down-s-line text-2xl text-gray-400 rotate-transition" id="icon-{{ $dept->id }}"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Collapsible Content -->
                            <div id="content-{{ $dept->id }}" class="collapse-content border-t border-gray-100">
                                <div class="p-6 bg-gray-50">
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
                                        <p class="text-sm font-medium text-gray-700 mb-2">Asset Lifecycle Distribution</p>
                                        <div class="h-3 w-full rounded-full bg-gray-100 overflow-hidden flex">
                                            @if($dept->total_assets > 0)
                                                @if($acquired > 0)
                                                    <div style="width: {{ round(($acquired / $dept->total_assets) * 100, 1) }}%; background-color: #3b82f6;" title="Acquired: {{ $acquired }}"></div>
                                                @endif
                                                @if($active > 0)
                                                    <div style="width: {{ round(($active / $dept->total_assets) * 100, 1) }}%; background-color: #22c55e;" title="Active: {{ $active }}"></div>
                                                @endif
                                                @if($forChecking > 0)
                                                    <div style="width: {{ round(($forChecking / $dept->total_assets) * 100, 1) }}%; background-color: #8b5cf6;" title="For Checking: {{ $forChecking }}"></div>
                                                @endif
                                                @if($forRepair > 0)
                                                    <div style="width: {{ round(($forRepair / $dept->total_assets) * 100, 1) }}%; background-color: #f59e0b;" title="For Repair: {{ $forRepair }}"></div>
                                                @endif
                                                @if($forReplacement > 0)
                                                    <div style="width: {{ round(($forReplacement / $dept->total_assets) * 100, 1) }}%; background-color: #ec4899;" title="For Replacement: {{ $forReplacement }}"></div>
                                                @endif
                                                @if($pulledOut > 0)
                                                    <div style="width: {{ round(($pulledOut / $dept->total_assets) * 100, 1) }}%; background-color: #94a3b8;" title="Pulled Out: {{ $pulledOut }}"></div>
                                                @endif
                                                @if($disposed > 0)
                                                    <div style="width: {{ round(($disposed / $dept->total_assets) * 100, 1) }}%; background-color: #ef4444;" title="Disposed: {{ $disposed }}"></div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-7 gap-2 mb-6">
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Assets newly registered into system">
                                            <p class="text-xs text-gray-500">Acquired</p>
                                            <p class="text-lg font-bold text-blue-600">{{ $acquired }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($acquired / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Operational assets in service">
                                            <p class="text-xs text-gray-500">Active</p>
                                            <p class="text-lg font-bold text-green-600">{{ $active }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($active / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Assets pending evaluation after expiration">
                                            <p class="text-xs text-gray-500">Checking</p>
                                            <p class="text-lg font-bold text-purple-600">{{ $forChecking }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($forChecking / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Assets requiring maintenance or repair">
                                            <p class="text-xs text-gray-500">Repair</p>
                                            <p class="text-lg font-bold text-amber-500">{{ $forRepair }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($forRepair / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Assets being replaced due to condition">
                                            <p class="text-xs text-gray-500">Replace</p>
                                            <p class="text-lg font-bold text-pink-600">{{ $forReplacement }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($forReplacement / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Assets transferred out of inventory">
                                            <p class="text-xs text-gray-500">Pullout</p>
                                            <p class="text-lg font-bold text-slate-500">{{ $pulledOut }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($pulledOut / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg border border-gray-200 hover:shadow-md transition cursor-pointer" title="Assets removed from service">
                                            <p class="text-xs text-gray-500">Disposed</p>
                                            <p class="text-lg font-bold text-red-600">{{ $disposed }}</p>
                                            <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($disposed / $dept->total_assets) * 100) : 0 }}%)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center p-12 bg-white rounded-xl border border-gray-200">
                            <i class="ri-building-line text-4xl text-gray-300 mb-4 block"></i>
                            <p class="text-gray-500 text-lg">No departments created yet</p>
                            <p class="text-gray-400 text-sm mt-2">Click "Create New Department" to get started</p>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Create Department Modal -->
    <div id="createDepartmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeCreateDepartmentModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Create New Department</h3>
                <button onclick="closeCreateDepartmentModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="createDeptForm">
                    @csrf
                    <div class="mb-4">
                        <label for="modalDeptName" class="block text-sm font-medium text-gray-700 mb-2">Department Name</label>
                        <input 
                            type="text" 
                            id="modalDeptName" 
                            name="department_name"
                            placeholder="Enter department name"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                        />
                    </div>
                    
                    <div id="modalError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="modalSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeCreateDepartmentModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center"
                        >
                            <i class="ri-add-line mr-2"></i>
                            Create Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Department Head Modal -->
    <div id="assignDeptHeadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeAssignDeptHeadModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 id="assignDeptHeadTitle" class="text-xl font-bold text-gray-900">Assign Department Head</h3>
                <button onclick="closeAssignDeptHeadModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">Select a user from this department to assign as Department Head:</p>
                <div id="deptUsersList" class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                    <p class="text-gray-500 text-center py-4">Loading...</p>
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
            usersList.innerHTML = '<p class="text-gray-500 text-center py-4">Loading...</p>';
            
            try {
                const response = await fetch(`/admin/department/${deptId}/users`);
                const data = await response.json();
                
                if (data.success && data.users.length > 0) {
                    usersList.innerHTML = data.users.map(user => `
                        <button type="button" onclick="assignDepartmentHead(${deptId}, ${user.id}, '${user.full_name}')" class="w-full text-left p-3 hover:bg-blue-50 border-b border-gray-200 transition flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">${user.full_name}</p>
                                <p class="text-xs text-gray-500">${user.email}</p>
                                <p class="text-xs text-gray-400">Employee #${user.unit_heads_number}</p>
                            </div>
                            <i class="ri-arrow-right-line text-gray-400"></i>
                        </button>
                    `).join('');
                } else {
                    usersList.innerHTML = '<p class="text-gray-500 text-center py-4">No users in this department</p>';
                }
            } catch (error) {
                usersList.innerHTML = `<p class="text-red-500 text-center py-4">Error loading users: ${error.message}</p>`;
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
            toast.className = `toast-notification fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} shadow-lg z-50`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>