@extends('layouts.admin_sidebar')

@section('title', 'Audit Logs')

@section('content')

    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="px-8 py-5">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Audit Logs</h2>
                    <p class="text-sm text-gray-500 mt-1">Track all system activities and actions</p>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Search -->
                    <div class="relative">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="searchInput" placeholder="Search logs..."
                            class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 w-56"/>
                    </div>
                    <!-- Date Filter -->
                    <input type="date" id="dateFilter"
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 text-gray-600"/>
                    <!-- Export -->
                    <button onclick="exportLogs()"
                        class="flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        <i class="ri-download-line mr-2"></i>
                        Export
                    </button>
                    <!-- Notification -->
                    <div class="relative cursor-pointer">
                        <i class="ri-notification-3-line text-xl text-gray-600"></i>
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                    </div>
                    <!-- Profile -->
                    <div class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-semibold">
                                {{ strtoupper(substr(Auth::user()->full_name ?? 'A', 0, 1)) }}
                            </span>
                        </div>
                        <i class="ri-arrow-down-s-line text-gray-500"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Total Logs</p>
                    <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="ri-file-list-line text-blue-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ $totalLogs ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Today's Activity</p>
                    <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="ri-calendar-check-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-green-600">{{ $todayLogs ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">This Week</p>
                    <div class="w-9 h-9 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="ri-bar-chart-line text-purple-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-purple-600">{{ $weekLogs ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Active Users</p>
                    <div class="w-9 h-9 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="ri-user-line text-orange-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-orange-600">{{ $activeUsers ?? 0 }}</p>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

            <!-- Tabs -->
            <div class="flex items-center justify-between px-6 pt-5 pb-0 border-b border-gray-100">
                <div class="flex space-x-1">
                    <button class="filter-tab active px-4 py-2.5 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-filter="all">
                        All
                    </button>
                    <button class="filter-tab px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="asset">
                        Assets
                    </button>
                    <button class="filter-tab px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="request">
                        Requests
                    </button>
                    <button class="filter-tab px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="auth">
                        Auth
                    </button>
                </div>
                <p class="text-sm text-gray-400 pb-3">{{ $logs->count() ?? 0 }} records</p>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="logsTable">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <th class="px-6 py-4 text-left font-semibold">User</th>
                            <th class="px-6 py-4 text-left font-semibold">Action</th>
                            <th class="px-6 py-4 text-left font-semibold">Asset</th>
                            <th class="px-6 py-4 text-left font-semibold">Request</th>
                            <th class="px-6 py-4 text-left font-semibold">Date & Time</th>
                            <th class="px-6 py-4 text-left font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="logsTableBody">

                        @forelse($logs ?? [] as $log)
                        <tr class="log-row hover:bg-gray-50 transition"
                            data-type="{{ str_contains(strtolower($log->notes ?? ''), 'asset') ? 'asset' : (str_contains(strtolower($log->notes ?? ''), 'request') ? 'request' : (str_contains(strtolower($log->notes ?? ''), 'login') ? 'auth' : 'all')) }}"
                            data-date="{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d') }}">

                            {{-- User --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-xs font-semibold">
                                            {{ strtoupper(substr($log->user_name ?? ($log->user->full_name ?? 'U'), 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $log->user_name ?? ($log->user->full_name ?? '—') }}</p>
                                        <p class="text-xs text-gray-400">{{ $log->user_role ?? ($log->user->role ?? '—') }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Action / Notes --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    {{-- Action icon based on note content --}}
                                    @php
                                        $note = strtolower($log->notes ?? '');
                                        $icon = 'ri-information-line text-gray-500';
                                        $bg = 'bg-gray-100';
                                        if (str_contains($note, 'approved')) { $icon = 'ri-checkbox-circle-line text-green-600'; $bg = 'bg-green-100'; }
                                        elseif (str_contains($note, 'rejected')) { $icon = 'ri-close-circle-line text-red-600'; $bg = 'bg-red-100'; }
                                        elseif (str_contains($note, 'created') || str_contains($note, 'registered')) { $icon = 'ri-add-circle-line text-blue-600'; $bg = 'bg-blue-100'; }
                                        elseif (str_contains($note, 'deleted') || str_contains($note, 'disposed')) { $icon = 'ri-delete-bin-line text-red-600'; $bg = 'bg-red-100'; }
                                        elseif (str_contains($note, 'updated') || str_contains($note, 'edited')) { $icon = 'ri-edit-line text-orange-600'; $bg = 'bg-orange-100'; }
                                        elseif (str_contains($note, 'login')) { $icon = 'ri-login-circle-line text-purple-600'; $bg = 'bg-purple-100'; }
                                        elseif (str_contains($note, 'transfer')) { $icon = 'ri-swap-line text-blue-600'; $bg = 'bg-blue-100'; }
                                    @endphp
                                    <div class="w-8 h-8 {{ $bg }} rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="{{ $icon }} text-sm"></i>
                                    </div>
                                    <p class="text-gray-700 max-w-xs">{{ $log->notes ?? '—' }}</p>
                                </div>
                            </td>

                            {{-- Asset --}}
                            <td class="px-6 py-4">
                                @if(!empty($log->asset_name) || !empty($log->asset_code))
                                <div>
                                    <p class="font-medium text-gray-900">{{ $log->asset_name ?? '—' }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $log->asset_code ?? '—' }}</p>
                                </div>
                                @else
                                <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Request --}}
                            <td class="px-6 py-4">
                                @if(!empty($log->request_type) || !empty($log->request_id))
                                <div>
                                    <p class="font-medium text-gray-900">{{ $log->request_type ?? '—' }}</p>
                                    <p class="text-xs text-gray-400 font-mono">REQ-{{ str_pad($log->request_id ?? 0, 5, '0', STR_PAD_LEFT) }}</p>
                                </div>
                                @else
                                <span class="text-gray-400">—</span>
                                @endif
                            
                            </td>

                            {{-- Date & Time --}}
                            <td class="px-6 py-4">
                                <p class="text-gray-700">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($log->created_at)->format('h:i A') }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</p>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4">
                                <button onclick="openViewModal({{ $log->id }})"
                                    class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition"
                                    title="View Details">
                                    <i class="ri-eye-line text-sm"></i>
                                </button>
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                        <i class="ri-shield-check-line text-2xl text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-500 font-medium">No audit logs found</p>
                                    <p class="text-gray-400 text-xs mt-1">System activities will appear here</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if(isset($logs) && $logs->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="text-center text-sm text-gray-400 mt-8 pt-6 border-t border-gray-200">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Log Details</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                @foreach($logs ?? [] as $log)
                        <div id="modal-{{ $log->id }}" class="modal-content hidden space-y-3">
                    {{-- User Info --}}
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-semibold">
                                {{ strtoupper(substr($log->user->full_name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ $log->user_name ?? ($log->user->full_name ?? '—') }}</p>
                            <p class="text-xs text-gray-500">{{ $log->user_role ?? ($log->user->role ?? '—') }} • {{ $log->user->department ?? '—' }}</p>
                        </div>
                    </div>
                    {{-- Details Grid --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Action</p>
                            <p class="text-sm font-medium text-gray-900">{{ $log->notes ?? '—' }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Date & Time</p>
                            <p class="text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y h:i A') }}</p>
                        </div>
                        @if(!empty($log->asset_name) || !empty($log->asset_code))
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Asset</p>
                            <p class="text-sm font-medium text-gray-900">{{ $log->asset_name ?? '—' }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $log->asset_code ?? '' }}</p>
                        </div>
                        @endif
                        @if(!empty($log->request_type) || !empty($log->request_id))
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Request</p>
                            <p class="text-sm font-medium text-gray-900">{{ $log->request_type ?? '—' }}</p>
                            <p class="text-xs text-gray-400 font-mono">REQ-{{ str_pad($log->request_id ?? 0, 5, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="p-6 border-t border-gray-100 flex justify-end">
                <button onclick="closeViewModal()"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <style>
        .filter-tab { transition: all 0.2s ease; }
        .log-row { transition: all 0.2s ease; }
    </style>

    <script>
        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.filter-tab').forEach(t => {
                    t.classList.remove('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                    t.classList.add('text-gray-500');
                });
                this.classList.add('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                this.classList.remove('text-gray-500');

                const filter = this.dataset.filter;
                document.querySelectorAll('.log-row').forEach(row => {
                    row.style.display = (filter === 'all' || row.dataset.type === filter) ? '' : 'none';
                });
            });
        });

        // Search
        document.getElementById('searchInput').addEventListener('input', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.log-row').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
            });
        });

        // Date filter
        document.getElementById('dateFilter').addEventListener('change', function () {
            const val = this.value;
            document.querySelectorAll('.log-row').forEach(row => {
                row.style.display = (!val || row.dataset.date === val) ? '' : 'none';
            });
        });

        // View Modal
        function openViewModal(id) {
            document.querySelectorAll('.modal-content').forEach(c => c.classList.add('hidden'));
            const content = document.getElementById('modal-' + id);
            if (content) content.classList.remove('hidden');
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Export (basic CSV export)
        function exportLogs() {
            const rows = [['User', 'Action', 'Asset', 'Request', 'Date']];
            document.querySelectorAll('.log-row').forEach(row => {
                const cells = row.querySelectorAll('td');
                rows.push([
                    cells[0]?.innerText.trim().replace(/\n/g, ' '),
                    cells[1]?.innerText.trim().replace(/\n/g, ' '),
                    cells[2]?.innerText.trim().replace(/\n/g, ' '),
                    cells[3]?.innerText.trim().replace(/\n/g, ' '),
                    cells[4]?.innerText.trim().replace(/\n/g, ' '),
                ]);
            });
            const csv = rows.map(r => r.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'audit-logs.csv';
            a.click();
        }
    </script>

@endsection