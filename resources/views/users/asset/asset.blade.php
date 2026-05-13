@extends('layouts.user_sidebar')

@section('title', 'My Assets')

@section('content')

    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="px-8 py-5">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">My Assets</h2>
                    <div class="flex items-center mt-1">
                        <span class="text-sm text-blue-600 font-medium">{{ Auth::user()->employee_numbers->Full_Name ?? 'User' }}</span>
                        <span class="mx-2 text-gray-300">•</span>
                        <p class="text-sm text-gray-500">View and manage your assigned assets</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Search -->
                    <div class="relative">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" placeholder="Search assets..."
                            class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 w-56"/>
                    </div>
                    <!-- Notification -->
                    <div class="relative cursor-pointer">
                        <i class="ri-notification-3-line text-xl text-gray-600"></i>
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                    </div>
                    <!-- Profile -->
                    <div class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-semibold">
                                {{ strtoupper(substr(Auth::user()->employee_numbers->Full_Name ?? 'U', 0, 1)) }}
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

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Total Assets</p>
                        <p class="text-3xl font-bold mt-2">{{ $totalAssets ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="ri-computer-line text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Active Assets</p>
                        <p class="text-3xl font-bold mt-2">{{ $activeAssets ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="ri-checkbox-circle-line text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Pending Requests</p>
                        <p class="text-3xl font-bold mt-2">{{ $pendingRequests ?? 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="ri-time-line text-2xl"></i>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Filter Tabs -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex space-x-2 border-b border-gray-200">
                <button class="filter-btn active px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-filter="all">
                    All Assets
                </button>
                <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="Active">
                    Active
                </button>
                <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="For Repair">
                    For Repair
                </button>
                <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="recent">
                    Recently Added
                </button>
            </div>
            @php
                $currentUser = Auth::user();
                $visibleStatuses = ['Acquired', 'Active', 'For Repair'];
                $visibleAssets = collect();

                if (isset($assignedAssets) && $currentUser) {
                    $visibleAssets = $assignedAssets->filter(function($a) use ($visibleStatuses, $currentUser) {
                        // Admins may view all assigned assets; regular users only their own
                        $statusOk = in_array(($a->Lifecycle_Status ?? 'Acquired'), $visibleStatuses, true);
                        if (($currentUser->role ?? '') === 'Admin') {
                            return $statusOk;
                        }
                        return ($a->user_id === $currentUser->id) && $statusOk;
                    });
                }
            @endphp
            <p class="text-sm text-gray-500">Showing {{ $visibleAssets->count() ?? 0 }} assets</p>
        </div>

        <!-- Assets Grid -->
        @if(isset($visibleAssets) && $visibleAssets->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="assetsGrid">
            @foreach($visibleAssets as $asset)
            <div class="asset-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                 data-status="{{ $asset->Lifecycle_Status ?? '' }}">

                {{-- Asset Image --}}
                <div class="relative h-44 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
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

                    {{-- Status badge on image --}}
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
                           class="w-8 h-8 bg-white/80 rounded-lg flex items-center justify-center hover:bg-white transition"
                           title="View QR Code"
                           data-qr-url="{{ $asset->qr_code_url ?? (isset($asset->qr_code_path) ? Storage::url($asset->qr_code_path) : '') }}"
                           data-asset-code="{{ $asset->Asset_code ?? '' }}"
                           onclick="openQrModal(this)">
                            <i class="ri-qr-code-line text-gray-700 text-sm"></i>
                        </a>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-5">
                    <div class="mb-3">
                        <h3 class="font-semibold text-gray-900 text-base leading-tight">{{ $asset->Asset_name ?? 'Untitled' }}</h3>
                        <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $asset->Asset_code ?? '' }}</p>
                    </div>

                    <div class="space-y-1.5 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-layout-grid-line text-gray-400 mr-2 text-xs"></i>
                            <span class="truncate">{{ $asset->Category ?? '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-calendar-line text-gray-400 mr-2 text-xs"></i>
                            <span>Assigned: {{ $asset->accusion_date ? \Carbon\Carbon::parse($asset->accusion_date)->format('M d, Y') : '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-tools-line text-gray-400 mr-2 text-xs"></i>
                            <span>Next Maintenance: {{ $asset->next_maintenance_date ? \Carbon\Carbon::parse($asset->next_maintenance_date)->format('M d, Y') : '—' }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="ri-map-pin-line text-gray-400 mr-2 text-xs"></i>
                            <span class="truncate">{{ $asset->asset_location ?? '—' }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="pt-3 border-t border-gray-100">
                        <a href="/users/assets/{{ $asset->id }}"
                           class="w-full px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition text-sm font-medium flex items-center justify-center">
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
        <div class="text-center py-16">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-inbox-line text-3xl text-gray-400"></i>
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
        .asset-card {
            transition: all 0.3s ease;
        }
        .asset-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.08);
        }
        .filter-btn { transition: all 0.2s ease; }
    </style>
    <!-- QR Modal -->
    <div id="qrModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" onclick="closeQrModal()">
        <div class="bg-white rounded-lg p-6 max-w-sm" onclick="event.stopPropagation();">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Asset QR Code</h3>
                <button onclick="closeQrModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div id="qrModalCanvas" class="flex items-center justify-center bg-gray-50 rounded-lg p-4"></div>
            <p id="qrModalAssetId" class="text-sm font-mono text-gray-600 text-center mt-4"></p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script>
        let qrModalInstance = null;

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                // Update active tab
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                    b.classList.add('text-gray-500');
                });
                this.classList.add('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
                this.classList.remove('text-gray-500');

                // Filter cards
                const filter = this.dataset.filter;
                document.querySelectorAll('.asset-card').forEach(card => {
                    if (filter === 'all' || filter === 'recent') {
                        card.style.display = '';
                    } else {
                        card.style.display = card.dataset.status === filter ? '' : 'none';
                    }
                });
            });
        });

        function openQrModal(el) {
            var code = el.getAttribute('data-asset-code') || '';
            var canvas = document.getElementById('qrModalCanvas');
            var idEl = document.getElementById('qrModalAssetId');

            // Clear previous QR
            if (qrModalInstance) {
                try { qrModalInstance.clear(); } catch(e) {}
            }
            canvas.innerHTML = '';

            if (code && code.length > 0) {
                qrModalInstance = new QRCode(canvas, {
                    text: code,
                    width: 220,
                    height: 220,
                    colorDark: "#1f2937",
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

        // Close on ESC
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