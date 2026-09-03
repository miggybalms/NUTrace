@extends('layouts.department_head_sidebar')

@section('title', 'My Assets')

@section('content')

    <!-- Header (search turned OFF – we put it under the filters) -->
    @include('layouts.department_head_header', [
        'title'             => 'My Assets',
        'subtitle'          => 'View and manage your assigned assets',
        'showSearch'        => false,
        'searchPlaceholder' => 'Search assets...',
    ])

    <!-- Content -->
    <div class="p-4 sm:p-8">

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-gradient-to-br from-[#0B1B33] to-[#1C3A63] rounded-xl shadow-lg p-5 sm:p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Total Assets</p>
                        <p class="text-3xl font-bold mt-2">{{ $totalAssets ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="ri-computer-line text-2xl text-[#E8C874]"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-[#C9A227] to-[#E8C874] rounded-xl shadow-lg p-5 sm:p-6 text-[#0B1B33]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Active Assets</p>
                        <p class="text-3xl font-bold mt-2">{{ $activeAssets ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-[#0B1B33]/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="ri-checkbox-circle-line text-2xl text-[#0B1B33]"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-[#142B4D] to-[#0B1B33] rounded-xl shadow-lg p-5 sm:p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Pending Requests</p>
                        <p class="text-3xl font-bold mt-2">{{ $pendingRequests ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="ri-time-line text-2xl text-[#E8C874]"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs + Search (search is now under the tabs) -->
        <div class="mb-6 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-flex items-center gap-1 bg-[#0B1B33]/5 rounded-full p-1 overflow-x-auto no-scrollbar w-full sm:w-auto">
                    <button class="filter-btn active px-4 py-2 rounded-full text-sm font-medium bg-[#0B1B33] text-white shadow-sm whitespace-nowrap transition" data-filter="all">
                        All Assets
                    </button>
                    <button class="filter-btn px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-[#0B1B33] whitespace-nowrap transition" data-filter="Active">
                        Active
                    </button>
                    <button class="filter-btn px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-[#0B1B33] whitespace-nowrap transition" data-filter="For Repair">
                        For Repair
                    </button>
                    <button class="filter-btn px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-[#0B1B33] whitespace-nowrap transition" data-filter="recent">
                        Recently Added
                    </button>
                    <button class="filter-btn px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-[#0B1B33] whitespace-nowrap transition" data-filter="department">
                        My Personal Assets
                    </button>
                </div>

                @php
                    $visibleStatuses = ['Acquired', 'Active', 'For Repair'];
                    $visibleAssets = isset($assignedAssets)
                        ? $assignedAssets->filter(fn($a) => in_array(($a->Lifecycle_Status ?? 'Acquired'), $visibleStatuses, true))
                        : collect();
                @endphp
                <p class="text-sm text-gray-500 whitespace-nowrap" data-list-count>
                    Showing {{ $visibleAssets->count() ?? 0 }} assets
                </p>
            </div>

            <!-- Search bar under the filters -->
            <div class="relative max-w-md">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text"
                       id="asset-search-input"
                       placeholder="Search assets..."
                       class="pl-9 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 w-full bg-white shadow-sm"
                       autocomplete="off">
            </div>
        </div>

        <!-- Assets Grid -->
        @if(isset($visibleAssets) && $visibleAssets->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6" id="assetsGrid">
            @foreach($visibleAssets as $asset)
            <div class="asset-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                 data-status="{{ $asset->Lifecycle_Status ?? '' }}"
                 data-owner-id="{{ $asset->user_id ?? '' }}"
                 data-name="{{ strtolower($asset->Asset_name ?? '') }}"
                 data-code="{{ strtolower($asset->Asset_code ?? '') }}"
                 data-category="{{ strtolower($asset->Category ?? '') }}"
                 data-location="{{ strtolower($asset->asset_location ?? '') }}">

                {{-- Asset Image --}}
                <div class="relative h-40 sm:h-44 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                    @if($asset->image_url)
                        <img src="{{ $asset->image_url }}" alt="{{ $asset->Asset_name }}"
                             class="w-full h-full object-cover"
                             onerror="this.classList.add('hidden'); this.parentElement.querySelector('.asset-image-fallback')?.classList.remove('hidden');"/>
                        <div class="asset-image-fallback hidden flex-col items-center text-gray-400 absolute inset-0 flex justify-center">
                            <i class="ri-image-line text-4xl mb-1"></i>
                            <span class="text-xs">Image unavailable</span>
                        </div>
                    @else
                        <div class="flex flex-col items-center text-gray-400">
                            <i class="ri-image-line text-4xl mb-1"></i>
                            <span class="text-xs">No image</span>
                        </div>
                    @endif

                    {{-- Status badge --}}
                    <div class="absolute top-3 left-3">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            @if(($asset->Lifecycle_Status ?? '') == 'Active') bg-green-100 text-green-700
                            @elseif(($asset->Lifecycle_Status ?? '') == 'For Repair') bg-red-100 text-red-700
                            @elseif(($asset->Lifecycle_Status ?? '') == 'Pullout') bg-orange-100 text-orange-700
                            @elseif(($asset->Lifecycle_Status ?? '') == 'Disposal') bg-gray-100 text-gray-700
                            @else bg-yellow-100 text-yellow-700
                            @endif">
                            {{ $asset->Lifecycle_Status ?? 'Acquired' }}
                        </span>
                    </div>

                    {{-- QR icon --}}
                    <div class="absolute top-3 right-3">
                        <a href="javascript:void(0)"
                           class="w-8 h-8 bg-white/85 rounded-lg flex items-center justify-center hover:bg-[#E8C874]/90 transition"
                           title="View QR Code"
                           data-qr-url="{{ $asset->qr_code_url ?? (isset($asset->qr_code_path) ? Storage::url($asset->qr_code_path) : '') }}"
                           data-asset-code="{{ $asset->Asset_code ?? '' }}"
                           onclick="openQrModal(this)">
                            <i class="ri-qr-code-line text-[#0B1B33] text-sm"></i>
                        </a>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-4 sm:p-5">
                    <div class="mb-3">
                        <h3 class="font-semibold text-[#0B1B33] text-base leading-tight">{{ $asset->Asset_name ?? 'Untitled' }}</h3>
                        <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $asset->Asset_code ?? '' }}</p>
                    </div>

                    <div class="space-y-1.5 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-layout-grid-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span class="truncate">{{ $asset->Category ?? '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-calendar-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span>Assigned: {{ $asset->accusion_date ? \Carbon\Carbon::parse($asset->accusion_date)->format('M d, Y') : '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-tools-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span>Next Maintenance: {{ $asset->next_maintenance_date ? \Carbon\Carbon::parse($asset->next_maintenance_date)->format('M d, Y') : '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-map-pin-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span class="truncate">{{ $asset->asset_location ?? '—' }}</span>
                        </div>
                    </div>

                    {{-- Actions – carry current filter/search so back button can restore them --}}
                    <div class="pt-3 border-t border-gray-100">
                        <a href="/department-head/assets/{{ $asset->id }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                           class="w-full px-3 py-2 bg-[#C9A227]/10 text-[#0B1B33] rounded-lg hover:bg-[#0B1B33] hover:text-[#E8C874] transition text-sm font-medium flex items-center justify-center">
                            <i class="ri-eye-line mr-1.5"></i>
                            View
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @else
        {{-- Empty state --}}
        <div class="text-center py-12 sm:py-16">
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-[#0B1B33]/5 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-inbox-line text-2xl sm:text-3xl text-[#C9A227]"></i>
            </div>
            <h3 class="text-gray-700 font-semibold text-lg mb-1">No Assets Found</h3>
            <p class="text-gray-400 text-sm">You have no assigned assets at the moment.</p>
        </div>
        @endif

        <!-- Footer -->
        <div class="text-center text-sm text-gray-400 mt-10 pt-6 border-t border-gray-200">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <style>
        .asset-card { transition: all 0.3s ease; }
        .asset-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(11, 27, 51, 0.14);
            border-color: rgba(201, 162, 39, 0.35);
        }
        .filter-btn { transition: all 0.2s ease; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <!-- QR Modal -->
    <div id="qrModal" class="fixed inset-0 bg-[#0B1B33]/60 hidden items-center justify-center z-50 p-4" onclick="closeQrModal()">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full" onclick="event.stopPropagation();">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-[#0B1B33]">Asset QR Code</h3>
                <button onclick="closeQrModal()" class="text-gray-400 hover:text-[#0B1B33]">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div id="qrModalCanvas" class="flex items-center justify-center bg-gray-50 rounded-lg p-4"></div>
            <p id="qrModalAssetId" class="text-sm font-mono text-gray-600 text-center mt-4 break-all"></p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script>
        window.__dhCurrentUserId = "{{ Auth::id() ?? '' }}";
        let qrModalInstance = null;

        // =====================
        // Unified Filter + Search (with URL persistence)
        // =====================
        (function () {
            const searchInput = document.getElementById('asset-search-input');
            const filterBtns  = document.querySelectorAll('.filter-btn');
            const cards       = document.querySelectorAll('.asset-card');
            const countLabel  = document.querySelector('[data-list-count]');
            const currentUserId = window.__dhCurrentUserId || '';

            // Restore state from URL
            const params = new URLSearchParams(window.location.search);
            let currentFilter = params.get('filter') || 'all';
            let currentSearch = params.get('q') || '';

            if (searchInput && currentSearch) {
                searchInput.value = currentSearch;
            }

            // Highlight the correct tab
            filterBtns.forEach(btn => {
                const isActive = btn.dataset.filter === currentFilter;
                btn.classList.toggle('active', isActive);
                btn.classList.toggle('bg-[#0B1B33]', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('shadow-sm', isActive);
                btn.classList.toggle('text-gray-500', !isActive);
            });

            function getCardText(card) {
                return [
                    card.dataset.name,
                    card.dataset.code,
                    card.dataset.category,
                    card.dataset.location,
                    card.dataset.status
                ].filter(Boolean).join(' ').toLowerCase();
            }

            function applyFilters() {
                const q = (searchInput?.value || '').trim().toLowerCase();
                let visible = 0;

                cards.forEach(card => {
                    const status = (card.dataset.status || '').trim();
                    const ownerId = (card.dataset.ownerId || '').trim();

                    let statusOk = true;
                    if (currentFilter === 'all' || currentFilter === 'recent') {
                        statusOk = true;
                    } else if (currentFilter === 'department') {
                        statusOk = ownerId === currentUserId;
                    } else {
                        statusOk = status === currentFilter;
                    }

                    const searchOk = !q || getCardText(card).includes(q);
                    const show = statusOk && searchOk;

                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                if (countLabel) {
                    countLabel.textContent = `Showing ${visible} assets`;
                }

                // Persist in URL so browser back + our Back button can restore it
                const newParams = new URLSearchParams();
                if (currentFilter && currentFilter !== 'all') {
                    newParams.set('filter', currentFilter);
                }
                if (q) {
                    newParams.set('q', q);
                }

                const newUrl = window.location.pathname + (newParams.toString() ? '?' + newParams.toString() : '');
                window.history.replaceState({}, '', newUrl);
            }

            // Tab clicks
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    filterBtns.forEach(b => {
                        b.classList.remove('active', 'bg-[#0B1B33]', 'text-white', 'shadow-sm');
                        b.classList.add('text-gray-500');
                    });
                    this.classList.add('active', 'bg-[#0B1B33]', 'text-white', 'shadow-sm');
                    this.classList.remove('text-gray-500');

                    currentFilter = this.dataset.filter;
                    applyFilters();
                });
            });

            // Live search
            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            // Run on load
            applyFilters();
        })();

        // =====================
        // QR Modal
        // =====================
        function openQrModal(el) {
            var code = el.getAttribute('data-asset-code') || '';
            var canvas = document.getElementById('qrModalCanvas');
            var idEl = document.getElementById('qrModalAssetId');

            if (qrModalInstance) {
                try { qrModalInstance.clear(); } catch(e) {}
            }
            canvas.innerHTML = '';

            if (code && code.length > 0) {
                qrModalInstance = new QRCode(canvas, {
                    text: code,
                    width: 220,
                    height: 220,
                    colorDark: "#0B1B33",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
                idEl.textContent = code;
            }

            var modal = document.getElementById('qrModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('tabindex', '-1');
            modal.focus();
        }

        function closeQrModal() {
            var modal = document.getElementById('qrModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            if (qrModalInstance) {
                try { qrModalInstance.clear(); } catch(e) {}
            }
            qrModalInstance = null;
            document.getElementById('qrModalCanvas').innerHTML = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('qrModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeQrModal();
                }
            }
        });
    </script>

@endsection