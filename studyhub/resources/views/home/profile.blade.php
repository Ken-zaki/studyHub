<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="data-supabase-url" content="{{ env('SUPABASE_URL') }}">
    <meta name="data-supabase-key" content="{{ env('SUPABASE_ANON_KEY') }}">
    <meta name="data-supabase-service-key" content="{{ env('SUPABASE_SERVICE_KEY') }}">
    <meta name="data-user-id" content="{{ session('user_id') }}">
    <meta name="data-user-first-name" content="{{ session('user_first_name') }}">
    <meta name="data-user-last-name" content="{{ session('user_last_name') }}">
    <meta name="data-user-username" content="{{ session('user_username') }}">
    {{-- FIX: ensure the photo URL is passed correctly --}}
    <meta name="data-user-photo" content="{{ session('user_profile_photo') ?? '' }}">
    <title>Profile - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
    <style>
        /* FIX: avatar container must clip the image properly */
        .profile-avatar-large {
            overflow: hidden;
        }
        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: inherit;
        }
        /* FIX: post avatars on the profile feed */
        .post-avatar {
            overflow: hidden;
            flex-shrink: 0;
        }
        .post-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: inherit;
        }
    </style>
</head>
<body>

@include('layouts.sidebar', ['activeNav' => 'profile'])

<main class="main-content">
    <div class="profile-column">

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <div class="profile-cover"><div class="profile-cover-pattern"></div></div>
            <div class="profile-body">
                <div class="profile-hero-header">
                    <div class="profile-avatar-wrap">
                        {{-- FIX: overflow:hidden inline so it always clips regardless of profile.css --}}
                        <div class="profile-avatar-large" id="profileAvatarLarge"
                             style="overflow:hidden;"></div>
                    </div>
                    <div>
                        <button class="profile-upload-btn" type="button" id="profilePhotoButton">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
                                <circle cx="12" cy="13" r="4"/>
                            </svg>
                            Change photo
                        </button>
                        <input type="file" id="profilePhotoInput" accept="image/*" style="display:none;">
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
                        <div class="profile-friends-scroll">
                            @php $friendList = is_array($profileData['friends'] ?? null) ? $profileData['friends'] : []; @endphp
                            @if (count($friendList) === 0)
                                <div class="profile-friends-empty">No friends added yet.</div>
                            @else
                                @foreach ($friendList as $friend)
                                    @php
                                        $friendName = trim((string)($friend['name'] ?? 'Friend'));
                                        $friendPhoto = trim((string)($friend['photo'] ?? ''));
                                        $friendInitials = trim((string)($friend['initials'] ?? ''));
                                        if ($friendInitials === '') {
                                            $parts = preg_split('/\s+/', $friendName) ?: [];
                                            $friendInitials = strtoupper(substr((string)($parts[0] ?? 'F'),0,1).substr((string)($parts[1] ?? ''),0,1));
                                        }
                                        $isFriendActive = (bool)($friend['is_active'] ?? false);
                                    @endphp
                                    <div class="profile-friend-row">
                                        <div class="profile-friend-main">
                                            {{-- FIX: avatar container clips image --}}
                                            <div class="profile-friend-avatar" style="overflow:hidden;">
                                                @if($friendPhoto !== '')
                                                    <img src="{{ $friendPhoto }}" alt="{{ $friendName }}"
                                                         style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
                                                @else
                                                    {{ $friendInitials }}
                                                @endif
                                            </div>
                                            <div class="profile-friend-name">{{ $friendName }}</div>
                                        </div>
                                        @if($isFriendActive)
                                            <span class="profile-friend-active-dot" title="Active"></span>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MY POSTS -->
        <div class="section-header">
            <span class="section-title">My Posts</span>
            <span class="post-count-badge" id="postCountBadge">Loading…</span>
        </div>
        <div id="profileFeed" class="feed">
            <div class="loading"><div class="loading-spinner"></div>Loading your posts…</div>
        </div>
    </div>
</main>

<!-- EDIT POST MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Post</span>
            <button class="modal-close" type="button" onclick="closeEditModal()">✕</button>
        </div>
        <textarea class="modal-textarea" id="editContent" placeholder="Edit your post…"></textarea>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeEditModal()">Cancel</button>
            <button class="btn-save" type="button" onclick="saveEdit()">Save Changes</button>
        </div>
    </div>
</div>

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

<script>
    window.profileData = @json($profileData ?? []);
</script>
<script src="{{ asset('js/profile.js') }}"></script>
</body>
</html>
