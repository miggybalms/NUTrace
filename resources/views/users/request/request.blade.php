@extends('layouts.user_sidebar')

@section('title', 'My Requests')

@section('content')

            <!-- Header – search OFF -->
                @include('layouts.user_header', [
                    'title'             => 'My Requests',
                    'showSearch'        => false,
                    'showAction'        => true,
                    'actionUrl'         => route('user.request-asset'),
                    'actionLabel'       => 'Submit',
                    'actionIcon'        => 'ri-add-line',
                ])

    <!-- Content -->
    <div class="p-4 sm:p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
            <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-[#5B6678]">Total Requests</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-[#0A1830]/10 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-file-list-line text-[#0A1830]"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-[#0F2143]">{{ $totalRequests ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-[#5B6678]">Pending</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-[#FBF1DE] rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-time-line text-[#B4791E]"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-[#B4791E]">{{ $pendingRequests ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-[#5B6678]">Approved</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-[#EAF4EE] rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-checkbox-circle-line text-[#2F7A4D]"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-[#2F7A4D]">{{ $approvedRequests ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm text-[#5B6678]">Rejected</p>
                    <div class="w-8 h-8 sm:w-9 sm:h-9 bg-[#F7E9E6] rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="ri-close-circle-line text-[#A23B32]"></i>
                    </div>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-[#A23B32]">{{ $rejectedRequests ?? 0 }}</p>
            </div>
        </div>

        <!-- Requests List -->
        <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm">

<!-- Tabs + Search under them -->
            <div class="px-4 sm:px-6 pt-5 pb-4 border-b border-[#EFE9D8] space-y-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="inline-flex items-center gap-1 bg-[#0A1830]/5 rounded-full p-1 overflow-x-auto no-scrollbar w-full sm:w-auto">
                        @php
                            $currentStatus = $status ?? request('status', 'all');
                            $currentSearch = $search ?? request('q', '');
                            $tabs = [
                                'all'      => 'All',
                                'Pending'  => 'Pending',
                                'Approved' => 'Approved',
                                'Rejected' => 'Rejected',
                            ];
                        @endphp

                        @foreach($tabs as $value => $label)
                            <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => 1, 'q' => $currentSearch ?: null]) }}"
                               class="filter-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition
                                      {{ $currentStatus === $value
                                            ? 'active bg-[#0A1830] text-white shadow-sm'
                                            : 'text-[#5B6678] hover:text-[#0A1830]' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>

                    <p class="text-sm text-[#8991A0] whitespace-nowrap">
                        {{ $requests->total() }} request{{ $requests->total() === 1 ? '' : 's' }}
                    </p>
                </div>

                {{-- Search under the tabs --}}
                <form method="GET" action="{{ url()->current() }}" class="relative max-w-md">
                    @if($currentStatus && $currentStatus !== 'all')
                        <input type="hidden" name="status" value="{{ $currentStatus }}">
                    @endif
                    <input type="hidden" name="page" value="1">

                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-[#8991A0] text-sm pointer-events-none"></i>
                    <input type="text"
                           name="q"
                           value="{{ $currentSearch }}"
                           placeholder="Search requests..."
                           class="pl-9 pr-10 py-2.5 border border-[#DED2AE] rounded-lg text-sm focus:outline-none focus:border-[#C9A227] w-full bg-white"
                           autocomplete="off">
                    @if($currentSearch !== '')
                        <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => 1]) }}"
                           class="absolute right-3 top-1/2 -translate-y-1/2 text-[#8991A0] hover:text-[#46536B]"
                           title="Clear search">
                            <i class="ri-close-line"></i>
                        </a>
                    @endif
                </form>
            </div>

            <!-- Request Cards -->
            <div class="p-4 sm:p-6 space-y-4" id="requestsList">

                @forelse($requests ?? [] as $request)
                <div class="request-row border border-[#EFE9D8] rounded-xl p-4 sm:p-5 hover:border-[#C9A227]/40 hover:shadow-sm transition cursor-pointer"
                     data-status="{{ $request->status }}"
                     onclick="openViewModal({{ $request->id }})">

                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="flex items-start space-x-3 sm:space-x-4 min-w-0">

                            {{-- Request Type Icon (category colors — unchanged) --}}
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl flex items-center justify-center flex-shrink-0
                                @if($request->request_type == 'Repair') bg-[#F7E9E6]
                                @elseif($request->request_type == 'Disposal') bg-[#EFE9D8]
                                @elseif($request->request_type == 'Transfer') bg-[#F3E7C4]
                                @elseif($request->request_type == 'Replacement') bg-[#EFE7F3]
                                @elseif($request->request_type == 'Pullout') bg-[#FBF1DE]
                                @else bg-[#EFE9D8]
                                @endif">
                                <i class="text-lg
                                    @if($request->request_type == 'Repair') ri-tools-line text-[#A23B32]
                                    @elseif($request->request_type == 'Disposal') ri-delete-bin-line text-[#46536B]
                                    @elseif($request->request_type == 'Transfer') ri-swap-line text-[#A8841E]
                                    @elseif($request->request_type == 'Replacement') ri-refresh-line text-[#6B4C82]
                                    @elseif($request->request_type == 'Pullout') ri-archive-drawer-line text-[#B4791E]
                                    @else ri-file-list-line text-[#46536B]
                                    @endif"></i>
                            </div>

                            {{-- Request Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1">
                                    <h4 class="font-semibold text-[#0A1830]">{{ $request->request_type }} Request</h4>
                                </div>

                                {{-- Single asset (employee) --}}
                                <div class="bg-[#F5F0E2] rounded-lg p-3">
                                    <p class="text-xs text-[#8991A0] mb-1">Asset</p>
                                    <div class="flex flex-col xs:flex-row xs:justify-between xs:items-center text-sm gap-0.5">
                                        <span class="font-medium text-[#0F2143] truncate">{{ $request->asset->Asset_name ?? '—' }}</span>
                                        <span class="font-mono text-xs text-[#5B6678]">{{ $request->asset->Asset_code ?? '' }}</span>
                                    </div>
                                </div>

                                @if($request->Note)
                                <p class="text-sm text-[#5B6678] line-clamp-1 mt-2 [overflow-wrap:anywhere] break-words">
                                    <span class="font-medium text-[#46536B]">Note:</span> {{ $request->Note }}
                                </p>
                                @endif
                                @if(!empty($request->admin_remarks))
                                <p class="text-xs text-[#8F5F16] mt-1.5 flex items-center">
                                    <i class="ri-shield-user-line mr-1"></i>Admin remarks available — open for details.
                                </p>
                                @endif
                            </div>
                        </div>

                        {{-- Right side: Status + Date (status colors — unchanged) --}}
                        <div class="flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2 sm:gap-2 flex-shrink-0 sm:ml-4 pl-[52px] sm:pl-0">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                                @if($request->status == 'Pending') bg-[#FBF1DE] text-[#8F5F16]
                                @elseif($request->status == 'Approved') bg-[#EAF4EE] text-[#245C3B]
                                @elseif($request->status == 'Rejected') bg-[#F7E9E6] text-[#7E2E27]
                                @else bg-[#EFE9D8] text-[#33425C]
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
                                <p class="text-xs text-[#8991A0]">
                                    {{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->format('M d, Y') : '—' }}
                                </p>
                                <p class="text-xs text-[#8991A0]">
                                    {{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->diffForHumans() : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Approved/Rejected note (status colors — unchanged) --}}
                    @if($request->status == 'Approved')
                    <div class="mt-5 pt-5 border-t border-[#EFE9D8] flex items-center text-xs text-[#2F7A4D]">
                        <i class="ri-checkbox-circle-fill mr-1.5"></i>
                        Your request has been approved and is being processed.
                    </div>
                    @elseif($request->status == 'Rejected')
                    <div class="mt-5 pt-5 border-t border-[#EFE9D8] flex items-center text-xs text-[#A23B32]">
                        <i class="ri-close-circle-fill mr-1.5"></i>
                        Your request was rejected. Click to see details.
                    </div>
                    @endif

                </div>
                @empty
                {{-- Empty state --}}
                <div class="text-center py-12 sm:py-16">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-[#0A1830]/5 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-file-list-line text-2xl sm:text-3xl text-[#C9A227]"></i>
                    </div>
                    <h3 class="text-[#33425C] font-semibold text-lg mb-1">No Requests Found</h3>
                    <p class="text-[#8991A0] text-sm mb-4">You haven't submitted any requests yet.</p>
                    <a href="{{ route('user.request-asset') }}"
                       class="inline-flex items-center px-4 py-2 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E9C766] transition text-sm font-semibold">
                        <i class="ri-add-line mr-2"></i>
                        Submit Your First Request
                    </a>
                </div>
                @endforelse

            </div>

            <!-- Pagination -->
            @if(isset($requests) && $requests->hasPages())
            <div class="px-4 sm:px-6 py-5 border-t border-[#EFE9D8] overflow-x-auto">
                {{ $requests->links() }}
            </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="text-center text-sm text-[#8991A0] mt-8 pt-6 border-t border-[#DED2AE]">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-[#0A1830]/60 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6 border-b border-[#DED2AE] flex justify-between items-center sticky top-0 bg-white">
                <h3 class="text-lg font-bold text-[#0A1830]">Request Details</h3>
                <button onclick="closeViewModal()" class="text-[#8991A0] hover:text-[#0A1830]">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="p-4 sm:p-6 space-y-4">
                @foreach($requests ?? [] as $request)
                <div id="modal-{{ $request->id }}" class="modal-content hidden">
                    <div class="flex items-center flex-wrap gap-3 mb-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0
                            @if($request->request_type == 'Repair') bg-[#F7E9E6]
                            @elseif($request->request_type == 'Disposal') bg-[#EFE9D8]
                            @elseif($request->request_type == 'Transfer') bg-[#F3E7C4]
                            @elseif($request->request_type == 'Replacement') bg-[#EFE7F3]
                            @elseif($request->request_type == 'Pullout') bg-[#FBF1DE]
                            @else bg-[#EFE9D8] @endif">
                            <i class="text-xl
                                @if($request->request_type == 'Repair') ri-tools-line text-[#A23B32]
                                @elseif($request->request_type == 'Disposal') ri-delete-bin-line text-[#46536B]
                                @elseif($request->request_type == 'Transfer') ri-swap-line text-[#A8841E]
                                @elseif($request->request_type == 'Replacement') ri-refresh-line text-[#6B4C82]
                                @elseif($request->request_type == 'Pullout') ri-archive-drawer-line text-[#B4791E]
                                @else ri-file-list-line text-[#46536B] @endif"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-bold text-[#0A1830]">{{ $request->request_type }} Request</h4>
                        </div>
                        <span class="ml-auto px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                            @if($request->status == 'Pending') bg-[#FBF1DE] text-[#8F5F16]
                            @elseif($request->status == 'Approved') bg-[#EAF4EE] text-[#245C3B]
                            @elseif($request->status == 'Rejected') bg-[#F7E9E6] text-[#7E2E27]
                            @else bg-[#EFE9D8] text-[#33425C] @endif">
                            {{ $request->status }}
                        </span>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="bg-[#F5F0E2] rounded-lg p-3">
                                <p class="text-xs text-[#8991A0] mb-1">Asset Name</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $request->asset->Asset_name ?? '—' }}</p>
                            </div>
                            <div class="bg-[#F5F0E2] rounded-lg p-3">
                                <p class="text-xs text-[#8991A0] mb-1">Asset Code</p>
                                <p class="text-sm font-medium text-[#0F2143] font-mono break-all">{{ $request->asset->Asset_code ?? '—' }}</p>
                            </div>
                            <div class="bg-[#F5F0E2] rounded-lg p-3">
                                <p class="text-xs text-[#8991A0] mb-1">Submitted On</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->format('M d, Y h:i A') : '—' }}</p>
                            </div>
                            <div class="bg-[#F5F0E2] rounded-lg p-3">
                                <p class="text-xs text-[#8991A0] mb-1">Last Updated</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ data_get($request, 'updated_at') ? \Carbon\Carbon::parse(data_get($request, 'updated_at'))->format('M d, Y h:i A') : '—' }}</p>
                            </div>
                        </div>
                        @if($request->Note)
                        <div class="bg-[#F5F0E2] rounded-lg p-3">
                            <p class="text-xs text-[#8991A0] mb-1">Your Request Note</p>
                            <p class="text-sm text-[#33425C] whitespace-pre-line [overflow-wrap:anywhere] break-words">{{ $request->Note }}</p>
                        </div>
                        @endif
                        @if(!empty($request->admin_remarks))
                        <div class="rounded-lg p-3 border border-[#EAD9B4] bg-[#FBF7EA]">
                            <p class="text-xs font-medium text-[#8F5F16] mb-1">
                                <i class="ri-shield-user-line mr-1"></i>Admin Remarks
                            </p>
                            <p class="text-sm text-[#33425C] whitespace-pre-line [overflow-wrap:anywhere] break-words">{{ $request->admin_remarks }}</p>
                            @if(!empty($request->admin_remarks_at))
                            <p class="text-xs text-[#8991A0] mt-2">
                                Asset Management Office · {{ \Carbon\Carbon::parse($request->admin_remarks_at)->format('M d, Y h:i A') }}
                            </p>
                            @endif
                        </div>
                        @endif
                        @if($request->file_path)
                        <div class="bg-[#F5F0E2] rounded-lg p-3">
                            <p class="text-xs text-[#8991A0] mb-1">Attached File</p>
                            <a href="{{ \App\Support\Media::url($request->file_path) }}" target="_blank"
                               class="text-sm text-[#0A1830] hover:text-[#C9A227] hover:underline flex items-center transition">
                                <i class="ri-file-line mr-1.5"></i>
                                {{ $request->file_name ?? 'View File' }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="px-4 sm:px-6 py-5 border-t border-[#EFE9D8] flex justify-end sticky bottom-0 bg-white">
                <button onclick="closeViewModal()"
                    class="px-4 py-2 bg-[#EFE9D8] text-[#33425C] rounded-lg hover:bg-[#E4DAC0] transition text-sm font-medium">
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
            function openViewModal(id) {
                document.querySelectorAll('.modal-content').forEach(c => c.classList.add('hidden'));
                const content = document.getElementById('modal-' + id);
                if (content) content.classList.remove('hidden');
                document.getElementById('viewModal').classList.remove('hidden');
            }

            function closeViewModal() {
                document.getElementById('viewModal').classList.add('hidden');
            }

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