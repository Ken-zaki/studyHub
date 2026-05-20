<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Friend Requests - StudyHub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/friend-req.css') }}">

</head>

<body>

    @include('layouts.sidebar', ['activeNav' => 'friend-requests'])

    <main class="main-content">
        <div class="feed-column">

            <header class="page-header">
                <h1 class="page-title">Friend Requests</h1>
                <p class="page-subtitle">Accept, decline, or cancel pending friend requests.</p>
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

            <div class="widget-card request-section">
                <div class="widget-title">Incoming Requests</div>

                @if (empty($incomingRequests))
                    <p class="request-empty">No incoming requests right now.</p>
                @else
                    <div class="request-grid">
                        @foreach ($incomingRequests as $item)
                            @php($request = $item['request'])

                            <div class="request-card">
                                <div class="request-main">
                                    <div class="friend-avatar">
                                        @if (!empty($item['photo']))
                                            <img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}">
                                        @else
                                            {{ $item['initials'] }}
                                        @endif
                                    </div>

                                    <div class="request-text">
                                        <div class="request-name">{{ $item['name'] }}</div>
                                        <div class="request-sub">Sent you a friend request</div>
                                    </div>
                                </div>

                                <div class="request-actions">
                                    <form method="POST"
                                        action="{{ route('friend-requests.accept', ['friendRequest' => $request->id]) }}">
                                        @csrf
                                        <button type="submit" class="request-btn accept">
                                            Accept
                                        </button>
                                    </form>

                                    <form method="POST"
                                        action="{{ route('friend-requests.decline', ['friendRequest' => $request->id]) }}">
                                        @csrf
                                        <button type="submit" class="request-btn decline">
                                            Decline
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="widget-card request-section">
                <div class="widget-title">Outgoing Requests</div>

                @if (empty($outgoingRequests))
                    <p class="request-empty">No outgoing requests waiting for a response.</p>
                @else
                    <div class="request-grid">
                        @foreach ($outgoingRequests as $item)
                            @php($request = $item['request'])

                            <div class="request-card">
                                <div class="request-main">
                                    <div class="friend-avatar">
                                        @if (!empty($item['photo']))
                                            <img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}">
                                        @else
                                            {{ $item['initials'] }}
                                        @endif
                                    </div>

                                    <div class="request-text">
                                        <div class="request-name">{{ $item['name'] }}</div>
                                        <div class="request-sub">Request sent</div>
                                    </div>
                                </div>

                                <div class="request-actions">
                                    <span class="request-badge">Pending</span>

                                    <form method="POST"
                                        action="{{ route('friend-requests.cancel', ['friendRequest' => $request->id]) }}">
                                        @csrf
                                        <button type="submit" class="request-btn cancel">
                                            Cancel
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
        window.SB_URL = '{{ config('services.supabase.url') }}';
        window.SB_ANON = '{{ config('services.supabase.anon_key') }}';
        window.SB_SVC = '{{ config('services.supabase.service_key') }}';
        window.UID = '{{ session('user_id') }}';
    </script>

    <script src="{{ asset('js/notifications.js') }}"></script>
    <script src="{{ asset('js/friend-req.js') }}"></script>


</body>

</html><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Friend Requests - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <style>
        .friend-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 18px 0 24px;
            border-bottom: 1px solid var(--border);
        }

        .friend-tab {
            appearance: none;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-primary);
            padding: 14px 28px;
            border-radius: 16px 16px 0 0;
            font: 700 18px/1 'DM Sans', sans-serif;
            cursor: pointer;
        }

        .friend-tab.active {
            background: #111;
            color: #fff;
            border-color: #111;
        }

        .friend-panel {
            display: none;
        }

        .friend-panel.active {
            display: block;
        }

        .friend-grid {
            display: grid;
            gap: 12px;
        }

        .friend-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #fff;
        }

        .friend-card-main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
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
            color: var(--text-secondary);
            font-size: 13px;
        }

        .friend-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .friend-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .friend-badge.online {
            background: #ecfdf5;
            color: #047857;
        }

        .friend-badge.offline {
            background: #f3f4f6;
            color: #6b7280;
        }

        .friend-status-banner {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid var(--border);
            color: var(--text-primary);
        }

        .friend-empty {
            color: var(--text-secondary);
        }
    </style>
