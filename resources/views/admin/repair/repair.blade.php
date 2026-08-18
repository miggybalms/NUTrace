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

        .repair-card {
            transition: all 0.2s ease;
        }

        .repair-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 14px -4px rgba(15, 23, 42, 0.12);
        }

        .status-badge {
            transition: all 0.2s ease;
        }

        .filter-btn {
            transition: all 0.15s ease;
        }

        .filter-btn.active {
            color: #b91c1c;
        }

        .modal {
            transition: all 0.2s ease;
        }

        .modal.show {
            display: flex;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-panel {
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        .detail-field dt {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 0.25rem;
        }

        .detail-field dd {
            font-size: 0.9375rem;
            color: #0f172a;
            font-weight: 500;
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
                                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Repair Management</h2>
                                <div class="flex items-center mt-1">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-700 bg-red-50 px-2 py-0.5 rounded-md">
                                        <i class="ri-tools-line"></i> Asset Officer
                                    </span>
                                    <p class="text-sm text-slate-500 hidden sm:block ml-3">Manage and track all repair requests</p>
                                </div>
                            </div>
                        </div>
                        <button onclick="openNewRepairModal()" class="w-full sm:w-auto justify-center bg-red-600 text-white px-4 py-2.5 rounded-lg hover:bg-red-700 transition-colors flex items-center font-medium text-sm shadow-sm shadow-red-600/20">
                            <i class="ri-add-line mr-1.5 text-base"></i>
                            New Repair Request
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-5 mb-6 sm:mb-8">
                    <div class="bg-white rounded-xl border border-slate-200 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Repairs</p>
                                <p class="text-3xl font-bold mt-2 text-slate-900" id="totalRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 bg-slate-100 rounded-lg flex items-center justify-center">
                                <i class="ri-tools-line text-xl text-slate-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Pending</p>
                                <p class="text-3xl font-bold mt-2 text-slate-900" id="pendingRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 bg-yellow-50 rounded-lg flex items-center justify-center">
                                <i class="ri-time-line text-xl text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">In Progress</p>
                                <p class="text-3xl font-bold mt-2 text-slate-900" id="inProgressRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 bg-blue-50 rounded-lg flex items-center justify-center">
                                <i class="ri-refresh-line text-xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Completed</p>
                                <p class="text-3xl font-bold mt-2 text-slate-900" id="completedRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 bg-green-50 rounded-lg flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-xl text-green-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6 overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-1 border-b border-slate-200 min-w-max">
                        <button class="filter-btn active px-4 py-2.5 text-sm font-medium text-red-700 border-b-2 border-red-600 whitespace-nowrap" data-filter="all">
                            All Requests
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="pending">
                            Pending
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="in_progress">
                            In Progress
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="completed">
                            Completed
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="cancelled">
                            Cancelled
                        </button>
                    </div>
                </div>

                <!-- Repair Requests List -->
                <div id="repairsList" class="space-y-3">
                    <!-- Repair cards will be dynamically inserted here -->
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="hidden bg-white rounded-xl border border-slate-200 p-12 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-tools-line text-3xl text-slate-400"></i>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 mb-1.5">No repair requests</h3>
                    <p class="text-sm text-slate-500">There are currently no repair requests to show.</p>
                    <button onclick="openNewRepairModal()" class="mt-5 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                        <i class="ri-add-line mr-1.5"></i>
                        Create New Repair Request
                    </button>
                </div>

                <!-- Footer -->
                <div class="text-center text-xs text-slate-400 mt-10 pt-6 border-t border-slate-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- New Repair Request Modal -->
    <div id="newRepairModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center modal p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">New Repair Request</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Log an issue for an asset that needs attention</p>
                </div>
                <button onclick="closeNewRepairModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="repairForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Select Asset <span class="text-red-500">*</span></label>
                        <select name="asset_id" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 transition-shadow">
                        <option value="">Select asset...</option>
                        @foreach($availableAssets ?? [] as $asset)
                        <option value="{{ $asset->id }}">
                        {{ $asset->Asset_name }} ({{ $asset->Asset_code }})
                        </option>
                        @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Issue Description <span class="text-red-500">*</span></label>
                        <textarea name="issue_description" rows="4" required placeholder="Describe the issue in detail..." class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 transition-shadow resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Priority <span class="text-red-500">*</span></label>
                            <select name="priority" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 transition-shadow">
                                <option value="low">Low - Can wait</option>
                                <option value="medium">Medium - Needs attention soon</option>
                                <option value="high">High - Urgent</option>
                                <option value="critical">Critical - Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Requested By</label>
                            <input type="text" name="requested_by" placeholder="Name of person requesting repair" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 transition-shadow">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Attach Photo (Optional)</label>
                        <div class="border-2 border-dashed border-slate-300 rounded-lg p-5 text-center cursor-pointer hover:border-red-400 hover:bg-red-50/40 transition-colors" onclick="document.getElementById('repair-photo').click()">
                            <i class="ri-image-add-line text-2xl text-slate-400 mb-1 block"></i>
                            <p class="text-sm text-slate-600 font-medium">Click to upload photo</p>
                            <p class="text-xs text-slate-400 mt-0.5">PNG, JPG up to 10MB</p>
                            <input type="file" id="repair-photo" name="attachment" class="hidden" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-5 border-t border-slate-100">
                    <button type="button" onclick="closeNewRepairModal()" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors shadow-sm shadow-red-600/20">Create Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View/Edit Repair Modal -->
    <div id="viewRepairModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center modal p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <h3 class="text-lg font-bold text-slate-900">Repair Request Details</h3>
                <button onclick="closeViewRepairModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
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

        function priorityBadgeClasses(priority) {
            switch (priority) {
                case 'critical': return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200';
                case 'high': return 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200';
                case 'medium': return 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-200';
                case 'low': return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-200';
                default: return 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200';
            }
        }

        function statusBadgeClasses(status) {
            switch (status) {
                case 'pending': return 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-200';
                case 'in_progress': return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200';
                case 'completed': return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-200';
                case 'cancelled': return 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200';
                default: return 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200';
            }
        }

        function statusDotColor(status) {
            switch (status) {
                case 'pending': return 'text-yellow-500';
                case 'in_progress': return 'text-blue-500';
                case 'completed': return 'text-green-500';
                case 'cancelled': return 'text-slate-400';
                default: return 'text-slate-400';
            }
        }

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
                    <div class="repair-card bg-white rounded-xl border border-slate-200 p-4 sm:p-5" data-id="${repair.id}">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center mb-4">
                                    <div class="w-11 h-11 bg-red-50 rounded-lg flex items-center justify-center mr-3.5 shrink-0">
                                        <i class="ri-tools-line text-red-600 text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-slate-900 text-base truncate">${repair.asset_name}</h3>
                                        <p class="text-xs text-slate-400 font-mono">${repair.asset_code}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-slate-400 mb-0.5">Issue</p>
                                        <p class="text-sm font-medium text-slate-800">${repair.issue.substring(0, 50)}${repair.issue.length > 50 ? '...' : ''}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 mb-0.5">Requested By</p>
                                        <p class="text-sm font-medium text-slate-800">${repair.requested_by}</p>
                                        <p class="text-xs text-slate-400">${repair.department}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 mb-0.5">Date Requested</p>
                                        <p class="text-sm font-medium text-slate-800">${new Date(repair.date_requested).toLocaleDateString()}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 mb-0.5">Priority</p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${priorityBadgeClasses(repair.priority)}">
                                            ${repair.priority.charAt(0).toUpperCase() + repair.priority.slice(1)}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <span class="status-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${statusBadgeClasses(repair.status)}"
                                        id="status-badge-${repair.id}">
                                        <i class="ri-circle-fill mr-1.5 text-[8px] ${statusDotColor(repair.status)}"></i>
                                        ${displayStatusLabel(repair.status)}
                                    </span>
                                    ${repair.status === 'completed' && repair.completion_date ? `
                                        <span class="text-xs text-slate-400">Completed ${new Date(repair.completion_date).toLocaleDateString()}</span>
                                    ` : ''}
                                </div>
                            </div>

                            <div class="flex sm:flex-col gap-1 shrink-0">
                                <button onclick="viewRepairDetails(${repair.id})" title="View" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                    <i class="ri-eye-line text-lg"></i>
                                </button>
                                ${repair.status !== 'completed' && repair.status !== 'cancelled' ? `
                                    <button onclick="viewRepairDetails(${repair.id})" title="Edit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                        <i class="ri-edit-line text-lg"></i>
                                    </button>
                                ` : ''}
                                <button onclick="deleteRepair(${repair.id})" title="Delete" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
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
                    <div class="space-y-6">
                        <div class="flex items-start justify-between pb-5 border-b border-slate-100">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center mr-4 shrink-0">
                                    <i class="ri-tools-line text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-slate-900">${repair.asset_name}</h4>
                                    <p class="text-xs text-slate-400 font-mono mt-0.5">${repair.asset_code}</p>
                                </div>
                            </div>
                            <span class="status-badge inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold shrink-0 ${statusBadgeClasses(repair.status)}">
                                <i class="ri-circle-fill mr-1.5 text-[8px] ${statusDotColor(repair.status)}"></i>
                                ${displayStatusLabel(repair.status)}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                            <div class="detail-field col-span-2">
                                <dt>Issue Description</dt>
                                <dd class="font-normal text-slate-700 leading-relaxed">${repair.issue}</dd>
                            </div>
                            <div class="detail-field">
                                <dt>Priority</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityBadgeClasses(repair.priority)}">
                                        ${repair.priority.charAt(0).toUpperCase() + repair.priority.slice(1)}
                                    </span>
                                </dd>
                            </div>
                            <div class="detail-field">
                                <dt>Date Requested</dt>
                                <dd>${new Date(repair.date_requested).toLocaleDateString()}</dd>
                            </div>
                            <div class="detail-field">
                                <dt>Requested By</dt>
                                <dd>${repair.requested_by}</dd>
                                <p class="text-xs text-slate-400 mt-0.5">${repair.department}</p>
                            </div>
                            ${repair.technician ? `
                            <div class="detail-field">
                                <dt>Assigned Technician</dt>
                                <dd>${repair.technician}</dd>
                            </div>
                            ` : ''}
                            ${repair.estimated_cost ? `
                            <div class="detail-field">
                                <dt>Estimated Cost</dt>
                                <dd>₱${repair.estimated_cost.toFixed(2)}</dd>
                            </div>
                            ` : ''}
                            ${repair.completion_date ? `
                            <div class="detail-field">
                                <dt>Completion Date</dt>
                                <dd>${new Date(repair.completion_date).toLocaleDateString()}</dd>
                            </div>
                            ` : ''}
                        </div>

                        ${repair.notes ? `
                        <div class="pt-5 border-t border-slate-100 detail-field">
                            <dt>Notes</dt>
                            <dd class="font-normal text-slate-700">${repair.notes}</dd>
                        </div>
                        ` : ''}

                        <div class="pt-5 border-t border-slate-100">
                            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Asset Details</h4>
                            <div class="bg-slate-50 rounded-xl p-4 grid grid-cols-2 gap-x-6 gap-y-4">
                                <div class="detail-field"><dt>Serial</dt><dd class="font-normal">${repair.serial_number ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Condition</dt><dd class="font-normal">${repair.condition ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Purchase Price</dt><dd class="font-normal">${repair.purchase_price ? ('₱' + repair.purchase_price.toFixed(2)) : '-'}</dd></div>
                                <div class="detail-field"><dt>Warranty (months)</dt><dd class="font-normal">${repair.warranty_months ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Location</dt><dd class="font-normal">${repair.asset_location ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Supplier / Model</dt><dd class="font-normal">${repair.supplier ?? '-'}${repair.model ? (' / ' + repair.model) : ''}</dd></div>
                            </div>
                        </div>

                        <div class="pt-5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <button onclick="closeViewRepairModal()" class="order-2 sm:order-1 px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">Close</button>
                            ${repair.status !== 'completed' ? `
                                <div class="order-1 sm:order-2 flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-slate-400 mr-1 hidden sm:inline">Set status:</span>
                                    <button onclick="changeRepairStatus(${repair.id}, 'pending')" class="px-3 py-1.5 bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-200 rounded-lg text-xs font-medium hover:bg-yellow-100 transition-colors">Pending</button>
                                    <button onclick="changeRepairStatus(${repair.id}, 'in_progress')" class="px-3 py-1.5 bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">In Progress</button>
                                    <button onclick="changeRepairStatus(${repair.id}, 'completed')" class="px-3 py-1.5 bg-green-50 text-green-700 ring-1 ring-inset ring-green-200 rounded-lg text-xs font-medium hover:bg-green-100 transition-colors">Completed</button>
                                    <button onclick="changeRepairStatus(${repair.id}, 'cancelled')" class="px-3 py-1.5 bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200 rounded-lg text-xs font-medium hover:bg-slate-200 transition-colors">Cancelled</button>
                                </div>
                            ` : ''}
                        </div>

                        <div class="pt-5 border-t border-slate-100 flex flex-col sm:flex-row gap-3">
                            <button onclick="sendAssetToReplacement(${repair.id}, ${repair.asset_id}, ${repair.request_id})" class="flex-1 px-4 py-2.5 bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-200 rounded-lg text-sm font-medium hover:bg-purple-100 transition-colors flex items-center justify-center">
                                <i class="ri-refresh-line mr-2"></i>
                                Send to Replacement
                            </button>
                            <button onclick="sendAssetToDisposal(${repair.id}, ${repair.asset_id}, ${repair.request_id})" class="flex-1 px-4 py-2.5 bg-red-50 text-red-700 ring-1 ring-inset ring-red-200 rounded-lg text-sm font-medium hover:bg-red-100 transition-colors flex items-center justify-center">
                                <i class="ri-delete-bin-line mr-2"></i>
                                Send to Disposal
                            </button>
                        </div>
                    </div>
                `;
                document.getElementById('viewRepairContent').innerHTML = content;
                document.getElementById('viewRepairModal').classList.remove('hidden');
                document.getElementById('viewRepairModal').classList.add('flex');
            }
        }

        function changeRepairStatus(id, newStatus) {
            const valid = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!valid.includes(newStatus)) return;
            const repair = repairs.find(r => r.id === id);
            if (!repair) return;

            // Send update to server
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch(`/admin/repairs/${id}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ status: newStatus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    repair.status = newStatus;
                    if (newStatus === 'completed') {
                        repair.completion_date = new Date().toISOString().split('T')[0];
                    } else {
                        repair.completion_date = null;
                    }
                    renderRepairs();
                    viewRepairDetails(id);
                    alert('Repair status updated to ' + displayStatusLabel(newStatus));
                } else {
                    alert('Error: ' + (data.message || 'Failed to update repair status'));
                }
            })
            .catch(err => {
                alert('Error updating repair status: ' + err.message);
            });
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
                    b.classList.remove('active', 'text-red-700', 'border-b-2', 'border-red-600');
                    b.classList.add('text-slate-500');
                });
                this.classList.add('active');
                this.classList.remove('text-slate-500');
                this.classList.add('text-red-700', 'border-b-2', 'border-red-600');

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
                repairsList.innerHTML = '<div class="text-center py-12 bg-white rounded-xl border border-slate-200"><p class="text-slate-500 text-sm">No matching repair requests found</p></div>';
            } else {
                repairsList.innerHTML = filtered.map(repair => `
                    <div class="repair-card bg-white rounded-xl border border-slate-200 p-5">
                        <!-- Same card structure as above -->
                    </div>
                `).join('');
            }
        });

        // Modal functions
        function displayStatusLabel(status) {
            if (!status) return '';
            switch(status) {
                case 'in_progress': return 'In Progress';
                case 'pending': return 'Pending';
                case 'completed': return 'Completed';
                case 'cancelled': return 'Cancelled';
                default: return status.charAt(0).toUpperCase() + status.slice(1);
            }
        }

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

        // Send asset to replacement
        function sendAssetToReplacement(repairId, assetId, requestId) {
            if (!confirm('Create a replacement request for this asset?')) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('/admin/replacements/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    asset_id: assetId,
                    reason: 'Created from repair request',
                    replacement_reason: 'Beyond Repair'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Replacement request created successfully!');
                    closeViewRepairModal();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create replacement request'));
                }
            })
            .catch(err => {
                alert('Error creating replacement: ' + err.message);
            });
        }

        // Send asset to disposal
        function sendAssetToDisposal(repairId, assetId, requestId) {
            if (!confirm('Create a disposal request for this asset?')) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('/admin/disposals/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    asset_id: assetId,
                    reason: 'Created from repair request',
                    disposal_reason: 'Beyond Repair'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Disposal request created successfully!');
                    closeViewRepairModal();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create disposal request'));
                }
            })
            .catch(err => {
                alert('Error creating disposal: ' + err.message);
            });
        }

        // Form submission — save to database
        document.getElementById('repairForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const payload = {
                asset_id: formData.get('asset_id'),
                issue_description: formData.get('issue_description'),
                priority: formData.get('priority'),
                requested_by: formData.get('requested_by') || '',
            };

            if (!payload.asset_id || !payload.issue_description) {
                alert('Please select an asset and describe the issue.');
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';
            }

            try {
                const res = await fetch('/admin/repairs/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to create repair request');
                }

                // Reload so the new repair comes from the database
                alert('Repair request created successfully!');
                location.reload();
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Request';
                }
            }
        });

        // Initial render
        renderRepairs();
    </script>
</body>
</html>