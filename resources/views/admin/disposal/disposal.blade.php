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
        
        .disposal-card {
            transition: all 0.3s ease;
        }
        
        .disposal-card:hover {
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
<body class="bg-gray-50">
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
                                <h2 class="text-2xl font-bold text-gray-900">Disposal</h2>
                                <p class="text-sm text-gray-500 mt-1">Manage disposed assets</p>
                            </div>
                        </div>
                        <button onclick="openScannerAuto()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-all hover:scale-105 flex items-center shadow-md">
                            <i class="ri-add-line mr-2"></i>
                            Record Disposal
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Stats Card -->
                <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 mb-8 text-white" id="statsCard">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-90">Total Disposed Assets</p>
                            <p class="text-4xl font-bold mt-2" id="totalDisposedCount">{{ $totalDisposed }}</p>
                            <p class="text-xs opacity-80 mt-2">Complete log of all disposed institutional assets</p>
                        </div>
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="ri-delete-bin-line text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Disposal Records List -->
                <div id="disposalRecordsContainer">
                    @if(isset($disposalRecords) && count($disposalRecords) > 0)
                        <div class="grid grid-cols-1 gap-4" id="disposalRecordsList">
                            @foreach($disposalRecords as $record)
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 disposal-card" data-id="{{ $record->id }}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="ri-delete-bin-line text-red-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold text-gray-900">{{ $record->asset_name ?? 'Asset' }}</h3>
                                                <p class="text-xs text-gray-500 font-mono">{{ $record->asset_code ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                            <div>
                                                <p class="text-xs text-gray-500">Disposal Date</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $record->disposal_date ?? date('Y-m-d') }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Reason</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $record->reason ?? $record->Description ?? $record->notes ?? '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Disposed By</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $record->disposed_by ?? $record->Approve_by ?? '-' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">Original Value</p>
                                                <p class="text-sm font-medium text-gray-900">₱{{ number_format($record->original_value ?? 0, 2) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button onclick="viewDisposalDetails({{ $record->id }})" class="text-blue-600 hover:text-blue-700">
                                            <i class="ri-eye-line text-xl"></i>
                                        </button>
                                        <button onclick="deleteDisposalRecord({{ $record->id }})" class="text-red-600 hover:text-red-700">
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
                                <i class="ri-inbox-line text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No disposal records yet</h3>
                            <p class="text-gray-500">There are currently no disposal reports to show.</p>
                            <button onclick="openNewDisposalModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                <i class="ri-add-line mr-2"></i>
                                Record First Disposal
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

    <!-- New Disposal Modal -->
    <div id="disposalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Record Asset Disposal</h3>
                    <button onclick="closeDisposalModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="disposalForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Asset *
                            <button type="button" onclick="openScanner('disposal_asset_select')" title="Scan asset QR" class="ml-3 inline-flex items-center px-2 py-1 border border-gray-300 rounded text-sm hover:bg-gray-100">
                                <i class="ri-camera-line"></i>
                                <span class="sr-only">Scan</span>
                            </button>
                        </label>
                        <select id="disposal_asset_select" name="asset_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                            <option value="">Search or select asset...</option>
                            @foreach($availableAssets ?? [] as $asset)
                            <option value="{{ $asset->id }}" data-code="{{ $asset->asset_code }}" data-status="{{ $asset->Lifecycle_Status }}">{{ $asset->name }} ({{ $asset->asset_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Disposal Date *</label>
                        <input type="date" name="disposal_date" required value="{{ date('Y-m-d') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Disposal *</label>
                        <select name="reason" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Disposed By *</label>
                        <input type="text" name="disposed_by" required placeholder="Name of person authorizing disposal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any additional information about the disposal..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeDisposalModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Record Disposal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scanner Modal -->
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
                <button onclick="manualCloseScanner()" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center">
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
            toast.style.backgroundColor = type === 'error' ? '#ef4444' : '#10b981';
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
        
        function viewDisposalDetails(id) {
            showToast('Viewing details for disposal #' + id);
        }
        
        function deleteDisposalRecord(id) {
            if (confirm('Are you sure you want to delete this disposal record?')) {
                showToast('Disposal record #' + id + ' deleted');
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
        console.log('Form disposal response:', response.status, json);

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

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopScanner();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</body>
</html>