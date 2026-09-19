<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - University Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #F3EEE0;
            color: #1A2233;
        }

        .font-display{ font-family: 'Fraunces', Georgia, serif; }
        .font-mono{ font-family: 'IBM Plex Mono', monospace; }

        .sidebar-item {
            transition: all 0.15s ease;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #F3EFE3;
        }

        .sidebar-item.active {
            background-color: rgba(201, 162, 39, 0.10);
            color: #E9C766;
            border-left-color: #C9A227;
        }

        .sidebar-item.active i {
            color: #E9C766;
        }
    </style>
</head>
<body class="bg-[#F3EEE0]">

    @php
        // Prefer explicitly-passed $currentUser, otherwise fall back to the authenticated user
        $user = $currentUser ?? Auth::user();
        $initial = $user?->initials ?? 'U';
    @endphp

    <!-- Mobile top bar with hamburger toggle (hidden on lg+) -->
    <div class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-[#0B1220] text-white flex items-center px-4 z-40 shadow-md">
        <button id="sidebarOpenBtn" class="text-2xl mr-3 focus:outline-none" aria-label="Open menu">
            <i class="ri-menu-line"></i>
        </button>
    <h1 class="text-lg font-bold flex items-center text-white">
        <i class="ri-stack-line mr-2 text-[#E9C766]"></i>
        NU Trace
    </h1>
    </div>

    <!-- Overlay (mobile only, shown when sidebar is open) -->
    <div id="sidebarOverlay" class="hidden lg:hidden fixed inset-0 bg-black/50 z-40"></div>

    <div class="flex h-screen overflow-hidden">

        <!-- SIDEBAR -->
        <div id="sidebar"
             class="w-64 bg-[#0B1220] text-white flex flex-col overflow-y-auto flex-shrink-0
                    fixed inset-y-0 left-0 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out
                    lg:static lg:translate-x-0 lg:transition-none lg:z-auto">

            <!-- Brand -->
            <div class="px-5 py-5 border-b border-[#C9A227]/15 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#C9A227] to-[#8f7015] flex items-center justify-center flex-shrink-0">
                        <i class="ri-stack-line text-[#0B1220] text-base"></i>
                    </div>
                    <h1 class="text-[17px] font-medium text-[#F3EFE3]">NU Trace</h1>
                </div>
                <button id="sidebarCloseBtn" class="lg:hidden text-[#7C86A0] hover:text-white" aria-label="Close menu">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-4 pt-4">
                <div class="flex items-center gap-2 px-2.5 pb-2.5">
                    <span class="text-xs font-medium text-[#7C86A0]">Main</span>
                    <span class="flex-1 h-px bg-[#C9A227]/15"></span>
                </div>
                     <a href="/users"
                         class="sidebar-item {{ Request::is('users') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-[#B7BFD4] rounded-lg mb-1">
                    <i class="ri-computer-line mr-3 text-lg"></i>
                    <span>My Assets</span>
                </a>

                    <a href="/users/assets"
                       class="sidebar-item {{ Request::is('users/assets*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-[#B7BFD4] rounded-lg mb-1">
                        <i class="ri-archive-line mr-3 text-lg"></i>
                        <span>Assets</span>
                    </a>

                    <a href="/user/requests"
                       class="sidebar-item {{ Request::is('user/requests*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-[#B7BFD4] rounded-lg mb-1">
                        <i class="ri-mail-line mr-3 text-lg"></i>
                        <span>Requests</span>
                    </a>
            </nav>

            <!-- User Info + Logout -->
            <div class="border-t border-[#C9A227]/15 p-4 mt-auto">
            <div class="flex items-center mb-3 p-2.5 rounded-xl bg-[#111B2E]">
                <div class="w-10 h-10 bg-[#1C2740] border border-[#C9A227]/70 rounded-full flex items-center justify-center flex-shrink-0">
                        @if($user && $user->profile_photo_url)
                            <img src="{{ $user->profile_photo_url }}"
                                 class="w-10 h-10 rounded-full object-cover"
                                 alt="Profile"/>
                        @else
                            <span class="text-[#E9C766] font-semibold text-sm">{{ $initial }}</span>
                        @endif
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $user?->display_name ?? 'User' }}</p>
                        <p class="text-xs text-[#7C86A0] truncate">{{ $user?->email ?? 'user@user.com' }}</p>
                    </div>                    </div>
                <a href="/logout" class="w-full flex items-center px-3 py-2 text-sm text-[#B7BFD4] rounded-lg hover:bg-white/5 transition">
                    <i class="ri-logout-box-r-line mr-3 text-lg"></i>
                    <span>Logout</span>
                </a>
            </div>

        </div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
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