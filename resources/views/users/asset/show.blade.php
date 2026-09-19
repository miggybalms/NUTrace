<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $asset->Asset_name ?? 'Asset' }} — My Assets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body{ background:#F3EEE0 !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',sans-serif !important; color:#1A2233; }
        .brand-title{ font-family:'Fraunces',Georgia,serif; }
    </style>
</head>
<body class="bg-[#F3EEE0]">
    <div class="flex h-screen overflow-hidden">
        @include('users.partials.sidebar')

        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
    <div class="max-w-4xl mx-auto p-8">

            {{-- Back Button --}}
            <a href="{{ url()->previous() }}"
            class="inline-flex items-center gap-1.5 text-sm text-[#5B6678] px-3 py-1.5 rounded-lg border border-[#DED2AE] bg-white hover:bg-[#F5F0E2] transition mb-5">
                <i class="ri-arrow-left-line"></i> Back to my assets
            </a>

        {{-- Main Card --}}
        <div class="bg-white border border-[#DED2AE] rounded-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex justify-between items-start p-6 pb-0">
                <div>
                    <span class="inline-block text-xs font-medium px-3 py-1 rounded-full bg-[#EAF4EE] text-[#245C3B] mb-2">
                        {{ $asset->Lifecycle_Status ?? 'Unknown' }}
                    </span>
                    <h1 class="brand-title text-2xl font-semibold text-[#0F2143] capitalize">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                    <p class="text-xs font-mono text-[#8991A0] mt-1">{{ $asset->Asset_code }}</p>
                </div>
                <div class="w-24 h-24 rounded-xl border border-[#EFE9D8] bg-[#F5F0E2] flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($asset->image_url)
                        <img src="{{ $asset->image_url }}" alt="Asset photo" class="w-full h-full object-contain" />
                    @else
                        <i class="ri-image-off-line text-2xl text-[#A8AFBC]"></i>
                    @endif
                </div>
            </div>

            <hr class="border-t border-[#EFE9D8] mx-6 mt-5">

            {{-- Assigned To --}}
            <div class="flex items-center gap-3 px-6 py-5 border-b border-[#EFE9D8]">
                <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                    <i class="ri-user-follow-line text-base"></i>
                </div>
                <div>
                    <p class="text-xs text-[#8991A0] mb-1">Assigned to</p>
                    <div class="inline-flex items-center gap-1.5 bg-[#EFE9D8] rounded-full pl-1 pr-3 py-0.5">
                        <div class="w-5 h-5 rounded-full bg-[#EFE7F3] text-[#523A64] text-[10px] font-semibold flex items-center justify-center">
                            {{ strtoupper(substr($asset->full_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($asset->full_name ?? ' U', ' '), 1, 1)) }}
                        </div>
                        <span class="text-sm font-medium text-[#24334F]">{{ $asset->full_name ?? 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            {{-- Detail Grid --}}
            <div class="grid grid-cols-2">

                {{-- Acquisition Date --}}
                <div class="flex items-center gap-3 px-6 py-5 border-b border-r border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-calendar-event-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Acquisition date</p>
                        <p class="text-sm font-medium text-[#0F2143]">{{ $asset->accusion_date ?? '—' }}</p>
                    </div>
                </div>

                {{-- Purchase Price --}}
                <div class="flex items-center gap-3 px-6 py-5 border-b border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-money-peso-circle-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Purchase price</p>
                        <p class="text-sm font-medium text-[#0F2143]">
                            {{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}
                        </p>
                    </div>
                </div>

                {{-- Serial Number --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-barcode-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Serial number</p>
                        <p class="text-sm font-medium font-mono text-[#0F2143]">{{ $asset->serial_Number ?? '—' }}</p>
                    </div>
                </div>

                {{-- Location --}}
                <div class="flex items-center gap-3 px-6 py-5 border-b border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-map-pin-2-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Location</p>
                        <p class="text-sm font-medium text-[#0F2143]">{{ $asset->asset_location ?? '—' }}</p>
                    </div>
                </div>

                {{-- Next Maintenance Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-[#EFE9D8]">
                    <div class="w-8 h-8 rounded-lg bg-[#EFE9D8] flex items-center justify-center text-[#5B6678]">
                        <i class="ri-tools-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-[#8991A0]">Next maintenance</p>
                        <p class="text-sm font-medium text-[#0F2143]">
                            @if($asset->next_maintenance_date)
                                {{ date('M d, Y', strtotime($asset->next_maintenance_date)) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
    </div>
</body>
</html>
