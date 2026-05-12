<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asset Registry - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Regenerate Button Animations */
        .regenerate-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .regenerate-btn:hover {
            background-color: #3b82f6;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .regenerate-btn:hover i {
            animation: spin 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .regenerate-btn:active {
            transform: translateY(0px);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* QR Code Styles */
        .qr-container {
            transition: all 0.3s ease;
        }
        
        .qr-container:hover {
            transform: scale(1.05);
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
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
        }
        
        .asset-id-display {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        }
        
        .asset-id-display.updated {
            transform: scale(1.02);
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            border-color: #3b82f6;
        }
        
        .condition-badge {
            transition: all 0.2s ease;
        }
        
        .condition-badge:hover {
            transform: scale(1.05);
        }
        
        .search-results {
            max-height: 200px;
            overflow-y: auto;
            scrollbar-width: thin;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <a href="javascript:void(0)" onclick="window.history.back()" class="text-gray-500 hover:text-gray-700 mr-4 transition-transform hover:translate-x-[-2px]">
                                <i class="ri-arrow-left-line text-xl"></i>
                            </a>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Asset Registry</h2>
                                <p class="text-sm text-gray-500 mt-1">Register new assets to the inventory</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-all hover:scale-105 flex items-center">
                                <i class="ri-question-line mr-2"></i>
                                Help
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="p-8">
                <form action="{{ route('admin.assets.store') }}" method="POST" enctype="multipart/form-data" class="max-w-4xl mx-auto" id="assetForm">
                    @csrf
                    <input type="hidden" name="qr_image" id="qr_image_input" value="">
                    @if(session('success'))
                        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-100 text-green-700">
                            {{ session('success') }}
                        </div>
                    @endif
                    


                    <!-- Basic Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Asset Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Asset Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="asset-name" required
                                       placeholder="e.g., Dell Laptop i7-12th Gen"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"
                                       onchange="updateQRCode()">
                            </div>

                            <!-- Asset Category -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Category <span class="text-red-500">*</span>
                                </label>
                                <select name="category" id="asset-category" required class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition" onchange="updateQRCode()">
                                    <option value="">Select category</option>
                                       <option value="Furnitures and Fixtures">Furnitures and Fixtures</option>
                                       <option value="General and Office Equipment">General and Office Equipment</option>
                                       <option value="Info and Equipment">Info and Equipment</option>
                                       <option value="laboratory Apparatus and equipment">laboratory Apparatus and equipment</option>
                                       <option value="library books">library books</option>
                                       <option value="Motor vehicles">Motor vehicles</option>
                                       <option value="P.E Equipment">P.E Equipment</option>
                                       <option value="Low value Asset">Low value Asset</option>
                                </select>
                            </div>

                            <!-- Condition -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Condition <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-4 gap-3 mb-4">
                                    <label class="condition-badge cursor-pointer">
                                        <input type="radio" name="condition" value="new" class="hidden peer" onchange="updateQRCode()">
                                        <div class="border-2 border-gray-200 rounded-lg p-3 text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all hover:shadow-md">
                                            <i class="ri-sparkling-line text-xl text-green-600 mb-1 block"></i>
                                            <span class="text-sm font-medium">New</span>
                                        </div>
                                    </label>
                                    <label class="condition-badge cursor-pointer">
                                        <input type="radio" name="condition" value="good" class="hidden peer" onchange="updateQRCode()">
                                        <div class="border-2 border-gray-200 rounded-lg p-3 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all hover:shadow-md">
                                            <i class="ri-checkbox-circle-line text-xl text-blue-600 mb-1 block"></i>
                                            <span class="text-sm font-medium">Good</span>
                                        </div>
                                    </label>
                                    <label class="condition-badge cursor-pointer">
                                        <input type="radio" name="condition" value="fair" class="hidden peer" onchange="updateQRCode()">
                                        <div class="border-2 border-gray-200 rounded-lg p-3 text-center peer-checked:border-yellow-500 peer-checked:bg-yellow-50 transition-all hover:shadow-md">
                                            <i class="ri-alert-line text-xl text-yellow-600 mb-1 block"></i>
                                            <span class="text-sm font-medium">Fair</span>
                                        </div>
                                    </label>
                                    <label class="condition-badge cursor-pointer">
                                        <input type="radio" name="condition" value="poor" class="hidden peer" onchange="updateQRCode()">
                                        <div class="border-2 border-gray-200 rounded-lg p-3 text-center peer-checked:border-red-500 peer-checked:bg-red-50 transition-all hover:shadow-md">
                                            <i class="ri-error-warning-line text-xl text-red-600 mb-1 block"></i>
                                            <span class="text-sm font-medium">Poor</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Assignment & Location -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Assign to (Name/Department)
                                </label>
                                <div class="relative">
                                    <input type="text" id="user-search" 
                                           placeholder="Type to search users (name or dept)"
                                           class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"
                                           autocomplete="off">
                                    <div id="search-results" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg search-results"></div>
                                </div>
                                <input type="hidden" name="assigned_to" id="assigned-to">
                                <div id="selected-user" class="mt-2 hidden">
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                                <i class="ri-user-line text-white text-sm"></i>
                                            </div>
                                            <div class="ml-2">
                                                <p class="text-sm font-medium text-gray-900" id="selected-user-name"></p>
                                                <p class="text-xs text-gray-500" id="selected-user-dept"></p>
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearSelectedUser()" class="text-red-500 hover:text-red-600 transition-colors">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Location -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Location
                                </label>
                                <input type="text" name="location" id="asset-location"
                                       placeholder="e.g., Room 301, Engineering Building"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"
                                       onchange="updateQRCode()">
                            </div>
                        </div>
                    </div>

                    <!-- Acquisition Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acquisition Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Date Acquired <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="acquisition_date" required
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Purchase Price
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">₱</span>
                                    <input type="number" name="purchase_price" step="0.01"
                                           placeholder="0.00"
                                           class="form-input w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Warranty (months)
                                </label>
                                <input type="number" name="warranty_months" value="12"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h3>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Serial Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Serial Number
                                </label>
                                <input type="text" name="serial_number" 
                                       placeholder="Enter serial number"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition">
                            </div>

                            <!-- Asset Photo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Asset Photo
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-500 transition-all hover:bg-gray-50 photo-upload cursor-pointer">
                                    <div class="space-y-1 text-center">
                                        <i class="ri-image-line text-3xl text-gray-400 mb-2 block"></i>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="asset-photo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Upload a file</span>
                                                <input id="asset-photo" name="asset_photo" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                                <div id="photo-preview" class="mt-3 hidden flex items-start space-x-3">
                                    <img id="preview-img" class="h-32 w-auto rounded-lg border border-gray-200" alt="Preview">
                                    <div class="flex items-center">
                                        <button type="button" onclick="removePreview()" class="px-3 py-1 bg-red-50 text-red-600 rounded-lg border border-red-100 hover:bg-red-100">Remove</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Notes
                                </label>
                                <textarea name="notes" rows="4"
                                          placeholder="Additional notes or remarks..."
                                          class="form-textarea w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Lifespan & Maintenance -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Lifespan & Maintenance</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Lifespan Months -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Lifespan (months)
                                </label>
                                <input type="number" name="lifespan_months" id="lifespan-months"
                                       placeholder="e.g., 60"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"
                                       onchange="calculateExpirationDate()">
                                <p class="text-xs text-gray-500 mt-1">Asset will expire after this many months</p>
                            </div>

                            <!-- Expiration Date (Auto-Calculated) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Expiration Date (Auto-Calculated)
                                </label>
                                <input type="date" name="expiration_date" id="expiration-date"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                                       readonly>
                                <p class="text-xs text-gray-500 mt-1">Auto-calculated: Acquisition Date + Lifespan</p>
                            </div>

                            <!-- Last Maintenance Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Last Maintenance Date (Optional)
                                </label>
                                <input type="date" name="last_maintenance_date" id="last-maintenance-date"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"
                                       onchange="calculateNextMaintenanceDate()">
                                <p class="text-xs text-gray-500 mt-1">If left empty, next maintenance will be calculated from registration date</p>
                            </div>

                            <!-- Maintenance Interval (months) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Maintenance Interval (months)
                                </label>
                                <input type="number" name="maintenance_interval" id="maintenance-interval"
                                       placeholder="e.g., 6"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-blue-500 transition"
                                       onchange="calculateNextMaintenanceDate()">
                                <p class="text-xs text-gray-500 mt-1">How often should maintenance be done?</p>
                            </div>

                            <!-- Next Maintenance Date (Auto-Calculated) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Next Maintenance Date (Auto-Calculated)
                                </label>
                                <input type="date" name="next_maintenance_date" id="next-maintenance-date"
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                                       readonly>
                                <p class="text-xs text-gray-500 mt-1">Auto-calculated: Last Maintenance (or Registration Date) + Interval</p>
                            </div>
                        </div>
                    </div>

                                        <!-- Auto-Generated Asset ID with QR Code -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Auto-Generated Asset ID</h3>
                                <p class="text-sm text-gray-500 mt-1">Unique identifier for this asset</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left side - Asset ID -->
                            <div>
                                <div class="flex items-center space-x-4">
                                    <div class="flex-1">
                                        <div class="asset-id-display bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-lg px-4 py-3 transition-all duration-300">
                                            <code class="text-lg font-mono font-semibold text-gray-900" id="asset-id-display">Not generated</code>
                                        </div>
                                    </div>
                                    <button type="button" id="regenerate-btn" onclick="regenerateAssetId()" 
                                            class="regenerate-btn px-5 py-3 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all duration-300 flex items-center font-medium group"
                                            disabled>
                                        <i class="ri-refresh-line mr-2 text-lg transition-transform duration-300 group-hover:rotate-180"></i>
                                        <span>Regenerate ID</span>
                                    </button>
                                </div>
                                <input type="hidden" name="asset_code" id="asset-code-input" value="">
                            </div>
                            
                            <!-- Right side - QR Code -->
                            <div class="border-l border-gray-200 pl-6">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-sm font-medium text-gray-700">Asset QR Code</label>
                                    <button type="button" onclick="viewQRCode()" 
                                            class="text-blue-600 hover:text-blue-700 text-sm flex items-center">
                                        <i class="ri-eye-line mr-1"></i>
                                        View Full Size
                                    </button>
                                </div>
                                <div class="qr-container bg-white border border-gray-200 rounded-lg p-3 inline-block">
                                    <div id="qrcode" class="flex justify-center"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Scan to view asset details</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4">
                        <button type="button" onclick="window.history.back()" 
                                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-all hover:scale-105">
                            Cancel
                        </button>
                        <button type="submit" id="register-btn"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all hover:scale-105 flex items-center shadow-md hover:shadow-lg"
                                disabled>
                            <i class="ri-add-line mr-2"></i>
                            Register Asset
                        </button>
                    </div>
                </form>

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900">Asset QR Code</h3>
                <button onclick="closeQRModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="flex justify-center mb-4">
                <div id="modal-qrcode" class="p-4 bg-white rounded-lg"></div>
            </div>
            <div class="text-center mb-4">
                <p class="text-sm text-gray-600 font-mono" id="modal-asset-id"></p>
                <p class="text-xs text-gray-500 mt-1">Scan this QR code to view asset details</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="downloadQRCode()" class="flex-1 download-btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all flex items-center justify-center">
                    <i class="ri-download-line mr-2"></i>
                    Download QR Code
                </button>
                <button onclick="printQRCode()" class="flex-1 download-btn bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-all flex items-center justify-center">
                    <i class="ri-printer-line mr-2"></i>
                    Print
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        let qrcodeInstance = null;
        let modalQRCodeInstance = null;
        
        // Initialize QR Code on page load and wire form inputs to regenerate
        document.addEventListener('DOMContentLoaded', function() {
            generateQRCode();

            // regenerate when important fields change (including asset code)
            ['asset-code-input', 'asset-name', 'asset-category', 'asset-location', 'acquisition_date'].forEach(id => {
                const el = document.getElementById(id) || document.querySelector(`[name="${id}"]`);
                if (el) el.addEventListener('input', generateQRCode);
            });

            // Also update when condition/radio or other selects change
            document.querySelectorAll('select, input[type=text], input[type=date], input[type=radio]').forEach(el => el.addEventListener('change', () => { generateQRCode(); checkFormReadiness(); }));

            // Setup button references
            window._regenerateBtn = document.getElementById('regenerate-btn');
            window._registerBtn = document.getElementById('register-btn');

            // Initial readiness check
            checkFormReadiness();
        });
        
        function generateQRCode() {
            const assetId = document.getElementById('asset-code-input').value;
            const assetName = document.getElementById('asset-name')?.value || 'New Asset';
            const category = document.getElementById('asset-category')?.value || 'Uncategorized';
            const location = document.getElementById('asset-location')?.value || 'Not specified';
            
            // Use only the asset code as QR payload
            const qrData = assetId || '';

            // Clear previous QR code
            const qrcodeDiv = document.getElementById('qrcode');
            qrcodeDiv.innerHTML = '';

            if (!qrData) {
                qrcodeInstance = null;
                // ensure register button is disabled when no QR
                if (window._registerBtn) window._registerBtn.disabled = true;
                return;
            }

            // Generate new QR code (payload = asset code string)
            qrcodeInstance = new QRCode(qrcodeDiv, {
                text: qrData,
                width: 120,
                height: 120,
                colorDark: "#1f2937",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            // after generating QR, enable register button
            setTimeout(() => {
                const qrCanvas = document.querySelector('#qrcode canvas');
                if (qrCanvas && window._registerBtn) {
                    window._registerBtn.disabled = false;
                    // also store the QR image into the hidden input so server receives it
                    try {
                        const dataUrl = qrCanvas.toDataURL('image/png');
                        const qrInput = document.getElementById('qr_image_input');
                        if (qrInput) qrInput.value = dataUrl;
                    } catch (err) {
                        // ignore canvas errors
                    }
                }
            }, 100);
        }
        
        function updateQRCode() {
            generateQRCode();
        }
        
        function regenerateAssetId() {
            const newId = generateAssetIdString();
            const displayElement = document.getElementById('asset-id-display');
            const inputElement = document.getElementById('asset-code-input');
            const containerElement = displayElement.parentElement;
            
            // Add animation to the ID container
            containerElement.classList.add('updated');
            
            // Update the ID with fade effect
            displayElement.style.opacity = '0';
            setTimeout(() => {
                displayElement.textContent = newId;
                inputElement.value = newId;
                displayElement.style.opacity = '1';
                generateQRCode(); // Regenerate QR code with new ID
                // enable register button now that qr exists
                const qrCanvas = document.querySelector('#qrcode canvas');
                if (qrCanvas && window._registerBtn) {
                    window._registerBtn.disabled = false;
                }
            }, 150);
            
            // Remove the highlight after animation
            setTimeout(() => {
                containerElement.classList.remove('updated');
            }, 300);
        }
        
        function generateAssetIdString() {
            const prefix = 'AST';
            const random1 = Math.random().toString(36).substring(2, 9).toUpperCase();
            const random2 = Math.random().toString(36).substring(2, 6).toUpperCase();
            return `${prefix}-${random1}-${random2}`;
        }

        // Check if required fields are filled to enable regenerate button
        function checkFormReadiness() {
            const name = document.getElementById('asset-name')?.value?.trim();
            const category = document.getElementById('asset-category')?.value?.trim();
            const acquisition = document.querySelector('input[name="acquisition_date"]')?.value?.trim();
            const condition = document.querySelector('input[name="condition"]:checked');

            const ready = name && category && acquisition && condition;
            if (window._regenerateBtn) {
                window._regenerateBtn.disabled = !ready;
                window._regenerateBtn.classList.toggle('opacity-50', !ready);
            }
            return !!ready;
        }
        
        function viewQRCode() {
            const assetId = document.getElementById('asset-code-input').value || '';

            // If no asset id, do not open modal
            if (!assetId) {
                alert('Please generate or set an Asset ID before viewing the QR code.');
                return;
            }

            const modalQRDiv = document.getElementById('modal-qrcode');
            modalQRDiv.innerHTML = '';

            // Modal QR encodes only the asset code string
            modalQRCodeInstance = new QRCode(modalQRDiv, {
                text: assetId,
                width: 200,
                height: 200,
                colorDark: "#1f2937",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            document.getElementById('modal-asset-id').textContent = assetId;
            document.getElementById('qrModal').classList.remove('hidden');
            document.getElementById('qrModal').classList.add('flex');
        }
        
        function closeQRModal() {
            document.getElementById('qrModal').classList.add('hidden');
            document.getElementById('qrModal').classList.remove('flex');
        }
        
        function downloadQRCode() {
            const qrCanvas = document.querySelector('#modal-qrcode canvas');
            if (qrCanvas) {
                const link = document.createElement('a');
                link.download = `qr-${document.getElementById('asset-code-input').value}.png`;
                link.href = qrCanvas.toDataURL();
                link.click();
            }
        }
        
        function printQRCode() {
            const qrCanvas = document.querySelector('#modal-qrcode canvas');
            if (qrCanvas) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print QR Code</title>
                            <style>
                                body {
                                    display: flex;
                                    justify-content: center;
                                    align-items: center;
                                    height: 100vh;
                                    margin: 0;
                                    font-family: Arial, sans-serif;
                                }
                                .container {
                                    text-align: center;
                                }
                                img {
                                    max-width: 300px;
                                }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <h2>Asset QR Code</h2>
                                <img src="${qrCanvas.toDataURL()}" />
                                <p>Asset ID: ${document.getElementById('asset-code-input').value}</p>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
        
        // User search using server-side endpoint (returns real DB users)
        const searchInput = document.getElementById('user-search');
        const searchResults = document.getElementById('search-results');
        let searchTimer = null;

        async function fetchUsers(query) {
            if (!query) return [];
            try {
                const res = await fetch(`/admin/users/search?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return [];
                return await res.json();
            } catch (e) {
                return [];
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    return;
                }

                clearTimeout(searchTimer);
                searchTimer = setTimeout(async () => {
                    const results = await fetchUsers(query);
                    searchResults.innerHTML = '';
                    if (results.length > 0) {
                        results.forEach(user => {
                            const el = document.createElement('div');
                            el.className = 'px-4 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors';
                            el.tabIndex = 0;
                            el.dataset.id = user.id;
                            el.dataset.name = user.name;
                            el.dataset.dept = user.department;
                            el.innerHTML = `
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                        <i class="ri-user-line text-gray-600"></i>
                                    </div>
                                    <div class="ml-2">
                                        <p class="text-sm font-medium text-gray-900"></p>
                                        <p class="text-xs text-gray-500"></p>
                                    </div>
                                </div>`;
                            el.querySelector('.text-sm').textContent = user.name;
                            el.querySelector('.text-xs').textContent = user.department || user.email || '';
                            el.addEventListener('click', function() {
                                selectUser(this.dataset.id, this.dataset.name, this.dataset.dept);
                            });
                            searchResults.appendChild(el);
                        });
                    } else {
                        const no = document.createElement('div');
                        no.className = 'px-4 py-2 text-sm text-gray-500';
                        no.textContent = 'No users found';
                        searchResults.appendChild(no);
                    }
                    searchResults.classList.remove('hidden');
                }, 300);
            });
        }

        function selectUser(id, name, department) {
            document.getElementById('assigned-to').value = parseInt(id) || '';
            document.getElementById('selected-user-name').textContent = name;
            document.getElementById('selected-user-dept').textContent = department;
            document.getElementById('selected-user').classList.remove('hidden');
            searchInput.value = name;
            searchResults.classList.add('hidden');
        }

        function clearSelectedUser() {
            document.getElementById('assigned-to').value = '';
            document.getElementById('selected-user').classList.add('hidden');
            searchInput.value = '';
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (searchInput && !searchInput.contains(e.target) && searchResults && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
        
        // Photo preview and drag/drop support
        const photoUpload = document.querySelector('.photo-upload');
        const photoInput = document.getElementById('asset-photo');

        // clicking the entire upload box opens file dialog
        photoUpload?.addEventListener('click', function(e) {
            // avoid triggering when clicking the actual input (label handles this too)
            if (e.target.tagName.toLowerCase() !== 'input') {
                photoInput.click();
            }
        });

        // drag over visuals
        photoUpload?.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('bg-gray-50');
            this.classList.add('border-blue-500');
        });
        photoUpload?.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('bg-gray-50');
            this.classList.remove('border-blue-500');
        });

        // drop handler — accept files and preview
        photoUpload?.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('bg-gray-50');
            this.classList.remove('border-blue-500');
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                // set input files (works in modern browsers)
                try {
                    photoInput.files = files;
                } catch (err) {
                    // fallback: nothing to set programmatically in some browsers
                }
                previewImage(photoInput);
            }
        });

        function previewImage(input) {
            const preview = document.getElementById('photo-preview');
            const previewImg = document.getElementById('preview-img');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                previewImg.src = '';
                preview.classList.add('hidden');
            }
        }

        function removePreview() {
            const preview = document.getElementById('photo-preview');
            const previewImg = document.getElementById('preview-img');
            const input = document.getElementById('asset-photo');
            // clear input value
            try { input.value = ''; } catch (e) {}
            previewImg.src = '';
            preview.classList.add('hidden');
        }
        
        // Form validation before submit
        document.getElementById('assetForm')?.addEventListener('submit', function(e) {
            const assetName = document.querySelector('input[name="name"]').value;
            const category = document.querySelector('select[name="category"]').value;
            const condition = document.querySelector('input[name="condition"]:checked');
            const acquisitionDate = document.querySelector('input[name="acquisition_date"]').value;
            if (!assetName || !category || !condition || !acquisitionDate) {
                e.preventDefault();
                alert('Please fill in all required fields (*)');
                return;
            }

            // Ensure a QR code has been generated for this asset code
            const assetCode = document.getElementById('asset-code-input')?.value || '';
            const qrCanvas = document.querySelector('#qrcode canvas');
            if (!assetCode || !qrCanvas) {
                e.preventDefault();
                alert('Please generate a QR code for the asset before registering.');
                return;
            }
            // capture QR image data URL into hidden input so server can save it
            try {
                const qrInput = document.getElementById('qr_image_input');
                let dataUrl = '';
                const canvas = document.querySelector('#qrcode canvas');
                if (canvas && canvas.toDataURL) {
                    dataUrl = canvas.toDataURL('image/png');
                } else {
                    const img = document.querySelector('#qrcode img');
                    if (img) dataUrl = img.src || '';
                }
                if (qrInput) qrInput.value = dataUrl;
            } catch (err) {
                // ignore
            }
        });
        
        // Toast popup for server messages
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastInner = document.getElementById('toast-inner');
            const toastTitle = document.getElementById('toast-title');
            const toastBody = document.getElementById('toast-body');

            if (!toast) return;

            // Reset styles
            toastInner.className = 'w-full flex items-start space-x-3 rounded-lg p-4';

            if (type === 'success') {
                toastInner.classList.add('bg-green-50', 'border', 'border-green-100', 'text-green-800');
                toastTitle.textContent = 'Saved';
                toastBody.textContent = message;
            } else {
                toastInner.classList.add('bg-red-50', 'border', 'border-red-100', 'text-red-800');
                toastTitle.textContent = 'Error';
                toastBody.textContent = message;
            }

            toast.classList.remove('hidden');
            toast.classList.add('opacity-100');

            // Auto hide after 5 seconds
            clearTimeout(window._assetToastTimer);
            window._assetToastTimer = setTimeout(() => {
                toast.classList.add('hidden');
            }, 5000);
        }

        document.getElementById('toast-close')?.addEventListener('click', function() {
            document.getElementById('toast')?.classList.add('hidden');
            clearTimeout(window._assetToastTimer);
        });

        // Calculate expiration date based on lifespan months
        function calculateExpirationDate() {
            const acquisitionDateInput = document.querySelector('input[name="acquisition_date"]');
            const lifespanInput = document.getElementById('lifespan-months');
            const expirationDateInput = document.getElementById('expiration-date');
            
            const acquisitionDate = acquisitionDateInput?.value;
            const lifespanMonths = parseInt(lifespanInput?.value) || 0;
            
            if (!acquisitionDate || lifespanMonths === 0) {
                expirationDateInput.value = '';
                return;
            }
            
            // Calculate expiration date: acquisition date + lifespan months
            const acqDate = new Date(acquisitionDate);
            const expirationDate = new Date(acqDate.getFullYear(), acqDate.getMonth() + lifespanMonths, acqDate.getDate());
            
            // Format as YYYY-MM-DD
            const year = expirationDate.getFullYear();
            const month = String(expirationDate.getMonth() + 1).padStart(2, '0');
            const day = String(expirationDate.getDate()).padStart(2, '0');
            expirationDateInput.value = `${year}-${month}-${day}`;
        }

        // Calculate next maintenance date based on last maintenance + interval
        function calculateNextMaintenanceDate() {
            const lastMaintenanceDateInput = document.getElementById('last-maintenance-date');
            const maintenanceIntervalInput = document.getElementById('maintenance-interval');
            const nextMaintenanceDateInput = document.getElementById('next-maintenance-date');
            
            const lastMaintenanceDate = lastMaintenanceDateInput?.value;
            const maintenanceMonths = parseInt(maintenanceIntervalInput?.value) || 0;
            
            if (!maintenanceMonths) {
                nextMaintenanceDateInput.value = '';
                return;
            }
            
            // If no last maintenance date, show that it will use registration date
            if (!lastMaintenanceDate) {
                nextMaintenanceDateInput.value = '';
                nextMaintenanceDateInput.placeholder = 'Will be set to registration date + interval';
                return;
            }
            
            // Calculate next maintenance date: last maintenance + interval months
            const lastDate = new Date(lastMaintenanceDate);
            const nextDate = new Date(lastDate.getFullYear(), lastDate.getMonth() + maintenanceMonths, lastDate.getDate());
            
            // Format as YYYY-MM-DD
            const year = nextDate.getFullYear();
            const month = String(nextDate.getMonth() + 1).padStart(2, '0');
            const day = String(nextDate.getDate()).padStart(2, '0');
            nextMaintenanceDateInput.value = `${year}-${month}-${day}`;
        }

        // Wire up calculation events on page load
        document.addEventListener('DOMContentLoaded', function() {
            // When acquisition date changes, recalculate expiration date
            document.querySelector('input[name="acquisition_date"]')?.addEventListener('change', calculateExpirationDate);
            
            // When lifespan or last maintenance changes, trigger calculations
            document.getElementById('lifespan-months')?.addEventListener('input', calculateExpirationDate);
            document.getElementById('last-maintenance-date')?.addEventListener('change', calculateNextMaintenanceDate);
            document.getElementById('maintenance-interval')?.addEventListener('input', calculateNextMaintenanceDate);

            // Trigger calculations if data already exists
            calculateExpirationDate();
            calculateNextMaintenanceDate();
        });

        // Trigger toast if server provided a message
        document.addEventListener('DOMContentLoaded', function() {
            const serverSuccess = @json(session('success'));
            const serverError = @json(session('error') ?? ($errors->any() ? $errors->first() : null));
            if (serverSuccess) {
                showToast(serverSuccess, 'success');
            }
            if (serverError) {
                showToast(serverError, 'error');
            }
        });
    </script>

    <!-- Toast markup -->
    <div id="toast" class="hidden fixed bottom-6 right-6 z-50 max-w-sm">
        <div id="toast-inner" class="w-full flex items-start space-x-3 rounded-lg p-4">
            <div class="flex-1">
                <p id="toast-title" class="text-sm font-medium text-gray-900">Saved</p>
                <p id="toast-body" class="text-xs text-gray-600 mt-1">Your asset was saved successfully.</p>
            </div>
            <div class="flex items-start">
                <button id="toast-close" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
            </div>
        </div>
    </div>
</body>
</html>