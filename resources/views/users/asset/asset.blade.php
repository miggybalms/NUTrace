@extends('layouts.user_sidebar')

@section('title', 'My Assets')

@section('content')

    <!-- Header (no search here anymore) -->
    @include('layouts.user_header', [
        'title'             => 'My Assets',
        'showSearch'        => false,          // ← turned off
        'searchPlaceholder' => 'Search assets...',
        'searchTarget'      => 'assets',
    ])

    <!-- Content -->
    <div class="p-4 sm:p-8">

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-gradient-to-br from-[#0A1830] to-[#15305B] rounded-xl shadow-lg p-5 sm:p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Total Assets</p>
                        <p class="text-3xl font-bold mt-2">{{ $totalAssets ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="ri-computer-line text-2xl text-[#E9C766]"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-[#C9A227] to-[#E9C766] rounded-xl shadow-lg p-5 sm:p-6 text-[#0A1830]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Active Assets</p>
                        <p class="text-3xl font-bold mt-2">{{ $activeAssets ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-[#0A1830]/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="ri-checkbox-circle-line text-2xl text-[#0A1830]"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-[#142442] to-[#0A1830] rounded-xl shadow-lg p-5 sm:p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-80">Pending Requests</p>
                        <p class="text-3xl font-bold mt-2">{{ $pendingRequests ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="ri-time-line text-2xl text-[#E9C766]"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs + Search (search is now under the tabs) -->
        <div class="mb-6 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {{-- Tabs are links now: the filter is applied in the database, so
                     it covers every asset the holder has, not just this page. --}}
                {{-- flex-wrap: on a phone the six lifecycle tabs become a two-row block of
                     pills instead of a strip you have to drag sideways. --}}
                <div class="inline-flex flex-wrap items-center gap-1 bg-[#0A1830]/5 rounded-full p-1 overflow-x-auto no-scrollbar w-full sm:w-auto">
                    @foreach(\App\Support\AssetListing::tabs(false) as $tabValue => $tabLabel)
                        <a href="{{ \App\Support\AssetListing::tabUrl(request()->url(), $tabValue, $currentSearch ?? '') }}"
                           class="filter-btn px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition
                                  {{ \App\Support\AssetListing::isActive($tabValue, $currentFilter ?? 'all')
                                      ? 'active bg-[#0A1830] text-white shadow-sm'
                                      : 'text-[#5B6678] hover:text-[#0A1830]' }}"
                           @if(\App\Support\AssetListing::isActive($tabValue, $currentFilter ?? 'all')) aria-current="page" @endif>
                            {{ $tabLabel }}
                        </a>
                    @endforeach
                </div>

                {{-- The count is the whole filtered set, not the page: the route
                     filters before it pages. --}}
                <p class="text-sm text-[#5B6678] whitespace-nowrap" data-list-count>
                    Showing {{ $assignedAssets->total() }} assets
                </p>
            </div>

            {{-- Search under the filters. It submits to the server, so it searches
                 every asset the holder has rather than the cards on this page. --}}
            <form method="GET" action="{{ request()->url() }}" class="relative max-w-md">
                @if(($currentFilter ?? 'all') !== 'all')
                    <input type="hidden" name="filter" value="{{ $currentFilter }}">
                @endif
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-[#5C6474] text-sm"></i>
                <input type="text"
                       id="asset-search-input"
                       name="q"
                       value="{{ $currentSearch ?? '' }}"
                       placeholder="Search assets..."
                       class="pl-9 pr-4 py-2.5 border border-[#DED2AE] rounded-lg text-sm focus:outline-none focus:border-[#C9A227] w-full bg-white shadow-sm"
                       autocomplete="off">
            </form>
        </div>

        <!-- Assets Grid -->
        @if($assignedAssets->total() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6" id="assetsGrid">
            @foreach($assignedAssets as $asset)
            @php
                // When the asset landed on this user's account: assignment/acquisition date,
                // falling back to when the record was registered. Drives "Recently Added".
                $addedOn = $asset->accusion_date
                    ? \Carbon\Carbon::parse($asset->accusion_date)->toDateString()
                    : ($asset->created_at ? \Carbon\Carbon::parse($asset->created_at)->toDateString() : '');
            @endphp
            <div class="asset-card bg-white rounded-xl shadow-sm border border-[#DED2AE] overflow-hidden"
                 data-status="{{ $asset->Lifecycle_Status ?? '' }}"
                 data-added="{{ $addedOn }}"
                 data-name="{{ strtolower($asset->Asset_name ?? '') }}"
                 data-code="{{ strtolower($asset->Asset_code ?? '') }}"
                 data-category="{{ strtolower($asset->Category ?? '') }}"
                 data-location="{{ strtolower($asset->asset_location ?? '') }}">

                {{-- Asset Image --}}
                <div class="relative h-40 sm:h-44 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                    @if($asset->image_url)
                        {{-- data-src: the photo is requested by the script under the
                             grid only when the card gets near the viewport. --}}
                        <img data-src="{{ $asset->image_url }}" alt="{{ $asset->Asset_name }}"
                             class="w-full h-full object-cover"
                             decoding="async"
                             onerror="this.classList.add('hidden'); this.parentElement.querySelector('.asset-image-fallback')?.classList.remove('hidden');"/>
                        <div class="asset-image-fallback hidden flex-col items-center text-[#5C6474] absolute inset-0 flex justify-center">
                            <i class="ri-image-line text-4xl mb-1"></i>
                            <span class="text-xs">Image unavailable</span>
                        </div>
                    @else
                        <div class="flex flex-col items-center text-[#5C6474]">
                            <i class="ri-image-line text-4xl mb-1"></i>
                            <span class="text-xs">No image</span>
                        </div>
                    @endif

                    {{-- Status badge --}}
                    <div class="absolute top-3 left-3">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            @if(($asset->Lifecycle_Status ?? '') == 'Active') bg-[#EAF4EE] text-[#245C3B]
                            @elseif(($asset->Lifecycle_Status ?? '') == 'For Repair') bg-[#F7E9E6] text-[#7E2E27]
                            @elseif(($asset->Lifecycle_Status ?? '') == 'For Checking') bg-[#FBF1DE] text-[#8F5F16]
                            @elseif(($asset->Lifecycle_Status ?? '') == 'For Replacement') bg-[#EFE7F3] text-[#523A64]
                            @elseif(($asset->Lifecycle_Status ?? '') == 'Pullout') bg-[#FBF1DE] text-[#8F5F16]
                            @elseif(($asset->Lifecycle_Status ?? '') == 'Disposal') bg-[#EFE9D8] text-[#33425C]
                            @else bg-[#FBF1DE] text-[#8F5F16]
                            @endif">
                            {{ $asset->Lifecycle_Status ?? 'Acquired' }}
                        </span>
                    </div>

                    {{-- QR icon --}}
                    <div class="absolute top-3 right-3">
                        <a href="javascript:void(0)"
                           class="w-8 h-8 bg-white/85 rounded-lg flex items-center justify-center hover:bg-[#E9C766]/90 transition"
                           title="View QR Code"
                           data-qr-url="{{ \App\Support\Media::url($asset->qr_code_path) ?? '' }}"
                           data-asset-code="{{ $asset->Asset_code ?? '' }}"
                           {{-- What the sticker actually carries: the signed payload. --}}
                           data-qr-payload="{{ \App\Support\AssetQr::stickerText($asset->Asset_code ?? '') }}"
                           onclick="openQrModal(this)">
                            <i class="ri-qr-code-line text-[#0A1830] text-sm"></i>
                        </a>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-4 sm:p-5">
                    <div class="mb-3">
                        <h3 class="font-semibold text-[#0A1830] text-base leading-tight">{{ $asset->Asset_name ?? 'Untitled' }}</h3>
                        <p class="text-xs text-[#5C6474] font-mono mt-0.5">{{ $asset->Asset_code ?? '' }}</p>
                    </div>

                    <div class="space-y-1.5 mb-4">
                        <div class="flex items-center text-sm text-[#46536B]">
                            <i class="ri-layout-grid-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span class="truncate">{{ $asset->Category ?? '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-[#46536B]">
                            <i class="ri-calendar-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span>Assigned: {{ $asset->accusion_date ? \Carbon\Carbon::parse($asset->accusion_date)->format('M d, Y') : '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-[#46536B]">
                            <i class="ri-tools-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span>Next Maintenance: {{ $asset->next_maintenance_date ? \Carbon\Carbon::parse($asset->next_maintenance_date)->format('M d, Y') : '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-[#46536B]">
                            <i class="ri-map-pin-line text-[#C9A227] mr-2 text-xs flex-shrink-0"></i>
                            <span class="truncate">{{ $asset->asset_location ?? '—' }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="pt-5 border-t border-[#EFE9D8]">
                        <a href="/users/assets/{{ $asset->id }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                        class="w-full px-3 py-2 bg-[#C9A227]/10 text-[#0A1830] rounded-lg hover:bg-[#0A1830] hover:text-[#E9C766] transition text-sm font-medium flex items-center justify-center">
                            <i class="ri-eye-line mr-1.5"></i>
                            View
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Photos are fetched as their card approaches the viewport. The browser's
             own loading="lazy" is not enough here: it pre-fetches everything within
             roughly 1250px of the viewport, which is the whole first page of cards,
             so the Network tab filled up before anything was scrolled. --}}
        <script>
            (function () {
                var photos = Array.prototype.slice.call(
                    document.querySelectorAll('#assetsGrid img[data-src]')
                );

                if (!photos.length) return;

                function reveal(img) {
                    var src = img.getAttribute('data-src');
                    if (!src) return;
                    img.removeAttribute('data-src');
                    img.src = src;
                }

                // No observer (very old browser): show everything rather than nothing.
                if (!('IntersectionObserver' in window)) {
                    photos.forEach(reveal);
                    return;
                }

                var loader = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        loader.unobserve(entry.target);
                        reveal(entry.target);
                    });
                }, { root: null, rootMargin: '200px 0px', threshold: 0 });

                photos.forEach(function (img) { loader.observe(img); });
            })();
        </script>

        @include('partials.asset_pagination', ['assets' => $assignedAssets])

        @else
        {{-- Empty state --}}
        <div class="text-center py-12 sm:py-16">
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-[#0A1830]/5 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-inbox-line text-2xl sm:text-3xl text-[#C9A227]"></i>
            </div>
            <h3 class="text-[#33425C] font-semibold text-lg mb-1">
                {{ \App\Support\AssetListing::hasFilters($currentFilter ?? 'all', $currentSearch ?? '') ? 'No Matching Assets' : 'No Assets Found' }}
            </h3>
            <p class="text-[#5C6474] text-sm">
                {{ \App\Support\AssetListing::hasFilters($currentFilter ?? 'all', $currentSearch ?? '')
                    ? 'Nothing matches the selected tab or search.'
                    : 'You have no assigned assets at the moment.' }}
            </p>
            @if(\App\Support\AssetListing::hasFilters($currentFilter ?? 'all', $currentSearch ?? ''))
                <a href="{{ request()->url() }}"
                   class="inline-flex items-center mt-3 px-3.5 py-1.5 rounded-full text-xs font-medium bg-[#0A1830]/5 text-[#33425C] hover:bg-[#0A1830]/10 transition">
                    <i class="ri-refresh-line mr-1"></i> Show all assets
                </a>
            @endif
        </div>
        @endif

        <!-- Footer -->
        <div class="text-center text-sm text-[#5C6474] mt-10 pt-6 border-t border-[#DED2AE]">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <style>
        .asset-card {
            transition: all 0.3s ease;
        }
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
    <div id="qrModal" class="fixed inset-0 bg-[#0A1830]/60 hidden items-center justify-center z-50 p-4" onclick="closeQrModal()">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full" onclick="event.stopPropagation();">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-[#0A1830]">Asset QR Code</h3>
                <button onclick="closeQrModal()" class="text-[#5C6474] hover:text-[#0A1830]" aria-label="Close">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div id="qrModalCanvas" class="flex items-center justify-center bg-[#F5F0E2] rounded-lg p-4"></div>
            <p id="qrModalAssetId" class="text-sm font-mono text-[#46536B] text-center mt-4 break-all"></p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    @include('partials.qr_scan_js')

    <script>
        let qrModalInstance = null;

        // Search runs on the server, so it covers every asset the holder has and
        // not only the cards on this page: typing pauses, then the form submits.
        let assetSearchTimer;
        document.getElementById('asset-search-input')?.addEventListener('input', function () {
            clearTimeout(assetSearchTimer);
            assetSearchTimer = setTimeout(() => this.form.submit(), 450);
        });

        // =====================
        // QR Modal (unchanged)
        // =====================
        function openQrModal(el) {
            var code = el.getAttribute('data-asset-code') || '';
            // The signed payload is what the printed sticker carries, so the
            // modal shows the same code a camera would read off the tag.
            var payload = el.getAttribute('data-qr-payload') || code;
            var canvas = document.getElementById('qrModalCanvas');
            var idEl = document.getElementById('qrModalAssetId');

            if (qrModalInstance) {
                try { qrModalInstance.clear(); } catch(e) {}
            }
            canvas.innerHTML = '';

            if (payload && payload.length > 0) {
                qrModalInstance = window.NUTraceQr.draw(canvas, payload, {
                    width: 220,
                    height: 220,
                    colorDark: "#0A1830"
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