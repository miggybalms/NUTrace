@extends('layouts.admin_sidebar')

@section('title', 'Replacement Records')

@section('content')

            <!-- Header -->
            <div class="bg-white border-b border-slate-200 sticky top-0 z-10">
                <div class="px-4 sm:px-8 py-5">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <div class="flex items-center">
                            <!-- Hamburger, mobile only -->
                            <button onclick="toggleSidebar()" class="lg:hidden mr-3 text-slate-500 hover:text-slate-900">
                                <i class="ri-menu-line text-2xl"></i>
                            </button>
                            <div>
                                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Replacement Records</h2>
                                <p class="text-sm text-slate-500 mt-1 hidden sm:block">Manage and track all asset replacement requests</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative flex-1 sm:flex-none">
                                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="text" id="searchInput" placeholder="Search replacements..."
                                    class="pl-9 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-shadow w-full sm:w-56"/>
                            </div>
                            <div class="relative cursor-pointer flex-shrink-0 text-slate-500 hover:text-slate-700">
                                <i class="ri-notification-3-line text-xl"></i>
                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"></span>
                            </div>
                            <div class="flex items-center space-x-2 cursor-pointer hover:bg-slate-50 rounded-lg px-2 py-1 flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-semibold">
                                        {{ strtoupper(substr(Auth::user()->full_name ?? 'A', 0, 1)) }}
                                    </span>
                                </div>
                                <i class="ri-arrow-down-s-line text-slate-400 hidden sm:block"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <div class="p-4 sm:p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</p>
                    <div class="w-9 h-9 bg-slate-100 rounded-lg flex items-center justify-center">
                        <i class="ri-refresh-line text-slate-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mt-1">{{ $totalReplacements ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Pending</p>
                    <div class="w-9 h-9 bg-yellow-50 rounded-lg flex items-center justify-center">
                        <i class="ri-time-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mt-1">{{ $pendingReplacements ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Approved</p>
                    <div class="w-9 h-9 bg-orange-50 rounded-lg flex items-center justify-center">
                        <i class="ri-checkbox-circle-line text-orange-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mt-1">{{ $approvedReplacements ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Received</p>
                    <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center">
                        <i class="ri-check-double-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900 mt-1">{{ $receivedReplacements ?? 0 }}</p>
            </div>
        </div>

        <!-- Replacement Table -->
        <div class="bg-white rounded-xl border border-slate-200">

            <!-- Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 sm:px-6 pt-4 pb-0 border-b border-slate-100">
            <div class="flex space-x-1 overflow-x-auto scrollbar-hide">
                <button class="filter-tab active px-4 py-2.5 text-sm font-medium text-blue-700 border-b-2 border-blue-600 whitespace-nowrap" data-filter="all">All</button>
                <button class="filter-tab px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="Pending">Pending</button>
                <button class="filter-tab px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="Approved">Approved</button>
                <button class="filter-tab px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800 whitespace-nowrap" data-filter="Received">Received</button>
            </div>
            <p class="text-xs text-slate-400 pb-3">{{ $replacements->count() ?? 0 }} records</p>
        </div>

                    <div class="overflow-x-auto">
            <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase text-[11px] tracking-wider">
                            <th class="px-6 py-3.5 text-left font-semibold">Old Asset Code</th>
                            <th class="px-6 py-3.5 text-left font-semibold">New Asset Code</th>
                            <th class="px-6 py-3.5 text-left font-semibold">Requested By</th>
                            <th class="px-6 py-3.5 text-left font-semibold">Reason</th>
                            <th class="px-6 py-3.5 text-left font-semibold">Progress</th>
                            <th class="px-6 py-3.5 text-left font-semibold">Status</th>
                            <th class="px-6 py-3.5 text-left font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">

                        @forelse($replacements ?? [] as $replacement)
                        <tr class="replacement-row hover:bg-slate-50/70 transition-colors"
                            data-status="{{ $replacement->status }}"
                            data-id="{{ data_get($replacement, 'id') ?? data_get($replacement, 'Replacement_id') }}">

                            {{-- Old Asset --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="ri-computer-line text-red-500 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900 font-mono text-[13px]">{{ data_get($replacement, 'old_asset_code') ?? ($replacement->oldAsset->Asset_code ?? '—') }}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ data_get($replacement, 'old_asset_name') ?? ($replacement->oldAsset->Asset_name ?? '') }}</p>
                                    </div>
                                </div>
                            </td>

                                        {{-- New Asset --}}
                                        <td class="px-6 py-4">
                                            @php
                                            $hasRealNewAsset = data_get($replacement, 'new_asset_id')
                                            && data_get($replacement, 'new_asset_id') != data_get($replacement, 'old_asset_id')
                                            && (data_get($replacement, 'new_asset_code') || data_get($replacement, 'newAsset'));
                                        @endphp

                                        @if($hasRealNewAsset)
                                        <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="ri-computer-line text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                    <p class="font-medium text-slate-900 font-mono text-[13px]">{{ data_get($replacement, 'new_asset_code') ?? data_get($replacement, 'newAsset.Asset_code') ?? '—' }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ data_get($replacement, 'new_asset_name') ?? data_get($replacement, 'newAsset.Asset_name') ?? '' }}</p>
                                    </div>
                                    </div>
                                    @else
                                    @if($replacement->status === 'Approved')
                                    @php
                                        $linkId = data_get($replacement, 'id') ?? data_get($replacement, 'Replacement_id');
                                        $oldName = data_get($replacement, 'old_asset_name') ?? data_get($replacement, 'oldAsset.Asset_name') ?? '';
                                        $oldCat  = data_get($replacement, 'old_asset_category') ?? data_get($replacement, 'oldAsset.Category') ?? '';
                                        $oldLoc  = data_get($replacement, 'old_asset_location') ?? data_get($replacement, 'oldAsset.asset_location') ?? '';
                                    @endphp
                                    <button type="button"
                                        onclick='openLinkModal({{ $linkId }}, @json($oldName), @json($oldCat), @json($oldLoc))'
                                        class="flex items-center space-x-2 px-3 py-1.5 border border-dashed border-slate-300 rounded-lg text-slate-400 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50/50 transition-colors text-xs font-medium">
                                        <i class="ri-link mr-1"></i>
                                        Link new asset
                                    </button>
                                @else
                            <span class="text-xs text-slate-400">—</span>
                            @endif
                            @endif
                        </td>

                            {{-- Requested By --}}
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ data_get($replacement, 'requested_by') ?? ($replacement->request->user->full_name ?? '—') }}</p>
                                <p class="text-xs text-slate-400">{{ data_get($replacement, 'department') ?? ($replacement->request->user->department ?? '—') }}</p>
                            </td>

                            {{-- Reason --}}
                            <td class="px-6 py-4">
                                <p class="text-slate-600 max-w-xs truncate">{{ $replacement->reason ?? '—' }}</p>
                            </td>

                            {{-- Progress Steps --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-1">
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                                            {{ in_array($replacement->status, ['Pending','Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400' }}">
                                            1
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1">Pending</span>
                                    </div>
                                    <div class="w-6 h-0.5 {{ in_array($replacement->status, ['Approved','Received']) ? 'bg-blue-600' : 'bg-slate-200' }} mb-4"></div>
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                                            {{ in_array($replacement->status, ['Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400' }}">
                                            2
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1">Approved</span>
                                    </div>
                                    <div class="w-6 h-0.5 {{ $replacement->status === 'Received' ? 'bg-green-500' : 'bg-slate-200' }} mb-4"></div>
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                                            {{ $replacement->status === 'Received' ? 'bg-green-500 text-white' : 'bg-slate-200 text-slate-400' }}">
                                            3
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1">Received</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                    @if($replacement->status == 'Pending') bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-200
                                    @elseif($replacement->status == 'Approved') bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200
                                    @elseif($replacement->status == 'Received') bg-green-50 text-green-700 ring-1 ring-inset ring-green-200
                                    @else bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200
                                    @endif">
                                    {{ $replacement->status ?? '—' }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4">
                                <div class="flex flex-row flex-nowrap items-center gap-1.5 whitespace-nowrap">
                                    <button type="button" onclick="openViewModal({{ $replacement->id }})"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-colors"
                                        title="View Details">
                                        <i class="ri-eye-line text-sm"></i>
                                    </button>

                                    @if($replacement->status === 'Pending')
                                    <button type="button" onclick="openApproveModal({{ $replacement->id }})"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center bg-green-50 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition-colors"
                                        title="Approve">
                                        <i class="ri-checkbox-circle-line text-sm"></i>
                                    </button>
                                    @endif

                                        @php
                                            $newId = data_get($replacement, 'new_asset_id') ?? data_get($replacement, 'new_assets_id');
                                            $oldId = data_get($replacement, 'old_asset_id') ?? data_get($replacement, 'old_assets_id');
                                            $hasRealNew = $newId && $oldId && (int)$newId !== (int)$oldId
                                                && (data_get($replacement, 'new_asset_code') || data_get($replacement, 'newAsset'));
                                        @endphp

                                        {{-- Mark as Received — only when Approved + real new asset linked --}}
                                        @if($replacement->status === 'Approved' && $hasRealNew)
                                        <button type="button" onclick="openReceivedModal({{ $replacement->id }})"
                                            class="w-8 h-8 shrink-0 flex items-center justify-center bg-teal-50 text-teal-600 rounded-lg hover:bg-teal-600 hover:text-white transition-colors"
                                            title="Mark as Received">
                                            <i class="ri-check-double-line text-sm"></i>
                                        </button>
                                        @endif

                                        @if($hasRealNew)
                                        <a href="/admin/assets/{{ $newId }}" target="_blank"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition-colors"
                                        title="View New Asset">
                                        <i class="ri-external-link-line text-sm"></i>
                                    </a>

                                    @if(data_get($replacement, 'new_asset_qr'))
                                        <button type="button"
                                            onclick="downloadUrl('{{ \Illuminate\Support\Facades\Storage::url(data_get($replacement, 'new_asset_qr')) }}', '{{ data_get($replacement, 'new_asset_code') ?? 'qr' }}')"
                                            class="w-8 h-8 shrink-0 flex items-center justify-center bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors"
                                            title="Download QR">
                                            <i class="ri-download-line text-sm"></i>
                                        </button>
                                        @php
                                            $qrPrintUrl = \Illuminate\Support\Facades\Storage::url(data_get($replacement, 'new_asset_qr'));
                                            $qrPrintMeta = [
                                                'code'     => data_get($replacement, 'new_asset_code') ?? data_get($replacement, 'newAsset.Asset_code'),
                                                'name'     => data_get($replacement, 'new_asset_name') ?? data_get($replacement, 'newAsset.Asset_name') ?? data_get($replacement, 'old_asset_name'),
                                                'location' => data_get($replacement, 'new_asset_location') ?? data_get($replacement, 'old_asset_location') ?? '—',
                                                'category' => data_get($replacement, 'new_asset_category') ?? data_get($replacement, 'old_asset_category') ?? '—',
                                                'acquired' => data_get($replacement, 'new_asset_acquired') ?? '',
                                            ];
                                        @endphp
                                        <button type="button"
                                            onclick='printUrl(@json($qrPrintUrl), @json($qrPrintMeta))'
                                            class="w-8 h-8 shrink-0 flex items-center justify-center bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors"
                                            title="Print QR">
                                            <i class="ri-printer-line text-sm"></i>
                                        </button>
                                    @endif
                                    @endif

                                    <button type="button" onclick="confirmDelete({{ $replacement->id }})"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center bg-red-50 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition-colors"
                                        title="Delete">
                                        <i class="ri-delete-bin-line text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                                        <i class="ri-refresh-line text-2xl text-slate-400"></i>
                                    </div>
                                    <p class="text-slate-700 font-medium text-sm">No replacement records found</p>
                                    <p class="text-slate-400 text-xs mt-1">Replacement requests will appear here once submitted</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            @if(isset($replacements) && method_exists($replacements, 'hasPages') && $replacements->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $replacements->links() }}
            </div>
            @endif
        </div>

        <div class="text-center text-xs text-slate-400 mt-10 pt-6 border-t border-slate-200">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <h3 class="text-lg font-bold text-slate-900">Replacement Details</h3>
                <button onclick="closeModal('viewModal')" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="p-6" id="viewModalContent">
                @foreach($replacements ?? [] as $r)
                <div id="view-{{ $r->id }}" class="modal-content hidden space-y-5">
                    {{-- Progress bar --}}
                    <div>
                        <div class="flex items-center space-x-2 flex-1 mb-1">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                {{ in_array($r->status, ['Pending','Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500' }}">1</div>
                            <div class="flex-1 h-1 {{ in_array($r->status, ['Approved','Received']) ? 'bg-blue-600' : 'bg-slate-200' }} rounded"></div>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                {{ in_array($r->status, ['Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500' }}">2</div>
                            <div class="flex-1 h-1 {{ $r->status === 'Received' ? 'bg-green-500' : 'bg-slate-200' }} rounded"></div>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                {{ $r->status === 'Received' ? 'bg-green-500 text-white' : 'bg-slate-200 text-slate-500' }}">3</div>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Pending</span><span>Approved</span><span>Received</span>
                        </div>
                    </div>

                    {{-- Old vs New Asset --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-red-50/60 border border-red-100 rounded-xl p-4">
                            <p class="text-xs font-semibold text-red-600 mb-2 flex items-center">
                                <i class="ri-arrow-left-line mr-1"></i> Old Asset
                            </p>
                            <p class="font-semibold text-slate-900 text-sm">{{ data_get($r, 'old_asset_name') ?? ($r->oldAsset->Asset_name ?? '—') }}</p>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">{{ data_get($r, 'old_asset_code') ?? ($r->oldAsset->Asset_code ?? '—') }}</p>
                            <p class="text-xs text-slate-500 mt-1.5">{{ data_get($r, 'old_asset_category') ?? ($r->oldAsset->Category ?? '—') }}</p>
                            <span class="text-[11px] font-medium px-2 py-0.5 bg-red-100 text-red-700 rounded-full mt-2 inline-block">
                                {{ data_get($r, 'old_asset_lifecycle_status') ?? ($r->oldAsset->Lifecycle_Status ?? '—') }}
                            </span>
                        </div>
                        <div class="bg-green-50/60 border border-green-100 rounded-xl p-4">
                            <p class="text-xs font-semibold text-green-700 mb-2 flex items-center">
                                <i class="ri-arrow-right-line mr-1"></i> New Asset
                            </p>
                            @php
                            $hasRealNewAsset = data_get($r, 'new_asset_id')
                            && data_get($r, 'new_asset_id') != data_get($r, 'old_asset_id');
                            @endphp

                            @if($hasRealNewAsset)
                            <p class="font-semibold text-slate-900 text-sm">{{ data_get($r, 'new_asset_name') ?? data_get($r, 'newAsset.Asset_name') ?? '—' }}</p>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">{{ data_get($r, 'new_asset_code') ?? data_get($r, 'newAsset.Asset_code') ?? '—' }}</p>
                            <p class="text-xs text-slate-500 mt-1.5">{{ data_get($r, 'new_asset_category') ?? data_get($r, 'newAsset.Category') ?? '—' }}</p>
                            <span class="text-[11px] font-medium px-2 py-0.5 bg-green-100 text-green-700 rounded-full mt-2 inline-block">
                            {{ data_get($r, 'new_asset_lifecycle_status') ?? data_get($r, 'newAsset.Lifecycle_Status') ?? '—' }}
                            </span>
                            @else
                            <p class="text-xs text-slate-400 italic mt-4">Not yet assigned</p>
                            @endif
                        </div>
                    </div>

                    {{-- Details --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-50 rounded-lg p-3.5">
                            <p class="text-xs text-slate-400 mb-1">Requested By</p>
                            <p class="text-sm font-medium text-slate-900">{{ data_get($r, 'requested_by') ?? ($r->request->user->full_name ?? '—') }}</p>
                            <p class="text-xs text-slate-400">{{ data_get($r, 'department') ?? ($r->request->user->department ?? '—') }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3.5">
                            <p class="text-xs text-slate-400 mb-1">Approved By</p>
                            <p class="text-sm font-medium text-slate-900">{{ $r->Approve_by ?? '—' }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3.5">
                            <p class="text-xs text-slate-400 mb-1">Replacement Date</p>
                            <p class="text-sm font-medium text-slate-900">
                                {{ $r->Replacement_Date ? \Carbon\Carbon::parse($r->Replacement_Date)->format('M d, Y') : '—' }}
                            </p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3.5">
                            <p class="text-xs text-slate-400 mb-1">Submitted On</p>
                            <p class="text-sm font-medium text-slate-900">
                                {{ data_get($r, 'created_at') ? \Carbon\Carbon::parse(data_get($r, 'created_at'))->format('M d, Y') : '—' }}
                            </p>
                        </div>
                    </div>

                    @if($r->reason)
                    <div class="bg-slate-50 rounded-lg p-3.5">
                        <p class="text-xs text-slate-400 mb-1">Reason</p>
                        <p class="text-sm text-slate-700">{{ $r->reason }}</p>
                    </div>
                    @endif

                    @if($r->notes)
                    <div class="bg-slate-50 rounded-lg p-3.5">
                        <p class="text-xs text-slate-400 mb-1">Notes</p>
                        <p class="text-sm text-slate-700">{{ $r->notes }}</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="p-6 border-t border-slate-100 flex justify-end">
                <button onclick="closeModal('viewModal')"
                    class="px-4 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900">Approve Replacement</h3>
                <button onclick="closeModal('approveModal')" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="p-6 space-y-4">
                    <div class="flex items-start space-x-3 p-3.5 bg-green-50 border border-green-100 rounded-lg">
                        <i class="ri-checkbox-circle-line text-green-600 text-lg mt-0.5"></i>
                        <p class="text-sm text-green-800">Approving this request will allow you to create and link a new asset for this replacement.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes (optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this approval..."
                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-shadow resize-none"></textarea>
                    </div>
                    <input type="hidden" name="status" value="Approved"/>
                    <input type="hidden" name="Approve_by" value="{{ Auth::user()->full_name ?? '' }}"/>
                </div>
                <div class="p-6 border-t border-slate-100 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('approveModal')"
                        class="px-4 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium shadow-sm shadow-green-600/20">
                        <i class="ri-checkbox-circle-line mr-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

            <!-- Link New Asset Modal (full form, prefilled from old asset) -->
            <div id="linkModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Create &amp; Link New Asset</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Pre-filled from old asset — edit as needed, then create</p>
                        </div>
                        <button type="button" onclick="closeModal('linkModal')" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 w-8 h-8 rounded-lg flex items-center justify-center">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <form id="linkForm" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                            <div class="p-6 space-y-5"> 
                        {{-- New Asset Code + QR (generate on demand) --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">New Asset Code (auto)</p>
                                    <p id="generatedCode" class="text-lg font-bold text-slate-900 font-mono mt-0.5">—</p>
                                    <input type="hidden" name="Asset_code" id="assetCodeHidden" value="">
                                </div>
                                <button type="button" onclick="regenerateCode()"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-300 transition">
                                    <i class="ri-refresh-line mr-1"></i> Regenerate code
                                </button>
                            </div>

                            {{-- Hidden until Generate QR is clicked --}}
                            <div id="linkQrSection" class="hidden mt-3 pt-3 border-t border-slate-200">
                                <div class="flex items-start gap-4">
                                    <div class="w-28 h-28 bg-white border border-slate-200 rounded-lg flex items-center justify-center overflow-hidden flex-shrink-0">
                                        <img id="generatedQrImg" src="" alt="QR" class="w-full h-full object-contain">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs text-slate-500 mb-2">QR for the new code. Updates when you regenerate.</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" onclick="downloadGeneratedQr()"
                                                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50">
                                                <i class="ri-download-line mr-1"></i> Download
                                            </button>
                                            <button type="button" onclick="printGeneratedQr()"
                                                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50">
                                                <i class="ri-printer-line mr-1"></i> Print
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Generate button (shown until QR is generated) --}}
                            <div id="linkQrGenerateWrap" class="mt-3">
                                <button type="button" onclick="generateLinkQr()"
                                    class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                                    <i class="ri-qr-code-line mr-1"></i> Generate QR Code
                                </button>
                                <p class="text-xs text-slate-400 mt-1.5 text-center">Fill in the fields below, then generate the QR.</p>
                            </div>
                            </div>

                                {{-- Prefilled fields --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Asset Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="Asset_name" id="link_asset_name" required
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    </div>
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                                                    <input type="text" id="link_category_display" value="" readonly
                                                        class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm bg-slate-50 text-slate-700 cursor-not-allowed">
                                                    <input type="hidden" name="Category" id="link_category" value="">
                                            </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Condition</label>
                                            <input type="text" value="New" readonly
                                                class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm bg-slate-50 text-slate-700 cursor-not-allowed">
                                            <input type="hidden" name="Condition" id="link_condition" value="New">
                                        </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Location</label>
                                        <input type="text" name="asset_location" id="link_location"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Serial Number</label>
                                        <input type="text" name="serial_Number" id="link_serial"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Purchase Price</label>
                                        <input type="number" step="0.01" name="purchase_Price" id="link_price"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Acquisition Date</label>
                                        <input type="date" name="accusion_date" id="link_acquired"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Supplier</label>
                                        <input type="text" name="supplier" id="link_supplier"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Warranty (months)</label>
                                        <input type="number" name="warranty_months" id="link_warranty" value="12"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Lifespan (months)</label>
                                        <input type="number" name="lifespan_months" id="link_lifespan"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Maintenance Interval (months)</label>
                                        <input type="number" name="maintenance_interval" id="link_interval"
                                            class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                    </div>

                                    {{-- Asset Photo --}}
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Asset Photo</label>
                                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-lg hover:border-blue-400 transition cursor-pointer"
                                            onclick="document.getElementById('link_asset_photo').click()">
                                            <div class="space-y-1 text-center">
                                                <i class="ri-image-line text-3xl text-slate-400 mb-1 block"></i>
                                                <div class="flex text-sm text-slate-600 justify-center">
                                                    <span class="font-medium text-blue-600">Upload a file</span>
                                                    <p class="pl-1">or drag and drop</p>
                                                </div>
                                                <p class="text-xs text-slate-500">PNG, JPG, GIF, WEBP up to 10MB</p>
                                            </div>
                                        </div>
                                        <input id="link_asset_photo" name="asset_photo" type="file" class="hidden" accept="image/*"
                                            onchange="previewLinkImage(this)">
                                        <div id="link_photo_preview" class="mt-3 hidden flex items-start space-x-3">
                                            <img id="link_preview_img" class="h-28 w-auto rounded-lg border border-slate-200" alt="Preview">
                                            <button type="button" onclick="removeLinkPreview()"
                                                class="px-3 py-1 bg-red-50 text-red-600 rounded-lg border border-red-100 hover:bg-red-100 text-sm">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            <div class="flex items-start space-x-2 p-3.5 bg-amber-50 border border-amber-100 rounded-lg">
                                <i class="ri-information-line text-amber-600 mt-0.5 flex-shrink-0"></i>
                                <p class="text-xs text-amber-800">
                                    New asset will be set to <strong>Active</strong> and assigned to the same user.
                                    Old asset will be moved to <strong>Pullout</strong> with reason “Replacement” (not deleted).
                                </p>
                            </div>
                        </div>
                        

                        <div class="p-6 border-t border-slate-100 flex justify-end space-x-3 sticky bottom-0 bg-white">
                            <button type="button" onclick="closeModal('linkModal')"
                                class="px-4 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium shadow-sm">
                                <i class="ri-add-line mr-1"></i> Create &amp; Link Asset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mark Received Modal -->
            <div id="receivedModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[70] flex items-center justify-center p-4">
                <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4">
                    <div class="p-6 text-center">
                        <div class="w-14 h-14 bg-teal-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="ri-check-double-line text-teal-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Mark as Received?</h3>
                        <p class="text-slate-500 text-sm leading-relaxed">
                            Confirming receipt will set the new asset to
                            <span class="font-semibold text-green-600">Active</span>
                            and the old asset to
                            <span class="font-semibold text-orange-600">Pullout</span>.
                        </p>
                    </div>
                    <form id="receivedForm" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="Received"/>
                        <div class="p-6 border-t border-slate-100 flex justify-center space-x-3">
                            <button type="button" onclick="closeModal('receivedModal')"
                                class="px-5 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-5 py-2.5 bg-teal-600 text-white rounded-lg hover:bg-teal-700 text-sm font-medium">
                                <i class="ri-check-double-line mr-1"></i> Confirm Received
                            </button>
                        </div>
                    </form>
                </div>
            </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="modal-panel bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4">
            <div class="p-6 text-center">
                <div class="w-14 h-14 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-delete-bin-line text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">Delete Replacement Record?</h3>
                <p class="text-slate-500 text-sm">This action cannot be undone.</p>
            </div>
            <div class="p-6 border-t border-slate-100 flex justify-center space-x-3">
                <button onclick="closeModal('deleteModal')"
                    class="px-5 py-2.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm font-medium">
                    Cancel
                </button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium shadow-sm shadow-red-600/20">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

            <style>
                .filter-tab { transition: all 0.15s ease; }
                .replacement-row { transition: background-color 0.15s ease; }
                .scrollbar-hide::-webkit-scrollbar { display: none; }
                .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
                @keyframes slideUp { from { opacity: 0; transform: translateY(12px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
                .modal-panel { animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
            </style>

    <script>
        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.filter-tab').forEach(t => {
                    t.classList.remove('active', 'text-blue-700', 'border-b-2', 'border-blue-600');
                    t.classList.add('text-slate-500');
                });
                this.classList.add('active', 'text-blue-700', 'border-b-2', 'border-blue-600');
                this.classList.remove('text-slate-500');
                const filter = this.dataset.filter;
                document.querySelectorAll('.replacement-row').forEach(row => {
                    row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
                });
            });
        });

        // Search
        document.getElementById('searchInput').addEventListener('input', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.replacement-row').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
            });
        });

        // Modal helpers
            function closeModal(id) {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            }

        // View Modal
        function openViewModal(id) {
            document.querySelectorAll('.modal-content').forEach(c => c.classList.add('hidden'));
            const content = document.getElementById('view-' + id);
            if (content) content.classList.remove('hidden');
            document.getElementById('viewModal').classList.remove('hidden');
        }

        // Approve Modal
        function openApproveModal(id) {
            document.getElementById('approveForm').action = `/admin/replacements/${id}/approve`;
            document.getElementById('approveModal').classList.remove('hidden');
        }

// Link Asset Modal — generates code + prefills from old asset
async function openLinkModal(id, oldName = '', oldCategory = '', oldLocation = '') {
    ['viewModal', 'approveModal', 'receivedModal', 'deleteModal'].forEach(mid => {
        document.getElementById(mid)?.classList.add('hidden');
    });
    const form = document.getElementById('linkForm');
    const modal = document.getElementById('linkModal');
    if (!form || !modal) {
        console.error('linkForm or linkModal not found in the page');
        alert('Link modal is missing on this page. Check the Blade HTML.');
        return;
    }

        form.action = `/admin/replacements/${id}/link`;

        // Only set code — do NOT show QR yet
        try { regenerateCodeOnly(); } catch (e) { console.warn(e); }

        // Hide QR section every time modal opens
        const qrSection = document.getElementById('linkQrSection');
        const genWrap = document.getElementById('linkQrGenerateWrap');
        if (qrSection) qrSection.classList.add('hidden');
        if (genWrap) genWrap.classList.remove('hidden');
        const qrImg = document.getElementById('generatedQrImg');
        if (qrImg) qrImg.src = '';

    // Reset
    ['link_asset_name','link_location','link_serial','link_price','link_acquired',
     'link_supplier','link_lifespan','link_interval'].forEach(fid => {
        const el = document.getElementById(fid);
        if (el) el.value = '';
    });
    const cond = document.getElementById('link_condition');
    if (cond) cond.value = 'New';
    const war = document.getElementById('link_warranty');
    if (war) war.value = '12';

    // Immediate prefill from row
    if (oldName) {
        const n = document.getElementById('link_asset_name');
        if (n) n.value = oldName;
    }
    if (oldCategory) {
        const catHidden = document.getElementById('link_category');
        const catDisplay = document.getElementById('link_category_display');
        if (catHidden) catHidden.value = oldCategory;
        if (catDisplay) catDisplay.value = oldCategory;
    }
    if (oldLocation) {
        const loc = document.getElementById('link_location');
        if (loc) loc.value = oldLocation;
    }
    const acquired = document.getElementById('link_acquired');
    if (acquired) acquired.value = new Date().toISOString().slice(0, 10);

    // API prefill (extra fields)
    try {
        const res = await fetch(`/admin/replacements/${id}/old-asset`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (res.ok) {
            const data = await res.json();
            const a = data.asset || {};
            if (a.Asset_name) {
                const n = document.getElementById('link_asset_name');
                if (n) n.value = a.Asset_name;
            }
            if (a.Category) {
                const catHidden = document.getElementById('link_category');
                const catDisplay = document.getElementById('link_category_display');
                if (catHidden) catHidden.value = a.Category;
                if (catDisplay) catDisplay.value = a.Category;
            }
            if (a.asset_location) {
                const loc = document.getElementById('link_location');
                if (loc) loc.value = a.asset_location;
            }
            if (a.purchase_Price != null) {
                const p = document.getElementById('link_price');
                if (p) p.value = a.purchase_Price;
            }
            if (a.supplier) {
                const s = document.getElementById('link_supplier');
                if (s) s.value = a.supplier;
            }
            if (a.warranty_months) {
                const w = document.getElementById('link_warranty');
                if (w) w.value = a.warranty_months;
            }
            if (a.lifespan_months) {
                const l = document.getElementById('link_lifespan');
                if (l) l.value = a.lifespan_months;
            }
            if (a.maintenance_interval) {
                const m = document.getElementById('link_interval');
                if (m) m.value = a.maintenance_interval;
            }
        }
    } catch (e) {
        console.warn('Could not prefill old asset', e);
    }

    modal.classList.remove('hidden');
}

        // AJAX submit for Create & Link form
        document.getElementById('linkForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            // Block submit if QR was not generated
                const qrSection = document.getElementById('linkQrSection');
                if (!qrSection || qrSection.classList.contains('hidden')) {
                    alert('Please generate the QR code before creating the asset.');
                    return;
                }

            const form = this;
            const action = form.action;
            const fd = new FormData(form);
            try {
                    // attach CSRF and JSON accept headers to ensure Laravel returns JSON
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : (fd.get('_token') || '');
                    const headers = {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    };
                    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

                    const res = await fetch(action, { method: 'POST', body: fd, credentials: 'same-origin', headers });
                    const ctype = res.headers.get('content-type') || '';
                    let data;
                    if (!res.ok) {
                        // try parse JSON error, otherwise read text
                        if (ctype.includes('application/json')) {
                            const err = await res.json();
                            throw new Error(err.message || JSON.stringify(err));
                        } else {
                            const txt = await res.text();
                            throw new Error('Request failed: ' + res.status + '\n' + (txt.substring ? txt.substring(0, 200) : txt));
                        }
                    }
                    if (ctype.includes('application/json')) {
                        data = await res.json();
                    } else {
                        const txt = await res.text();
                        throw new Error('Unexpected non-JSON response:\n' + (txt.substring ? txt.substring(0, 200) : txt));
                    }
                    if (!data || !data.success) throw new Error(data?.message || 'Unknown error');

                    // Update the replacement row inline
                    const rid = data.replacement_id;
                    const row = document.querySelector(`.replacement-row[data-id="${rid}"]`);
                    if (row) {
                        // New Asset Code cell is the second td
                        const newCell = row.children[1];
                        if (newCell) {
                            newCell.innerHTML = `
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="ri-computer-line text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900 font-mono text-[13px]">${data.asset.code}</p>
                                        <p class="text-xs text-slate-400 mt-0.5">${data.asset.name || ''}</p>
                                    </div>
                                </div>
                            `;
                        }

                    // Rebuild actions — horizontal + Mark Received
                    const actionsCell = row.children[row.children.length - 1];
                    if (actionsCell) {
                        let html = `
                            <div class="flex flex-row flex-nowrap items-center gap-1.5 whitespace-nowrap">
                                <button type="button" onclick="openViewModal(${rid})"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-colors"
                                    title="View Details">
                                    <i class="ri-eye-line text-sm"></i>
                                </button>
                                <button type="button" onclick="openReceivedModal(${rid})"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center bg-teal-50 text-teal-600 rounded-lg hover:bg-teal-600 hover:text-white transition-colors"
                                    title="Mark as Received">
                                    <i class="ri-check-double-line text-sm"></i>
                                </button>
                                <a href="/admin/assets/${data.asset.id}" target="_blank"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition-colors"
                                    title="View New Asset">
                                    <i class="ri-external-link-line text-sm"></i>
                                </a>`;

                        if (data.asset.qr_url) {
                            const q = (s) => String(s ?? '').replace(/"/g, '&quot;');
                            html += `
                                <button type="button"
                                    class="js-dl-qr w-8 h-8 shrink-0 flex items-center justify-center bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors"
                                    data-qr-dl="${q(data.asset.qr_url)}"
                                    data-qr-code="${q(data.asset.code || 'qr')}"
                                    title="Download QR">
                                    <i class="ri-download-line text-sm"></i>
                                </button>
                                <button type="button"
                                    class="js-print-qr w-8 h-8 shrink-0 flex items-center justify-center bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors"
                                    data-qr-print="${q(data.asset.qr_url)}"
                                    data-code="${q(data.asset.code)}"
                                    data-name="${q(data.asset.name || 'ASSET')}"
                                    data-location="${q(data.asset.location || '—')}"
                                    data-category="${q(data.asset.category || '—')}"
                                    data-acquired="${q(data.asset.acquired || '—')}"
                                    title="Print QR">
                                    <i class="ri-printer-line text-sm"></i>
                                </button>`;
                        }

                        html += `
                                <button type="button" onclick="confirmDelete(${rid})"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center bg-red-50 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition-colors"
                                    title="Delete">
                                    <i class="ri-delete-bin-line text-sm"></i>
                                </button>
                            </div>`;

                        actionsCell.innerHTML = html;

                        actionsCell.querySelectorAll('.js-dl-qr').forEach(btn => {
                            btn.addEventListener('click', () =>
                                downloadUrl(btn.dataset.qrDl, btn.dataset.qrCode)
                            );
                        });
                        actionsCell.querySelectorAll('.js-print-qr').forEach(btn => {
                            btn.addEventListener('click', () =>
                                printUrl(btn.dataset.qrPrint, {
                                    code:     btn.dataset.code,
                                    name:     btn.dataset.name,
                                    location: btn.dataset.location,
                                    category: btn.dataset.category,
                                    acquired: btn.dataset.acquired
                                })
                            );
                        });
                    }
                }

                closeModal('linkModal');
            } catch (err) {
                console.error('Link submit error', err);
                alert('Failed to create and link asset: ' + (err.message || err));
            }
        });

        // Generate asset code: AST-XXXXXXXX-XXXX
        function generateCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            const part1 = Array.from({length: 8}, () => chars[Math.floor(Math.random() * chars.length)]).join('');
            const part2 = Array.from({length: 4}, () => chars[Math.floor(Math.random() * chars.length)]).join('');
            return `AST-${part1}-${part2}`;
        }

            function regenerateCodeOnly() {
                const code = generateCode();
                const codeEl = document.getElementById('generatedCode');
                const hidden = document.getElementById('assetCodeHidden');
                if (codeEl) codeEl.textContent = code;
                if (hidden) hidden.value = code;

                // If QR was already visible, refresh it
                const qrSection = document.getElementById('linkQrSection');
                if (qrSection && !qrSection.classList.contains('hidden')) {
                    generateLinkQr();
                }
            }

                function generateLinkQr() {
                    // Required fields before QR can be generated
                    const name = document.getElementById('link_asset_name')?.value?.trim();
                    const category = document.getElementById('link_category')?.value?.trim()
                        || document.getElementById('link_category_display')?.value?.trim();
                    const condition = document.getElementById('link_condition')?.value?.trim();
                    const acquired = document.getElementById('link_acquired')?.value?.trim();

                    if (!name) {
                        alert('Please enter Asset Name before generating the QR.');
                        document.getElementById('link_asset_name')?.focus();
                        return;
                    }
                    if (!category) {
                        alert('Category is missing. Close and open Link again, or check the old asset.');
                        return;
                    }
                    if (!condition) {
                        alert('Condition is required.');
                        return;
                    }
                    if (!acquired) {
                        alert('Please set Acquisition Date before generating the QR.');
                        document.getElementById('link_acquired')?.focus();
                        return;
                    }

                    const code = document.getElementById('assetCodeHidden')?.value
                        || document.getElementById('generatedCode')?.textContent;
                    if (!code || code === '—') {
                        alert('No asset code yet. Click Regenerate code first.');
                        return;
                    }

                    const img = document.getElementById('generatedQrImg');
                    if (img) {
                        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(code);
                    }

                    document.getElementById('linkQrSection')?.classList.remove('hidden');
                    document.getElementById('linkQrGenerateWrap')?.classList.add('hidden');
                }

            // Keep regenerateCode as alias for the button
            function regenerateCode() {
                regenerateCodeOnly();
            }

        // Download generated QR image as PNG
        async function downloadGeneratedQr() {
            const img = document.getElementById('generatedQrImg');
            if (!img || !img.src) return alert('No QR available to download');
            try {
                const res = await fetch(img.src);
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const code = document.getElementById('assetCodeHidden')?.value || 'qr';
                a.download = code + '.png';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (e) {
                console.error('Download QR failed', e);
                alert('Unable to download QR image');
            }
        }

        function buildStickerHtml({ qrSrc, code, name, location, category, acquired }) {
            const safe = (v) => String(v ?? '—')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;');

            return '<!DOCTYPE html><html><head><title>QR Sticker</title><style>'
                + '@page{size:90mm 40mm;margin:0}*{margin:0;padding:0;box-sizing:border-box}'
                + 'body{font-family:Arial,Helvetica,sans-serif}'
                + '.tag{width:90mm;height:40mm;border:0.45mm solid #111;display:flex;flex-direction:column;overflow:hidden}'
                + '.header{text-align:center;padding:1.2mm 2mm 0.8mm;border-bottom:0.35mm solid #111}'
                + '.header .office{font-size:6pt;font-weight:700;letter-spacing:0.5px}'
                + '.header .name{font-size:8.5pt;font-weight:700;text-transform:uppercase;margin-top:0.3mm}'
                + '.header .campus{font-size:5.5pt;color:#444}'
                + '.body{flex:1;display:grid;grid-template-columns:1fr 25mm;min-height:0}'
                + '.info{padding:1.2mm 2mm;display:flex;flex-direction:column;justify-content:center;gap:1.2mm;border-right:0.35mm solid #111}'
                + '.code{font-family:Courier New,monospace;font-size:7.5pt;font-weight:700}'
                + '.row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.8mm}'
                + '.cell{border:0.25mm solid #888;padding:0.6mm 0.5mm;text-align:center;font-size:5.5pt;line-height:1.15}'
                + '.cell span{display:block;font-size:4.5pt;color:#555;font-weight:600}'
                + '.qr{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1mm}'
                + '.qr img{width:20mm;height:20mm}.qr span{font-size:4.5pt;margin-top:0.3mm}'
                + '.footer{text-align:center;font-size:5.5pt;font-weight:700;color:#c00;padding:0.7mm;border-top:0.35mm solid #111;letter-spacing:0.4px}'
                + '@media print{body{margin:0}}'
                + '</style></head><body><div class="tag">'
                + '<div class="header"><div class="office">ASSET MANAGEMENT OFFICE</div>'
                + '<div class="name">' + safe(name || 'ASSET') + '</div>'
                + '<div class="campus">NU LIPA</div></div>'
                + '<div class="body"><div class="info"><div class="code">' + safe(code) + '</div>'
                + '<div class="row">'
                + '<div class="cell"><span>Location</span>' + safe(location) + '</div>'
                + '<div class="cell"><span>Category</span>' + safe(category) + '</div>'
                + '<div class="cell"><span>Acquired</span>' + safe(acquired) + '</div>'
                + '</div></div>'
                + '<div class="qr"><img src="' + qrSrc + '" alt="QR"><span>Scan me</span></div>'
                + '</div><div class="footer">DO NOT REMOVE THIS TAG</div></div>'
                + '<script>window.onload=function(){window.print();setTimeout(function(){window.close()},500)};<\/script>'
                + '</body></html>';
        }
                    function printGeneratedQr() {
                        const img = document.getElementById('generatedQrImg');
                        if (!img || !img.src) return alert('No QR available to print');

                        const code = document.getElementById('assetCodeHidden')?.value
                                || document.getElementById('generatedCode')?.textContent
                                || 'ASSET';
                        const name = document.getElementById('link_asset_name')?.value || 'REPLACEMENT ASSET';
                        const location = document.getElementById('link_location')?.value || '—';
                        const category = document.getElementById('link_category')?.value || '—';
                        const acquired = document.getElementById('link_acquired')?.value
                            ? new Date(document.getElementById('link_acquired').value).toLocaleDateString('en-US')
                            : new Date().toLocaleDateString('en-US');

                        const html = buildStickerHtml({ qrSrc: img.src, code, name, location, category, acquired });
                        const w = window.open('', '_blank', 'width=500,height=300');
                        w.document.write(html);
                        w.document.close();
                    }

                    function previewLinkImage(input) {
                const preview = document.getElementById('link_photo_preview');
                const img = document.getElementById('link_preview_img');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        preview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(input.files[0]);
                } else {
                    img.src = '';
                    preview.classList.add('hidden');
                }
            }

            function removeLinkPreview() {
                const input = document.getElementById('link_asset_photo');
                const preview = document.getElementById('link_photo_preview');
                const img = document.getElementById('link_preview_img');
                try { input.value = ''; } catch (e) {}
                img.src = '';
                preview.classList.add('hidden');
            }

        // Download arbitrary URL (used for asset QR downloads)
        async function downloadUrl(url, filename) {
            if (!url) return alert('No file URL provided');
            try {
                const res = await fetch(url);
                if (!res.ok) throw new Error('Failed to fetch');
                const blob = await res.blob();
                const u = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = u;
                a.download = (filename || 'file') + '.png';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(u);
            } catch (e) {
                console.error('Download failed', e);
                alert('Unable to download file');
            }
        }

            // Print from table action button (after link)
            // Call as: printUrl(qrUrl, { code, name, location, category, acquired })
            function printUrl(url, meta = {}) {
                if (!url) return alert('No URL provided');

                const html = buildStickerHtml({
                    qrSrc: url,
                    code:     meta.code     || 'ASSET',
                    name:     meta.name     || 'ASSET',
                    location: meta.location || '—',
                    category: meta.category || '—',
                    acquired: meta.acquired || '—'
                });

                const w = window.open('', '_blank', 'width=500,height=300');
                w.document.write(html);
                w.document.close();
            }

                // Mark Received Modal
            function openReceivedModal(id) {
                // Always hide every other overlay first
                ['linkModal', 'viewModal', 'approveModal', 'deleteModal'].forEach(mid => {
                    const el = document.getElementById(mid);
                    if (el) el.classList.add('hidden');
                });

                const form = document.getElementById('receivedForm');
                const modal = document.getElementById('receivedModal');

                if (!form || !modal) {
                    console.error('receivedForm or receivedModal missing from the page');
                    alert('Received modal is missing. Check that #receivedModal is not inside #linkModal.');
                    return;
                }

                form.action = `/admin/replacements/${id}/received`;
                modal.classList.remove('hidden');
            }

        // Delete Modal
        function confirmDelete(id) {
            document.getElementById('deleteForm').action = `/admin/replacements/${id}`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
    </script>

@endsection