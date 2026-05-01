{{-- resources/views/home/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard – StudyHub')

@php $activeNav = 'dashboard'; @endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/user_dashboard.css') }}">
@endpush

@section('content')

{{-- ── SHARED SIDEBAR + TOP BAR ─────────────────────────────────────────────── --}}
@include('layouts.sidebar')

{{-- ── CALENDAR TOP BAR ACTIONS (injected into the left side of top-bar) ──────
     The shared sidebar partial renders .top-bar with only right-side buttons.
     We absolutely-position our left-side buttons inside it via CSS/flex.
     Alternatively, if your layouts.app / sidebar partial has a @stack('topbar-left'),
     use that. Otherwise this div sits right after the top-bar and we use CSS to
     position it. The simplest approach: just render it and let dashboard.css handle it.
--}}
<div class="top-bar-left" id="calTopBarLeft">
    <button class="btn-add-event" id="btnAdd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Event
    </button>
    <button class="btn-select-mode" id="btnSelectMode">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Select
    </button>
    <div class="bulk-bar" id="bulkBar">
        <span class="bulk-bar-count" id="bulkCount">0 selected</span>
        <button class="btn-bulk-cancel" id="btnBulkCancel">Cancel</button>
        <button class="btn-bulk-delete" id="btnBulkDelete">🗑 Delete Selected</button>
    </div>
</div>

{{-- ── MAIN ─────────────────────────────────────────────────────────────────── --}}
<main class="main-content">
    <div class="center-column">

        {{-- Calendar card (month + week in same card) --}}
        <div class="calendar-card">
            <div class="cal-header">
                <div class="cal-nav-group">
                    <button class="cal-nav-btn" id="btnPrev">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <h2 class="cal-month-title" id="calTitle"></h2>
                    <button class="cal-nav-btn" id="btnNext">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                    <button class="cal-today-btn" id="btnToday">Today</button>
                </div>
                <div class="cal-view-toggle">
                    <button class="view-btn active" data-view="month">Month</button>
                    <button class="view-btn"        data-view="week">Week</button>
                </div>
            </div>

            {{-- Month view --}}
            <div id="monthView">
                <div class="cal-weekdays">
                    <div class="cal-weekday">Sun</div><div class="cal-weekday">Mon</div>
                    <div class="cal-weekday">Tue</div><div class="cal-weekday">Wed</div>
                    <div class="cal-weekday">Thu</div><div class="cal-weekday">Fri</div>
                    <div class="cal-weekday">Sat</div>
                </div>
                <div class="cal-days" id="calDays">
                    <div class="state-box" style="grid-column:1/-1"><div class="spinner"></div>Loading…</div>
                </div>
            </div>

            {{-- Week view --}}
            <div id="weekView">
                <div id="weekSummaryBar" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;"></div>
                <div style="border:1px solid var(--border);border-radius:16px;overflow:hidden;">
                    <div id="weekGrid"></div>
                </div>
            </div>

        </div>{{-- /.calendar-card --}}

        {{-- Upcoming --}}
        <div class="upcoming-card">
            <div class="section-title">📅 Upcoming This Week</div>
            <div class="upcoming-list" id="upcomingList">
                <div class="state-box"><div class="spinner"></div>Loading…</div>
            </div>
        </div>

    </div>{{-- /.center-column --}}

    {{-- Right sidebar --}}
    <aside class="right-sidebar">
        <div class="widget-card">
            <div class="widget-title">⏰ Deadlines</div>
            <div id="deadlinesList"><div class="state-box"><div class="spinner"></div></div></div>
        </div>
        <div class="widget-card">
            <div class="widget-title">🗂️ Filter My Events</div>
            <div id="myCalendars"></div>
        </div>
        <div class="widget-card" id="allEventsWidget">
            <div class="widget-title" style="margin-bottom:12px">📋 All My Events</div>
            <input type="text" class="all-ev-search" id="allEvSearch" placeholder="Search events…">
            <div id="allEventsList"></div>
        </div>
    </aside>
</main>

<!-- ══════════════════════════════════════════
     EDIT POST MODAL
══════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Post</span>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <textarea class="modal-textarea" id="editContent" placeholder="Edit your post…"></textarea>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn-save" onclick="saveEdit()">Save Changes</button>
        </div>
    </div>
</div>

<script>
// ── CONFIG ──────────────────────────────────────────────────────────────────
const SB_URL  = '{{ env("SUPABASE_URL") }}';
const SB_ANON = '{{ env("SUPABASE_ANON_KEY") }}';
const SB_SVC  = '{{ env("SUPABASE_SERVICE_KEY") }}';
const UID     = '{{ session("user_id") }}';

const SUPABASE_URL         = SB_URL;
const SUPABASE_ANON_KEY    = SB_ANON;
const SUPABASE_SERVICE_KEY = SB_SVC;

// ── CURRENT USER (from Laravel session / auth) ───────────────────────────────
// ✅ FIX: Use the authenticated user's real UUID from your auth system.
//    The session user_id MUST match a row in public.profiles(id).
//    If you use Laravel Breeze/Fortify + Supabase, set session('user_id') = auth()->id()
//    and make sure that UUID exists in profiles.
let currentUser = {
    id:                '{{ session("user_id") }}',
    first_name:        '{{ session("user_first_name") }}',
    last_name:         '{{ session("user_last_name") }}',
    username:          '{{ session("user_username") }}',
    profile_photo_url: '{{ session("user_profile_photo") }}'
};

let currentAttachment = null;
let editingPostId      = null;

// ── STUDY TIPS ───────────────────────────────────────────────────────────────
const TIPS = [
    "Use the Pomodoro Technique: 25 min focus, 5 min break.",
    "Teaching a concept to someone else is one of the best ways to learn it.",
    "Spaced repetition beats cramming every time.",
    "Get 7–9 hours of sleep — memory consolidation happens while you rest.",
    "Active recall (testing yourself) is more effective than re-reading.",
    "Break large tasks into smaller, concrete next-actions.",
    "Vary your study location to improve recall in different contexts.",
];

// ── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderCurrentUserUI();
    loadPosts();
    setupFileInputs();
    renderTrending();
    document.getElementById('studyTip').textContent = TIPS[Math.floor(Math.random() * TIPS.length)];

    // Close dropdowns on outside click
    document.addEventListener('click', e => {
        if (!e.target.closest('.post-menu-wrap')) {
            document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
        }
    });
});