</head>

<body>

    @include('layouts.sidebar', ['activeNav' => 'friend-requests'])

    @php
        $activePanel = request('tab', 'discover');
        if (!in_array($activePanel, ['discover', 'requests', 'friends'], true)) {
            $activePanel = 'discover';
        }
    @endphp

    <main class="main-content">
        <div class="feed-column">
            <header class="page-header">
                <h1 class="page-title">Friends</h1>
                <p class="page-subtitle">Discover people, manage requests, and view your friends.</p>
            </header>

            @if (session('status'))
                <div class="friend-status-banner">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="friend-status-banner" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="friend-tabs" role="tablist" aria-label="Friends tabs">
                <button type="button" class="friend-tab {{ $activePanel === 'discover' ? 'active' : '' }}"
                    data-target="discoverPanel">Discover</button>
                <button type="button" class="friend-tab {{ $activePanel === 'requests' ? 'active' : '' }}"
                    data-target="requestsPanel">Requests</button>
                <button type="button" class="friend-tab {{ $activePanel === 'friends' ? 'active' : '' }}"
                    data-target="friendsPanel">Friends</button>
            </div>

            <section id="discoverPanel" class="friend-panel {{ $activePanel === 'discover' ? 'active' : '' }}"
                aria-labelledby="Discover">
                <div class="widget-card">
                    <div class="widget-title">Discover People</div>
                    @if (empty($discoverProfiles))
                        <p class="friend-empty">No people available right now.</p>
                    @else
                        <div class="friend-grid">
                            @foreach ($discoverProfiles as $person)
                                <div class="friend-card">
                                    <div class="friend-card-main">
                                        <div class="friend-avatar">
                                            @if (!empty($person['photo']))
                                                <img src="{{ $person['photo'] }}" alt="{{ $person['name'] }}">
                                            @else
                                                {{ $person['initials'] }}
                                            @endif
                                        </div>
                                        <div class="friend-card-text">
                                            <div class="friend-card-name">{{ $person['name'] }}</div>
                                            <div class="friend-card-sub">{{ '@' . ($person['username'] ?: 'user') }}
                                            </div>
                                            <div class="friend-card-sub">
                                                <span
                                                    class="friend-badge {{ $person['is_active'] ? 'online' : 'offline' }}">{{ $person['is_active'] ? 'Online' : 'Offline' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="friend-actions">
                                        @if (($person['relationship'] ?? 'available') === 'available')
                                            <form method="POST"
                                                action="{{ route('friend-requests.send', ['receiverId' => $person['id']]) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="profile-upload-btn profile-add-friend-btn">Add
                                                    Friend</button>
                                            </form>
                                        @elseif (($person['relationship'] ?? '') === 'pending_outgoing')
                                            <button type="button" class="profile-upload-btn profile-add-friend-btn"
                                                disabled>Request Sent</button>
                                        @elseif (($person['relationship'] ?? '') === 'pending_incoming')
                                            <a href="{{ route('friend-requests') }}"
                                                class="profile-upload-btn profile-add-friend-btn"
                                                style="display:inline-flex;align-items:center;text-decoration:none;">Check
                                                Requests</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section id="requestsPanel" class="friend-panel {{ $activePanel === 'requests' ? 'active' : '' }}"
                aria-labelledby="Requests">
                <div class="widget-card">
                    <div class="widget-title">Incoming Requests</div>
                    @if (empty($incomingRequests))
                        <p class="friend-empty">No incoming requests right now.</p>
                    @else
                        <div class="friend-grid">
                            @foreach ($incomingRequests as $item)
                                @php($request = $item['request'])
                                <div class="friend-card">
                                    <div class="friend-card-main">
                                        <div class="friend-avatar">
                                            @if (!empty($item['photo']))
                                                <img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}">
                                            @else
                                                {{ $item['initials'] }}
                                            @endif
                                        </div>
                                        <div class="friend-card-text">
                                            <div class="friend-card-name">{{ $item['name'] }}</div>
                                            <div class="friend-card-sub">Sent you a friend request</div>
                                        </div>
                                    </div>
                                    <div class="friend-actions">
                                        <form method="POST"
                                            action="{{ route('friend-requests.accept', ['friendRequest' => $request->id]) }}">
                                            @csrf
                                            <button type="submit"
                                                class="profile-upload-btn profile-add-friend-btn">Accept</button>
                                        </form>
                                        <form method="POST"
                                            action="{{ route('friend-requests.decline', ['friendRequest' => $request->id]) }}">
                                            @csrf
                                            <button type="submit" class="profile-upload-btn profile-add-friend-btn"
                                                style="background:#f3f4f6;color:#374151;">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="widget-card" style="margin-top:16px;">
                    <div class="widget-title">Outgoing Requests</div>
                    @if (empty($outgoingRequests))
                        <p class="friend-empty">No outgoing requests waiting for a response.</p>
                    @else
                        <div class="friend-grid">
                            @foreach ($outgoingRequests as $item)
                                @php($request = $item['request'])
                                <div class="friend-card">
                                    <div class="friend-card-main">
                                        <div class="friend-avatar">
                                            @if (!empty($item['photo']))
                                                <img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}">
                                            @else
                                                {{ $item['initials'] }}
                                            @endif
                                        </div>
                                        <div class="friend-card-text">
                                            <div class="friend-card-name">{{ $item['name'] }}</div>
                                            <div class="friend-card-sub">Request sent</div>
                                        </div>
                                    </div>
                                    <div class="friend-actions">
                                        <span class="friend-badge offline">Pending</span>
                                        <form method="POST"
                                            action="{{ route('friend-requests.cancel', ['friendRequest' => $request->id]) }}">
                                            @csrf
                                            <button type="submit" class="profile-upload-btn profile-add-friend-btn"
                                                style="background:#f3f4f6;color:#374151;">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section id="friendsPanel" class="friend-panel {{ $activePanel === 'friends' ? 'active' : '' }}"
                aria-labelledby="Friends">
                <div class="widget-card">
                    <div class="widget-title">Your Friends</div>
                    @if (empty($friends))
                        <p class="friend-empty">No friends yet. Discover people above!</p>
                    @else
                        <div class="friend-grid">
                            @foreach ($friends as $friend)
                                <div class="friend-card">
                                    <div class="friend-card-main">
                                        <div class="friend-avatar">
                                            @if (!empty($friend['photo']))
                                                <img src="{{ $friend['photo'] }}" alt="{{ $friend['name'] }}">
                                            @else
                                                {{ $friend['initials'] }}
                                            @endif
                                        </div>
                                        <div class="friend-card-text">
                                            <div class="friend-card-name">{{ $friend['name'] }}</div>
                                            <div class="friend-card-sub">{{ '@' . ($friend['username'] ?: 'user') }}
                                            </div>
                                            <div class="friend-card-sub">
                                                <span
                                                    class="friend-badge {{ $friend['is_active'] ? 'online' : 'offline' }}">{{ $friend['is_active'] ? 'Online' : 'Offline' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="friend-actions">
                                        <a href="{{ route('profile.view', ['userId' => $friend['id'], 'name' => $friend['name'], 'photo' => $friend['photo']]) }}"
                                            class="profile-upload-btn profile-add-friend-btn"
                                            style="display:inline-flex;align-items:center;text-decoration:none;">View
                                            Profile</a>
                                        <form method="POST"
                                            action="{{ route('friends.remove', ['friendId' => $friend['id']]) }}">
                                            @csrf
                                            <button type="submit" class="profile-upload-btn profile-add-friend-btn"
                                                style="background:#f3f4f6;color:#374151;">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </main>

    <script>
        const tabs = Array.from(document.querySelectorAll('.friend-tab'));
        const panels = Array.from(document.querySelectorAll('.friend-panel'));

        function activatePanel(targetId) {
            panels.forEach(panel => panel.classList.toggle('active', panel.id === targetId));
            tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.target === targetId));
        }

        tabs.forEach(tab => tab.addEventListener('click', () => activatePanel(tab.dataset.target)));
    </script>

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
