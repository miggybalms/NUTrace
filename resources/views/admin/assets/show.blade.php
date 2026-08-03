<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asset Details - {{ $asset->Asset_code ?? 'Asset' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <div class="flex-1 overflow-y-auto bg-gray-50">
            <div class="max-w-4xl mx-auto p-8">
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-2xl font-bold">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                            <p class="text-sm text-gray-500 mt-1">Code: <span class="font-mono">{{ $asset->Asset_code }}</span></p>
                            <p class="text-sm text-gray-500 mt-1">Status: {{ $asset->Lifecycle_Status }}</p>
                            <p class="text-sm text-gray-500 mt-1">Assigned to: {{ $asset->full_name ?? 'Unassigned' }}</p>
                        </div>
                        <div class="text-right">
                        @php
                        $photo = $asset->image_url ?? $asset->url ?? null;
                        @endphp

                        @if($photo)
                        <img src="{{ \Illuminate\Support\Str::startsWith($photo, ['http://', 'https://', '/storage', 'storage/'])
                        ? (Str::startsWith($photo, 'storage/') ? asset($photo) : $photo)
                        : asset('storage/' . ltrim($photo, '/')) }}"
                        alt="{{ $asset->Asset_name ?? 'Asset' }}"
                        class="h-28 w-auto rounded-lg border object-cover" />
                        @else
                        <div class="h-28 w-28 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm">
                        No Photo
                        </div>
                        @endif
                            @if(!empty($asset->qr_code_url) || !empty($asset->qr_code_path))
                                <div class="mt-3">
                                    <p class="text-xs text-gray-500">Asset QR</p>
                                    <img src="{{ $asset->qr_code_url ?? (\Illuminate\Support\Facades\Storage::url($asset->qr_code_path)) }}" alt="Asset QR" class="h-28 w-28 rounded-lg border mt-1" />
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Acquisition Date</p>
                            <p class="text-sm text-gray-900">{{ $asset->accusion_date ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Purchase Price</p>
                            <p class="text-sm text-gray-900">{{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Serial Number</p>
                            <p class="text-sm text-gray-900">{{ $asset->serial_Number ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Location</p>
                            <p class="text-sm text-gray-900">{{ $asset->asset_location ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Condition</p>
                            <p class="text-sm text-gray-900">{{ $asset->Condition ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Category</p>
                            <p class="text-sm text-gray-900">{{ $asset->Category ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Lifespan Information -->
                    @if($asset->lifespan_months || $asset->expiration_date)
                    <div class="mt-8 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Lifespan</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Lifespan Duration</p>
                                <p class="text-sm text-gray-900">{{ $asset->lifespan_months ? $asset->lifespan_months . ' months' : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Expiration Date</p>
                                @if($asset->expiration_date)
                                    @php
                                        $expirationDate = \Carbon\Carbon::parse($asset->expiration_date);
                                        $isExpired = $expirationDate->isPast();
                                        $daysRemaining = now()->diffInDays($expirationDate, false);
                                    @endphp
                                    <p class="text-sm {{ $isExpired ? 'text-red-600 font-semibold' : ($daysRemaining < 90 ? 'text-amber-600' : 'text-gray-900') }}">
                                        {{ $expirationDate->format('M d, Y') }}
                                        @if($isExpired)
                                            <span class="text-xs ml-1">(Expired {{ abs($daysRemaining) }} days ago)</span>
                                        @elseif($daysRemaining < 90)
                                            <span class="text-xs ml-1">({{ $daysRemaining }} days remaining)</span>
                                        @endif
                                    </p>
                                @else
                                    <p class="text-sm text-gray-900">—</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Maintenance Information -->
                    @if($asset->maintenance_interval || $asset->next_maintenance_date)
                    <div class="mt-8 border-t pt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Maintenance Schedule</h3>
                            @if($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->isPast())
                                <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">OVERDUE</span>
                            @elseif($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->diffInDays(now()) <= 14)
                                <span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">DUE SOON</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-xs text-gray-500">Maintenance Interval</p>
                                <p class="text-sm text-gray-900">{{ $asset->maintenance_interval ? $asset->maintenance_interval . ' months' : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Last Maintenance Date</p>
                                <p class="text-sm text-gray-900">{{ $asset->last_maintenance_date ? \Carbon\Carbon::parse($asset->last_maintenance_date)->format('M d, Y') : 'Never' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Next Maintenance Due</p>
                                @if($asset->next_maintenance_date)
                                    @php
                                        $nextMaintDate = \Carbon\Carbon::parse($asset->next_maintenance_date);
                                        $isOverdue = $nextMaintDate->isPast();
                                        $daysUntilDue = now()->diffInDays($nextMaintDate, false);
                                    @endphp
                                    <p class="text-sm {{ $isOverdue ? 'text-red-600 font-semibold' : ($daysUntilDue < 14 ? 'text-amber-600' : 'text-gray-900') }}">
                                        {{ $nextMaintDate->format('M d, Y') }}
                                        @if($isOverdue)
                                            <span class="text-xs ml-1">({{ abs($daysUntilDue) }} days overdue)</span>
                                        @elseif($daysUntilDue < 14)
                                            <span class="text-xs ml-1">({{ $daysUntilDue }} days)</span>
                                        @endif
                                    </p>
                                @else
                                    <p class="text-sm text-gray-900">—</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Repair History</p>
                                <p class="text-sm text-gray-900">{{ $asset->repair_counts ?? 0 }} repair(s)</p>
                            </div>
                        </div>
                        
                        <!-- Mark Maintenance Complete Button -->
                        @if($asset->next_maintenance_date)
                        <button onclick="openMaintenanceCompleteModal()" class="mt-4 inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <i class="ri-checkbox-circle-line mr-2"></i>
                            Mark Maintenance Complete
                        </button>
                        @endif
                    </div>
                    @endif

                    <!-- Expired Asset Evaluation Section -->
                    @if($asset->expiration_date && \Carbon\Carbon::parse($asset->expiration_date)->isPast())
                    <div class="mt-8 border-t pt-6 bg-red-50 p-6 rounded-lg border-2 border-red-200">
                        <div class="flex items-start gap-3 mb-4">
                            <i class="ri-error-warning-line text-red-600 text-2xl mt-1"></i>
                            <div>
                                <h3 class="text-lg font-bold text-red-900">Expired Asset Evaluation</h3>
                                <p class="text-sm text-red-700 mt-1">This asset has reached the end of its operational lifespan and requires evaluation.</p>
                                <p class="text-sm text-red-700 mt-2">Please review the asset's condition and select an appropriate action:</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-6">
                            <!-- Return to Active with Extension -->
                            <button onclick="openReturnToActiveModal()" class="p-4 bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg hover:shadow-lg transition text-left">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold">Return to Active</p>
                                        <p class="text-xs mt-1 opacity-90">Asset is still functional<br/>Optional lifespan extension</p>
                                    </div>
                                    <i class="ri-check-double-line text-2xl opacity-70"></i>
                                </div>
                            </button>

                            <!-- Send for Repair -->
                            <button onclick="openSendToRepairModal()" class="p-4 bg-gradient-to-br from-amber-500 to-amber-600 text-white rounded-lg hover:shadow-lg transition text-left">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold">Send for Repair</p>
                                        <p class="text-xs mt-1 opacity-90">Asset needs maintenance<br/>Schedule repair evaluation</p>
                                    </div>
                                    <i class="ri-tools-line text-2xl opacity-70"></i>
                                </div>
                            </button>

                            <!-- Recommend for Replacement -->
                            <button onclick="openRecommendReplacementModal()" class="p-4 bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg hover:shadow-lg transition text-left">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold">Recommend Replacement</p>
                                        <p class="text-xs mt-1 opacity-90">Asset beyond economical repair<br/>Initiate replacement request</p>
                                    </div>
                                    <i class="ri-refresh-line text-2xl opacity-70"></i>
                                </div>
                            </button>

                            <!-- Proceed with Disposal -->
                            <button onclick="openProceedDisposalModal()" class="p-4 bg-gradient-to-br from-red-500 to-red-600 text-white rounded-lg hover:shadow-lg transition text-left">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold">Proceed with Disposal</p>
                                        <p class="text-xs mt-1 opacity-90">Asset no longer serviceable<br/>End of life disposal process</p>
                                    </div>
                                    <i class="ri-delete-bin-line text-2xl opacity-70"></i>
                                </div>
                            </button>
                        </div>
                    </div>
                    @endif

                    <div class="mt-6">
                        <a href="/admin/assets" class="inline-flex items-center px-4 py-2 bg-gray-100 border rounded-lg">&larr; Back to assets</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Maintenance Complete Modal -->
    <div id="maintenanceCompleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeMaintenanceCompleteModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Mark Maintenance Complete</h3>
                <button onclick="closeMaintenanceCompleteModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="maintenanceCompleteForm">
                    @csrf
                    <div class="mb-4">
                        <label for="maintenanceDate" class="block text-sm font-medium text-gray-700 mb-2">Completion Date</label>
                        <input 
                            type="date" 
                            id="maintenanceDate" 
                            name="completion_date"
                            value="{{ date('Y-m-d') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                        />
                    </div>
                    
                    <div class="mb-4">
                        <label for="maintenanceNotes" class="block text-sm font-medium text-gray-700 mb-2">Maintenance Notes (Optional)</label>
                        <textarea 
                            id="maintenanceNotes" 
                            name="notes"
                            placeholder="e.g., Replaced filters, lubricated joints, all systems operational"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition resize-none"
                        ></textarea>
                    </div>
                    
                    <div id="maintenanceError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="maintenanceSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeMaintenanceCompleteModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center"
                        >
                            <i class="ri-checkbox-circle-line mr-2"></i>
                            Mark Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Return to Active Modal -->
    <div id="returnToActiveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeReturnToActiveModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Return Asset to Active</h3>
                <button onclick="closeReturnToActiveModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="returnToActiveForm">
                    @csrf
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-900">Asset will be returned to <strong>Active</strong> status and can resume operational use.</p>
                    </div>

                    <div class="mb-4">
                        <label for="extendLifespan" class="flex items-center">
                            <input type="checkbox" id="extendLifespan" name="extend_lifespan" class="w-4 h-4 text-green-600 rounded border-gray-300" onchange="toggleExtensionFields()">
                            <span class="ml-2 text-sm font-medium text-gray-700">Extend asset lifespan</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-6">Optional: Add additional months to operational lifespan</p>
                    </div>

                    <div id="extensionFields" style="display: none;" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <label for="extensionMonths" class="block text-sm font-medium text-gray-700 mb-2">Additional Lifespan Months</label>
                        <input 
                            type="number" 
                            id="extensionMonths" 
                            name="extension_months"
                            min="1"
                            max="120"
                            value="12"
                            placeholder="Number of months to extend"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                        />
                        <p class="text-xs text-gray-600 mt-2">New expiration date will be: <span id="newExpirationDate">N/A</span></p>
                    </div>

                    <div class="mb-4">
                        <label for="evaluationNotes" class="block text-sm font-medium text-gray-700 mb-2">Evaluation Notes</label>
                        <textarea 
                            id="evaluationNotes" 
                            name="evaluation_notes"
                            placeholder="e.g., Asset condition satisfactory, performs all required functions, recommend continued use"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition resize-none"
                        ></textarea>
                    </div>
                    
                    <div id="returnError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="returnSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeReturnToActiveModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center"
                        >
                            <i class="ri-check-double-line mr-2"></i>
                            Return to Active
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send for Repair Modal -->
    <div id="sendToRepairModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeSendToRepairModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Send Asset for Repair</h3>
                <button onclick="closeSendToRepairModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="sendToRepairForm">
                    @csrf
                    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <p class="text-sm text-amber-900">Asset will transition to <strong>For Repair</strong> status. Maintenance evaluation and servicing will be scheduled.</p>
                    </div>

                    <div class="mb-4">
                        <label for="repairDescription" class="block text-sm font-medium text-gray-700 mb-2">Issues or Deterioration Identified</label>
                        <textarea 
                            id="repairDescription" 
                            name="repair_description"
                            placeholder="e.g., Display flickering, keyboard unresponsive, battery not charging, performance degradation"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition resize-none"
                        ></textarea>
                    </div>

                    <div id="repairError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="repairSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeSendToRepairModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition flex items-center"
                        >
                            <i class="ri-tools-line mr-2"></i>
                            Send for Repair
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recommend Replacement Modal -->
    <div id="recommendReplacementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeRecommendReplacementModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Recommend Replacement</h3>
                <button onclick="closeRecommendReplacementModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="recommendReplacementForm">
                    @csrf
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-900">Asset will transition to <strong>For Replacement</strong> status. A replacement request will be initiated and requires approval.</p>
                    </div>

                    <div class="mb-4">
                        <label for="replacementReason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Replacement</label>
                        <textarea 
                            id="replacementReason" 
                            name="replacement_reason"
                            placeholder="e.g., Beyond economical repair, frequent failures, obsolete technology, does not meet operational requirements"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition resize-none"
                        ></textarea>
                    </div>

                    <div id="replacementError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="replacementSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeRecommendReplacementModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center"
                        >
                            <i class="ri-refresh-line mr-2"></i>
                            Recommend Replacement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Proceed with Disposal Modal -->
    <div id="proceedDisposalModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeProceedDisposalModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Proceed with Disposal</h3>
                <button onclick="closeProceedDisposalModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="proceedDisposalForm">
                    @csrf
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-900"><strong>Warning:</strong> Asset will transition to disposal process. This action marks the end of the asset's operational lifespan.</p>
                    </div>

                    <div class="mb-4">
                        <label>
                            <input type="checkbox" id="confirmDisposal" name="confirm_disposal" class="w-4 h-4 text-red-600 rounded border-gray-300" onchange="toggleDisposalConfirm()">
                            <span class="ml-2 text-sm font-medium text-gray-700">I confirm this asset should be disposed</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label for="disposalReason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Disposal</label>
                        <textarea 
                            id="disposalReason" 
                            name="disposal_reason"
                            placeholder="e.g., End of life, no longer serviceable, obsolete, environmental concerns"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none transition resize-none"
                        ></textarea>
                    </div>

                    <div id="disposalError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="disposalSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeProceedDisposalModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            id="disposalSubmitBtn"
                            disabled
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i class="ri-delete-bin-line mr-2"></i>
                            Proceed with Disposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openMaintenanceCompleteModal() {
            document.getElementById('maintenanceCompleteModal').classList.remove('hidden');
        }
        
        function closeMaintenanceCompleteModal(event) {
            if (event && event.target.id !== 'maintenanceCompleteModal') return;
            document.getElementById('maintenanceCompleteModal').classList.add('hidden');
            document.getElementById('maintenanceCompleteForm').reset();
            document.getElementById('maintenanceDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('maintenanceError').style.display = 'none';
            document.getElementById('maintenanceSuccess').style.display = 'none';
        }
        
        document.getElementById('maintenanceCompleteForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const completionDate = document.getElementById('maintenanceDate').value;
            const notes = document.getElementById('maintenanceNotes').value.trim();
            const errorEl = document.getElementById('maintenanceError');
            const successEl = document.getElementById('maintenanceSuccess');
            
            errorEl.style.display = 'none';
            successEl.style.display = 'none';
            
            if (!completionDate) {
                errorEl.textContent = 'Completion date is required';
                errorEl.style.display = 'block';
                return;
            }
            
            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/maintenance-complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        completion_date: completionDate,
                        notes: notes || 'Maintenance completed'
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ ' + data.message;
                    successEl.style.display = 'block';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to mark maintenance complete';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} shadow-lg z-50`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // ===== Return to Active Modal Functions =====
        function openReturnToActiveModal() {
            document.getElementById('returnToActiveModal').classList.remove('hidden');
            calculateNewExpirationDate();
        }
        
        function closeReturnToActiveModal(event) {
            if (event && event.target.id !== 'returnToActiveModal') return;
            document.getElementById('returnToActiveModal').classList.add('hidden');
            document.getElementById('returnToActiveForm').reset();
            document.getElementById('returnError').style.display = 'none';
            document.getElementById('returnSuccess').style.display = 'none';
            document.getElementById('extensionFields').style.display = 'none';
        }

        function toggleExtensionFields() {
            const checkbox = document.getElementById('extendLifespan');
            const fields = document.getElementById('extensionFields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
            if (checkbox.checked) calculateNewExpirationDate();
        }

        function calculateNewExpirationDate() {
            const months = parseInt(document.getElementById('extensionMonths').value) || 0;
            const currentExpiration = new Date('{{ $asset->expiration_date }}');
            const newDate = new Date(currentExpiration);
            newDate.setMonth(newDate.getMonth() + months);
            document.getElementById('newExpirationDate').textContent = newDate.toLocaleDateString();
        }

        document.getElementById('extensionMonths').addEventListener('change', calculateNewExpirationDate);

        document.getElementById('returnToActiveForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('returnError');
            const successEl = document.getElementById('returnSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            try {
                const assetId = '{{ $asset->id }}';
                const extendLifespan = document.getElementById('extendLifespan').checked;
                const extensionMonths = extendLifespan ? parseInt(document.getElementById('extensionMonths').value) : 0;
                const notes = document.getElementById('evaluationNotes').value.trim();

                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'return_active',
                        extension_months: extensionMonths,
                        evaluation_notes: notes || 'Asset returned to active status'
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Asset successfully returned to active status';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to update asset';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });

        // ===== Send for Repair Modal Functions =====
        function openSendToRepairModal() {
            document.getElementById('sendToRepairModal').classList.remove('hidden');
        }
        
        function closeSendToRepairModal(event) {
            if (event && event.target.id !== 'sendToRepairModal') return;
            document.getElementById('sendToRepairModal').classList.add('hidden');
            document.getElementById('sendToRepairForm').reset();
            document.getElementById('repairError').style.display = 'none';
            document.getElementById('repairSuccess').style.display = 'none';
        }

        document.getElementById('sendToRepairForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('repairError');
            const successEl = document.getElementById('repairSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            const description = document.getElementById('repairDescription').value.trim();
            if (!description) {
                errorEl.textContent = 'Please describe the issues identified';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'send_repair',
                        evaluation_notes: description
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Asset sent for repair evaluation';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to send asset for repair';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });

        // ===== Recommend Replacement Modal Functions =====
        function openRecommendReplacementModal() {
            document.getElementById('recommendReplacementModal').classList.remove('hidden');
        }
        
        function closeRecommendReplacementModal(event) {
            if (event && event.target.id !== 'recommendReplacementModal') return;
            document.getElementById('recommendReplacementModal').classList.add('hidden');
            document.getElementById('recommendReplacementForm').reset();
            document.getElementById('replacementError').style.display = 'none';
            document.getElementById('replacementSuccess').style.display = 'none';
        }

        document.getElementById('recommendReplacementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('replacementError');
            const successEl = document.getElementById('replacementSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            const reason = document.getElementById('replacementReason').value.trim();
            if (!reason) {
                errorEl.textContent = 'Please provide a reason for replacement';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'recommend_replacement',
                        evaluation_notes: reason
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Replacement request initiated';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to initiate replacement';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });

        // ===== Proceed with Disposal Modal Functions =====
        function openProceedDisposalModal() {
            document.getElementById('proceedDisposalModal').classList.remove('hidden');
        }
        
        function closeProceedDisposalModal(event) {
            if (event && event.target.id !== 'proceedDisposalModal') return;
            document.getElementById('proceedDisposalModal').classList.add('hidden');
            document.getElementById('proceedDisposalForm').reset();
            document.getElementById('confirmDisposal').checked = false;
            document.getElementById('disposalSubmitBtn').disabled = true;
            document.getElementById('disposalError').style.display = 'none';
            document.getElementById('disposalSuccess').style.display = 'none';
        }

        function toggleDisposalConfirm() {
            const confirmed = document.getElementById('confirmDisposal').checked;
            document.getElementById('disposalSubmitBtn').disabled = !confirmed;
        }

        document.getElementById('proceedDisposalForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('disposalError');
            const successEl = document.getElementById('disposalSuccess');
            errorEl.style.display = 'none';
            successEl.style.display = 'none';

            const reason = document.getElementById('disposalReason').value.trim();
            if (!reason) {
                errorEl.textContent = 'Please provide a reason for disposal';
                errorEl.style.display = 'block';
                return;
            }

            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/evaluate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        action: 'proceed_disposal',
                        evaluation_notes: reason
                    })
                });

                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ Asset marked for disposal';
                    successEl.style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to mark asset for disposal';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });
    </script>
