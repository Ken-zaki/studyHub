/**
 * Profile View Page - JavaScript
 * Handles viewing other users' profiles and their posts
 */

// ── CONFIG ────────────────────────────────────────────────────────────────────
const SUPABASE_URL = document.querySelector('meta[name="data-supabase-url"]')?.content || '';
const SUPABASE_ANON_KEY = document.querySelector('meta[name="data-supabase-key"]')?.content || '';

// ── CURRENT VIEWED USER ───────────────────────────────────────────────────────
const viewedUserId = document.querySelector('meta[name="data-viewed-user-id"]')?.content || '';
const authUserId = document.querySelector('meta[name="data-current-user-id"]')?.content || '';
const fallbackViewedName = document.querySelector('meta[name="data-viewed-user-name"]')?.content || '';
const fallbackViewedPhoto = document.querySelector('meta[name="data-viewed-user-photo"]')?.content || '';
const fallbackViewedUsername = document.querySelector('meta[name="data-viewed-user-username"]')?.content || '';

function getProfilePhotoUrl(profile) {
    return (
        profile?.profile_photo_url ||
        profile?.profile_photo ||
        profile?.photo_url ||
        profile?.avatar_url ||
        ''
    );
}

function renderAvatarHTML(photoUrl, label, initials) {
    if (photoUrl) {
        return `<img src="${photoUrl}" alt="${escapeHTML(label)}" onerror="this.onerror=null;this.src='';this.parentElement.textContent='${initials}'">`;
    }

    return initials;
}

// ── INITIALIZATION ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadSelf();
    loadViewedUserInfo();
    loadViewedUserPosts();
});

