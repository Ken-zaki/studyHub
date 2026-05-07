<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'StudyHub')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    @stack('styles')
</head>
<body>
    @yield('content')

    @stack('scripts')
</body>
</html>
