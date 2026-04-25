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
        <!-- Sidebar -->
        <div class="w-64 bg-gray-900 text-white flex flex-col overflow-y-auto">
            <div class="p-6 border-b border-gray-800">
                <h1 class="text-2xl font-bold flex items-center">
                    <i class="ri-dashboard-line mr-2"></i>
                    Dashboard
                </h1>
            </div>

            <div class="p-4 border-b border-gray-800">
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                    <input type="text" id="searchDepartments" placeholder="Search departments..." 
                           class="w-full pl-9 pr-3 py-2 bg-gray-800 rounded-lg text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <nav class="flex-1 py-4">
                <div class="px-4 mb-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-3">MAIN</p>
                    <a href="#" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-dashboard-line mr-3 text-lg"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="sidebar-item active flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-database-line mr-3 text-lg"></i>
                        <span>Assets</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-mail-line mr-3 text-lg"></i>
                        <span>Requests</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-delete-bin-line mr-3 text-lg"></i>
                        <span>Disposal</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-logout-box-r-line mr-3 text-lg"></i>
                        <span>Pullout</span>
                    </a>
                </div>
            </nav>

            <div class="border-t border-gray-800 p-4 mt-auto">
                <div class="flex items-center mb-3 p-2 rounded-lg bg-gray-800">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-white font-semibold text-sm">AO</span>
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Asset Officer</p>
                        <p class="text-xs text-gray-400 truncate">admin@university.edu</p>
                    </div>
                    <i class="ri-settings-3-line text-gray-400 cursor-pointer hover:text-white text-sm"></i>
                </div>
                <a href="#" class="flex items-center px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-gray-800 transition">
                    <i class="ri-logout-box-line mr-3 text-lg"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

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
                        <a href="/admin/assets/registry" class="inline-flex bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center transition-all hover:scale-105">
                            <i class="ri-add-line mr-2"></i>
                            Add New Asset
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Department Cards -->
                <div class="space-y-4" id="departmentsContainer">
                    @foreach($departments as $dept)
                    <div class="department-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" data-department="{{ strtolower($dept->name) }}">
                        <!-- Department Header - Clickable -->
                        <div class="p-6 cursor-pointer" onclick="toggleDepartment({{ $dept->id }})">
                            <div class="flex justify-between items-center">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900">{{ $dept->name }}</h3>
                                            <p class="text-sm text-gray-500 mt-1">{{ $dept->head }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-bold text-blue-600">{{ $dept->total_assets }}</p>
                                            <p class="text-xs text-gray-500">Total Assets</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Expand/Collapse Icon -->
                                <div class="ml-4">
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
                                    $forRepair = 0;
                                    $pulledOut = 0;
                                    $disposed = 0;
                                    
                                    foreach($dept->assets as $asset) {
                                        switch($asset->status) {
                                            case 'acquired': $acquired++; break;
                                            case 'active': $active++; break;
                                            case 'for_repair': $forRepair++; break;
                                            case 'pulled_out': $pulledOut++; break;
                                            case 'disposed': $disposed++; break;
                                        }
                                    }
                                @endphp
                                
                                <div class="grid grid-cols-5 gap-3 mb-6">
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Acquired</p>
                                        <p class="text-xl font-bold text-yellow-600">{{ $acquired }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($acquired / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Active</p>
                                        <p class="text-xl font-bold text-green-600">{{ $active }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($active / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">For Repair</p>
                                        <p class="text-xl font-bold text-red-600">{{ $forRepair }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($forRepair / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Pulled Out</p>
                                        <p class="text-xl font-bold text-orange-600">{{ $pulledOut }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($pulledOut / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Disposed</p>
                                        <p class="text-xl font-bold text-gray-600">{{ $disposed }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($disposed / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                </div>
                                
                                <!-- View All Assets Button -->
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-semibold text-gray-900">Assets List</h4>
                                    <button class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                        View All Assets
                                        <i class="ri-arrow-right-line ml-1"></i>
                                    </button>
                                </div>
                                
                                @if(count($dept->assets) > 0)
                                    <!-- Assets Table -->
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead>
                                                <tr class="border-b border-gray-200">
                                                    <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase">Asset Name</th>
                                                    <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase">Asset Code</th>
                                                    <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                                    <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase">Assigned To</th>
                                                    <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase">Date Acquired</th>
                                                    <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($dept->assets as $asset)
                                                <tr class="asset-row border-b border-gray-100">
                                                    <td class="py-3 px-3 text-sm text-gray-900">{{ $asset->name }}</td>
                                                    <td class="py-3 px-3 text-sm text-gray-600 font-mono">
                                                        <a href="/admin/assets/{{ $asset->id }}" class="text-blue-600 hover:underline">{{ $asset->asset_code }}</a>
                                                    </td>
                                                    <td class="py-3 px-3">
                                                        <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                            @if($asset->status == 'acquired') bg-yellow-100 text-yellow-700
                                                            @elseif($asset->status == 'active') bg-green-100 text-green-700
                                                            @elseif($asset->status == 'for_repair') bg-red-100 text-red-700
                                                            @elseif($asset->status == 'pulled_out') bg-orange-100 text-orange-700
                                                            @else bg-gray-100 text-gray-700
                                                            @endif">
                                                            <i class="ri-circle-fill mr-1 text-xs"></i>
                                                            {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-3 text-sm text-gray-600">{{ $asset->assigned_to }}</td>
                                                    <td class="py-3 px-3 text-sm text-gray-600">{{ $asset->acquisition_date }}</td>
                                                    <td class="py-3 px-3">
                                                        <div class="flex items-center space-x-2">
                                                            <button class="text-blue-600 hover:text-blue-700" title="Edit">
                                                                <i class="ri-edit-line"></i>
                                                            </button>
                                                            <button class="text-red-600 hover:text-red-700" title="Delete">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                            <button class="text-gray-600 hover:text-gray-700" title="View QR">
                                                                <i class="ri-qr-code-line"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <!-- Empty State -->
                                    <div class="text-center py-8 bg-white rounded-lg border border-gray-200">
                                        <i class="ri-inbox-line text-4xl text-gray-300 mb-2 block"></i>
                                        <p class="text-gray-500">No assets found in this department</p>
                                        <button class="mt-3 text-blue-600 hover:text-blue-700 text-sm">
                                            + Add New Asset
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
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
        
        // Search functionality
        document.getElementById('searchDepartments').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.department-card');
            
            cards.forEach(card => {
                const departmentName = card.getAttribute('data-department');
                if (departmentName && departmentName.includes(searchTerm)) {
                    card.style.display = '';
                } else if (searchTerm === '') {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Open first department by default (IT)
        document.addEventListener('DOMContentLoaded', function() {
            const defaultDept = 1;
            const content = document.getElementById(`content-${defaultDept}`);
            const icon = document.getElementById(`icon-${defaultDept}`);
            if (content && icon) {
                content.classList.add('expanded');
                icon.classList.add('rotate-180');
            }
        });
    </script>
</body>
</html>