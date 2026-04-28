<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Repair Management - Admin Dashboard</title>
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
        
        .repair-card {
            transition: all 0.3s ease;
        }
        
        .repair-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            transition: all 0.2s ease;
        }
        
        .filter-btn {
            transition: all 0.2s ease;
        }
        
        .filter-btn.active {
            background-color: #3b82f6;
            color: white;
        }
        
        .modal {
            transition: all 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
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
                            <h2 class="text-2xl font-bold text-gray-900">Repair Management</h2>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-blue-600 font-medium">Asset Officer</span>
                                <span class="mx-2 text-gray-300">•</span>
                                <p class="text-sm text-gray-500">Manage and track all repair requests</p>
                            </div>
                        </div>
                        <button onclick="openNewRepairModal()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-all hover:scale-105 flex items-center shadow-md">
                            <i class="ri-add-line mr-2"></i>
                            New Repair Request
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Total Repairs</p>
                                <p class="text-3xl font-bold mt-2" id="totalRepairs">0</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-tools-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Pending</p>
                                <p class="text-3xl font-bold mt-2" id="pendingRepairs">0</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-time-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">In Progress</p>
                                <p class="text-3xl font-bold mt-2" id="inProgressRepairs">0</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-refresh-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Completed</p>
                                <p class="text-3xl font-bold mt-2" id="completedRepairs">0</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6">
                    <div class="flex space-x-2 border-b border-gray-200">
                        <button class="filter-btn active px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-filter="all">
                            All Requests
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="pending">
                            Pending
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="in_progress">
                            In Progress
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="completed">
                            Completed
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="cancelled">
                            Cancelled
                        </button>
                    </div>
                </div>

                <!-- Repair Requests List -->
                <div id="repairsList" class="space-y-4">
                    <!-- Repair cards will be dynamically inserted here -->
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-tools-line text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No repair requests</h3>
                    <p class="text-gray-500">There are currently no repair requests to show.</p>
                    <button onclick="openNewRepairModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        <i class="ri-add-line mr-2"></i>
                        Create New Repair Request
                    </button>
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- New Repair Request Modal -->
    <div id="newRepairModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">New Repair Request</h3>
                    <button onclick="closeNewRepairModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="repairForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Asset *</label>
                        <select name="asset_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                            <option value="">Select asset...</option>
                            <option value="1">Dell XPS 15 Laptop (AST-001)</option>
                            <option value="2">HP Monitor 24" (AST-002)</option>
                            <option value="3">Logitech Keyboard (AST-003)</option>
                            <option value="4">Wireless Mouse (AST-004)</option>
                            <option value="5">HP LaserJet Printer (AST-005)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Issue Description *</label>
                        <textarea name="issue_description" rows="4" required placeholder="Describe the issue in detail..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority *</label>
                        <select name="priority" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                            <option value="low">Low - Can wait</option>
                            <option value="medium">Medium - Needs attention soon</option>
                            <option value="high">High - Urgent</option>
                            <option value="critical">Critical - Emergency</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Requested By</label>
                        <input type="text" name="requested_by" placeholder="Name of person requesting repair" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Attach Photo (Optional)</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-red-500 transition" onclick="document.getElementById('repair-photo').click()">
                            <i class="ri-image-line text-2xl text-gray-400 mb-1 block"></i>
                            <p class="text-sm text-gray-600">Click to upload photo</p>
                            <p class="text-xs text-gray-400">PNG, JPG up to 10MB</p>
                            <input type="file" id="repair-photo" name="attachment" class="hidden" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeNewRepairModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Create Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View/Edit Repair Modal -->
    <div id="viewRepairModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Repair Request Details</h3>
                    <button onclick="closeViewRepairModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6" id="viewRepairContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Use server-provided repairs collection (passed from controller)
        let repairs = @json($repairs ?? []);

        let currentFilter = "all";

        function renderRepairs() {
            const repairsList = document.getElementById('repairsList');
            const emptyState = document.getElementById('emptyState');
            
            let filteredRepairs = repairs;
            if (currentFilter !== "all") {
                filteredRepairs = repairs.filter(r => r.status === currentFilter);
            }
            
            if (filteredRepairs.length === 0) {
                repairsList.classList.add('hidden');
                emptyState.classList.remove('hidden');
            } else {
                repairsList.classList.remove('hidden');
                emptyState.classList.add('hidden');
                
                repairsList.innerHTML = filteredRepairs.map(repair => `
                    <div class="repair-card bg-white rounded-xl shadow-sm border border-gray-200 p-6" data-id="${repair.id}">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                                        <i class="ri-tools-line text-red-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 text-lg">${repair.asset_name}</h3>
                                        <p class="text-xs text-gray-500 font-mono">${repair.asset_code}</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-500">Issue</p>
                                        <p class="text-sm font-medium text-gray-900">${repair.issue.substring(0, 50)}${repair.issue.length > 50 ? '...' : ''}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Requested By</p>
                                        <p class="text-sm font-medium text-gray-900">${repair.requested_by}</p>
                                        <p class="text-xs text-gray-400">${repair.department}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Date Requested</p>
                                        <p class="text-sm font-medium text-gray-900">${new Date(repair.date_requested).toLocaleDateString()}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Priority</p>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            ${repair.priority === 'critical' ? 'bg-red-100 text-red-700' : ''}
                                            ${repair.priority === 'high' ? 'bg-orange-100 text-orange-700' : ''}
                                            ${repair.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : ''}
                                            ${repair.priority === 'low' ? 'bg-green-100 text-green-700' : ''}">
                                            ${repair.priority.charAt(0).toUpperCase() + repair.priority.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-3">
                                    <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        ${repair.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ''}
                                        ${repair.status === 'in_progress' ? 'bg-blue-100 text-blue-700' : ''}
                                        ${repair.status === 'completed' ? 'bg-green-100 text-green-700' : ''}
                                        ${repair.status === 'cancelled' ? 'bg-gray-100 text-gray-700' : ''}">
                                        <i class="ri-circle-fill mr-1 text-xs"></i>
                                        ${repair.status === 'in_progress' ? 'In Progress' : repair.status.charAt(0).toUpperCase() + repair.status.slice(1)}
                                    </span>
                                    ${repair.status === 'completed' && repair.completion_date ? `
                                        <span class="text-xs text-gray-400">Completed: ${new Date(repair.completion_date).toLocaleDateString()}</span>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="flex space-x-2 ml-4">
                                <button onclick="viewRepairDetails(${repair.id})" class="px-3 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                    <i class="ri-eye-line text-lg"></i>
                                </button>
                                ${repair.status !== 'completed' && repair.status !== 'cancelled' ? `
                                    <button onclick="updateRepairStatus(${repair.id})" class="px-3 py-2 text-green-600 hover:bg-green-50 rounded-lg transition">
                                        <i class="ri-edit-line text-lg"></i>
                                    </button>
                                ` : ''}
                                <button onclick="deleteRepair(${repair.id})" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                                    <i class="ri-delete-bin-line text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            // Update statistics
            document.getElementById('totalRepairs').textContent = repairs.length;
            document.getElementById('pendingRepairs').textContent = repairs.filter(r => r.status === 'pending').length;
            document.getElementById('inProgressRepairs').textContent = repairs.filter(r => r.status === 'in_progress').length;
            document.getElementById('completedRepairs').textContent = repairs.filter(r => r.status === 'completed').length;
        }
        
        function viewRepairDetails(id) {
            const repair = repairs.find(r => r.id === id);
            if (repair) {
                const content = `
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">${repair.asset_name}</h4>
                                <p class="text-sm text-gray-500 font-mono">${repair.asset_code}</p>
                            </div>
                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                ${repair.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ''}
                                ${repair.status === 'in_progress' ? 'bg-blue-100 text-blue-700' : ''}
                                ${repair.status === 'completed' ? 'bg-green-100 text-green-700' : ''}">
                                ${repair.status === 'in_progress' ? 'In Progress' : repair.status.charAt(0).toUpperCase() + repair.status.slice(1)}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Issue Description</p>
                                <p class="text-sm text-gray-900 mt-1">${repair.issue}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Priority</p>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium mt-1
                                    ${repair.priority === 'critical' ? 'bg-red-100 text-red-700' : ''}
                                    ${repair.priority === 'high' ? 'bg-orange-100 text-orange-700' : ''}
                                    ${repair.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : ''}
                                    ${repair.priority === 'low' ? 'bg-green-100 text-green-700' : ''}">
                                    ${repair.priority.charAt(0).toUpperCase() + repair.priority.slice(1)}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Requested By</p>
                                <p class="text-sm font-medium text-gray-900">${repair.requested_by}</p>
                                <p class="text-xs text-gray-400">${repair.department}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Date Requested</p>
                                <p class="text-sm font-medium text-gray-900">${new Date(repair.date_requested).toLocaleDateString()}</p>
                            </div>
                            ${repair.technician ? `
                            <div>
                                <p class="text-xs text-gray-500">Assigned Technician</p>
                                <p class="text-sm font-medium text-gray-900">${repair.technician}</p>
                            </div>
                            ` : ''}
                            ${repair.estimated_cost ? `
                            <div>
                                <p class="text-xs text-gray-500">Estimated Cost</p>
                                <p class="text-sm font-medium text-gray-900">$${repair.estimated_cost.toFixed(2)}</p>
                            </div>
                            ` : ''}
                            ${repair.completion_date ? `
                            <div>
                                <p class="text-xs text-gray-500">Completion Date</p>
                                <p class="text-sm font-medium text-gray-900">${new Date(repair.completion_date).toLocaleDateString()}</p>
                            </div>
                            ` : ''}
                        </div>
                        
                        ${repair.notes ? `
                        <div class="pt-4 border-t border-gray-200">
                            <p class="text-xs text-gray-500">Notes</p>
                            <p class="text-sm text-gray-700 mt-1">${repair.notes}</p>
                        </div>
                        ` : ''}
                        
                        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                            <button onclick="closeViewRepairModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                            ${repair.status !== 'completed' ? `
                                <button onclick="updateRepairStatus(${repair.id})" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update Status</button>
                            ` : ''}
                        </div>
                    </div>
                `;
                document.getElementById('viewRepairContent').innerHTML = content;
                document.getElementById('viewRepairModal').classList.remove('hidden');
                document.getElementById('viewRepairModal').classList.add('flex');
            }
        }
        
        function updateRepairStatus(id) {
            const newStatus = prompt('Update status (pending, in_progress, completed, cancelled):');
            if (newStatus && ['pending', 'in_progress', 'completed', 'cancelled'].includes(newStatus)) {
                const repair = repairs.find(r => r.id === id);
                if (repair) {
                    repair.status = newStatus;
                    if (newStatus === 'completed') {
                        repair.completion_date = new Date().toISOString().split('T')[0];
                    }
                    renderRepairs();
                    closeViewRepairModal();
                    alert(`Repair status updated to ${newStatus}`);
                }
            }
        }
        
        function deleteRepair(id) {
            if (confirm('Are you sure you want to delete this repair request?')) {
                repairs = repairs.filter(r => r.id !== id);
                renderRepairs();
                alert('Repair request deleted successfully');
            }
        }
        
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                    b.classList.add('text-gray-500');
                });
                this.classList.add('active');
                this.classList.remove('text-gray-500');
                this.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
                
                currentFilter = this.getAttribute('data-filter');
                renderRepairs();
            });
        });
        
        // Search functionality
        document.getElementById('searchRepairs')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const filtered = repairs.filter(r => 
                r.asset_name.toLowerCase().includes(searchTerm) || 
                r.asset_code.toLowerCase().includes(searchTerm) ||
                r.requested_by.toLowerCase().includes(searchTerm) ||
                r.issue.toLowerCase().includes(searchTerm)
            );
            
            const repairsList = document.getElementById('repairsList');
            if (filtered.length === 0) {
                repairsList.innerHTML = '<div class="text-center py-12 bg-white rounded-xl"><p class="text-gray-500">No matching repair requests found</p></div>';
            } else {
                repairsList.innerHTML = filtered.map(repair => `
                    <div class="repair-card bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <!-- Same card structure as above -->
                    </div>
                `).join('');
            }
        });
        
        // Modal functions
        function openNewRepairModal() {
            document.getElementById('newRepairModal').classList.remove('hidden');
            document.getElementById('newRepairModal').classList.add('flex');
        }
        
        function closeNewRepairModal() {
            document.getElementById('newRepairModal').classList.add('hidden');
            document.getElementById('newRepairModal').classList.remove('flex');
            document.getElementById('repairForm').reset();
        }
        
        function closeViewRepairModal() {
            document.getElementById('viewRepairModal').classList.add('hidden');
            document.getElementById('viewRepairModal').classList.remove('flex');
        }
        
        // Form submission
        document.getElementById('repairForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const newRepair = {
                id: repairs.length + 1,
                asset_name: "New Asset",
                asset_code: "NEW-001",
                issue: formData.get('issue_description'),
                status: "pending",
                priority: formData.get('priority'),
                requested_by: formData.get('requested_by') || "System",
                department: "Various",
                date_requested: new Date().toISOString().split('T')[0],
                estimated_cost: null,
                technician: null,
                completion_date: null,
                notes: null
            };
            repairs.unshift(newRepair);
            renderRepairs();
            closeNewRepairModal();
            alert('Repair request created successfully!');
        });
        
        // Initial render
        renderRepairs();
    </script>
</body>
</html>