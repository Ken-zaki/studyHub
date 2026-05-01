/* ============================================================
   resources/js/dashboard.js
   Dashboard page logic: posts, attachments, trending, edit/delete/archive
   ============================================================ */

// ── CONFIG (injected from blade via <script> block) ──────────────────────────
// SUPABASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_KEY, and currentUser
// are declared in dashboard.blade.php before this file is loaded.

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

// ── STATE ─────────────────────────────────────────────────────────────────────
let currentAttachment = null;
let editingPostId     = null;

// ── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderCurrentUserUI();
    loadPosts();
    setupFileInputs();
    renderTrending();

    const tipEl = document.getElementById('studyTip');
    if (tipEl) tipEl.textContent = TIPS[Math.floor(Math.random() * TIPS.length)];

    // Close dropdowns on outside click
    document.addEventListener('click', e => {
        if (!e.target.closest('.post-menu-wrap')) {
            document.querySelectorAll('.post-dropdown.open')
                    .forEach(d => d.classList.remove('open'));
        }
    });
});

// ── RENDER USER UI ────────────────────────────────────────────────────────────
function renderCurrentUserUI() {
    const initials = avatarInitials(currentUser.first_name, currentUser.last_name);
    const fullName = [currentUser.first_name, currentUser.last_name].filter(Boolean).join(' ')
                     || currentUser.username || 'You';

    setAvatarEl('sidebarAvatar',    initials, currentUser.profile_photo_url, fullName);
    setAvatarEl('topBarAvatar',     initials, currentUser.profile_photo_url, fullName, true);
    setAvatarEl('createPostAvatar', initials, currentUser.profile_photo_url, fullName);

    const nameEl = document.getElementById('sidebarUserName');
    if (nameEl) nameEl.textContent = fullName;
}

function setAvatarEl(id, initials, photoUrl, alt, isTopBar = false) {
    const el = document.getElementById(id);
    if (!el) return;
    if (photoUrl) {
        const style = isTopBar ? 'width:100%;height:100%;object-fit:cover;border-radius:10px;' : '';
        el.innerHTML = `<img src="${photoUrl}" alt="${alt}" style="${style}">`;
    } else {
        el.textContent = initials;
    }
}

function avatarInitials(first, last) {
    return `${(first || '?')[0]}${(last || '?')[0]}`.toUpperCase();
}

// ── TRENDING ─────────────────────────────────────────────────────────────────
async function renderTrending() {
    const fallback = [['#StudyTips',12],['#Mathematics',9],['#Science',7],['#English',5],['#History',3]];
    let sorted = fallback;

    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?select=subject_tag&is_archived=eq.false`,
            { headers: supabaseHeaders() }
        );
        const rows = await res.json();
        const counts = {};
        rows.forEach(r => { if (r.subject_tag) counts[r.subject_tag] = (counts[r.subject_tag] || 0) + 1; });
        const computed = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 5);
        if (computed.length) sorted = computed;
    } catch { /* use fallback */ }

    const el = document.getElementById('trendingList');
    if (!el) return;
    el.innerHTML = sorted.map(([tag, count], i) => `
        <div class="trend-item">
            <span class="trend-rank">${i + 1}</span>
            <div class="trend-info">
                <div class="trend-tag">${tag.startsWith('#') ? tag : '#' + tag}</div>
                <div class="trend-count">${count} post${count !== 1 ? 's' : ''}</div>
            </div>
        </div>
    `).join('');
}

// ── LOAD POSTS ────────────────────────────────────────────────────────────────
async function loadPosts() {
    const feed = document.getElementById('feed');
    feed.innerHTML = '<div class="loading-state">Loading posts…</div>';
    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?select=*,profiles(username,first_name,last_name,profile_photo_url)&is_archived=eq.false&order=created_at.desc`,
            { headers: supabaseHeaders() }
        );
        if (!res.ok) throw new Error((await res.json()).message || 'Failed to load');
        displayPosts(await res.json());
    } catch (err) {
        feed.innerHTML = `<div class="alert-error">❌ Failed to load posts: ${err.message}</div>`;
    }
}

function displayPosts(posts) {
    const feed = document.getElementById('feed');
    if (!posts.length) {
        feed.innerHTML = '<div class="loading-state">No posts yet. Be the first to post! 🎉</div>';
        return;
    }
    feed.innerHTML = posts.map(createPostHTML).join('');
}

// ── POST HTML ─────────────────────────────────────────────────────────────────
function createPostHTML(post) {
    const author   = post.profiles || {};
    const fullName = `${author.first_name || 'Unknown'} ${author.last_name || 'User'}`;
    const initials = avatarInitials(author.first_name || 'U', author.last_name || 'U');
    const timeAgo  = formatTimeAgo(post.created_at);
    const isOwn    = post.user_id === currentUser.id;
    const profileUrl = `/profile/${encodeURIComponent(post.user_id || '')}?name=${encodeURIComponent(fullName)}&photo=${encodeURIComponent(author.profile_photo_url || '')}&username=${encodeURIComponent(author.username || '')}`;

    const avatarHTML = author.profile_photo_url
        ? `<img src="${author.profile_photo_url}" alt="${escapeHTML(fullName)}">`
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

    const menuHTML = isOwn ? `
        <div class="post-menu-wrap">
            <button class="post-menu-btn" onclick="toggleMenu('${post.id}', event)" title="Options">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="5" r="1.5"/>
                    <circle cx="12" cy="12" r="1.5"/>
                    <circle cx="12" cy="19" r="1.5"/>
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
        </div>` : '';

    return `
        <div class="post-card" id="post-${post.id}">
            <div class="post-header">
                <a class="post-avatar-link" href="${profileUrl}" aria-label="View ${escapeHTML(fullName)} profile">${avatarHTML}</a>
                <div class="post-info">
                    <div class="post-author"><a href="${profileUrl}" class="post-author-link">${escapeHTML(fullName)}</a></div>
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
        </div>`;
}

