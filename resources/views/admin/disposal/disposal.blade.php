{{-- resources/views/admin/disposal.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Disposal Management - Admin Dashboard</title>
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

        .disposal-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28);
            transition: all 0.3s ease;
        }
        
        .disposal-card:hover {
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

        /* Fixed scanner styles */
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

        /* Region around scanner for better visibility */
        #qrScannerWrap {
            background: #000;
            border-radius: 8px;
            padding: 0;
        }

        /* Scanning overlay effect */
        .scan-region {
            position: relative;
        }

        .scan-region::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            pointer-events: none;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    @php
    $disposalRecords = $disposalRecords ?? collect();
    $availableAssets = $availableAssets ?? collect();
    if (!isset($totalDisposed)) {
        $totalDisposed = is_countable($disposalRecords) ? count($disposalRecords) : 0;
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
                                <h2 class="font-display text-xl sm:text-2xl font-semibold" style="color:var(--navy-900);">Disposal</h2>
                                <p class="text-sm mt-1 hidden sm:block" style="color:var(--ink-600);">Manage disposed assets</p>
                            </div>
                        </div>
                        <button onclick="openScannerAuto()" class="btn-gold">
                            <i class="ri-add-line sm:mr-2"></i>
                            <span class="hidden sm:inline">Record Disposal</span>
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
                            <p class="eyebrow" style="color:var(--gold-500);">Total Disposed Assets</p>
                            <p class="font-display text-4xl font-bold mt-2" id="totalDisposedCount">{{ $totalDisposed }}</p>
                            <p class="text-xs mt-2" style="color:#C7D2E3;">Complete log of all disposed institutional assets</p>
                        </div>
                        <div class="w-20 h-20 rounded-full flex items-center justify-center" style="background:rgba(162,59,50,.35); border:1px solid rgba(255,255,255,.15);">
                            <i class="ri-delete-bin-line text-4xl" style="color:#F0C4BE;"></i>
                        </div>
                    </div>
                </div>

                <!-- Disposal Records List -->
                <div id="disposalRecordsContainer">
                    @if(isset($disposalRecords) && count($disposalRecords) > 0)
                        <div class="grid grid-cols-1 gap-4" id="disposalRecordsList">
                            @foreach($disposalRecords as $record)
                            <div class="disposal-card p-6" data-id="{{ $record->id }}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3" style="background:var(--brick-tint);">
                                                <i class="ri-delete-bin-line text-xl" style="color:var(--brick);"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold" style="color:var(--navy-900);">{{ $record->asset_name ?? 'Asset' }}</h3>
                                                <p class="text-xs font-mono" style="color:var(--ink-400);">{{ $record->asset_code ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Disposal Date</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->disposal_date ?? date('Y-m-d') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Reason</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->reason ?? $record->Description ?? $record->notes ?? '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Disposed By</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->disposed_by ?? $record->Approve_by ?? '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Original Value</p>
                                                <p class="text-sm font-medium font-mono" style="color:var(--navy-900);">₱{{ number_format($record->original_value ?? 0, 2) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                        <div class="flex space-x-1">
                                            @if($record->asset_still_exists ?? true)
                                                {{-- Asset still in database → show view + permanent delete --}}
                                                <button onclick="viewDisposalDetails({{ $record->id }})"
                                                        class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--steel);"
                                                        onmouseover="this.style.background='var(--steel-tint)'" onmouseout="this.style.background='transparent'"
                                                        title="View">
                                                    <i class="ri-eye-line text-xl"></i>
                                                </button>
                                                <button onclick="permanentDeleteAsset({{ $record->id }})"
                                                        class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--brick);"
                                                        onmouseover="this.style.background='var(--brick-tint)'" onmouseout="this.style.background='transparent'"
                                                        title="Permanently delete asset from system">
                                                    <i class="ri-delete-bin-line text-xl"></i>
                                                </button>
                                            @else
                                                {{-- Asset already gone → just a historical record --}}
                                                <span class="text-xs italic self-center" style="color:var(--ink-400);">Archived</span>
                                            @endif
                                        </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div id="emptyState" class="p-12 text-center" style="background:#fff; border-radius:14px; border:1px solid var(--line);">
                            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--paper-2);">
                                <i class="ri-inbox-line text-4xl" style="color:var(--ink-400);"></i>
                            </div>
                            <h3 class="text-lg font-semibold mb-2" style="color:var(--navy-900);">No disposal records yet</h3>
                            <p style="color:var(--ink-400);">There are currently no disposal reports to show.</p>
                            <button onclick="openNewDisposalModal()" class="btn-gold mt-4">
                                <i class="ri-add-line mr-2"></i>
                                Record First Disposal
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

    <!-- New Disposal Modal -->
    <div id="disposalModal" class="hidden fixed inset-0 z-50 items-center justify-center modal" style="background:rgba(10,24,48,.55);">
        <div class="rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head p-6">
                <div class="flex justify-between items-center">
                    <h3 class="font-display text-xl font-semibold text-white">Record Asset Disposal</h3>
                    <button onclick="closeDisposalModal()" class="text-white/60 hover:text-white">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="disposalForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Select Asset *
                            <button type="button" onclick="openScanner('disposal_asset_select')" title="Scan asset QR" class="ml-3 inline-flex items-center px-2 py-1 rounded text-sm transition-colors" style="border:1px solid var(--line); color:var(--ink-600);" onmouseover="this.style.background='var(--paper-2)'" onmouseout="this.style.background='transparent'">
                                <i class="ri-camera-line"></i>
                                <span class="sr-only">Scan</span>
                            </button>
                        </label>
                        <select id="disposal_asset_select" name="asset_id" required class="form-input">
                            <option value="">Search or select asset...</option>
                            @foreach($availableAssets ?? [] as $asset)
                            <option value="{{ $asset->id }}" data-code="{{ $asset->asset_code }}" data-status="{{ $asset->Lifecycle_Status }}">{{ $asset->name }} ({{ $asset->asset_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Disposal Date *</label>
                        <input type="date" name="disposal_date" required value="{{ date('Y-m-d') }}" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Reason for Disposal *</label>
                        <select name="reason" required class="form-input">
                            <option value="">Select reason...</option>
                            <option value="Damaged">Damaged - Beyond Repair</option>
                            <option value="Obsolete">Obsolete - No longer needed</option>
                            <option value="Lost">Lost / Missing</option>
                            <option value="Stolen">Stolen</option>
                            <option value="Upgraded">Upgraded - Replaced by newer model</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Disposed By *</label>
                        <input type="text" name="disposed_by" required placeholder="Name of person authorizing disposal" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color:var(--ink-600);">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any additional information about the disposal..." class="form-input"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4" style="border-top:1px solid var(--line);">
                    <button type="button" onclick="closeDisposalModal()" class="btn-ghost">Cancel</button>
                    <button type="submit" class="btn-gold">Record Disposal</button>
                </div>
            </form>
        </div>
    </div>


    <!-- View Disposal Details Modal -->
<div id="viewDisposalModal" class="hidden fixed inset-0 z-50 items-center justify-center modal" style="background:rgba(10,24,48,.55);">
    <div class="rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
        <div class="modal-head p-6 sticky top-0 z-10 flex justify-between items-center">
            <h3 class="font-display text-xl font-semibold text-white">Disposal Details</h3>
            <button onclick="closeViewDisposalModal()" class="text-white/60 hover:text-white">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>
        <div class="p-6" id="viewDisposalContent">
            <!-- filled by JS -->
        </div>
        <div class="p-6 flex justify-end" style="border-top:1px solid var(--line);">
            <button onclick="closeViewDisposalModal()" class="btn-ghost">Close</button>
        </div>
    </div>
</div>

    <!-- Scanner Modal -->
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
                <button onclick="manualCloseScanner()" class="px-6 py-2 text-white rounded-lg transition flex items-center" style="background:var(--brick);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
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
    <script>
        // Scanner variables
        let html5QrCode = null;
        let currentScannerSelectId = null;
        let scannerAutoMode = false;
        let isScanning = false;
        const adminName = "{{ Auth::user()->name ?? Auth::user()->email ?? 'Admin' }}";
        
        const statusEl = document.getElementById('qr-reader-status');

        // Open scanner for auto disposal
        function openScannerAuto() {
            scannerAutoMode = true;
            currentScannerSelectId = null;
            
            // Show modal
            const modal = document.getElementById('scannerModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            statusEl.textContent = 'Requesting camera permission...';
            
            // Stop any existing scanner
            stopScanner();
            
            // Start scanner after a short delay
            setTimeout(() => {
                startHtml5Scanner();
            }, 100);
        }

        // Open scanner for asset selection
        function openScanner(selectId) {
            scannerAutoMode = false;
            currentScannerSelectId = selectId;
            
            const modal = document.getElementById('scannerModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            statusEl.textContent = 'Requesting camera permission...';
            
            stopScanner();
            
            setTimeout(() => {
                startHtml5Scanner();
            }, 100);
        }

        function startHtml5Scanner() {
            const scannerElement = document.getElementById('qrScanner');
            
            if (!scannerElement) {
                console.error('Scanner element not found');
                statusEl.textContent = 'Error: Scanner element not found';
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
                        // Success callback
                        console.log('QR Code detected:', decodedText);
                        handleSuccessfulScan(decodedText);
                    },
                    (errorMessage) => {
                        // Error callback - silent fail, scanner continues
                        // console.log('Scan error:', errorMessage);
                    }
                ).then(() => {
                    statusEl.textContent = '✓ Camera ready - scanning for QR codes...';
                    isScanning = true;
                }).catch((err) => {
                    console.error('Failed to start scanner:', err);
                    statusEl.textContent = 'Error starting camera. Please check permissions.';
                    showToast('Camera error: ' + err.message);
                    
                    // Try fallback with getUserMedia directly
                    tryFallbackCamera();
                });
            } catch (err) {
                console.error('Exception starting scanner:', err);
                statusEl.textContent = 'Error initializing scanner.';
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
                    video.srcObject = stream;
                    video.style.display = 'block';
                    video.play();
                    statusEl.textContent = '✓ Camera active (fallback mode)';
                    
                    // Simple frame capture for scanning
                    const canvas = document.getElementById('qrCanvas');
                    const context = canvas.getContext('2d');
                    
                    // Set up interval to capture frames
                    if (window.scanInterval) clearInterval(window.scanInterval);
                    window.scanInterval = setInterval(() => {
                        if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);
                            
                            // Use jsQR library if available
                            if (typeof jsQR !== 'undefined') {
                                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                                const code = jsQR(imageData.data, imageData.width, imageData.height);
                                if (code && code.data) {
                                    handleSuccessfulScan(code.data);
                                }
                            }
                        }
                    }, 500);
                })
                .catch(function(err) {
                    console.error('Fallback camera error:', err);
                    statusEl.textContent = 'Cannot access camera. Please check permissions.';
                });
            }
        }

        function handleSuccessfulScan(decodedText) {
            // Play beep sound (optional)
            try {
                const beep = new Audio('data:audio/wav;base64,U3RlYWx0aCBzb3VuZA==');
                beep.play().catch(() => {});
            } catch(e) {}
            
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
                handleAutoDispose(decodedText);
                statusEl.textContent = '✓ Disposal recorded! Ready for next scan...';
            } else if (currentScannerSelectId) {
                handleScannedCode(decodedText, currentScannerSelectId);
                statusEl.textContent = '✓ Asset selected! Ready for next scan...';
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
                sel.value = opt.value;
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

async function handleAutoDispose(code) {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let assetId = null;
    let assetStatus = null;

    // 1. Try to find asset in the dropdown first
    let opt = document.querySelector('option[data-code="' + code + '"]');
    if (opt) {
        assetId = opt.value;
        assetStatus = opt.getAttribute('data-status');
    } else {
        // 2. Fallback: ask the server
        try {
            const res = await fetch('/admin/assets/find-by-code?code=' + encodeURIComponent(code), {
                headers: { 'Accept': 'application/json' }
            });
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

    // 3. Send disposal request
    const payload = {
        asset_id: assetId,
        disposal_date: new Date().toISOString().slice(0, 10),
        reason: 'Scanned Disposal',
        disposed_by: adminName,
        notes: 'Recorded by admin via QR scan.',
    };

    try {
        const res = await fetch('/admin/disposal/record', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json().catch(() => ({}));
        console.log('Disposal response:', res.status, json);

        if (res.ok && json.success) {
            showToast('✓ Disposal recorded successfully!');
            await refreshDisposalList();
        } else {
            // This is where "already exists" message will appear
            const realError = json.error || json.message || res.statusText || 'Unknown error';
            showToast('Failed: ' + realError, 'error');
            console.error('Disposal error:', json);
        }
    } catch (e) {
        showToast('Network error: ' + e.message, 'error');
        console.error(e);
    }
}
        async function refreshDisposalList() {
            try {
                const response = await fetch(window.location.href);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newRecordsList = doc.querySelector('#disposalRecordsList');
                const oldRecordsList = document.querySelector('#disposalRecordsList');
                const newEmptyState = doc.querySelector('#emptyState');
                const oldEmptyState = document.querySelector('#emptyState');
                const newTotalCount = doc.querySelector('#totalDisposedCount');
                const oldTotalCount = document.querySelector('#totalDisposedCount');
                
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
                
            } catch (error) {
                console.error('Failed to refresh:', error);
            }
        }

        function stopScanner() {
            if (window.scanInterval) {
                clearInterval(window.scanInterval);
                window.scanInterval = null;
            }
            
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                }).catch(() => {
                    html5QrCode = null;
                });
            }
            
            // Stop video stream
            const video = document.getElementById('qrVideo');
            if (video && video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
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
            statusEl.textContent = 'Camera stopped.';
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

        function openNewDisposalModal() {
            document.getElementById('disposalModal').classList.remove('hidden');
            document.getElementById('disposalModal').classList.add('flex');
        }
        
        function closeDisposalModal() {
            document.getElementById('disposalModal').classList.add('hidden');
            document.getElementById('disposalModal').classList.remove('flex');
        }
        
function closeViewDisposalModal() {
    const modal = document.getElementById('viewDisposalModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function viewDisposalDetails(id) {
    const modal = document.getElementById('viewDisposalModal');
    const content = document.getElementById('viewDisposalContent');
    content.innerHTML = '<p class="text-center py-10" style="color:var(--ink-400);">Loading...</p>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        const res = await fetch(`/admin/disposal/${id}/details`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) throw new Error('Failed to load details');
        const data = await res.json();
        renderDisposalDetails(data);
    } catch (err) {
        content.innerHTML = `<p class="text-center py-10" style="color:var(--brick);">Failed to load details: ${err.message}</p>`;
    }
}

function isEmptyVal(v) {
    return v === null || v === undefined || v === '' ||
           String(v).trim() === '' || String(v).trim() === '-' ||
           String(v).toLowerCase() === 'n/a' || String(v).toLowerCase() === 'null';
}

function fieldRow(label, value) {
    if (isEmptyVal(value)) return '';
    return `
        <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
            <p class="text-xs mb-1" style="color:var(--ink-400);">${label}</p>
            <p class="text-sm font-medium" style="color:var(--navy-900);">${value}</p>
        </div>`;
}

function money(v) {
    if (isEmptyVal(v) || isNaN(v)) return null;
    return '₱' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 });
}

function renderDisposalDetails(d) {
    const content = document.getElementById('viewDisposalContent');

    const header = `
        <div class="flex items-center mb-5">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3" style="background:var(--brick-tint);">
                <i class="ri-delete-bin-line text-xl" style="color:var(--brick);"></i>
            </div>
            <div>
                <h4 class="font-semibold" style="color:var(--navy-900);">${d.asset_name || 'Asset'}</h4>
                ${!isEmptyVal(d.asset_code) ? `<p class="text-xs font-mono" style="color:var(--ink-400);">${d.asset_code}</p>` : ''}
            </div>
        </div>`;

    const disposalRows = [
        fieldRow('Disposal Date', d.disposal_date),
        fieldRow('Reason', d.reason),
        fieldRow('Disposed By', d.disposed_by),
        fieldRow('Original Value', money(d.original_value)),
    ].filter(Boolean).join('');

    const notesBlock = !isEmptyVal(d.notes) ? `
        <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
            <p class="text-xs mb-1" style="color:var(--ink-400);">Notes</p>
            <p class="text-sm" style="color:var(--ink-600);">${d.notes}</p>
        </div>` : '';

    const assetRows = [
        fieldRow('Category', d.category),
        fieldRow('Condition', d.condition),
        fieldRow('Serial Number', d.serial_number),
        fieldRow('Location', d.asset_location),
        fieldRow('Supplier', d.supplier),
        fieldRow('Model', d.model),
        fieldRow('Manufacturer', d.manufacture),
        fieldRow('Purchase Price', money(d.purchase_price)),
        fieldRow('Warranty (months)', d.warranty_months),
        fieldRow('Lifespan (months)', d.lifespan_months),
    ].filter(Boolean).join('');

    const assetSection = assetRows ? `
        <div class="pt-4 mt-5" style="border-top:1px solid var(--line);">
            <p class="eyebrow mb-3">Asset Details</p>
            <div class="grid grid-cols-2 gap-3">${assetRows}</div>
        </div>` : `
        <div class="pt-4 mt-5" style="border-top:1px solid var(--line);">
            <p class="text-xs italic" style="color:var(--ink-400);">Asset record no longer exists — this is a historical entry.</p>
        </div>`;

    content.innerHTML = `
        ${header}
        <div class="grid grid-cols-2 gap-3">${disposalRows}</div>
        ${notesBlock}
        ${assetSection}
    `;
}

async function permanentDeleteAsset(disposalId) {
    if (!confirm('This will PERMANENTLY delete the asset from the system.\n\nThe disposal record will remain for history, but the asset can never be recovered.\n\nAre you sure?')) {
        return;
    }

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch(`/admin/disposal/${disposalId}/permanent-delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
        });

        const data = await res.json();

        if (!res.ok) {
            showToast(data.error || data.message || 'Failed to delete', 'error');
            console.error('Permanent delete failed:', data);
            return;
        }

        showToast(data.message || 'Asset permanently deleted. Record kept.');
        await refreshDisposalList();
    } catch (err) {
        showToast('Network error: ' + err.message, 'error');
    }
}

document.getElementById('disposalForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const response = await fetch('/admin/disposal/record', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const json = await response.json().catch(() => ({}));

        if (response.ok && json.success) {
            showToast('✓ Disposal recorded successfully!');
            closeDisposalModal();
            await refreshDisposalList();
            this.reset();
        } else {
            const realError = json.error || json.message || response.statusText || 'Unknown error';
            showToast('Failed: ' + realError, 'error');
        }
    } catch (error) {
        showToast('Network error: ' + error.message, 'error');
    }
});

window.addEventListener('beforeunload', function() {
    stopScanner();
});
</script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</body>
</html>