/* ============================================================
   public/js/profile.js  — Own profile page
   ============================================================ */

// ── CONFIG ────────────────────────────────────────────────────
const SB_URL     = document.querySelector('meta[name="data-supabase-url"]')?.content || '';
const SB_KEY     = document.querySelector('meta[name="data-supabase-key"]')?.content || '';
const SB_SVC     = document.querySelector('meta[name="data-supabase-service-key"]')?.content || '';

const currentUser = {
    id:                document.querySelector('meta[name="data-user-id"]')?.content || '',
    first_name:        document.querySelector('meta[name="data-user-first-name"]')?.content || '',
    last_name:         document.querySelector('meta[name="data-user-last-name"]')?.content || '',
    username:          document.querySelector('meta[name="data-user-username"]')?.content || '',
    profile_photo_url: document.querySelector('meta[name="data-user-photo"]')?.content || ''
};

const profileData  = window.profileData || {};
let editingPostId  = null;
let followModalMode = 'followers';

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderUserUI();
    loadProfilePosts();
    loadFollowCounts();

    const photoBtn   = document.getElementById('profilePhotoButton');
    const photoInput = document.getElementById('profilePhotoInput');
    if (photoBtn)   photoBtn.addEventListener('click', () => photoInput?.click());
    if (photoInput) photoInput.addEventListener('change', handleProfilePhotoChange);

    document.addEventListener('click', e => {
        if (!e.target.closest('.post-menu-wrap'))
            document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeEditModal(); closeFollowModal(); }
    });
});

// ── RENDER USER UI ────────────────────────────────────────────
function renderUserUI() {
    const firstName = profileData.first_name || currentUser.first_name;
    const lastName  = profileData.last_name  || currentUser.last_name;
    const username  = profileData.username   || currentUser.username;
    const photoUrl  = profileData.profile_photo_url || currentUser.profile_photo_url;
    const fullName  = profileData.display_name || `${firstName} ${lastName}`.trim() || username || 'You';
    const initials  = mkInitials(firstName, lastName);

    setAvatar('profileAvatarLarge', photoUrl, fullName, initials);
    setAvatar('sidebarAvatar',      photoUrl, fullName, initials);
    setAvatar('topBarAvatar',       photoUrl, fullName, initials);

    setText('profileFullName',  fullName);
    setText('profileUsername',  username ? `@${username}` : '');
    setText('profileBio',       profileData.bio || '');
    setText('profileJoinedDate', fmtJoined(profileData.joined_at));
    setText('statResourceCount', fmtNum(profileData.stats?.resources_uploaded ?? 0));
    setText('statStudySessions',
        `${fmtNum(profileData.stats?.study_sessions_active ?? 0)} / ${fmtNum(profileData.stats?.study_sessions_completed ?? 0)}`);
    setText('statFocusTime', fmtFocus(profileData.stats?.total_focus_seconds ?? 0));

    const sidebarName = document.getElementById('sidebarUserName');
    if (sidebarName) sidebarName.textContent = fullName;
}

