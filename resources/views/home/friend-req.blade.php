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
    <script src="{{ asset('js/studyhub-core.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
    <script src="{{ asset('js/friend-req.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initNotifications === 'function') {
                initNotifications();
            }
        });
    </script>
    @include('layouts.admin_bar')
</body>

</html>
