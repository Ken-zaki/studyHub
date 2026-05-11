<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="data-supabase-url" content="{{ env('SUPABASE_URL') }}">
    <meta name="data-supabase-key" content="{{ env('SUPABASE_ANON_KEY') }}">
    {{-- Pass the viewed user's username; JS will resolve it to a UUID via Supabase --}}
    <meta name="data-viewed-username" content="{{ $username ?? '' }}">
    {{-- If your controller resolves it to a UUID already, also pass it --}}
    <meta name="data-viewed-user-id" content="{{ $userId ?? '' }}">
    <meta name="data-supabase-service-key" content="{{ env('SUPABASE_SERVICE_KEY') }}">
    <meta name="data-current-user-id" content="{{ session('user_id') }}">
    <meta name="data-current-user-username" content="{{ session('user_username') }}">
    <title>Profile - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/profileview.css') }}">
</head>
<body>

@include('layouts.sidebar', ['activeNav' => 'profile'])

<main class="main-content">
    <div class="profile-column">

        @if(session('status'))
            <div class="friend-status-banner" style="margin-bottom:16px;">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="friend-status-banner" style="margin-bottom:16px;background:#fef2f2;color:#991b1b;border-color:#fecaca;">{{ $errors->first() }}</div>
        @endif

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <div class="profile-cover"><div class="profile-cover-pattern"></div></div>
            <div class="profile-body">
                <div class="profile-hero-header">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar-large" id="profileAvatarLarge"></div>
                    </div>
                    <!-- Action buttons (Add Friend + Follow) — rendered by JS to avoid flicker -->
                    <div id="profileActions" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        @if(($userId ?? '') !== session('user_id'))
                            @if(($relationshipState ?? 'none') === 'friends')
                                <button type="button" class="profile-upload-btn profile-add-friend-btn" disabled>Friends</button>
                                <form method="POST" action="{{ route('friends.remove', ['friendId' => $userId ?? '']) }}">
                                    @csrf
                                    <button type="submit" class="profile-upload-btn" style="background:#f3f4f6;color:#374151;">Remove</button>
                                </form>
                            @elseif(($relationshipState ?? 'none') === 'pending_outgoing')
                                <button type="button" class="profile-upload-btn profile-add-friend-btn" disabled>Request Sent</button>
                            @elseif(($relationshipState ?? 'none') === 'pending_incoming' && !empty($pendingRequestId))
                                <form method="POST" action="{{ route('friend-requests.accept', ['friendRequest' => $pendingRequestId]) }}">
                                    @csrf
                                    <button type="submit" class="profile-upload-btn profile-add-friend-btn">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('friend-requests.decline', ['friendRequest' => $pendingRequestId]) }}">
                                    @csrf
                                    <button type="submit" class="profile-upload-btn" style="background:#f3f4f6;color:#374151;">Decline</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('friend-requests.send', ['receiverId' => $userId ?? '']) }}">
                                    @csrf
                                    <button type="submit" class="profile-upload-btn profile-add-friend-btn">Add Friend</button>
                                </form>
                            @endif
                            <!-- Follow button — state managed by JS -->
                            <button type="button" class="pv-follow-btn" id="followBtn" onclick="toggleFollow()">
                                + Follow
                            </button>
                        @endif
                    </div>
                </div>

                <div class="profile-meta">
                    <div>
                        <div class="profile-name" id="profileFullName">Loading…</div>
                        <div class="profile-username" id="profileUsername"></div>
                        <div class="profile-bio" id="profileBio"></div>
                        <div class="profile-meta-row">
                            <div class="profile-meta-pill">Joined <span id="profileJoinedDate">—</span></div>
                        </div>
                    </div>
                </div>

                <!-- Followers / Following counts -->
                <div class="profile-follow-row">
                    <button class="profile-follow-stat" onclick="openFollowModal('followers')">
                        <span class="profile-follow-num" id="followerCount">—</span>
                        <span class="profile-follow-divider"></span>
                        <span class="profile-follow-lbl">Followers</span>
                    </button>
                    <button class="profile-follow-stat" onclick="openFollowModal('following')">
                        <span class="profile-follow-num" id="followingCount">—</span>
                        <span class="profile-follow-divider"></span>
                        <span class="profile-follow-lbl">Following</span>
                    </button>
                </div>

                <div class="profile-insights-row">
                    <div class="profile-stats-list">
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Posts made</div>
                            <div class="stat-value" id="statPostCount">—</div>
                        </div>
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Resources uploaded</div>
                            <div class="stat-value" id="statResourceCount">—</div>
                        </div>
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Study sessions (active/completed)</div>
                            <div class="stat-value" id="statStudySessions">—</div>
                        </div>
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Total focus time</div>
                            <div class="stat-value" id="statFocusTime">—</div>
                        </div>
                    </div>

                    <div class="profile-friends-card">
                        <div class="profile-friends-header">Friends</div>
                        <div class="profile-friends-scroll" id="friendsList">
                            @php $friendList = is_array($profileData['friends'] ?? null) ? $profileData['friends'] : []; @endphp
                            @if(count($friendList) === 0)
                                <div class="profile-friends-empty">No friends added yet.</div>
                            @else
                                @foreach($friendList as $friend)
                                    @php
                                        $friendName = trim((string)($friend['name'] ?? 'Friend'));
                                        $friendPhoto = trim((string)($friend['photo'] ?? ''));
                                        $friendInitials = trim((string)($friend['initials'] ?? ''));
                                        if($friendInitials === '') {
                                            $parts = preg_split('/\s+/', $friendName) ?: [];
                                            $friendInitials = strtoupper(substr((string)($parts[0] ?? 'F'),0,1).substr((string)($parts[1] ?? ''),0,1));
                                        }
                                        $isFriendActive = (bool)($friend['is_active'] ?? false);
                                    @endphp
                                    <div class="profile-friend-row">
                                        <div class="profile-friend-main">
                                            <div class="profile-friend-avatar">
                                                @if($friendPhoto !== '') <img src="{{ $friendPhoto }}" alt="{{ $friendName }}"> @else {{ $friendInitials }} @endif
                                            </div>
                                            <div class="profile-friend-name">{{ $friendName }}</div>
                                        </div>
                                        @if($isFriendActive) <span class="profile-friend-active-dot" title="Active"></span> @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- THEIR POSTS -->
        <div class="section-header">
            <span class="section-title" id="postsTitle">Posts</span>
            <span class="post-count-badge" id="postCountBadge">Loading…</span>
        </div>
        <div id="profileFeed" class="feed">
            <div class="loading"><div class="loading-spinner"></div>Loading posts…</div>
        </div>
    </div>
</main>

<!-- FOLLOWERS / FOLLOWING MODAL -->
<div class="modal-overlay" id="followModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <span class="modal-title" id="followModalTitle">Followers</span>
            <button class="modal-close" type="button" onclick="closeFollowModal()">✕</button>
        </div>
        <div id="followModalList" class="follow-modal-list-wrap">
            <div class="loading"><div class="loading-spinner"></div></div>
        </div>
    </div>
</div>

<script src="{{ asset('js/profileview.js') }}"></script>
</body>
</html>
