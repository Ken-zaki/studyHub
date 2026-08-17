/* ============================================================
   public/js/profileview.js  — Viewing another user's profile
   ============================================================ */

// ── CONFIG ────────────────────────────────────────────────────
const SB_URL = document.querySelector('meta[name="data-supabase-url"]')?.content  || '';
const SB_KEY = document.querySelector('meta[name="data-supabase-key"]')?.content  || '';
// Service key is used only for the follow DELETE — RLS blocks anon deletes
const SB_SVC = document.querySelector('meta[name="data-supabase-service-key"]')?.content || SB_KEY;

const viewedUsername   = document.querySelector('meta[name="data-viewed-username"]')?.content   || '';
const viewedUserIdMeta = document.querySelector('meta[name="data-viewed-user-id"]')?.content    || '';
const currentUserId    = document.querySelector('meta[name="data-current-user-id"]')?.content   || '';

let viewedUserId = viewedUserIdMeta;
let isFollowing  = false;

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await resolveViewedUser();
    loadSelfUI();
    loadViewedUserInfo();
    loadViewedUserPosts();
    loadFollowCounts();
    loadFollowState();

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeFollowModal();
    });
    // Close modal on backdrop click
    document.getElementById('followModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('followModal')) closeFollowModal();
    });
});

// ── RESOLVE username → UUID ───────────────────────────────────
async function resolveViewedUser() {
    if (viewedUserId && isUUID(viewedUserId)) return;
    const slug = viewedUsername || viewedUserId;
    if (!slug) return;
    try {
        const res  = await sbGet(`/rest/v1/profiles?username=eq.${encodeURIComponent(slug)}&select=id`);
        const data = await res.json();
        if (data?.[0]?.id) viewedUserId = data[0].id;
    } catch(e) { console.error('Could not resolve user:', e); }
}
function isUUID(s) { return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(s); }

// ── LOAD OWN SIDEBAR / TOPBAR ─────────────────────────────────
async function loadSelfUI() {
    if (!currentUserId) return;
    try {
        const res = await sbGet(`/rest/v1/profiles?id=eq.${currentUserId}&select=first_name,last_name,profile_photo_url`);
        const p   = (await res.json())?.[0];
        if (!p) return;
        const name     = `${p.first_name||''} ${p.last_name||''}`.trim();
        const initials = mkInitials(p.first_name, p.last_name);
        setAvatar('topBarAvatar',  p.profile_photo_url, name, initials);
        setAvatar('sidebarAvatar', p.profile_photo_url, name, initials);
        const sn = document.getElementById('sidebarUserName');
        if (sn) sn.textContent = name;
    } catch(e) {}
}

// ── LOAD VIEWED USER PROFILE ──────────────────────────────────
async function loadViewedUserInfo() {
    if (!viewedUserId) { setText('profileFullName', 'User not found'); return; }
    try {
        const res = await sbGet(
            `/rest/v1/profiles?id=eq.${viewedUserId}` +
            `&select=first_name,last_name,username,bio,profile_photo_url,created_at`
        );
        const p = (await res.json())?.[0];
        if (!p) { setText('profileFullName', 'User not found'); return; }

        const name = `${p.first_name||''} ${p.last_name||''}`.trim() || p.username || 'User';
        setText('profileFullName',  name);
        setText('profileUsername',  p.username ? `@${p.username}` : '');
        setText('profileBio',       p.bio || '');
        setText('profileJoinedDate',
            p.created_at
                ? new Intl.DateTimeFormat('en',{month:'short',year:'numeric'}).format(new Date(p.created_at))
                : '—');
        setText('postsTitle', `${p.first_name || 'User'}'s Posts`);
        setAvatar('profileAvatarLarge', p.profile_photo_url, name, mkInitials(p.first_name, p.last_name));

        loadViewedUserStats();
    } catch(err) {
        setText('profileFullName', 'Error loading profile');
        console.error(err);
    }
}

async function loadViewedUserStats() {
    if (!viewedUserId) return;
    try {
        const res  = await sbGet(`/rest/v1/posts?user_id=eq.${viewedUserId}&visibility=eq.public&select=id`);
        const data = await res.json();
        setText('statPostCount', String((data||[]).length));
    } catch(e) { setText('statPostCount', '—'); }
    setText('statResourceCount', '—');
    setText('statStudySessions', '—');
    setText('statFocusTime',     '—');
}

