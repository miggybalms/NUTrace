<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - University Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .sidebar-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background-color: #1f2937;
            color: #ffffff;
        }

        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .sidebar-item.active i {
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50">

    @php
        // Prefer explicitly-passed $currentUser, otherwise fall back to the authenticated user
        $user = $currentUser ?? Auth::user();
        $initial = $user ? strtoupper(substr($user->full_name ?? 'U', 0, 1)) : 'U';
    @endphp

    <!-- Mobile top bar with hamburger toggle (hidden on lg+) -->
    <div class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-gray-900 text-white flex items-center px-4 z-40 shadow-md">
        <button id="sidebarOpenBtn" class="text-2xl mr-3 focus:outline-none" aria-label="Open menu">
            <i class="ri-menu-line"></i>
        </button>
        <h1 class="text-lg font-bold flex items-center text-white">
            <i class="ri-dashboard-line mr-2 text-blue-400"></i>
            Dashboard
        </h1>
    </div>

    <!-- Overlay (mobile only, shown when sidebar is open) -->
    <div id="sidebarOverlay" class="hidden lg:hidden fixed inset-0 bg-black/50 z-40"></div>

    <div class="flex h-screen overflow-hidden">

        <!-- SIDEBAR -->
        <div id="sidebar"
             class="w-64 bg-gray-900 text-white flex flex-col overflow-y-auto flex-shrink-0
                    fixed inset-y-0 left-0 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out
                    lg:static lg:translate-x-0 lg:transition-none lg:z-auto">

            <!-- Logo / Title -->
            <div class="p-6 pb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold flex items-center text-white">
                    <i class="ri-dashboard-line mr-2 text-blue-400"></i>
                    Dashboard
                </h1>
                <button id="sidebarCloseBtn" class="lg:hidden text-gray-400 hover:text-white text-2xl focus:outline-none" aria-label="Close menu">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-4">
                     <a href="/users"
                         class="sidebar-item {{ Request::is('users') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                    <i class="ri-computer-line mr-3 text-lg"></i>
                    <span>My Assets</span>
                </a>

                    <a href="/users/assets"
                       class="sidebar-item {{ Request::is('users/assets*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-archive-line mr-3 text-lg"></i>
                        <span>Assets</span>
                    </a>

                    <a href="/user/requests"
                       class="sidebar-item {{ Request::is('user/requests*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                        <i class="ri-mail-line mr-3 text-lg"></i>
                        <span>Requests</span>
                    </a>
            </nav>

            <!-- User Info + Logout -->
            <div class="border-t border-gray-800 p-4 mt-auto">
                <div class="flex items-center mb-3 p-2 rounded-lg bg-gray-800">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        @if($user && $user->profile_photo_url)
                            <img src="{{ $user->profile_photo_url }}"
                                 class="w-10 h-10 rounded-full object-cover"
                                 alt="Profile"/>
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

        <!-- MAIN CONTENT -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Spacer so page content isn't hidden under the fixed mobile top bar -->
            <div class="lg:hidden h-14"></div>
            @yield('content')
        </div>

    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const openBtn = document.getElementById('sidebarOpenBtn');
            const closeBtn = document.getElementById('sidebarCloseBtn');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            if (openBtn) openBtn.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);

            // Close the drawer automatically if the viewport is resized up to desktop size
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });
        })();
    </script>

</body>
</html>