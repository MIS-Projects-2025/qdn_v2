<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Tab icon: the TELFORD logo in public/telford_logo.jpg. If that file is
             missing, fall back to the QDN warning icon so the tab never shows
             a blank/broken icon. --}}
        @if (file_exists(public_path('telford_logo.jpg')))
            <link rel="icon" href="{{ asset('telford_logo.jpg') }}" type="image/jpeg">
        @else
            <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23f59e0b'/%3E%3Cpath d='M16 8v10' stroke='white' stroke-width='3.5' stroke-linecap='round'/%3E%3Ccircle cx='16' cy='23.5' r='2' fill='white'/%3E%3C/svg%3E">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