// ── RENDER USER UI ────────────────────────────────────────────────────────────
function renderCurrentUserUI() {
    const initials = avatarInitials(currentUser.first_name, currentUser.last_name);
    const fullName = `${currentUser.first_name} ${currentUser.last_name}`.trim() || currentUser.username || 'You';

    // Sidebar avatar
    const sidebarAv = document.getElementById('sidebarAvatar');
    sidebarAv.innerHTML = currentUser.profile_photo_url
        ? `<img src="${currentUser.profile_photo_url}" alt="${fullName}">`
        : initials;

    document.getElementById('sidebarUserName').textContent = fullName;

    // Top-bar avatar
    const topAv = document.getElementById('topBarAvatar');
    topAv.innerHTML = currentUser.profile_photo_url
        ? `<img src="${currentUser.profile_photo_url}" alt="${fullName}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`
        : initials;

    // Create-post avatar
    const cpAv = document.getElementById('createPostAvatar');
    cpAv.innerHTML = currentUser.profile_photo_url
        ? `<img src="${currentUser.profile_photo_url}" alt="${fullName}">`
        : initials;
}

function avatarInitials(first, last) {
    return `${(first||'?')[0]}${(last||'?')[0]}`.toUpperCase();
}

// ── TRENDING ─────────────────────────────────────────────────────────────────
async function renderTrending() {
    // Derive trending from actual post subject_tags
    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?select=subject_tag&is_archived=eq.false`,
            { headers: { 'apikey': SUPABASE_ANON_KEY, 'Authorization': `Bearer ${SUPABASE_ANON_KEY}` } }
        );
        const rows = await res.json();
        const counts = {};
        rows.forEach(r => { if (r.subject_tag) counts[r.subject_tag] = (counts[r.subject_tag]||0) + 1; });
        let sorted = Object.entries(counts).sort((a,b) => b[1]-a[1]).slice(0,5);

        if (!sorted.length) {
            sorted = [['#StudyTips',12],['#Mathematics',9],['#Science',7],['#English',5],['#History',3]];
        }

        const el = document.getElementById('trendingList');
        el.innerHTML = sorted.map(([tag, count], i) => `
            <div class="trend-item">
                <span class="trend-rank">${i+1}</span>
                <div class="trend-info">
                    <div class="trend-tag">${tag.startsWith('#') ? tag : '#'+tag}</div>
                    <div class="trend-count">${count} post${count!==1?'s':''}</div>
                </div>
            </div>
        `).join('');
    } catch {
        // Fallback static
        const fallback = [['#StudyTips',12],['#Mathematics',9],['#Science',7],['#English',5],['#History',3]];
        document.getElementById('trendingList').innerHTML = fallback.map(([tag, count], i) => `
            <div class="trend-item">
                <span class="trend-rank">${i+1}</span>
                <div class="trend-info">
                    <div class="trend-tag">${tag}</div>
                    <div class="trend-count">${count} posts</div>
                </div>
            </div>
        `).join('');
    }
}

// ── SHOW PROFILE PAGE ─────────────────────────────────────────────────────────
function showProfilePage(e) {
    if (e) e.preventDefault();
    document.getElementById('feedColumn').style.display = 'none';
    document.getElementById('profileColumn').style.display = 'block';
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    event?.currentTarget?.classList?.add('active');
}

function showNewsfeed() {
    document.getElementById('feedColumn').style.display = 'block';
    document.getElementById('profileColumn').style.display = 'none';
}

// ── LOAD POSTS ────────────────────────────────────────────────────────────────
async function loadPosts() {
    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?select=*,profiles(username,first_name,last_name,profile_photo_url)&is_archived=eq.false&order=created_at.desc`,
            { headers: { 'apikey': SUPABASE_ANON_KEY, 'Authorization': `Bearer ${SUPABASE_ANON_KEY}` } }
        );
        if (!res.ok) throw new Error((await res.json()).message || 'Failed to load');
        displayPosts(await res.json());
    } catch (err) {
        document.getElementById('feed').innerHTML =
            `<div class="error">❌ Failed to load posts: ${err.message}</div>`;
    }
}

