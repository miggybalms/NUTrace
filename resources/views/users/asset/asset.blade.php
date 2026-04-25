<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Assets - User Dashboard</title>
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
        
        .asset-card {
            transition: all 0.3s ease;
        }
        
        .asset-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
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
        
        .filter-btn.active:hover {
            background-color: #2563eb;
        }
        
        .qr-hover:hover .qr-tooltip {
            display: block;
        }
        
        .view-details {
            transition: all 0.2s ease;
        }
        
        .view-details:hover {
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <h1 class="text-xl font-bold text-gray-800">Dashboard</h1>
            </div>

            <nav class="flex-1 py-6">
                <div class="px-4 space-y-1">
                    <a href="#" class="sidebar-item flex items-center px-4 py-3 text-gray-700 rounded-lg">
                        <i class="ri-database-line mr-3 text-lg"></i>
                        <span>Assets</span>
                    </a>
                    <a href="#" class="sidebar-item active flex items-center px-4 py-3 text-gray-700 rounded-lg">
                        <i class="ri-computer-line mr-3 text-lg"></i>
                        <span>My Assets</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-50">
                        <i class="ri-qr-code-line mr-3 text-lg"></i>
                        <span>QR Scanner</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-50">
                        <i class="ri-mail-line mr-3 text-lg"></i>
                        <span>Requests</span>
                    </a>
                </div>
            </nav>

            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                        <i class="ri-user-line text-gray-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-700">John Doe</p>
                        <p class="text-xs text-gray-500">john.doe@university.edu</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">My Assets</h2>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-blue-600 font-medium">John Doe</span>
                                <span class="mx-2 text-gray-300">•</span>
                                <p class="text-sm text-gray-500">View and manage your assigned assets</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition flex items-center">
                                <i class="ri-download-line mr-2"></i>
                                Export List
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Total Assets</p>
                                <p class="text-3xl font-bold mt-2">4</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-computer-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Active Assets</p>
                                <p class="text-3xl font-bold mt-2">4</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Pending Requests</p>
                                <p class="text-3xl font-bold mt-2">0</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-time-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90">Total Value</p>
                                <p class="text-3xl font-bold mt-2">$1,875</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="ri-money-dollar-circle-line text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6">
                    <div class="flex space-x-2 border-b border-gray-200">
                        <button class="filter-btn active px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600">
                            All Assets
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                            Active
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                            For Repair
                        </button>
                        <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                            Recently Added
                        </button>
                    </div>
                </div>

                <!-- Assets Grid -->
                @if(isset($assignedAssets) && count($assignedAssets) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($assignedAssets as $asset)
                    <div class="asset-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="relative h-40 bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center">
                            <i class="ri-computer-line text-6xl text-white opacity-80"></i>
                            <div class="absolute top-3 right-3">
                                <a href="/users/assets/{{ $asset->id }}" class="text-white text-xl">
                                    <i class="ri-qr-code-line cursor-pointer hover:scale-110 transition"></i>
                                </a>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-lg">{{ $asset->Asset_name ?? 'Untitled' }}</h3>
                                    <p class="text-xs text-gray-500 font-mono mt-1">{{ $asset->Asset_code ?? '' }}</p>
                                </div>
                                <span class="status-badge px-2 py-1 rounded-full text-xs font-medium @if(($asset->Lifecycle_Status ?? '') == 'Active') bg-green-100 text-green-700 @else bg-gray-100 text-gray-700 @endif">
                                    {{ $asset->Lifecycle_Status ?? 'Unknown' }}
                                </span>
                            </div>
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center text-sm">
                                    <i class="ri-user-line text-gray-400 mr-2"></i>
                                    <span class="text-gray-600">Assigned to: <span class="font-medium text-gray-900">{{ $asset->user?->full_name ?? 'Unassigned' }}</span></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="ri-calendar-line text-gray-400 mr-2"></i>
                                    <span class="text-gray-600">Acquired: <span class="font-medium text-gray-900">{{ $asset->accusion_date ?? '—' }}</span></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="ri-money-dollar-circle-line text-gray-400 mr-2"></i>
                                    <span class="text-gray-600">Value: <span class="font-medium text-gray-900">{{ $asset->purchase_Price ? '$' . number_format($asset->purchase_Price, 2) : '—' }}</span></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="ri-map-pin-line text-gray-400 mr-2"></i>
                                    <span class="text-gray-600">Location: <span class="font-medium text-gray-900">{{ $asset->asset_location ?? '—' }}</span></span>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <a href="/users/assets/{{ $asset->id }}" class="view-details flex-1 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-blue-600 hover:text-white transition flex items-center justify-center">
                                    <i class="ri-eye-line mr-1"></i>
                                    View Details
                                </a>
                                <button class="px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition">
                                    <i class="ri-tools-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="ri-inbox-line text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500">No assigned assets to display.</p>
                </div>
                @endif

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
                    <div class="w-32 h-32 bg-gradient-to-r from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                        <i class="ri-computer-line text-5xl text-white"></i>
                    </div>
                    <div class="ml-6 flex-1">
                        <h4 class="text-xl font-bold text-gray-900">Dell XPS 15 Laptop</h4>
                        <p class="text-sm text-gray-500 font-mono">AST-001</p>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <i class="ri-checkbox-circle-fill mr-1 text-xs"></i>
                                Active
                            </span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-gray-500">Serial Number</p>
                        <p class="text-sm font-medium text-gray-900">SN123456789</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Category</p>
                        <p class="text-sm font-medium text-gray-900">Computer & Laptops</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Assigned To</p>
                        <p class="text-sm font-medium text-gray-900">John Doe (IT Department)</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Location</p>
                        <p class="text-sm font-medium text-gray-900">Room 301, Engineering Building</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Date Acquired</p>
                        <p class="text-sm font-medium text-gray-900">January 15, 2026</p>
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
                    <p class="text-sm text-gray-700">Development laptop with 32GB RAM, 1TB SSD. Used for software development and testing.</p>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button onclick="closeAssetModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
                    <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Report Issue</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAssetModal() {
            document.getElementById('assetModal').classList.remove('hidden');
            document.getElementById('assetModal').classList.add('flex');
        }
        
        function closeAssetModal() {
            document.getElementById('assetModal').classList.add('hidden');
            document.getElementById('assetModal').classList.remove('flex');
        }
        
        // Attach click handlers to view details buttons
        document.querySelectorAll('.view-details').forEach(btn => {
            btn.addEventListener('click', openAssetModal);
        });
        
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
                    b.classList.add('text-gray-500');
                });
                this.classList.add('active');
                this.classList.remove('text-gray-500');
                this.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
            });
        });
    </script>
</body>
</html>