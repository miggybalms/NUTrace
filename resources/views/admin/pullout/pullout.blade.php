{{-- resources/views/admin/pullout.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pullout Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--paper);
            color: var(--ink-900);
        }

        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-400); }

        .sidebar-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .sidebar-item:hover {
            background-color: #374151;
        }
        
        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }

        .btn-gold{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; background:var(--gold-500); color:var(--navy-950); display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-gold:hover{ filter:brightness(1.06); }
        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:500; border-radius:9px; padding:.55rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; transition:background .15s; }
        .btn-ghost:hover{ background:var(--paper-2); }

        .hero-card{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); border-radius:14px; position:relative; overflow:hidden; }
        .hero-card::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:3px; background:linear-gradient(90deg,transparent, var(--gold-500), transparent); }

        .pullout-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28);
            transition: all 0.3s ease;
        }
        
        .pullout-card:hover {
            transform: translateY(-2px);
            border-color: var(--gold-500);
            box-shadow: 0 2px 4px rgba(10,24,48,.06), 0 16px 32px -16px rgba(10, 24, 48, 0.3);
        }
        
        .modal {
            transition: all 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .status-badge {
            transition: all 0.2s ease;
        }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
        .form-input{ width:100%; border:1px solid var(--line); border-radius:9px; padding:.55rem .9rem; font-size:.9rem; outline:none; transition:border-color .15s, box-shadow .15s; }
        .form-input:focus{ border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }

        /* Fixed scanner styles matching disposal page */
        #qrScanner {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        #qrScanner video, 
        #qrScanner img {
            width: 100%;
            height: auto;
            display: block;
        }

        #qrVideo {
            width: 100%;
            height: auto;
            display: block;
            background: #000;
            border-radius: 8px;
            transform: scaleX(1);
        }

        #qrScannerWrap {
            background: #000;
            border-radius: 8px;
            padding: 0;
        }
    </style>
