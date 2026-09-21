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
        @include('department_head.partial.sidebar')

        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
            <!-- Header -->
            <div class="topbar sticky top-0 z-10">
                <div class="px-4 sm:px-8 py-4 sm:py-5">
                    <div class="flex items-start sm:items-center">
                        <a href="{{ route('department_head.requests.index') }}" class="text-[#5B6678] hover:text-[#33425C] mr-3 sm:mr-4 mt-1 sm:mt-0 flex-shrink-0">
                            <i class="ri-arrow-left-line text-xl"></i>
                        </a>
                        <div class="min-w-0">
                            <h2 class="brand-title text-lg sm:text-2xl font-semibold text-[#0F2143]">Submit Request</h2>
                            <p class="text-xs sm:text-sm text-[#5B6678] mt-1">Submit a new asset request (single or bulk) for your department</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8">
                <form id="bulk-request-form" action="{{ route('department_head.requests.store') }}" method="POST" enctype="multipart/form-data" class="max-w-3xl mx-auto">
                    @csrf

                    @if(session('success'))
                        <div class="mb-6 rounded-lg border border-[#CFE3D4] bg-[#EAF4EE] px-4 py-3 text-[#1D4A2E]">
                            <div class="flex items-center">
                                <i class="ri-checkbox-circle-line mr-2 text-lg flex-shrink-0"></i>
                                <span class="text-sm">{{ session('success') }}</span>
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
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-4 sm:p-6 mb-6">
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
                            </select>
                        </div>

                        <div id="transferAssignBlock" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-[#33425C] mb-2">Assign To (new owner)</label>
                            <select name="assign_to_user_id" id="assign_to_user_id"
                                    class="form-select w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition">
                                <option value="">Select user to assign</option>
                                {{-- You can load users of the department here if needed --}}
                            </select>
                            <p class="text-xs text-[#8991A0] mt-1">Choose who should become the new owner if this is a transfer.</p>
                        </div>
                    </div>

                    <!-- Asset Selection (Bulk) -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-4 sm:p-6 mb-6">
                        <div class="flex items-center justify-between mb-4 gap-2">
                            <h3 class="text-base sm:text-lg font-semibold text-[#0F2143]">Assets</h3>
                            <span id="asset-count-badge" class="text-xs font-medium bg-[#F3E7C4] text-[#0F2143] px-2.5 py-1 rounded-full whitespace-nowrap">0 selected</span>
                        </div>

                        <div class="mb-4">
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:space-x-3">
                                <button type="button" id="toggle-scanner"
                                        class="px-3 py-2 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition text-sm whitespace-nowrap">
                                    <i class="ri-camera-line mr-1"></i>Scan QR
                                </button>

                                <div class="flex-1 relative">
                                    <input id="asset_code_input" type="text"
                                           placeholder="Enter Asset Code (e.g. AST-12345)"
                                           class="form-input w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition pr-16 sm:pr-24 text-sm">
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
                            <div id="draft-list" class="divide-y divide-[#EFE9D8] max-h-72 overflow-y-auto"></div>
                            <div id="empty-draft" class="px-4 py-8 text-center text-[#8991A0] text-sm">
                                <i class="ri-inbox-line text-3xl mb-2 block"></i>
                                No assets added yet. Scan a QR code or enter an Asset Code above.
                            </div>
                        </div>

                        <div id="hidden-asset-ids"></div>
                    </div>

                    <!-- Reason / Notes -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-4 sm:p-6 mb-6">
                        <label class="block text-sm font-medium text-[#33425C] mb-2">
                            Reason / Notes / Specific Instructions <span class="text-[#A23B32]">*</span>
                        </label>
                        <textarea name="notes" id="notes-input" rows="5" required maxlength="500"
                                  placeholder="Please describe your concerns, reason for the request, or any specific instructions..."
                                  class="form-textarea w-full px-4 py-2 border border-[#CFC4A4] rounded-lg focus:border-[#C9A227] transition text-sm">{{ old('notes') }}</textarea>
                        <div class="flex items-center justify-between gap-3 mt-2">
                            <p class="text-xs text-[#8991A0]">
                                Explain the problem, reason or any specific instructions. Maximum 500 characters.
                            </p>
                            <span id="notes-counter" class="text-xs font-medium text-[#8991A0] whitespace-nowrap">0 / 500 characters</span>
                        </div>
                    </div>

                    <!-- Attach Photo -->
                    <div class="bg-white rounded-xl shadow-sm border border-[#DED2AE] p-4 sm:p-6 mb-8">
                        <label class="block text-sm font-medium text-[#33425C] mb-2">
                            Attach Photo (Optional)
                        </label>
                        <div class="upload-area border-2 border-dashed border-[#CFC4A4] rounded-lg p-4 sm:p-6 text-center cursor-pointer"
                             onclick="document.getElementById('photo-upload').click()">
                            <i class="ri-image-line text-3xl text-[#8991A0] mb-2 block"></i>
                            <p class="text-sm text-[#46536B]">Click to upload or drag and drop</p>
                            <p class="text-xs text-[#8991A0] mt-1">PNG, JPG up to 10MB</p>
                            <input type="file" id="photo-upload" name="attachment" class="hidden" accept="image/*" onchange="previewPhoto(this)">
                        </div>
                        <div id="photo-preview" class="mt-3 hidden">
                            <img id="photo-preview-img" class="h-28 sm:h-32 w-auto rounded-lg border border-[#DED2AE]" alt="Photo Preview">
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-4">
                        <button type="button" onclick="window.history.back()"
                                class="px-6 py-2 border border-[#CFC4A4] rounded-lg text-[#33425C] hover:bg-[#F5F0E2] transition">
                            Cancel
                        </button>
                        <button id="request-submit" type="submit"
                                class="submit-btn px-6 py-2 bg-[#C9A227] text-[#0A1830] rounded-lg hover:bg-[#E0BC44] transition flex items-center justify-center shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
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
                type === 'success' ? 'text-[#2F7A4D]' :
                type === 'error'   ? 'text-[#A23B32]'   : 'text-[#5B6678]'
            );
        }

        function updateUI() {
            const count = draftAssets.size;
            countBadge.textContent = count + ' selected';
            countBadge.className = count > 0
                ? 'text-xs font-medium bg-[#F3E7C4] text-[#0F2143] px-2.5 py-1 rounded-full whitespace-nowrap'
                : 'text-xs font-medium bg-[#EFE9D8] text-[#5B6678] px-2.5 py-1 rounded-full whitespace-nowrap';

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

        // Request note counter (max 500 characters)
        const notesInput   = document.getElementById('notes-input');
        const notesCounter = document.getElementById('notes-counter');
        const NOTES_MAX    = 500;

        function updateNotesCounter() {
            if (!notesInput || !notesCounter) return;
            const length = notesInput.value.length;
            notesCounter.textContent = `${length} / ${NOTES_MAX} characters`;
            notesCounter.classList.toggle('text-[#A23B32]', length >= NOTES_MAX);
            notesCounter.classList.toggle('text-[#8991A0]', length < NOTES_MAX);
        }

        if (notesInput) {
            notesInput.addEventListener('input', updateNotesCounter);
            updateNotesCounter();
        }

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

            if (notesInput && notesInput.value.trim().length > NOTES_MAX) {
                e.preventDefault();
                alert(`Your request note cannot exceed ${NOTES_MAX} characters.`);
                return;
            }
        });

        updateUI();
    </script>
</body>
</html>