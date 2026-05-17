<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - StudyHub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">

    <style>
        .friend-status-banner {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid var(--border);
            color: var(--text-primary);
        }

        .friends-section {
            margin-bottom: 18px;
        }

        .friends-grid {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .friend-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fff;
            transition: 0.2s ease;
        }

        .friend-card:hover {
            border-color: #d1d5db;
            background: #fafafa;
        }

        .friend-card-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            text-decoration: none;
            color: inherit;
            flex: 1;
        }

        .friend-card-text {
            min-width: 0;
        }

        .friend-card-name {
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .friend-card-sub {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .friend-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .friend-btn {
            border: none;
            border-radius: 10px;
            padding: 7px 13px;
            font: 700 13px/1 'DM Sans', sans-serif;
            cursor: pointer;
            transition: 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .friend-btn:hover {
            transform: translateY(-1px);
        }

        .friend-btn.view {
            background: #111;
            color: #fff;
        }

        .friend-btn.remove {
            background: #f3f4f6;
            color: #374151;
        }

        .friend-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 6px;
            width: fit-content;
        }

        .friend-badge.online {
            background: #ecfdf5;
            color: #047857;
        }

        .friend-badge.offline {
            background: #f3f4f6;
            color: #6b7280;
        }

        .friend-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .friends-empty {
            margin-top: 10px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .friend-card {
                align-items: flex-start;
                flex-direction: column;
            }

            .friend-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>

<body>

    @include('layouts.sidebar', ['activeNav' => 'friends'])

    <main class="main-content">
        <div class="feed-column">

            <header class="page-header">
                <h1 class="page-title">Friends</h1>
                <p class="page-subtitle">People you are currently connected with.</p>
            </header>

            @if (session('status'))
                <div class="friend-status-banner">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="friend-status-banner" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="widget-card friends-section">
                <div class="widget-title">Your Friends</div>

                @if (empty($friends))
                    <p class="friends-empty">You do not have any friends yet.</p>
                @else
                    <div class="friends-grid">
                        @foreach ($friends as $friend)
                            <div class="friend-card">

                                <a href="{{ route('profile.view', ['userId' => $friend['id'], 'name' => $friend['name'], 'photo' => $friend['photo']]) }}"
                                    class="friend-card-main">

                                    <div class="friend-avatar">
                                        @if ($friend['photo'])
                                            <img src="{{ $friend['photo'] }}" alt="{{ $friend['name'] }}">
                                        @else
                                            {{ $friend['initials'] }}
                                        @endif
                                    </div>

                                    <div class="friend-card-text">
                                        <div class="friend-card-name">
                                            {{ $friend['name'] }}
                                        </div>

                                        <div class="friend-card-sub">
                                            {{ $friend['is_active'] ? 'Currently online' : 'Currently offline' }}
                                        </div>

                                        <div class="friend-badge {{ $friend['is_active'] ? 'online' : 'offline' }}">
                                            <span class="friend-badge-dot"></span>
                                            {{ $friend['is_active'] ? 'Online' : 'Offline' }}
                                        </div>
                                    </div>
                                </a>

                                <div class="friend-actions">
                                    <a href="{{ route('profile.view', ['userId' => $friend['id'], 'name' => $friend['name'], 'photo' => $friend['photo']]) }}"
                                        class="friend-btn view">
                                        View
                                    </a>

                                    <form method="POST"
                                        action="{{ route('friends.remove', ['friendId' => $friend['id']]) }}">
                                        @csrf
                                        <button type="submit" class="friend-btn remove">
                                            Remove
                                        </button>
                                    </form>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </main>

    <script>
        const SB_URL = '{{ config('services.supabase.url') }}';
        const SB_ANON = '{{ config('services.supabase.anon_key') }}';
        const SB_SVC = '{{ config('services.supabase.service_key') }}';
        const UID = '{{ session('user_id') }}';
    </script>

    <script src="{{ asset('js/notifications.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => initNotifications());
    </script>

</body>

</html>