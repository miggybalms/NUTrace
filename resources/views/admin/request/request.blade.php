<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Request Management - Admin Dashboard</title>
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
        
        .request-row {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .request-row:hover {
            background-color: #f9fafb;
            transform: translateX(2px);
        }
        
        .request-row.selected {
            background-color: #eff6ff;
            border-left: 3px solid #3b82f6;
        }
        
        .status-badge {
            transition: all 0.2s ease;
        }
        
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }
        
        .detail-card {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
                            <h2 class="text-2xl font-bold text-gray-900">Requests</h2>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-blue-600 font-medium">Admin</span>
                                <span class="mx-2 text-gray-300">•</span>
                                <p class="text-sm text-gray-500">Manage and process asset requests</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                                <i class="ri-file-copy-line mr-2"></i>
                                Export Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Requests</p>
                                <p class="text-3xl font-bold text-gray-900">{{ $totalRequests ?? 0 }}</p>
                                <p class="text-xs text-gray-500 mt-2">All time requests</p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="ri-mail-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Pending</p>
                                <p class="text-3xl font-bold text-orange-600">{{ $pendingRequests ?? 0 }}</p>
                                <p class="text-xs text-orange-600 mt-2">Awaiting approval</p>
                            </div>
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="ri-time-line text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Approved</p>
                                <p class="text-3xl font-bold text-green-600">{{ $approvedRequests ?? 0 }}</p>
                                <p class="text-xs text-green-600 mt-2">Approved requests</p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Rejected</p>
                                <p class="text-3xl font-bold text-red-600">{{ $rejectedRequests ?? 0 }}</p>
                                <p class="text-xs text-red-600 mt-2">Rejected requests</p>
                            </div>
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="ri-close-circle-line text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-t-xl shadow-sm border border-gray-200 mb-0">
                    <div class="flex space-x-8 px-6 pt-4">
                        <button class="tab-btn pb-3 text-sm font-medium text-gray-500 hover:text-gray-700 transition" data-tab="all">
                            All Requests
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium text-gray-500 hover:text-gray-700 transition" data-tab="pending">
                            Pending
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium text-gray-500 hover:text-gray-700 transition" data-tab="approved">
                            Approved
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium text-gray-500 hover:text-gray-700 transition" data-tab="rejected">
                            Rejected
                        </button>
                    </div>
                </div>

                <!-- Main Content Area: Request List + Details Side by Side -->
                <div class="flex gap-6">
                    <!-- Request List Section -->
                    <div class="flex-1 bg-white rounded-b-xl shadow-sm border border-t-0 border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Request ID</th>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Asset Name</th>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Type</th>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Submitted By</th>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Date</th>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                        <th class="text-left py-3 px-6 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="requests-table-body">
                                    @forelse($requests as $request)
                                    <tr class="request-row border-b border-gray-100 hover:bg-gray-50 transition" data-request-id="{{ $request->id }}" onclick="selectRequest({{ $request->id }})">
                                        <td class="py-3 px-6 text-sm font-mono text-gray-900">#REQ-{{ str_pad($request->id, 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="py-3 px-6 text-sm text-gray-900">{{ $request->asset_name }}</td>
                                        <td class="py-3 px-6">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                @if($request->type == 'new_asset') bg-blue-100 text-blue-700
                                                @elseif($request->type == 'repair') bg-red-100 text-red-700
                                                @elseif($request->type == 'pullout') bg-orange-100 text-orange-700
                                                @else bg-gray-100 text-gray-700
                                                @endif">
                                                {{ ucfirst(str_replace('_', ' ', $request->type)) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-6 text-sm text-gray-600">{{ $request->submitted_by }}</td>
                                        <td class="py-3 px-6 text-sm text-gray-600">{{ $request->created_at->format('M d, Y') }}</td>
                                        <td class="py-3 px-6">
                                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                @if($request->status == 'pending') bg-orange-100 text-orange-700
                                                @elseif($request->status == 'approved') bg-green-100 text-green-700
                                                @else bg-red-100 text-red-700
                                                @endif">
                                                @if($request->status == 'pending')
                                                    <i class="ri-time-line mr-1 text-xs"></i>
                                                @elseif($request->status == 'approved')
                                                    <i class="ri-checkbox-circle-line mr-1 text-xs"></i>
                                                @else
                                                    <i class="ri-close-circle-line mr-1 text-xs"></i>
                                                @endif
                                                {{ ucfirst($request->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-6">
                                            <div class="flex items-center space-x-2" onclick="event.stopPropagation()">
                                                @if($request->status == 'pending')
                                                <button onclick="approveRequest({{ $request->id }})" class="text-green-600 hover:text-green-700" title="Approve">
                                                    <i class="ri-checkbox-circle-line text-lg"></i>
                                                </button>
                                                <button onclick="rejectRequest({{ $request->id }})" class="text-red-600 hover:text-red-700" title="Reject">
                                                    <i class="ri-close-circle-line text-lg"></i>
                                                </button>
                                                @endif
                                                <button class="text-blue-600 hover:text-blue-700" title="View Details">
                                                    <i class="ri-eye-line text-lg"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center">
                                            <i class="ri-inbox-line text-5xl text-gray-300 mb-3 block"></i>
                                            <p class="text-gray-500">No requests found.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Request Details Section -->
                    <div class="w-96 bg-white rounded-xl shadow-sm border border-gray-200 overflow-y-auto" id="request-details-panel">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="ri-file-info-line mr-2 text-blue-600"></i>
                                Request Details
                            </h3>
                            
                            <div id="no-selection-message" class="text-center py-12">
                                <i class="ri-inbox-line text-5xl text-gray-300 mb-3 block"></i>
                                <p class="text-gray-500">No request selected.</p>
                                <p class="text-xs text-gray-400 mt-2">Click on any request to view details</p>
                            </div>
                            
                            <div id="request-detail-content" style="display: none;">
                                <!-- Asset Photo -->
                                <div class="mb-6">
                                    <div class="bg-gray-100 rounded-lg h-48 flex items-center justify-center mb-3">
                                        <i class="ri-image-line text-4xl text-gray-400"></i>
                                    </div>
                                    <p class="text-xs text-gray-500 text-center">Asset Photo</p>
                                </div>
                                
                                <!-- Request Information -->
                                <div class="space-y-4">
                                    <div class="pb-3 border-b border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Request ID</p>
                                        <p class="text-sm font-semibold text-gray-900 font-mono" id="detail-id">-</p>
                                    </div>
                                    
                                    <div class="pb-3 border-b border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Asset Name</p>
                                        <p class="text-sm font-semibold text-gray-900" id="detail-asset">-</p>
                                    </div>
                                    
                                    <div class="pb-3 border-b border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Request Type</p>
                                        <p class="text-sm font-semibold text-gray-900" id="detail-type">-</p>
                                    </div>
                                    
                                    <div class="pb-3 border-b border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Submitted By</p>
                                        <p class="text-sm text-gray-900" id="detail-submitter">-</p>
                                        <p class="text-xs text-gray-500 mt-1" id="detail-email">-</p>
                                    </div>
                                    
                                    <div class="pb-3 border-b border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Date Submitted</p>
                                        <p class="text-sm text-gray-900" id="detail-date">-</p>
                                    </div>
                                    
                                    <div class="pb-3 border-b border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Status</p>
                                        <span id="detail-status-badge" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">-</span>
                                    </div>
                                    
                                    <div class="pb-3">
                                        <p class="text-xs text-gray-500 mb-1">Description / Reason</p>
                                        <p class="text-sm text-gray-700 mt-1 leading-relaxed" id="detail-description">-</p>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons for Details Panel -->
                                <div class="mt-6 pt-4 border-t border-gray-200 flex gap-3" id="detail-actions">
                                    <button onclick="approveCurrentRequest()" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center justify-center">
                                        <i class="ri-checkbox-circle-line mr-2"></i>
                                        Approve
                                    </button>
                                    <button onclick="rejectCurrentRequest()" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center justify-center">
                                        <i class="ri-close-circle-line mr-2"></i>
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sample requests data (replace with your actual data from Laravel)
        const requestsData = @json($requests ?? []);
        let currentSelectedRequestId = null;
        
        function selectRequest(requestId) {
            // Remove selected class from all rows
            document.querySelectorAll('.request-row').forEach(row => {
                row.classList.remove('selected');
            });
            
            // Add selected class to clicked row
            const selectedRow = document.querySelector(`.request-row[data-request-id="${requestId}"]`);
            if (selectedRow) {
                selectedRow.classList.add('selected');
            }
            
            // Find request data
            const request = requestsData.find(r => r.id == requestId);
            if (request) {
                currentSelectedRequestId = requestId;
                displayRequestDetails(request);
            }
        }
        
        function displayRequestDetails(request) {
            // Hide no selection message and show details
            document.getElementById('no-selection-message').style.display = 'none';
            document.getElementById('request-detail-content').style.display = 'block';
            
            // Populate details
            document.getElementById('detail-id').textContent = `#REQ-${String(request.id).padStart(4, '0')}`;
            document.getElementById('detail-asset').textContent = request.asset_name;
            document.getElementById('detail-type').innerHTML = getTypeBadge(request.type);
            document.getElementById('detail-submitter').textContent = request.submitted_by;
            document.getElementById('detail-email').textContent = request.email || 'user@university.edu';
            document.getElementById('detail-date').textContent = new Date(request.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('detail-description').textContent = request.description || 'No description provided.';
            
            // Status badge
            const statusBadge = document.getElementById('detail-status-badge');
            statusBadge.innerHTML = getStatusBadge(request.status);
            
            // Show/hide action buttons based on status
            const actionButtons = document.getElementById('detail-actions');
            if (request.status === 'pending') {
                actionButtons.style.display = 'flex';
            } else {
                actionButtons.style.display = 'none';
            }
        }
        
        function getTypeBadge(type) {
            const badges = {
                'new_asset': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700"><i class="ri-add-line mr-1 text-xs"></i>New Asset</span>',
                'repair': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700"><i class="ri-tools-line mr-1 text-xs"></i>Repair</span>',
                'pullout': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700"><i class="ri-logout-box-line mr-1 text-xs"></i>Pullout</span>',
                'disposal': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><i class="ri-delete-bin-line mr-1 text-xs"></i>Disposal</span>'
            };
            return badges[type] || badges['new_asset'];
        }
        
        function getStatusBadge(status) {
            if (status === 'pending') {
                return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700"><i class="ri-time-line mr-1 text-xs"></i>Pending</span>';
            } else if (status === 'approved') {
                return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700"><i class="ri-checkbox-circle-line mr-1 text-xs"></i>Approved</span>';
            } else {
                return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700"><i class="ri-close-circle-line mr-1 text-xs"></i>Rejected</span>';
            }
        }
        
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this request?')) {
                // Add your approval logic here
                alert(`Request #REQ-${String(requestId).padStart(4, '0')} approved!`);
                location.reload();
            }
        }
        
        function rejectRequest(requestId) {
            if (confirm('Are you sure you want to reject this request?')) {
                // Add your rejection logic here
                alert(`Request #REQ-${String(requestId).padStart(4, '0')} rejected!`);
                location.reload();
            }
        }
        
        function approveCurrentRequest() {
            if (currentSelectedRequestId) {
                approveRequest(currentSelectedRequestId);
            }
        }
        
        function rejectCurrentRequest() {
            if (currentSelectedRequestId) {
                rejectRequest(currentSelectedRequestId);
            }
        }
        
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.tab;
                
                // Update active tab styling
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('tab-active');
                    b.classList.add('text-gray-500');
                });
                this.classList.add('tab-active');
                this.classList.remove('text-gray-500');
                
                // Filter table rows
                filterRequests(tab);
            });
        });
        
        function filterRequests(status) {
            const rows = document.querySelectorAll('#requests-table-body tr');
            rows.forEach(row => {
                if (row.querySelector('td')) { // Skip empty state row
                    const statusCell = row.querySelector('td:nth-child(6) .status-badge');
                    if (statusCell) {
                        const rowStatus = statusCell.textContent.trim().toLowerCase();
                        if (status === 'all' || rowStatus === status) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                }
            });
        }
        
        // Read `tab` from query parameters and set initial active tab (defaults to 'all')
        (function() {
            const params = new URLSearchParams(window.location.search);
            const initialTab = params.get('tab') || 'all';
            const btn = document.querySelector(`.tab-btn[data-tab="${initialTab}"]`) || document.querySelector('.tab-btn[data-tab="all"]') || document.querySelector('.tab-btn');
            if (btn) {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
                btn.classList.add('tab-active');
                btn.classList.remove('text-gray-500');
            }
            filterRequests(initialTab);
        })();
    </script>
</body>
</html>