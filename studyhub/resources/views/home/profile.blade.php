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
    <link rel="stylesheet" href="{{ asset('css/newsfeed.css') }}">
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
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">

                        <button class="profile-upload-btn" type="button" id="profilePhotoButton">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
                                <circle cx="12" cy="13" r="4"/>
                            </svg>
                            Change photo
                        </button>

                        <button class="profile-upload-btn" type="button" onclick="openProfileEditModal()">
                            Edit Profile
                        </button>

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

<!-- EDIT PROFILE MODAL -->
<div class="modal-overlay" id="profileEditModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Profile</span>
            <button class="modal-close" type="button" onclick="closeProfileEditModal()">✕</button>
        </div>

        <div style="display:flex; flex-direction:column; gap:14px;">

            <input
                type="text"
                id="editDisplayName"
                class="modal-textarea"
                style="min-height:auto;"
                placeholder="Display Name"
            >

            <textarea
                id="editBio"
                class="modal-textarea"
                placeholder="Write your bio..."
            ></textarea>

        </div>

        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeProfileEditModal()">
                Cancel
            </button>

            <button class="btn-save" onclick="saveProfileEdit()">
                Save Changes
            </button>
        </div>
    </div>
</div>

{{-- ── EDIT POST MODAL ──────────────────────────────────────── --}}
<div class="modal-overlay" id="pf-editModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title">Edit Post</span>
            <button class="modal-close" type="button" onclick="pfCloseEdit()">✕</button>
        </div>

        <div class="edit-vis-row">
            <label style="font-size:13px;font-weight:600;color:var(--text-secondary);">Visibility</label>
            <select id="pf-editVis" class="edit-vis-select">
                <option value="public">🌐 Public</option>
                <option value="only_me">🔒 Only Me</option>
            </select>
        </div>

        <textarea class="modal-textarea" id="pf-editContent" placeholder="Edit your post…"></textarea>

        {{-- Subject tags --}}
        <div style="margin-top:10px;">
            <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:6px;">Subject tags</div>
            <div class="cp-tag-row" id="pf-editTagRow"></div>
        </div>

        <div class="cp-link-preview" id="pf-editLinkPreview" style="display:none;margin-top:10px;"></div>
        <div class="cp-link-row" id="pf-editLinkRow" style="display:none;margin-top:8px;">
            <input type="url" id="pf-editLinkInput" class="cp-link-input"
                placeholder="Paste a URL and press Enter…"
                onkeydown="if(event.key==='Enter'){event.preventDefault();pfFetchEditLink(this.value);}">
        </div>

        <div class="cp-media-preview" id="pf-editMediaPreview" style="margin-top:10px;"></div>
        <div class="cp-file-chips"    id="pf-editFileChips"    style="margin-top:8px;"></div>

        <div class="edit-attach-row" style="margin-top:10px;">
            <button class="cp-attach-btn" onclick="document.getElementById('pf-editMediaInput').click()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;">
                    <rect x="3" y="3" width="18" height="18" rx="3"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <span>Add Photo/Video</span>
            </button>
            <button class="cp-attach-btn" onclick="document.getElementById('pf-editFileInput').click()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                </svg>
                <span>Add File</span>
            </button>
            <button class="cp-attach-btn" onclick="pfToggleEditLink()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;">
                    <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
                </svg>
                <span>Add/Edit Link</span>
            </button>
        </div>
        <input type="file" id="pf-editMediaInput" multiple accept="image/*,video/*" style="display:none;"
            onchange="pfHandleEditMedia(this.files)">
        <input type="file" id="pf-editFileInput"  multiple style="display:none;"
            onchange="pfHandleEditFiles(this.files)">

        <div class="modal-actions" style="margin-top:16px;">
            <button class="btn-cancel" type="button" onclick="pfCloseEdit()">Cancel</button>
            <button class="btn-save"   type="button" id="pf-editSaveBtn" onclick="pfSavePost()">Save Changes</button>
        </div>
    </div>
</div>

{{-- ── COMMENTS DRAWER ──────────────────────────────────────── --}}
<div class="comments-overlay" id="pf-commentsOverlay" onclick="pfCloseComments(event)">
    <div class="comments-drawer">
        <div class="comments-drawer-header">
            <span class="comments-drawer-title">Comments</span>
            <button class="modal-close" type="button" onclick="pfCloseComments(null,true)">✕</button>
        </div>
        <div class="comments-list" id="pf-commentsList">
            <div class="res-loading-sm">Loading comments…</div>
        </div>
        <div class="comments-input-bar">
            <div class="comments-input-avatar" id="pf-commentsAvatar"></div>
            <div class="comments-input-wrap">
                <textarea id="pf-commentInput" class="comments-textarea"
                    placeholder="Write a comment…"
                    oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px';"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();pfSubmitComment();}"></textarea>
                <button class="comments-send-btn" id="pf-commentSend" onclick="pfSubmitComment()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── IMAGE LIGHTBOX ───────────────────────────────────────── --}}
<div class="lightbox-overlay" id="pf-lightbox" onclick="if(event.target===this)pfCloseLightbox()">
    <button class="lightbox-close" onclick="pfCloseLightbox()">✕</button>
    <button class="lightbox-nav lightbox-prev" id="pf-lightboxPrev" onclick="pfLightboxPrev()">‹</button>
    <div class="lightbox-inner">
        <img class="lightbox-img" id="pf-lightboxImg" src="" alt="">
    </div>
    <button class="lightbox-nav lightbox-next" id="pf-lightboxNext" onclick="pfLightboxNext()">›</button>
    <div class="lightbox-counter" id="pf-lightboxCounter"></div>
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
<script src="{{ asset('js/notifications.js') }}"></script>
<script src="{{ asset('js/profile.js') }}"></script>

@include('layouts.admin_bar')
</body>
</html>
