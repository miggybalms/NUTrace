@php
    $user = auth()->user();
    $initials = $user ? strtoupper(substr($user->full_name, 0, 2)) : 'AO';
@endphp

<div class="w-64 bg-gray-900 text-white flex flex-col overflow-y-auto">
    <div class="p-6 border-b border-gray-800">
        <h1 class="text-2xl font-bold flex items-center">
            <i class="ri-dashboard-line mr-2"></i>
            Dashboard
        </h1>
    </div>

    <div class="p-4 border-b border-gray-800">
        <div class="relative">
            <i class="ri-search-line absolute left-3 top-2.5 text-gray-400 text-sm"></i>
            <input type="text" placeholder="Search assets..." 
                   class="w-full pl-9 pr-3 py-2 bg-gray-800 rounded-lg text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <nav class="flex-1 py-4">
        <div class="px-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-3">MAIN</p>
            <a href="/admin" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1 {{ request()->is('admin') ? 'active' : '' }}">
                <i class="ri-dashboard-line mr-3 text-lg"></i>
                <span>Dashboard</span>
            </a>
            <a href="/admin/assets" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1 {{ request()->is('admin/assets*') ? 'active' : '' }}">
                <i class="ri-computer-line mr-3 text-lg"></i>
                <span>Assets</span>
            </a>
            <a href="/admin/requests" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1 {{ request()->is('admin/requests*') ? 'active' : '' }}">
                <i class="ri-mail-line mr-3 text-lg"></i>
                <span>Requests</span>
            </a>
            <a href="/admin/disposal" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1 {{ request()->is('admin/disposal*') ? 'active' : '' }}">
                <i class="ri-delete-bin-line mr-3 text-lg"></i>
                <span>Disposal</span>
            </a>
            <a href="/admin/pullout" class="sidebar-item flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1 {{ request()->is('admin/pullout*') ? 'active' : '' }}">
                <i class="ri-logout-box-r-line mr-3 text-lg"></i>
                <span>Pullout</span>
            </a>
        </div>
    </nav>

    <div class="border-t border-gray-800 p-4 mt-auto">
        <div class="flex items-center mb-3 p-2 rounded-lg bg-gray-800">
            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-white font-semibold text-sm">{{ $initials }}</span>
            </div>
            <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">{{ $user?->full_name ?? 'Asset Officer' }}</p>
                <p class="text-xs text-gray-400 truncate">{{ $user?->email ?? 'admin@university.edu' }}</p>
            </div>
            <i class="ri-settings-3-line text-gray-400 cursor-pointer hover:text-white text-sm"></i>
        </div>
        <a href="/logout" class="flex items-center px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-gray-800 transition">
            <i class="ri-logout-box-line mr-3 text-lg"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
