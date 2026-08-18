<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Assets - Department Assets</title>
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
            background-color: #e5e7eb;
            color: #1f2937;
        }

        .sidebar-item.active {
            background-color: #eff6ff;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .asset-row {
            transition: background-color 0.15s ease;
        }

        .asset-row:hover {
            background-color: #f8fafc;
        }

        .filter-chip {
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .filter-chip.active {
            background-color: #2563eb;
            color: white;
        }

        .filter-chip.active:hover {
            background-color: #1d4ed8;
        }

        .filter-chip:hover:not(.active) {
            background-color: #e2e8f0;
        }

        .action-btn {
            transition: all 0.15s ease;
        }

        .action-btn:hover {
            transform: scale(1.08);
        }

        /* Custom scrollbar */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-panel {
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .detail-field p:first-child {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 0.25rem;
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
                    <div class="flex justify-between items-center gap-4">
                        <div class="flex items-center min-w-0">
                            <a href="/admin/assets" class="text-slate-400 hover:text-slate-700 mr-3.5 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 transition-colors flex-shrink-0">
                                <i class="ri-arrow-left-line text-xl"></i>
                            </a>
                            <div class="min-w-0">
                                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Assets</h2>
                                <div class="flex items-center mt-1">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md">
                                        <i class="ri-computer-line"></i> Asset Officer
                                    </span>
                                    <p class="text-sm text-slate-500 ml-3 hidden sm:block">Manage and track all university assets</p>
                                </div>
                            </div>
                        </div>
                        <button class="bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 flex items-center transition-colors font-medium text-sm shadow-sm shadow-blue-600/20 flex-shrink-0">
                            <i class="ri-add-line mr-1.5"></i>
                            <span class="hidden sm:inline">Add New Asset</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <!-- Search and Filter Section -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

<!-- Search Box -->
<form method="GET" action="{{ request()->url() }}" class="md:col-span-2" id="searchForm">
    <label class="block text-sm font-medium text-slate-700 mb-1.5">Search</label>
    <div class="relative">
        <i class="ri-search-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text"
               id="searchAssets"
               name="search"
               value="{{ $currentSearch ?? '' }}"
               placeholder="Search assets..."
               class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-shadow"
               autocomplete="off">
        
        <input type="hidden" name="status" value="{{ $currentStatus ?? 'all' }}">
        <input type="hidden" name="category" value="{{ $currentCategory ?? 'all' }}">
    </div>
</form>

<!-- Categories Dropdown -->
<form method="GET">
    <input type="hidden" name="status" value="{{ $currentStatus ?? 'all' }}">
    <input type="hidden" name="search" value="{{ $currentSearch ?? '' }}">
    <label class="block text-sm font-medium text-slate-700 mb-1.5">Categories</label>
    
    <!-- ✅ Only ONE select. Added id="categoryFilter" -->
    <select id="categoryFilter" name="category" onchange="this.form.submit()"
            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-shadow">
        <option value="all">All Categories</option>
        @foreach(($categoryOptions ?? []) as $opt)
            <option value="{{ $opt['value'] }}"
                {{ ($currentCategory ?? 'all') === $opt['value'] ? 'selected' : '' }}>
                {{ $opt['label'] }}
            </option>
        @endforeach
    </select>
</form>
                    </div>

                    <div class="mt-5 pt-5 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-sm text-slate-500">Only assets with <span class="font-semibold text-green-700">Active</span> lifecycle stage can be selected for pullout.</p>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm font-medium" onclick="selectAllVisibleActiveAssets()">
                                Select All Active
                            </button>
                            <button type="button" id="pulloutSelectedBtn" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed text-sm font-medium shadow-sm shadow-orange-600/20" onclick="openBulkPulloutModal()" disabled>
                                Pull Out Selected
                            </button>
                        </div>
                    </div>
                    
<!-- Filter Chips -->
<div class="mt-4 flex flex-wrap gap-2">
    @php
        $filters = [
            'all'        => 'All',
            'active'     => 'Active',
            'acquired'   => 'Acquired',
            'for_repair' => 'For Repair',
            'pulled_out' => 'Pulled Out',
            'disposed'   => 'Disposed',
        ];
        // Preserve ALL query parameters except 'status' and 'page'
        $baseQuery = request()->except(['status', 'page']);
    @endphp

    @foreach($filters as $key => $label)
        <a href="{{ request()->url() }}?{{ http_build_query(array_merge($baseQuery, ['status' => $key])) }}"
           class="filter-chip px-3.5 py-1.5 rounded-full text-xs font-medium
                  {{ ($currentStatus ?? 'all') === $key
                      ? 'active'
                      : 'bg-slate-100 text-slate-600' }}">
            {{ $label }}
        </a>
    @endforeach

    <a href="{{ request()->url() }}?{{ http_build_query(request()->except(['status', 'category', 'search', 'page'])) }}"
       class="filter-chip px-3.5 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
        <i class="ri-refresh-line mr-0.5"></i> Reset
    </a>
</div>
                </div>

                <!-- Assets Table -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-900">Assets in {{ $departmentName ?? 'Department' }}</h3>
                        <p class="text-sm text-slate-500 mt-0.5">Showing all assets from {{ $departmentName ?? 'selected' }} Department</p>
                    </div>
                    
                    <div class="table-container overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200">
                                    <th class="text-left py-3 px-4">
                                        <input type="checkbox" id="selectAllAssets" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" onchange="toggleVisibleAssetSelection(this)">
                                    </th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Asset ID</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Asset Name</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Accountable</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Date Acquired</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Current Location</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Lifecycle Stage</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="assetsTableBody" class="divide-y divide-slate-100">
                                @forelse($assets ?? [] as $asset)
                                <tr class="asset-row transition-colors" data-asset-id="{{ $asset->db_id ?? $asset->id }}" data-asset-code="{{ $asset->id }}" data-category="{{ $asset->category_code }}" data-status="{{ $asset->status }}" data-name="{{ strtolower($asset->name) }}" data-id="{{ strtolower($asset->id) }}" data-qr-url="{{ $asset->qr_code_url ?? '' }}" data-qr-path="{{ $asset->qr_code_path ? Storage::url($asset->qr_code_path) : '' }}" data-serial="{{ $asset->serial_number ?? '' }}" data-purchase-price="{{ $asset->purchase_price ?? '' }}" data-warranty-months="{{ $asset->warranty_months ?? '' }}" data-condition="{{ $asset->condition ?? '' }}">
                                    <td class="py-3 px-4">
                                        @if($asset->status === 'active')
                                            <input type="checkbox" class="asset-select-checkbox rounded border-slate-300 text-orange-600 focus:ring-orange-500" value="{{ $asset->db_id ?? $asset->id }}" data-status="{{ $asset->status }}" onchange="updateBulkPulloutButtonState()">
                                        @else
                                            <input type="checkbox" class="rounded border-slate-200 text-slate-300" disabled title="Only Active assets can be pulled out">
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm font-mono text-slate-800">{{ $asset->id }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                @if(str_contains(strtolower($asset->name), 'laptop') || str_contains(strtolower($asset->name), 'computer'))
                                                    <i class="ri-computer-line text-blue-600"></i>
                                                @elseif(str_contains(strtolower($asset->name), 'chair'))
                                                    <i class="ri-chair-line text-blue-600"></i>
                                                @elseif(str_contains(strtolower($asset->name), 'printer'))
                                                    <i class="ri-printer-line text-blue-600"></i>
                                                @elseif(str_contains(strtolower($asset->name), 'monitor'))
                                                    <i class="ri-tv-line text-blue-600"></i>
                                                @else
                                                    <i class="ri-device-line text-blue-600"></i>
                                                @endif
                                            </div>
                                            <span class="text-sm font-medium text-slate-900">{{ $asset->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-slate-600">{{ $asset->category }}</td>
                                    <td class="py-3 px-4 text-sm text-slate-600">{{ $asset->accountable }}</td>
                                    <td class="py-3 px-4 text-sm text-slate-600">{{ $asset->date_acquired }}</td>
                                    <td class="py-3 px-4 text-sm text-slate-600">{{ $asset->location }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                            @if($asset->status == 'active') bg-green-50 text-green-700 ring-1 ring-inset ring-green-200
                                            @elseif($asset->status == 'acquired') bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-200
                                            @elseif($asset->status == 'for_repair') bg-red-50 text-red-700 ring-1 ring-inset ring-red-200
                                            @elseif($asset->status == 'pulled_out') bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200
                                            @else bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200
                                            @endif">
                                            <i class="ri-circle-fill mr-1.5 text-[8px]"></i>
                                            {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-1">
                                            <!-- View Details Button -->
                                            <button class="action-btn view-details-btn w-8 h-8 flex items-center justify-center text-blue-600 hover:bg-blue-50 rounded-lg" title="View Details">
                                                <i class="ri-eye-line text-lg"></i>
                                            </button>
                                            <!-- QR Code Button -->
                                            <button class="action-btn view-qr-btn w-8 h-8 flex items-center justify-center text-purple-600 hover:bg-purple-50 rounded-lg" title="View QR Code">
                                                <i class="ri-qr-code-line text-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="py-16 text-center text-slate-500">
                                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i class="ri-inbox-line text-2xl text-slate-400"></i>
                                        </div>
                                        <p class="text-sm">No assets found for this department.</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-5 sm:px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row gap-3 justify-between items-center">
    <p class="text-sm text-slate-500">
        Showing
        <span class="font-medium text-slate-700">{{ $assets->firstItem() ?? 0 }}</span>
        –
        <span class="font-medium text-slate-700">{{ $assets->lastItem() ?? 0 }}</span>
        of
        <span class="font-medium text-slate-700">{{ $assets->total() }}</span>
        assets
    </p>

    <div>
        {{ $assets->appends(request()->except('page'))->links() }}
    </div>
</div>
                </div>

                <!-- Footer -->
                <div class="text-center text-xs text-slate-400 mt-10 pt-6 border-t border-slate-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Pullout Modal -->
    <div id="bulkPulloutModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-slate-100">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Bulk Pullout</h3>
                        <p class="text-sm text-slate-500 mt-0.5"><span id="bulkPulloutCount">0</span> selected asset(s)</p>
                    </div>
                    <button onclick="closeBulkPulloutModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
            </div>
<form id="bulkPulloutForm" class="p-6 space-y-4">
    @csrf
    <div>
    <label class="block text-sm font-medium text-slate-700 mb-1.5">Reason</label>
    <input type="text" name="reason" value="Pullout" readonly
        class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-slate-700 text-sm cursor-not-allowed">
</div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Destination</label>
        <input type="text" name="destination" value="Storage Room" readonly
            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-slate-700 text-sm cursor-not-allowed">
        <p class="text-xs text-slate-400 mt-1.5">Pulled-out assets are moved to Storage Room.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Additional Notes</label>
        <textarea name="notes" rows="3" placeholder="Any additional information about the pullout..."
            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100 transition-shadow resize-none"></textarea>
    </div>

    <div id="bulkPulloutAssetSummary" class="p-3.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700"></div>

    <div class="flex justify-end space-x-3 pt-2 border-t border-slate-100">
        <button type="button" onclick="closeBulkPulloutModal()"
            class="px-4 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm font-medium">Cancel</button>
        <button type="submit" class="px-4 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium shadow-sm shadow-orange-600/20">Submit Pullout</button>
    </div>
</form>
        </div>
    </div>

  <!-- Asset Details Modal -->
<div id="assetModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-900">Asset Details</h3>
            <button type="button" onclick="closeAssetModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="flex mb-6 pb-6 border-b border-slate-100">
                <div class="w-16 h-16 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="ri-computer-line text-2xl text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-lg font-bold text-slate-900" id="modalAssetName">—</h4>
                    <p class="text-xs text-slate-400 font-mono mt-0.5" id="modalAssetId">—</p>
                    <span id="modalStatus" class="inline-flex items-center px-2.5 py-1 mt-2 rounded-full text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-200">
                        Active
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div class="detail-field">
                    <p>Category</p>
                    <p class="text-sm font-medium text-slate-900" id="modalCategory">—</p>
                </div>
                <div class="detail-field">
                    <p>Accountable</p>
                    <p class="text-sm font-medium text-slate-900" id="modalAccountable">—</p>
                </div>
                <div class="detail-field">
                    <p>Date Acquired</p>
                    <p class="text-sm font-medium text-slate-900" id="modalDate">—</p>
                </div>
                <div class="detail-field">
                    <p>Location</p>
                    <p class="text-sm font-medium text-slate-900" id="modalLocation">—</p>
                </div>
                <div class="detail-field">
                    <p>Serial Number</p>
                    <p class="text-sm font-medium text-slate-900" id="modalSerial">—</p>
                </div>
                <div class="detail-field">
                    <p>Purchase Price</p>
                    <p class="text-sm font-medium text-slate-900" id="modalPurchasePrice">—</p>
                </div>
                <div class="detail-field">
                    <p>Warranty</p>
                    <p class="text-sm font-medium text-slate-900" id="modalWarranty">—</p>
                </div>
                <div class="detail-field">
                    <p>Condition</p>
                    <p class="text-sm font-medium text-slate-900" id="modalCondition">—</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-900">Asset QR Code</h3>
            <button type="button" onclick="closeQRModal()" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="p-6 flex flex-col items-center">
            <p class="text-sm text-slate-500 mb-4 font-mono" id="qrModalAssetId"></p>
            <img id="qrModalImg" class="hidden max-w-[220px] rounded-lg border border-slate-200 p-2" alt="QR Code">
            <div id="qrModalCanvas" class="flex items-center justify-center" style="display:none;"></div>
            <div class="flex gap-3 mt-6 w-full">
                <button type="button" onclick="downloadQRFromModal()"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shadow-sm shadow-blue-600/20">
                    <i class="ri-download-line mr-1.5"></i> Download
                </button>
                <button type="button" onclick="printQRFromModal()"
                    class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm font-medium">
                    <i class="ri-printer-line mr-1.5"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

    <script>

        let searchTimeout;
        document.getElementById('searchAssets')?.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
        this.form.submit();          // real GET request → server
    }, 400);
});
        
        function getVisibleActiveCheckboxes() {
            return Array.from(document.querySelectorAll('.asset-select-checkbox')).filter((checkbox) => {
                const row = checkbox.closest('tr');
                return row && row.style.display !== 'none' && checkbox.dataset.status === 'active';
            });
        }

        function updateBulkPulloutButtonState() {
            const selected = document.querySelectorAll('.asset-select-checkbox:checked').length;
            const button = document.getElementById('pulloutSelectedBtn');
            const counter = document.getElementById('bulkPulloutCount');
            const summary = document.getElementById('bulkPulloutAssetSummary');
            const selectedRows = Array.from(document.querySelectorAll('.asset-select-checkbox:checked')).map((checkbox) => {
                const row = checkbox.closest('tr');
                return row ? `${row.getAttribute('data-asset-code') || row.getAttribute('data-id')}` : null;
            }).filter(Boolean);

            if (button) {
                button.disabled = selected === 0;
            }
            if (counter) {
                counter.textContent = selected.toString();
            }
            if (summary) {
                summary.textContent = selectedRows.length ? `Selected assets: ${selectedRows.join(', ')}` : 'No assets selected.';
            }
        }

        function toggleVisibleAssetSelection(masterCheckbox) {
            getVisibleActiveCheckboxes().forEach((checkbox) => {
                checkbox.checked = masterCheckbox.checked;
            });
            updateBulkPulloutButtonState();
        }

        function selectAllVisibleActiveAssets() {
            const master = document.getElementById('selectAllAssets');
            if (master) {
                master.checked = true;
            }
            getVisibleActiveCheckboxes().forEach((checkbox) => {
                checkbox.checked = true;
            });
            updateBulkPulloutButtonState();
        }

        function openBulkPulloutModal() {
            const selectedAssetIds = Array.from(document.querySelectorAll('.asset-select-checkbox:checked')).map((checkbox) => checkbox.value);
            if (!selectedAssetIds.length) {
                return;
            }

            const modal = document.getElementById('bulkPulloutModal');
            const summary = document.getElementById('bulkPulloutAssetSummary');
            const counter = document.getElementById('bulkPulloutCount');

            if (counter) {
                counter.textContent = selectedAssetIds.length.toString();
            }
            if (summary) {
                summary.textContent = `Selected asset IDs: ${selectedAssetIds.join(', ')}`;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeBulkPulloutModal() {
            const modal = document.getElementById('bulkPulloutModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

document.getElementById('bulkPulloutForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const selectedAssetIds = Array.from(document.querySelectorAll('.asset-select-checkbox:checked'))
        .map((checkbox) => checkbox.value);

    if (!selectedAssetIds.length) {
        alert('Please select at least one active asset.');
        return;
    }

    const formData = new FormData(this);
    const reason = formData.get('reason') || '';

    if (!reason) {
        alert('Please select a reason for pullout.');
        return;
    }

const payload = {
    asset_ids: selectedAssetIds,
    reason: reason,
    destination: 'Storage Room',
    expected_return_date: null,
    notes: formData.get('notes') || '',
    pullout_date: new Date().toISOString().slice(0, 10),
    pulled_by: @json(Auth::user()->email ?? 'Admin'),
    status: 'pending',
};

    try {
        const response = await fetch('/admin/pullout/record', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.error || data.message || 'Failed to create pullout.');
            return;
        }

        alert(data.message || 'Pullout recorded successfully!');
        window.location.reload();
    } catch (error) {
        alert('Network error: ' + error.message);
    }
});

        document.querySelectorAll('.asset-select-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', updateBulkPulloutButtonState);
        });


        function toggleReassignFields() {
    const reason = document.getElementById('pulloutReason')?.value;
    const block = document.getElementById('reassignUserBlock');
    const select = document.getElementById('assignToUser');
    if (!block) return;

    if (reason === 'Reassignment') {
        block.classList.remove('hidden');
        if (select) select.required = true;
    } else {
        block.classList.add('hidden');
        if (select) {
            select.required = false;
            select.value = '';
        }
    }
}  


        // Modal functions
        function openAssetModal(assetId) {
    const row = document.querySelector(`tr[data-asset-id="${assetId}"]`);
    if (!row) {
        alert('Asset row not found');
        return;
    }

    window.currentAssetId = assetId;

    // Columns: 1 checkbox | 2 ID | 3 Name | 4 Category | 5 Accountable | 6 Date | 7 Location | 8 Status | 9 Actions
    const name = row.querySelector('td:nth-child(3) span')?.textContent?.trim() || 'Asset';
    const assetCode = row.getAttribute('data-asset-code') || row.querySelector('td:nth-child(2)')?.textContent?.trim() || '';
    const category = row.querySelector('td:nth-child(4)')?.textContent?.trim() || '-';
    const accountable = row.querySelector('td:nth-child(5)')?.textContent?.trim() || '-';
    const date = row.querySelector('td:nth-child(6)')?.textContent?.trim() || '-';
    const location = row.querySelector('td:nth-child(7)')?.textContent?.trim() || '-';
    const status = row.querySelector('td:nth-child(8) span')?.textContent?.trim() || 'Active';
    const serial = row.getAttribute('data-serial') || '-';
    const purchasePrice = row.getAttribute('data-purchase-price') || '';
    const warrantyMonths = row.getAttribute('data-warranty-months') || '';
    const condition = row.getAttribute('data-condition') || '';

    document.getElementById('modalAssetName').textContent = name;
    document.getElementById('modalAssetId').textContent = assetCode;
    document.getElementById('modalCategory').textContent = category;
    document.getElementById('modalAccountable').textContent = accountable;
    document.getElementById('modalDate').textContent = date;
    document.getElementById('modalLocation').textContent = location;
    document.getElementById('modalSerial').textContent = serial || '-';
    document.getElementById('modalPurchasePrice').textContent = purchasePrice ? ('₱' + purchasePrice) : '-';
    document.getElementById('modalWarranty').textContent = warrantyMonths ? (warrantyMonths + ' months') : '-';
    document.getElementById('modalCondition').textContent = condition || '-';

    const statusBadge = document.getElementById('modalStatus');
    statusBadge.innerHTML = `<i class="ri-circle-fill mr-1 text-xs"></i>${status}`;

    const modal = document.getElementById('assetModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAssetModal() {
    const modal = document.getElementById('assetModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function closeQRModal() {
    const modal = document.getElementById('qrModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    const img = document.getElementById('qrModalImg');
    const canvas = document.getElementById('qrModalCanvas');
    if (img) { img.src = ''; img.classList.add('hidden'); }
    if (canvas) { canvas.innerHTML = ''; canvas.style.display = 'none'; }
}

// Attach View Details buttons
document.querySelectorAll('.action-btn[title="View Details"]').forEach((btn) => {
    btn.addEventListener('click', function () {
        const row = this.closest('tr');
        const assetId = row?.getAttribute('data-asset-id');
        if (assetId) openAssetModal(assetId);
    });
});

// Attach QR buttons
let modalQRCodeInstance = null;
document.querySelectorAll('.view-qr-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
        const row = this.closest('tr');
        if (!row) return;

        const qrUrl = row.getAttribute('data-qr-url') || row.getAttribute('data-qr-path') || '';
        const assetCode = row.getAttribute('data-asset-code') || row.getAttribute('data-id') || '';
        const img = document.getElementById('qrModalImg');
        const canvasWrap = document.getElementById('qrModalCanvas');
        const idEl = document.getElementById('qrModalAssetId');

        if (modalQRCodeInstance && modalQRCodeInstance.clear) {
            try { modalQRCodeInstance.clear(); } catch (e) {}
        }
        modalQRCodeInstance = null;

        img.classList.add('hidden');
        img.src = '';
        canvasWrap.innerHTML = '';
        canvasWrap.style.display = 'none';
        idEl.textContent = assetCode;

        if (qrUrl && qrUrl.trim() !== '') {
            img.src = qrUrl;
            img.classList.remove('hidden');
        } else if (assetCode) {
            canvasWrap.style.display = 'flex';
            if (typeof QRCode !== 'undefined') {
                modalQRCodeInstance = new QRCode(canvasWrap, {
                    text: assetCode,
                    width: 220,
                    height: 220,
                    colorDark: '#1f2937',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            } else {
                // Fallback: external QR API
                img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(assetCode);
                img.classList.remove('hidden');
                canvasWrap.style.display = 'none';
            }
        }

        const modal = document.getElementById('qrModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    });
});

function downloadQRFromModal() {
    const img = document.getElementById('qrModalImg');
    const canvasWrap = document.getElementById('qrModalCanvas');
    const assetId = document.getElementById('qrModalAssetId')?.textContent || 'asset-qr';
    if (img && img.src && !img.classList.contains('hidden')) {
        const link = document.createElement('a');
        link.href = img.src;
        link.download = `qr-${assetId}.png`;
        link.click();
        return;
    }
    const canvas = canvasWrap?.querySelector('canvas');
    if (canvas) {
        const link = document.createElement('a');
        link.download = `qr-${assetId}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }
}

        function printQRFromModal() {
            const img = document.getElementById('qrModalImg');
            const canvasWrap = document.getElementById('qrModalCanvas');
            const assetId = document.getElementById('qrModalAssetId')?.textContent || 'asset-qr';

            // Get QR image
            let qrSrc = '';
            if (img && img.src && !img.classList.contains('hidden')) {
                qrSrc = img.src;
            } else {
                const canvas = canvasWrap?.querySelector('canvas');
                if (canvas) qrSrc = canvas.toDataURL('image/png');
            }
            if (!qrSrc) {
                alert('No QR code available to print.');
                return;
            }

            // Find the table row for this asset to get extra fields
            const row = document.querySelector(`tr[data-asset-code="${assetId}"]`)
                    || document.querySelector(`tr[data-id="${assetId}"]`);

            const assetName = row?.querySelector('td:nth-child(3) span')?.textContent?.trim()
                        || row?.getAttribute('data-name')
                        || 'ASSET';
            const category  = row?.querySelector('td:nth-child(4)')?.textContent?.trim() || '—';
            const location  = row?.querySelector('td:nth-child(7)')?.textContent?.trim() || '—';
            const acquired  = row?.querySelector('td:nth-child(6)')?.textContent?.trim() || '—';

            const win = window.open('', '_blank', 'width=500,height=300');
            win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
        <title>QR Sticker – ${assetId}</title>
        <style>
        @page { size: 90mm 40mm; margin: 0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, Helvetica, sans-serif; }
        .tag {
            width: 90mm;
            height: 40mm;
            border: 0.45mm solid #111;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .header {
            text-align: center;
            padding: 1.2mm 2mm 0.8mm;
            border-bottom: 0.35mm solid #111;
        }
        .header .office { font-size: 6pt; font-weight: 700; letter-spacing: 0.5px; }
        .header .name   { font-size: 8.5pt; font-weight: 700; text-transform: uppercase; margin-top: 0.3mm; }
        .header .campus { font-size: 5.5pt; color: #444; }
        .body {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 25mm;
            min-height: 0;
        }
        .info {
            padding: 1.2mm 2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.2mm;
            border-right: 0.35mm solid #111;
        }
        .code {
            font-family: 'Courier New', monospace;
            font-size: 7.5pt;
            font-weight: 700;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.8mm;
        }
        .cell {
            border: 0.25mm solid #888;
            padding: 0.6mm 0.5mm;
            text-align: center;
            font-size: 5.5pt;
            line-height: 1.15;
        }
        .cell span {
            display: block;
            font-size: 4.5pt;
            color: #555;
            font-weight: 600;
        }
        .qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1mm;
        }
        .qr img { width: 20mm; height: 20mm; }
        .qr span { font-size: 4.5pt; margin-top: 0.3mm; }
        .footer {
            text-align: center;
            font-size: 5.5pt;
            font-weight: 700;
            color: #c00;
            padding: 0.7mm;
            border-top: 0.35mm solid #111;
            letter-spacing: 0.4px;
        }
        @media print {
            body { margin: 0; }
        }
        </style>
        </head>
        <body>
        <div class="tag">
            <div class="header">
            <div class="office">ASSET MANAGEMENT OFFICE</div>
            <div class="name">${assetName}</div>
            <div class="campus">NU LIPA</div>
            </div>
            <div class="body">
            <div class="info">
                <div class="code">${assetId}</div>
                <div class="row">
                <div class="cell"><span>Location</span>${location}</div>
                <div class="cell"><span>Category</span>${category}</div>
                <div class="cell"><span>Acquired</span>${acquired}</div>
                </div>
            </div>
            <div class="qr">
                <img src="${qrSrc}" alt="QR">
                <span>Scan me</span>
            </div>
            </div>
            <div class="footer">DO NOT REMOVE THIS TAG</div>
        </div>
        <script>
            window.onload = function() {
            window.print();
            setTimeout(() => window.close(), 500);
            };
        <\/script>
        </body>
        </html>
            `);
            win.document.close();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</body>
</html>