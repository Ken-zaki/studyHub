@php
    $sessionFirstName    = session('user_first_name', 'User');
    $sessionLastName     = session('user_last_name', '');
    $sessionUsername     = session('user_username', '');
    $sessionProfilePhoto = session('user_profile_photo', '');
    $sessionInitials     = strtoupper(substr($sessionFirstName, 0, 1) . substr($sessionLastName, 0, 1));
    $sessionFullName     = trim($sessionFirstName . ' ' . $sessionLastName) ?: $sessionUsername ?: 'You';

    // $activeNav is passed from each page controller
    $activeNav = $activeNav ?? '';
@endphp

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">S</div>
            <span class="logo-text">StudyHub</span>
        </div>
    </div>

    <nav class="sidebar-nav">

        {{-- 1. Dashboard --}}
        <a href="{{ route('dashboard') }}" class="nav-item {{ $activeNav === 'dashboard' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            <span class="nav-text">Dashboard</span>
        </a>

        {{-- 2. Newsfeed --}}
        <a href="{{ route('newsfeed') }}" class="nav-item {{ $activeNav === 'newsfeed' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
            <span class="nav-text">Newsfeed</span>
        </a>

        {{-- 3. Study Groups --}}
        <a href="{{ route('study-groups') }}" class="nav-item {{ $activeNav === 'study-groups' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            <span class="nav-text">Study Groups</span>
        </a>

        {{-- 4. Resources --}}
        <a href="{{ route('resources') }}" class="nav-item {{ $activeNav === 'resources' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
            </svg>
            <span class="nav-text">Resources</span>
        </a>

        {{-- 5. Focus Mode --}}
        <a href="{{ route('focus-mode') }}" class="nav-item {{ $activeNav === 'focus-mode' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <circle cx="12" cy="12" r="6"/>
                <circle cx="12" cy="12" r="2"/>
            </svg>
            <span class="nav-text">Focus Mode</span>
        </a>

        {{-- 6. Messages --}}
        <a href="{{ route('messages') }}" class="nav-item {{ $activeNav === 'messages' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            <span class="nav-text">Messages</span>
        </a>

        {{-- 7. Settings --}}
        <a href="{{ route('settings') }}" class="nav-item {{ $activeNav === 'settings' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
            <span class="nav-text">Settings</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('profile') }}" class="user-profile">
            <div class="user-avatar">
                @if($sessionProfilePhoto)
                    <img src="{{ $sessionProfilePhoto }}" alt="{{ $sessionFullName }}">
                @else
                    {{ $sessionInitials }}
                @endif
            </div>
            <div class="user-info">
                <div class="user-name">{{ $sessionFullName }}</div>
                <div class="user-status">Online</div>
            </div>
        </a>
    </div>
</aside>

<div class="top-bar">
    <a href="{{ route('notifications') }}" class="top-bar-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <span class="notif-dot"></span>
    </a>
    <a href="{{ route('profile') }}" class="top-bar-avatar" title="Your Profile">
        @if($sessionProfilePhoto)
            <img src="{{ $sessionProfilePhoto }}" alt="{{ $sessionFullName }}">
        @else
            {{ $sessionInitials }}
        @endif
    </a>
</div>
