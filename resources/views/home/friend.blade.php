<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5f7a;
            --primary-dark: #144d61;
            --secondary: #f59e42;
            --accent: #ff6b6b;
            --bg-main: #fafbfc;
            --bg-sidebar: #ffffff;
            --text-primary: #1a1a1a;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-collapsed);
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .sidebar:hover {
            width: var(--sidebar-width);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Crimson Pro', serif;
            font-weight: 700;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .logo-text {
            font-family: 'Crimson Pro', serif;
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0;
            width: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .sidebar:hover .logo-text {
            opacity: 1;
            width: auto;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            margin-bottom: 4px;
            border-radius: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .nav-item:hover {
            background: var(--bg-main);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(26, 95, 122, 0.08) 0%, rgba(245, 158, 66, 0.08) 100%);
            color: var(--primary);
            font-weight: 600;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 0 4px 4px 0;
        }

        .nav-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .nav-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .sidebar:hover .nav-text {
            opacity: 1;
            width: auto;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--accent);
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            opacity: 0;
            width: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .sidebar:hover .nav-badge {
            opacity: 1;
            width: auto;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .user-profile:hover {
            background: var(--bg-main);
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            opacity: 0;
            width: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .sidebar:hover .user-info {
            opacity: 1;
            width: auto;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .user-status {
            font-size: 12px;
            color: var(--text-light);
        }

        .main-content {
            margin-left: var(--sidebar-collapsed);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            padding: 32px;
        }

        .friends-shell {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            font-family: 'Crimson Pro', serif;
            font-size: 36px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .success-box {
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            color: #065f46;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 14px;
        }

        .error-box {
            background: #fff1f1;
            border: 1px solid #ffd4d4;
            color: #a63030;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 14px;
        }

        .muted-note {
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .content-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .panel {
            background: var(--bg-sidebar);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
        }

        .panel-title {
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--primary);
            font-size: 18px;
        }

        .friends-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .friend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 8px;
            background: var(--bg-main);
            transition: all 0.2s ease;
            justify-content: space-between;
        }

        .friend-item:hover {
            background: rgba(26, 95, 122, 0.08);
        }

        .friend-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .friend-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .friend-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .friend-action {
            display: flex;
            gap: 8px;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: rgba(255, 107, 107, 0.15);
            color: var(--accent);
        }

        .btn-danger:hover {
            background: rgba(255, 107, 107, 0.25);
        }

        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
        }

        .btn-warning {
            background: rgba(245, 158, 66, 0.15);
            color: var(--secondary);
        }

        .btn-warning:hover {
            background: rgba(245, 158, 66, 0.25);
        }

        @media (max-width: 768px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    @php
        $sidebarFirstName = trim((string) session('user_first_name', ''));
        $sidebarLastName = trim((string) session('user_last_name', ''));
        $sidebarUsername = trim((string) session('user_username', ''));
        $sidebarUserId = trim((string) session('user_id', ''));

        $sidebarDisplayName = trim($sidebarFirstName.' '.$sidebarLastName);
        if ($sidebarDisplayName === '') {
            $sidebarDisplayName = $sidebarUsername !== '' ? $sidebarUsername : ($sidebarUserId !== '' ? 'User '.$sidebarUserId : 'Guest User');
        }

        $avatarSeed = trim(($sidebarFirstName !== '' ? $sidebarFirstName : '').' '.($sidebarLastName !== '' ? $sidebarLastName : ''));
        if ($avatarSeed === '') {
            $avatarSeed = $sidebarUsername !== '' ? $sidebarUsername : $sidebarDisplayName;
        }

        $avatarParts = preg_split('/\s+/', $avatarSeed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $avatarInitials = strtoupper(substr($avatarParts[0] ?? 'U', 0, 1).substr($avatarParts[1] ?? '', 0, 1));
    @endphp

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">S</div>
                <span class="logo-text">StudyHub</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                </svg>
                <span class="nav-text">Newsfeed</span>
            </a>

            <a href="{{ route('calendar') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span class="nav-text">Calendar</span>
            </a>

            <a href="{{ route('study-groups') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
                </svg>
                <span class="nav-text">Study Groups</span>
            </a>

            <a href="{{ route('resources') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
                </svg>
                <span class="nav-text">Resources</span>
            </a>

            <a href="{{ route('notifications') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <span class="nav-text">Notifications</span>
            </a>

            <a href="{{ route('messages') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                <span class="nav-text">Messages</span>
            </a>

            <a href="{{ route('friends') }}" class="nav-item active">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
                </svg>
                <span class="nav-text">Friends</span>
            </a>

            <a href="{{ route('settings') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="nav-text">Profile</span>
            </a>

            <a href="{{ route('settings') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                </svg>
                <span class="nav-text">Settings</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile" onclick="window.location.href='{{ route('settings') }}'">
                <div class="user-avatar">{{ $avatarInitials }}</div>
                <div class="user-info">
                    <div class="user-name">{{ $sidebarDisplayName }}</div>
                    <div class="user-status">Online</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="friends-shell">
            <h1 class="page-title">Friends</h1>

            @if (!$currentUserId)
                <p class="muted-note">No active session user found. Please log in first.</p>
            @endif

            @if (session('success'))
                <div class="success-box">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-box">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="content-layout">
                <!-- Current Friends -->
                <section class="panel">
                    <div class="panel-title">Your Friends ({{ $friends->count() }})</div>
                    <div class="friends-list">
                        @forelse ($friends as $friend)
                            <div class="friend-item">
                                <div class="friend-info">
                                    <div class="friend-avatar">
                                        @php
                                            $friendFirstName = trim((string) ($friend->first_name ?? ''));
                                            $friendLastName = trim((string) ($friend->last_name ?? ''));
                                            $friendName = trim($friendFirstName.' '.$friendLastName);
                                            if ($friendName === '') {
                                                $friendName = trim((string) ($friend->name ?? $friend->username ?? 'U'));
                                            }
                                            $parts = preg_split('/\s+/', $friendName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                            $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1).substr($parts[1] ?? '', 0, 1));
                                        @endphp
                                        {{ $initials }}
                                    </div>
                                    <div class="friend-name">{{ $friend->name }}</div>
                                </div>
                                <div class="friend-action">
                                    <form method="POST" action="{{ route('friend.remove', $friend->id) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove this friend?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="muted-note">You don't have any friends yet. Add friends to get started!</p>
                        @endforelse
                    </div>
                </section>

                <!-- Add Friends -->
                <section class="panel">
                    <div class="panel-title">Add Friends ({{ $otherUsers->count() }})</div>
                    <div class="friends-list">
                        @forelse ($otherUsers as $user)
                            <div class="friend-item">
                                <div class="friend-info">
                                    <div class="friend-avatar">
                                        @php
                                            $userFirstName = trim((string) ($user->first_name ?? ''));
                                            $userLastName = trim((string) ($user->last_name ?? ''));
                                            $userName = trim($userFirstName.' '.$userLastName);
                                            if ($userName === '') {
                                                $userName = trim((string) ($user->name ?? $user->username ?? 'U'));
                                            }
                                            $parts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                            $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1).substr($parts[1] ?? '', 0, 1));
                                            $hasPendingRequest = in_array((string) $user->id, $pendingRequestIds ?? []);
                                        @endphp
                                        {{ $initials }}
                                    </div>
                                    <div class="friend-name">{{ $user->name }}</div>
                                </div>
                                <div class="friend-action">
                                    @if ($hasPendingRequest)
                                        <span class="btn btn-warning" style="cursor: default;"><small>Request Pending</small></span>
                                    @else
                                        <form method="POST" action="{{ route('friend-request.send') }}" style="display: inline;">
                                            @csrf
                                            <input type="hidden" name="receiver_id" value="{{ $user->id }}">
                                            <button type="submit" class="btn btn-primary">
                                                Add Friend
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="muted-note">No other users available to add as friends.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
