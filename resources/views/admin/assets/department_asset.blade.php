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
            background-color: #C9A227;
        color: #0A1830;
    }

        .filter-chip.active:hover {
            background-color: #E0BC44;
        }

        .filter-chip:hover:not(.active) {
            background-color: #EAE2C9;
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
            --line:#DED2AE;
        }
        body{ background:var(--paper) !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',sans-serif !important; color:var(--ink-900); }
        .font-display{ font-family:'Fraunces',Georgia,serif; }
        .brand-title{ font-family:'Fraunces',Georgia,serif; }
        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }
        input[type=checkbox]{ accent-color:var(--gold-500); }
        .filter-chip.active{ background-color:var(--gold-500) !important; color:var(--navy-950) !important; }
        .filter-chip:hover:not(.active){ background-color:var(--paper-2) !important; }
        .asset-row:hover{ background-color:var(--paper-2) !important; }
        .table-container::-webkit-scrollbar-thumb{ background:#D8CDAF; border-radius:4px; }
        .table-container::-webkit-scrollbar-thumb:hover{ background:#C6B893; }
        .detail-field p:first-child{ color:var(--ink-400); }
    </style>
</head>
<body class="bg-[#F3EEE0]">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
            <!-- Header -->
            <!-- Header (shared admin header) -->
            @include('admin.partials.header', [
                'adminHeaderPage'     => 'department_assets',
                'adminHeaderTitle'    => 'Assets',
                'adminHeaderSubtitle' => 'Manage and track all university assets',
                'adminHeaderIcon'     => 'ri-computer-line',
                'adminHeaderBadge'    => 'Asset Officer',
                'adminHeaderBackUrl'  => '/admin/assets',
            ])

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <!-- Search and Filter Section -->
                <div class="bg-white rounded-xl border border-[#DED2AE] p-5 sm:p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

<!-- Search Box -->
<form method="GET" action="{{ request()->url() }}" class="md:col-span-2" id="searchForm">
    <label class="block text-sm font-medium text-[#33425C] mb-1.5">Search</label>
    <div class="relative">
        <i class="ri-search-line absolute left-3.5 top-1/2 -translate-y-1/2 text-[#8991A0]"></i>
        <input type="text"
               id="searchAssets"
               name="search"
               value="{{ $currentSearch ?? '' }}"
               placeholder="Search assets..."
               class="w-full pl-10 pr-4 py-2.5 border border-[#CFC4A4] rounded-lg text-sm focus:outline-none focus:border-[#C9A227] focus:ring-2 focus:ring-[#F3E7C4] transition-shadow"
               autocomplete="off">
        
        <input type="hidden" name="status" value="{{ $currentStatus ?? 'all' }}">
        <input type="hidden" name="category" value="{{ $currentCategory ?? 'all' }}">
    </div>
</form>

<!-- Categories Dropdown -->
<form method="GET">
    <input type="hidden" name="status" value="{{ $currentStatus ?? 'all' }}">
    <input type="hidden" name="search" value="{{ $currentSearch ?? '' }}">
    <label class="block text-sm font-medium text-[#33425C] mb-1.5">Categories</label>
    
    <!-- ✅ Only ONE select. Added id="categoryFilter" -->
    <select id="categoryFilter" name="category" onchange="this.form.submit()"
            class="w-full px-3.5 py-2.5 border border-[#CFC4A4] rounded-lg text-sm focus:outline-none focus:border-[#C9A227] focus:ring-2 focus:ring-[#F3E7C4] transition-shadow">
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

                    <div class="mt-5 pt-5 border-t border-[#EFE9D8] flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-sm text-[#5B6678]">Only assets with <span class="font-semibold text-[#245C3B]">Active</span> lifecycle stage can be selected for pullout.</p>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-4 py-2 border border-[#CFC4A4] rounded-lg text-[#33425C] hover:bg-[#F5F0E2] transition-colors text-sm font-medium" onclick="selectAllVisibleActiveAssets()">
                                Select All Active
                            </button>
                            <button type="button" id="pulloutSelectedBtn" class="px-4 py-2 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition-colors disabled:opacity-40 disabled:cursor-not-allowed text-sm font-medium shadow-sm" onclick="openBulkPulloutModal()" disabled>
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
                      : 'bg-[#EFE9D8] text-[#4B5468]' }}">
            {{ $label }}
        </a>
    @endforeach

    <a href="{{ request()->url() }}?{{ http_build_query(request()->except(['status', 'category', 'search', 'page'])) }}"
       class="filter-chip px-3.5 py-1.5 rounded-full text-xs font-medium bg-[#EFE9D8] text-[#4B5468]">
        <i class="ri-refresh-line mr-0.5"></i> Reset
    </a>
</div>
                </div>

                <!-- Assets Table -->
                <div class="bg-white rounded-xl border border-[#DED2AE] overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-[#EFE9D8]">
                        <h3 class="font-display text-lg font-semibold text-[#0F2143]">Assets in {{ $departmentName ?? 'Department' }}</h3>
                        <p class="text-sm text-[#5B6678] mt-0.5">Showing all assets from {{ $departmentName ?? 'selected' }} Department</p>
                    </div>
                    
                    <div class="table-container overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-[#F5F0E2] border-b border-[#DED2AE]">
                                    <th class="text-left py-3 px-4">
                                        <input type="checkbox" id="selectAllAssets" class="rounded border-[#CFC4A4] text-[#C9A227] focus:ring-[#E0BC44]" onchange="toggleVisibleAssetSelection(this)">
                                    </th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Asset ID</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Asset Name</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Category</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Accountable</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Date Acquired</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Current Location</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Lifecycle Stage</th>
                                    <th class="text-left py-3 px-4 text-[11px] font-semibold text-[#5B6678] uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="assetsTableBody" class="divide-y divide-[#EFE9D8]">
                                @forelse($assets ?? [] as $asset)
                                <tr class="asset-row transition-colors" data-asset-id="{{ $asset->db_id ?? $asset->id }}" data-asset-code="{{ $asset->id }}" data-category="{{ $asset->category_code }}" data-status="{{ $asset->status }}" data-name="{{ strtolower($asset->name) }}" data-id="{{ strtolower($asset->id) }}" data-qr-url="{{ $asset->qr_code_url ?? '' }}" data-qr-path="{{ $asset->qr_code_path ? Storage::url($asset->qr_code_path) : '' }}" data-serial="{{ $asset->serial_number ?? '' }}" data-purchase-price="{{ $asset->purchase_price ?? '' }}" data-warranty-months="{{ $asset->warranty_months ?? '' }}" data-condition="{{ $asset->condition ?? '' }}">
                                    <td class="py-3 px-4">
                                        @if($asset->status === 'active')
                                            <input type="checkbox" class="asset-select-checkbox rounded border-[#CFC4A4] text-[#C9A227] focus:ring-[#E0BC44]" value="{{ $asset->db_id ?? $asset->id }}" data-status="{{ $asset->status }}" onchange="updateBulkPulloutButtonState()">
                                        @else
                                            <input type="checkbox" class="rounded border-[#DED2AE] text-[#A8AFBC]" disabled title="Only Active assets can be pulled out">
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm font-mono text-[#24334F]">{{ $asset->id }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-[#F3E7C4] rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                @if(str_contains(strtolower($asset->name), 'laptop') || str_contains(strtolower($asset->name), 'computer'))
                                                    <i class="ri-computer-line text-[#A8841E]"></i>
                                                @elseif(str_contains(strtolower($asset->name), 'chair'))
                                                    <i class="ri-chair-line text-[#A8841E]"></i>
                                                @elseif(str_contains(strtolower($asset->name), 'printer'))
                                                    <i class="ri-printer-line text-[#A8841E]"></i>
                                                @elseif(str_contains(strtolower($asset->name), 'monitor'))
                                                    <i class="ri-tv-line text-[#A8841E]"></i>
                                                @else
                                                    <i class="ri-device-line text-[#A8841E]"></i>
                                                @endif
                                            </div>
                                            <span class="text-sm font-medium text-[#0F2143]">{{ $asset->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-[#4B5468]">{{ $asset->category }}</td>
                                    <td class="py-3 px-4 text-sm text-[#4B5468]">{{ $asset->accountable }}</td>
                                    <td class="py-3 px-4 text-sm text-[#4B5468]">{{ $asset->date_acquired }}</td>
                                    <td class="py-3 px-4 text-sm text-[#4B5468]">{{ $asset->location }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                            @if($asset->status == 'active') bg-[#EAF4EE] text-[#245C3B] ring-1 ring-inset ring-[#CFE3D4]
                                            @elseif($asset->status == 'acquired') bg-[#E9F0F7] text-[#234869] ring-1 ring-inset ring-[#CFE0EE]
                                            @elseif($asset->status == 'for_repair') bg-[#F7E9E6] text-[#7E2E27] ring-1 ring-inset ring-[#E8CCC6]
                                            @elseif($asset->status == 'pulled_out') bg-[#FBF1DE] text-[#8F5F16] ring-1 ring-inset ring-[#E9D9B4]
                                            @else bg-[#EFE9D8] text-[#4B5468] ring-1 ring-inset ring-[#DED2AE]
                                            @endif">
                                            <i class="ri-circle-fill mr-1.5 text-[8px]"></i>
                                            {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-1">
                                            <!-- View Details Button -->
                                            <button class="action-btn view-details-btn w-8 h-8 flex items-center justify-center text-[#A8841E] hover:bg-[#F3E7C4] rounded-lg" title="View Details">
                                                <i class="ri-eye-line text-lg"></i>
                                            </button>
                                            <!-- QR Code Button -->
                                            <button class="action-btn view-qr-btn w-8 h-8 flex items-center justify-center text-[#6B4C82] hover:bg-[#EFE7F3] rounded-lg" title="View QR Code">
                                                <i class="ri-qr-code-line text-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="py-16 text-center text-[#5B6678]">
                                        <div class="w-16 h-16 bg-[#EFE9D8] rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i class="ri-inbox-line text-2xl text-[#8991A0]"></i>
                                        </div>
                                        <p class="text-sm">No assets found for this department.</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-5 sm:px-6 py-4 border-t border-[#EFE9D8] flex flex-col sm:flex-row gap-3 justify-between items-center">
    <p class="text-sm text-[#5B6678]">
        Showing
        <span class="font-medium text-[#33425C]">{{ $assets->firstItem() ?? 0 }}</span>
        –
        <span class="font-medium text-[#33425C]">{{ $assets->lastItem() ?? 0 }}</span>
        of
        <span class="font-medium text-[#33425C]">{{ $assets->total() }}</span>
        assets
    </p>

    <div>
        {{ $assets->appends(request()->except('page'))->links() }}
    </div>
</div>
                </div>

                <!-- Footer -->
                <div class="text-center text-xs text-[#8991A0] mt-10 pt-6 border-t border-[#DED2AE]">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Pullout Modal -->
    <div id="bulkPulloutModal" class="hidden fixed inset-0 bg-[#0A1830]/60 backdrop-blur-sm z-50 items-center justify-center p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-[#EFE9D8]">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-[#0F2143]">Bulk Pullout</h3>
                        <p class="text-sm text-[#5B6678] mt-0.5"><span id="bulkPulloutCount">0</span> selected asset(s)</p>
                    </div>
                    <button onclick="closeBulkPulloutModal()" class="text-[#8991A0] hover:text-[#33425C] hover:bg-[#EFE9D8] w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
            </div>
<form id="bulkPulloutForm" class="p-6 space-y-4">
    @csrf
    <div>
    <label class="block text-sm font-medium text-[#33425C] mb-1.5">Reason</label>
    <input type="text" name="reason" value="Pullout" readonly
        class="w-full px-3.5 py-2.5 border border-[#DED2AE] rounded-lg bg-[#F5F0E2] text-[#33425C] text-sm cursor-not-allowed">
</div>

    <div>
        <label class="block text-sm font-medium text-[#33425C] mb-1.5">Destination</label>
        <input type="text" name="destination" value="Storage Room" readonly
            class="w-full px-3.5 py-2.5 border border-[#DED2AE] rounded-lg bg-[#F5F0E2] text-[#33425C] text-sm cursor-not-allowed">
        <p class="text-xs text-[#8991A0] mt-1.5">Pulled-out assets are moved to Storage Room.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-[#33425C] mb-1.5">Additional Notes</label>
        <textarea name="notes" rows="3" placeholder="Any additional information about the pullout..."
            class="w-full px-3.5 py-2.5 border border-[#CFC4A4] rounded-lg text-sm focus:outline-none focus:border-[#C9A227] focus:ring-2 focus:ring-[#F3E7C4] transition-shadow resize-none"></textarea>
    </div>

    <div id="bulkPulloutAssetSummary" class="p-3.5 bg-[#F5F0E2] border border-[#DED2AE] rounded-lg text-sm text-[#33425C]"></div>

    <div class="flex justify-end space-x-3 pt-2 border-t border-[#EFE9D8]">
        <button type="button" onclick="closeBulkPulloutModal()"
            class="px-4 py-2.5 border border-[#CFC4A4] rounded-lg text-[#33425C] hover:bg-[#F5F0E2] transition-colors text-sm font-medium">Cancel</button>
        <button type="submit" class="px-4 py-2.5 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition-colors text-sm font-medium shadow-sm">Submit Pullout</button>
    </div>
</form>
        </div>
    </div>

  <!-- Asset Details Modal -->
<div id="assetModal" class="hidden fixed inset-0 bg-[#0A1830]/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-5 border-b border-[#EFE9D8] flex justify-between items-center">
            <h3 class="text-lg font-bold text-[#0F2143]">Asset Details</h3>
            <button type="button" onclick="closeAssetModal()" class="text-[#8991A0] hover:text-[#33425C] hover:bg-[#EFE9D8] w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="flex mb-6 pb-6 border-b border-[#EFE9D8]">
                <div class="w-16 h-16 bg-[#F3E7C4] rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="ri-computer-line text-2xl text-[#A8841E]"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-lg font-bold text-[#0F2143]" id="modalAssetName">—</h4>
                    <p class="text-xs text-[#8991A0] font-mono mt-0.5" id="modalAssetId">—</p>
                    <span id="modalStatus" class="inline-flex items-center px-2.5 py-1 mt-2 rounded-full text-xs font-medium bg-[#EAF4EE] text-[#245C3B] ring-1 ring-inset ring-[#CFE3D4]">
                        Active
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div class="detail-field">
                    <p>Category</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalCategory">—</p>
                </div>
                <div class="detail-field">
                    <p>Accountable</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalAccountable">—</p>
                </div>
                <div class="detail-field">
                    <p>Date Acquired</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalDate">—</p>
                </div>
                <div class="detail-field">
                    <p>Location</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalLocation">—</p>
                </div>
                <div class="detail-field">
                    <p>Serial Number</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalSerial">—</p>
                </div>
                <div class="detail-field">
                    <p>Purchase Price</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalPurchasePrice">—</p>
                </div>
                <div class="detail-field">
                    <p>Warranty</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalWarranty">—</p>
                </div>
                <div class="detail-field">
                    <p>Condition</p>
                    <p class="text-sm font-medium text-[#0F2143]" id="modalCondition">—</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="hidden fixed inset-0 bg-[#0A1830]/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4">
        <div class="px-6 py-5 border-b border-[#EFE9D8] flex justify-between items-center">
            <h3 class="text-lg font-bold text-[#0F2143]">Asset QR Code</h3>
            <button type="button" onclick="closeQRModal()" class="text-[#8991A0] hover:text-[#33425C] hover:bg-[#EFE9D8] w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="p-6 flex flex-col items-center">
            <p class="text-sm text-[#5B6678] mb-4 font-mono" id="qrModalAssetId"></p>
            <img id="qrModalImg" class="hidden max-w-[220px] rounded-lg border border-[#DED2AE] p-2" alt="QR Code">
            <div id="qrModalCanvas" class="flex items-center justify-center" style="display:none;"></div>
            <div class="flex gap-3 mt-6 w-full">
                <button type="button" onclick="downloadQRFromModal()"
                    class="flex-1 px-4 py-2.5 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition-colors text-sm font-medium shadow-sm">
                    <i class="ri-download-line mr-1.5"></i> Download
                </button>
                <button type="button" onclick="printQRFromModal()"
                    class="flex-1 px-4 py-2.5 border border-[#CFC4A4] rounded-lg text-[#33425C] hover:bg-[#F5F0E2] transition-colors text-sm font-medium">
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