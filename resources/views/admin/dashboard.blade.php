<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard - Asset Management</title>
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
            padding-left: 1.5rem;
        }
        
        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }
        
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')
        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-blue-600 font-medium">Admin</span>
                                <span class="mx-2 text-gray-300">•</span>
                                <p class="text-sm text-gray-500">Overview of asset management system</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="relative cursor-pointer">
                                <i class="ri-notification-3-line text-xl text-gray-600"></i>
                                <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                            </div>
                            <div class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1">
                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-semibold">AD</span>
                                </div>
                                <i class="ri-arrow-down-s-line text-gray-500"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="p-8">
                <!-- Quick Summary -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900">Quick summary of key metrics</h3>
                    <p class="text-sm text-gray-500 mt-1">Real-time overview of your asset inventory</p>
                </div>

                <!-- Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Acquired this month -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Acquired this month</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($acquiredThisMonth ?? 1847) }}</p>
                                <p class="text-xs text-green-600 mt-2 flex items-center">
                                    <i class="ri-arrow-up-line mr-0.5"></i>
                                    12% from last month
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="ri-calendar-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Active Assets -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Active Assets</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($activeAssets ?? 2854) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Total active inventory</p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- For Repair -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">For Repair</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($forRepairAssets ?? 28) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Needs maintenance</p>
                            </div>
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="ri-tools-line text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Requests -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Pending Requests</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($pendingRequests ?? 15) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Awaiting approval</p>
                            </div>
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="ri-time-line text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Links</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="/admin/requests?tab=pending" class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-time-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Pending Requests</p>
                            <p class="text-xs text-gray-500 mt-1">View pending approvals</p>
                        </a>
                        <a href="/admin/assets/registry" class="bg-gradient-to-r from-green-50 to-green-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-database-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Asset Registry</p>
                            <p class="text-xs text-gray-500 mt-1">Register new assets</p>
                        </a>
                        <a href="/admin/disposal" class="bg-gradient-to-r from-red-50 to-red-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-delete-bin-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Record Disposal</p>
                            <p class="text-xs text-gray-500 mt-1">Log disposed assets</p>
                        </a>
                        <a href="/admin/pullout" class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-orange-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-logout-box-r-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Record Pullout</p>
                            <p class="text-xs text-gray-500 mt-1">Log pulled out assets</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                                <p class="text-sm text-gray-500 mt-1">Latest actions and updates</p>
                            </div>
                            <button class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                View All
                                <i class="ri-arrow-right-line ml-1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        @if(isset($recentActivities) && count($recentActivities) > 0)
                            <div class="space-y-4">
                                @foreach($recentActivities as $activity)
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="ri-file-copy-line text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $activity->type }}</p>
                                            <p class="text-sm text-gray-500">{{ $activity->description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400">{{ $activity->time }}</span>
                                        @if(isset($activity->status))
                                            <p class="text-xs mt-1">
                                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                                    @if($activity->status == 'pending') bg-orange-100 text-orange-700
                                                    @elseif($activity->status == 'approved') bg-green-100 text-green-700
                                                    @endif">
                                                    {{ ucfirst($activity->status) }}
                                                </span>
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <i class="ri-inbox-line text-5xl text-gray-300 mb-3 block"></i>
                                <p class="text-gray-500">No recent activities</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html>