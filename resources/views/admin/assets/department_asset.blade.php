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
            transition: background-color 0.2s ease;
        }
        
        .asset-row:hover {
            background-color: #f9fafb;
        }
        
        .filter-chip {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .filter-chip.active {
            background-color: #3b82f6;
            color: white;
        }
        
        .filter-chip.active:hover {
            background-color: #2563eb;
        }
        
        .filter-chip:hover:not(.active) {
            background-color: #e5e7eb;
        }
        
        .action-btn {
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
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
                                <p class="text-sm text-gray-500">Manage and track all university assets</p>
                            </div>
                        </div>
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center transition-all hover:scale-105">
                            <i class="ri-add-line mr-2"></i>
                            Add New Asset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Search and Filter Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Search Box -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <div class="relative">
                                <i class="ri-search-line absolute left-3 top-2.5 text-gray-400"></i>
                                <input type="text" id="searchAssets" placeholder="Search assets..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                            </div>
                        </div>
                        
                        <!-- Categories Dropdown -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Categories</label>
                            <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                                <option value="all">All Categories</option>
                                @foreach(($categoryOptions ?? []) as $categoryOption)
                                    <option value="{{ $categoryOption['value'] }}">{{ $categoryOption['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filter Chips -->
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button class="filter-chip active px-3 py-1 rounded-full text-sm bg-blue-600 text-white" data-filter="all">
                            All
                        </button>
                        <button class="filter-chip px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-gray-200" data-filter="active">
                            Active
                        </button>
                        <button class="filter-chip px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-gray-200" data-filter="acquired">
                            Acquired
                        </button>
                        <button class="filter-chip px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-gray-200" data-filter="for_repair">
                            For Repair
                        </button>
                        <button class="filter-chip px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-gray-200" data-filter="pulled_out">
                            Pulled Out
                        </button>
                        <button class="filter-chip px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-gray-200" data-filter="disposed">
                            Disposed
                        </button>
                        <button class="filter-chip px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-gray-200" data-filter="reset" onclick="resetFilters()">
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Assets Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-900">Assets in {{ $departmentName ?? 'Department' }}</h3>
                        <p class="text-sm text-gray-500 mt-1">Showing all assets from {{ $departmentName ?? 'selected' }} Department</p>
                    </div>
                    
                    <div class="table-container overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">ASSET ID</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">ASSET NAME</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">CATEGORY</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">ACCOUNTABLE</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">DATE ACQUIRED</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">CURRENT LOCATION</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">LIFECYCLE STAGE</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="assetsTableBody">
                                @forelse($assets ?? [] as $asset)
                                <tr class="asset-row border-b border-gray-100 hover:bg-gray-50 transition" data-asset-id="{{ $asset->id }}" data-category="{{ $asset->category_code }}" data-status="{{ $asset->status }}" data-name="{{ strtolower($asset->name) }}" data-id="{{ strtolower($asset->id) }}">
                                    <td class="py-3 px-4 text-sm font-mono text-gray-900">{{ $asset->id }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
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
                                            <span class="text-sm font-medium text-gray-900">{{ $asset->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ $asset->category }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ $asset->accountable }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ $asset->date_acquired }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ $asset->location }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            @if($asset->status == 'active') bg-green-100 text-green-700
                                            @elseif($asset->status == 'acquired') bg-yellow-100 text-yellow-700
                                            @elseif($asset->status == 'for_repair') bg-red-100 text-red-700
                                            @elseif($asset->status == 'pulled_out') bg-orange-100 text-orange-700
                                            @else bg-gray-100 text-gray-700
                                            @endif">
                                            <i class="ri-circle-fill mr-1 text-xs"></i>
                                            {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-3">
                                            <button class="action-btn text-blue-600 hover:text-blue-700" title="View Details">
                                                <i class="ri-eye-line text-lg"></i>
                                            </button>
                                            <button class="action-btn text-green-600 hover:text-green-700" title="Edit Asset">
                                                <i class="ri-edit-line text-lg"></i>
                                            </button>
                                            <button class="action-btn text-purple-600 hover:text-purple-700" title="View QR Code">
                                                <i class="ri-qr-code-line text-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-gray-500">
                                        <i class="ri-inbox-line text-4xl text-gray-300 mb-2 block"></i>
                                        No assets found for this department.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                        <p class="text-sm text-gray-500">Showing <span id="showingCount">{{ count($assets ?? []) }}</span> of <span id="totalCount">{{ count($assets ?? []) }}</span> assets</p>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 disabled:opacity-50" id="prevPage" disabled>
                                <i class="ri-arrow-left-s-line"></i>
                            </button>
                            <button class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700" id="page1">1</button>
                            <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50" id="nextPage">
                                <i class="ri-arrow-right-s-line"></i>
                            </button>
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

    <!-- Asset Details Modal -->
    <div id="assetModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Asset Details</h3>
                    <button onclick="closeAssetModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="flex mb-6">
                    <div class="w-24 h-24 bg-gradient-to-r from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                        <i class="ri-computer-line text-4xl text-white"></i>
                    </div>
                    <div class="ml-6 flex-1">
                        <h4 class="text-xl font-bold text-gray-900" id="modalAssetName">Dell XPS 15</h4>
                        <p class="text-sm text-gray-500 font-mono" id="modalAssetId">AST-001</p>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700" id="modalStatus">
                                <i class="ri-circle-fill mr-1 text-xs"></i>
                                Active
                            </span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500">Category</p>
                        <p class="text-sm font-medium text-gray-900" id="modalCategory">Computer & Laptops</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Accountable Person</p>
                        <p class="text-sm font-medium text-gray-900" id="modalAccountable">user user</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Date Acquired</p>
                        <p class="text-sm font-medium text-gray-900" id="modalDate">Jan 15, 2026</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Current Location</p>
                        <p class="text-sm font-medium text-gray-900" id="modalLocation">IT Department, Room 301</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Serial Number</p>
                        <p class="text-sm font-medium text-gray-900">SN123456789</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Purchase Price</p>
                        <p class="text-sm font-medium text-gray-900">$1,500.00</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Warranty</p>
                        <p class="text-sm font-medium text-gray-900">12 months remaining</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Condition</p>
                        <p class="text-sm font-medium text-green-600">Good</p>
                    </div>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-xs text-gray-500 mb-2">Notes</p>
                    <p class="text-sm text-gray-700">Development laptop with 32GB RAM, 1TB SSD.</p>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button onclick="closeAssetModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Edit Asset</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search and Filter functionality
        const searchInput = document.getElementById('searchAssets');
        const categoryFilter = document.getElementById('categoryFilter');
        const filterChips = document.querySelectorAll('.filter-chip');
        const tableRows = document.querySelectorAll('#assetsTableBody tr');
        const showingCountSpan = document.getElementById('showingCount');
        const totalCountSpan = document.getElementById('totalCount');
        
        let currentStatusFilter = 'all';
        
        function filterAssets() {
            const searchTerm = searchInput.value.toLowerCase();
            const categoryValue = categoryFilter.value;
            
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                const assetName = row.getAttribute('data-name') || '';
                const assetId = row.getAttribute('data-id') || '';
                const assetCategory = row.getAttribute('data-category') || '';
                const assetStatus = row.getAttribute('data-status') || '';
                
                const matchesSearch = assetName.includes(searchTerm) || assetId.includes(searchTerm);
                const matchesCategory = categoryValue === 'all' || assetCategory === categoryValue;
                const matchesStatus = currentStatusFilter === 'all' || assetStatus === currentStatusFilter;
                
                if (matchesSearch && matchesCategory && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            showingCountSpan.textContent = visibleCount;
            totalCountSpan.textContent = tableRows.length;
        }
        
        // Search input event
        searchInput.addEventListener('input', filterAssets);
        
        // Category filter event
        categoryFilter.addEventListener('change', filterAssets);
        
        // Filter chips
        filterChips.forEach(chip => {
            chip.addEventListener('click', function() {
                const filterValue = this.getAttribute('data-filter');
                
                if (filterValue === 'reset') {
                    // Reset all filters
                    searchInput.value = '';
                    categoryFilter.value = 'all';
                    currentStatusFilter = 'all';
                    
                    // Update chip active states
                    filterChips.forEach(c => {
                        if (c.getAttribute('data-filter') === 'all') {
                            c.classList.add('active', 'bg-blue-600', 'text-white');
                            c.classList.remove('bg-gray-100', 'text-gray-700');
                        } else {
                            c.classList.remove('active', 'bg-blue-600', 'text-white');
                            c.classList.add('bg-gray-100', 'text-gray-700');
                        }
                    });
                } else {
                    // Update active chip
                    filterChips.forEach(c => {
                        if (c.getAttribute('data-filter') === filterValue) {
                            c.classList.add('active', 'bg-blue-600', 'text-white');
                            c.classList.remove('bg-gray-100', 'text-gray-700');
                        } else if (c.getAttribute('data-filter') !== 'reset') {
                            c.classList.remove('active', 'bg-blue-600', 'text-white');
                            c.classList.add('bg-gray-100', 'text-gray-700');
                        }
                    });
                    
                    currentStatusFilter = filterValue;
                }
                
                filterAssets();
            });
        });
        
        // Modal functions
        function openAssetModal(assetId) {
            // Find the row and get data
            const row = document.querySelector(`tr[data-asset-id="${assetId}"]`);
            if (row) {
                const name = row.querySelector('td:nth-child(2) span')?.textContent || 'Asset';
                const assetIdValue = row.querySelector('td:first-child')?.textContent || '';
                const category = row.querySelector('td:nth-child(3)')?.textContent || '';
                const accountable = row.querySelector('td:nth-child(4)')?.textContent || '';
                const date = row.querySelector('td:nth-child(5)')?.textContent || '';
                const location = row.querySelector('td:nth-child(6)')?.textContent || '';
                const statusSpan = row.querySelector('td:nth-child(7) span');
                const status = statusSpan?.textContent?.trim() || 'Active';
                
                document.getElementById('modalAssetName').textContent = name;
                document.getElementById('modalAssetId').textContent = assetIdValue;
                document.getElementById('modalCategory').textContent = category;
                document.getElementById('modalAccountable').textContent = accountable;
                document.getElementById('modalDate').textContent = date;
                document.getElementById('modalLocation').textContent = location;
                
                // Update status badge
                const statusBadge = document.getElementById('modalStatus');
                if (status.toLowerCase().includes('active')) {
                    statusBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700';
                } else if (status.toLowerCase().includes('pending')) {
                    statusBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700';
                } else {
                    statusBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700';
                }
                statusBadge.innerHTML = `<i class="ri-circle-fill mr-1 text-xs"></i>${status}`;
            }
            
            document.getElementById('assetModal').classList.remove('hidden');
            document.getElementById('assetModal').classList.add('flex');
        }
        
        function closeAssetModal() {
            document.getElementById('assetModal').classList.add('hidden');
            document.getElementById('assetModal').classList.remove('flex');
        }
        
        function resetFilters() {
            searchInput.value = '';
            categoryFilter.value = 'all';
            currentStatusFilter = 'all';
            
            filterChips.forEach(c => {
                if (c.getAttribute('data-filter') === 'all') {
                    c.classList.add('active', 'bg-blue-600', 'text-white');
                    c.classList.remove('bg-gray-100', 'text-gray-700');
                } else if (c.getAttribute('data-filter') !== 'reset') {
                    c.classList.remove('active', 'bg-blue-600', 'text-white');
                    c.classList.add('bg-gray-100', 'text-gray-700');
                }
            });
            
            filterAssets();
        }
        
        // Attach click handlers to view buttons
        document.querySelectorAll('.action-btn[title="View Details"]').forEach((btn, index) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const assetId = row.getAttribute('data-asset-id');
                openAssetModal(assetId);
            });
        });
    </script>
</body>
</html>