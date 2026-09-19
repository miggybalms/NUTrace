<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Submit Bulk Request - Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; }
        .sidebar-item {
            border-left: 3px solid transparent; transition: all 0.2s ease; cursor: pointer; }
        .sidebar-item:hover { background-color: rgba(255, 255, 255, 0.05); color: #F3EFE3; }
        .sidebar-item.active { background-color: rgba(201, 162, 39, 0.10); color: #E9C766; border-left-color: #C9A227; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none; border-color: #C9A227; box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.18);
        }
        .upload-area { transition: all 0.2s ease; }
        .upload-area:hover { border-color: #C9A227; background-color: #F5F0E2; }
        .submit-btn { transition: all 0.3s ease; }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(201, 162, 39, 0.3); }
        .asset-chip { animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body{ background:#F3EEE0 !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',sans-serif !important; color:#1A2233; }
        .brand-title{ font-family:'Fraunces',Georgia,serif; }
        .topbar{ background:#fff; border-bottom:1px solid #DED2AE; position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, #C9A227 20%, #C9A227 80%, transparent); opacity:.7; }
    </style>
</head>
<body class="bg-[#F3EEE0]">
    <div class="flex h-screen overflow-hidden">
        @include('users.partials.sidebar')

        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
            <!-- Header -->
            <div class="topbar sticky top-0 z-10">
                <div class="px-8 py-5">
                    <div class="flex items-center">
                        <a href="{{ route('user.requests.index') }}" class="text-[#5B6678] hover:text-[#33425C] mr-4">
                            <i class="ri-arrow-left-line text-xl"></i>
                        </a>
                        <div>
                            <h2 class="brand-title text-2xl font-semibold text-[#0F2143]">Submit Request</h2>
                            <p class="text-sm text-[#5B6678] mt-1">Submit a new asset request (single or bulk)</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <form id="bulk-request-form" action="{{ route('user.requests.store') }}" method="POST" enctype="multipart/form-data" class="max-w-3xl mx-auto">
                    @csrf

                    @if(session('success'))
                        <div class="mb-6 rounded-lg border border-[#CFE3D4] bg-[#EAF4EE] px-4 py-3 text-[#1D4A2E]">
                            <div class="flex items-center">
                                <i class="ri-checkbox-circle-line mr-2 text-lg"></i>
                                <span>{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-6 rounded-lg border border-[#E8CCC6] bg-[#F7E9E6] px-4 py-3 text-[#7E2E27]">
                            <ul class="list-disc list-inside text-sm">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Request Type -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-6 mb-6">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-[#33425C] mb-2">
                                Request Type <span class="text-[#A23B32]">*</span>
                            </label>
                            <select name="request_type" id="request_type" required
                                    class="form-select w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition">
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
                            <label class="block text-sm font-medium text-[#33425C] mb-2">Assign To (new owner)</label>
                            <select name="assign_to_user_id" id="assign_to_user_id"
                                    class="form-select w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition">
                                <option value="">Select user to assign</option>
                                @foreach($users ?? [] as $u)
                                    <option value="{{ $u->id }}">{{ $u->Full_Name }} @if(!empty($u->department)) — {{ $u->department }} @endif</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-[#8991A0] mt-1">Choose who should become the new owner if this is a transfer.</p>
                        </div>
                    </div>

                    <!-- Asset Selection (Bulk) -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-[#0F2143]">Assets</h3>
                            <span id="asset-count-badge" class="text-xs font-medium bg-[#F3E7C4] text-[#0F2143] px-2.5 py-1 rounded-full">0 selected</span>
                        </div>

                        <!-- Scanner + Manual Entry -->
                        <div class="mb-4">
                            <div class="flex items-center space-x-3">
                                <button type="button" id="toggle-scanner"
                                        class="px-3 py-2 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition text-sm">
                                    <i class="ri-camera-line mr-1"></i>Scan QR
                                </button>

                                <div class="flex-1 relative">
                                    <input id="asset_code_input" type="text"
                                           placeholder="Enter Asset Code (e.g. AST-12345) then press Enter or click Add"
                                           class="form-input w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition pr-24">
                                    <button type="button" id="add-asset-btn"
                                            class="absolute right-1 top-1 bottom-1 px-3 bg-[#C9A227] text-[#0A1830] text-sm rounded-md hover:bg-[#E0BC44] transition">
                                        Add
                                    </button>
                                </div>
                            </div>

                            <div id="qr-reader" class="mt-4 hidden"></div>
                            <p id="asset-feedback" class="text-xs mt-2"></p>
                        </div>

                        <!-- Draft Asset List -->
                        <div id="draft-list-container" class="border border-[#DED2AE] rounded-lg overflow-hidden">
                            <div class="bg-[#F5F0E2] px-4 py-2 border-b border-[#DED2AE] text-xs font-medium text-[#5B6678] uppercase tracking-wide">
                                Selected Assets (Draft)
                            </div>
                            <div id="draft-list" class="divide-y divide-[#EFE9D8] max-h-72 overflow-y-auto">
                                <!-- filled by JS -->
                            </div>
                            <div id="empty-draft" class="px-4 py-8 text-center text-[#8991A0] text-sm">
                                <i class="ri-inbox-line text-3xl mb-2 block"></i>
                                No assets added yet. Scan a QR code or enter an Asset Code above.
                            </div>
                        </div>

                        <!-- Hidden inputs that will be submitted -->
                        <div id="hidden-asset-ids"></div>
                    </div>

                    <!-- Reason / Notes -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-6 mb-6">
                        <label class="block text-sm font-medium text-[#33425C] mb-2">
                            Reason / Notes / Specific Instructions <span class="text-[#A23B32]">*</span>
                        </label>
                        <textarea name="notes" rows="5" required
                                  placeholder="Please describe your concerns, reason for the request, or any specific instructions..."
                                  class="form-textarea w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition">{{ old('notes') }}</textarea>
                    </div>

                    <!-- Attach Photo -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-6 mb-8">
                        <label class="block text-sm font-medium text-[#33425C] mb-2">
                            Attach Photo (Optional)
                        </label>
                        <div class="upload-area border-2 border-dashed border-[#CFC4A4] rounded-lg p-6 text-center cursor-pointer"
                             onclick="document.getElementById('photo-upload').click()">
                            <i class="ri-image-line text-3xl text-[#8991A0] mb-2 block"></i>
                            <p class="text-sm text-[#46536B]">Click to upload or drag and drop</p>
                            <p class="text-xs text-[#8991A0] mt-1">PNG, JPG up to 10MB</p>
                            <input type="file" id="photo-upload" name="attachment" class="hidden" accept="image/*" onchange="previewPhoto(this)">
                        </div>
                        <div id="photo-preview" class="mt-3 hidden">
                            <img id="photo-preview-img" class="h-32 w-auto rounded-lg border border-[#DED2AE]" alt="Photo Preview">
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-4">
                        <button type="button" onclick="window.history.back()"
                                class="px-6 py-2 border border-[#CFC4A4] rounded-lg text-[#33425C] hover:bg-[#F5F0E2] transition">
                            Cancel
                        </button>
                        <button id="request-submit" type="submit"
                                class="submit-btn px-6 py-2 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition flex items-center shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <i class="ri-send-plane-line mr-2"></i>
                            Submit Request
                        </button>
                    </div>
                </form>

                <div class="text-center text-sm text-[#5B6678] mt-8 pt-6 border-t border-[#DED2AE]">
                    © 2026 University Asset Management
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        // ─────────────────────────────────────────────
        // State
        // ─────────────────────────────────────────────
        const draftAssets = new Map(); // key = asset.id, value = asset object

        const assetCodeInput   = document.getElementById('asset_code_input');
        const addAssetBtn      = document.getElementById('add-asset-btn');
        const assetFeedback    = document.getElementById('asset-feedback');
        const draftList        = document.getElementById('draft-list');
        const emptyDraft       = document.getElementById('empty-draft');
        const hiddenContainer  = document.getElementById('hidden-asset-ids');
        const countBadge       = document.getElementById('asset-count-badge');
        const submitBtn        = document.getElementById('request-submit');
        const form             = document.getElementById('bulk-request-form');

        // ─────────────────────────────────────────────
        // Helpers
        // ─────────────────────────────────────────────
        function showFeedback(msg, type = 'info') {
            assetFeedback.textContent = msg;
            assetFeedback.className = 'text-xs mt-2 ' + (
                type === 'success' ? 'text-[#2F7A4D]' :
                type === 'error'   ? 'text-[#A23B32]'   :
                                     'text-[#5B6678]'
            );
        }

        function updateUI() {
            const count = draftAssets.size;

            // Badge
            countBadge.textContent = count + ' selected';
            countBadge.className = count > 0
                ? 'text-xs font-medium bg-[#F3E7C4] text-[#0F2143] px-2.5 py-1 rounded-full'
                : 'text-xs font-medium bg-[#EFE9D8] text-[#5B6678] px-2.5 py-1 rounded-full';

            // Empty state
            emptyDraft.style.display = count === 0 ? 'block' : 'none';

            // Submit button
            submitBtn.disabled = count === 0;

            // Rebuild hidden inputs
            hiddenContainer.innerHTML = '';
            draftAssets.forEach((asset) => {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'asset_ids[]';
                input.value = asset.id;
                hiddenContainer.appendChild(input);
            });

            // Rebuild visual list
            draftList.innerHTML = '';
            draftAssets.forEach((asset) => {
                const row = document.createElement('div');
                row.className = 'asset-chip flex items-center justify-between px-4 py-3 hover:bg-[#F5F0E2]';
                row.innerHTML = `
                    <div class="min-w-0">
                        <div class="font-medium text-[#0F2143] truncate">${escapeHtml(asset.name || 'Unnamed')}</div>
                        <div class="text-xs text-[#5B6678] mt-0.5">
                            ${escapeHtml(asset.code)}
                            ${asset.category ? ' · ' + escapeHtml(asset.category) : ''}
                            ${asset.lifecycle_status ? ' · ' + escapeHtml(asset.lifecycle_status) : ''}
                        </div>
                    </div>
                    <button type="button" class="remove-asset ml-3 text-[#A23B32] hover:text-[#7E2E27] p-1 rounded"
                            data-id="${asset.id}" title="Remove">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                `;
                draftList.appendChild(row);
            });

            // Attach remove listeners
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

        // ─────────────────────────────────────────────
        // Add asset by code
        // ─────────────────────────────────────────────
        async function addAssetByCode(code) {
            code = (code || '').trim();
            if (!code) {
                showFeedback('Please enter an Asset Code.', 'error');
                return;
            }

            showFeedback('Checking...', 'info');

            try {
                const url  = '{{ url("/user/assets/check-code") }}?code=' + encodeURIComponent(code);
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await resp.json();

                if (!data.exists || !data.asset) {
                    showFeedback("This asset doesn't exist or isn't assigned to you.", 'error');
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

        // Button + Enter key
        addAssetBtn.addEventListener('click', () => addAssetByCode(assetCodeInput.value));
        assetCodeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addAssetByCode(assetCodeInput.value);
            }
        });

        // ─────────────────────────────────────────────
        // QR Scanner
        // ─────────────────────────────────────────────
        let html5QrCode = null;
        let scannerActive = false;
        const toggleScannerBtn = document.getElementById('toggle-scanner');
        const qrReader = document.getElementById('qr-reader');

        async function onDetected(decodedText) {
            if (!decodedText) return;
            await addAssetByCode(decodedText);

            // stop scanner after successful add
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

        // ─────────────────────────────────────────────
        // Transfer block toggle
        // ─────────────────────────────────────────────
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

        // ─────────────────────────────────────────────
        // Photo preview
        // ─────────────────────────────────────────────
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

        // ─────────────────────────────────────────────
        // Form submit guard
        // ─────────────────────────────────────────────
        form.addEventListener('submit', function (e) {
            if (draftAssets.size === 0) {
                e.preventDefault();
                alert('Please add at least one asset before submitting.');
                return;
            }

            const requestType = requestTypeSelect.value;
            if (!requestType) {
                e.preventDefault();
                alert('Please select a request type.');
                return;
            }

            if (requestType === 'Transfer' && !document.getElementById('assign_to_user_id').value) {
                e.preventDefault();
                alert('Please select the new owner for the Transfer request.');
                return;
            }
        });

        // Initial UI
        updateUI();
    </script>
</body>
</html>