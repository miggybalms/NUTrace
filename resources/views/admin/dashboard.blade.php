<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard - Asset Management</title>
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
            --paper:#FAF7F0; --paper-2:#F2ECDD;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
            --line:#E6DFCD;
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
        .eyebrow{ font-family:'Inter',sans-serif; font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--gold-600); }
        .field-label{ font-family:'Inter',sans-serif; font-size:.68rem; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-400); }

        .sidebar-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background-color: #374151;
            padding-left: 1.5rem;
        }

        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }

        .badge-admin{ background:var(--gold-100); color:var(--navy-900); padding:.15rem .55rem; border-radius:999px; font-weight:600; font-size:.68rem; letter-spacing:.04em; text-transform:uppercase; }

        .avatar-badge{ background:var(--navy-950); color:var(--gold-500); border:1px solid var(--gold-500); }

        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -16px rgba(10, 24, 48, 0.28);
        }

        .metric-card{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:1.5rem; }
        .metric-icon{ width:2.5rem; height:2.5rem; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

        .btn-gold{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.6rem 1.15rem; background:var(--gold-500); color:var(--navy-950); display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-gold:hover{ filter:brightness(1.06); }
        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; }
        .btn-ghost:hover{ background:var(--paper-2); }
        .btn-solid{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; color:#fff; display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-solid:hover{ filter:brightness(1.08); }

        .quicklink-tile{ background:#fff; border:1px solid var(--line); border-radius:12px; padding:1.4rem 1.1rem; text-align:center; border-top-width:3px; transition:box-shadow .15s ease, transform .15s ease; }
        .quicklink-tile:hover{ box-shadow:0 12px 26px -16px rgba(10,24,48,.3); transform:translateY(-2px); }
        .quicklink-icon{ width:3rem; height:3rem; border-radius:11px; display:flex; align-items:center; justify-content:center; margin:0 auto .85rem; color:#fff; transition:transform .2s ease; }
        .quicklink-tile:hover .quicklink-icon{ transform:scale(1.08); }

        .card-registry{ background:#fff; border:1px solid var(--line); border-radius:14px; }
        .section-title{ font-family:'Fraunces',serif; font-weight:600; font-size:1.1rem; color:var(--navy-900); }

        .status-pill{ display:inline-flex; align-items:center; padding:.15rem .6rem; border-radius:999px; font-size:.7rem; font-weight:600; }

        /* Pulse animation for notification bell */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')
        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
            <!-- Header -->

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
            <!-- Header -->
            <div class="topbar sticky top-0 z-10">
            <div class="px-4 sm:px-8 py-5">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <!-- Hamburger, mobile only -->
                        <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-600);">
                            <i class="ri-menu-line text-2xl"></i>
                        </button>
                        <div>
                            <h2 class="font-display text-xl sm:text-2xl font-semibold" style="color:var(--navy-900);">Dashboard</h2>
                            <div class="flex items-center mt-1.5 gap-2">
                                <span class="badge-admin">Admin</span>
                                <span style="color:var(--ink-400);">•</span>
                                <p class="text-sm hidden sm:block" style="color:var(--ink-600);">Overview of asset management system</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                <!-- bell + avatar block stays exactly the same -->
                            <!-- Maintenance Alerts Bell -->
                            <div class="relative">
                                <button id="maintenanceAlertsBell" class="relative cursor-pointer transition" style="color:var(--ink-600);" title="Maintenance & Lifespan Alerts">
                                    <i class="ri-notification-3-line text-xl"></i>
                                    <span id="alertBadge" class="absolute -top-1 -right-1 w-5 h-5 text-white text-xs font-bold rounded-full flex items-center justify-center hidden" style="background:var(--brick);">0</span>
                                </button>

                                <!-- Alerts Dropdown -->
                                <div id="alertsDropdown" class="hidden absolute right-0 mt-2 w-96 rounded-lg shadow-xl z-50 max-h-screen overflow-y-auto" style="background:#fff; border:1px solid var(--line);">
                                    <!-- Lifespan Expiration Alerts Tab -->
                                    <div class="p-4" style="border-bottom:1px solid var(--line); background:var(--brick-tint);">
                                        <h3 class="font-semibold" style="color:var(--brick-dark);">
                                            <i class="ri-time-line mr-2"></i>
                                            Assets Requiring Evaluation
                                        </h3>
                                        <p class="text-xs mt-1" style="color:var(--brick-dark); opacity:.85;">Assets that have reached their lifespan and require evaluation</p>
                                    </div>
                                    <div id="lifespanAlertsList" class="divide-y max-h-56 overflow-y-auto" style="border-color:var(--line);">
                                        <div class="p-4 text-center text-sm" style="color:var(--ink-400);">
                                            <p><i class="ri-check-line text-lg" style="color:var(--forest);"></i></p>
                                            <p>No lifespan expiration alerts</p>
                                        </div>
                                    </div>

                                    <!-- Maintenance Alerts Tab -->
                                    <div class="p-4" style="border-bottom:1px solid var(--line); background:var(--bronze-tint);">
                                        <h3 class="font-semibold" style="color:var(--bronze-dark);">
                                            <i class="ri-tools-line mr-2"></i>
                                            Maintenance Due
                                        </h3>
                                        <p class="text-xs mt-1" style="color:var(--bronze-dark); opacity:.85;">Assets requiring preventive maintenance</p>
                                    </div>
                                    <div id="maintenanceAlertsList" class="divide-y max-h-56 overflow-y-auto" style="border-color:var(--line);">
                                        <div class="p-4 text-center text-sm" style="color:var(--ink-400);">
                                            <p><i class="ri-check-line text-lg" style="color:var(--forest);"></i></p>
                                            <p>No maintenance alerts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 cursor-pointer rounded-lg px-2 py-1" style="transition:background .15s;" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                                <div class="avatar-badge w-8 h-8 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-semibold">AD</span>
                                </div>
                                <i class="ri-arrow-down-s-line" style="color:var(--ink-400);"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="p-8">
                <!-- Quick Summary -->
                <div class="mb-8 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h3 class="section-title">Quick summary of key metrics</h3>
                        <p class="text-sm mt-1" style="color:var(--ink-600);">Real-time overview of your asset inventory</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button id="openScannerBtn" class="btn-gold flex items-center space-x-2">
                            <i class="ri-scan-line"></i>
                            <span>Scanner</span>
                        </button>
                    </div>
                </div>

                <!-- Metrics Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                    <!-- Acquired this month -->
                    <div class="metric-card card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Acquired this month</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ number_format($acquiredThisMonth ?? 1847) }}</p>
                                @php
                                    $pct = $acquiredChangePercent ?? 0;
                                @endphp
                                <p class="text-xs mt-2 flex items-center"
                                   style="color: {{ $pct > 0 ? 'var(--forest-dark)' : ($pct < 0 ? 'var(--brick-dark)' : 'var(--ink-400)') }};">
                                    @if($pct > 0)
                                        <i class="ri-arrow-up-line mr-0.5"></i>
                                        {{ $pct }}% from last month
                                    @elseif($pct < 0)
                                        <i class="ri-arrow-down-line mr-0.5"></i>
                                        {{ abs($pct) }}% from last month
                                    @else
                                        <i class="ri-arrow-right-line mr-0.5"></i>
                                        0% from last month
                                    @endif
                                </p>
                            </div>
                            <div class="metric-icon" style="background:var(--steel-tint);">
                                <i class="ri-calendar-line text-xl" style="color:var(--steel);"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Active Assets -->
                    <div class="metric-card card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Active Assets</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ number_format($activeAssets ?? 2854) }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Total active inventory</p>
                            </div>
                            <div class="metric-icon" style="background:var(--forest-tint);">
                                <i class="ri-checkbox-circle-line text-xl" style="color:var(--forest);"></i>
                            </div>
                        </div>
                    </div>

                    <!-- For Repair -->
                    <div class="metric-card card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">For Repair</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ number_format($forRepairAssets ?? 28) }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Needs maintenance</p>
                            </div>
                            <div class="metric-icon" style="background:var(--bronze-tint);">
                                <i class="ri-tools-line text-xl" style="color:var(--bronze);"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Requests -->
                    <div class="metric-card card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Pending Requests</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ number_format($pendingRequests ?? 15) }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Awaiting approval</p>
                            </div>
                            <div class="metric-icon" style="background:var(--gold-100);">
                                <i class="ri-time-line text-xl" style="color:var(--gold-600);"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="mb-8">
                    <h3 class="section-title mb-4">Quick Links</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="/admin/requests?tab=pending" class="quicklink-tile block group" style="border-top-color:var(--gold-500);">
                            <div class="quicklink-icon" style="background:var(--gold-500); color:var(--navy-950);">
                                <i class="ri-time-line text-2xl"></i>
                            </div>
                            <p class="font-display font-semibold" style="color:var(--navy-900);">Pending Requests</p>
                            <p class="text-xs mt-1" style="color:var(--ink-400);">View pending approvals</p>
                        </a>
                        <a href="/admin/assets/registry" class="quicklink-tile block group" style="border-top-color:var(--forest);">
                            <div class="quicklink-icon" style="background:var(--forest);">
                                <i class="ri-database-line text-2xl"></i>
                            </div>
                            <p class="font-display font-semibold" style="color:var(--navy-900);">Asset Registry</p>
                            <p class="text-xs mt-1" style="color:var(--ink-400);">Register new assets</p>
                        </a>
                        <a href="/admin/disposal" class="quicklink-tile block group" style="border-top-color:var(--brick);">
                            <div class="quicklink-icon" style="background:var(--brick);">
                                <i class="ri-delete-bin-line text-2xl"></i>
                            </div>
                            <p class="font-display font-semibold" style="color:var(--navy-900);">Record Disposal</p>
                            <p class="text-xs mt-1" style="color:var(--ink-400);">Log disposed assets</p>
                        </a>
                        <a href="/admin/pullout" class="quicklink-tile block group" style="border-top-color:var(--steel);">
                            <div class="quicklink-icon" style="background:var(--steel);">
                                <i class="ri-logout-box-r-line text-2xl"></i>
                            </div>
                            <p class="font-display font-semibold" style="color:var(--navy-900);">Record Pullout</p>
                            <p class="text-xs mt-1" style="color:var(--ink-400);">Log pulled out assets</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card-registry">
                    <div class="p-6" style="border-bottom:1px solid var(--line);">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="section-title">Recent Activity</h3>
                                <p class="text-sm mt-1" style="color:var(--ink-600);">Latest actions and updates</p>
                            </div>
                            <a href="/admin/audit-logs" class="text-sm font-medium flex items-center" style="color:var(--gold-600);">
                                View All
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        @if(isset($recentActivities) && count($recentActivities) > 0)
                            <div class="space-y-4">
                                @foreach($recentActivities as $activity)
                                <div class="flex items-center justify-between p-3 rounded-lg transition" style="transition:background .15s;" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:var(--steel-tint);">
                                            <i class="ri-file-copy-line" style="color:var(--steel);"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium" style="color:var(--navy-900);">{{ $activity->type }}</p>
                                            <p class="text-sm" style="color:var(--ink-600);">{{ $activity->description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs" style="color:var(--ink-400);">{{ $activity->time }}</span>
                                        @if(isset($activity->status))
                                            <p class="text-xs mt-1">
                                                <span class="status-pill"
                                                    style="{{ $activity->status == 'pending' ? 'background:var(--bronze-tint); color:var(--bronze-dark);' : ($activity->status == 'approved' ? 'background:var(--forest-tint); color:var(--forest-dark);' : '') }}">
                                                    {{ ucfirst($activity->status) }}
                                                </span>
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <i class="ri-inbox-line text-5xl mb-3 block" style="color:var(--line);"></i>
                                <p style="color:var(--ink-400);">No recent activities</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center text-sm mt-8 pt-6" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Scanner Modal -->
    <div id="scannerModal" class="hidden fixed inset-0 z-50 items-center justify-center" style="background:rgba(10,24,48,.55);">
        <div class="rounded-xl shadow-2xl max-w-lg w-full mx-4" style="background:#fff; overflow:hidden;">
            <div class="flex justify-between items-center p-6" style="background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative;">
                <h3 class="font-display text-lg font-semibold text-white">Asset Scanner</h3>
                <button onclick="closeScannerModal()" class="text-white/60 hover:text-white"><i class="ri-close-line text-2xl"></i></button>
            </div>
            <div class="space-y-4 p-6">
                <p class="text-sm" style="color:var(--ink-600);">Scan or enter an asset code to view details.</p>
                <div class="flex space-x-2">
                    <button id="startCameraBtn" class="btn-solid" style="background:var(--steel);">Start Camera</button>
                    <button id="stopCameraBtn" class="btn-solid" style="background:var(--brick); display:none">Turn Off Camera</button>
                </div>

                <div id="qrScannerWrap" class="hidden">
                    <div id="qrScanner" style="width:100%;max-width:420px;margin-top:8px">
                        <!-- html5-qrcode will render here or fallback will use video -->
                        <video id="qrVideo" autoplay muted playsinline style="width:100%;height:auto;display:none;border-radius:6px;border:1px solid var(--line);"></video>
                        <canvas id="qrCanvas" style="display:none;"></canvas>
                    </div>
                    <div class="mt-2 flex items-center space-x-2">
                        <button id="stopCameraBtn" class="px-3 py-1 rounded text-white" style="background:var(--brick);">Stop</button>
                        <span class="text-sm" style="color:var(--ink-400);">Camera will auto-detect QR codes.</span>
                    </div>
                </div>

                <div id="scannerResult" class="hidden p-4 rounded-lg" style="background:var(--paper-2); border:1px solid var(--line);">
                    <div id="assetImageWrapper" class="mb-4 flex justify-center">
                        <img id="assetImage" src="" alt="Asset" class="h-32 w-32 object-cover rounded hidden" style="border:1px solid var(--line);">
                    </div>
                    <h4 class="font-display font-semibold" style="color:var(--navy-900);" id="resName">-</h4>
                    <p class="text-xs font-mono" style="color:var(--ink-400);" id="resCode">-</p>
                    <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
                        <div>
                            <p class="field-label">Status</p>
                            <p id="resStatus" class="text-sm font-medium mt-0.5" style="color:var(--navy-900);">-</p>
                        </div>
                        <div>
                            <p class="field-label">Department</p>
                            <p id="resDept" class="text-sm font-medium mt-0.5" style="color:var(--navy-900);">-</p>
                        </div>
                        <div>
                            <p class="field-label">Owner</p>
                            <p id="resOwner" class="text-sm font-medium mt-0.5" style="color:var(--navy-900);">-</p>
                        </div>
                        <div>
                            <p class="field-label">Location</p>
                            <p id="resLocation" class="text-sm font-medium mt-0.5" style="color:var(--navy-900);">-</p>
                        </div>
                    </div>
                </div>
                <p id="scannerMsg" class="text-sm hidden" style="color:var(--brick-dark);"></p>
            </div>
            <div class="flex justify-end p-6 pt-0">
                <button onclick="closeScannerModal()" class="btn-ghost">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.7/minified/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        function openScannerModal() {
            document.getElementById('scannerModal').classList.remove('hidden');
            document.getElementById('scannerModal').classList.add('flex');
            // auto-start camera scanner like user request page
            try {
                document.getElementById('scannerMsg').classList.add('hidden');
                startCamera();
            } catch (e) {
                // ignore if scanner not available
            }
        }
        function closeScannerModal() {
            // Ensure camera is stopped when closing
            try { stopCamera(); } catch (e) { /* ignore */ }
            document.getElementById('scannerModal').classList.add('hidden');
            document.getElementById('scannerModal').classList.remove('flex');
            document.getElementById('scannerResult').classList.add('hidden');
            document.getElementById('scannerMsg').classList.add('hidden');
            // reset start/stop button visibility
            try {
                startCameraBtn.style.display = 'inline-block';
                stopCameraBtn.style.display = 'none';
            } catch (e) {}
        }

        document.getElementById('openScannerBtn').addEventListener('click', openScannerModal);
        // lookup helper used by scanner on successful decode
        function lookupAsset(code) {
            if (!code) {
                document.getElementById('scannerMsg').textContent = 'Invalid code scanned.';
                document.getElementById('scannerMsg').classList.remove('hidden');
                return;
            }
            document.getElementById('scannerMsg').classList.add('hidden');
            fetch(`/admin/assets/scan?code=${encodeURIComponent(code)}`)
                .then(res => res.json())
                .then(json => {
                    if (!json.success) {
                        document.getElementById('scannerMsg').textContent = json.message || 'Asset not found';
                        document.getElementById('scannerMsg').classList.remove('hidden');
                        document.getElementById('scannerResult').classList.add('hidden');
                        return;
                    }
                    const d = json.data;
                    document.getElementById('resName').textContent = d.name || '-';
                    document.getElementById('resCode').textContent = d.asset_code || '-';
                    document.getElementById('resStatus').textContent = d.status || '-';
                    document.getElementById('resDept').textContent = d.department || '-';
                    document.getElementById('resOwner').textContent = d.owner || '-';
                    document.getElementById('resLocation').textContent = d.location || '-';
                    
                    // Display image if available
                    const assetImage = document.getElementById('assetImage');
                    if (d.image_url) {
                        assetImage.src = d.image_url;
                        assetImage.classList.remove('hidden');
                    } else {
                        assetImage.classList.add('hidden');
                    }
                    
                    document.getElementById('scannerResult').classList.remove('hidden');
                })
                .catch(err => {
                    document.getElementById('scannerMsg').textContent = 'Lookup failed';
                    document.getElementById('scannerMsg').classList.remove('hidden');
                });
        }

        // --- html5-qrcode integration ---
        let html5QrCode = null;
        const qrScannerWrap = document.getElementById('qrScannerWrap');
        const qrScannerEl = document.getElementById('qrScanner');
        const startCameraBtn = document.getElementById('startCameraBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');

        function onScanSuccess(decodedText, decodedResult) {
            // stop camera immediately and perform lookup
            stopCamera();
            lookupAsset(decodedText);
        }

        function onScanFailure(error) {
            // ignore occasional scan failures
            // console.debug('scan failure', error);
        }

        let fallbackStream = null;
        let fallbackScanTimer = null;

        function startCamera() {
            qrScannerWrap.classList.remove('hidden');

            // Preferred: use Html5Qrcode if available
            if (window.Html5Qrcode) {
                try {
                    html5QrCode = new Html5Qrcode('qrScanner');
                    Html5Qrcode.getCameras().then(cameras => {
                        const cameraId = (cameras && cameras.length) ? cameras[0].id : null;
                        html5QrCode.start(
                            { deviceId: cameraId },
                            { fps: 10, qrbox: 250 },
                            onScanSuccess,
                            onScanFailure
                        ).catch(err => {
                            // fallback to getUserMedia approach
                            startCameraFallback();
                        });
                    }).catch(err => {
                        startCameraFallback();
                    });
                    return;
                } catch (e) {
                    // fallthrough to fallback
                }
            }

            // Fallback: use navigator.mediaDevices + jsQR
            startCameraFallback();
        }

        function startCameraFallback() {
            const video = document.getElementById('qrVideo');
            const canvas = document.getElementById('qrCanvas');
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                document.getElementById('scannerMsg').textContent = 'Camera not available in this browser.';
                document.getElementById('scannerMsg').classList.remove('hidden');
                return;
            }
            document.getElementById('scannerMsg').classList.add('hidden');
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(stream => {
                fallbackStream = stream;
                video.srcObject = stream;
                video.style.display = 'block';
                canvas.style.display = 'none';
                video.play();

                const ctx = canvas.getContext('2d');
                fallbackScanTimer = setInterval(() => {
                    if (video.readyState !== video.HAVE_ENOUGH_DATA) return;
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    try {
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height);
                        if (code && code.data) {
                            onScanSuccess(code.data, null);
                        }
                    } catch (e) {
                        // ignore cross-origin or read errors
                    }
                }, 300);
            }).catch(err => {
                document.getElementById('scannerMsg').textContent = 'Camera permission denied or no camera found.';
                document.getElementById('scannerMsg').classList.remove('hidden');
            });
        }

        function stopCamera() {
            // stop html5QrCode if present
            if (html5QrCode && html5QrCode.stop) {
                try {
                    html5QrCode.stop().then(() => {
                        try { html5QrCode.clear(); } catch(e) {}
                        html5QrCode = null;
                        qrScannerWrap.classList.add('hidden');
                    }).catch(()=>{
                        html5QrCode = null;
                        qrScannerWrap.classList.add('hidden');
                    });
                } catch (e) {
                    html5QrCode = null;
                }
            }

            // stop fallback stream if present
            if (fallbackScanTimer) {
                clearInterval(fallbackScanTimer);
                fallbackScanTimer = null;
            }
            if (fallbackStream) {
                try { fallbackStream.getTracks().forEach(t => t.stop()); } catch (e) {}
                fallbackStream = null;
            }
            const video = document.getElementById('qrVideo');
            if (video) {
                try { video.pause(); } catch(e) {}
                try { video.srcObject = null; } catch(e) {}
                video.style.display = 'none';
            }
            qrScannerWrap.classList.add('hidden');
        }

        startCameraBtn.addEventListener('click', function () {
            document.getElementById('scannerMsg').classList.add('hidden');
            // start camera and toggle buttons
            startCamera();
            startCameraBtn.style.display = 'none';
            stopCameraBtn.style.display = 'inline-block';
        });
        stopCameraBtn.addEventListener('click', function () {
            stopCamera();
            stopCameraBtn.style.display = 'none';
            startCameraBtn.style.display = 'inline-block';
        });

        // Maintenance & Lifespan Alerts System
        const maintenanceAlertsBell = document.getElementById('maintenanceAlertsBell');
        const alertsDropdown = document.getElementById('alertsDropdown');
        const alertBadge = document.getElementById('alertBadge');
        const lifespanAlertsList = document.getElementById('lifespanAlertsList');
        const maintenanceAlertsList = document.getElementById('maintenanceAlertsList');

        // Fetch lifespan expiration alerts
        function fetchLifespanAlerts() {
            fetch('/admin/api/lifespan-alerts')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.alerts.length > 0) {
                        lifespanAlertsList.innerHTML = data.alerts.map(alert => `
                            <div class="p-3 hover:bg-red-50 transition border-l-4 border-red-500">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">${alert.Asset_name}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Code: ${alert.Asset_code}</p>
                                        <p class="text-xs text-gray-500">Assigned to: ${alert.assigned_to || 'Unassigned'}</p>
                                        <p class="text-xs text-red-600 font-medium mt-1">
                                            <i class="ri-error-warning-line"></i> Expired: ${new Date(alert.expiration_date).toLocaleDateString()}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">Repair history: ${alert.repair_counts || 0}</p>
                                    </div>
                                    <a href="/admin/assets/${alert.id}" class="text-xs text-blue-600 hover:text-blue-700 font-medium ml-2">
                                        Evaluate <i class="ri-arrow-right-line"></i>
                                    </a>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        lifespanAlertsList.innerHTML = `
                            <div class="p-4 text-center text-gray-500 text-sm">
                                <p><i class="ri-check-line text-green-500 text-lg"></i></p>
                                <p>No lifespan expiration alerts</p>
                            </div>
                        `;
                    }
                })
                .catch(error => console.error('Error fetching lifespan alerts:', error));
        }

        // Fetch maintenance alerts
        function fetchMaintenanceAlerts() {
            fetch('/admin/api/maintenance-alerts')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.alerts.length > 0) {
                        maintenanceAlertsList.innerHTML = data.alerts.map(alert => `
                            <div class="p-3 hover:bg-amber-50 transition border-l-4 border-amber-500">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">${alert.Asset_name}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Code: ${alert.Asset_code}</p>
                                        <p class="text-xs text-gray-500">Assigned to: ${alert.assigned_to || 'Unassigned'}</p>
                                        <p class="text-xs text-amber-600 font-medium mt-1">
                                            <i class="ri-alert-line"></i> Due: ${new Date(alert.next_maintenance_date).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <a href="/admin/assets/${alert.id}" class="text-xs text-blue-600 hover:text-blue-700 font-medium ml-2">
                                        Service <i class="ri-arrow-right-line"></i>
                                    </a>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        maintenanceAlertsList.innerHTML = `
                            <div class="p-4 text-center text-gray-500 text-sm">
                                <p><i class="ri-check-line text-green-500 text-lg"></i></p>
                                <p>No maintenance alerts</p>
                            </div>
                        `;
                    }
                    
                    updateAlertBadge();
                })
                .catch(error => console.error('Error fetching maintenance alerts:', error));
        }

        // Update alert badge with total count
        function updateAlertBadge() {
            fetch('/admin/api/lifespan-alerts').then(r => r.json()).then(d1 => {
                fetch('/admin/api/maintenance-alerts').then(r => r.json()).then(d2 => {
                    const totalCount = (d1.count || 0) + (d2.count || 0);
                    if (totalCount > 0) {
                        alertBadge.textContent = totalCount;
                        alertBadge.classList.remove('hidden');
                        maintenanceAlertsBell.classList.add('animate-pulse');
                        playNotificationSound();
                    } else {
                        alertBadge.classList.add('hidden');
                        maintenanceAlertsBell.classList.remove('animate-pulse');
                    }
                });
            });
        }

        // Play notification sound
        function playNotificationSound() {
            // Create a simple beep sound using Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }

        // Toggle dropdown
        maintenanceAlertsBell.addEventListener('click', function (e) {
            e.stopPropagation();
            alertsDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function () {
            alertsDropdown.classList.add('hidden');
        });

        // Auto-transition assets that need evaluation (on page load)
        function autoTransitionAssets() {
            fetch('/admin/api/assets/check-and-transition', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.transitioned_count > 0) {
                    console.log(`Auto-transitioned ${data.transitioned_count} asset(s) to "For Checking" status`);
                }
            })
            .catch(error => console.error('Auto-transition error:', error));
        }

        // Trigger auto-transition and fetch alerts on page load
        autoTransitionAssets();
        fetchLifespanAlerts();
        fetchMaintenanceAlerts();

        // Refresh alerts and auto-transition every 30 seconds
        setInterval(() => {
            autoTransitionAssets();
            fetchLifespanAlerts();
            fetchMaintenanceAlerts();
        }, 30000);
    </script>
</body>
</html>