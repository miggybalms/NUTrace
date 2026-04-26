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

                                <div class="mb-4">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Asset Lifecycle Distribution</p>
                                    <div class="h-3 w-full rounded-full bg-gray-100 overflow-hidden flex">
                                        @if($dept->total_assets > 0)
                                            @if($acquired > 0)
                                                <div style="width: {{ round(($acquired / $dept->total_assets) * 100, 1) }}%; background-color: #3b82f6;"></div>
                                            @endif
                                            @if($active > 0)
                                                <div style="width: {{ round(($active / $dept->total_assets) * 100, 1) }}%; background-color: #22c55e;"></div>
                                            @endif
                                            @if($forRepair > 0)
                                                <div style="width: {{ round(($forRepair / $dept->total_assets) * 100, 1) }}%; background-color: #f59e0b;"></div>
                                            @endif
                                            @if($pulledOut > 0)
                                                <div style="width: {{ round(($pulledOut / $dept->total_assets) * 100, 1) }}%; background-color: #94a3b8;"></div>
                                            @endif
                                            @if($disposed > 0)
                                                <div style="width: {{ round(($disposed / $dept->total_assets) * 100, 1) }}%; background-color: #ef4444;"></div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-5 gap-3 mb-6">
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Acquired</p>
                                        <p class="text-xl font-bold text-blue-600">{{ $acquired }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($acquired / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Active</p>
                                        <p class="text-xl font-bold text-green-600">{{ $active }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($active / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">For Repair</p>
                                        <p class="text-xl font-bold text-amber-500">{{ $forRepair }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($forRepair / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Pulled Out</p>
                                        <p class="text-xl font-bold text-slate-500">{{ $pulledOut }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($pulledOut / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500">Disposed</p>
                                        <p class="text-xl font-bold text-red-500">{{ $disposed }}</p>
                                        <p class="text-xs text-gray-400">({{ $dept->total_assets > 0 ? round(($disposed / $dept->total_assets) * 100) : 0 }}%)</p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <a href="{{ route('admin.assets.department', ['department' => $dept->name]) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                        View All Assets
                                        <i class="ri-arrow-right-line ml-1"></i>
                                    </a>
                                </div>
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