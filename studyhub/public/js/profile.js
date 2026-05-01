/**
 * Profile Page - JavaScript
 * Handles user profile management, posts, photo upload, and interactions
 */

// ── CONFIG ────────────────────────────────────────────────────────────────────
const SUPABASE_URL = document.querySelector('meta[data-supabase-url]')?.content || '';
const SUPABASE_ANON_KEY = document.querySelector('meta[data-supabase-key]')?.content || '';
const SUPABASE_SERVICE_KEY = document.querySelector('meta[data-supabase-service-key]')?.content || '';

// ── CURRENT USER ──────────────────────────────────────────────────────────────
const currentUser = {
    id: document.querySelector('meta[data-user-id]')?.content || '',
    first_name: document.querySelector('meta[data-user-first-name]')?.content || '',
    last_name: document.querySelector('meta[data-user-last-name]')?.content || '',
    username: document.querySelector('meta[data-user-username]')?.content || '',
    profile_photo_url: document.querySelector('meta[data-user-photo]')?.content || ''
};

const profileData = window.profileData || {};

let editingPostId = null;

// ── INITIALIZATION ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderUserUI();
    loadProfilePosts();

    // Profile photo upload
    const photoBtn = document.getElementById('profilePhotoButton');
    const photoInput = document.getElementById('profilePhotoInput');
    if (photoBtn) photoBtn.addEventListener('click', () => photoInput?.click());
    if (photoInput) photoInput.addEventListener('change', handleProfilePhotoChange);

    // Close menus on outside click
    document.addEventListener('click', e => {
        if (!e.target.closest('.post-menu-wrap')) {
            document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
        }
        if (!e.target.closest('#profileMenuWrap')) {
            document.getElementById('profileMenu')?.classList.remove('open');
        }
    });

    // Profile menu toggle
    const topBarAvatar = document.getElementById('topBarAvatar');
    if (topBarAvatar) {
        topBarAvatar.addEventListener('click', e => {
            e.stopPropagation();
            document.getElementById('profileMenu')?.classList.toggle('open');
        });
    }
});

// ── RENDER USER UI ────────────────────────────────────────────────────────────
function renderUserUI() {
    const firstName = profileData.first_name || currentUser.first_name;
    const lastName = profileData.last_name || currentUser.last_name;
    const username = profileData.username || currentUser.username;
    const photoUrl = profileData.profile_photo_url || currentUser.profile_photo_url;
    const initials = avatarInitials(firstName, lastName);
    const fullName = profileData.display_name
        || `${firstName} ${lastName}`.trim()
        || username
        || 'You';
    const joinedDate = formatJoinedDate(profileData.joined_at);
    const bio = profileData.bio || 'StudyHub learner';

    currentUser.first_name = firstName;
    currentUser.last_name = lastName;
    currentUser.username = username;
    currentUser.profile_photo_url = photoUrl;

    // Sidebar
    const sidebarAv = document.getElementById('sidebarAvatar');
    if (sidebarAv) {
        sidebarAv.innerHTML = photoUrl
            ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}">`
            : initials;
    }
    const sidebarName = document.getElementById('sidebarUserName');
    if (sidebarName) sidebarName.textContent = fullName;

    // Top bar
    const topAv = document.getElementById('topBarAvatar');
    if (topAv) {
        topAv.innerHTML = photoUrl
            ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`
            : initials;
    }

    // Profile hero
    const heroAv = document.getElementById('profileAvatarLarge');
    if (heroAv) {
        heroAv.innerHTML = photoUrl
            ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}">`
            : initials;
    }

    // Profile info
    const fullNameEl = document.getElementById('profileFullName');
    if (fullNameEl) fullNameEl.textContent = fullName;

    const usernameEl = document.getElementById('profileUsername');
    if (usernameEl) usernameEl.textContent = username ? `@${username}` : '';

    const bioEl = document.getElementById('profileBio');
    if (bioEl) bioEl.textContent = bio;

    const joinedEl = document.getElementById('profileJoinedDate');
    if (joinedEl) joinedEl.textContent = joinedDate;

    // Stats
    const resourceCountEl = document.getElementById('statResourceCount');
    if (resourceCountEl) resourceCountEl.textContent = formatCount(profileData.stats?.resources_uploaded ?? 0);

    const studySessionsEl = document.getElementById('statStudySessions');
    if (studySessionsEl) {
        studySessionsEl.textContent = `${formatCount(profileData.stats?.study_sessions_active ?? 0)} active / ${formatCount(profileData.stats?.study_sessions_completed ?? 0)} completed`;
    }

    const focusTimeEl = document.getElementById('statFocusTime');
    if (focusTimeEl) focusTimeEl.textContent = formatFocusTime(profileData.stats?.total_focus_seconds ?? 0);
}

