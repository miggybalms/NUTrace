{{--
    Shared "Asset Details" body for the employee and department-head sides.

    Expects:
        $asset        — object from App\Support\AssetDetails::load()
        $details      — array from App\Support\AssetDetails::build()
        $backUrl      — URL back to the asset list (filter/search preserved)

    The page is monitoring-only. Requests (repairs included) are filed from the
    Requests page, and lifecycle control (maintenance completion, disposal,
    replacement, pullout, accountability) stays in the admin area.
--}}
@php
    $maintenance = $details['maintenance'];
    $lifespan    = $details['lifespan'];
    $warranty    = $details['warranty'];
    $lifecycle   = $details['lifecycle'];
    $openRepair  = $details['openRepair'];
    $fmt         = fn ($date) => $date ? $date->format('M d, Y') : '—';

    $lifecycleClass = [
        'Active'          => 'bg-[#EAF4EE] text-[#245C3B]',
        'For Repair'      => 'bg-[#F7E9E6] text-[#7E2E27]',
        'For Checking'    => 'bg-[#FBF1DE] text-[#8F5F16]',
        'For Replacement' => 'bg-[#EFE7F3] text-[#523A64]',
        'Acquired'        => 'bg-[#F3E7C4] text-[#7A5214]',
        'Pullout'         => 'bg-[#EFE9D8] text-[#33425C]',
        'Disposal'        => 'bg-[#EFE9D8] text-[#33425C]',
    ][$lifecycle['status']] ?? 'bg-[#EFE9D8] text-[#33425C]';

    $maintenanceClass = [
        'Upcoming'      => 'bg-[#FBF1DE] text-[#8F5F16]',
        'Due'           => 'bg-[#FDF0E3] text-[#B4791E]',
        'Overdue'       => 'bg-[#F7E9E6] text-[#7E2E27]',
        'Not scheduled' => 'bg-[#EFE9D8] text-[#33425C]',
    ][$maintenance['status']] ?? 'bg-[#EFE9D8] text-[#33425C]';

    $lifespanClass = [
        'Within Lifespan' => 'bg-[#EAF4EE] text-[#245C3B]',
        'Expired'         => 'bg-[#F7E9E6] text-[#7E2E27]',
        'Not set'         => 'bg-[#EFE9D8] text-[#33425C]',
    ][$lifespan['status']] ?? 'bg-[#EFE9D8] text-[#33425C]';

    $warrantyClass = [
        'Active'        => 'bg-[#EAF4EE] text-[#245C3B]',
        'Expired'       => 'bg-[#EFE9D8] text-[#33425C]',
        'Not started'   => 'bg-[#FBF1DE] text-[#8F5F16]',
        'Not specified' => 'bg-[#EFE9D8] text-[#33425C]',
    ][$warranty['status']] ?? 'bg-[#EFE9D8] text-[#33425C]';

    $repairClass = fn ($status) => [
        'Pending'     => 'bg-[#FBF1DE] text-[#8F5F16]',
        'In Progress' => 'bg-[#0A1830] text-[#E9C766]',
        'Completed'   => 'bg-[#EAF4EE] text-[#245C3B]',
        'Cancelled'   => 'bg-[#EFE9D8] text-[#33425C]',
    ][$status] ?? 'bg-[#EFE9D8] text-[#33425C]';

    $toneDot = [
        'gold'  => 'bg-[#C9A227]',
        'amber' => 'bg-[#B4791E]',
        'green' => 'bg-[#2F7A4D]',
        'navy'  => 'bg-[#0A1830]',
        'muted' => 'bg-[#A8AFBC]',
    ];

    $hasRepairHistory = count($details['repairHistory']) > 0;
    $hasMaintenanceHistory = $details['maintenanceHistory']->count() > 0;
@endphp

