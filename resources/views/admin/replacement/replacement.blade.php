@extends('layouts.admin_sidebar')

@section('title', 'Replacement Records')

@section('content')

<style>
    :root{
        --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
        --gold-500:#C9A227; --gold-600:#A8841E; --gold-100:#F3E7C4;
        --paper:#F3EEE0; --paper-2:#EAE2C9;
        --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
        --line:#DED2AE;
        --forest:#2F7A4D; --forest-dark:#245C3B; --forest-tint:#EAF4EE;
        --bronze:#B4791E; --bronze-dark:#8F5F16; --bronze-tint:#FBF1DE;
        --steel:#2E5C8A; --steel-dark:#234869; --steel-tint:#E9F0F7;
        --brick:#A23B32; --brick-dark:#7E2E27; --brick-tint:#F7E9E6;
        --plum:#6B4C82; --plum-dark:#523A64; --plum-tint:#EFE7F3;
        --teal:#1E7A72; --teal-dark:#155850; --teal-tint:#E3F3F1;
    }
    body{ background:var(--paper) !important; font-family:'Inter',system-ui,-apple-system,sans-serif; color:var(--ink-900); }
    .font-display{ font-family:'Fraunces',serif; }
    .font-mono{ font-family:'IBM Plex Mono',monospace; }
    .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-400); }
    .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
    .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }
    .search-input{ border:1px solid var(--line); transition:border-color .15s, box-shadow .15s; }
    .search-input:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .avatar-badge{ background:var(--navy-950); color:var(--gold-500); border:1px solid var(--gold-500); }
    .stat-card{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
    .panel{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
    .filter-tab{ transition: all 0.15s ease; color:var(--ink-400); position:relative; }
    .filter-tab.active{ color:var(--navy-900) !important; font-weight:600; }
    .filter-tab.active::after{ content:""; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:var(--gold-500); }
    .replacement-row{ transition: background-color 0.15s ease; }
    .replacement-row:hover{ background-color: var(--paper-2); }
    .btn-gold{ background:var(--gold-500); color:var(--navy-950); font-weight:600; transition:filter .15s; }
    .btn-gold:hover{ filter:brightness(1.06); }
    .form-input{ border:1px solid var(--line); transition:border-color .15s, box-shadow .15s; }
    .form-input:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
    .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(12px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .modal-panel { animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
</style>

            <!-- Header -->
            <div class="topbar sticky top-0 z-10">
                <div class="px-4 sm:px-8 py-5">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <div class="flex items-center">
                            <!-- Hamburger, mobile only -->
                            <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-400);">
                                <i class="ri-menu-line text-2xl"></i>
                            </button>
                            <div>
                                <h2 class="font-display text-xl sm:text-2xl font-semibold tracking-tight" style="color:var(--navy-900);">Replacement Records</h2>
                                <p class="text-sm mt-1 hidden sm:block" style="color:var(--ink-600);">Manage and track all asset replacement requests</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative flex-1 sm:flex-none">
                                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400);"></i>
                                <input type="text" id="searchInput" placeholder="Search replacements..."
                                    class="search-input pl-9 pr-4 py-2.5 rounded-lg text-sm w-full sm:w-56"/>
                            </div>
                            <div class="relative cursor-pointer flex-shrink-0" style="color:var(--ink-400);">
                                <i class="ri-notification-3-line text-xl"></i>
                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full ring-2 ring-white" style="background:var(--brick);"></span>
                            </div>
                            <div class="flex items-center space-x-2 cursor-pointer rounded-lg px-2 py-1 flex-shrink-0" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                                <div class="avatar-badge w-8 h-8 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-semibold">
                                        {{ strtoupper(substr(Auth::user()->full_name ?? 'A', 0, 1)) }}
                                    </span>
                                </div>
                                <i class="ri-arrow-down-s-line hidden sm:block" style="color:var(--ink-400);"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <div class="p-4 sm:p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
            <div class="stat-card p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="eyebrow">Total</p>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--steel-tint);">
                        <i class="ri-refresh-line" style="color:var(--steel);"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold mt-1" style="color:var(--navy-900);">{{ $totalReplacements ?? 0 }}</p>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="eyebrow">Pending</p>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--bronze-tint);">
                        <i class="ri-time-line" style="color:var(--bronze);"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold mt-1" style="color:var(--navy-900);">{{ $pendingReplacements ?? 0 }}</p>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="eyebrow">Approved</p>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--gold-100);">
                        <i class="ri-checkbox-circle-line" style="color:var(--gold-600);"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold mt-1" style="color:var(--navy-900);">{{ $approvedReplacements ?? 0 }}</p>
            </div>
            <div class="stat-card p-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="eyebrow">Received</p>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--forest-tint);">
                        <i class="ri-check-double-line" style="color:var(--forest);"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold mt-1" style="color:var(--navy-900);">{{ $receivedReplacements ?? 0 }}</p>
            </div>
        </div>

        <!-- Replacement Table -->
        <div class="panel">

            <!-- Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 sm:px-6 pt-4 pb-0" style="border-bottom:1px solid var(--line);">
            <div class="flex space-x-1 overflow-x-auto scrollbar-hide">
                <button class="filter-tab active px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="all">All</button>
                <button class="filter-tab px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="Pending">Pending</button>
                <button class="filter-tab px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="Approved">Approved</button>
                <button class="filter-tab px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="Received">Received</button>
            </div>
            <p class="text-xs pb-3" style="color:var(--ink-400);">{{ $replacements->count() ?? 0 }} records</p>
        </div>

                    <div class="overflow-x-auto">
            <table class="w-full text-sm">
                    <thead>
                        <tr style="background:var(--paper-2);">
                            <th class="eyebrow px-6 py-3.5 text-left">Old Asset Code</th>
                            <th class="eyebrow px-6 py-3.5 text-left">New Asset Code</th>
                            <th class="eyebrow px-6 py-3.5 text-left">Requested By</th>
                            <th class="eyebrow px-6 py-3.5 text-left">Reason</th>
                            <th class="eyebrow px-6 py-3.5 text-left">Progress</th>
                            <th class="eyebrow px-6 py-3.5 text-left">Status</th>
                            <th class="eyebrow px-6 py-3.5 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="border-top:1px solid var(--line);">

                        @forelse($replacements ?? [] as $replacement)
                        <tr class="replacement-row"
                            style="border-bottom:1px solid var(--line);"
                            data-status="{{ $replacement->status }}"
                            data-id="{{ data_get($replacement, 'id') ?? data_get($replacement, 'Replacement_id') }}">

                            {{-- Old Asset --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:var(--brick-tint);">
                                        <i class="ri-computer-line text-sm" style="color:var(--brick);"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium font-mono text-[13px]" style="color:var(--navy-900);">{{ data_get($replacement, 'old_asset_code') ?? ($replacement->oldAsset->Asset_code ?? '—') }}</p>
                                        <p class="text-xs mt-0.5" style="color:var(--ink-400);">{{ data_get($replacement, 'old_asset_name') ?? ($replacement->oldAsset->Asset_name ?? '') }}</p>
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
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:var(--forest-tint);">
                                        <i class="ri-computer-line text-sm" style="color:var(--forest);"></i>
                                    </div>
                                    <div>
                                    <p class="font-medium font-mono text-[13px]" style="color:var(--navy-900);">{{ data_get($replacement, 'new_asset_code') ?? data_get($replacement, 'newAsset.Asset_code') ?? '—' }}</p>
                                    <p class="text-xs mt-0.5" style="color:var(--ink-400);">{{ data_get($replacement, 'new_asset_name') ?? data_get($replacement, 'newAsset.Asset_name') ?? '' }}</p>
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
                                        class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                                        style="border:1px dashed var(--line); color:var(--ink-400);"
                                        onmouseover="this.style.borderColor='var(--gold-500)'; this.style.color='var(--gold-600)'; this.style.background='var(--gold-100)'"
                                        onmouseout="this.style.borderColor='var(--line)'; this.style.color='var(--ink-400)'; this.style.background='transparent'">
                                        <i class="ri-link mr-1"></i>
                                        Link new asset
                                    </button>
                                @else
                            <span class="text-xs" style="color:var(--ink-400);">—</span>
                            @endif
                            @endif
                        </td>

                            {{-- Requested By --}}
                            <td class="px-6 py-4">
                                <p class="font-medium" style="color:var(--navy-900);">{{ data_get($replacement, 'requested_by') ?? ($replacement->request->user->full_name ?? '—') }}</p>
                                <p class="text-xs" style="color:var(--ink-400);">{{ data_get($replacement, 'department') ?? ($replacement->request->user->department ?? '—') }}</p>
                            </td>

                            {{-- Reason --}}
                            <td class="px-6 py-4">
                                <p class="max-w-xs truncate" style="color:var(--ink-600);">{{ $replacement->reason ?? '—' }}</p>
                            </td>

                            {{-- Progress Steps --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-1">
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold"
                                            style="{{ in_array($replacement->status, ['Pending','Approved','Received']) ? 'background:var(--navy-900); color:#fff;' : 'background:var(--paper-2); color:var(--ink-400);' }}">
                                            1
                                        </div>
                                        <span class="text-[10px] mt-1" style="color:var(--ink-400);">Pending</span>
                                    </div>
                                    <div class="w-6 h-0.5 mb-4" style="background:{{ in_array($replacement->status, ['Approved','Received']) ? 'var(--navy-900)' : 'var(--paper-2)' }};"></div>
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold"
                                            style="{{ in_array($replacement->status, ['Approved','Received']) ? 'background:var(--navy-900); color:#fff;' : 'background:var(--paper-2); color:var(--ink-400);' }}">
                                            2
                                        </div>
                                        <span class="text-[10px] mt-1" style="color:var(--ink-400);">Approved</span>
                                    </div>
                                    <div class="w-6 h-0.5 mb-4" style="background:{{ $replacement->status === 'Received' ? 'var(--forest)' : 'var(--paper-2)' }};"></div>
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold"
                                            style="{{ $replacement->status === 'Received' ? 'background:var(--forest); color:#fff;' : 'background:var(--paper-2); color:var(--ink-400);' }}">
                                            3
                                        </div>
                                        <span class="text-[10px] mt-1" style="color:var(--ink-400);">Received</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                    style="
                                    @if($replacement->status == 'Pending') background:var(--bronze-tint); color:var(--bronze-dark);
                                    @elseif($replacement->status == 'Approved') background:var(--gold-100); color:var(--gold-600);
                                    @elseif($replacement->status == 'Received') background:var(--forest-tint); color:var(--forest-dark);
                                    @else background:var(--paper-2); color:var(--ink-600);
                                    @endif">
                                    {{ $replacement->status ?? '—' }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4">
                                <div class="flex flex-row flex-nowrap items-center gap-1.5 whitespace-nowrap">
                                    <button type="button" onclick="openViewModal({{ $replacement->id }})"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                        style="background:var(--steel-tint); color:var(--steel);"
                                        onmouseover="this.style.background='var(--steel)'; this.style.color='#fff'"
                                        onmouseout="this.style.background='var(--steel-tint)'; this.style.color='var(--steel)'"
                                        title="View Details">
                                        <i class="ri-eye-line text-sm"></i>
                                    </button>

                                    @if($replacement->status === 'Pending')
                                    <button type="button" onclick="openApproveModal({{ $replacement->id }})"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                        style="background:var(--forest-tint); color:var(--forest);"
                                        onmouseover="this.style.background='var(--forest)'; this.style.color='#fff'"
                                        onmouseout="this.style.background='var(--forest-tint)'; this.style.color='var(--forest)'"
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
                                            class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                            style="background:var(--teal-tint); color:var(--teal);"
                                            onmouseover="this.style.background='var(--teal)'; this.style.color='#fff'"
                                            onmouseout="this.style.background='var(--teal-tint)'; this.style.color='var(--teal)'"
                                            title="Mark as Received">
                                            <i class="ri-check-double-line text-sm"></i>
                                        </button>
                                        @endif

                                        @if($hasRealNew)
                                        <a href="/admin/assets/{{ $newId }}" target="_blank"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                        style="background:var(--plum-tint); color:var(--plum);"
                                        onmouseover="this.style.background='var(--plum)'; this.style.color='#fff'"
                                        onmouseout="this.style.background='var(--plum-tint)'; this.style.color='var(--plum)'"
                                        title="View New Asset">
                                        <i class="ri-external-link-line text-sm"></i>
                                    </a>

                                    @if(data_get($replacement, 'new_asset_qr'))
                                        <button type="button"
                                            onclick="downloadUrl('{{ \Illuminate\Support\Facades\Storage::url(data_get($replacement, 'new_asset_qr')) }}', '{{ data_get($replacement, 'new_asset_code') ?? 'qr' }}')"
                                            class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                            style="background:var(--paper-2); color:var(--ink-600);"
                                            onmouseover="this.style.background='var(--line)'"
                                            onmouseout="this.style.background='var(--paper-2)'"
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
                                            class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                            style="background:var(--paper-2); color:var(--ink-600);"
                                            onmouseover="this.style.background='var(--line)'"
                                            onmouseout="this.style.background='var(--paper-2)'"
                                            title="Print QR">
                                            <i class="ri-printer-line text-sm"></i>
                                        </button>
                                    @endif
                                    @endif

                                    <button type="button" onclick="confirmDelete({{ $replacement->id }})"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                        style="background:var(--brick-tint); color:var(--brick);"
                                        onmouseover="this.style.background='var(--brick)'; this.style.color='#fff'"
                                        onmouseout="this.style.background='var(--brick-tint)'; this.style.color='var(--brick)'"
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
                                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-3" style="background:var(--paper-2);">
                                        <i class="ri-refresh-line text-2xl" style="color:var(--ink-400);"></i>
                                    </div>
                                    <p class="font-medium text-sm" style="color:var(--ink-900);">No replacement records found</p>
                                    <p class="text-xs mt-1" style="color:var(--ink-400);">Replacement requests will appear here once submitted</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            @if(isset($replacements) && method_exists($replacements, 'hasPages') && $replacements->hasPages())
            <div class="px-6 py-4" style="border-top:1px solid var(--line);">
                {{ $replacements->links() }}
            </div>
            @endif
        </div>

        <div class="text-center text-xs mt-10 pt-6" style="color:var(--ink-400); border-top:1px solid var(--line);">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);">
        <div class="modal-panel rounded-2xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head px-6 py-5 flex justify-between items-center sticky top-0 z-10">
                <h3 class="font-display text-lg font-semibold text-white">Replacement Details</h3>
                <button onclick="closeModal('viewModal')" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="p-6" id="viewModalContent">
                @foreach($replacements ?? [] as $r)
                <div id="view-{{ $r->id }}" class="modal-content hidden space-y-5">
                    {{-- Progress bar --}}
                    <div>
                        <div class="flex items-center space-x-2 flex-1 mb-1">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                style="{{ in_array($r->status, ['Pending','Approved','Received']) ? 'background:var(--navy-900); color:#fff;' : 'background:var(--paper-2); color:var(--ink-400);' }}">1</div>
                            <div class="flex-1 h-1 rounded" style="background:{{ in_array($r->status, ['Approved','Received']) ? 'var(--navy-900)' : 'var(--paper-2)' }};"></div>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                style="{{ in_array($r->status, ['Approved','Received']) ? 'background:var(--navy-900); color:#fff;' : 'background:var(--paper-2); color:var(--ink-400);' }}">2</div>
                            <div class="flex-1 h-1 rounded" style="background:{{ $r->status === 'Received' ? 'var(--forest)' : 'var(--paper-2)' }};"></div>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                style="{{ $r->status === 'Received' ? 'background:var(--forest); color:#fff;' : 'background:var(--paper-2); color:var(--ink-400);' }}">3</div>
                        </div>
                        <div class="flex justify-between text-xs" style="color:var(--ink-400);">
                            <span>Pending</span><span>Approved</span><span>Received</span>
                        </div>
                    </div>

                    {{-- Old vs New Asset --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl p-4" style="background:var(--brick-tint); border:1px solid #E7C9C1;">
                            <p class="text-xs font-semibold mb-2 flex items-center" style="color:var(--brick-dark);">
                                <i class="ri-arrow-left-line mr-1"></i> Old Asset
                            </p>
                            <p class="font-semibold text-sm" style="color:var(--navy-900);">{{ data_get($r, 'old_asset_name') ?? ($r->oldAsset->Asset_name ?? '—') }}</p>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--ink-400);">{{ data_get($r, 'old_asset_code') ?? ($r->oldAsset->Asset_code ?? '—') }}</p>
                            <p class="text-xs mt-1.5" style="color:var(--ink-600);">{{ data_get($r, 'old_asset_category') ?? ($r->oldAsset->Category ?? '—') }}</p>
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-full mt-2 inline-block" style="background:#F0D4CE; color:var(--brick-dark);">
                                {{ data_get($r, 'old_asset_lifecycle_status') ?? ($r->oldAsset->Lifecycle_Status ?? '—') }}
                            </span>
                        </div>
                        <div class="rounded-xl p-4" style="background:var(--forest-tint); border:1px solid #BFDEC7;">
                            <p class="text-xs font-semibold mb-2 flex items-center" style="color:var(--forest-dark);">
                                <i class="ri-arrow-right-line mr-1"></i> New Asset
                            </p>
                            @php
                            $hasRealNewAsset = data_get($r, 'new_asset_id')
                            && data_get($r, 'new_asset_id') != data_get($r, 'old_asset_id');
                            @endphp

                            @if($hasRealNewAsset)
                            <p class="font-semibold text-sm" style="color:var(--navy-900);">{{ data_get($r, 'new_asset_name') ?? data_get($r, 'newAsset.Asset_name') ?? '—' }}</p>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--ink-400);">{{ data_get($r, 'new_asset_code') ?? data_get($r, 'newAsset.Asset_code') ?? '—' }}</p>
                            <p class="text-xs mt-1.5" style="color:var(--ink-600);">{{ data_get($r, 'new_asset_category') ?? data_get($r, 'newAsset.Category') ?? '—' }}</p>
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-full mt-2 inline-block" style="background:#CFE7D6; color:var(--forest-dark);">
                            {{ data_get($r, 'new_asset_lifecycle_status') ?? data_get($r, 'newAsset.Lifecycle_Status') ?? '—' }}
                            </span>
                            @else
                            <p class="text-xs italic mt-4" style="color:var(--ink-400);">Not yet assigned</p>
                            @endif
                        </div>
                    </div>

                    {{-- Details --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                            <p class="text-xs mb-1" style="color:var(--ink-400);">Requested By</p>
                            <p class="text-sm font-medium" style="color:var(--navy-900);">{{ data_get($r, 'requested_by') ?? ($r->request->user->full_name ?? '—') }}</p>
                            <p class="text-xs" style="color:var(--ink-400);">{{ data_get($r, 'department') ?? ($r->request->user->department ?? '—') }}</p>
                        </div>
                        <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                            <p class="text-xs mb-1" style="color:var(--ink-400);">Approved By</p>
                            <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $r->Approve_by ?? '—' }}</p>
                        </div>
                        <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                            <p class="text-xs mb-1" style="color:var(--ink-400);">Replacement Date</p>
                            <p class="text-sm font-medium" style="color:var(--navy-900);">
                                {{ $r->Replacement_Date ? \Carbon\Carbon::parse($r->Replacement_Date)->format('M d, Y') : '—' }}
                            </p>
                        </div>
                        <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                            <p class="text-xs mb-1" style="color:var(--ink-400);">Submitted On</p>
                            <p class="text-sm font-medium" style="color:var(--navy-900);">
                                {{ data_get($r, 'created_at') ? \Carbon\Carbon::parse(data_get($r, 'created_at'))->format('M d, Y') : '—' }}
                            </p>
                        </div>
                    </div>

                    @if($r->reason)
                    <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                        <p class="text-xs mb-1" style="color:var(--ink-400);">Reason</p>
                        <p class="text-sm" style="color:var(--ink-600);">{{ $r->reason }}</p>
                    </div>
                    @endif

                    @if($r->notes)
                    <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                        <p class="text-xs mb-1" style="color:var(--ink-400);">Notes</p>
                        <p class="text-sm" style="color:var(--ink-600);">{{ $r->notes }}</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="p-6 flex justify-end" style="border-top:1px solid var(--line);">
                <button onclick="closeModal('viewModal')"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium transition-colors" style="border:1px solid var(--line); color:var(--navy-800);" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="hidden fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);">
        <div class="modal-panel rounded-2xl shadow-xl max-w-sm w-full mx-4" style="background:#fff;">
            <div class="modal-head px-6 py-5 flex justify-between items-center">
                <h3 class="font-display text-lg font-semibold text-white">Approve Replacement</h3>
                <button onclick="closeModal('approveModal')" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="p-6 space-y-4">
                    <div class="flex items-start space-x-3 p-3.5 rounded-lg" style="background:var(--forest-tint); border:1px solid #BFDEC7;">
                        <i class="ri-checkbox-circle-line text-lg mt-0.5" style="color:var(--forest);"></i>
                        <p class="text-sm" style="color:var(--forest-dark);">Approving this request will allow you to create and link a new asset for this replacement.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--ink-600);">Notes (optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this approval..."
                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm resize-none"></textarea>
                    </div>
                    <input type="hidden" name="status" value="Approved"/>
                    <input type="hidden" name="Approve_by" value="{{ Auth::user()->full_name ?? '' }}"/>
                </div>
                <div class="p-6 flex justify-end space-x-3" style="border-top:1px solid var(--line);">
                    <button type="button" onclick="closeModal('approveModal')"
                        class="px-4 py-2.5 rounded-lg text-sm font-medium transition-colors" style="border:1px solid var(--line); color:var(--navy-800);" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2.5 text-white rounded-lg text-sm font-medium transition" style="background:var(--forest);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                        <i class="ri-checkbox-circle-line mr-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

            <!-- Link New Asset Modal (full form, prefilled from old asset) -->
            <div id="linkModal" class="hidden fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);">
                <div class="modal-panel rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
                    <div class="modal-head px-6 py-5 flex justify-between items-center sticky top-0 z-10">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-white">Create &amp; Link New Asset</h3>
                            <p class="text-xs mt-0.5" style="color:var(--gold-100);">Pre-filled from old asset — edit as needed, then create</p>
                        </div>
                        <button type="button" onclick="closeModal('linkModal')" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <form id="linkForm" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                            <div class="p-6 space-y-5"> 
                        {{-- New Asset Code + QR (generate on demand) --}}
                        <div class="rounded-xl p-4 mb-4" style="border:1px solid var(--line); background:var(--paper-2);">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="eyebrow">New Asset Code (auto)</p>
                                    <p id="generatedCode" class="text-lg font-bold font-mono mt-0.5" style="color:var(--navy-900);">—</p>
                                    <input type="hidden" name="Asset_code" id="assetCodeHidden" value="">
                                </div>
                                <button type="button" onclick="regenerateCode()"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
                                    style="border:1px solid var(--line); background:#fff; color:var(--ink-600);"
                                    onmouseover="this.style.borderColor='var(--gold-500)'; this.style.color='var(--gold-600)'"
                                    onmouseout="this.style.borderColor='var(--line)'; this.style.color='var(--ink-600)'">
                                    <i class="ri-refresh-line mr-1"></i> Regenerate code
                                </button>
                            </div>

                            {{-- Hidden until Generate QR is clicked --}}
                            <div id="linkQrSection" class="hidden mt-3 pt-3" style="border-top:1px solid var(--line);">
                                <div class="flex items-start gap-4">
                                    <div class="w-28 h-28 rounded-lg flex items-center justify-center overflow-hidden flex-shrink-0" style="background:#fff; border:1px solid var(--line);">
                                        <img id="generatedQrImg" src="" alt="QR" class="w-full h-full object-contain">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs mb-2" style="color:var(--ink-400);">QR for the new code. Updates when you regenerate.</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" onclick="downloadGeneratedQr()"
                                                class="px-3 py-1.5 text-xs font-medium rounded-lg" style="border:1px solid var(--line); background:#fff; color:var(--ink-600);">
                                                <i class="ri-download-line mr-1"></i> Download
                                            </button>
                                            <button type="button" onclick="printGeneratedQr()"
                                                class="px-3 py-1.5 text-xs font-medium rounded-lg" style="border:1px solid var(--line); background:#fff; color:var(--ink-600);">
                                                <i class="ri-printer-line mr-1"></i> Print
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Generate button (shown until QR is generated) --}}
                            <div id="linkQrGenerateWrap" class="mt-3">
                                <button type="button" onclick="generateLinkQr()"
                                    class="btn-gold w-full px-4 py-2.5 rounded-lg text-sm">
                                    <i class="ri-qr-code-line mr-1"></i> Generate QR Code
                                </button>
                                <p class="text-xs mt-1.5 text-center" style="color:var(--ink-400);">Fill in the fields below, then generate the QR.</p>
                            </div>
                            </div>

                                {{-- Prefilled fields --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Asset Name <span style="color:var(--brick);">*</span></label>
                                        <input type="text" name="Asset_name" id="link_asset_name" required
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                            <div>
                                                <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Category</label>
                                                    <input type="text" id="link_category_display" value="" readonly
                                                        class="w-full px-3.5 py-2.5 rounded-lg text-sm cursor-not-allowed" style="border:1px solid var(--line); background:var(--paper-2); color:var(--ink-600);">
                                                    <input type="hidden" name="Category" id="link_category" value="">
                                            </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Condition</label>
                                            <input type="text" value="New" readonly
                                                class="w-full px-3.5 py-2.5 rounded-lg text-sm cursor-not-allowed" style="border:1px solid var(--line); background:var(--paper-2); color:var(--ink-600);">
                                            <input type="hidden" name="Condition" id="link_condition" value="New">
                                        </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Location</label>
                                        <input type="text" name="asset_location" id="link_location"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Serial Number</label>
                                        <input type="text" name="serial_Number" id="link_serial"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Purchase Price</label>
                                        <input type="number" step="0.01" name="purchase_Price" id="link_price"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Acquisition Date</label>
                                        <input type="date" name="accusion_date" id="link_acquired"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Supplier</label>
                                        <input type="text" name="supplier" id="link_supplier"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Warranty (months)</label>
                                        <input type="number" name="warranty_months" id="link_warranty" value="12"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Lifespan (months)</label>
                                        <input type="number" name="lifespan_months" id="link_lifespan"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Maintenance Interval (months)</label>
                                        <input type="number" name="maintenance_interval" id="link_interval"
                                            class="form-input w-full px-3.5 py-2.5 rounded-lg text-sm">
                                    </div>

                                    {{-- Asset Photo --}}
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Asset Photo</label>
                                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 rounded-lg cursor-pointer transition"
                                            style="border:2px dashed var(--line);"
                                            onmouseover="this.style.borderColor='var(--gold-500)'"
                                            onmouseout="this.style.borderColor='var(--line)'"
                                            onclick="document.getElementById('link_asset_photo').click()">
                                            <div class="space-y-1 text-center">
                                                <i class="ri-image-line text-3xl mb-1 block" style="color:var(--ink-400);"></i>
                                                <div class="flex text-sm justify-center" style="color:var(--ink-600);">
                                                    <span class="font-medium" style="color:var(--gold-600);">Upload a file</span>
                                                    <p class="pl-1">or drag and drop</p>
                                                </div>
                                                <p class="text-xs" style="color:var(--ink-400);">PNG, JPG, GIF, WEBP up to 10MB</p>
                                            </div>
                                        </div>
                                        <input id="link_asset_photo" name="asset_photo" type="file" class="hidden" accept="image/*"
                                            onchange="previewLinkImage(this)">
                                        <div id="link_photo_preview" class="mt-3 hidden flex items-start space-x-3">
                                            <img id="link_preview_img" class="h-28 w-auto rounded-lg" style="border:1px solid var(--line);" alt="Preview">
                                            <button type="button" onclick="removeLinkPreview()"
                                                class="px-3 py-1 rounded-lg text-sm" style="background:var(--brick-tint); color:var(--brick); border:1px solid #E7C9C1;">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            <div class="flex items-start space-x-2 p-3.5 rounded-lg" style="background:var(--bronze-tint); border:1px solid #E7CE9C;">
                                <i class="ri-information-line mt-0.5 flex-shrink-0" style="color:var(--bronze);"></i>
                                <p class="text-xs" style="color:var(--bronze-dark);">
                                    The new asset will be created and the requester will be notified that it is ready for pickup.
                                    When the user collects it in person, mark the replacement as <strong>Received</strong> —
                                    that will set the new asset to <strong>Active</strong> and move the old asset to <strong>Pullout</strong>.
                                </p>
                            </div>
                        </div>
                        

                        <div class="p-6 flex justify-end space-x-3 sticky bottom-0" style="border-top:1px solid var(--line); background:#fff;">
                            <button type="button" onclick="closeModal('linkModal')"
                                class="px-4 py-2.5 rounded-lg text-sm font-medium" style="border:1px solid var(--line); color:var(--navy-800);">
                                Cancel
                            </button>
                            <button type="submit"
                                class="btn-gold px-4 py-2.5 rounded-lg text-sm">
                                <i class="ri-add-line mr-1"></i> Create &amp; Link Asset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mark Received Modal -->
            <div id="receivedModal" class="hidden fixed inset-0 backdrop-blur-sm z-[70] flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);">
                <div class="modal-panel rounded-2xl shadow-xl max-w-sm w-full mx-4" style="background:#fff;">
                    <div class="p-6 text-center">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--teal-tint);">
                            <i class="ri-check-double-line text-2xl" style="color:var(--teal);"></i>
                        </div>
                        <h3 class="font-display text-lg font-semibold mb-2" style="color:var(--navy-900);">Mark as Received?</h3>
                        <p class="text-sm leading-relaxed" style="color:var(--ink-600);">
                            Confirming receipt will set the new asset to
                            <span class="font-semibold" style="color:var(--forest);">Active</span>
                            and the old asset to
                            <span class="font-semibold" style="color:var(--bronze-dark);">Pullout</span>.
                        </p>
                    </div>
                    <form id="receivedForm" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="Received"/>
                        <div class="p-6 flex justify-center space-x-3" style="border-top:1px solid var(--line);">
                            <button type="button" onclick="closeModal('receivedModal')"
                                class="px-5 py-2.5 rounded-lg text-sm font-medium" style="border:1px solid var(--line); color:var(--navy-800);">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-5 py-2.5 text-white rounded-lg text-sm font-medium transition" style="background:var(--teal);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                                <i class="ri-check-double-line mr-1"></i> Confirm Received
                            </button>
                        </div>
                    </form>
                </div>
            </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="background:rgba(10,24,48,.55);">
        <div class="modal-panel rounded-2xl shadow-xl max-w-sm w-full mx-4" style="background:#fff;">
            <div class="p-6 text-center">
                <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--brick-tint);">
                    <i class="ri-delete-bin-line text-2xl" style="color:var(--brick);"></i>
                </div>
                <h3 class="font-display text-lg font-semibold mb-2" style="color:var(--navy-900);">Delete Replacement Record?</h3>
                <p class="text-sm" style="color:var(--ink-600);">This action cannot be undone.</p>
            </div>
            <div class="p-6 flex justify-center space-x-3" style="border-top:1px solid var(--line);">
                <button onclick="closeModal('deleteModal')"
                    class="px-5 py-2.5 rounded-lg text-sm font-medium transition-colors" style="border:1px solid var(--line); color:var(--navy-800);" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                    Cancel
                </button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="px-5 py-2.5 text-white rounded-lg text-sm font-medium transition" style="background:var(--brick);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.filter-tab').forEach(t => {
                    t.classList.remove('active');
                });
                this.classList.add('active');
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
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:var(--forest-tint);">
                                        <i class="ri-computer-line text-sm" style="color:var(--forest);"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium font-mono text-[13px]" style="color:var(--navy-900);">${data.asset.code}</p>
                                        <p class="text-xs mt-0.5" style="color:var(--ink-400);">${data.asset.name || ''}</p>
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
                                    class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    style="background:var(--steel-tint); color:var(--steel);"
                                    title="View Details">
                                    <i class="ri-eye-line text-sm"></i>
                                </button>
                                <button type="button" onclick="openReceivedModal(${rid})"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    style="background:var(--teal-tint); color:var(--teal);"
                                    title="Mark as Received">
                                    <i class="ri-check-double-line text-sm"></i>
                                </button>
                                <a href="/admin/assets/${data.asset.id}" target="_blank"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    style="background:var(--plum-tint); color:var(--plum);"
                                    title="View New Asset">
                                    <i class="ri-external-link-line text-sm"></i>
                                </a>`;

                        if (data.asset.qr_url) {
                            const q = (s) => String(s ?? '').replace(/"/g, '&quot;');
                            html += `
                                <button type="button"
                                    class="js-dl-qr w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    style="background:var(--paper-2); color:var(--ink-600);"
                                    data-qr-dl="${q(data.asset.qr_url)}"
                                    data-qr-code="${q(data.asset.code || 'qr')}"
                                    title="Download QR">
                                    <i class="ri-download-line text-sm"></i>
                                </button>
                                <button type="button"
                                    class="js-print-qr w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    style="background:var(--paper-2); color:var(--ink-600);"
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
                                    class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    style="background:var(--brick-tint); color:var(--brick);"
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