// ── 3-DOT MENU ────────────────────────────────────────────────────────────────
function toggleMenu(postId, e) {
    e.stopPropagation();
    const menu    = document.getElementById(`menu-${postId}`);
    const wasOpen = menu.classList.contains('open');
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) menu.classList.add('open');
}

// ── EDIT ─────────────────────────────────────────────────────────────────────
function openEditModal(postId) {
    closeAllMenus();
    const card = document.getElementById(`post-${postId}`);
    editingPostId = postId;
    document.getElementById('editContent').value = card.querySelector('.post-text').textContent;
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
            headers: supabaseServiceHeaders(),
            body: JSON.stringify({ content: newContent })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Update failed');
        closeEditModal();
        await loadPosts();
    } catch (err) { alert('Failed to edit: ' + err.message); }
}

// ── ARCHIVE ───────────────────────────────────────────────────────────────────
async function archivePost(postId) {
    closeAllMenus();
    if (!confirm('Archive this post? It will be hidden from the feed.')) return;
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${postId}`, {
            method: 'PATCH',
            headers: supabaseServiceHeaders(),
            body: JSON.stringify({ is_archived: true })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Archive failed');
        document.getElementById(`post-${postId}`)?.remove();
    } catch (err) { alert('Failed to archive: ' + err.message); }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
async function deletePost(postId) {
    closeAllMenus();
    if (!confirm('Delete this post? This cannot be undone.')) return;
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${postId}`, {
            method: 'DELETE',
            headers: supabaseServiceHeaders()
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Delete failed');
        document.getElementById(`post-${postId}`)?.remove();
    } catch (err) { alert('Failed to delete: ' + err.message); }
}

// ── CREATE POST ───────────────────────────────────────────────────────────────
async function createPost() {
    const content    = document.getElementById('postContent').value.trim();
    const postButton = document.getElementById('postButton');

    if (!content) return alert('Please write something!');
    if (!currentUser.id) return alert('Not logged in. Please refresh and log in again.');

    postButton.disabled    = true;
    postButton.textContent = 'Posting…';

    try {
        let mediaUrl = null;
        let postType = 'text';

        if (currentAttachment) {
            if (currentAttachment.type === 'link') {
                postType = 'link';
            } else {
                mediaUrl = await uploadFile(currentAttachment.file, currentAttachment.type);
                postType = currentAttachment.type;
            }
        }

        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts`, {
            method: 'POST',
            headers: { ...supabaseServiceHeaders(), 'Prefer': 'return=representation' },
            body: JSON.stringify({
                user_id:   currentUser.id,
                content,
                post_type: postType,
                media_url: mediaUrl,
                link_url:  currentAttachment?.type === 'link' ? currentAttachment.url : null
            })
        });

        if (!res.ok) {
            const err = await res.json();
            if (err.code === '23503')
                throw new Error('Your user profile was not found in the database. Make sure your account is fully registered.');
            throw new Error(err.message || JSON.stringify(err));
        }

        // Clear form
        document.getElementById('postContent').value = '';
        removeAttachment();
        await loadPosts();

    } catch (err) {
        alert('Failed to create post:\n' + err.message);
    } finally {
        postButton.disabled    = false;
        postButton.textContent = 'Post';
    }
}

// ── FILE UPLOAD ───────────────────────────────────────────────────────────────
async function uploadFile(file, type) {
    const bucket   = type === 'image' ? 'post-images' : type === 'video' ? 'post-videos' : 'post-files';
    const ext      = file.name.split('.').pop();
    const fileName = `${currentUser.id}/${Date.now()}.${ext}`;

    const res = await fetch(`${SUPABASE_URL}/storage/v1/object/${bucket}/${fileName}`, {
        method: 'POST',
        headers: {
            'apikey':        SUPABASE_SERVICE_KEY,
            'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
            'Content-Type':  file.type,
            'x-upsert':      'true'
        },
        body: file
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
    ['imageInput', 'fileInput', 'videoInput'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

// ── INTERACTIONS (placeholders) ───────────────────────────────────────────────
function likePost(id)    { alert('Like functionality coming soon!'); }
function commentPost(id) { alert('Comment functionality coming soon!'); }
function sharePost(id)   { alert('Share functionality coming soon!'); }

// ── SUPABASE HEADER HELPERS ───────────────────────────────────────────────────
function supabaseHeaders() {
    return {
        'apikey':        SUPABASE_ANON_KEY,
        'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
    };
}
function supabaseServiceHeaders() {
    return {
        'apikey':        SUPABASE_SERVICE_KEY,
        'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
        'Content-Type':  'application/json'
    };
}

// ── UTILS ─────────────────────────────────────────────────────────────────────
function closeAllMenus() {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
}

function formatTimeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return `${Math.floor(s / 60)}m ago`;
    if (s < 86400)  return `${Math.floor(s / 3600)}h ago`;
    if (s < 604800) return `${Math.floor(s / 86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}

function escapeHTML(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