// ── LOAD POSTS ────────────────────────────────────────────────
async function loadProfilePosts() {
    const feed = document.getElementById('profileFeed');
    if (!feed || !currentUser.id) {
        if (feed) feed.innerHTML = `<div class="error-state">❌ Not logged in.</div>`;
        return;
    }
    try {
        const res = await sbSvcFetch(
            `${SB_URL}/rest/v1/posts` +
            `?select=*,profiles(username,first_name,last_name,profile_photo_url)` +
            `&user_id=eq.${currentUser.id}` +
            `&order=created_at.desc`
        );
        const posts = await res.json();

        setText('statPostCount',  String(posts.length));
        setText('postCountBadge', `${posts.length} post${posts.length !== 1 ? 's' : ''}`);

        if (!posts.length) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">✏️</div>
                    <h3>No posts yet</h3>
                    <p>Anything you post on the newsfeed will appear here.</p>
                    <a href="/newsfeed" class="empty-state-link">Go to Newsfeed</a>
                </div>`;
            return;
        }
        feed.innerHTML = posts.map(p => postCardHTML(p, true)).join('');
    } catch(err) {
        feed.innerHTML = `<div class="error-state">❌ ${escH(err.message)}</div>`;
        setText('statPostCount',  '—');
        setText('postCountBadge', 'Error');
    }
}

// ── POST CARD HTML ────────────────────────────────────────────
function postCardHTML(post, isOwn = false) {
    const author   = post.profiles || {};
    const name     = `${author.first_name||currentUser.first_name||''} ${author.last_name||currentUser.last_name||''}`.trim() || 'You';
    const photo    = author.profile_photo_url || currentUser.profile_photo_url || '';
    const initials = mkInitials(author.first_name||currentUser.first_name, author.last_name||currentUser.last_name);
    const ago      = timeAgo(post.created_at);
    const visIcon  = { public:'🌐', friends:'👥', only_me:'🔒' }[post.visibility] || '🌐';

    const media  = safeJSON(post.media_urls, []);
    const files  = safeJSON(post.file_urls,  []);
    const link   = safeJSON(post.link_meta,  null);

    let mediaHTML = '';
    if (media.length) {
        mediaHTML = `<div class="post-media-grid count-${Math.min(media.length,4)}">` +
            media.slice(0,4).map((url, i) => {
                const isVid = /\.(mp4|mov|webm)(\?|$)/i.test(url);
                const more  = i === 3 && media.length > 4 ? `<div class="media-more">+${media.length-4}</div>` : '';
                return `<div class="post-media-item">${isVid
                    ? `<video src="${escH(url)}" controls preload="none"></video>`
                    : `<img src="${escH(url)}" alt="" loading="lazy">`}${more}</div>`;
            }).join('') + `</div>`;
    }
    if (files.length) {
        mediaHTML += `<div class="post-files-list">` +
            files.map(f => `<a class="post-file-chip" href="${escH(f.url)}" target="_blank" download>
                📎 <span>${escH(f.name||'File')}</span></a>`).join('') + `</div>`;
    }
    if (link?.url) {
        mediaHTML += `<a class="post-link-preview" href="${escH(link.url)}" target="_blank" rel="noopener">
            <div class="post-link-title">${escH(link.title||link.url)}</div>
            <div class="post-link-url">${escH(link.url)}</div></a>`;
    }

    return `
    <div class="post-card" id="post-${post.id}">
        <div class="post-header">
            <div class="post-avatar">${photo ? `<img src="${escH(photo)}" alt="">` : initials}</div>
            <div class="post-info">
                <div class="post-author">${escH(name)} <span class="post-vis-icon" title="${post.visibility}">${visIcon}</span></div>
                <div class="post-time">${ago}</div>
            </div>
            ${isOwn ? `
            <div class="post-owner-actions">
                <button class="post-action-btn edit-btn" type="button" onclick="openEditModal('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </button>
                <button class="post-action-btn delete-btn" type="button" onclick="deletePost('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    Delete
                </button>
            </div>` : ''}
        </div>
        ${post.content ? `<div class="post-content"><p class="post-text">${escH(post.content)}</p></div>` : ''}
        ${mediaHTML ? `<div class="post-media">${mediaHTML}</div>` : ''}
        <div class="post-footer">
            <button class="post-interact-btn" onclick="likePost('${post.id}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                Like
            </button>
            <button class="post-interact-btn" onclick="commentPost('${post.id}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Comment
            </button>
            <button class="post-interact-btn" onclick="sharePost('${post.id}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                Share
            </button>
        </div>
    </div>`;
}

// ── FOLLOW COUNTS ─────────────────────────────────────────────
async function loadFollowCounts() {
    if (!currentUser.id) return;
    try {
        const [fersRes, fingRes] = await Promise.all([
            sbFetch(`${SB_URL}/rest/v1/follows?following_id=eq.${currentUser.id}&select=id`),
            sbFetch(`${SB_URL}/rest/v1/follows?follower_id=eq.${currentUser.id}&select=id`)
        ]);
        const [fers, fing] = await Promise.all([fersRes.json(), fingRes.json()]);
        setText('followerCount',  String((fers||[]).length));
        setText('followingCount', String((fing||[]).length));
    } catch(e) {}
}

// ── FOLLOWERS / FOLLOWING MODAL ───────────────────────────────
async function openFollowModal(mode) {
    followModalMode = mode;
    setText('followModalTitle', mode === 'followers' ? 'Followers' : 'Following');
    document.getElementById('followModal').classList.add('open');
    const list = document.getElementById('followModalList');
    list.innerHTML = '<div class="loading"><div class="loading-spinner"></div></div>';
    try {
        const fkCol  = mode === 'followers' ? 'follower_id' : 'following_id';
        const filter = mode === 'followers'
            ? `following_id=eq.${currentUser.id}`
            : `follower_id=eq.${currentUser.id}`;
        const res  = await sbFetch(`${SB_URL}/rest/v1/follows?${filter}&select=${fkCol},profiles:${fkCol}(id,first_name,last_name,username,profile_photo_url)`);
        const rows = await res.json();
        if (!rows?.length) { list.innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:14px;">Nobody here yet.</div>'; return; }
        list.innerHTML = rows.map(r => {
            const p    = Array.isArray(r.profiles) ? r.profiles[0] : r.profiles;
            if (!p) return '';
            const name = `${p.first_name||''} ${p.last_name||''}`.trim() || p.username || 'User';
            const ini  = mkInitials(p.first_name, p.last_name);
            return `<a href="/profile/${escH(p.username||p.id)}" class="follow-modal-item">
                <div class="follow-modal-avatar">${p.profile_photo_url
                    ? `<img src="${escH(p.profile_photo_url)}" alt="">` : ini}</div>
                <div>
                    <div class="follow-modal-name">${escH(name)}</div>
                    <div class="follow-modal-username">@${escH(p.username||'')}</div>
                </div>
            </a>`;
        }).join('');
    } catch(e) { list.innerHTML = `<div class="error-state">${escH(e.message)}</div>`; }
}
function closeFollowModal() { document.getElementById('followModal').classList.remove('open'); }

// ── EDIT / DELETE ─────────────────────────────────────────────
function openEditModal(postId) {
    const text = document.querySelector(`#post-${postId} .post-text`)?.textContent || '';
    editingPostId = postId;
    document.getElementById('editContent').value = text;
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    editingPostId = null;
}
async function saveEdit() {
    const content = document.getElementById('editContent').value.trim();
    if (!content) { alert('Content cannot be empty.'); return; }
    try {
        const res = await fetch(`${SB_URL}/rest/v1/posts?id=eq.${editingPostId}`, {
            method: 'PATCH',
            headers: { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}`, 'Content-Type': 'application/json', 'Prefer': 'return=representation' },
            body: JSON.stringify({ content })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Update failed');
        closeEditModal();
        loadProfilePosts();
    } catch(err) { alert('Failed to edit: ' + err.message); }
}
async function deletePost(postId) {
    if (!confirm('Delete this post? This cannot be undone.')) return;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/posts?id=eq.${postId}`, {
            method: 'DELETE',
            headers: { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}` }
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Delete failed');
        document.getElementById(`post-${postId}`)?.remove();
        refreshPostCount();
    } catch(err) { alert('Failed to delete: ' + err.message); }
}
function refreshPostCount() {
    const n = document.querySelectorAll('.post-card').length;
    setText('statPostCount',  String(n));
    setText('postCountBadge', `${n} post${n !== 1 ? 's' : ''}`);
    if (n === 0) loadProfilePosts();
}

// ── PROFILE PHOTO ─────────────────────────────────────────────
function handleProfilePhotoChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        const url = String(reader.result || '');
        if (!url) return;
        currentUser.profile_photo_url = url;
        profileData.profile_photo_url = url;
        ['profileAvatarLarge','sidebarAvatar','topBarAvatar'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = `<img src="${url}" alt="">`;
        });
        fetch('/set-session', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content||'', 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: currentUser.id, first_name: currentUser.first_name, last_name: currentUser.last_name, username: currentUser.username, profile_photo: url })
        }).catch(() => {});
    };
    reader.readAsDataURL(file);
}

// ── INTERACTION STUBS ─────────────────────────────────────────
function likePost(id)    { window.location.href = `/newsfeed#post-${id}`; }
function commentPost(id) { window.location.href = `/newsfeed#post-${id}`; }
function sharePost(id)   { window.location.href = `/newsfeed#post-${id}`; }

// ── HELPERS ───────────────────────────────────────────────────
function sbFetch(url) {
    return fetch(url, { headers: { 'apikey': SB_KEY, 'Authorization': `Bearer ${SB_KEY}` } });
}
function sbSvcFetch(url) {
    const key = SB_SVC || SB_KEY;
    return fetch(url, { headers: { 'apikey': key, 'Authorization': `Bearer ${key}` } });
}
function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setAvatar(id, photoUrl, name, initials) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = photoUrl ? `<img src="${escH(photoUrl)}" alt="${escH(name)}">` : initials;
}
function mkInitials(first, last) {
    return ((first||'?')[0] + (last||'?')[0]).toUpperCase();
}
function fmtJoined(v) {
    if (!v) return 'Recently';
    const d = new Date(v);
    return isNaN(d) ? 'Recently' : new Intl.DateTimeFormat('en',{month:'short',year:'numeric'}).format(d);
}
function fmtNum(v) { const n = Number(v||0); return isFinite(n) ? n.toLocaleString() : '0'; }
function fmtFocus(s) {
    const sec = Number(s||0);
    if (!isFinite(sec)||sec<=0) return '0 min';
    const h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60);
    return h > 0 ? (m > 0 ? `${h}h ${m}m` : `${h}h`) : `${Math.max(1,m)}m`;
}
function timeAgo(ts) {
    const s = Math.floor((Date.now()-new Date(ts))/1000);
    if (s<60) return 'Just now';
    if (s<3600) return `${Math.floor(s/60)}m ago`;
    if (s<86400) return `${Math.floor(s/3600)}h ago`;
    if (s<604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}
function safeJSON(val, fallback) {
    if (!val) return fallback;
    if (typeof val === 'object') return val;
    try { return JSON.parse(val); } catch { return fallback; }
}
function escH(t) {
    if (t==null) return '';
    const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML;
}
