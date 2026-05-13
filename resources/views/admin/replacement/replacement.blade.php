@extends('layouts.admin_sidebar')

@section('title', 'Replacement Records')

@section('content')

    <!-- Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div class="px-8 py-5">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Replacement Records</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage and track all asset replacement requests</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="searchInput" placeholder="Search replacements..."
                            class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 w-56"/>
                    </div>
                    <div class="relative cursor-pointer">
                        <i class="ri-notification-3-line text-xl text-gray-600"></i>
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                    </div>
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

    <div class="p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Total</p>
                    <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="ri-refresh-line text-blue-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ $totalReplacements ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Pending</p>
                    <div class="w-9 h-9 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="ri-time-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-yellow-600">{{ $pendingReplacements ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Approved</p>
                    <div class="w-9 h-9 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="ri-checkbox-circle-line text-orange-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-orange-600">{{ $approvedReplacements ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500">Received</p>
                    <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="ri-check-double-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-green-600">{{ $receivedReplacements ?? 0 }}</p>
            </div>
        </div>

        <!-- Replacement Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

            <!-- Tabs -->
            <div class="flex items-center justify-between px-6 pt-5 pb-0 border-b border-gray-100">
                <div class="flex space-x-1">
                    <button class="filter-tab active px-4 py-2.5 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-filter="all">All</button>
                    <button class="filter-tab px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="Pending">Pending</button>
                    <button class="filter-tab px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="Approved">Approved</button>
                    <button class="filter-tab px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700" data-filter="Received">Received</button>
                </div>
                <p class="text-sm text-gray-400 pb-3">{{ $replacements->count() ?? 0 }} records</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <th class="px-6 py-4 text-left font-semibold">Old Asset Code</th>
                            <th class="px-6 py-4 text-left font-semibold">New Asset Code</th>
                            <th class="px-6 py-4 text-left font-semibold">Requested By</th>
                            <th class="px-6 py-4 text-left font-semibold">Reason</th>
                            <th class="px-6 py-4 text-left font-semibold">Progress</th>
                            <th class="px-6 py-4 text-left font-semibold">Status</th>
                            <th class="px-6 py-4 text-left font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">

                        @forelse($replacements ?? [] as $replacement)
                        <tr class="replacement-row hover:bg-gray-50 transition" data-status="{{ $replacement->status }}" data-id="{{ $replacement->id }}">

                            {{-- Old Asset --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="ri-computer-line text-red-500 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 font-mono">{{ data_get($replacement, 'old_asset_code') ?? ($replacement->oldAsset->Asset_code ?? '—') }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ data_get($replacement, 'old_asset_name') ?? ($replacement->oldAsset->Asset_name ?? '') }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- New Asset --}}
                            <td class="px-6 py-4">
                                @if(data_get($replacement, 'new_asset_code') || data_get($replacement, 'new_asset_name') || data_get($replacement, 'newAsset'))
                                    <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <i class="ri-computer-line text-green-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 font-mono">{{ data_get($replacement, 'new_asset_code') ?? data_get($replacement, 'newAsset.Asset_code') ?? '—' }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ data_get($replacement, 'new_asset_name') ?? data_get($replacement, 'newAsset.Asset_name') ?? '' }}</p>
                                        </div>
                                    </div>
                                @else
                                    @if($replacement->status === 'Approved')
                                        <button onclick="openLinkModal({{ $replacement->id }})"
                                            class="flex items-center space-x-2 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-gray-400 hover:border-blue-400 hover:text-blue-500 transition text-xs">
                                            <i class="ri-link mr-1"></i>
                                            Link new asset
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                @endif
                            </td>

                            {{-- Requested By --}}
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ data_get($replacement, 'requested_by') ?? ($replacement->request->user->full_name ?? '—') }}</p>
                                <p class="text-xs text-gray-400">{{ data_get($replacement, 'department') ?? ($replacement->request->user->department ?? '—') }}</p>
                            </td>

                            {{-- Reason --}}
                            <td class="px-6 py-4">
                                <p class="text-gray-700 max-w-xs truncate">{{ $replacement->reason ?? '—' }}</p>
                            </td>

                            {{-- Progress Steps --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-1">
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                            {{ in_array($replacement->status, ['Pending','Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400' }}">
                                            1
                                        </div>
                                        <span class="text-xs text-gray-400 mt-0.5">Pending</span>
                                    </div>
                                    <div class="w-6 h-0.5 {{ in_array($replacement->status, ['Approved','Received']) ? 'bg-blue-600' : 'bg-gray-200' }} mb-3"></div>
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                            {{ in_array($replacement->status, ['Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400' }}">
                                            2
                                        </div>
                                        <span class="text-xs text-gray-400 mt-0.5">Approved</span>
                                    </div>
                                    <div class="w-6 h-0.5 {{ $replacement->status === 'Received' ? 'bg-green-500' : 'bg-gray-200' }} mb-3"></div>
                                    <div class="flex flex-col items-center">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                            {{ $replacement->status === 'Received' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400' }}">
                                            3
                                        </div>
                                        <span class="text-xs text-gray-400 mt-0.5">Received</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                    @if($replacement->status == 'Pending') bg-yellow-100 text-yellow-700
                                    @elseif($replacement->status == 'Approved') bg-orange-100 text-orange-700
                                    @elseif($replacement->status == 'Received') bg-green-100 text-green-700
                                    @else bg-gray-100 text-gray-700
                                    @endif">
                                    {{ $replacement->status ?? '—' }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <button onclick="openViewModal({{ $replacement->id }})"
                                        class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition"
                                        title="View Details">
                                        <i class="ri-eye-line text-sm"></i>
                                    </button>

                                    @if($replacement->status === 'Pending')
                                    <button onclick="openApproveModal({{ $replacement->id }})"
                                        class="p-1.5 bg-green-50 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition"
                                        title="Approve">
                                        <i class="ri-checkbox-circle-line text-sm"></i>
                                    </button>
                                    @endif

                                    @if($replacement->status === 'Approved' && data_get($replacement, 'newAsset'))
                                    <button onclick="openReceivedModal({{ $replacement->id }})"
                                        class="p-1.5 bg-teal-50 text-teal-600 rounded-lg hover:bg-teal-600 hover:text-white transition"
                                        title="Mark as Received">
                                        <i class="ri-check-double-line text-sm"></i>
                                    </button>
                                    @endif

                                    @if(data_get($replacement, 'new_asset_id'))
                                    <a href="/admin/assets/{{ data_get($replacement, 'new_asset_id') }}" target="_blank"
                                        class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition"
                                        title="View New Asset">
                                        <i class="ri-external-link-line text-sm"></i>
                                    </a>

                                    @if(data_get($replacement, 'new_asset_qr'))
                                    <button onclick="downloadUrl('{{ \Illuminate\Support\Facades\Storage::url(data_get($replacement, 'new_asset_qr')) }}', '{{ data_get($replacement, 'new_asset_code') ?? 'qr' }}')"
                                        class="p-1.5 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-200 transition"
                                        title="Download QR">
                                        <i class="ri-download-line text-sm"></i>
                                    </button>
                                    <button onclick="printUrl('{{ \Illuminate\Support\Facades\Storage::url(data_get($replacement, 'new_asset_qr')) }}')"
                                        class="p-1.5 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-200 transition"
                                        title="Print QR">
                                        <i class="ri-printer-line text-sm"></i>
                                    </button>
                                    @endif
                                    @endif

                                    <button onclick="confirmDelete({{ $replacement->id }})"
                                        class="p-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition"
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
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                        <i class="ri-refresh-line text-2xl text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-500 font-medium">No replacement records found</p>
                                    <p class="text-gray-400 text-xs mt-1">Replacement requests will appear here once submitted</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            @if(isset($replacements) && $replacements->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $replacements->links() }}
            </div>
            @endif
        </div>

        <div class="text-center text-sm text-gray-400 mt-8 pt-6 border-t border-gray-200">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Replacement Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="p-6" id="viewModalContent">
                @foreach($replacements ?? [] as $r)
                <div id="view-{{ $r->id }}" class="modal-content hidden space-y-4">
                    {{-- Progress bar --}}
                    <div class="flex items-center space-x-2 flex-1 mb-1">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                            {{ in_array($r->status, ['Pending','Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">1</div>
                        <div class="flex-1 h-1 {{ in_array($r->status, ['Approved','Received']) ? 'bg-blue-600' : 'bg-gray-200' }} rounded"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                            {{ in_array($r->status, ['Approved','Received']) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">2</div>
                        <div class="flex-1 h-1 {{ $r->status === 'Received' ? 'bg-green-500' : 'bg-gray-200' }} rounded"></div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                            {{ $r->status === 'Received' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500' }}">3</div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mb-4">
                        <span>Pending</span><span>Approved</span><span>Received</span>
                    </div>

                    {{-- Old vs New Asset --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                            <p class="text-xs font-semibold text-red-500 mb-2 flex items-center">
                                <i class="ri-arrow-left-line mr-1"></i> Old Asset
                            </p>
                            <p class="font-semibold text-gray-900 text-sm">{{ data_get($r, 'old_asset_name') ?? ($r->oldAsset->Asset_name ?? '—') }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ data_get($r, 'old_asset_code') ?? ($r->oldAsset->Asset_code ?? '—') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ data_get($r, 'old_asset_category') ?? ($r->oldAsset->Category ?? '—') }}</p>
                            <span class="text-xs px-2 py-0.5 bg-red-100 text-red-600 rounded-full mt-2 inline-block">
                                {{ data_get($r, 'old_asset_lifecycle_status') ?? ($r->oldAsset->Lifecycle_Status ?? '—') }}
                            </span>
                        </div>
                        <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                            <p class="text-xs font-semibold text-green-600 mb-2 flex items-center">
                                <i class="ri-arrow-right-line mr-1"></i> New Asset
                            </p>
                            @if(data_get($r, 'new_asset_code') || data_get($r, 'new_asset_name') || data_get($r, 'newAsset'))
                                <p class="font-semibold text-gray-900 text-sm">{{ data_get($r, 'new_asset_name') ?? data_get($r, 'newAsset.Asset_name') ?? '—' }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ data_get($r, 'new_asset_code') ?? data_get($r, 'newAsset.Asset_code') ?? '—' }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ data_get($r, 'new_asset_category') ?? data_get($r, 'newAsset.Category') ?? '—' }}</p>
                                <span class="text-xs px-2 py-0.5 bg-green-100 text-green-600 rounded-full mt-2 inline-block">
                                    {{ data_get($r, 'new_asset_lifecycle_status') ?? data_get($r, 'newAsset.Lifecycle_Status') ?? '—' }}
                                </span>
                            @else
                                <p class="text-xs text-gray-400 italic mt-4">Not yet assigned</p>
                            @endif
                        </div>
                    </div>

                    {{-- Details --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Requested By</p>
                            <p class="text-sm font-medium text-gray-900">{{ data_get($r, 'requested_by') ?? ($r->request->user->full_name ?? '—') }}</p>
                            <p class="text-xs text-gray-400">{{ data_get($r, 'department') ?? ($r->request->user->department ?? '—') }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Approved By</p>
                            <p class="text-sm font-medium text-gray-900">{{ $r->Approve_by ?? '—' }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Replacement Date</p>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $r->Replacement_Date ? \Carbon\Carbon::parse($r->Replacement_Date)->format('M d, Y') : '—' }}
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Submitted On</p>
                            <p class="text-sm font-medium text-gray-900">
                                {{ data_get($r, 'created_at') ? \Carbon\Carbon::parse(data_get($r, 'created_at'))->format('M d, Y') : '—' }}
                            </p>
                        </div>
                    </div>

                    @if($r->reason)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Reason</p>
                        <p class="text-sm text-gray-700">{{ $r->reason }}</p>
                    </div>
                    @endif

                    @if($r->notes)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 mb-1">Notes</p>
                        <p class="text-sm text-gray-700">{{ $r->notes }}</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="p-6 border-t border-gray-100 flex justify-end">
                <button onclick="closeModal('viewModal')"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full mx-4">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Approve Replacement</h3>
                <button onclick="closeModal('approveModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="p-6 space-y-4">
                    <div class="flex items-center space-x-3 p-3 bg-green-50 border border-green-100 rounded-lg">
                        <i class="ri-checkbox-circle-line text-green-500 text-xl"></i>
                        <p class="text-sm text-green-700">Approving this request will allow you to create and link a new asset for this replacement.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this approval..."
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 resize-none"></textarea>
                    </div>
                    <input type="hidden" name="status" value="Approved"/>
                    <input type="hidden" name="Approve_by" value="{{ Auth::user()->full_name ?? '' }}"/>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('approveModal')"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium">
                        <i class="ri-checkbox-circle-line mr-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Link New Asset Modal (Create & Link) -->
    <div id="linkModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Create & Link New Asset</h3>
                <button onclick="closeModal('linkModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <form id="linkForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="p-6 space-y-4">

                    {{-- Auto-generated Asset Code --}}
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                        <p class="text-xs text-blue-500 font-semibold mb-2 flex items-center">
                            <i class="ri-qr-code-line mr-1"></i> Auto-Generated Asset Code
                        </p>
                        <div class="flex items-center space-x-2">
                            <p class="text-lg font-bold text-blue-700 font-mono flex-1" id="generatedCode">—</p>
                            <button type="button" onclick="regenerateCode()"
                                class="px-3 py-1.5 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition text-xs font-medium flex items-center">
                                <i class="ri-refresh-line mr-1"></i> Regenerate
                            </button>
                        </div>
                        <div class="mt-3 flex items-start space-x-4">
                            <div class="w-28 h-28 bg-white rounded-md flex items-center justify-center border border-gray-100">
                                <img id="generatedQrImg" src="" alt="QR Code" class="w-24 h-24 object-contain"/>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs text-gray-500">QR preview for the generated code. This updates when you regenerate the code.</p>
                                <div class="mt-3 flex items-center space-x-2">
                                    <button type="button" onclick="downloadGeneratedQr()"
                                        class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                                        <i class="ri-download-line mr-1"></i> Download QR
                                    </button>
                                    <button type="button" onclick="printGeneratedQr()"
                                        class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                                        <i class="ri-printer-line mr-1"></i> Print QR
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-blue-400 mt-1">This code will be used to generate the QR code automatically</p>
                        <input type="hidden" name="Asset_code" id="assetCodeHidden"/>
                    </div>

                    {{-- Only Asset Code & QR preview are required for linking; other fields removed per request --}}

                    {{-- Info note --}}
                    <div class="flex items-start space-x-2 p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                        <i class="ri-information-line text-yellow-500 mt-0.5 flex-shrink-0"></i>
                        <p class="text-xs text-yellow-700">The new asset will be automatically assigned to the same user as the old asset. A QR code will be generated from the asset code above.</p>
                    </div>

                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('linkModal')"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                        <i class="ri-add-line mr-1"></i> Create & Link Asset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mark Received Modal -->
    <div id="receivedModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full mx-4">
            <div class="p-6 text-center">
                <div class="w-14 h-14 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-check-double-line text-teal-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Mark as Received?</h3>
                <p class="text-gray-500 text-sm">Confirming receipt will set the new asset status to
                    <span class="font-semibold text-green-600">Active</span> and the old asset status to
                    <span class="font-semibold text-red-500">For Repair</span>.
                </p>
            </div>
            <form id="receivedForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="Received"/>
                <div class="p-6 border-t border-gray-100 flex justify-center space-x-3">
                    <button type="button" onclick="closeModal('receivedModal')"
                        class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition text-sm font-medium">
                        <i class="ri-check-double-line mr-1"></i> Confirm Received
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full mx-4">
            <div class="p-6 text-center">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-delete-bin-line text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Replacement Record?</h3>
                <p class="text-gray-500 text-sm">This action cannot be undone.</p>
            </div>
            <div class="p-6 border-t border-gray-100 flex justify-center space-x-3">
                <button onclick="closeModal('deleteModal')"
                    class="px-5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                    Cancel
                </button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .filter-tab { transition: all 0.2s ease; }
        .replacement-row { transition: all 0.2s ease; }
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
            document.getElementById(id).classList.add('hidden');
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

        // Link Asset Modal — generates code on open
        function openLinkModal(id) {
            document.getElementById('linkForm').action = `/admin/replacements/${id}/link`;
            regenerateCode();
            document.getElementById('linkModal').classList.remove('hidden');
        }

        // AJAX submit for Create & Link form
        document.getElementById('linkForm').addEventListener('submit', async function (e) {
            e.preventDefault();
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
                                <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="ri-computer-line text-green-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 font-mono">${data.asset.code}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">${data.asset.name || ''}</p>
                                </div>
                            </div>
                        `;

                        // Append action buttons (view/download/print)
                        const actionsCell = row.children[row.children.length - 1];
                        if (actionsCell) {
                            const linkBtn = document.createElement('a');
                            linkBtn.href = `/admin/assets/${data.asset.id}`;
                            linkBtn.target = '_blank';
                            linkBtn.className = 'p-1.5 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition';
                            linkBtn.title = 'View New Asset';
                            linkBtn.innerHTML = '<i class="ri-external-link-line text-sm"></i>';
                            actionsCell.prepend(linkBtn);

                            if (data.asset.qr_url) {
                                const dlBtn = document.createElement('button');
                                dlBtn.className = 'p-1.5 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-200 transition';
                                dlBtn.title = 'Download QR';
                                dlBtn.type = 'button';
                                dlBtn.innerHTML = '<i class="ri-download-line text-sm"></i>';
                                dlBtn.addEventListener('click', () => downloadUrl(data.asset.qr_url, data.asset.code || 'qr'));
                                actionsCell.prepend(dlBtn);

                                const prBtn = document.createElement('button');
                                prBtn.className = 'p-1.5 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-200 transition';
                                prBtn.title = 'Print QR';
                                prBtn.type = 'button';
                                prBtn.innerHTML = '<i class="ri-printer-line text-sm"></i>';
                                prBtn.addEventListener('click', () => printUrl(data.asset.qr_url));
                                actionsCell.prepend(prBtn);
                            }
                        }
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

        function regenerateCode() {
            const code = generateCode();
            document.getElementById('generatedCode').textContent = code;
            document.getElementById('assetCodeHidden').value = code;
            // Update QR preview image only — do not reset other form fields
            try {
                const img = document.getElementById('generatedQrImg');
                if (img) {
                    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(code);
                    img.src = qrUrl;
                }
            } catch (e) {
                console.error('QR preview error', e);
            }
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

        // Print generated QR image in a new window
        function printGeneratedQr() {
            const img = document.getElementById('generatedQrImg');
            if (!img || !img.src) return alert('No QR available to print');
            const w = window.open('', '_blank', 'width=400,height=600');
            const html = `<!doctype html><html><head><title>Print QR</title><style>body{margin:0;display:flex;align-items:center;justify-content:center;height:100vh}img{max-width:90%;height:auto}</style></head><body><img src="${img.src}" alt="QR"/></body><script>window.onload=function(){window.print();setTimeout(()=>window.close(),200);};<\/script></html>`;
            w.document.write(html);
            w.document.close();
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

        // Print an image URL in a new window and invoke print
        function printUrl(url) {
            if (!url) return alert('No URL provided');
            const w = window.open('', '_blank', 'width=400,height=600');
            const html = `<!doctype html><html><head><title>Print QR</title><style>body{margin:0;display:flex;align-items:center;justify-content:center;height:100vh}img{max-width:90%;height:auto}</style></head><body><img src="${url}" alt="QR"/></body><script>window.onload=function(){window.print();setTimeout(()=>window.close(),200);};<\/script></html>`;
            w.document.write(html);
            w.document.close();
        }

        // Mark Received Modal
        function openReceivedModal(id) {
            document.getElementById('receivedForm').action = `/admin/replacements/${id}/received`;
            document.getElementById('receivedModal').classList.remove('hidden');
        }

        // Delete Modal
        function confirmDelete(id) {
            document.getElementById('deleteForm').action = `/admin/replacements/${id}`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
    </script>

@endsection