</head>
<body>
    @php
    $pulloutRecords = $pulloutRecords ?? collect();
    $availableAssets = $availableAssets ?? collect();
    if (!isset($totalPulledOut)) {
        $totalPulledOut = is_countable($pulloutRecords) ? count($pulloutRecords) : 0;
    }
    @endphp
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
                <!-- Header -->
                <div class="topbar sticky top-0 z-10">
                    <div class="px-4 sm:px-8 py-5">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <!-- Hamburger, mobile only -->
                                <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-400);">
                                    <i class="ri-menu-line text-2xl"></i>
                                </button>
                                <a href="#" onclick="window.history.back(); return false;" class="mr-4 transition-transform hover:translate-x-[-2px]" style="color:var(--ink-400);">
                                    <i class="ri-arrow-left-line text-xl"></i>
                                </a>
                                <div>
                                    <h2 class="font-display text-xl sm:text-2xl font-semibold" style="color:var(--navy-900);">Record Pullout</h2>
                                    <p class="text-sm mt-1 hidden sm:block" style="color:var(--ink-600);">Manage pulled out assets</p>
                                </div>
                            </div>
                            <button onclick="openScannerAuto()" class="btn-gold">
                                <i class="ri-add-line sm:mr-2"></i>
                                <span class="hidden sm:inline">Record Pullout</span>
                            </button>
                        </div>
                    </div>
                </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Stats Card -->
                <div class="hero-card p-6 mb-8 text-white" id="statsCard">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="eyebrow" style="color:var(--gold-500);">Total Pulled Out</p>
                            <p class="font-display text-4xl font-bold mt-2" id="totalPulledOutCount">{{ $totalPulledOut }}</p>
                            <p class="text-xs mt-2" style="color:#C7D2E3;">Complete log of pulled out institutional assets</p>
                        </div>
                        <div class="w-20 h-20 rounded-full flex items-center justify-center" style="background:rgba(180,121,30,.35); border:1px solid rgba(255,255,255,.15);">
                            <i class="ri-logout-box-r-line text-4xl" style="color:#F3DCB0;"></i>
                        </div>
                    </div>
                </div>

                <!-- Pullout Records List -->
                <div id="pulloutRecordsContainer">
                    @if(isset($pulloutRecords) && count($pulloutRecords) > 0)
                        <div class="grid grid-cols-1 gap-4" id="pulloutRecordsList">
                            @foreach($pulloutRecords as $record)
                            <div class="pullout-card p-6" data-id="{{ $record->id }}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3" style="background:var(--bronze-tint);">
                                                <i class="ri-logout-box-r-line text-xl" style="color:var(--bronze);"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold" style="color:var(--navy-900);">
                                                    @if(($record->asset_count ?? 1) > 1)
                                                        {{ $record->asset_count }} Assets
                                                    @else
                                                        {{ $record->asset_name ?? 'Asset' }}
                                                    @endif
                                                </h3>
                                                <p class="text-xs font-mono" style="color:var(--ink-400);">
                                                    @if(($record->asset_count ?? 1) > 1)
                                                        {{ $record->asset_codes->take(3)->implode(', ') }}
                                                        @if($record->asset_codes->count() > 3)
                                                            ...
                                                        @endif
                                                    @else
                                                        {{ $record->asset_code ?? 'N/A' }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        @if(($record->asset_count ?? 1) > 1)
                                        <div class="mb-3 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" style="background:var(--bronze-tint); color:var(--bronze-dark);">
                                            {{ $record->asset_count }} assets in one pullout
                                        </div>
                                        @endif
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Pullout Date</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->pullout_date ?? date('Y-m-d') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Reason</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->reason ?? $record->Description ?? $record->notes ?? '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Pulled By</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->pulled_by ?? 'Admin' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Status</p>
                                                <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                    style="
                                                    @if(($record->status ?? 'pending') == 'pending') background:var(--bronze-tint); color:var(--bronze-dark);
                                                    @elseif(($record->status ?? 'pending') == 'approved') background:var(--forest-tint); color:var(--forest-dark);
                                                    @else background:var(--brick-tint); color:var(--brick-dark);
                                                    @endif">
                                                    {{ ucfirst($record->status ?? 'pending') }}
                                                </span>
                                            </div>
                                        </div>
                                        @if($record->destination ?? false)
                                        <div class="mt-3 pt-3" style="border-top:1px solid var(--line);">
                                            <p class="text-xs" style="color:var(--ink-400);">Destination / New Location</p>
                                            <p class="text-sm" style="color:var(--ink-600);">{{ $record->destination }}</p>
                                        </div>
                                        @endif
                                    </div>
                                        <div class="flex space-x-1">
                                            <button onclick="viewPulloutDetails({{ $record->id }})" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--steel);" onmouseover="this.style.background='var(--steel-tint)'" onmouseout="this.style.background='transparent'" title="View">
                                            <i class="ri-eye-line text-xl"></i>
                                            </button>
                                            @if(($record->status ?? 'pending') == 'pending')
                                            <button onclick="approvePullout({{ $record->id }})" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--forest);" onmouseover="this.style.background='var(--forest-tint)'" onmouseout="this.style.background='transparent'" title="Approve">
                                            <i class="ri-checkbox-circle-line text-xl"></i>
                                            </button>
                                            @endif
                                            <button onclick="openEditPullout({{ $record->id }})" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--bronze);" onmouseover="this.style.background='var(--bronze-tint)'" onmouseout="this.style.background='transparent'" title="Edit / Resolve">
                                            <i class="ri-edit-line text-xl"></i>
                                            </button>
                                            <button onclick="openDisposeFromPullout({{ $record->id }})" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--brick);" onmouseover="this.style.background='var(--brick-tint)'" onmouseout="this.style.background='transparent'" title="Dispose assets">
                                            <i class="ri-delete-bin-line text-xl"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div id="emptyState" class="p-12 text-center" style="background:#fff; border-radius:14px; border:1px solid var(--line);">
                            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--paper-2);">
                                <i class="ri-logout-box-r-line text-4xl" style="color:var(--ink-400);"></i>
                            </div>
                            <h3 class="text-lg font-semibold mb-2" style="color:var(--navy-900);">No pullout records yet</h3>
                            <p style="color:var(--ink-400);">There are currently no pullout reports to show.</p>
                            <button onclick="openNewPulloutModal()" class="btn-gold mt-4">
                                <i class="ri-add-line mr-2"></i>
                                Record First Pullout
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="text-center text-sm mt-8 pt-6" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- View Pullout Details Modal -->
<div id="viewPulloutModal" class="hidden fixed inset-0 z-50 items-center justify-center" style="background:rgba(10,24,48,.55);">
    <div class="rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
        <!-- Header -->
        <div class="modal-head p-6 flex justify-between items-center">
            <div>
                <h3 class="font-display text-xl font-semibold text-white">Pullout Details</h3>
                <p class="text-sm mt-1" style="color:var(--gold-100);">Pullout #<span id="viewPulloutIdLabel">—</span></p>
            </div>
            <button type="button" onclick="closeViewPullout()" class="text-white/60 hover:text-white">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-5">
            <!-- Basic info -->
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs" style="color:var(--ink-400);">Pullout Date</p>
                    <p id="viewPulloutDate" class="font-medium" style="color:var(--navy-900);">—</p>
                </div>
                <div>
                    <p class="text-xs" style="color:var(--ink-400);">Status</p>
                    <p id="viewPulloutStatus" class="font-medium">—</p>
                </div>
                <div>
                    <p class="text-xs" style="color:var(--ink-400);">Reason</p>
                    <p id="viewPulloutReason" class="font-medium" style="color:var(--navy-900);">—</p>
                </div>
                <div>
                    <p class="text-xs" style="color:var(--ink-400);">Pulled By</p>
                    <p id="viewPulloutBy" class="font-medium" style="color:var(--navy-900);">—</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs" style="color:var(--ink-400);">Destination / New Location</p>
                    <p id="viewPulloutDestination" class="font-medium" style="color:var(--navy-900);">—</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs" style="color:var(--ink-400);">Notes</p>
                    <p id="viewPulloutNotes" class="font-medium" style="color:var(--navy-900);">—</p>
                </div>
            </div>

            <!-- Assets list -->
            <div>
                <h4 class="text-sm font-semibold mb-2" style="color:var(--ink-600);">Assets in this pullout</h4>
                <div id="viewAssetList" class="rounded-lg divide-y max-h-64 overflow-y-auto" style="border:1px solid var(--line); border-color:var(--line);">
                    <!-- Filled by JS -->
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 flex justify-end" style="border-top:1px solid var(--line);">
            <button type="button" onclick="closeViewPullout()" class="btn-ghost">
                Close
            </button>
        </div>
    </div>
