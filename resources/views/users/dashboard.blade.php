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
            background-color: #142B4D;
        }
        
        .sidebar-item.active {
            background-color: #0B1B33;
            color: #E8C874;
            border-right: 3px solid #C9A227;
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(11, 27, 51, 0.14);
            border-color: rgba(201, 162, 39, 0.35);
        }
        
        .request-item {
            transition: all 0.2s ease;
        }
        
        .request-item:hover {
            background-color: #f9fafb;
            transform: translateX(4px);
            border-color: rgba(201, 162, 39, 0.35);
        }
        
        .submit-btn {
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201, 162, 39, 0.35);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('users.partials.sidebar', ['currentUser' => $user])

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            @include('layouts.user_header', [
                'title' => 'Welcome, ' . (
                    $user?->full_name
                    ?? optional($user?->employee_numbers)->Full_Name
                    ?? 'User'
                ),
                'subtitle' => 'Overview of your assigned assets and requests',
            ])

            <!-- Content -->
            <div class="p-8">
                <!-- Total Assets Section -->
                <div class="mb-8">
                    <div class="bg-gradient-to-br from-[#0B1B33] to-[#1C3A63] rounded-xl shadow-lg p-6 text-white">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg font-semibold">Total Assets</h3>
                                <p class="text-sm text-white/70 mt-1">Your assigned equipment and devices</p>
                            </div>
                            <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center">
                                <i class="ri-computer-line text-[#E8C874] text-xl"></i>
                            </div>
                        </div>
                        <p class="text-4xl font-bold">{{ $totalAssets ?? 0 }}</p>
                    </div>
                </div>

                <!-- Stats Cards (lifecycle status colors — unchanged) -->
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
                    <a href="{{ route('user.request-asset') }}" class="submit-btn bg-[#C9A227] text-[#0B1B33] px-6 py-3 rounded-lg hover:bg-[#E8C874] transition-all inline-flex items-center shadow-md font-semibold">
                        <i class="ri-add-line mr-2 text-lg"></i>
                        Submit Request
                    </a>
                </div>

                

                <!-- Recent Requests -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center p-6 border-b border-gray-200">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0B1B33]">Recent Requests</h3>
                            <p class="text-sm text-gray-500 mt-1">Your latest request activities</p>
                        </div>
                            <a href="/user/requests"
                            class="text-[#0B1B33] hover:text-[#C9A227] text-sm font-medium flex items-center transition">
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
                                            <p class="font-medium text-[#0B1B33]">{{ ucfirst(str_replace('_', ' ', $request->type)) }}</p>
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
                                <div class="w-16 h-16 bg-[#0B1B33]/5 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="ri-inbox-line text-2xl text-[#C9A227]"></i>
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

    <!-- Notifications Polling + Toast -->
    <script>
        (function () {
            const endpoint = '/api/notifications/user';
            const storageKey = 'repairStatuses_user';

            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `toast fixed bottom-6 right-6 px-5 py-3 rounded-lg text-white shadow-lg z-50 ${type === 'error' ? 'bg-red-600' : 'bg-[#0B1B33]'}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => { toast.remove(); }, 6000);
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
                            showToast(`Request #REQ-${String(r.id).padStart(4,'0')} for ${r.Asset_name || 'asset'} status changed: ${r.status}`);
                        }
                        // update stored status
                        stored[r.id] = r.status;
                    });

                    localStorage.setItem(storageKey, JSON.stringify(stored));
                } catch (e) {
                    // ignore network errors
                }
            }

            // initial fetch and polling
            fetchNotifications();
            setInterval(fetchNotifications, 30000);
        })();
    </script>

</body>
</html>