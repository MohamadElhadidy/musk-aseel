<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin' }} - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @if(app()->getLocale() === 'ar')
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    @endif

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        @if(app()->getLocale()==='ar') body {
            font-family: 'Tajawal', sans-serif;
        }

        @endif


        [x-cloak] {
        display: none !important;
    }
    </style>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #1F2937;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #6B7280;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body class="antialiased bg-gray-100" >
    <div class="min-h-screen flex overflow-hidden" x-data="{ sidebarOpen: false }" x-cloak>
        <!-- Sidebar -->
        <livewire:admin.layout.sidebar />
        <!-- Main content area -->
        <div class="flex-1 flex flex-col transition-all duration-250 ml-64" >
            <!-- Topbar -->
            <livewire:admin.layout.topbar />

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50 mt-16">
                {{ $slot }}
            </main>
        </div>
    </div>


    <livewire:shared.toast />

    @livewireScripts


    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                       open: {{ session('sidebar_collapsed', false) ? 'false' : 'true' }},
            });
            //:class="$store.sidebar.open ? 'ml-64' : 'ml-20'"
        });
    </script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs" defer></script>
</body>

</html>