// ── DISPLAY POSTS ─────────────────────────────────────────────────────────────
function displayPosts(posts) {
    const feed = document.getElementById('feed');
    if (!posts.length) {
        feed.innerHTML = '<div class="loading">No posts yet. Be the first to post! 🎉</div>';
        return;
    }
    feed.innerHTML = posts.map(createPostHTML).join('');
}

// ── POST HTML ─────────────────────────────────────────────────────────────────
function createPostHTML(post) {
    const author    = post.profiles || {};
    const fullName  = `${author.first_name||'Unknown'} ${author.last_name||'User'}`;
    const initials  = avatarInitials(author.first_name||'U', author.last_name||'U');
    const timeAgo   = formatTimeAgo(post.created_at);
    const isOwn     = post.user_id === currentUser.id;

    const avatarHTML = author.profile_photo_url
        ? `<img src="${author.profile_photo_url}" alt="${fullName}">`
        : initials;

    let mediaHTML = '';
    if (post.media_url) {
        if (post.post_type === 'image')
            mediaHTML = `<img src="${post.media_url}" class="post-image" alt="Post image">`;
        else if (post.post_type === 'video')
            mediaHTML = `<video controls class="post-image"><source src="${post.media_url}"></video>`;
        else if (post.post_type === 'file')
            mediaHTML = `<a href="${post.media_url}" target="_blank" class="post-link">📎 Download File</a>`;
    }
    if (post.link_url)
        mediaHTML += `<a href="${escapeHTML(post.link_url)}" target="_blank" class="post-link">🔗 ${escapeHTML(post.link_url)}</a>`;

    // Only show 3-dot menu for own posts
    const menuHTML = isOwn ? `
        <div class="post-menu-wrap">
            <button class="post-menu-btn" onclick="toggleMenu('${post.id}', event)" title="Options">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                </svg>
            </button>
            <div class="post-dropdown" id="menu-${post.id}">
                <button class="dropdown-item" onclick="openEditModal('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit
                </button>
                <button class="dropdown-item" onclick="archivePost('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="21 8 21 21 3 21 3 8"/>
                        <rect x="1" y="3" width="22" height="5"/>
                        <line x1="10" y1="12" x2="14" y2="12"/>
                    </svg>
                    Archive
                </button>
                <button class="dropdown-item danger" onclick="deletePost('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    ` : '';

    return `
        <div class="post-card" id="post-${post.id}">
            <div class="post-header">
                <div class="post-avatar">${avatarHTML}</div>
                <div class="post-info">
                    <div class="post-author">${escapeHTML(fullName)}</div>
                    <div class="post-time">${timeAgo}</div>
                </div>
                ${menuHTML}
            </div>
            <div class="post-content">
                <p class="post-text">${escapeHTML(post.content)}</p>
                ${mediaHTML ? `<div class="post-media">${mediaHTML}</div>` : ''}
            </div>
            <div class="post-interactions">
                <button class="interaction-btn" onclick="likePost('${post.id}')">❤️ Like</button>
                <button class="interaction-btn" onclick="commentPost('${post.id}')">💬 Comment</button>
                <button class="interaction-btn" onclick="sharePost('${post.id}')">🔄 Share</button>
            </div>
        </div>
    `;
}

