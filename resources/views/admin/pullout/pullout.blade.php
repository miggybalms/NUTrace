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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }
        
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
        
        .pullout-card {
            transition: all 0.3s ease;
        }
        
        .pullout-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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
            background-color: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            animation: slideIn 0.3s ease;
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
<body class="bg-gray-50">
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
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <a href="#" onclick="window.history.back(); return false;" class="text-gray-500 hover:text-gray-700 mr-4 transition-transform hover:translate-x-[-2px]">
                                <i class="ri-arrow-left-line text-xl"></i>
                            </a>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Record Pullout</h2>
                                <p class="text-sm text-gray-500 mt-1">Manage pulled out assets</p>
                            </div>
                        </div>
                        <button onclick="openScannerAuto()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-all hover:scale-105 flex items-center shadow-md">
                            <i class="ri-add-line mr-2"></i>
                            Record Pullout
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Stats Card -->
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 mb-8 text-white" id="statsCard">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-90">Total Pulled Out</p>
                            <p class="text-4xl font-bold mt-2" id="totalPulledOutCount">{{ $totalPulledOut }}</p>
                            <p class="text-xs opacity-80 mt-2">Complete log of pulled out institutional assets</p>
                        </div>
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="ri-logout-box-r-line text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Pullout Records List -->
                <div id="pulloutRecordsContainer">
                    @if(isset($pulloutRecords) && count($pulloutRecords) > 0)
                        <div class="grid grid-cols-1 gap-4" id="pulloutRecordsList">
                            @foreach($pulloutRecords as $record)
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 pullout-card" data-id="{{ $record->id }}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="ri-logout-box-r-line text-orange-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold text-gray-900">{{ $record->asset_name ?? 'Asset' }}</h3>
                                                <p class="text-xs text-gray-500 font-mono">{{ $record->asset_code ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                            <div>
                                                <p class="text-xs text-gray-500">Pullout Date</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $record->pullout_date ?? date('Y-m-d') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Reason</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $record->reason ?? $record->Description ?? $record->notes ?? '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Pulled By</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $record->pulled_by ?? 'Admin' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Status</p>
                                                <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                    @if(($record->status ?? 'pending') == 'pending') bg-yellow-100 text-yellow-700
                                                    @elseif(($record->status ?? 'pending') == 'approved') bg-green-100 text-green-700
                                                    @else bg-red-100 text-red-700
                                                    @endif">
                                                    {{ ucfirst($record->status ?? 'pending') }}
                                                </span>
                                            </div>
                                        </div>
                                        @if($record->destination ?? false)
                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            <p class="text-xs text-gray-500">Destination / New Location</p>
                                            <p class="text-sm text-gray-700">{{ $record->destination }}</p>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="flex space-x-2">
                                        <button onclick="viewPulloutDetails({{ $record->id }})" class="text-blue-600 hover:text-blue-700">
                                            <i class="ri-eye-line text-xl"></i>
                                        </button>
                                        @if(($record->status ?? 'pending') == 'pending')
                                        <button onclick="approvePullout({{ $record->id }})" class="text-green-600 hover:text-green-700">
                                            <i class="ri-checkbox-circle-line text-xl"></i>
                                        </button>
                                        @endif
                                        <button onclick="deletePulloutRecord({{ $record->id }})" class="text-red-600 hover:text-red-700">
                                            <i class="ri-delete-bin-line text-xl"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div id="emptyState" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="ri-logout-box-r-line text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No pullout records yet</h3>
                            <p class="text-gray-500">There are currently no pullout reports to show.</p>
                            <button onclick="openNewPulloutModal()" class="mt-4 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                                <i class="ri-add-line mr-2"></i>
                                Record First Pullout
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- New Pullout Modal -->
    <div id="pulloutModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Record Asset Pullout</h3>
                    <button onclick="closePulloutModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="pulloutForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Asset *
                            <button type="button" onclick="openScanner('pullout_asset_select')" title="Scan asset QR" class="ml-3 inline-flex items-center px-2 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">
                                <i class="ri-camera-line"></i>
                                <span class="sr-only">Scan</span>
                            </button>
                        </label>
                        <select id="pullout_asset_select" name="asset_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                            <option value="">Search or select asset...</option>
                            @foreach($availableAssets ?? [] as $asset)
                            <option value="{{ $asset->id }}" data-code="{{ $asset->asset_code }}" data-status="{{ $asset->Lifecycle_Status }}">{{ $asset->name }} ({{ $asset->asset_code }}) - Assigned to: {{ $asset->assignedUser->name ?? 'Unassigned' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pullout Date *</label>
                        <input type="date" name="pullout_date" required value="{{ date('Y-m-d') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Pullout *</label>
                        <select name="reason" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pulled By *</label>
                        <input type="text" name="pulled_by" required placeholder="Name of person authorizing pullout" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Destination / New Location</label>
                        <input type="text" name="destination" placeholder="e.g., IT Department, Room 302, Storage Room" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expected Return Date (if applicable)</label>
                        <input type="date" name="expected_return_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any additional information about the pullout..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closePulloutModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Record Pullout</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-delete-bin-line text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Pullout Record</h3>
                    <p class="text-gray-500 mb-4">Are you sure you want to delete this pullout record? This action cannot be undone.</p>
                    <div class="flex space-x-3">
                        <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button onclick="confirmDelete()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scanner Modal - Stays open until manually closed (same style as disposal) -->
    <div id="scannerModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Scan Asset QR</h3>
                <button onclick="manualCloseScanner()" class="text-gray-400 hover:text-gray-600 transition">
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
            <p id="qr-reader-status" class="text-sm text-gray-600 text-center mb-3">Initializing camera...</p>
            
            <div class="flex justify-center">
                <button onclick="manualCloseScanner()" class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition flex items-center">
                    <i class="ri-close-line mr-2"></i>
                    Close Camera
                </button>
            </div>

            <div class="mt-4 p-3 bg-blue-50 border-l-4 border-blue-400 text-sm text-blue-800 rounded">
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
                asset_id: assetId,
                pullout_date: new Date().toISOString().slice(0,10),
                reason: 'Scanned Pullout',
                pulled_by: adminName,
                notes: 'Recorded by admin via QR scan.',
                status: 'pending'
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
            toast.style.backgroundColor = type === 'error' ? '#ef4444' : '#10b981';
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
        
        function viewPulloutDetails(id) {
            showToast('Viewing details for pullout #' + id);
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
        
        async function confirmDelete() {
            if (currentDeleteId) {
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const response = await fetch('/admin/pullout/delete/' + currentDeleteId, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        }
                    });
                    if (response.ok) {
                        showToast('Pullout record deleted');
                        await refreshPulloutList();
                    } else {
                        showToast('Failed to delete', 'error');
                    }
                } catch (error) {
                    showToast('Network error: ' + error.message, 'error');
                }
                closeDeleteModal();
            }
        }

        // Form submission
        document.getElementById('pulloutForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            data.status = 'pending';
            
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