<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>User Dashboard - Asset Management</title>
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
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .request-item {
            transition: all 0.2s ease;
        }
        
        .request-item:hover {
            background-color: #f9fafb;
            transform: translateX(4px);
        }
        
        .submit-btn {
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('department_head.partial.sidebar', ['currentUser' => $user])

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="flex items-center">
                                <h2 class="text-2xl font-bold text-gray-900">Welcome, {{ $user?->full_name ?? 'User' }}</h2>
                            </div>
                            <div class="flex items-center mt-1">
                                <span class="text-sm font-semibold text-gray-900">{{ $user?->unit_heads_number }} - {{ $user?->role ?? 'Employee' }}</span>
                                <span class="mx-2 text-gray-300">•</span>
                                @php
                                    $deptName = $user && $user->department_id ? \Illuminate\Support\Facades\DB::table('departments')->where('id', $user->department_id)->value('Name') : 'N/A';
                                @endphp
                                <span class="text-sm text-blue-600 font-medium">{{ $deptName }}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="relative cursor-pointer">
                                <i class="ri-notification-3-line text-xl text-gray-600"></i>
                                <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                            </div>
                            <div class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1">
                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-semibold">U</span>
                                </div>
                                <i class="ri-arrow-down-s-line text-gray-500"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Total Assets Section -->
                <div class="mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Total Assets</h3>
                                <p class="text-sm text-gray-500 mt-1">Your assigned equipment and devices</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="ri-computer-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-4xl font-bold text-gray-900">{{ $totalAssets ?? 0 }}</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <!-- Acquired -->
                    <div class="stat-card bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-500">Acquired</p>
                            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="ri-folder-received-line text-yellow-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['acquired']['count'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">({{ $stats['acquired']['percent'] ?? 0 }}%)</p>
                    </div>

                    <!-- Active -->
                    <div class="stat-card bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-500">Active</p>
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-green-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-green-600">{{ $stats['active']['count'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">({{ $stats['active']['percent'] ?? 0 }}%)</p>
                    </div>

                    <!-- For Repair -->
                    <div class="stat-card bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-500">For Repair</p>
                            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="ri-tools-line text-red-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-red-600">{{ $stats['for_repair']['count'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">({{ $stats['for_repair']['percent'] ?? 0 }}%)</p>
                    </div>

                    <!-- Pulled Out -->
                    <div class="stat-card bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-500">Pulled Out</p>
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="ri-logout-box-r-line text-orange-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-orange-600">{{ $stats['pulled_out']['count'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">({{ $stats['pulled_out']['percent'] ?? 0 }}%)</p>
                    </div>

                    <!-- Disposed -->
                    <div class="stat-card bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-500">Disposed</p>
                            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i class="ri-delete-bin-line text-gray-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-gray-600">{{ $stats['disposed']['count'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">({{ $stats['disposed']['percent'] ?? 0 }}%)</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mb-8">
                    <a href="{{ route('user.request-asset') }}" class="submit-btn bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-all inline-flex items-center shadow-md">
                        <i class="ri-add-line mr-2 text-lg"></i>
                        Submit Request
                    </a>
                </div>

                

                <!-- Recent Requests -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center p-6 border-b border-gray-200">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Recent Requests</h3>
                            <p class="text-sm text-gray-500 mt-1">Your latest request activities</p>
                        </div>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                            View All
                            <i class="ri-arrow-right-line ml-1"></i>
                        </a>
                    </div>
                    <div class="p-6">
                        @if(isset($recentRequests) && count($recentRequests) > 0)
                            <div class="space-y-3">
                                @foreach($recentRequests as $request)
                                <div class="request-item flex justify-between items-center p-3 rounded-lg border border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full 
                                            @if($request->type == 'new_asset') bg-blue-100
                                            @elseif($request->type == 'repair') bg-red-100
                                            @else bg-orange-100
                                            @endif flex items-center justify-center">
                                            <i class="
                                                @if($request->type == 'new_asset') ri-add-line text-blue-600
                                                @elseif($request->type == 'repair') ri-tools-line text-red-600
                                                @else ri-logout-box-r-line text-orange-600
                                                @endif"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $request->type)) }}</p>
                                            <p class="text-sm text-gray-500">{{ $request->description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400">{{ $request->created_at->diffForHumans() }}</span>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                @if($request->status == 'pending') bg-yellow-100 text-yellow-700
                                                @elseif($request->status == 'approved') bg-green-100 text-green-700
                                                @else bg-red-100 text-red-700
                                                @endif">
                                                {{ ucfirst($request->status) }}
                                            </span>
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
                                <p class="text-gray-500">No recent requests</p>
                                <p class="text-xs text-gray-400 mt-1">Submit a request to get started</p>
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

    <!-- Department Notifications Polling + Toast -->
    <script>
        (function () {
            const endpoint = '/api/notifications/department';
            const storageKey = 'repairStatuses_dept';

            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `toast fixed bottom-6 right-6 px-5 py-3 rounded-lg text-white shadow-lg z-50 ${type === 'error' ? 'bg-red-600' : 'bg-blue-600'}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => { toast.remove(); }, 7000);
            }

            async function fetchNotifications() {
                try {
                    const res = await fetch(endpoint, { credentials: 'same-origin' });
                    if (!res.ok) return;
                    const data = await res.json();
                    const repairs = data.repairs || [];
                    const stored = JSON.parse(localStorage.getItem(storageKey) || '{}');

                    repairs.forEach(r => {
                        const prev = stored[r.id];
                        if (prev && prev !== r.status) {
                            showToast(`Request #REQ-${String(r.id).padStart(4,'0')} (${r.Asset_name || 'asset'}) by ${r.submitted_by || 'user'}: status changed to ${r.status}`);
                        }
                        stored[r.id] = r.status;
                    });

                    localStorage.setItem(storageKey, JSON.stringify(stored));
                } catch (e) {
                    // ignore
                }
            }

            fetchNotifications();
            setInterval(fetchNotifications, 30000);
        })();
    </script>

</body>
</html>