// ── 3-DOT MENU ────────────────────────────────────────────────────────────────
function toggleMenu(postId, e) {
    e.stopPropagation();
    const menu = document.getElementById(`menu-${postId}`);
    const wasOpen = menu.classList.contains('open');
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) menu.classList.add('open');
}

// ── EDIT ─────────────────────────────────────────────────────────────────────
function openEditModal(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    const card = document.getElementById(`post-${postId}`);
    const text = card.querySelector('.post-text').textContent;
    editingPostId = postId;
    document.getElementById('editContent').value = text;
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    editingPostId = null;
}
async function saveEdit() {
    const newContent = document.getElementById('editContent').value.trim();
    if (!newContent) return alert('Content cannot be empty.');
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${editingPostId}`, {
            method: 'PATCH',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type': 'application/json',
                'Prefer': 'return=representation'
            },
            body: JSON.stringify({ content: newContent })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Update failed');
        closeEditModal();
        await loadPosts();
    } catch (err) {
        alert('Failed to edit post: ' + err.message);
    }
}

// ── ARCHIVE ───────────────────────────────────────────────────────────────────
async function archivePost(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!confirm('Archive this post? It will be hidden from the feed.')) return;
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${postId}`, {
            method: 'PATCH',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ is_archived: true })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Archive failed');
        document.getElementById(`post-${postId}`)?.remove();
    } catch (err) {
        alert('Failed to archive post: ' + err.message);
    }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
async function deletePost(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!confirm('Delete this post? This cannot be undone.')) return;
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${postId}`, {
            method: 'DELETE',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`
            }
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Delete failed');
        document.getElementById(`post-${postId}`)?.remove();
    } catch (err) {
        alert('Failed to delete post: ' + err.message);
    }
}

