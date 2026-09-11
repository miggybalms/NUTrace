<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $asset->Asset_name ?? 'Asset' }} — My Assets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body{ background:#F3EEE0 !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',sans-serif !important; color:#1A2233; }
        .brand-title{ font-family:'Fraunces',Georgia,serif; }
    </style>
</head>
<body class="bg-[#F3EEE0]">
    <div class="flex h-screen overflow-hidden">
        @include('department_head.partial.sidebar')

        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
    <div class="max-w-4xl mx-auto p-8">

            {{-- Back Button --}}
            <a href="{{ url()->previous() }}"
            class="inline-flex items-center gap-1.5 text-sm text-[#5B6678] px-3 py-1.5 rounded-lg border border-[#DED2AE] bg-white hover:bg-[#F5F0E2] transition mb-5">
                <i class="ri-arrow-left-line"></i> Back to my assets
            </a>
            
        {{-- Main Card --}}
        <div class="bg-white border border-[#DED2AE] rounded-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex justify-between items-start p-6 pb-0">
                <div>
                    <span class="inline-block text-xs font-medium px-3 py-1 rounded-full bg-[#EAF4EE] text-[#245C3B] mb-2">
                        {{ $asset->Lifecycle_Status ?? 'Unknown' }}
                    </span>
                    <h1 class="brand-title text-2xl font-semibold text-[#0F2143] capitalize">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                    <p class="text-xs font-mono text-[#8991A0] mt-1">{{ $asset->Asset_code }}</p>
                </div>
                <div class="w-24 h-24 rounded-xl border border-[#EFE9D8] bg-[#F5F0E2] flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($asset->image_url)
                        <img src="{{ $asset->image_url }}" alt="Asset photo" class="w-full h-full object-contain" />
                    @else
                        <i class="ri-image-off-line text-2xl text-[#A8AFBC]"></i>
                    @endif
                </div>
            </div>

            <hr class="border-t border-[#EFE9D8] mx-6 mt-5">

            {{-- Assigned To --}}
            <div class="flex items-center gap-3 px-6 py-4 border-b border-[#EFE9D8]">
                <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                    <i class="ri-user-follow-line text-base"></i>
                </div>
                <div>
                    <p class="text-xs text-[#8991A0] mb-1">Assigned to</p>
                    <div class="inline-flex items-center gap-1.5 bg-[#EFE9D8] rounded-full pl-1 pr-3 py-0.5">
                        <div class="w-5 h-5 rounded-full bg-[#EFE7F3] text-[#523A64] text-[10px] font-semibold flex items-center justify-center">
                            {{ strtoupper(substr($asset->full_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($asset->full_name ?? ' U', ' '), 1, 1)) }}
                        </div>
                        <span class="text-sm font-medium text-[#24334F]">{{ $asset->full_name ?? 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            {{-- Detail Grid --}}
            <div class="grid grid-cols-2">

                {{-- Acquisition Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-r border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-calendar-event-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Acquisition date</p>
                        <p class="text-sm font-medium text-[#0F2143]">{{ $asset->accusion_date ?? '—' }}</p>
                    </div>
                </div>

                {{-- Purchase Price --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-money-peso-circle-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Purchase price</p>
                        <p class="text-sm font-medium text-[#0F2143]">
                            {{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}
                        </p>
                    </div>
                </div>

                {{-- Serial Number --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-barcode-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Serial number</p>
                        <p class="text-sm font-medium font-mono text-[#0F2143]">{{ $asset->serial_Number ?? '—' }}</p>
                    </div>
                </div>

                {{-- Location --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-map-pin-2-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Location</p>
                        <p class="text-sm font-medium text-[#0F2143]">{{ $asset->asset_location ?? '—' }}</p>
                    </div>
                </div>

                {{-- Next Maintenance Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-tools-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Next maintenance</p>
                        <p class="text-sm font-medium text-[#0F2143]">
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
            <div class="px-6 py-4 border-t border-[#EFE9D8]">
                <button type="button" onclick="openRepairHistoryModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#FBF1DE] text-[#8F5F16] rounded-lg hover:bg-[#FDF0E3] transition text-sm font-medium border border-[#EAD9B4]">
                    <i class="ri-tools-fill"></i>
                    View Repair History
                    <span class="ml-1 px-2 py-0.5 bg-[#F0DFBC] text-[#7A5214] rounded-full text-xs font-semibold">
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
            <div class="px-6 py-4 border-b border-[#DED2AE] flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#0F2143]">Repair History</h3>
                    <p class="text-sm text-[#5B6678] mt-0.5">{{ $asset->Asset_name ?? 'Asset' }} · {{ $asset->Asset_code ?? '' }}</p>
                </div>
                <button type="button" onclick="closeRepairHistoryModal()" class="text-[#8991A0] hover:text-[#46536B]">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="overflow-y-auto p-6 space-y-4">
                @foreach($repairs as $repair)
                <div class="border border-[#DED2AE] rounded-xl p-4 hover:bg-[#F5F0E2] transition">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-lg bg-[#FDF0E3] flex items-center justify-center text-[#B4791E]">
                                <i class="ri-tools-line"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-[#0F2143]">
                                    Repair #{{ $repair->Repair_id ?? $repair->id ?? '—' }}
                                </p>
                                <p class="text-xs text-[#5B6678]">
                                    {{ $repair->Repair_Date ? \Carbon\Carbon::parse($repair->Repair_Date)->format('M d, Y · h:i A') : '—' }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                            @if(($repair->status ?? '') === 'Completed') bg-[#EAF4EE] text-[#245C3B]
                            @elseif(($repair->status ?? '') === 'Pending') bg-[#FBF1DE] text-[#8F5F16]
                            @else bg-[#EFE9D8] text-[#33425C]
                            @endif">
                            {{ $repair->status ?? 'Unknown' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-[#8991A0] mb-0.5">Description</p>
                            <p class="text-[#24334F]">{{ $repair->Repair_Description ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#8991A0] mb-0.5">Approved / Handled by</p>
                            <p class="text-[#24334F]">{{ $repair->Approve_by ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#8991A0] mb-0.5">Repair Cost</p>
                            <p class="text-[#24334F]">
                                {{ isset($repair->Repair_Cost) ? '₱' . number_format((float)$repair->Repair_Cost, 2) : '—' }}
                            </p>
                        </div>
                        @if(!empty($repair->Request_id))
                        <div>
                            <p class="text-xs text-[#8991A0] mb-0.5">Linked Request</p>
                            <p class="text-[#24334F]">#{{ $repair->Request_id }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="px-6 py-3 border-t border-[#DED2AE] bg-[#F5F0E2] text-right">
                <button type="button" onclick="closeRepairHistoryModal()"
                    class="px-4 py-2 border border-[#CFC4A4] rounded-lg text-[#33425C] hover:bg-white text-sm">
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