<style>
    .ad-title { font-family:'Fraunces', Georgia, serif; }
    .ad-mono  { font-family:'IBM Plex Mono', ui-monospace, monospace; }
    .ad-card  { background:#fff; border:1px solid #DED2AE; border-radius:16px; }
    .ad-fact  { display:flex; align-items:flex-start; gap:.75rem; }
    .ad-fact-icon {
        width:34px; height:34px; border-radius:10px; background:#F5F0E2;
        color:#5B6678; display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .ad-btn-gold { background:#C9A227; color:#0A1830; transition:all .2s ease; }
    .ad-btn-gold:hover { background:#E0BC44; transform:translateY(-1px); }
    .ad-btn-ghost { border:1px solid #CFC4A4; color:#33425C; transition:all .2s ease; }
    .ad-btn-ghost:hover { background:#F5F0E2; }
    .ad-timeline-item:last-child .ad-timeline-line { display:none; }
</style>

<div class="p-4 sm:p-8">

    {{-- Back --}}
    <a href="{{ $backUrl }}"
       class="inline-flex items-center gap-1.5 text-sm text-[#5B6678] px-3 py-1.5 rounded-lg border border-[#DED2AE] bg-white hover:bg-[#F5F0E2] transition mb-5">
        <i class="ri-arrow-left-line"></i> Back to my assets
    </a>

    @if(session('success'))
        <div class="mb-5 flex items-start gap-2 rounded-xl border border-[#CFE3D4] bg-[#EAF4EE] px-4 py-3 text-[#1D4A2E]">
            <i class="ri-checkbox-circle-line text-lg mt-0.5"></i>
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 flex items-start gap-2 rounded-xl border border-[#E8CCC6] bg-[#F7E9E6] px-4 py-3 text-[#7E2E27]">
            <i class="ri-error-warning-line text-lg mt-0.5"></i>
            <span class="text-sm">{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-[#E8CCC6] bg-[#F7E9E6] px-4 py-3 text-[#7E2E27]">
            <ul class="list-disc list-inside text-sm space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ══════════════ Hero ══════════════ --}}
    <div class="ad-card overflow-hidden mb-6">
        <div class="p-5 sm:p-6 flex flex-col sm:flex-row gap-5 sm:gap-6">
            <div class="w-full sm:w-40 h-40 rounded-xl border border-[#EFE9D8] bg-[#F5F0E2] flex items-center justify-center overflow-hidden flex-shrink-0">
                @if($asset->image_url)
                    <img src="{{ $asset->image_url }}" alt="{{ $asset->Asset_name }}"
                         class="w-full h-full object-cover"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="display:none" class="w-full h-full flex-col items-center justify-center text-[#A8AFBC]">
                        <i class="ri-image-line text-3xl"></i>
                        <span class="text-xs mt-1">Image unavailable</span>
                    </div>
                @else
                    <div class="flex flex-col items-center text-[#A8AFBC]">
                        <i class="ri-image-line text-3xl"></i>
                        <span class="text-xs mt-1">No image</span>
                    </div>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <span class="inline-block text-xs font-semibold px-3 py-1 rounded-full {{ $lifecycleClass }}">
                    {{ $lifecycle['status'] }}
                </span>
                <h1 class="ad-title text-2xl sm:text-[28px] font-semibold text-[#0F2143] mt-2 capitalize">
                    {{ $asset->Asset_name ?? 'Asset' }}
                </h1>
                <p class="ad-mono text-sm text-[#8991A0] mt-1">{{ $asset->Asset_code ?? '—' }}</p>

                <div class="flex flex-wrap gap-2 mt-4">
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-[#F5F0E2] text-[#46536B]">
                        <i class="ri-layout-grid-line text-[#C9A227]"></i>{{ $asset->Category ?? 'Uncategorised' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-[#F5F0E2] text-[#46536B]">
                        <i class="ri-sparkling-line text-[#C9A227]"></i>Condition: {{ $asset->Condition ?? '—' }}
                    </span>
                    @if($asset->asset_location)
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-[#F5F0E2] text-[#46536B]">
                            <i class="ri-map-pin-2-line text-[#C9A227]"></i>{{ $asset->asset_location }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2 mt-5">
                    <button type="button" onclick="openAssetQrModal()" class="ad-btn-ghost inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium">
                        <i class="ri-qr-code-line"></i> View QR Code
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════ Current repair banner ══════════════ --}}
    @if($openRepair)
        <div class="ad-card p-5 mb-6 border-l-4" style="border-left-color:#C9A227;">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-[#FDF0E3] text-[#B4791E] flex items-center justify-center flex-shrink-0">
                    <i class="ri-tools-fill text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-[#0F2143]">Repair Status: {{ $openRepair->status }}</h3>
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $repairClass($openRepair->status) }}">
                            Repair #{{ $openRepair->Repair_id }}
                        </span>
                    </div>
                    <p class="text-sm text-[#5B6678] mt-1">
                        @if($openRepair->status === 'In Progress')
                            This asset is currently being serviced. You will be notified when the servicing is finished.
                        @else
                            The Asset Management Office has received your repair request and is evaluating it.
                        @endif
                    </p>
                    <p class="text-xs text-[#8991A0] mt-2">
                        Reported problem: {{ $openRepair->Repair_Description ?: '—' }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ══════════════ Left column ══════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Asset information --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                        <i class="ri-information-line"></i>
                    </div>
                    <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Asset Information</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 divide-[#EFE9D8]">
                    <div class="p-5 sm:p-6 space-y-4">
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-barcode-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Serial number</p>
                                <p class="ad-mono text-sm font-medium text-[#0F2143]">{{ $asset->serial_Number ?: '—' }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-cpu-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Model</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $asset->model ?: '—' }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-building-4-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Manufacturer</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $asset->manufacture ?: '—' }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-store-2-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Supplier</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $asset->supplier ?: '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6 space-y-4 border-t sm:border-t-0 sm:border-l border-[#EFE9D8]">
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-calendar-event-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Acquisition date</p>
                                <p class="text-sm font-medium text-[#0F2143]">
                                    {{ $asset->accusion_date ? \Carbon\Carbon::parse($asset->accusion_date)->format('M d, Y') : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-money-peso-circle-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Purchase price</p>
                                <p class="text-sm font-medium text-[#0F2143]">
                                    {{ $asset->purchase_Price ? '₱' . number_format((float) $asset->purchase_Price, 2) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-map-pin-2-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Location</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $asset->asset_location ?: '—' }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-user-follow-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Accountable to</p>
                                <p class="text-sm font-medium text-[#0F2143]">
                                    {{ $asset->full_name ?: 'Unassigned' }}
                                    @if($asset->department_name)
                                        <span class="text-[#8991A0] font-normal">· {{ $asset->department_name }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Maintenance --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                            <i class="ri-tools-line"></i>
                        </div>
                        <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Maintenance</h2>
                    </div>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $maintenanceClass }}">
                        {{ $maintenance['status'] }}
                    </span>
                </div>

                <div class="p-5 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-loop-right-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Maintenance interval</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $maintenance['interval_label'] }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-history-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Last maintenance</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $fmt($maintenance['last']) }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-calendar-check-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Next maintenance</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $fmt($maintenance['next']) }}</p>
                            </div>
                        </div>
                    </div>

                    @if($maintenance['status'] === 'Overdue')
                        <p class="mt-4 text-sm text-[#7E2E27] bg-[#F7E9E6] border border-[#E8CCC6] rounded-lg px-4 py-3">
                            <i class="ri-error-warning-line mr-1"></i>
                            Scheduled maintenance is overdue. The Asset Management Office has been notified — no action is required from you.
                        </p>
                    @elseif($maintenance['status'] === 'Due')
                        <p class="mt-4 text-sm text-[#8F5F16] bg-[#FBF1DE] border border-[#EAD9B4] rounded-lg px-4 py-3">
                            <i class="ri-time-line mr-1"></i>
                            Scheduled maintenance is due. The Asset Management Office handles the schedule — this is not a repair request.
                        </p>
                    @elseif($maintenance['status'] === 'Upcoming')
                        <p class="mt-4 text-sm text-[#5B6678] bg-[#F5F0E2] border border-[#EFE9D8] rounded-lg px-4 py-3">
                            <i class="ri-information-line mr-1"></i>
                            Upcoming preventive maintenance in {{ max($maintenance['days_until'], 0) }} day(s).
                            Maintenance due does not mean the asset is broken — the asset stays usable.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Lifespan --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                            <i class="ri-hourglass-line"></i>
                        </div>
                        <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Lifespan</h2>
                    </div>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $lifespanClass }}">
                        {{ $lifespan['status'] }}
                    </span>
                </div>

                <div class="p-5 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-timer-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Lifespan duration</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $lifespan['label'] }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-calendar-event-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Acquisition date</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $fmt($lifespan['acquired']) }}</p>
                            </div>
                        </div>
                        <div class="ad-fact">
                            <div class="ad-fact-icon"><i class="ri-calendar-close-line"></i></div>
                            <div>
                                <p class="text-xs text-[#8991A0]">Expected expiration</p>
                                <p class="text-sm font-medium text-[#0F2143]">{{ $fmt($lifespan['expires']) }}</p>
                            </div>
                        </div>
                    </div>

                    @if($lifespan['needs_review'])
                        <p class="mt-4 text-sm text-[#8F5F16] bg-[#FBF1DE] border border-[#EAD9B4] rounded-lg px-4 py-3">
                            <i class="ri-alert-line mr-1"></i>
                            This asset has reached the end of its expected lifespan and is queued for evaluation by the
                            Asset Management Office. Reaching the lifespan does not dispose of the asset — the office decides
                            whether it stays in service, needs repair, needs replacement, or should be retired.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Warranty --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Warranty</h2>
                    </div>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $warrantyClass }}">
                        {{ $warranty['status'] }}
                    </span>
                </div>

                <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="ad-fact">
                        <div class="ad-fact-icon"><i class="ri-file-shield-2-line"></i></div>
                        <div>
                            <p class="text-xs text-[#8991A0]">Warranty period</p>
                            <p class="text-sm font-medium text-[#0F2143]">{{ $warranty['label'] }}</p>
                        </div>
                    </div>
                    <div class="ad-fact">
                        <div class="ad-fact-icon"><i class="ri-calendar-check-line"></i></div>
                        <div>
                            <p class="text-xs text-[#8991A0]">Warranty expiration</p>
                            <p class="text-sm font-medium text-[#0F2143]">{{ $fmt($warranty['expires']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Repair history --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                            <i class="ri-tools-fill"></i>
                        </div>
                        <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Repair History</h2>
                    </div>
                    @if($hasRepairHistory)
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-[#F3E7C4] text-[#7A5214]">
                            {{ count($details['repairHistory']) }}
                        </span>
                    @endif
                </div>

                @if($hasRepairHistory)
                    <div class="divide-y divide-[#EFE9D8]">
                        @foreach($details['repairHistory'] as $repair)
                            <div class="p-5 sm:p-6">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-[#0F2143]">Repair #{{ $repair['id'] }}</p>
                                        <p class="text-xs text-[#5B6678] mt-0.5">
                                            {{ $repair['date'] ? $repair['date']->format('M d, Y · h:i A') : '—' }}
                                        </p>
                                    </div>
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $repairClass($repair['status']) }}">
                                        {{ $repair['status'] ?: 'Unknown' }}
                                    </span>
                                </div>

                                <p class="text-sm text-[#24334F] mt-3">{{ $repair['description'] ?: '—' }}</p>

                                @if($repair['result'] || $repair['cost'] || $repair['parts'])
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 pt-4 border-t border-[#EFE9D8]">
                                        @if($repair['result'])
                                            <div>
                                                <p class="text-xs text-[#8991A0]">Repair result</p>
                                                <p class="text-sm text-[#24334F]">{{ $repair['result'] }}</p>
                                            </div>
                                        @endif
                                        @if($repair['parts'])
                                            <div>
                                                <p class="text-xs text-[#8991A0]">Parts replaced</p>
                                                <p class="text-sm text-[#24334F]">{{ $repair['parts'] }}</p>
                                            </div>
                                        @endif
                                        @if($repair['cost'])
                                            <div>
                                                <p class="text-xs text-[#8991A0]">Repair cost</p>
                                                <p class="text-sm text-[#24334F]">₱{{ number_format((float) $repair['cost'], 2) }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-10 text-center">
                        <div class="w-14 h-14 rounded-full bg-[#F5F0E2] flex items-center justify-center mx-auto mb-3">
                            <i class="ri-tools-line text-xl text-[#C9A227]"></i>
                        </div>
                        <p class="text-sm font-medium text-[#33425C]">No repair records</p>
                        <p class="text-xs text-[#8991A0] mt-1">This asset has never been sent for repair.</p>
                    </div>
                @endif
            </div>

            {{-- Maintenance history --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                        <i class="ri-shield-check-line"></i>
                    </div>
                    <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Maintenance History</h2>
                </div>

                @if($hasMaintenanceHistory)
                    <div class="divide-y divide-[#EFE9D8]">
                        @foreach($details['maintenanceHistory'] as $log)
                            <div class="px-5 sm:px-6 py-5 flex items-start gap-3">
                                <div class="w-9 h-9 rounded-lg bg-[#EAF4EE] text-[#245C3B] flex items-center justify-center flex-shrink-0">
                                    <i class="ri-check-line"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-[#0F2143]">Preventive Maintenance — Completed</p>
                                    <p class="text-xs text-[#5B6678] mt-0.5">
                                        {{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M d, Y') : '—' }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-10 text-center">
                        <div class="w-14 h-14 rounded-full bg-[#F5F0E2] flex items-center justify-center mx-auto mb-3">
                            <i class="ri-shield-check-line text-xl text-[#C9A227]"></i>
                        </div>
                        <p class="text-sm font-medium text-[#33425C]">No maintenance recorded yet</p>
                        <p class="text-xs text-[#8991A0] mt-1">
                            Completed preventive maintenance for this asset will appear here.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                        <i class="ri-history-line"></i>
                    </div>
                    <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Asset Timeline</h2>
                </div>

                @if(count($details['timeline']))
                    <div class="p-5 sm:p-6">
                        @foreach($details['timeline'] as $event)
                            <div class="ad-timeline-item flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="w-3 h-3 rounded-full ring-4 ring-white {{ $toneDot[$event['tone']] ?? $toneDot['muted'] }}"></span>
                                    <span class="ad-timeline-line w-px flex-1 bg-[#E7DFC9] my-1"></span>
                                </div>
                                <div class="pb-5 min-w-0">
                                    <p class="text-xs text-[#8991A0]">{{ $event['date']->format('M d, Y') }}</p>
                                    <p class="text-sm font-semibold text-[#0F2143] mt-0.5">{{ $event['title'] }}</p>
                                    <p class="text-xs text-[#5B6678] mt-0.5">{{ $event['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-10 text-center text-sm text-[#8991A0]">No history recorded for this asset yet.</div>
                @endif
            </div>
        </div>

        {{-- ══════════════ Right column ══════════════ --}}
        <div class="space-y-6">

            {{-- Lifecycle status --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                        <i class="ri-loop-left-line"></i>
                    </div>
                    <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Current Status</h2>
                </div>
                <div class="p-5 sm:p-6">
                    <span class="inline-block text-sm font-semibold px-4 py-1.5 rounded-full {{ $lifecycleClass }}">
                        {{ $lifecycle['status'] }}
                    </span>
                    <p class="text-sm text-[#5B6678] mt-3">{{ $lifecycle['explain'] }}</p>
                    <p class="text-xs text-[#8991A0] mt-3">
                        Lifecycle status is updated by the Asset Management Office.
                    </p>
                </div>
            </div>

            {{-- Accountability --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                        <i class="ri-user-follow-line"></i>
                    </div>
                    <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Accountability</h2>
                </div>
                <div class="p-5 sm:p-6 space-y-3 text-sm">
                    <div class="flex items-center gap-2.5">
                        <i class="ri-user-3-line text-[#8991A0]"></i>
                        <span class="text-[#24334F]">{{ $asset->full_name ?: 'Unassigned' }}</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <i class="ri-building-2-line text-[#8991A0]"></i>
                        <span class="text-[#24334F]">{{ $asset->department_name ?: 'No department on record' }}</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <i class="ri-map-pin-2-line text-[#8991A0]"></i>
                        <span class="text-[#24334F]">{{ $asset->asset_location ?: 'No location on record' }}</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <i class="ri-loop-left-line text-[#8991A0]"></i>
                        <span class="text-[#24334F]">{{ $lifecycle['status'] }}</span>
                    </div>
                </div>
            </div>

            {{-- What you can do --}}
            <div class="ad-card overflow-hidden">
                <div class="px-6 py-5 border-b border-[#EFE9D8] flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F5F0E2] text-[#8F5F16] flex items-center justify-center">
                        <i class="ri-list-check-2"></i>
                    </div>
                    <h2 class="ad-title text-lg font-semibold text-[#0F2143]">Your Options</h2>
                </div>
                <div class="p-5 sm:p-6">
                    <ul class="space-y-2.5 text-sm text-[#33425C]">
                        <li class="flex items-start gap-2"><i class="ri-check-line text-[#2F7A4D] mt-0.5"></i> View this asset's information and QR code</li>
                        <li class="flex items-start gap-2"><i class="ri-check-line text-[#2F7A4D] mt-0.5"></i> Monitor maintenance, warranty and lifespan</li>
                        <li class="flex items-start gap-2"><i class="ri-check-line text-[#2F7A4D] mt-0.5"></i> File requests for this asset from the Requests page</li>
                        <li class="flex items-start gap-2"><i class="ri-check-line text-[#2F7A4D] mt-0.5"></i> Track repair status and history</li>
                    </ul>
                    <p class="text-xs text-[#8991A0] mt-4 pt-4 border-t border-[#EFE9D8]">
                        Lifecycle actions — maintenance completion, repair approval, replacement, pullout, disposal and
                        accountability changes — are handled by the Asset Management Office.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center text-sm text-[#8991A0] mt-10 pt-7 border-t border-[#DED2AE]">
        © {{ date('Y') }} University Asset Management. All rights reserved.
    </div>
</div>

{{-- ══════════════ QR modal ══════════════ --}}
<div id="assetQrModal" class="fixed inset-0 bg-[#0A1830]/60 hidden items-center justify-center z-50 p-4" onclick="closeAssetQrModal()">
    <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl" onclick="event.stopPropagation();">
        <div class="flex items-center justify-between mb-4">
            <h3 class="ad-title text-lg font-semibold text-[#0A1830]">Asset QR Code</h3>
            <button type="button" onclick="closeAssetQrModal()" class="text-[#8991A0] hover:text-[#0A1830]">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div id="assetQrHolder" class="flex items-center justify-center bg-[#F5F0E2] rounded-xl p-4 min-h-[220px]"></div>
        <p class="ad-mono text-sm text-[#46536B] text-center mt-4 break-all">{{ $asset->Asset_code }}</p>
        <p class="text-xs text-[#8991A0] text-center mt-1">
            Scan this code to identify the physical asset. It does not create a new asset record.
        </p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    (function () {
        // ── QR code: prefer the stored image, fall back to drawing it live ──
        var storedQrUrl = @json($details['qrUrl']);
        var assetCode   = @json($asset->Asset_code);
        var qrInstance  = null;
        var qrRendered  = false;

        window.openAssetQrModal = function () {
            var holder = document.getElementById('assetQrHolder');
            var modal  = document.getElementById('assetQrModal');
            if (!holder || !modal) return;

            if (!qrRendered) {
                holder.innerHTML = '';

                if (storedQrUrl) {
                    var img = document.createElement('img');
                    img.src = storedQrUrl;
                    img.alt = 'QR Code';
                    img.className = 'w-[220px] h-[220px] object-contain';
                    img.onerror = function () { drawQr(holder); };
                    holder.appendChild(img);
                    qrRendered = true;
                } else {
                    drawQr(holder);
                }
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        function drawQr(holder) {
            holder.innerHTML = '';
            if (!assetCode || typeof QRCode === 'undefined') return;
            try {
                qrInstance = new QRCode(holder, {
                    text: assetCode,
                    width: 220,
                    height: 220,
                    colorDark: '#0A1830',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (e) {}
        }

        window.closeAssetQrModal = function () {
            var modal = document.getElementById('assetQrModal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            window.closeAssetQrModal();
        });
    })();
</script>
