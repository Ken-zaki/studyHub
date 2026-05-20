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
    <link rel="stylesheet" href="{{ asset('css/friends.css') }}">

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

                                <a href="{{ route('profile.view', ['userId' => $friend['id']]) }}" class="friend-card-main">

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
                                    <a href="{{ route('profile.view', ['userId' => $friend['id']]) }}" class="friend-btn view">
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
