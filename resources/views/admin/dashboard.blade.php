<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard - Asset Management</title>
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
        
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

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
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')
        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-blue-600 font-medium">Admin</span>
                                <span class="mx-2 text-gray-300">•</span>
                                <p class="text-sm text-gray-500">Overview of asset management system</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <!-- Maintenance Alerts Bell -->
                            <div class="relative">
                                <button id="maintenanceAlertsBell" class="relative cursor-pointer text-gray-600 hover:text-gray-900 transition">
                                    <i class="ri-notification-3-line text-xl"></i>
                                    <span id="alertBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center hidden">0</span>
                                </button>
                                
                                <!-- Maintenance Alerts Dropdown -->
                                <div id="alertsDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-y-auto">
                                    <div class="p-4 border-b border-gray-100">
                                        <h3 class="font-semibold text-gray-900">Maintenance Alerts</h3>
                                        <p class="text-xs text-gray-500 mt-1">Assets due for maintenance</p>
                                    </div>
                                    <div id="alertsList" class="divide-y divide-gray-100">
                                        <div class="p-4 text-center text-gray-500 text-sm">
                                            <p><i class="ri-check-line text-green-500 text-lg"></i></p>
                                            <p>No maintenance alerts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1">
                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-semibold">AD</span>
                                </div>
                                <i class="ri-arrow-down-s-line text-gray-500"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="p-8">
                <!-- Quick Summary -->
                <div class="mb-8 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Quick summary of key metrics</h3>
                        <p class="text-sm text-gray-500 mt-1">Real-time overview of your asset inventory</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button id="openScannerBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center space-x-2">
                            <i class="ri-scan-line"></i>
                            <span>Scanner</span>
                        </button>
                    </div>
                </div>

                <!-- Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Acquired this month -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Acquired this month</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($acquiredThisMonth ?? 1847) }}</p>
                                @php
                                    $pct = $acquiredChangePercent ?? 0;
                                @endphp
                                <p class="text-xs mt-2 flex items-center"
                                   style="color: {{ $pct > 0 ? '#16a34a' : ($pct < 0 ? '#dc2626' : '#6b7280') }};">
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
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="ri-calendar-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Active Assets -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Active Assets</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($activeAssets ?? 2854) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Total active inventory</p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="ri-checkbox-circle-line text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- For Repair -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">For Repair</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($forRepairAssets ?? 28) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Needs maintenance</p>
                            </div>
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="ri-tools-line text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Requests -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Pending Requests</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($pendingRequests ?? 15) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Awaiting approval</p>
                            </div>
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="ri-time-line text-orange-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Links</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="/admin/requests?tab=pending" class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-time-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Pending Requests</p>
                            <p class="text-xs text-gray-500 mt-1">View pending approvals</p>
                        </a>
                        <a href="/admin/assets/registry" class="bg-gradient-to-r from-green-50 to-green-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-database-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Asset Registry</p>
                            <p class="text-xs text-gray-500 mt-1">Register new assets</p>
                        </a>
                        <a href="/admin/disposal" class="bg-gradient-to-r from-red-50 to-red-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-delete-bin-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Record Disposal</p>
                            <p class="text-xs text-gray-500 mt-1">Log disposed assets</p>
                        </a>
                        <a href="/admin/pullout" class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl p-5 text-center hover:shadow-md transition-all group">
                            <div class="w-12 h-12 bg-orange-500 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="ri-logout-box-r-line text-2xl text-white"></i>
                            </div>
                            <p class="font-semibold text-gray-900">Record Pullout</p>
                            <p class="text-xs text-gray-500 mt-1">Log pulled out assets</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                                <p class="text-sm text-gray-500 mt-1">Latest actions and updates</p>
                            </div>
                            <a href="/admin/audit-logs" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                View All
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        @if(isset($recentActivities) && count($recentActivities) > 0)
                            <div class="space-y-4">
                                @foreach($recentActivities as $activity)
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="ri-file-copy-line text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $activity->type }}</p>
                                            <p class="text-sm text-gray-500">{{ $activity->description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400">{{ $activity->time }}</span>
                                        @if(isset($activity->status))
                                            <p class="text-xs mt-1">
                                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                                    @if($activity->status == 'pending') bg-orange-100 text-orange-700
                                                    @elseif($activity->status == 'approved') bg-green-100 text-green-700
                                                    @endif">
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
                                <i class="ri-inbox-line text-5xl text-gray-300 mb-3 block"></i>
                                <p class="text-gray-500">No recent activities</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Scanner Modal -->
    <div id="scannerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Asset Scanner</h3>
                <button onclick="closeScannerModal()" class="text-gray-400 hover:text-gray-600"><i class="ri-close-line text-2xl"></i></button>
            </div>
                <div class="space-y-4">
                <p class="text-sm text-gray-500">Scan or enter an asset code to view details.</p>
                <div class="flex space-x-2">
                    <button id="startCameraBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Start Camera</button>
                    <button id="stopCameraBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg" style="display:none">Turn Off Camera</button>
                </div>

                <div id="qrScannerWrap" class="hidden">
                    <div id="qrScanner" style="width:100%;max-width:420px;margin-top:8px">
                        <!-- html5-qrcode will render here or fallback will use video -->
                        <video id="qrVideo" autoplay muted playsinline style="width:100%;height:auto;display:none;border-radius:6px;border:1px solid #e5e7eb;"></video>
                        <canvas id="qrCanvas" style="display:none;"></canvas>
                    </div>
                    <div class="mt-2 flex items-center space-x-2">
                        <button id="stopCameraBtn" class="px-3 py-1 bg-red-600 text-white rounded">Stop</button>
                        <span class="text-sm text-gray-500">Camera will auto-detect QR codes.</span>
                    </div>
                </div>

                <div id="scannerResult" class="hidden bg-gray-50 p-4 rounded border border-gray-100">
                    <div id="assetImageWrapper" class="mb-4 flex justify-center">
                        <img id="assetImage" src="" alt="Asset" class="h-32 w-32 object-cover rounded border border-gray-200 hidden">
                    </div>
                    <h4 class="font-semibold text-gray-900" id="resName">-</h4>
                    <p class="text-xs text-gray-500 font-mono" id="resCode">-</p>
                    <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            <p id="resStatus" class="text-sm font-medium">-</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Department</p>
                            <p id="resDept" class="text-sm font-medium">-</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Owner</p>
                            <p id="resOwner" class="text-sm font-medium">-</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Location</p>
                            <p id="resLocation" class="text-sm font-medium">-</p>
                        </div>
                    </div>
                </div>
                <p id="scannerMsg" class="text-sm text-red-600 hidden"></p>
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="closeScannerModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
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

        // Maintenance Alerts System
        const maintenanceAlertsBell = document.getElementById('maintenanceAlertsBell');
        const alertsDropdown = document.getElementById('alertsDropdown');
        const alertBadge = document.getElementById('alertBadge');
        const alertsList = document.getElementById('alertsList');

        // Fetch maintenance alerts
        function fetchMaintenanceAlerts() {
            fetch('/admin/api/maintenance-alerts')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.count;
                        
                        // Update badge
                        if (count > 0) {
                            alertBadge.textContent = count;
                            alertBadge.classList.remove('hidden');
                            maintenanceAlertsBell.classList.add('animate-pulse');
                            playNotificationSound();
                        } else {
                            alertBadge.classList.add('hidden');
                            maintenanceAlertsBell.classList.remove('animate-pulse');
                        }

                        // Update alerts list
                        if (count > 0) {
                            alertsList.innerHTML = data.alerts.map(alert => `
                                <div class="p-3 hover:bg-gray-50 transition border-l-4 border-orange-500">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900">${alert.Asset_name}</p>
                                            <p class="text-xs text-gray-500 mt-0.5">Code: ${alert.Asset_code}</p>
                                            <p class="text-xs text-gray-500">Assigned to: ${alert.assigned_to || 'Unassigned'}</p>
                                            <p class="text-xs text-orange-600 font-medium mt-1">
                                                <i class="ri-alert-line"></i> Due: ${new Date(alert.next_maintenance_date).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <a href="/admin/assets/${alert.id}" class="text-xs text-blue-600 hover:text-blue-700 font-medium ml-2">
                                            View <i class="ri-arrow-right-line"></i>
                                        </a>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            alertsList.innerHTML = `
                                <div class="p-4 text-center text-gray-500 text-sm">
                                    <p><i class="ri-check-line text-green-500 text-lg"></i></p>
                                    <p>No maintenance alerts</p>
                                </div>
                            `;
                        }
                    }
                })
                .catch(error => console.error('Error fetching maintenance alerts:', error));
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

        // Fetch alerts on page load
        fetchMaintenanceAlerts();

        // Refresh alerts every 30 seconds
        setInterval(fetchMaintenanceAlerts, 30000);
    </script>
</body>
</html>