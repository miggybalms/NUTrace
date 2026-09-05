@php
    $user = auth()->user();
    $initials = $user ? strtoupper(substr($user->full_name, 0, 2)) : 'AO';
@endphp

<!-- Mobile overlay (click to close sidebar) -->
<div id="sidebarOverlay" onclick="closeSidebar()"
     class="hidden fixed inset-0 bg-black/60 z-40 lg:hidden"></div>

<div id="sidebar"
     class="fixed inset-y-0 left-0 z-50 w-64 bg-[#0B1220] text-white flex flex-col overflow-y-auto
            transform -translate-x-full transition-transform duration-300 ease-in-out
            lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:z-auto">

    <div class="px-5 py-5 border-b border-[#C9A227]/15 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#C9A227] to-[#8f7015] flex items-center justify-center flex-shrink-0">
                <i class="ri-dashboard-line text-[#0B1220] text-base"></i>
            </div>
            <h1 class="text-[17px] font-medium text-[#F3EFE3]">Dashboard</h1>
        </div>
        <!-- Close button, mobile only -->
        <button onclick="closeSidebar()" class="lg:hidden text-[#7C86A0] hover:text-white">
            <i class="ri-close-line text-2xl"></i>
        </button>
    </div>

    <nav class="flex-1 py-4">
        <div class="px-4 mb-1">
            <div class="flex items-center gap-2 px-2.5 pb-2.5">
                <span class="text-xs font-medium text-[#7C86A0]">Main</span>
                <span class="flex-1 h-px bg-[#C9A227]/15"></span>
            </div>

            <a href="/admin"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin') && !request()->is('admin/*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-dashboard-line text-lg"></i>
                <span>Dashboard</span>
            </a>
            <a href="/admin/assets"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin/assets*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-computer-line text-lg"></i>
                <span>Assets</span>
            </a>
            <a href="/admin/requests"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin/requests*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-mail-line text-lg"></i>
                <span>Requests</span>
            </a>
            <a href="/admin/replacement"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin/replacement*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-refresh-line text-lg"></i>
                <span>Replacement</span>
            </a>
            <a href="/admin/repair"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin/repair*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-tools-line text-lg"></i>
                <span>Repair</span>
            </a>
            <a href="/admin/disposal"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin/disposal*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-delete-bin-line text-lg"></i>
                <span>Disposal</span>
            </a>
            <a href="/admin/pullout"
               class="flex items-center gap-3 px-3 py-2.5 mb-0.5 text-sm rounded-lg transition-colors
                      {{ request()->is('admin/pullout*')
                          ? 'border-l-[3px] border-[#C9A227] rounded-l-none bg-[#C9A227]/10 text-[#E9C766] font-medium pl-[9px]'
                          : 'text-[#B7BFD4] hover:bg-white/5 hover:text-[#F3EFE3]' }}">
                <i class="ri-logout-box-r-line text-lg"></i>
                <span>Pullout</span>
            </a>
        </div>
    </nav>

    <div class="border-t border-[#C9A227]/15 p-3.5 mt-auto">
        <div class="flex items-center mb-2 p-2.5 rounded-xl bg-[#111B2E]">
            <div class="w-9 h-9 rounded-full bg-[#1C2740] border border-[#C9A227]/70 flex items-center justify-center flex-shrink-0">
                @if($user && $user->profile_photo_url)
                    <img src="{{ $user->profile_photo_url }}"
                         class="w-9 h-9 rounded-full object-cover"
                         alt="Profile" />
                @else
                    <span class="text-[#E9C766] font-medium text-[13px]">{{ $initials }}</span>
                @endif
            </div>
            <div class="ml-2.5 flex-1 min-w-0">
                <p class="text-[13px] font-medium text-[#F3EFE3] truncate">{{ $user?->full_name ?? 'Asset Officer' }}</p>
                <p class="text-xs text-[#7C86A0] truncate">{{ $user?->email ?? 'admin@university.edu' }}</p>
            </div>
            <i class="ri-settings-3-line text-[#7C86A0] cursor-pointer hover:text-[#E9C766] text-sm"></i>
        </div>
        <a href="/logout" class="flex items-center gap-3 px-3 py-2 text-sm text-[#9AA3B8] rounded-lg hover:bg-white/5 hover:text-[#F3EFE3] transition-colors">
            <i class="ri-logout-box-line text-lg"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    function toggleSidebar() {
        document.getElementById('sidebar').classList.contains('-translate-x-full')
            ? openSidebar()
            : closeSidebar();
    }
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            document.getElementById('sidebarOverlay').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    });
</script>