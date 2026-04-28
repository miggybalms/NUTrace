<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Submit Request - Asset Management</title>
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
            background-color: #e5e7eb;
            color: #1f2937;
        }
        
        .sidebar-item.active {
            background-color: #eff6ff;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .upload-area {
            transition: all 0.2s ease;
        }
        
        .upload-area:hover {
            border-color: #3b82f6;
            background-color: #f9fafb;
        }
        
        .submit-btn {
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('users.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex items-center">
                        <a href="#" class="text-gray-500 hover:text-gray-700 mr-4">
                            <i class="ri-arrow-left-line text-xl"></i>
                        </a>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">Submit Request</h2>
                            <p class="text-sm text-gray-500 mt-1">Submit a new asset request</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="p-8">
                <form action="{{ route('user.requests.store') }}" method="POST" enctype="multipart/form-data" class="max-w-2xl mx-auto">
                    @csrf

                    @if(session('success'))
                        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                            <div class="flex items-center">
                                <i class="ri-checkbox-circle-line mr-2 text-lg"></i>
                                <span>{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Request Type -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Request Type <span class="text-red-500">*</span>
                            </label>
                            <select name="request_type" required class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                                <option value="">Select Request Type</option>
                                <option value="Repair" {{ old('request_type') == 'Repair' ? 'selected' : '' }}>Repair Request</option>
                                <option value="Disposal" {{ old('request_type') == 'Disposal' ? 'selected' : '' }}>Disposal Request</option>
                                <option value="Transfer" {{ old('request_type') == 'Transfer' ? 'selected' : '' }}>Transfer Request</option>
                                <option value="Replacement" {{ old('request_type') == 'Replacement' ? 'selected' : '' }}>Replacement Request</option>
                                <option value="Pullout" {{ old('request_type') == 'Pullout' ? 'selected' : '' }}>Pullout Request</option>
                                <option value="Other" {{ old('request_type') == 'Other' ? 'selected' : '' }}>Other Request</option>
                            </select>
                        </div>
                    </div>

                    <!-- Asset QR Code Section -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset QR Code (Optional)</h3>
                        
                        <div class="mb-4">
                            <div class="flex items-center space-x-3">
                                <button type="button" id="toggle-scanner" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    <i class="ri-camera-line mr-2"></i>Scan with camera
                                </button>
                                <label class="upload-area relative border-2 border-dashed border-gray-300 rounded-lg p-3 text-center cursor-pointer flex-1" for="qr-upload">
                                    <button type="button" id="clear-qr-top-btn" title="Clear" class="absolute right-2 top-2 bg-white border border-gray-200 rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-50">Clear</button>
                                    <div>
                                        <i class="ri-qr-code-line text-3xl text-gray-400 mb-2 block"></i>
                                        <p class="text-sm text-gray-600">+ Upload Asset QR Code</p>
                                        <p class="text-xs text-gray-400 mt-1">Click to upload or drag and drop</p>
                                    </div>
                                </label>
                                <input type="file" id="qr-upload" name="qr_code" class="hidden" accept="image/*">
                            </div>

                            <div id="qr-preview" class="mt-3 hidden">
                                <img id="qr-preview-img" class="h-24 w-auto rounded-lg border border-gray-200" alt="QR Preview">
                            </div>

                            <div id="qr-reader" class="mt-4 hidden"></div>
                        </div>

                        <div class="relative my-4">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white text-gray-500">or</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Enter Asset Code manually
                            </label>
                            <div class="relative">
                                <input id="asset_code_input" type="text" name="asset_code" 
                                    placeholder="Enter asset code (e.g., AST-12345)"
                                    class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                            </div>
                            <p id="asset-code-feedback" class="text-xs mt-1"></p>
                            <p class="text-xs text-gray-400 mt-1">Enter the asset code if you know it, or leave blank for new asset requests</p>
                        </div>
                    </div>

                    <!-- Reason / Notes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Reason / Notes / Specific Instructions <span class="text-red-500">*</span>
                        </label>
                        <textarea name="notes" rows="6" required
                                  placeholder="Please describe your concerns, reason for the request, or any specific instructions..."
                                  class="form-textarea w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"></textarea>
                    </div>

                    <!-- Attach Photo -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Attach Photo (Optional)
                        </label>
                        <div class="upload-area border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer" onclick="document.getElementById('photo-upload').click()">
                            <i class="ri-image-line text-3xl text-gray-400 mb-2 block"></i>
                            <p class="text-sm text-gray-600">Click to upload or drag and drop</p>
                            <p class="text-xs text-gray-400 mt-1">PNG, JPG up to 10MB</p>
                            <input type="file" id="photo-upload" name="attachment" class="hidden" accept="image/*" onchange="previewPhoto(this)">
                        </div>
                        <div id="photo-preview" class="mt-3 hidden">
                            <img id="photo-preview-img" class="h-32 w-auto rounded-lg border border-gray-200" alt="Photo Preview">
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4">
                        <button type="button" onclick="window.history.back()" 
                                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button id="request-submit" type="submit" 
                                class="submit-btn px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center shadow-md">
                            <i class="ri-send-plane-line mr-2"></i>
                            Submit Request
                        </button>
                    </div>
                </form>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        // Debounce helper
        function debounce(fn, wait) {
            let t;
            return function (...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        // Asset code validation
        const assetCodeInput = document.querySelector('input[name="asset_code"]') || document.getElementById('asset_code_input');
        const assetFeedback = document.getElementById('asset-code-feedback');
        const submitBtn = document.getElementById('request-submit');
        let assetValid = null; // null=unknown, true=valid, false=invalid

        async function validateAssetCode(code) {
            if (!code) {
                assetFeedback.textContent = '';
                assetFeedback.className = 'text-xs mt-1';
                assetValid = null;
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50');
                return;
            }

            try {
                const url = '{{ url('/user/assets/check-code') }}?code=' + encodeURIComponent(code);
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) throw new Error('Network');
                const data = await resp.json();
                if (data.exists) {
                    assetFeedback.textContent = 'Asset found: ' + (data.asset.name || 'Unnamed');
                    assetFeedback.className = 'text-xs mt-1 text-green-600';
                    assetValid = true;
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50');
                } else {
                    assetFeedback.textContent = "This asset doesn't exist or isn't assigned to you.";
                    assetFeedback.className = 'text-xs mt-1 text-red-600';
                    assetValid = false;
                    // disable submit to prevent invalid code submission
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50');
                }
            } catch (e) {
                assetFeedback.textContent = '';
                assetFeedback.className = 'text-xs mt-1';
                assetValid = null;
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50');
            }
        }

        const debouncedValidate = debounce((e) => validateAssetCode(e.target.value.trim()), 400);
        assetCodeInput?.addEventListener('input', debouncedValidate);

        // QR Scanner integration using html5-qrcode
        let html5QrCode = null;
        let scannerActive = false;
        const toggleScannerBtn = document.getElementById('toggle-scanner');
        const qrReader = document.getElementById('qr-reader');

        async function onDetected(decodedText) {
            // set value and validate
            if (!decodedText) return;
            assetCodeInput.value = decodedText;
            validateAssetCode(decodedText);
            // stop scanner if active
            if (scannerActive && html5QrCode) {
                try { await html5QrCode.stop(); } catch (e) { /* ignore */ }
                scannerActive = false;
                qrReader.classList.add('hidden');
                toggleScannerBtn.textContent = 'Scan with camera';
            }
        }

        toggleScannerBtn?.addEventListener('click', async function () {
            if (!scannerActive) {
                // start scanner
                qrReader.classList.remove('hidden');
                html5QrCode = new Html5Qrcode("qr-reader");
                try {
                    await html5QrCode.start(
                        { facingMode: { exact: "environment" } },
                        { fps: 10, qrbox: 250 },
                        (decodedText) => onDetected(decodedText),
                        (errorMessage) => { /* ignore scan errors */ }
                    );
                    scannerActive = true;
                    toggleScannerBtn.textContent = 'Stop scanner';
                } catch (e) {
                    // fallback to less strict facingMode
                    try {
                        await html5QrCode.start(
                            { facingMode: "environment" },
                            { fps: 10, qrbox: 250 },
                            (decodedText) => onDetected(decodedText)
                        );
                        scannerActive = true;
                        toggleScannerBtn.textContent = 'Stop scanner';
                    } catch (err) {
                        alert('Unable to access camera for scanning.');
                        qrReader.classList.add('hidden');
                    }
                }
            } else {
                // stop scanner
                if (html5QrCode) {
                    try { await html5QrCode.stop(); } catch (e) { /* ignore */ }
                }
                scannerActive = false;
                qrReader.classList.add('hidden');
                toggleScannerBtn.textContent = 'Scan with camera';
            }
        });

        // scan uploaded image file for QR
        document.getElementById('qr-upload')?.addEventListener('change', async function (e) {
            const file = e.target.files && e.target.files[0];
            const preview = document.getElementById('qr-preview');
            const previewImg = document.getElementById('qr-preview-img');
            if (!file) return;

            // preview
            const reader = new FileReader();
            reader.onload = function(ev) {
                previewImg.src = ev.target.result;
                preview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);

            // try to scan using html5-qrcode
            // create a temporary instance if needed
            let scanner = html5QrCode || new Html5Qrcode("qr-reader-temp");
            try {
                const result = await scanner.scanFile(file, true);
                if (result) {
                    onDetected(result?.decodedText || result);
                }
            } catch (err) {
                // couldn't decode
            } finally {
                // if we created a temp scanner, clear it
                if (!html5QrCode) {
                    try { await scanner.clear(); } catch (e) {}
                }
            }
        });

        // Consolidated clear: clears uploaded QR image, preview, stops scanner, and clears manual code
        document.getElementById('clear-qr-top-btn')?.addEventListener('click', async function () {
            const upload = document.getElementById('qr-upload');
            const preview = document.getElementById('qr-preview');
            const previewImg = document.getElementById('qr-preview-img');
            if (upload) {
                try { upload.value = ''; } catch (e) { upload.value = null; }
            }
            if (previewImg) previewImg.src = '';
            if (preview) preview.classList.add('hidden');

            // clear manual code and feedback
            if (assetCodeInput) {
                assetCodeInput.value = '';
                validateAssetCode('');
            }

            // stop active camera scanner
            if (scannerActive && html5QrCode) {
                try { await html5QrCode.stop(); } catch (e) { /* ignore */ }
                scannerActive = false;
                qrReader.classList.add('hidden');
                toggleScannerBtn.textContent = 'Scan with camera';
            }
        });

        // Prevent submit if asset code invalid
        document.querySelector('form')?.addEventListener('submit', function (e) {
            const code = assetCodeInput?.value?.trim();
            if (code && assetValid === false) {
                e.preventDefault();
                alert("The entered asset code doesn't exist or is not assigned to you.");
                assetCodeInput.focus();
            }
        });
        // Preview QR Code
        function previewQRCode(input) {
            const preview = document.getElementById('qr-preview');
            const previewImg = document.getElementById('qr-preview-img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Preview Photo
        function previewPhoto(input) {
            const preview = document.getElementById('photo-preview');
            const previewImg = document.getElementById('photo-preview-img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requestType = document.querySelector('select[name="request_type"]').value;
            const notes = document.querySelector('textarea[name="notes"]').value;
            
            if (!requestType) {
                e.preventDefault();
                alert('Please select a request type');
                return;
            }
            
            if (!notes.trim()) {
                e.preventDefault();
                alert('Please provide reason/notes for your request');
                return;
            }
        });
    </script>
</body>
</html>