// ── LOAD CURRENT USER INFO ────────────────────────────────────────────────────
async function loadSelf() {
    if (!authUserId) return;

    try {
        const response = await fetch(
            `${SUPABASE_URL}/rest/v1/profiles?id=eq.${authUserId}&select=first_name,last_name,profile_photo_url`,
            {
                headers: {
                    'apikey': SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (!response.ok) throw new Error('Failed to fetch user profile');

        const data = await response.json();
        const profile = Array.isArray(data) ? data[0] : data;

        if (!profile) return;

        // Update top bar avatar
        const topBarAvatar = document.getElementById('topBarAvatar');
        if (topBarAvatar) {
            const topName = `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim() || 'Profile';
            const topInitials = ((profile.first_name?.[0] ?? '') + (profile.last_name?.[0] ?? '')).toUpperCase() || '?';
            topBarAvatar.innerHTML = renderAvatarHTML(getProfilePhotoUrl(profile), topName, topInitials);
        }

        // Update sidebar
        const sidebarAvatar = document.getElementById('sidebarAvatar');
        const sidebarUserName = document.getElementById('sidebarUserName');
        if (sidebarAvatar) {
            sidebarAvatar.innerHTML = topBarAvatar?.innerHTML || '';
        }
        if (sidebarUserName) {
            sidebarUserName.textContent = `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim() || 'Profile';
        }
    } catch (err) {
        console.error('Error loading current user:', err);
    }
}

// ── LOAD VIEWED USER INFO ──────────────────────────────────────────────────────
async function loadViewedUserInfo() {
    if (!viewedUserId) return;

    try {
        const response = await fetch(
            `${SUPABASE_URL}/rest/v1/profiles?id=eq.${viewedUserId}&select=first_name,last_name,username,bio,profile_photo_url,created_at`,
            {
                headers: {
                    'apikey': SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (!response.ok) throw new Error('Failed to fetch viewed user profile');

        const data = await response.json();
        const profile = Array.isArray(data) ? data[0] : data;

        if (!profile) {
            document.getElementById('profileFullName').textContent = fallbackViewedName || 'User not found';
            return;
        }

        const queryName = fallbackViewedName.trim();
        const queryPhoto = fallbackViewedPhoto.trim();
        const queryUsername = fallbackViewedUsername.trim();
        const fullName = `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim() || queryName || 'User';
        const userName = profile.username ?? queryUsername ?? 'user';
        const photoUrl = getProfilePhotoUrl(profile) || queryPhoto;

        // Update profile info
        document.getElementById('profileFullName').textContent = fullName;
        document.getElementById('profileUsername').textContent = `@${userName}`;
        document.getElementById('profileBio').textContent = 
            profile.bio ?? '';
        document.getElementById('profileJoinedDate').textContent = 
            new Date(profile.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });

        // Update avatar
        const avatarEl = document.getElementById('profileAvatarLarge');
        if (avatarEl) {
            const initials = ((profile.first_name?.[0] ?? queryName?.[0] ?? '') + (profile.last_name?.[0] ?? '')).toUpperCase() || 'U';
            avatarEl.innerHTML = renderAvatarHTML(photoUrl, fullName, initials);
        }

        // Load stats
        await loadViewedUserStats(viewedUserId);

    } catch (err) {
        console.error('Error loading viewed user info:', err);
        document.getElementById('profileFullName').textContent = 'Error loading profile';
    }
}

// ── LOAD VIEWED USER STATS ────────────────────────────────────────────────────
async function loadViewedUserStats(userId) {
    try {
        // Load post count
        const postsResponse = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?user_id=eq.${userId}&is_archived=eq.false&select=id`,
            {
                headers: {
                    'apikey': SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (postsResponse.ok) {
            const posts = await postsResponse.json();
            document.getElementById('statPostCount').textContent = Array.isArray(posts) ? posts.length : 0;
        }

        // Set placeholder stats
        document.getElementById('statResourceCount').textContent = '0';
        document.getElementById('statStudySessions').textContent = '0/0';
        document.getElementById('statFocusTime').textContent = '0h';

    } catch (err) {
        console.error('Error loading stats:', err);
    }
}

// ── LOAD VIEWED USER FRIENDS ──────────────────────────────────────────────────
async function loadViewedUserFriends(userId) {
    const friendsList = document.getElementById('friendsList');
    if (!friendsList) return;

    try {
        const response = await fetch(
            `${SUPABASE_URL}/rest/v1/user_friends?user_id=eq.${userId}&is_active=eq.true&select=friend_id,friend_profiles:friend_id(first_name,last_name,profile_photo_url)`,
            {
                headers: {
                    'apikey': SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (!response.ok) {
            friendsList.innerHTML = '<div class="profile-friends-empty">No friends to display</div>';
            return;
        }

        const friends = await response.json();

        if (!Array.isArray(friends) || friends.length === 0) {
            friendsList.innerHTML = '<div class="profile-friends-empty">No friends added yet</div>';
            return;
        }

        friendsList.innerHTML = friends.map(f => {
            const friend = Array.isArray(f.friend_profiles) ? f.friend_profiles[0] : f.friend_profiles;
            if (!friend) return '';
            
            const name = `${friend.first_name ?? ''} ${friend.last_name ?? ''}`.trim() || 'Friend';
            const initials = (friend.first_name?.[0] ?? '') + (friend.last_name?.[0] ?? '');
            const photoUrl = getProfilePhotoUrl(friend);
            
            return `
                <div class="profile-friend-row">
                    <div class="profile-friend-main">
                        <div class="profile-friend-avatar">
                            ${renderAvatarHTML(photoUrl, name, initials.toUpperCase() || 'F')}
                        </div>
                        <div class="profile-friend-name">${escapeHTML(name)}</div>
                    </div>
                </div>`;
        }).join('');

    } catch (err) {
        console.error('Error loading friends:', err);
        friendsList.innerHTML = '<div class="profile-friends-empty">Could not load friends</div>';
    }
}

// ── LOAD VIEWED USER POSTS ────────────────────────────────────────────────────
async function loadViewedUserPosts() {
    const feed = document.getElementById('profileFeed');
    if (!feed) return;

    try {
        const response = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?user_id=eq.${viewedUserId}&is_archived=eq.false&order=created_at.desc&select=*`,
            {
                headers: {
                    'apikey': SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (!response.ok) throw new Error('Failed to fetch posts');

        const posts = await response.json();

        if (!Array.isArray(posts) || posts.length === 0) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>No posts yet</h3>
                    <p>This user has not shared anything yet.</p>
                </div>`;
            document.getElementById('postCountBadge').textContent = '0';
            return;
        }

        document.getElementById('postCountBadge').textContent = posts.length;
        feed.innerHTML = posts.map(createPostHTML).join('');

    } catch (err) {
        console.error('Error loading posts:', err);
        feed.innerHTML = `<div class="error-state">❌ Failed to load posts: ${err.message}</div>`;
    }
}

// ── CREATE POST HTML ──────────────────────────────────────────────────────────
function createPostHTML(post) {
    // Get viewed user's info from the page
    const userNameEl = document.getElementById('profileFullName');
    
    const userName = userNameEl?.textContent || fallbackViewedName || 'User';
    const userInitials = userName.split(' ').map(n => n[0]).join('').toUpperCase() || 'U';
    const timeAgo = formatTimeAgo(post.created_at);

    return `
        <div class="post-card">
            <div class="post-header">
                <div class="post-avatar">${userInitials}</div>
                <div class="post-info">
                    <div class="post-author">${escapeHTML(userName)}</div>
                    <div class="post-time">${timeAgo}</div>
                </div>
            </div>
            <div class="post-content">
                <div class="post-text">${escapeHTML(post.content ?? '')}</div>
            </div>
        </div>`;
}

// ── UTILITIES ─────────────────────────────────────────────────────────────────
function formatTimeAgo(timestamp) {
    const seconds = Math.floor((Date.now() - new Date(timestamp)) / 1000);
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return new Date(timestamp).toLocaleDateString();
}

function escapeHTML(value) {
    const element = document.createElement('div');
    element.textContent = value;
    return element.innerHTML;
}