function avatarInitials(first, last) {
    return `${(first || '?')[0]}${(last || '?')[0]}`.toUpperCase();
}

function formatJoinedDate(value) {
    if (!value) return 'Recently';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Recently';
    return new Intl.DateTimeFormat('en', { month: 'short', year: 'numeric' }).format(date);
}

function formatCount(value) {
    const count = Number(value || 0);
    return Number.isFinite(count) ? count.toLocaleString() : '0';
}

function formatFocusTime(totalSeconds) {
    const seconds = Number(totalSeconds || 0);
    if (!Number.isFinite(seconds) || seconds <= 0) return '0 min';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
    }
    return `${Math.max(1, minutes)}m`;
}

function handleProfilePhotoChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
        const dataUrl = String(reader.result || '');
        if (!dataUrl) return;

        currentUser.profile_photo_url = dataUrl;
        profileData.profile_photo_url = dataUrl;

        // Update UI
        const profileAv = document.getElementById('profileAvatarLarge');
        if (profileAv) profileAv.innerHTML = `<img src="${dataUrl}" alt="${escapeHTML(profileData.display_name || 'Profile photo')}">`;

        const sidebarAv = document.getElementById('sidebarAvatar');
        if (sidebarAv) sidebarAv.innerHTML = `<img src="${dataUrl}" alt="${escapeHTML(profileData.display_name || 'Profile photo')}">`;

        const topAv = document.getElementById('topBarAvatar');
        if (topAv) topAv.innerHTML = `<img src="${dataUrl}" alt="${escapeHTML(profileData.display_name || 'Profile photo')}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;

        // Sync with server
        fetch(document.querySelector('form[method="POST"]')?.action || '/set-session', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUser.id,
                first_name: currentUser.first_name,
                last_name: currentUser.last_name,
                username: currentUser.username,
                profile_photo: dataUrl,
                student_type: profileData.student_type || ''
            })
        }).catch(err => {
            console.warn('Session sync failed (UI already updated):', err);
        });
    };

    reader.readAsDataURL(file);
}

// ── LOAD PROFILE POSTS ────────────────────────────────────────────────────────
async function loadProfilePosts() {
    const feed = document.getElementById('profileFeed');
    if (!feed) return;

    if (!currentUser.id || currentUser.id === '') {
        feed.innerHTML = `<div class="error-state">❌ Not logged in. Please <a href="/login">log in</a>.</div>`;
        return;
    }

    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts` +
            `?select=*,profiles(username,first_name,last_name,profile_photo_url)` +
            `&user_id=eq.${currentUser.id}` +
            `&is_archived=eq.false` +
            `&order=created_at.desc`,
            {
                headers: {
                    'apikey': SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (!res.ok) throw new Error((await res.json()).message || 'Failed to load');

        const posts = await res.json();

        // Update counters
        const postCountEl = document.getElementById('statPostCount');
        if (postCountEl) postCountEl.textContent = posts.length;

        const badgeEl = document.getElementById('postCountBadge');
        if (badgeEl) badgeEl.textContent = `${posts.length} post${posts.length !== 1 ? 's' : ''}`;

        if (!posts.length) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">✏️</div>
                    <h3>No posts yet</h3>
                    <p>Anything you post on the newsfeed will appear here.</p>
                    <a href="/dashboard" class="empty-state-link">Go to Newsfeed</a>
                </div>`;
            return;
        }

        feed.innerHTML = posts.map(createPostHTML).join('');

    } catch (err) {
        feed.innerHTML = `<div class="error-state">❌ Failed to load posts: ${err.message}</div>`;
        const postCountEl = document.getElementById('statPostCount');
        if (postCountEl) postCountEl.textContent = '—';
        const badgeEl = document.getElementById('postCountBadge');
        if (badgeEl) badgeEl.textContent = 'Error';
    }
}

// ── POST HTML ─────────────────────────────────────────────────────────────────
function createPostHTML(post) {
    const author = post.profiles || {};
    const firstName = author.first_name || currentUser.first_name || 'Unknown';
    const lastName = author.last_name || currentUser.last_name || 'User';
    const fullName = `${firstName} ${lastName}`.trim();
    const initials = avatarInitials(firstName, lastName);
    const timeAgo = formatTimeAgo(post.created_at);
    const photoUrl = author.profile_photo_url || currentUser.profile_photo_url || '';

    const avatarHTML = photoUrl
        ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}">`
        : initials;

    // Media
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

    return `
        <div class="post-card" id="post-${post.id}">
            <div class="post-header">
                <div class="post-avatar">${avatarHTML}</div>
                <div class="post-info">
                    <div class="post-author"><a href="/profile/${post.user_id}" style="color:inherit; text-decoration:none">${escapeHTML(fullName)}</a></div>
                    <div class="post-time">${timeAgo}</div>
                </div>
                <div class="post-menu-wrap">
                    <button class="post-menu-btn" type="button" onclick="toggleMenu('${post.id}', event)" title="Options">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="1.5"/>
                            <circle cx="12" cy="12" r="1.5"/>
                            <circle cx="12" cy="19" r="1.5"/>
                        </svg>
                    </button>
                    <div class="post-dropdown" id="menu-${post.id}">
                        <button class="dropdown-item" type="button" onclick="openEditModal('${post.id}')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit
                        </button>
                        <button class="dropdown-item" type="button" onclick="archivePost('${post.id}')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="21 8 21 21 3 21 3 8"/>
                                <rect x="1" y="3" width="22" height="5"/>
                                <line x1="10" y1="12" x2="14" y2="12"/>
                            </svg>
                            Archive
                        </button>
                        <button class="dropdown-item danger" type="button" onclick="deletePost('${post.id}')">
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
            </div>
            <div class="post-content">
                <p class="post-text">${escapeHTML(post.content)}</p>
                ${mediaHTML ? `<div class="post-media">${mediaHTML}</div>` : ''}
            </div>
            <div class="post-interactions">
                <button class="interaction-btn" type="button" onclick="likePost('${post.id}')">❤️ Like</button>
                <button class="interaction-btn" type="button" onclick="commentPost('${post.id}')">💬 Comment</button>
                <button class="interaction-btn" type="button" onclick="sharePost('${post.id}')">🔄 Share</button>
            </div>
        </div>`;
}

// ── 3-DOT MENU ────────────────────────────────────────────────────────────────
function toggleMenu(postId, e) {
    e.stopPropagation();
    const menu = document.getElementById(`menu-${postId}`);
    if (!menu) return;
    const wasOpen = menu.classList.contains('open');
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) menu.classList.add('open');
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
function openEditModal(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    const postEl = document.getElementById(`post-${postId}`);
    if (!postEl) return;
    const text = postEl.querySelector('.post-text')?.textContent || '';
    editingPostId = postId;
    const contentEl = document.getElementById('editContent');
    if (contentEl) contentEl.value = text;
    const modal = document.getElementById('editModal');
    if (modal) modal.classList.add('open');
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) modal.classList.remove('open');
    editingPostId = null;
}