// ── LOAD POSTS ────────────────────────────────────────────────
async function loadViewedUserPosts() {
    const feed = document.getElementById('profileFeed');
    if (!feed || !viewedUserId) {
        if (feed) feed.innerHTML = `<div class="error-state">❌ Could not determine user.</div>`;
        return;
    }
    try {
        const res   = await sbGet(
            `/rest/v1/posts` +
            `?user_id=eq.${viewedUserId}` +
            `&visibility=eq.public` +
            `&order=created_at.desc` +
            `&select=*,profiles(first_name,last_name,username,profile_photo_url)`
        );
        const posts = await res.json();

        setText('postCountBadge', String(posts.length));
        setText('statPostCount',  String(posts.length));

        if (!posts?.length) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>No posts yet</h3>
                    <p>This user hasn't shared anything publicly.</p>
                </div>`;
            return;
        }
        feed.innerHTML = posts.map(p => postCardHTML(p)).join('');
    } catch(err) {
        feed.innerHTML = `<div class="error-state">❌ ${escH(err.message)}</div>`;
    }
}

function postCardHTML(post) {
    const author   = post.profiles || {};
    const name     = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'User';
    const photo    = author.profile_photo_url || '';
    const initials = mkInitials(author.first_name, author.last_name);
    const ago      = timeAgo(post.created_at);
    const visIcon  = { public:'🌐', friends:'👥', only_me:'🔒' }[post.visibility] || '🌐';

    const media = safeJSON(post.media_urls, []);
    const files = safeJSON(post.file_urls,  []);
    const link  = safeJSON(post.link_meta,  null);

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
            files.map(f => `<a class="post-file-chip" href="${escH(f.url)}" target="_blank" download>📎 <span>${escH(f.name||'File')}</span></a>`).join('') + `</div>`;
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
        </div>
        ${post.content ? `<div class="post-content"><p class="post-text">${escH(post.content)}</p></div>` : ''}
        ${mediaHTML ? `<div class="post-media">${mediaHTML}</div>` : ''}
    </div>`;
}

// ── FOLLOW SYSTEM ─────────────────────────────────────────────
async function loadFollowCounts() {
    if (!viewedUserId) return;
    try {
        const [fersRes, fingRes] = await Promise.all([
            sbGet(`/rest/v1/follows?following_id=eq.${viewedUserId}&select=id`),
            sbGet(`/rest/v1/follows?follower_id=eq.${viewedUserId}&select=id`)
        ]);
        const [fers, fing] = await Promise.all([fersRes.json(), fingRes.json()]);
        setText('followerCount',  String((fers||[]).length));
        setText('followingCount', String((fing||[]).length));
    } catch(e) {}
}

async function loadFollowState() {
    const btn = document.getElementById('followBtn');
    if (!btn) return;
    // Hide follow button if viewing own profile
    if (!currentUserId || !viewedUserId || viewedUserId === currentUserId) {
        btn.style.display = 'none'; return;
    }
    try {
        const res  = await sbGet(
            `/rest/v1/follows?follower_id=eq.${currentUserId}&following_id=eq.${viewedUserId}&select=id`
        );
        const data = await res.json();
        isFollowing = (data||[]).length > 0;
        updateFollowBtn();
    } catch(e) {}
}

async function toggleFollow() {
    if (!currentUserId || !viewedUserId) return;
    const btn = document.getElementById('followBtn');
    if (btn) { btn.disabled = true; }

    try {
        if (isFollowing) {
            // ── UNFOLLOW ──
            // Must use service key because RLS `auth.uid()` won't match when using anon key
            const res = await fetch(
                `${SB_URL}/rest/v1/follows?follower_id=eq.${encodeURIComponent(currentUserId)}&following_id=eq.${encodeURIComponent(viewedUserId)}`,
                {
                    method: 'DELETE',
                    headers: {
                        'apikey':        SB_SVC,
                        'Authorization': `Bearer ${SB_SVC}`,
                        'Content-Type':  'application/json',
                        'Prefer':        'return=minimal'
                    }
                }
            );
            // 200, 204, 404 → all fine
            if (!res.ok && res.status !== 404 && res.status !== 204) {
                const body = await res.text();
                throw new Error(`Unfollow failed (${res.status}): ${body}`);
            }
            isFollowing = false;
        } else {
            // ── FOLLOW ──
            const res = await fetch(`${SB_URL}/rest/v1/follows`, {
                method: 'POST',
                headers: {
                    'apikey':        SB_SVC,
                    'Authorization': `Bearer ${SB_SVC}`,
                    'Content-Type':  'application/json',
                    'Prefer':        'return=minimal'
                },
                body: JSON.stringify({ follower_id: currentUserId, following_id: viewedUserId })
            });
            // 201 = created, 409 = already exists
            if (!res.ok && res.status !== 409) {
                const body = await res.text();
                throw new Error(`Follow failed (${res.status}): ${body}`);
            }
            isFollowing = true;
        }
        updateFollowBtn();
        await loadFollowCounts();
    } catch(e) {
        console.error('Follow toggle error:', e);
        alert('Could not update follow status. Please try again.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

function updateFollowBtn() {
    const btn = document.getElementById('followBtn');
    if (!btn) return;
    if (isFollowing) {
        btn.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;flex-shrink:0;">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Following`;
        btn.classList.add('following');
        btn.title = 'Click to unfollow';
    } else {
        btn.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;flex-shrink:0;">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Follow`;
        btn.classList.remove('following');
        btn.title = 'Follow this user';
    }
}

// ── FOLLOWERS / FOLLOWING MODAL ───────────────────────────────
async function openFollowModal(mode) {
    setText('followModalTitle', mode === 'followers' ? 'Followers' : 'Following');
    document.getElementById('followModal').classList.add('open');
    const list = document.getElementById('followModalList');
    list.innerHTML = '<div class="loading"><div class="loading-spinner"></div></div>';
    try {
        const filter = mode === 'followers'
            ? `following_id=eq.${viewedUserId}`
            : `follower_id=eq.${viewedUserId}`;
        const fkCol  = mode === 'followers' ? 'follower_id' : 'following_id';
        const res    = await sbGet(
            `/rest/v1/follows?${filter}&select=${fkCol},profiles:${fkCol}(id,first_name,last_name,username,profile_photo_url)`
        );
        const rows = await res.json();
        if (!rows?.length) {
            list.innerHTML = `<div class="follow-modal-empty">Nobody here yet.</div>`;
            return;
        }
        list.innerHTML = rows.map(r => {
            const p    = Array.isArray(r.profiles) ? r.profiles[0] : r.profiles;
            if (!p) return '';
            const name = `${p.first_name||''} ${p.last_name||''}`.trim() || p.username || 'User';
            const ini  = mkInitials(p.first_name, p.last_name);
            return `
            <a href="/profile/${escH(p.username||p.id)}" class="follow-modal-item">
                <div class="follow-modal-avatar">
                    ${p.profile_photo_url ? `<img src="${escH(p.profile_photo_url)}" alt="">` : ini}
                </div>
                <div class="follow-modal-info">
                    <div class="follow-modal-name">${escH(name)}</div>
                    <div class="follow-modal-username">@${escH(p.username||'')}</div>
                </div>
                <svg class="follow-modal-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>`;
        }).join('');
    } catch(e) {
        list.innerHTML = `<div class="error-state">${escH(e.message)}</div>`;
    }
}
function closeFollowModal() { document.getElementById('followModal')?.classList.remove('open'); }

// ── HELPERS ───────────────────────────────────────────────────
function sbGet(path) {
    return fetch(`${SB_URL}${path}`, {
        headers: { 'apikey': SB_KEY, 'Authorization': `Bearer ${SB_KEY}` }
    });
}
function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setAvatar(id, photoUrl, name, initials) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = photoUrl ? `<img src="${escH(photoUrl)}" alt="${escH(name||'')}">` : initials;
}
function mkInitials(first, last) { return ((first||'?')[0]+(last||'?')[0]).toUpperCase(); }
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
