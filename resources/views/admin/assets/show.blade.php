<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asset Details - {{ $asset->Asset_code ?? 'Asset' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-100:#F3E7C4;
            --paper:#FAF7F0; --paper-2:#F2ECDD;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
            --line:#E6DFCD;
            --forest:#2F7A4D; --forest-dark:#245C3B;
            --bronze:#B4791E; --bronze-dark:#8F5F16;
            --steel:#2E5C8A; --steel-dark:#234869;
            --brick:#A23B32; --brick-dark:#7E2E27;
        }
        body{ font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink-900); }
        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-family:'Inter',sans-serif; font-size:.7rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:var(--gold-600); }
        .card-registry{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 1px 2px rgba(10,24,48,.04), 0 12px 32px -20px rgba(10,24,48,.25); overflow:hidden; }
        .registry-header{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .registry-header::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:3px; background:linear-gradient(90deg,transparent, var(--gold-500), transparent); }
        .photo-frame{ padding:6px; background:linear-gradient(160deg,var(--gold-500),var(--gold-600)); border-radius:12px; }
        .photo-frame-inner{ background:#fff; border-radius:8px; overflow:hidden; }
        .stub{ position:relative; background:var(--paper); border:1px dashed #C7BE9E; border-radius:10px; }
        .stub::before, .stub::after{ content:""; position:absolute; width:12px; height:12px; background:var(--navy-800); border-radius:50%; top:50%; transform:translateY(-50%); }
        .stub::before{ left:-7px; } .stub::after{ right:-7px; }
        .section-title{ font-family:'Fraunces',serif; font-weight:600; font-size:1.15rem; color:var(--navy-900); }
        .divider-gold{ height:1px; background:linear-gradient(90deg,var(--gold-500) 0, var(--line) 20%, var(--line) 100%); }
        .field-label{ font-family:'Inter',sans-serif; font-size:.68rem; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-400); }
        .field-value{ font-size:.92rem; font-weight:500; color:var(--ink-900); }
        .status-pill{ display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .65rem; border-radius:999px; border:1px solid var(--gold-500); background:var(--gold-100); color:var(--navy-900); font-size:.72rem; font-weight:600; letter-spacing:.03em; }
        .action-tile{ border:1px solid var(--line); background:#fff; border-radius:12px; padding:1.1rem; text-align:left; transition:box-shadow .15s ease, transform .15s ease, border-color .15s ease; border-left-width:4px; }
        .action-tile:hover{ box-shadow:0 10px 24px -14px rgba(10,24,48,.35); transform:translateY(-1px); }
        .icon-badge{ width:2.25rem; height:2.25rem; border-radius:9px; display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; }
        .btn-primary{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; color:#fff; display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-primary:hover{ filter:brightness(1.08); }
        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; }
        .btn-ghost:hover{ background:var(--paper-2); }
        .modal-panel{ background:#fff; border-radius:14px; border:1px solid var(--line); overflow:hidden; }
        .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
        .form-input{ width:100%; border:1px solid var(--line); border-radius:9px; padding:.55rem .9rem; font-size:.9rem; outline:none; transition:border-color .15s, box-shadow .15s; background:#fff; }
        .form-input:focus{ border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
        .notice-box{ border-radius:10px; padding:.85rem 1rem; font-size:.85rem; border:1px solid; }
    </style>
</head>

<body>
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
            <div class="max-w-4xl mx-auto p-8">
                <div class="card-registry">

                    <!-- Registry header band -->
                    <div class="registry-header p-6">
                        <div class="flex justify-between items-start gap-6">
                            <div>
                                <p class="eyebrow mb-2" style="color:var(--gold-500);">Asset Record</p>
                                <h1 class="font-display text-2xl md:text-3xl font-semibold text-white">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                                <p class="text-sm mt-2 font-mono" style="color:var(--gold-100);">{{ $asset->Asset_code }}</p>
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <span class="status-pill">
                                        <i class="ri-shield-check-line"></i>{{ $asset->Lifecycle_Status }}
                                    </span>
                                    <span class="text-sm" style="color:#C7D2E3;">Assigned to&nbsp;<span class="font-medium text-white">{{ $asset->full_name ?? 'Unassigned' }}</span></span>
                                </div>
                            </div>

                            <div class="text-right flex-shrink-0">
                                @php
                                $photo = $asset->image_url ?? $asset->url ?? null;
                                @endphp

                                @if($photo)
                                <div class="photo-frame inline-block">
                                    <div class="photo-frame-inner">
                                        <img src="{{ \Illuminate\Support\Str::startsWith($photo, ['http://', 'https://', '/storage', 'storage/'])
                                        ? (Str::startsWith($photo, 'storage/') ? asset($photo) : $photo)
                                        : asset('storage/' . ltrim($photo, '/')) }}"
                                        alt="{{ $asset->Asset_name ?? 'Asset' }}"
                                        class="h-24 w-24 object-cover" />
                                    </div>
                                </div>
                                @else
                                <div class="h-24 w-24 rounded-lg flex items-center justify-center text-xs" style="background:rgba(255,255,255,.08); color:#C7D2E3; border:1px dashed rgba(255,255,255,.25);">
                                    No Photo
                                </div>
                                @endif

                                @if(!empty($asset->qr_code_url) || !empty($asset->qr_code_path))
                                <div class="mt-4 stub p-3">
                                    <p class="eyebrow" style="color:var(--navy-800); font-size:.6rem;">Scan to Verify</p>
                                    <img src="{{ $asset->qr_code_url ?? (\Illuminate\Support\Facades\Storage::url($asset->qr_code_path)) }}" alt="Asset QR" class="h-20 w-20 mt-1 mx-auto" />
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="p-6 md:p-8">

                        <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                            <div>
                                <p class="field-label">Acquisition Date</p>
                                <p class="field-value">{{ $asset->accusion_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="field-label">Purchase Price</p>
                                <p class="field-value font-mono">{{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}</p>
                            </div>
                            <div>
                                <p class="field-label">Serial Number</p>
                                <p class="field-value font-mono">{{ $asset->serial_Number ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="field-label">Location</p>
                                <p class="field-value">{{ $asset->asset_location ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="field-label">Condition</p>
                                <p class="field-value">{{ $asset->Condition ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="field-label">Category</p>
                                <p class="field-value">{{ $asset->Category ?? '—' }}</p>
                            </div>
                        </div>

                        <!-- Lifespan Information -->
                        @if($asset->lifespan_months || $asset->expiration_date)
                        <div class="mt-8 pt-6" style="border-top:1px solid var(--line);">
                            <p class="eyebrow mb-1">Lifecycle</p>
                            <h3 class="section-title mb-4">Asset Lifespan</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="field-label">Lifespan Duration</p>
                                    <p class="field-value">{{ $asset->lifespan_months ? $asset->lifespan_months . ' months' : '—' }}</p>
                                </div>
                                <div>
                                    <p class="field-label">Expiration Date</p>
                                    @if($asset->expiration_date)
                                        @php
                                            $expirationDate = \Carbon\Carbon::parse($asset->expiration_date);
                                            $isExpired = $expirationDate->isPast();
                                            $daysRemaining = now()->diffInDays($expirationDate, false);
                                        @endphp
                                        <p class="field-value" style="color: {{ $isExpired ? 'var(--brick-dark)' : ($daysRemaining < 90 ? 'var(--bronze-dark)' : 'var(--ink-900)') }}; font-weight:{{ $isExpired ? '700' : '500' }};">
                                            {{ $expirationDate->format('M d, Y') }}
                                            @if($isExpired)
                                                <span class="text-xs ml-1 font-normal">(Expired {{ abs($daysRemaining) }} days ago)</span>
                                            @elseif($daysRemaining < 90)
                                                <span class="text-xs ml-1 font-normal">({{ $daysRemaining }} days remaining)</span>
                                            @endif
                                        </p>
                                    @else
                                        <p class="field-value">—</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Maintenance Information -->
                        @if($asset->maintenance_interval || $asset->next_maintenance_date)
                        <div class="mt-8 pt-6" style="border-top:1px solid var(--line);">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <p class="eyebrow mb-1">Upkeep</p>
                                    <h3 class="section-title">Maintenance Schedule</h3>
                                </div>
                                @if($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->isPast())
                                    <span class="status-pill" style="background:#F7E2DF; border-color:var(--brick); color:var(--brick-dark);">OVERDUE</span>
                                @elseif($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->diffInDays(now()) <= 14)
                                    <span class="status-pill" style="background:#F5EAD4; border-color:var(--bronze); color:var(--bronze-dark);">DUE SOON</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="field-label">Maintenance Interval</p>
                                    <p class="field-value">{{ $asset->maintenance_interval ? $asset->maintenance_interval . ' months' : '—' }}</p>
                                </div>
                                <div>
                                    <p class="field-label">Last Maintenance Date</p>
                                    <p class="field-value">{{ $asset->last_maintenance_date ? \Carbon\Carbon::parse($asset->last_maintenance_date)->format('M d, Y') : 'Never' }}</p>
                                </div>
                                <div>
                                    <p class="field-label">Next Maintenance Due</p>
                                    @if($asset->next_maintenance_date)
                                        @php
                                            $nextMaintDate = \Carbon\Carbon::parse($asset->next_maintenance_date);
                                            $isOverdue = $nextMaintDate->isPast();
                                            $daysUntilDue = now()->diffInDays($nextMaintDate, false);
                                        @endphp
                                        <p class="field-value" style="color: {{ $isOverdue ? 'var(--brick-dark)' : ($daysUntilDue < 14 ? 'var(--bronze-dark)' : 'var(--ink-900)') }}; font-weight:{{ $isOverdue ? '700' : '500' }};">
                                            {{ $nextMaintDate->format('M d, Y') }}
                                            @if($isOverdue)
                                                <span class="text-xs ml-1 font-normal">({{ abs($daysUntilDue) }} days overdue)</span>
                                            @elseif($daysUntilDue < 14)
                                                <span class="text-xs ml-1 font-normal">({{ $daysUntilDue }} days)</span>
                                            @endif
                                        </p>
                                    @else
                                        <p class="field-value">—</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="field-label">Repair History</p>
                                    <p class="field-value">{{ $asset->repair_counts ?? 0 }} repair(s)</p>
                                </div>
                            </div>

                            <!-- Mark Maintenance Complete Button -->
                            @if($asset->next_maintenance_date && !($asset->expiration_date && \Carbon\Carbon::parse($asset->expiration_date)->isPast()))
                                <button onclick="openMaintenanceCompleteModal()" class="btn-primary mt-2" style="background:var(--forest);">
                                    <i class="ri-checkbox-circle-line mr-2"></i>
                                    Mark Maintenance Complete
                                </button>
                            @endif
                        </div>
                        @endif

                       @php
                            $isExpired = $asset->expiration_date && \Carbon\Carbon::parse($asset->expiration_date)->isPast();
                            $isPullout = ($asset->Lifecycle_Status ?? '') === 'Pullout';
                        @endphp

                        @if($isExpired)
                        <div class="mt-8 pt-6" style="border-top:1px solid var(--line);">
                            <div class="rounded-xl p-6" style="background:#FBF3F0; border:1px solid #E7C9C1;">
                                <div class="flex items-start gap-3 mb-5">
                                    <div class="icon-badge flex-shrink-0" style="background:var(--brick); width:2.5rem; height:2.5rem;">
                                        <i class="ri-error-warning-line text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-display text-lg font-semibold" style="color:var(--brick-dark);">
                                            @if($isPullout)
                                                Expired Pullout Asset
                                            @else
                                                Expired Asset Evaluation
                                            @endif
                                        </h3>
                                        <p class="text-sm mt-1" style="color:#7A4A44;">
                                            @if($isPullout)
                                                This pulled-out asset has reached the end of its operational lifespan. You can extend its lifespan or proceed with disposal. Status will remain <strong>Pullout</strong>.
                                            @else
                                                This asset has reached the end of its operational lifespan and requires evaluation.
                                            @endif
                                        </p>
                                        <p class="text-sm mt-2" style="color:#7A4A44;">Please select an appropriate action:</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @if($isPullout)
                                        {{-- Pullout + expired: only Extend Lifespan + Disposal --}}
                                        <button onclick="openExtendLifespanModal()" class="action-tile" style="border-left-color:var(--forest);">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-display font-semibold" style="color:var(--navy-900);">Extend Lifespan</p>
                                                    <p class="text-xs mt-1" style="color:var(--ink-600);">Keep as Pullout<br/>Add months to expiration date</p>
                                                </div>
                                                <div class="icon-badge" style="background:var(--forest);"><i class="ri-calendar-check-line"></i></div>
                                            </div>
                                        </button>

                                        <button onclick="openProceedDisposalModal()" class="action-tile" style="border-left-color:var(--brick);">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-display font-semibold" style="color:var(--navy-900);">Proceed with Disposal</p>
                                                    <p class="text-xs mt-1" style="color:var(--ink-600);">Asset no longer serviceable<br/>End of life disposal process</p>
                                                </div>
                                                <div class="icon-badge" style="background:var(--brick);"><i class="ri-delete-bin-line"></i></div>
                                            </div>
                                        </button>
                                    @else
                                        {{-- Normal For Checking evaluation --}}
                                        <button onclick="openReturnToActiveModal()" class="action-tile" style="border-left-color:var(--forest);">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-display font-semibold" style="color:var(--navy-900);">Return to Active</p>
                                                    <p class="text-xs mt-1" style="color:var(--ink-600);">Asset is still functional<br/>Optional lifespan extension</p>
                                                </div>
                                                <div class="icon-badge" style="background:var(--forest);"><i class="ri-check-double-line"></i></div>
                                            </div>
                                        </button>

                                        <button onclick="openSendToRepairModal()" class="action-tile" style="border-left-color:var(--bronze);">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-display font-semibold" style="color:var(--navy-900);">Send for Repair</p>
                                                    <p class="text-xs mt-1" style="color:var(--ink-600);">Asset needs maintenance<br/>Schedule repair evaluation</p>
                                                </div>
                                                <div class="icon-badge" style="background:var(--bronze);"><i class="ri-tools-line"></i></div>
                                            </div>
                                        </button>

                                        <button onclick="openRecommendReplacementModal()" class="action-tile" style="border-left-color:var(--steel);">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-display font-semibold" style="color:var(--navy-900);">Recommend Replacement</p>
                                                    <p class="text-xs mt-1" style="color:var(--ink-600);">Asset beyond economical repair<br/>Initiate replacement request</p>
                                                </div>
                                                <div class="icon-badge" style="background:var(--steel);"><i class="ri-refresh-line"></i></div>
                                            </div>
                                        </button>

                                        <button onclick="openProceedDisposalModal()" class="action-tile" style="border-left-color:var(--brick);">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-display font-semibold" style="color:var(--navy-900);">Proceed with Disposal</p>
                                                    <p class="text-xs mt-1" style="color:var(--ink-600);">Asset no longer serviceable<br/>End of life disposal process</p>
                                                </div>
                                                <div class="icon-badge" style="background:var(--brick);"><i class="ri-delete-bin-line"></i></div>
                                            </div>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="mt-8">
                            <a href="/admin/assets" class="btn-ghost inline-flex items-center">&larr; Back to assets</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Maintenance Complete Modal -->
    <div id="maintenanceCompleteModal" class="fixed inset-0 hidden z-50 flex items-center justify-center" style="background:rgba(10,24,48,.55);" onclick="closeMaintenanceCompleteModal(event)">
        <div class="modal-panel shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="modal-head p-6 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Mark Maintenance Complete</h3>
                <button onclick="closeMaintenanceCompleteModal()" class="text-white/60 hover:text-white">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <form id="maintenanceCompleteForm">
                    @csrf
                    <div class="mb-4">
                        <label for="maintenanceDate" class="field-label block mb-2">Completion Date</label>
                        <input
                            type="date"
                            id="maintenanceDate"
                            name="completion_date"
                            value="{{ date('Y-m-d') }}"
                            class="form-input"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="maintenanceNotes" class="field-label block mb-2">Maintenance Notes (Optional)</label>
                        <textarea
                            id="maintenanceNotes"
                            name="notes"
                            placeholder="e.g., Replaced filters, lubricated joints, all systems operational"
                            rows="4"
                            class="form-input resize-none"
                        ></textarea>
                    </div>

                    <div id="maintenanceError" class="text-sm mb-4" style="display: none; color:var(--brick-dark);"></div>
                    <div id="maintenanceSuccess" class="text-sm mb-4" style="display: none; color:var(--forest-dark);"></div>

                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            onclick="closeMaintenanceCompleteModal()"
                            class="btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            style="background:var(--forest);"
                        >
                            <i class="ri-checkbox-circle-line mr-2"></i>
                            Mark Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Return to Active Modal -->
    <div id="returnToActiveModal" class="fixed inset-0 hidden z-50 flex items-center justify-center" style="background:rgba(10,24,48,.55);" onclick="closeReturnToActiveModal(event)">
        <div class="modal-panel shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="modal-head p-6 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Return Asset to Active</h3>
                <button onclick="closeReturnToActiveModal()" class="text-white/60 hover:text-white">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <form id="returnToActiveForm">
                    @csrf
                    <div class="mb-4 notice-box" style="background:#EFF7F1; border-color:#BFDEC7;">
                        <p style="color:var(--forest-dark);">Asset will be returned to <strong>Active</strong> status and can resume operational use.</p>
                    </div>

                    <div class="mb-4">
                        <label for="extendLifespan" class="flex items-center">
                            <input type="checkbox" id="extendLifespan" name="extend_lifespan" class="w-4 h-4 rounded border-gray-300" style="accent-color: var(--forest);" onchange="toggleExtensionFields()">
                            <span class="ml-2 text-sm font-medium" style="color:var(--ink-900);">Extend asset lifespan</span>
                        </label>
                        <p class="text-xs mt-1 ml-6" style="color:var(--ink-400);">Optional: Add additional months to operational lifespan</p>
                    </div>

                    <div id="extensionFields" style="display: none;" class="mb-4 notice-box" style="background:#EEF3FA; border-color:#C7D6EA;">
                        <label for="extensionMonths" class="field-label block mb-2">Additional Lifespan Months</label>
                        <input
                            type="number"
                            id="extensionMonths"
                            name="extension_months"
                            min="1"
                            max="120"
                            value="12"
                            placeholder="Number of months to extend"
                            class="form-input"
                        />
                        <p class="text-xs mt-2" style="color:var(--ink-600);">New expiration date will be: <span id="newExpirationDate" class="font-mono">N/A</span></p>
                    </div>

                    <div class="mb-4">
                        <label for="evaluationNotes" class="field-label block mb-2">Evaluation Notes</label>
                        <textarea
                            id="evaluationNotes"
                            name="evaluation_notes"
                            placeholder="e.g., Asset condition satisfactory, performs all required functions, recommend continued use"
                            rows="3"
                            class="form-input resize-none"
                        ></textarea>
                    </div>

                    <div id="returnError" class="text-sm mb-4" style="display: none; color:var(--brick-dark);"></div>
                    <div id="returnSuccess" class="text-sm mb-4" style="display: none; color:var(--forest-dark);"></div>

                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            onclick="closeReturnToActiveModal()"
                            class="btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            style="background:var(--forest);"
                        >
                            <i class="ri-check-double-line mr-2"></i>
                            Return to Active
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send for Repair Modal -->
    <div id="sendToRepairModal" class="fixed inset-0 hidden z-50 flex items-center justify-center" style="background:rgba(10,24,48,.55);" onclick="closeSendToRepairModal(event)">
        <div class="modal-panel shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="modal-head p-6 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Send Asset for Repair</h3>
                <button onclick="closeSendToRepairModal()" class="text-white/60 hover:text-white">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <form id="sendToRepairForm">
                    @csrf
                    <div class="mb-4 notice-box" style="background:#FBF3E4; border-color:#E7CE9C;">
                        <p style="color:var(--bronze-dark);">Asset will transition to <strong>For Repair</strong> status. Maintenance evaluation and servicing will be scheduled.</p>
                    </div>

                    <div class="mb-4">
                        <label for="repairDescription" class="field-label block mb-2">Issues or Deterioration Identified</label>
                        <textarea
                            id="repairDescription"
                            name="repair_description"
                            placeholder="e.g., Display flickering, keyboard unresponsive, battery not charging, performance degradation"
                            rows="4"
                            class="form-input resize-none"
                        ></textarea>
                    </div>

                    <div id="repairError" class="text-sm mb-4" style="display: none; color:var(--brick-dark);"></div>
                    <div id="repairSuccess" class="text-sm mb-4" style="display: none; color:var(--forest-dark);"></div>

                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            onclick="closeSendToRepairModal()"
                            class="btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            style="background:var(--bronze);"
                        >
                            <i class="ri-tools-line mr-2"></i>
                            Send for Repair
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recommend Replacement Modal -->
    <div id="recommendReplacementModal" class="fixed inset-0 hidden z-50 flex items-center justify-center" style="background:rgba(10,24,48,.55);" onclick="closeRecommendReplacementModal(event)">
        <div class="modal-panel shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="modal-head p-6 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Recommend Replacement</h3>
                <button onclick="closeRecommendReplacementModal()" class="text-white/60 hover:text-white">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <form id="recommendReplacementForm">
                    @csrf
                    <div class="mb-4 notice-box" style="background:#EEF3FA; border-color:#C7D6EA;">
                        <p style="color:var(--steel-dark);">Asset will transition to <strong>For Replacement</strong> status. A replacement request will be initiated and requires approval.</p>
                    </div>

                    <div class="mb-4">
                        <label for="replacementReason" class="field-label block mb-2">Reason for Replacement</label>
                        <textarea
                            id="replacementReason"
                            name="replacement_reason"
                            placeholder="e.g., Beyond economical repair, frequent failures, obsolete technology, does not meet operational requirements"
                            rows="4"
                            class="form-input resize-none"
                        ></textarea>
                    </div>

                    <div id="replacementError" class="text-sm mb-4" style="display: none; color:var(--brick-dark);"></div>
                    <div id="replacementSuccess" class="text-sm mb-4" style="display: none; color:var(--forest-dark);"></div>

                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            onclick="closeRecommendReplacementModal()"
                            class="btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            style="background:var(--steel);"
                        >
                            <i class="ri-refresh-line mr-2"></i>
                            Recommend Replacement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Proceed with Disposal Modal -->
    <div id="proceedDisposalModal" class="fixed inset-0 hidden z-50 flex items-center justify-center" style="background:rgba(10,24,48,.55);" onclick="closeProceedDisposalModal(event)">
        <div class="modal-panel shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="modal-head p-6 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Proceed with Disposal</h3>
                <button onclick="closeProceedDisposalModal()" class="text-white/60 hover:text-white">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <form id="proceedDisposalForm">
                    @csrf
                    <div class="mb-4 notice-box" style="background:#FBF3F0; border-color:#E7C9C1;">
                        <p style="color:var(--brick-dark);"><strong>Warning:</strong> Asset will transition to disposal process. This action marks the end of the asset's operational lifespan.</p>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="confirmDisposal" name="confirm_disposal" class="w-4 h-4 rounded border-gray-300" style="accent-color: var(--brick);" onchange="toggleDisposalConfirm()">
                            <span class="ml-2 text-sm font-medium" style="color:var(--ink-900);">I confirm this asset should be disposed</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label for="disposalReason" class="field-label block mb-2">Reason for Disposal</label>
                        <textarea
                            id="disposalReason"
                            name="disposal_reason"
                            placeholder="e.g., End of life, no longer serviceable, obsolete, environmental concerns"
                            rows="4"
                            class="form-input resize-none"
                        ></textarea>
                    </div>

                    <div id="disposalError" class="text-sm mb-4" style="display: none; color:var(--brick-dark);"></div>
                    <div id="disposalSuccess" class="text-sm mb-4" style="display: none; color:var(--forest-dark);"></div>

                    <div class="flex gap-3 justify-end">
                        <button
                            type="button"
                            onclick="closeProceedDisposalModal()"
                            class="btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            id="disposalSubmitBtn"
                            disabled
                            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background:var(--brick);"
                        >
                            <i class="ri-delete-bin-line mr-2"></i>
                            Proceed with Disposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
<!-- Extend Lifespan Modal (Pullout only — status stays Pullout) -->
<div id="extendLifespanModal" class="fixed inset-0 hidden z-50 flex items-center justify-center" style="background:rgba(10,24,48,.55);" onclick="closeExtendLifespanModal(event)">
    <div class="modal-panel shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
        <div class="modal-head p-6 flex justify-between items-center">
            <h3 class="font-display text-xl font-semibold text-white">Extend Asset Lifespan</h3>
            <button onclick="closeExtendLifespanModal()" class="text-white/60 hover:text-white">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>
        <div class="p-6">
            <form id="extendLifespanForm">
                @csrf
                <div class="mb-4 notice-box" style="background:#EFF7F1; border-color:#BFDEC7;">
                    <p style="color:var(--forest-dark);">Asset will <strong>remain in Pullout</strong> status. Only the expiration date will be updated.</p>
                </div>

                <div class="mb-4">
                    <label for="pulloutExtensionMonths" class="field-label block mb-2">Additional Lifespan Months *</label>
                    <input type="number" id="pulloutExtensionMonths" name="extension_months" min="1" max="120" value="12" class="form-input" required />
                    <p class="text-xs mt-2" style="color:var(--ink-600);">New expiration date will be: <span id="pulloutNewExpirationDate" class="font-mono">N/A</span></p>
                </div>

                <div class="mb-4">
                    <label for="pulloutExtensionNotes" class="field-label block mb-2">Notes (Optional)</label>
                    <textarea id="pulloutExtensionNotes" name="notes" rows="3" class="form-input resize-none"
                        placeholder="e.g., Extended while in storage, still serviceable if returned"></textarea>
                </div>

                <div id="extendLifespanError" class="text-sm mb-4" style="display:none; color:var(--brick-dark);"></div>
                <div id="extendLifespanSuccess" class="text-sm mb-4" style="display:none; color:var(--forest-dark);"></div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeExtendLifespanModal()" class="btn-ghost">Cancel</button>
                    <button type="submit" class="btn-primary" style="background:var(--forest);">
                        <i class="ri-calendar-check-line mr-2"></i>Extend Lifespan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



    <script>
        function openMaintenanceCompleteModal() {
            document.getElementById('maintenanceCompleteModal').classList.remove('hidden');
        }
        
        function closeMaintenanceCompleteModal(event) {
            if (event && event.target.id !== 'maintenanceCompleteModal') return;
            document.getElementById('maintenanceCompleteModal').classList.add('hidden');
            document.getElementById('maintenanceCompleteForm').reset();
            document.getElementById('maintenanceDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('maintenanceError').style.display = 'none';
            document.getElementById('maintenanceSuccess').style.display = 'none';
        }
        
        document.getElementById('maintenanceCompleteForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const completionDate = document.getElementById('maintenanceDate').value;
            const notes = document.getElementById('maintenanceNotes').value.trim();
            const errorEl = document.getElementById('maintenanceError');
            const successEl = document.getElementById('maintenanceSuccess');
            
            errorEl.style.display = 'none';
            successEl.style.display = 'none';
            
            if (!completionDate) {
                errorEl.textContent = 'Completion date is required';
                errorEl.style.display = 'block';
                return;
            }
            
            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/maintenance-complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        completion_date: completionDate,
                        notes: notes || 'Maintenance completed'
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ ' + data.message;
                    successEl.style.display = 'block';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to mark maintenance complete';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} shadow-lg z-50`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // ===== Return to Active Modal Functions =====
        function openReturnToActiveModal() {
            document.getElementById('returnToActiveModal').classList.remove('hidden');
            calculateNewExpirationDate();
        }
        
        function closeReturnToActiveModal(event) {
            if (event && event.target.id !== 'returnToActiveModal') return;
            document.getElementById('returnToActiveModal').classList.add('hidden');
            document.getElementById('returnToActiveForm').reset();
            document.getElementById('returnError').style.display = 'none';
            document.getElementById('returnSuccess').style.display = 'none';
            document.getElementById('extensionFields').style.display = 'none';
        }

        function toggleExtensionFields() {
            const checkbox = document.getElementById('extendLifespan');
            const fields = document.getElementById('extensionFields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
            if (checkbox.checked) calculateNewExpirationDate();
        }

        function calculateNewExpirationDate() {
            const months = parseInt(document.getElementById('extensionMonths').value) || 0;
            const currentExpiration = new Date('{{ $asset->expiration_date }}');
            const newDate = new Date(currentExpiration);
            newDate.setMonth(newDate.getMonth() + months);
            document.getElementById('newExpirationDate').textContent = newDate.toLocaleDateString();
        }

        document.getElementById('extensionMonths').addEventListener('change', calculateNewExpirationDate);

        document.getElementById('returnToActiveForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('returnError');
            const successEl = document.getElementById('returnSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            try {
                const assetId = '{{ $asset->id }}';
                const extendLifespan = document.getElementById('extendLifespan').checked;
                const extensionMonths = extendLifespan ? parseInt(document.getElementById('extensionMonths').value) : 0;
                const notes = document.getElementById('evaluationNotes').value.trim();

                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'return_active',
                        extension_months: extensionMonths,
                        evaluation_notes: notes || 'Asset returned to active status'
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Asset successfully returned to active status';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to update asset';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });

        // ===== Send for Repair Modal Functions =====
        function openSendToRepairModal() {
            document.getElementById('sendToRepairModal').classList.remove('hidden');
        }
        
        function closeSendToRepairModal(event) {
            if (event && event.target.id !== 'sendToRepairModal') return;
            document.getElementById('sendToRepairModal').classList.add('hidden');
            document.getElementById('sendToRepairForm').reset();
            document.getElementById('repairError').style.display = 'none';
            document.getElementById('repairSuccess').style.display = 'none';
        }

        document.getElementById('sendToRepairForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('repairError');
            const successEl = document.getElementById('repairSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            const description = document.getElementById('repairDescription').value.trim();
            if (!description) {
                errorEl.textContent = 'Please describe the issues identified';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'send_repair',
                        evaluation_notes: description
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Asset sent for repair evaluation';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to send asset for repair';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });

        // ===== Recommend Replacement Modal Functions =====
        function openRecommendReplacementModal() {
            document.getElementById('recommendReplacementModal').classList.remove('hidden');
        }
        
        function closeRecommendReplacementModal(event) {
            if (event && event.target.id !== 'recommendReplacementModal') return;
            document.getElementById('recommendReplacementModal').classList.add('hidden');
            document.getElementById('recommendReplacementForm').reset();
            document.getElementById('replacementError').style.display = 'none';
            document.getElementById('replacementSuccess').style.display = 'none';
        }

        document.getElementById('recommendReplacementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('replacementError');
            const successEl = document.getElementById('replacementSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            const reason = document.getElementById('replacementReason').value.trim();
            if (!reason) {
                errorEl.textContent = 'Please provide a reason for replacement';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'recommend_replacement',
                        evaluation_notes: reason
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Replacement request initiated';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to initiate replacement';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });

        function openExtendLifespanModal() {
    document.getElementById('extendLifespanModal').classList.remove('hidden');
    calcPulloutNewExpiration();
}
function closeExtendLifespanModal(event) {
    if (event && event.target.id !== 'extendLifespanModal') return;
    document.getElementById('extendLifespanModal').classList.add('hidden');
    document.getElementById('extendLifespanForm').reset();
    document.getElementById('pulloutExtensionMonths').value = 12;
    document.getElementById('extendLifespanError').style.display = 'none';
    document.getElementById('extendLifespanSuccess').style.display = 'none';
}
function calcPulloutNewExpiration() {
    const months = parseInt(document.getElementById('pulloutExtensionMonths').value) || 0;
    const current = new Date('{{ $asset->expiration_date }}');
    const next = new Date(current);
    next.setMonth(next.getMonth() + months);
    document.getElementById('pulloutNewExpirationDate').textContent = next.toLocaleDateString();
}
document.getElementById('pulloutExtensionMonths')?.addEventListener('change', calcPulloutNewExpiration);
document.getElementById('pulloutExtensionMonths')?.addEventListener('input', calcPulloutNewExpiration);

document.getElementById('extendLifespanForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorEl = document.getElementById('extendLifespanError');
    const successEl = document.getElementById('extendLifespanSuccess');
    errorEl.style.display = 'none';
    successEl.style.display = 'none';

    const months = parseInt(document.getElementById('pulloutExtensionMonths').value);
    if (!months || months < 1) {
        errorEl.textContent = 'Please enter a valid number of months';
        errorEl.style.display = 'block';
        return;
    }

    try {
        const assetId = '{{ $asset->id }}';
        const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                action: 'extend_lifespan_pullout',
                extension_months: months,
                evaluation_notes: document.getElementById('pulloutExtensionNotes').value.trim() || 'Lifespan extended while in pullout'
            })
        });
        const data = await response.json();
        if (response.ok) {
            successEl.textContent = '✓ Lifespan extended. Asset remains in Pullout.';
            successEl.style.display = 'block';
            setTimeout(() => location.reload(), 1500);
        } else {
            errorEl.textContent = data.message || 'Failed to extend lifespan';
            errorEl.style.display = 'block';
        }
    } catch (err) {
        errorEl.textContent = 'Error: ' + err.message;
        errorEl.style.display = 'block';
    }
});


        // ===== Proceed with Disposal Modal Functions =====
        function openProceedDisposalModal() {
            document.getElementById('proceedDisposalModal').classList.remove('hidden');
        }
        
        function closeProceedDisposalModal(event) {
            if (event && event.target.id !== 'proceedDisposalModal') return;
            document.getElementById('proceedDisposalModal').classList.add('hidden');
            document.getElementById('proceedDisposalForm').reset();
            document.getElementById('confirmDisposal').checked = false;
            document.getElementById('disposalSubmitBtn').disabled = true;
            document.getElementById('disposalError').style.display = 'none';
            document.getElementById('disposalSuccess').style.display = 'none';
        }

        function toggleDisposalConfirm() {
            const confirmed = document.getElementById('confirmDisposal').checked;
            document.getElementById('disposalSubmitBtn').disabled = !confirmed;
        }

        document.getElementById('proceedDisposalForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('disposalError');
            const successEl = document.getElementById('disposalSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            const reason = document.getElementById('disposalReason').value.trim();
            if (!reason) {
                errorEl.textContent = 'Please provide a reason for disposal';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'proceed_disposal',
                        evaluation_notes: reason
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Asset marked for disposal';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to mark asset for disposal';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });
    </script>
</body>
</html>