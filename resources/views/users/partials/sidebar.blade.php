@php
    $user = $currentUser ?? Auth::user();
    $initial = $user ? strtoupper(substr($user->full_name ?? 'U', 0, 1)) : 'U';
@endphp

<div class="w-64 bg-gray-900 text-white flex flex-col overflow-y-auto flex-shrink-0">
    <div class="p-6 pb-6">
        <h1 class="text-2xl font-bold flex items-center text-white">
            <i class="ri-dashboard-line mr-2 text-blue-400"></i>
            Dashboard
        </h1>
    </div>

    <nav class="flex-1 px-4">
        <a href="/users"
           class="sidebar-item {{ request()->is('users') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
            <i class="ri-computer-line mr-3 text-lg"></i>
            <span>My Assets</span>
        </a>

        <a href="/users/assets"
           class="sidebar-item {{ request()->is('users/assets*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
            <i class="ri-archive-line mr-3 text-lg"></i>
            <span>Assets</span>
        </a>

        <a href="/user/requests"
           class="sidebar-item {{ request()->is('user/request-asset*') || request()->is('user/requests*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
            <i class="ri-mail-line mr-3 text-lg"></i>
            <span>Requests</span>
        </a>
    </nav>

    <div class="border-t border-gray-800 p-4 mt-auto">
        <div class="flex items-center mb-3 p-2 rounded-lg bg-gray-800">
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        @if($user && $user->profile_photo_url)
                            <img src="{{ $user->profile_photo_url }}" class="w-10 h-10 rounded-full object-cover" alt="Profile"/>
                        @else
                            <span class="text-white font-semibold text-sm">{{ $initial }}</span>
                        @endif
                </div>
                <div class="ml-3 flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ $user?->full_name ?? 'User' }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ $user?->email ?? 'user@user.com' }}</p>
                </div>
            <a href="/user/settings">
                <i class="ri-settings-3-line text-gray-400 cursor-pointer hover:text-white text-sm"></i>
            </a>
        </div>
        <a href="/logout" class="w-full flex items-center px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-gray-800 transition">
            <i class="ri-logout-box-line mr-3 text-lg"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
