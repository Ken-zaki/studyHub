{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="en">

<head>
    {{-- Inline theme/accent/font-size bootstrap (must run before CSS) --}}
    <script>
        (function() {
            var theme = localStorage.getItem('sh_theme') || 'light';
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var resolved = theme === 'auto' ? (prefersDark ? 'dark' : 'light') : theme;
            document.documentElement.setAttribute('data-theme', resolved);

            var accent = localStorage.getItem('sh_accent');
            if (accent) document.documentElement.style.setProperty('--primary', accent);

            var fontSize = localStorage.getItem('sh_font_size');
            if (fontSize) document.documentElement.style.setProperty('font-size', fontSize + 'px');
        })();
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'StudyHub')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Global stylesheet --}}
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">

    {{-- Page-specific stylesheets (e.g. user_dashboard.css) --}}
    @stack('styles')
</head>

<body>

    @yield('content')

    {{-- Page-specific scripts injected here by each blade via @push('scripts') --}}
    @stack('scripts')

    @include('layouts.admin_bar')
</body>

</html>