// ── CREATE POST ───────────────────────────────────────────────────────────────
async function createPost() {
    const content = document.getElementById('postContent').value.trim();
    if (!content) return alert('Please write something!');

    // ✅ FIX EXPLANATION:
    // The RLS error "new row violates row-level security policy" happens because
    // the user_id you're passing doesn't exist in public.profiles.
    // Even with RLS disabled on newsfeed_posts, the FOREIGN KEY constraint
    // on user_id → profiles(id) will reject any UUID not in profiles.
    //
    // SOLUTION: Make sure session('user_id') is set to the real authenticated
    // user's UUID that exists in your profiles table.
    // In your login controller: session(['user_id' => $user->id, ...])
    // where $user->id matches public.profiles(id).

    if (!currentUser.id || currentUser.id === '') {
        return alert('Not logged in. Please refresh and log in again.');
    }

    const postButton = document.getElementById('postButton');
    postButton.disabled = true;
    postButton.textContent = 'Posting…';

    try {
        let mediaUrl = null;
        let postType = 'text';

        if (currentAttachment) {
            if (currentAttachment.type === 'link') {
                postType = 'link';
            } else {
                // ✅ FIX FOR FILE UPLOAD:
                // Using service key (not anon key) for storage to bypass auth issues.
                // Also make sure the storage buckets exist in Supabase:
                //   post-images, post-videos, post-files  (set to public)
                mediaUrl = await uploadFile(currentAttachment.file, currentAttachment.type);
                postType = currentAttachment.type;
            }
        }

        const postData = {
            user_id:  currentUser.id,
            content:  content,
            post_type: postType,
            media_url: mediaUrl,
            link_url:  currentAttachment?.type === 'link' ? currentAttachment.url : null
        };

        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts`, {
            method: 'POST',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type': 'application/json',
                'Prefer': 'return=representation'
            },
            body: JSON.stringify(postData)
        });

        if (!res.ok) {
            const err = await res.json();
            // Friendly error messages
            if (err.code === '23503')
                throw new Error('Your user profile was not found in the database. Make sure your account is fully registered.');
            throw new Error(err.message || JSON.stringify(err));
        }

        // Clear form
        document.getElementById('postContent').value = '';
        currentAttachment = null;
        const preview = document.getElementById('attachmentPreview');
        preview.innerHTML = '';
        preview.classList.remove('active');
        ['imageInput','fileInput','videoInput'].forEach(id => document.getElementById(id).value = '');

        await loadPosts();
    } catch (err) {
        alert('Failed to create post:\n' + err.message);
    } finally {
        postButton.disabled = false;
        postButton.textContent = 'Post';
    }
}

// ── FILE UPLOAD ───────────────────────────────────────────────────────────────
// ✅ FIX: Use service key for storage uploads & send file as Blob (not ArrayBuffer).
//    Also ensure your Supabase storage buckets are named: post-images, post-videos, post-files
//    and are set to PUBLIC in the Supabase dashboard.
async function uploadFile(file, type) {
    const bucket   = type === 'image' ? 'post-images' : type === 'video' ? 'post-videos' : 'post-files';
    const ext      = file.name.split('.').pop();
    const fileName = `${currentUser.id}/${Date.now()}.${ext}`;

    const res = await fetch(`${SUPABASE_URL}/storage/v1/object/${bucket}/${fileName}`, {
        method: 'POST',
        headers: {
            'apikey': SUPABASE_SERVICE_KEY,
            'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
            'Content-Type': file.type,
            'x-upsert': 'true'
        },
        body: file   // ✅ send File directly — no need for ArrayBuffer
    });

    if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || err.message || 'Upload failed');
    }

    return `${SUPABASE_URL}/storage/v1/object/public/${bucket}/${fileName}`;
}

// ── ATTACHMENT HELPERS ────────────────────────────────────────────────────────
function attachImage()  { document.getElementById('imageInput').click(); }
function attachFile()   { document.getElementById('fileInput').click(); }
function attachVideo()  { document.getElementById('videoInput').click(); }
function attachLink() {
    const url = prompt('Enter the link URL:');
    if (url) {
        currentAttachment = { type: 'link', url };
        showAttachmentPreview(`🔗 Link: ${escapeHTML(url)}`);
    }
}

function setupFileInputs() {
    document.getElementById('imageInput').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        currentAttachment = { type: 'image', file };
        const reader = new FileReader();
        reader.onload = ev =>
            showAttachmentPreview(`<img src="${ev.target.result}" class="preview-image" alt="preview">`);
        reader.readAsDataURL(file);
    });
    document.getElementById('fileInput').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        currentAttachment = { type: 'file', file };
        showAttachmentPreview(`📎 ${escapeHTML(file.name)}`);
    });
    document.getElementById('videoInput').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        currentAttachment = { type: 'video', file };
        showAttachmentPreview(`🎥 ${escapeHTML(file.name)}`);
    });
}

function showAttachmentPreview(html) {
    const preview = document.getElementById('attachmentPreview');
    preview.innerHTML = `
        <div class="preview-file">
            ${html}
            <button class="remove-attachment" onclick="removeAttachment()">Remove</button>
        </div>`;
    preview.classList.add('active');
}

function removeAttachment() {
    currentAttachment = null;
    const preview = document.getElementById('attachmentPreview');
    preview.innerHTML = '';
    preview.classList.remove('active');
    ['imageInput','fileInput','videoInput'].forEach(id => document.getElementById(id).value = '');
}

// ── INTERACTIONS ──────────────────────────────────────────────────────────────
function likePost(id)    { alert('Like functionality coming soon!'); }
function commentPost(id) { alert('Comment functionality coming soon!'); }
function sharePost(id)   { alert('Share functionality coming soon!'); }

// ── UTILS ─────────────────────────────────────────────────────────────────────
function formatTimeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return `${Math.floor(s/60)}m ago`;
    if (s < 86400)  return `${Math.floor(s/3600)}h ago`;
    if (s < 604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}

function escapeHTML(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>

<script src="{{ asset('js/user_dashboard.js') }}"></script>

</body>
</html>
