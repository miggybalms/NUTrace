@extends('layouts.user_sidebar')

@section('title', 'My Requests')

@section('content')

    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="px-4 sm:px-8 py-4 sm:py-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:justify-between lg:items-center">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">My Requests</h2>
                    <p class="text-sm text-gray-500 mt-1">Track all your submitted asset requests</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <!-- Search -->
                    <div class="relative flex-1 min-w-[140px] sm:flex-none order-1">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="searchInput" placeholder="Search requests..."
                            class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 w-full sm:w-56"/>
                    </div>
                    <!-- Submit Request Button -->
                    <a href="{{ route('user.request-asset') }}"
                       class="order-2 flex items-center px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium shadow-sm whitespace-nowrap">
                        <i class="ri-add-line mr-1.5 sm:mr-2"></i>
                        <span class="hidden xs:inline">Submit Request</span>
                        <span class="xs:hidden">Submit</span>
                    </a>
                    <!-- Notification -->
                    <div class="order-3 relative cursor-pointer">
                        <i class="ri-notification-3-line text-xl text-gray-600"></i>
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                    </div>
                    <!-- Profile -->
                    <div class="order-4 flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-semibold">
                                {{ strtoupper(substr(Auth::user()->full_name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                        <i class="ri-arrow-down-s-line text-gray-500 hidden sm:inline"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="p-4 sm:p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-gray-500">Total Requests</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-file-list-line text-blue-600"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $totalRequests ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-gray-500">Pending</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-time-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-yellow-600">{{ $pendingRequests ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-gray-500">Approved</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-checkbox-circle-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-green-600">{{ $approvedRequests ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-gray-500">Rejected</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-close-circle-line text-red-600"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-red-600">{{ $rejectedRequests ?? 0 }}</p>
            </div>
        </div>

        <!-- Requests List -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

            <!-- Tabs -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-4 sm:px-6 pt-5 pb-0 border-b border-gray-100">
                <div class="flex space-x-1 overflow-x-auto no-scrollbar -mx-1 px-1">
                    <button class="filter-tab active px-3 sm:px-4 py-2.5 text-sm font-medium text-blue-600 border-b-2 border-blue-600 whitespace-nowrap" data-filter="all">
                        All
                    </button>
                    <button class="filter-tab px-3 sm:px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap" data-filter="Pending">
                        Pending
                    </button>
                    <button class="filter-tab px-3 sm:px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap" data-filter="Approved">
                        Approved
                    </button>
                    <button class="filter-tab px-3 sm:px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap" data-filter="Rejected">
                        Rejected
                    </button>
                </div>
                <p class="text-sm text-gray-400 pb-3">{{ $requests->count() ?? 0 }} requests</p>
            </div>

            <!-- Request Cards -->
            <div class="p-4 sm:p-6 space-y-4" id="requestsList">

                @forelse($requests ?? [] as $request)
                <div class="request-row border border-gray-100 rounded-xl p-4 sm:p-5 hover:border-blue-200 hover:shadow-sm transition cursor-pointer"
                     data-status="{{ $request->status }}"
                     onclick="openViewModal({{ $request->id }})">

                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="flex items-start space-x-3 sm:space-x-4 min-w-0">

                            {{-- Request Type Icon --}}
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl flex items-center justify-center flex-shrink-0
                                @if($request->request_type == 'Repair') bg-red-100
                                @elseif($request->request_type == 'Disposal') bg-gray-100
                                @elseif($request->request_type == 'Transfer') bg-blue-100
                                @elseif($request->request_type == 'Replacement') bg-purple-100
                                @elseif($request->request_type == 'Pullout') bg-orange-100
                                @else bg-gray-100
                                @endif">
                                <i class="text-lg
                                    @if($request->request_type == 'Repair') ri-tools-line text-red-600
                                    @elseif($request->request_type == 'Disposal') ri-delete-bin-line text-gray-600
                                    @elseif($request->request_type == 'Transfer') ri-swap-line text-blue-600
                                    @elseif($request->request_type == 'Replacement') ri-refresh-line text-purple-600
                                    @elseif($request->request_type == 'Pullout') ri-logout-box-r-line text-orange-600
                                    @else ri-file-list-line text-gray-600
                                    @endif"></i>
                            </div>

                            {{-- Request Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1">
                                    <h4 class="font-semibold text-gray-900">{{ $request->request_type }} Request</h4>
                                    <span class="text-xs text-gray-400 hidden sm:inline">•</span>
                                    <span class="text-xs text-gray-400 font-mono">REQ-{{ str_pad($request->id, 5, '0', STR_PAD_LEFT) }}</span>
                                </div>

                                {{-- Single asset (employee) --}}
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400 mb-1">Asset</p>
                                    <div class="flex flex-col xs:flex-row xs:justify-between xs:items-center text-sm gap-0.5">
                                        <span class="font-medium text-gray-900 truncate">{{ $request->asset->Asset_name ?? '—' }}</span>
                                        <span class="font-mono text-xs text-gray-500">{{ $request->asset->Asset_code ?? '' }}</span>
                                    </div>
                                </div>

                                @if($request->Note)
                                <p class="text-sm text-gray-500 line-clamp-1 mt-2">
                                    <span class="font-medium text-gray-600">Note:</span> {{ $request->Note }}
                                </p>
                                @endif
                            </div>
                        </div>

                        {{-- Right side: Status + Date --}}
                        <div class="flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2 sm:gap-2 flex-shrink-0 sm:ml-4 pl-[52px] sm:pl-0">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                                @if($request->status == 'Pending') bg-yellow-100 text-yellow-700
                                @elseif($request->status == 'Approved') bg-green-100 text-green-700
                                @elseif($request->status == 'Rejected') bg-red-100 text-red-700
                                @else bg-gray-100 text-gray-700
                                @endif">
                                @if($request->status == 'Pending')
                                    <i class="ri-time-line mr-1"></i>
                                @elseif($request->status == 'Approved')
                                    <i class="ri-checkbox-circle-line mr-1"></i>
                                @elseif($request->status == 'Rejected')
                                    <i class="ri-close-circle-line mr-1"></i>
                                @endif
                                {{ $request->status }}
                            </span>
                            <div class="text-right sm:text-right">
                                <p class="text-xs text-gray-400">
                                    {{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->format('M d, Y') : '—' }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->diffForHumans() : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Approved/Rejected note --}}
                    @if($request->status == 'Approved')
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center text-xs text-green-600">
                        <i class="ri-checkbox-circle-fill mr-1.5"></i>
                        Your request has been approved and is being processed.
                    </div>
                    @elseif($request->status == 'Rejected')
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center text-xs text-red-500">
                        <i class="ri-close-circle-fill mr-1.5"></i>
                        Your request was rejected. Click to see details.
                    </div>
                    @endif

                </div>
                @empty
                {{-- Empty state --}}
                <div class="text-center py-12 sm:py-16">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-file-list-line text-2xl sm:text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-gray-700 font-semibold text-lg mb-1">No Requests Found</h3>
                    <p class="text-gray-400 text-sm mb-4">You haven't submitted any requests yet.</p>
                    <a href="{{ route('user.request-asset') }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                        <i class="ri-add-line mr-2"></i>
                        Submit Your First Request
                    </a>
                </div>
                @endforelse

            </div>

            <!-- Pagination -->
            @if(isset($requests) && $requests->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-100 overflow-x-auto">
                {{ $requests->links() }}
            </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="text-center text-sm text-gray-400 mt-8 pt-6 border-t border-gray-200">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h3 class="text-lg font-bold text-gray-900">Request Details</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="p-4 sm:p-6 space-y-4">
                @foreach($requests ?? [] as $request)
                <div id="modal-{{ $request->id }}" class="modal-content hidden">
                    <div class="flex items-center flex-wrap gap-3 mb-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0
                            @if($request->request_type == 'Repair') bg-red-100
                            @elseif($request->request_type == 'Disposal') bg-gray-100
                            @elseif($request->request_type == 'Transfer') bg-blue-100
                            @elseif($request->request_type == 'Replacement') bg-purple-100
                            @elseif($request->request_type == 'Pullout') bg-orange-100
                            @else bg-gray-100 @endif">
                            <i class="text-xl
                                @if($request->request_type == 'Repair') ri-tools-line text-red-600
                                @elseif($request->request_type == 'Disposal') ri-delete-bin-line text-gray-600
                                @elseif($request->request_type == 'Transfer') ri-swap-line text-blue-600
                                @elseif($request->request_type == 'Replacement') ri-refresh-line text-purple-600
                                @elseif($request->request_type == 'Pullout') ri-logout-box-r-line text-orange-600
                                @else ri-file-list-line text-gray-600 @endif"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-bold text-gray-900">{{ $request->request_type }} Request</h4>
                            <p class="text-xs text-gray-400 font-mono">REQ-{{ str_pad($request->id, 5, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <span class="ml-auto px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                            @if($request->status == 'Pending') bg-yellow-100 text-yellow-700
                            @elseif($request->status == 'Approved') bg-green-100 text-green-700
                            @elseif($request->status == 'Rejected') bg-red-100 text-red-700
                            @else bg-gray-100 text-gray-700 @endif">
                            {{ $request->status }}
                        </span>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-400 mb-1">Asset Name</p>
                                <p class="text-sm font-medium text-gray-900">{{ $request->asset->Asset_name ?? '—' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-400 mb-1">Asset Code</p>
                                <p class="text-sm font-medium text-gray-900 font-mono break-all">{{ $request->asset->Asset_code ?? '—' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-400 mb-1">Submitted On</p>
                                <p class="text-sm font-medium text-gray-900">{{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->format('M d, Y h:i A') : '—' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs text-gray-400 mb-1">Last Updated</p>
                                <p class="text-sm font-medium text-gray-900">{{ data_get($request, 'updated_at') ? \Carbon\Carbon::parse(data_get($request, 'updated_at'))->format('M d, Y h:i A') : '—' }}</p>
                            </div>
                        </div>
                        @if($request->Note)
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Note / Reason</p>
                            <p class="text-sm text-gray-700">{{ $request->Note }}</p>
                        </div>
                        @endif
                        @if($request->file_path)
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Attached File</p>
                            <a href="{{ asset('storage/' . $request->file_path) }}" target="_blank"
                               class="text-sm text-blue-600 hover:underline flex items-center">
                                <i class="ri-file-line mr-1.5"></i>
                                {{ $request->file_name ?? 'View File' }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="p-4 sm:p-6 border-t border-gray-100 flex justify-end sticky bottom-0 bg-white">
                <button onclick="closeViewModal()"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <style>
        .filter-tab { transition: all 0.2s ease; }
        .request-row { transition: all 0.2s ease; }
        .line-clamp-1 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
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
                document.querySelectorAll('.request-row').forEach(row => {
                    row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
                });
            });
        });

        // Search
        document.getElementById('searchInput').addEventListener('input', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.request-row').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
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
    </script>

@endsection