async function saveEdit() {
    const contentEl = document.getElementById('editContent');
    const newContent = contentEl?.value.trim() || '';
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
        await loadProfilePosts();
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
        refreshPostCount();
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
        refreshPostCount();
    } catch (err) {
        alert('Failed to delete post: ' + err.message);
    }
}

// ── REFRESH COUNTS ────────────────────────────────────────────────────────────
function refreshPostCount() {
    const remaining = document.querySelectorAll('.post-card').length;
    const postCountEl = document.getElementById('statPostCount');
    if (postCountEl) postCountEl.textContent = remaining;

    const badgeEl = document.getElementById('postCountBadge');
    if (badgeEl) badgeEl.textContent = `${remaining} post${remaining !== 1 ? 's' : ''}`;

    if (remaining === 0) {
        const feed = document.getElementById('profileFeed');
        if (feed) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">✏️</div>
                    <h3>No posts yet</h3>
                    <p>Anything you post on the newsfeed will appear here.</p>
                    <a href="/dashboard" class="empty-state-link">Go to Newsfeed</a>
                </div>`;
        }
    }
}

// ── INTERACTIONS ──────────────────────────────────────────────────────────────
function likePost(id) { alert('Like functionality coming soon!'); }
function commentPost(id) { alert('Comment functionality coming soon!'); }
function sharePost(id) { alert('Share functionality coming soon!'); }

// ── UTILITIES ─────────────────────────────────────────────────────────────────
function formatTimeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60) return 'Just now';
    if (s < 3600) return `${Math.floor(s / 60)}m ago`;
    if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
    if (s < 604800) return `${Math.floor(s / 86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}

function escapeHTML(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
