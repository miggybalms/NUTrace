<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $asset->Asset_name ?? 'Asset' }} — My Assets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('department_head.partial.sidebar')

        <div class="flex-1 overflow-y-auto bg-gray-50">
    <div class="max-w-4xl mx-auto p-8">

            {{-- Back Button --}}
            <a href="{{ url()->previous() }}"
            class="inline-flex items-center gap-1.5 text-sm text-gray-500 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-100 hover:bg-gray-200 transition mb-5">
                <i class="ri-arrow-left-line"></i> Back to my assets
            </a>
            
        {{-- Main Card --}}
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex justify-between items-start p-6 pb-0">
                <div>
                    <span class="inline-block text-xs font-medium px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 mb-2">
                        {{ $asset->Lifecycle_Status ?? 'Unknown' }}
                    </span>
                    <h1 class="text-2xl font-semibold text-gray-900 capitalize">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                    <p class="text-xs font-mono text-gray-400 mt-1">{{ $asset->Asset_code }}</p>
                </div>
                <div class="w-24 h-24 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($asset->image_url)
                        <img src="{{ $asset->image_url }}" alt="Asset photo" class="w-full h-full object-contain" />
                    @else
                        <i class="ri-image-off-line text-2xl text-gray-300"></i>
                    @endif
                </div>
            </div>

            <hr class="border-t border-gray-100 mx-6 mt-5">

            {{-- Assigned To --}}
            <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                    <i class="ri-user-follow-line text-base"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Assigned to</p>
                    <div class="inline-flex items-center gap-1.5 bg-gray-100 rounded-full pl-1 pr-3 py-0.5">
                        <div class="w-5 h-5 rounded-full bg-violet-100 text-violet-700 text-[10px] font-semibold flex items-center justify-center">
                            {{ strtoupper(substr($asset->full_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($asset->full_name ?? ' U', ' '), 1, 1)) }}
                        </div>
                        <span class="text-sm font-medium text-gray-800">{{ $asset->full_name ?? 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            {{-- Detail Grid --}}
            <div class="grid grid-cols-2">

                {{-- Acquisition Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-r border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-calendar-event-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Acquisition date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $asset->accusion_date ?? '—' }}</p>
                    </div>
                </div>

                {{-- Purchase Price --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-money-peso-circle-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Purchase price</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}
                        </p>
                    </div>
                </div>

                {{-- Serial Number --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-barcode-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Serial number</p>
                        <p class="text-sm font-medium font-mono text-gray-900">{{ $asset->serial_Number ?? '—' }}</p>
                    </div>
                </div>

                {{-- Location --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-map-pin-2-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Location</p>
                        <p class="text-sm font-medium text-gray-900">{{ $asset->asset_location ?? '—' }}</p>
                    </div>
                </div>

                {{-- Next Maintenance Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-tools-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Next maintenance</p>
                        <p class="text-sm font-medium text-gray-900">
                            @if($asset->next_maintenance_date)
                                {{ date('M d, Y', strtotime($asset->next_maintenance_date)) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

            </div>

            {{-- Repair History Button (only if this asset has repair records) --}}
            @if(isset($repairs) && $repairs->count() > 0)
            <div class="px-6 py-4 border-t border-gray-100">
                <button type="button" onclick="openRepairHistoryModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition text-sm font-medium border border-orange-200">
                    <i class="ri-tools-fill"></i>
                    View Repair History
                    <span class="ml-1 px-2 py-0.5 bg-orange-200 text-orange-800 rounded-full text-xs font-semibold">
                        {{ $repairs->count() }}
                    </span>
                </button>
            </div>
            @endif

        </div>
    </div>
</div>
    </div>

    {{-- Repair History Modal --}}
    @if(isset($repairs) && $repairs->count() > 0)
    <div id="repairHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Repair History</h3>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $asset->Asset_name ?? 'Asset' }} · {{ $asset->Asset_code ?? '' }}</p>
                </div>
                <button type="button" onclick="closeRepairHistoryModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="overflow-y-auto p-6 space-y-4">
                @foreach($repairs as $repair)
                <div class="border border-gray-200 rounded-xl p-4 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center text-orange-600">
                                <i class="ri-tools-line"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    Repair #{{ $repair->Repair_id ?? $repair->id ?? '—' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $repair->Repair_Date ? \Carbon\Carbon::parse($repair->Repair_Date)->format('M d, Y · h:i A') : '—' }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                            @if(($repair->status ?? '') === 'Completed') bg-green-100 text-green-700
                            @elseif(($repair->status ?? '') === 'Pending') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ $repair->status ?? 'Unknown' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Description</p>
                            <p class="text-gray-800">{{ $repair->Repair_Description ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Approved / Handled by</p>
                            <p class="text-gray-800">{{ $repair->Approve_by ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Repair Cost</p>
                            <p class="text-gray-800">
                                {{ isset($repair->Repair_Cost) ? '₱' . number_format((float)$repair->Repair_Cost, 2) : '—' }}
                            </p>
                        </div>
                        @if(!empty($repair->Request_id))
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Linked Request</p>
                            <p class="text-gray-800">#{{ $repair->Request_id }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 text-right">
                <button type="button" onclick="closeRepairHistoryModal()"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-white text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

    <script>
        function openRepairHistoryModal() {
            const modal = document.getElementById('repairHistoryModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRepairHistoryModal() {
            const modal = document.getElementById('repairHistoryModal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeRepairHistoryModal();
        });

        // Close when clicking the dark backdrop
        document.getElementById('repairHistoryModal')?.addEventListener('click', function (e) {
            if (e.target === this) closeRepairHistoryModal();
        });
    </script>
</body>
</html>