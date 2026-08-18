<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Submit Bulk Request - Department Head</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .upload-area { transition: all 0.2s ease; }
        .upload-area:hover { border-color: #3b82f6; background-color: #f9fafb; }
        .submit-btn { transition: all 0.3s ease; }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .asset-chip { animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('department_head.partial.sidebar')

        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-4 sm:px-8 py-4 sm:py-5">
                    <div class="flex items-start sm:items-center">
                        <a href="{{ route('department_head.requests.index') }}" class="text-gray-500 hover:text-gray-700 mr-3 sm:mr-4 mt-1 sm:mt-0 flex-shrink-0">
                            <i class="ri-arrow-left-line text-xl"></i>
                        </a>
                        <div class="min-w-0">
                            <h2 class="text-lg sm:text-2xl font-bold text-gray-900">Submit Request</h2>
                            <p class="text-xs sm:text-sm text-gray-500 mt-1">Submit a new asset request (single or bulk) for your department</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8">
                <form id="bulk-request-form" action="{{ route('department_head.requests.store') }}" method="POST" enctype="multipart/form-data" class="max-w-3xl mx-auto">
                    @csrf

                    @if(session('success'))
                        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                            <div class="flex items-center">
                                <i class="ri-checkbox-circle-line mr-2 text-lg flex-shrink-0"></i>
                                <span class="text-sm">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                            <ul class="list-disc list-inside text-sm">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Request Type -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Request Type <span class="text-red-500">*</span>
                            </label>
                            <select name="request_type" id="request_type" required
                                    class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                                <option value="">Select Request Type</option>
                                <option value="Repair" {{ old('request_type') == 'Repair' ? 'selected' : '' }}>Repair Request</option>
                                <option value="Disposal" {{ old('request_type') == 'Disposal' ? 'selected' : '' }}>Disposal Request</option>
                                <option value="Transfer" {{ old('request_type') == 'Transfer' ? 'selected' : '' }}>Transfer Request</option>
                                <option value="Replacement" {{ old('request_type') == 'Replacement' ? 'selected' : '' }}>Replacement Request</option>
                                <option value="Pullout" {{ old('request_type') == 'Pullout' ? 'selected' : '' }}>Pullout Request</option>
                                <option value="Other" {{ old('request_type') == 'Other' ? 'selected' : '' }}>Other Request</option>
                            </select>
                        </div>

                        <div id="transferAssignBlock" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assign To (new owner)</label>
                            <select name="assign_to_user_id" id="assign_to_user_id"
                                    class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                                <option value="">Select user to assign</option>
                                {{-- You can load users of the department here if needed --}}
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Choose who should become the new owner if this is a transfer.</p>
                        </div>
                    </div>

                    <!-- Asset Selection (Bulk) -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
                        <div class="flex items-center justify-between mb-4 gap-2">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Assets</h3>
                            <span id="asset-count-badge" class="text-xs font-medium bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full whitespace-nowrap">0 selected</span>
                        </div>

                        <div class="mb-4">
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:space-x-3">
                                <button type="button" id="toggle-scanner"
                                        class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm whitespace-nowrap">
                                    <i class="ri-camera-line mr-1"></i>Scan QR
                                </button>

                                <div class="flex-1 relative">
                                    <input id="asset_code_input" type="text"
                                           placeholder="Enter Asset Code (e.g. AST-12345)"
                                           class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition pr-16 sm:pr-24 text-sm">
                                    <button type="button" id="add-asset-btn"
                                            class="absolute right-1 top-1 bottom-1 px-3 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition">
                                        Add
                                    </button>
                                </div>
                            </div>

                            <div id="qr-reader" class="mt-4 hidden"></div>
                            <p id="asset-feedback" class="text-xs mt-2"></p>
                        </div>

                        <!-- Draft Asset List -->
                        <div id="draft-list-container" class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                Selected Assets (Draft)
                            </div>
                            <div id="draft-list" class="divide-y divide-gray-100 max-h-72 overflow-y-auto"></div>
                            <div id="empty-draft" class="px-4 py-8 text-center text-gray-400 text-sm">
                                <i class="ri-inbox-line text-3xl mb-2 block"></i>
                                No assets added yet. Scan a QR code or enter an Asset Code above.
                            </div>
                        </div>

                        <div id="hidden-asset-ids"></div>
                    </div>

                    <!-- Reason / Notes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Reason / Notes / Specific Instructions <span class="text-red-500">*</span>
                        </label>
                        <textarea name="notes" rows="5" required
                                  placeholder="Please describe your concerns, reason for the request, or any specific instructions..."
                                  class="form-textarea w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition text-sm">{{ old('notes') }}</textarea>
                    </div>

                    <!-- Attach Photo -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Attach Photo (Optional)
                        </label>
                        <div class="upload-area border-2 border-dashed border-gray-300 rounded-lg p-4 sm:p-6 text-center cursor-pointer"
                             onclick="document.getElementById('photo-upload').click()">
                            <i class="ri-image-line text-3xl text-gray-400 mb-2 block"></i>
                            <p class="text-sm text-gray-600">Click to upload or drag and drop</p>
                            <p class="text-xs text-gray-400 mt-1">PNG, JPG up to 10MB</p>
                            <input type="file" id="photo-upload" name="attachment" class="hidden" accept="image/*" onchange="previewPhoto(this)">
                        </div>
                        <div id="photo-preview" class="mt-3 hidden">
                            <img id="photo-preview-img" class="h-28 sm:h-32 w-auto rounded-lg border border-gray-200" alt="Photo Preview">
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-4">
                        <button type="button" onclick="window.history.back()"
                                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button id="request-submit" type="submit"
                                class="submit-btn px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <i class="ri-send-plane-line mr-2"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const draftAssets = new Map();

        const assetCodeInput  = document.getElementById('asset_code_input');
        const addAssetBtn     = document.getElementById('add-asset-btn');
        const assetFeedback   = document.getElementById('asset-feedback');
        const draftList       = document.getElementById('draft-list');
        const emptyDraft      = document.getElementById('empty-draft');
        const hiddenContainer = document.getElementById('hidden-asset-ids');
        const countBadge      = document.getElementById('asset-count-badge');
        const submitBtn       = document.getElementById('request-submit');
        const form            = document.getElementById('bulk-request-form');

        function showFeedback(msg, type = 'info') {
            assetFeedback.textContent = msg;
            assetFeedback.className = 'text-xs mt-2 ' + (
                type === 'success' ? 'text-green-600' :
                type === 'error'   ? 'text-red-600'   : 'text-gray-500'
            );
        }

        function updateUI() {
            const count = draftAssets.size;
            countBadge.textContent = count + ' selected';
            countBadge.className = count > 0
                ? 'text-xs font-medium bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full whitespace-nowrap'
                : 'text-xs font-medium bg-gray-100 text-gray-500 px-2.5 py-1 rounded-full whitespace-nowrap';

            emptyDraft.style.display = count === 0 ? 'block' : 'none';
            submitBtn.disabled = count === 0;

            hiddenContainer.innerHTML = '';
            draftAssets.forEach((asset) => {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'asset_ids[]';
                input.value = asset.id;
                hiddenContainer.appendChild(input);
            });

            draftList.innerHTML = '';
            draftAssets.forEach((asset) => {
                const row = document.createElement('div');
                row.className = 'asset-chip flex items-center justify-between px-4 py-3 hover:bg-gray-50';
                row.innerHTML = `
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900 truncate">${escapeHtml(asset.name || 'Unnamed')}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            ${escapeHtml(asset.code)}
                            ${asset.category ? ' · ' + escapeHtml(asset.category) : ''}
                            ${asset.lifecycle_status ? ' · ' + escapeHtml(asset.lifecycle_status) : ''}
                        </div>
                    </div>
                    <button type="button" class="remove-asset ml-3 text-red-500 hover:text-red-700 p-1 rounded"
                            data-id="${asset.id}" title="Remove">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                `;
                draftList.appendChild(row);
            });

            draftList.querySelectorAll('.remove-asset').forEach(btn => {
                btn.addEventListener('click', () => {
                    draftAssets.delete(Number(btn.dataset.id));
                    updateUI();
                });
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        async function addAssetByCode(code) {
            code = (code || '').trim();
            if (!code) {
                showFeedback('Please enter an Asset Code.', 'error');
                return;
            }

            showFeedback('Checking...', 'info');

            try {
                // ← Department Head endpoint
                const url  = '{{ url("/department-head/assets/check-code") }}?code=' + encodeURIComponent(code);
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await resp.json();

                if (!data.exists || !data.asset) {
                    showFeedback("This asset doesn't exist or is outside your department.", 'error');
                    return;
                }

                const asset = data.asset;

                if (draftAssets.has(asset.id)) {
                    showFeedback('This asset is already in the list.', 'error');
                    return;
                }

                draftAssets.set(asset.id, asset);
                updateUI();
                showFeedback(`Added: ${asset.name} (${asset.code})`, 'success');
                assetCodeInput.value = '';
                assetCodeInput.focus();

            } catch (e) {
                showFeedback('Unable to validate asset. Please try again.', 'error');
            }
        }

        addAssetBtn.addEventListener('click', () => addAssetByCode(assetCodeInput.value));
        assetCodeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addAssetByCode(assetCodeInput.value);
            }
        });

        // QR Scanner
        let html5QrCode = null;
        let scannerActive = false;
        const toggleScannerBtn = document.getElementById('toggle-scanner');
        const qrReader = document.getElementById('qr-reader');

        async function onDetected(decodedText) {
            if (!decodedText) return;
            await addAssetByCode(decodedText);
            if (scannerActive && html5QrCode) {
                try { await html5QrCode.stop(); } catch (e) {}
                scannerActive = false;
                qrReader.classList.add('hidden');
                toggleScannerBtn.innerHTML = '<i class="ri-camera-line mr-1"></i>Scan QR';
            }
        }

        toggleScannerBtn.addEventListener('click', async function () {
            if (!scannerActive) {
                qrReader.classList.remove('hidden');
                html5QrCode = new Html5Qrcode("qr-reader");
                try {
                    await html5QrCode.start(
                        { facingMode: { exact: "environment" } },
                        { fps: 10, qrbox: 250 },
                        (decodedText) => onDetected(decodedText),
                        () => {}
                    );
                    scannerActive = true;
                    toggleScannerBtn.innerHTML = '<i class="ri-stop-circle-line mr-1"></i>Stop Scanner';
                } catch (e) {
                    try {
                        await html5QrCode.start(
                            { facingMode: "environment" },
                            { fps: 10, qrbox: 250 },
                            (decodedText) => onDetected(decodedText)
                        );
                        scannerActive = true;
                        toggleScannerBtn.innerHTML = '<i class="ri-stop-circle-line mr-1"></i>Stop Scanner';
                    } catch (err) {
                        alert('Unable to access camera for scanning.');
                        qrReader.classList.add('hidden');
                    }
                }
            } else {
                if (html5QrCode) {
                    try { await html5QrCode.stop(); } catch (e) {}
                }
                scannerActive = false;
                qrReader.classList.add('hidden');
                toggleScannerBtn.innerHTML = '<i class="ri-camera-line mr-1"></i>Scan QR';
            }
        });

        // Transfer block
        const requestTypeSelect = document.getElementById('request_type');
        const transferBlock = document.getElementById('transferAssignBlock');
        function updateTransferBlock() {
            if (requestTypeSelect.value === 'Transfer') {
                transferBlock.classList.remove('hidden');
            } else {
                transferBlock.classList.add('hidden');
                document.getElementById('assign_to_user_id').value = '';
            }
        }
        requestTypeSelect.addEventListener('change', updateTransferBlock);
        updateTransferBlock();

        function previewPhoto(input) {
            const preview = document.getElementById('photo-preview');
            const previewImg = document.getElementById('photo-preview-img');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        form.addEventListener('submit', function (e) {
            if (draftAssets.size === 0) {
                e.preventDefault();
                alert('Please add at least one asset before submitting.');
                return;
            }
            if (!requestTypeSelect.value) {
                e.preventDefault();
                alert('Please select a request type.');
                return;
            }
        });

        updateUI();
    </script>
</body>
</html>