</div>


    <!-- New Pullout Modal -->
    <div id="pulloutModal" class="hidden fixed inset-0 z-50 items-center justify-center modal" style="background:rgba(10,24,48,.55);">
        <div class="rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head p-6">
                <div class="flex justify-between items-center">
                    <h3 class="font-display text-xl font-semibold text-white">Record Asset Pullout</h3>
                    <button onclick="closePulloutModal()" class="text-white/60 hover:text-white">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="pulloutForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Select Asset(s) *
                            <button type="button" onclick="openScanner('pullout_asset_select')" title="Scan asset QR" class="ml-3 inline-flex items-center px-2 py-1 rounded text-sm transition-colors" style="border:1px solid var(--line); color:var(--ink-600);" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                                <i class="ri-camera-line"></i>
                                <span class="sr-only">Scan</span>
                            </button>
                        </label>
                        <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="text" id="pulloutAssetSearch" oninput="filterPulloutAssets(this.value)" placeholder="Search assets by code or name..." class="form-input sm:flex-1">
                            <button type="button" onclick="selectAllVisiblePulloutAssets()" class="px-4 py-2 rounded-lg text-sm transition-colors" style="border:1px solid var(--gold-500); color:var(--gold-600); background:var(--gold-100);">
                                Select All Visible
                            </button>
                        </div>
                        <select id="pullout_asset_select" name="asset_ids[]" multiple required class="form-input" style="min-height:180px;">
                            @foreach($availableAssets ?? [] as $asset)
                            <option value="{{ $asset->id }}" data-code="{{ $asset->asset_code }}" data-status="{{ $asset->Lifecycle_Status }}">{{ $asset->name }} ({{ $asset->asset_code }}) - Assigned to: {{ $asset->assignedUser->name ?? 'Unassigned' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs" style="color:var(--ink-400);">Hold Ctrl on Windows or Command on Mac to select multiple assets.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Pullout Date *</label>
                        <input type="date" name="pullout_date" required value="{{ date('Y-m-d') }}" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Reason for Pullout *</label>
                        <select name="reason" required class="form-input">
                            <option value="">Select reason...</option>
                            <option value="Transfer">Transfer to another department</option>
                            <option value="Repair">Needs repair/maintenance</option>
                            <option value="Reassignment">Reassignment to different user</option>
                            <option value="Upgrade">Upgrade to newer equipment</option>
                            <option value="Temporary">Temporary removal</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Pulled By *</label>
                        <input type="text" name="pulled_by" required placeholder="Name of person authorizing pullout" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Destination / New Location</label>
                        <input type="text" name="destination" placeholder="e.g., IT Department, Room 302, Storage Room" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Expected Return Date (if applicable)</label>
                        <input type="date" name="expected_return_date" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any additional information about the pullout..." class="form-input"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4" style="border-top:1px solid var(--line);">
                    <button type="button" onclick="closePulloutModal()" class="btn-ghost">Cancel</button>
                    <button type="submit" class="btn-gold">Record Pullout</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dispose from Pullout Modal -->
<div id="disposeFromPulloutModal" class="hidden fixed inset-0 z-50 items-center justify-center" style="background:rgba(10,24,48,.55);">
    <div class="rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
        <div class="modal-head p-6 flex justify-between items-center">
            <div>
                <h3 class="font-display text-xl font-semibold text-white">Dispose Assets</h3>
                <p class="text-sm mt-1" style="color:var(--gold-100);">Pullout #<span id="disposePulloutIdLabel">—</span></p>
            </div>
            <button type="button" onclick="closeDisposeFromPullout()" class="text-white/60 hover:text-white">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>

        <form id="disposeFromPulloutForm" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="disposePulloutId" name="pullout_id">

            <div>
                <p class="text-sm mb-2" style="color:var(--ink-600);">Select which assets to dispose:</p>
                <div id="disposeAssetList" class="rounded-lg divide-y max-h-56 overflow-y-auto" style="border:1px solid var(--line); border-color:var(--line);">
                    <!-- filled by JS -->
                </div>
                <div class="mt-2 flex gap-2">
                    <button type="button" onclick="selectAllDisposeAssets(true)" class="text-xs hover:underline" style="color:var(--steel);">Select all</button>
                    <button type="button" onclick="selectAllDisposeAssets(false)" class="text-xs hover:underline" style="color:var(--ink-400);">Clear</button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Disposal Date</label>
                <input type="date" name="disposal_date" value="{{ date('Y-m-d') }}" class="form-input">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Reason</label>
                <select name="reason" required class="form-input">
                    <option value="Obsolete">Obsolete</option>
                    <option value="Damage">Damaged</option>
                    <option value="Beyond Repair">Beyond Repair</option>
                    <option value="Lost">Lost / Stolen</option>
                    <option value="Replace">Replaced / Upgraded</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color:var(--ink-600);">Notes (optional)</label>
                <textarea name="notes" rows="2" class="form-input" placeholder="Any additional notes..."></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4" style="border-top:1px solid var(--line);">
                <button type="button" onclick="closeDisposeFromPullout()" class="btn-ghost">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-white rounded-lg text-sm font-medium transition" style="background:var(--brick);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                    Dispose Selected
                </button>
            </div>
        </form>
    </div>
</div>

<div id="editPulloutModal" class="hidden fixed inset-0 z-50 items-center justify-center" style="background:rgba(10,24,48,.55);">
    <div class="rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
        <!-- Header -->
        <div class="modal-head p-6 flex justify-between items-center">
            <div>
                <h3 class="font-display text-xl font-semibold text-white">Resolve Pullout</h3>
                <p class="text-sm mt-1" style="color:var(--gold-100);">Pullout #<span id="editPulloutIdLabel">—</span></p>
            </div>
            <button type="button" onclick="closeEditPullout()" class="text-white/60 hover:text-white">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4">
            <input type="hidden" id="editPulloutId">

            <!-- Asset selection -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium" style="color:var(--ink-600);">Assets in this pullout *</label>
                    <button type="button" onclick="toggleAllEditAssets(true)" class="text-xs hover:underline" style="color:var(--gold-600);">Select All</button>
                </div>
                <div id="editAssetList" class="rounded-lg max-h-40 overflow-y-auto p-2 space-y-1" style="border:1px solid var(--line);">
                    <!-- Filled by JS -->
                </div>
                <p class="text-xs mt-1" style="color:var(--ink-400);">Uncheck any asset you want to leave in pullout.</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Action *</label>
                <select id="editAction" onchange="toggleEditActionFields()" class="form-input">
                    <option value="">Select action...</option>
                    <option value="assign">Assign to new user (release from storage)</option>
                    <option value="repair">Send to repair</option>
                </select>
            </div>

            <!-- Assign block -->
            <div id="editAssignBlock" class="hidden space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">New Owner *</label>
                    <div class="relative">
                        <input type="text" id="editUserSearch" autocomplete="off"
                            placeholder="Type name or email to search..."
                            oninput="filterEditUsers()" onfocus="showEditUserList()"
                            class="form-input">
                        <input type="hidden" id="editAssignUserId" value="">
                        <div id="editUserList"
                            class="hidden absolute z-20 mt-1 w-full max-h-56 overflow-y-auto rounded-lg shadow-lg" style="background:#fff; border:1px solid var(--line);">
                            @foreach($users ?? [] as $u)
                                <button type="button"
                                    class="edit-user-option w-full text-left px-4 py-2 text-sm transition-colors"
                                    style="border-bottom:1px solid var(--line);"
                                    onmouseover="this.style.background='var(--gold-100)'" onmouseout="this.style.background='transparent'"
                                    data-id="{{ $u->id }}"
                                    data-label="{{ strtolower(($u->full_name ?? '') . ' ' . ($u->email ?? '')) }}"
                                    onclick="selectEditUser(this)">
                                    <span class="font-medium" style="color:var(--navy-900);">{{ $u->full_name ?? 'User' }}</span>
                                    <span class="text-xs block" style="color:var(--ink-400);">{{ $u->email }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <p class="text-xs mt-1" style="color:var(--ink-400);">
                        Selected: <span id="editSelectedUserLabel" class="font-medium" style="color:var(--ink-600);">None</span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">New Location *</label>
                    <input type="text" id="editNewLocation" placeholder="e.g., Room 301, Faculty Office, Lab 2" class="form-input">
                </div>
            </div>

            <!-- Repair block -->
            <div id="editRepairBlock" class="hidden">
                <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Issue description</label>
                <textarea id="editRepairNotes" rows="3" placeholder="What needs repair?" class="form-input"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Notes (optional)</label>
                <textarea id="editNotes" rows="2" placeholder="Optional notes..." class="form-input"></textarea>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 flex justify-end gap-3" style="border-top:1px solid var(--line);">
            <button type="button" onclick="closeEditPullout()" class="btn-ghost">Cancel</button>
            <button type="button" onclick="submitEditPullout()" class="btn-gold">Save</button>
        </div>
    </div>
</div> <!-- end of modal overlay -->

    <!-- Scanner Modal - Stays open until manually closed (same style as disposal) -->
    <div id="scannerModal" class="hidden fixed inset-0 z-50 items-center justify-center modal" style="background:rgba(10,24,48,.75);">
        <div class="rounded-xl shadow-2xl max-w-lg w-full mx-4 p-6" style="background:#fff;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-display text-xl font-semibold" style="color:var(--navy-900);">Scan Asset QR</h3>
                <button onclick="manualCloseScanner()" class="transition" style="color:var(--ink-400);">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <!-- QR Scanner Container -->
            <div id="qrScannerWrap" class="mt-2 mb-4">
                <div id="qrScanner" style="width:100%; background:#000; border-radius:8px; overflow:hidden;">
                    <video id="qrVideo" autoplay muted playsinline style="width:100%; height:auto; display:block;"></video>
                    <canvas id="qrCanvas" style="display:none;"></canvas>
                </div>
            </div>

            <!-- Status and Controls -->
            <p id="qr-reader-status" class="text-sm text-center mb-3" style="color:var(--ink-600);">Initializing camera...</p>
            
            <div class="flex justify-center">
                <button onclick="manualCloseScanner()" class="px-6 py-2 text-white rounded-lg transition flex items-center" style="background:var(--bronze);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                    <i class="ri-close-line mr-2"></i>
                    Close Camera
                </button>
            </div>

            <div class="mt-4 p-3 rounded text-sm" style="background:var(--steel-tint); border-left:4px solid var(--steel); color:var(--steel-dark);">
                <strong class="font-medium">💡 Camera Tips:</strong>
                <ul class="mt-1 list-disc list-inside space-y-1">
                    <li>Camera stays open - click "Close Camera" when done</li>
                    <li>Allow camera access when your browser prompts</li>
                    <li>Hold QR code steady in front of camera</li>
                    <li>Check browser permissions if camera doesn't start</li>
                    <li>Scan multiple assets without closing the camera!</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.7/minified/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        // Scanner variables - camera stays open until manually closed (same as disposal)
        let html5QrCode = null;
        let currentScannerSelectId = null;
        let scannerAutoMode = false;
        let isScanning = false;
        let fallbackStream = null;
        let fallbackScanTimer = null;
        let currentDeleteId = null;
        const adminName = "{{ Auth::user()->name ?? Auth::user()->email ?? 'Admin' }}";
        
        const statusEl = document.getElementById('qr-reader-status');


// Store assets of the current pullout
let currentPulloutAssets = [];

function openEditPullout(id) {
    document.getElementById('editPulloutId').value = id;
    document.getElementById('editPulloutIdLabel').textContent = id;
    document.getElementById('editAction').value = '';
    document.getElementById('editAssignUserId').value = '';
    document.getElementById('editNewLocation').value = '';
    document.getElementById('editUserSearch').value = '';
    document.getElementById('editSelectedUserLabel').textContent = 'None';
    document.getElementById('editUserList')?.classList.add('hidden');
    document.getElementById('editNotes').value = '';
    document.getElementById('editRepairNotes').value = '';
    toggleEditActionFields();

    // Load the assets that belong to this pullout
    loadPulloutAssets(id);

    const modal = document.getElementById('editPulloutModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

async function loadPulloutAssets(pulloutId) {
    const container = document.getElementById('editAssetList');
    container.innerHTML = '<p class="text-sm text-gray-500 p-2">Loading assets...</p>';

    try {
        const res = await fetch(`/admin/pullout/${pulloutId}/assets`);
        const data = await res.json();

        if (!data.success || !data.assets.length) {
            container.innerHTML = '<p class="text-sm text-red-500 p-2">No assets found.</p>';
            currentPulloutAssets = [];
            return;
        }

        currentPulloutAssets = data.assets;
        container.innerHTML = '';

        data.assets.forEach(asset => {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded';
            div.innerHTML = `
                <input type="checkbox" class="edit-asset-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                    value="${asset.id}" id="edit_asset_${asset.id}" checked>
                <label for="edit_asset_${asset.id}" class="text-sm cursor-pointer flex-1">
                    <span class="font-medium">${asset.name}</span>
                    <span class="text-xs text-gray-500 font-mono ml-1">${asset.code}</span>
                </label>
            `;
            container.appendChild(div);
        });
    } catch (e) {
        console.error(e);
        container.innerHTML = '<p class="text-sm text-red-500 p-2">Failed to load assets.</p>';
        currentPulloutAssets = [];
    }
}

function toggleAllEditAssets(checked) {
    document.querySelectorAll('.edit-asset-checkbox').forEach(cb => cb.checked = checked);
}

async function submitEditPullout() {
    const id = document.getElementById('editPulloutId').value;
    const action = document.getElementById('editAction').value;
    const assignToUserId = document.getElementById('editAssignUserId').value || null;
    const repairNotes = document.getElementById('editRepairNotes')?.value || '';
    const newLocation = document.getElementById('editNewLocation')?.value || '';
    const notes = document.getElementById('editNotes')?.value || '';

    // Collect ONLY the checked assets
    const selectedAssetIds = Array.from(
        document.querySelectorAll('#editAssetList .edit-asset-checkbox:checked')
    ).map(cb => parseInt(cb.value, 10));

    console.log('Selected asset IDs being sent:', selectedAssetIds);

    if (!action) {
        showToast('Please select an action.', 'error');
        return;
    }
    if (selectedAssetIds.length === 0) {
        showToast('Please select at least one asset.', 'error');
        return;
    }

    if (action === 'assign') {
        if (!assignToUserId) {
            showToast('Please select a new owner.', 'error');
            return;
        }
        if (!newLocation.trim()) {
            showToast('New location is required.', 'error');
            return;
        }
    }

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('/admin/pullout/' + id + '/resolve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                asset_ids: selectedAssetIds,          // ← this line was missing
                assign_to_user_id: assignToUserId,
                repair_notes: repairNotes,
                new_location: newLocation,
                notes: notes,
            }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            showToast(data.message || 'Failed to update pullout', 'error');
            return;
        }
        showToast(data.message || 'Updated successfully');
        closeEditPullout();
        await refreshPulloutList();
    } catch (e) {
        showToast('Network error: ' + e.message, 'error');
    }
}

function showEditUserList() {
    const list = document.getElementById('editUserList');
    if (list) list.classList.remove('hidden');
}

function filterEditUsers() {
    const q = (document.getElementById('editUserSearch')?.value || '').toLowerCase().trim();
    const list = document.getElementById('editUserList');
    if (!list) return;

    list.classList.remove('hidden');
    list.querySelectorAll('.edit-user-option').forEach((btn) => {
        const label = btn.getAttribute('data-label') || '';
        btn.style.display = (!q || label.includes(q)) ? '' : 'none';
    });
}

function selectEditUser(btn) {
    const id = btn.getAttribute('data-id');
    const name = btn.querySelector('.font-medium')?.textContent || 'User';
    const email = btn.querySelector('.text-xs')?.textContent || '';

    document.getElementById('editAssignUserId').value = id;
    document.getElementById('editUserSearch').value = name + (email ? ' (' + email + ')' : '');
    document.getElementById('editSelectedUserLabel').textContent = name + (email ? ' — ' + email : '');
    document.getElementById('editUserList').classList.add('hidden');
}

// Close list when clicking outside
document.addEventListener('click', function (e) {
    const block = document.getElementById('editAssignBlock');
    const list = document.getElementById('editUserList');
    if (!block || !list || block.classList.contains('hidden')) return;
    if (!block.contains(e.target)) {
        list.classList.add('hidden');
    }
});

function closeEditPullout() {
    const modal = document.getElementById('editPulloutModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function toggleEditActionFields() {
    const action = document.getElementById('editAction').value;
    document.getElementById('editAssignBlock').classList.toggle('hidden', action !== 'assign');
    document.getElementById('editRepairBlock').classList.toggle('hidden', action !== 'repair');
}




        // Open scanner for auto pullout (Record Pullout button)
        function openScannerAuto() {
            scannerAutoMode = true;
            currentScannerSelectId = null;
            
            // Show modal
            const modal = document.getElementById('scannerModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            if (statusEl) statusEl.textContent = 'Requesting camera permission...';
            
            // Stop any existing scanner
            stopScanner();
            
            // Start scanner after a short delay
            setTimeout(() => {
                startHtml5Scanner();
            }, 100);
        }

        // Open scanner for manual asset selection
        function openScanner(selectId) {
            scannerAutoMode = false;
            currentScannerSelectId = selectId;
            
            const modal = document.getElementById('scannerModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            if (statusEl) statusEl.textContent = 'Requesting camera permission...';
            
            stopScanner();
            
            setTimeout(() => {
                startHtml5Scanner();
            }, 100);
        }

        function startHtml5Scanner() {
            const scannerElement = document.getElementById('qrScanner');
            
            if (!scannerElement) {
                console.error('Scanner element not found');
                if (statusEl) statusEl.textContent = 'Error: Scanner element not found';
                return;
            }

            // Clear any existing scanner
            if (html5QrCode) {
                try {
                    html5QrCode.stop().catch(() => {});
                    html5QrCode.clear();
                } catch(e) {}
                html5QrCode = null;
            }

            // Create new scanner instance
            try {
                html5QrCode = new Html5Qrcode("qrScanner");
                
                const config = {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                };

                const constraints = {
                    video: {
                        facingMode: "environment"
                    }
                };

                html5QrCode.start(
                    constraints,
                    config,
                    (decodedText, decodedResult) => {
                        console.log('QR Code detected:', decodedText);
                        handleSuccessfulScan(decodedText);
                    },
                    (errorMessage) => {
                        // Silent fail - scanner continues
                    }
                ).then(() => {
                    if (statusEl) statusEl.textContent = '✓ Camera ready - scanning for QR codes...';
                    isScanning = true;
                }).catch((err) => {
                    console.error('Failed to start scanner:', err);
                    if (statusEl) statusEl.textContent = 'Error starting camera. Trying fallback...';
                    tryFallbackCamera();
                });
            } catch (err) {
                console.error('Exception starting scanner:', err);
                if (statusEl) statusEl.textContent = 'Error initializing scanner.';
                tryFallbackCamera();
            }
        }

        function tryFallbackCamera() {
            const video = document.getElementById('qrVideo');
            
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' } 
                })
                .then(function(stream) {
                    fallbackStream = stream;
                    video.srcObject = stream;
                    video.style.display = 'block';
                    video.play();
                    if (statusEl) statusEl.textContent = '✓ Camera active (fallback mode) - scanning...';
                    
                    const canvas = document.getElementById('qrCanvas');
                    const context = canvas.getContext('2d');
                    
                    if (fallbackScanTimer) clearInterval(fallbackScanTimer);
                    fallbackScanTimer = setInterval(() => {
                        if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);
                            
                            if (typeof jsQR !== 'undefined') {
                                try {
                                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                                    if (code && code.data) {
                                        handleSuccessfulScan(code.data);
                                    }
                                } catch(e) {}
                            }
                        }
                    }, 500);
                })
                .catch(function(err) {
                    console.error('Fallback camera error:', err);
                    if (statusEl) statusEl.textContent = 'Cannot access camera. Please check permissions.';
                    showToast('Camera error: ' + err.message, 'error');
                });
            } else {
                if (statusEl) statusEl.textContent = 'Camera not supported in this browser.';
            }
        }

        function handleSuccessfulScan(decodedText) {
            // Visual feedback - flash the scanner area
            const scannerDiv = document.getElementById('qrScanner');
            if (scannerDiv) {
                scannerDiv.style.transition = '0.1s';
                scannerDiv.style.opacity = '0.5';
                setTimeout(() => {
                    scannerDiv.style.opacity = '1';
                }, 100);
            }
            
            if (scannerAutoMode) {
                handleAutoPullout(decodedText);
                if (statusEl) statusEl.textContent = '✓ Pullout recorded! Ready for next scan...';
            } else if (currentScannerSelectId) {
                handleScannedCode(decodedText, currentScannerSelectId);
                if (statusEl) statusEl.textContent = '✓ Asset selected! Ready for next scan...';
            }
        }

        function handleScannedCode(code, selectId) {
            if (!selectId) return;
            const sel = document.getElementById(selectId);
            if (!sel) return;
            
            let opt = sel.querySelector('option[data-code="' + code + '"]');
            if (!opt) {
                const opts = Array.from(sel.options).filter(o => 
                    (o.dataset.code && (o.dataset.code === code || o.dataset.code.includes(code))) || 
                    (o.text && o.text.includes(code))
                );
                opt = opts.length ? opts[0] : null;
            }
            
            if (opt) {
                if (sel.multiple) {
                    opt.selected = true;
                } else {
                    sel.value = opt.value;
                }
                sel.dispatchEvent(new Event('change'));
                sel.classList.add('border-green-500', 'bg-green-50');
                setTimeout(() => {
                    sel.classList.remove('border-green-500', 'bg-green-50');
                }, 500);
                showToast('Asset selected: ' + opt.text);
            } else {
                showToast('Asset not found: ' + code, 'error');
            }
        }

        function filterPulloutAssets(query) {
            const select = document.getElementById('pullout_asset_select');
            if (!select) return;

            const term = (query || '').trim().toLowerCase();
            Array.from(select.options).forEach((option) => {
                const haystack = `${option.textContent || ''} ${option.dataset.code || ''}`.toLowerCase();
                option.hidden = term ? !haystack.includes(term) : false;
            });
        }

        function selectAllVisiblePulloutAssets() {
            const select = document.getElementById('pullout_asset_select');
            if (!select) return;

            Array.from(select.options).forEach((option) => {
                if (!option.hidden) {
                    option.selected = true;
                }
            });

            select.dispatchEvent(new Event('change'));
            showToast('Visible assets selected.');
        }

        async function handleAutoPullout(code) {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            let assetId = null;
            let assetStatus = null;
            
            let opt = document.querySelector('option[data-code="' + code + '"]');
            if (opt) {
                assetId = opt.value;
                assetStatus = opt.getAttribute('data-status');
            } else {
                try {
                    const res = await fetch('/admin/assets/find-by-code?code=' + encodeURIComponent(code), 
                        { headers: { 'Accept': 'application/json' }}
                    );
                    if (res.ok) {
                        const data = await res.json();
                        assetId = data.id || null;
                        assetStatus = data.status || null;
                    }
                } catch (e) {
                    console.warn('Server lookup failed:', e);
                }
            }

            if (!assetId) {
                showToast('Asset not found: ' + code, 'error');
                return;
            }

            // Check if asset status is Active or Acquired
            if (assetStatus && assetStatus !== 'Active' && assetStatus !== 'Acquired') {
                showToast('Only assets with "Active" or "Acquired" status can be pulled out. Current status: ' + assetStatus, 'error');
                return;
            }

            const payload = {
                asset_ids: [assetId],
                pullout_date: new Date().toISOString().slice(0,10),
                reason: 'Scanned Pullout',
                pulled_by: adminName,
                notes: 'Recorded by admin via QR scan.',
                status: 'approved'
            };

            try {
                const res = await fetch('/admin/pullout/record', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (res.ok) {
                    showToast('✓ Pullout recorded successfully!');
                    await refreshPulloutList();
                } else {
                    showToast('Failed: ' + (json.message || res.statusText), 'error');
                }
            } catch (e) {
                showToast('Network error: ' + e.message, 'error');
            }
        }

        async function refreshPulloutList() {
            try {
                const response = await fetch(window.location.href);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newRecordsList = doc.querySelector('#pulloutRecordsList');
                const oldRecordsList = document.querySelector('#pulloutRecordsList');
                const newEmptyState = doc.querySelector('#emptyState');
                const oldEmptyState = document.querySelector('#emptyState');
                const newTotalCount = doc.querySelector('#totalPulledOutCount');
                const oldTotalCount = document.querySelector('#totalPulledOutCount');
                
                if (newRecordsList && oldRecordsList) {
                    oldRecordsList.innerHTML = newRecordsList.innerHTML;
                } else if (newEmptyState && oldEmptyState) {
                    oldEmptyState.innerHTML = newEmptyState.innerHTML;
                } else if (newRecordsList && oldEmptyState) {
                    oldEmptyState.outerHTML = newRecordsList.outerHTML;
                } else if (newEmptyState && oldRecordsList) {
                    oldRecordsList.outerHTML = newEmptyState.outerHTML;
                }
                
                if (newTotalCount && oldTotalCount) {
                    oldTotalCount.textContent = newTotalCount.textContent;
                }
                
                showToast('List refreshed');
            } catch (error) {
                console.error('Failed to refresh:', error);
            }
        }

        function stopScanner() {
            if (fallbackScanTimer) {
                clearInterval(fallbackScanTimer);
                fallbackScanTimer = null;
            }
            
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                }).catch(() => {
                    html5QrCode = null;
                });
            }
            
            if (fallbackStream) {
                fallbackStream.getTracks().forEach(track => track.stop());
                fallbackStream = null;
            }
            
            const video = document.getElementById('qrVideo');
            if (video && video.srcObject) {
                video.srcObject = null;
            }
            
            isScanning = false;
        }

        function manualCloseScanner() {
            stopScanner();
            const modal = document.getElementById('scannerModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            currentScannerSelectId = null;
            scannerAutoMode = false;
            if (statusEl) statusEl.textContent = 'Camera stopped.';
        }

        function showToast(message, type = 'success') {
            const existingToast = document.querySelector('.toast-notification');
            if (existingToast) existingToast.remove();
            
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.backgroundColor = type === 'error' ? '#A23B32' : '#2F7A4D';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function openNewPulloutModal() {
            document.getElementById('pulloutModal').classList.remove('hidden');
            document.getElementById('pulloutModal').classList.add('flex');
        }
        
        function closePulloutModal() {
            document.getElementById('pulloutModal').classList.add('hidden');
            document.getElementById('pulloutModal').classList.remove('flex');
        }
        
async function viewPulloutDetails(id) {
    // Show loading state
    document.getElementById('viewPulloutIdLabel').textContent = id;
    document.getElementById('viewPulloutDate').textContent = 'Loading...';
    document.getElementById('viewPulloutStatus').textContent = '—';
    document.getElementById('viewPulloutReason').textContent = '—';
    document.getElementById('viewPulloutBy').textContent = '—';
    document.getElementById('viewPulloutDestination').textContent = '—';
    document.getElementById('viewPulloutNotes').textContent = '—';
    document.getElementById('viewAssetList').innerHTML = '<p class="p-4 text-sm text-gray-500">Loading assets...</p>';

    const modal = document.getElementById('viewPulloutModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        // Fetch pullout basic info + assets
        const res = await fetch(`/admin/pullout/${id}/details`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to load details', 'error');
            closeViewPullout();
            return;
        }

        const p = data.pullout;

        document.getElementById('viewPulloutDate').textContent = p.pullout_date || '—';
        document.getElementById('viewPulloutStatus').innerHTML = `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                style="${p.status === 'pending' ? 'background:var(--bronze-tint); color:var(--bronze-dark);' :
                  p.status === 'approved' ? 'background:var(--forest-tint); color:var(--forest-dark);' :
                  'background:var(--brick-tint); color:var(--brick-dark);'}">
                ${p.status ? p.status.charAt(0).toUpperCase() + p.status.slice(1) : '—'}
            </span>`;
        document.getElementById('viewPulloutReason').textContent = p.reason || p.Description || '—';
        document.getElementById('viewPulloutBy').textContent = p.pulled_by || p.Approve_by || '—';
        document.getElementById('viewPulloutDestination').textContent = p.destination || '—';
        document.getElementById('viewPulloutNotes').textContent = p.notes || '—';

        // Assets
        const list = document.getElementById('viewAssetList');
        if (!data.assets || data.assets.length === 0) {
            list.innerHTML = '<p class="p-4 text-sm text-gray-500">No assets linked.</p>';
        } else {
            list.innerHTML = data.assets.map(a => `
                <div class="flex items-center justify-between p-3 hover:bg-gray-50">
                    <div>
                        <p class="font-medium text-gray-900">${a.name || 'Asset'}</p>
                        <p class="text-xs text-gray-500 font-mono">${a.code || '—'}</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                        ${a.status || '—'}
                    </span>
                </div>
            `).join('');
        }
    } catch (e) {
        console.error(e);
        showToast('Network error while loading details', 'error');
        closeViewPullout();
    }
}

function closeViewPullout() {
    const modal = document.getElementById('viewPulloutModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
        
        async function approvePullout(id) {
            if (confirm('Approve this pullout request?')) {
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const response = await fetch('/admin/pullout/approve/' + id, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        }
                    });
                    if (response.ok) {
                        showToast('Pullout request approved!');
                        await refreshPulloutList();
                    } else {
                        showToast('Failed to approve', 'error');
                    }
                } catch (error) {
                    showToast('Network error: ' + error.message, 'error');
                }
            }
        }
        
        function deletePulloutRecord(id) {
            currentDeleteId = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
            currentDeleteId = null;
        }
        
async function openDisposeFromPullout(id) {
    document.getElementById('disposePulloutId').value = id;
    document.getElementById('disposePulloutIdLabel').textContent = id;
    document.getElementById('disposeAssetList').innerHTML = '<p class="p-4 text-sm text-gray-500">Loading assets...</p>';

    const modal = document.getElementById('disposeFromPulloutModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        const res = await fetch(`/admin/pullout/${id}/details`);
        const data = await res.json();

        if (!data.success || !data.assets || data.assets.length === 0) {
            document.getElementById('disposeAssetList').innerHTML =
                '<p class="p-4 text-sm text-gray-500">No assets linked to this pullout.</p>';
            return;
        }

        document.getElementById('disposeAssetList').innerHTML = data.assets.map(a => `
            <label class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" name="asset_ids[]" value="${a.id}" class="dispose-asset-cb rounded border-gray-300 text-red-600" checked>
                <div class="flex-1">
                    <p class="font-medium text-gray-900">${a.name || 'Asset'}</p>
                    <p class="text-xs text-gray-500 font-mono">${a.code || '—'}</p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">${a.status || '—'}</span>
            </label>
        `).join('');
    } catch (e) {
        console.error(e);
        showToast('Failed to load assets', 'error');
        closeDisposeFromPullout();
    }
}

function closeDisposeFromPullout() {
    const modal = document.getElementById('disposeFromPulloutModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function selectAllDisposeAssets(checked) {
    document.querySelectorAll('.dispose-asset-cb').forEach(cb => cb.checked = checked);
}

document.getElementById('disposeFromPulloutForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const pulloutId = document.getElementById('disposePulloutId').value;
    const selected = Array.from(document.querySelectorAll('.dispose-asset-cb:checked')).map(cb => cb.value);

    if (selected.length === 0) {
        showToast('Please select at least one asset to dispose.', 'error');
        return;
    }

    const formData = new FormData(this);
    const payload = {
        asset_ids: selected,
        disposal_date: formData.get('disposal_date'),
        reason: formData.get('reason'),
        notes: formData.get('notes') || '',
        disposed_by: @json(Auth::user()->email ?? 'Admin'),
    };

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch(`/admin/pullout/${pulloutId}/dispose`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (!res.ok) {
            showToast(data.message || data.error || 'Failed to dispose', 'error');
            return;
        }

        showToast(data.message || 'Assets disposed successfully!');
        closeDisposeFromPullout();
        await refreshPulloutList();
    } catch (err) {
        showToast('Network error: ' + err.message, 'error');
    }
});

        // Form submission
        document.getElementById('pulloutForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            data.asset_ids = formData.getAll('asset_ids[]').filter(Boolean);
            delete data['asset_ids[]'];
            delete data.asset_id;
            data.status = 'approved';

            if (!data.asset_ids.length) {
                showToast('Please select at least one asset.', 'error');
                return;
            }
            
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch('/admin/pullout/record', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                if (response.ok) {
                    showToast('Pullout recorded successfully!');
                    closePulloutModal();
                    await refreshPulloutList();
                    this.reset();
                } else {
                    const error = await response.json();
                    showToast('Error: ' + (error.message || 'Failed to record'), 'error');
                }
            } catch (error) {
                showToast('Network error: ' + error.message, 'error');
            }
        });

        // Prevent ESC key from closing scanner modal when camera is active
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const scannerModal = document.getElementById('scannerModal');
                    if (scannerModal && !scannerModal.classList.contains('hidden')) {
                        e.preventDefault();
                        if (confirm('Camera is active. Close camera and scanner?')) {
                            manualCloseScanner();
                        }
                    }
                }
            });
            
            const scannerModal = document.getElementById('scannerModal');
            if (scannerModal) {
                scannerModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (confirm('Camera is active. Close camera and scanner?')) {
                            manualCloseScanner();
                        }
                    }
                });
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopScanner();
        });
    </script>
</body>
</html>