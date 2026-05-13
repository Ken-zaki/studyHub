/* ============================================================
   public/js/newsfeed.js  — StudyHub Newsfeed (fixed)
   ============================================================ */

const _sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// ── STATE ────────────────────────────────────────────────────
let currentTab       = 'for_you';
let currentVis       = 'public';
let stagedMedia      = [];
let stagedFiles      = [];
let stagedLink       = null;
let composerOpen     = false;
let activePostId     = null;
let activeReportId   = null;
let activeShareId    = null;
let editingPostId    = null;
let followingSet     = new Set();
let friendSet        = new Set();
let replyingTo       = null;

// Edit-modal staged media/files/link (separate from composer)
let editStagedMedia   = [];   // { file, objectUrl, type } — new uploads
let editStagedFiles   = [];   // { file, name, size } — new file attachments
let editExistingMedia = [];   // existing URLs kept
let editExistingFiles = [];   // existing file objects kept
let editStagedLink    = null; // { url, title, image } — current link in edit modal

// Lightbox
let lightboxImages   = [];
let lightboxIndex    = 0;

const STUDY_TIPS = [
    "Use the Pomodoro Technique: 25 min focus, 5 min break.",
    "Teach what you learned — it's the best way to remember it.",
    "Spaced repetition beats cramming every single time.",
    "Review your notes within 24 hours to retain 80% more.",
    "Sleep consolidates memory — don't pull all-nighters before exams.",
    "Write by hand when studying; it boosts recall over typing.",
    'Ask "why?" for every fact — understanding > memorising.',
];

// ── INIT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    renderAvatar(currentUser, 'cpAvatar');
    renderAvatar(currentUser, 'commentsAvatar');
    document.getElementById('studyTip').textContent =
        STUDY_TIPS[Math.floor(Math.random() * STUDY_TIPS.length)];

    await loadFollowing();
    loadFeed();
    loadWhoToFollow();
    loadTrending();

    document.addEventListener('click', e => {
        if (!e.target.closest('.cp-vis-wrap'))
            document.getElementById('cpVisMenu').style.display = 'none';
        if (!e.target.closest('.post-menu-wrap'))
            document.querySelectorAll('.post-menu-dropdown.open')
                .forEach(el => el.classList.remove('open'));
    });

    // Lightbox keyboard
    document.addEventListener('keydown', e => {
        const lb = document.getElementById('lightbox');
        if (!lb?.classList.contains('open')) return;
        if (e.key === 'ArrowRight') lightboxNext();
        if (e.key === 'ArrowLeft')  lightboxPrev();
        if (e.key === 'Escape')     closeLightbox();
    });
});

// ── AVATAR HELPER ─────────────────────────────────────────────
function renderAvatar(user, elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (user?.profile_photo_url) {
        el.innerHTML = `<img src="${escH(user.profile_photo_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
    } else {
        const fn = user?.first_name || 'U';
        const ln = user?.last_name  || '';
        el.textContent = (fn[0] + (ln[0]||'')).toUpperCase();
    }
}

function avatarHTML(user, size = 40) {
    const radius = Math.round(size * 0.3);
    const fs     = Math.round(size * 0.35);
    const style  = `width:${size}px;height:${size}px;border-radius:${radius}px;
        background:linear-gradient(135deg,var(--primary),var(--primary-dark));
        color:white;font-size:${fs}px;font-weight:700;
        display:flex;align-items:center;justify-content:center;
        flex-shrink:0;overflow:hidden;`;
    if (user?.profile_photo_url) {
        return `<div style="${style}"><img src="${escH(user.profile_photo_url)}" style="width:100%;height:100%;object-fit:cover;"></div>`;
    }
    const fn = user?.first_name || 'U';
    const ln = user?.last_name  || '';
    return `<div style="${style}">${(fn[0]+(ln[0]||'')).toUpperCase()}</div>`;
}

// ── FOLLOW SYSTEM ─────────────────────────────────────────────
async function loadFollowing() {
    if (!currentUser.id) return;

    // Load who we follow via anon key (follows table has open SELECT policy)
    try {
        const { data: fData, error: fErr } = await _sb.from('follows')
            .select('following_id').eq('follower_id', currentUser.id);
        if (fErr) console.warn('[follows error]', fErr.message);
        followingSet = new Set((fData||[]).map(r => r.following_id));
    } catch(e) { console.error('[follows fetch]', e); }

    // FIX: user_friends uses auth.uid() in RLS but we use Laravel sessions,
    // so auth.uid() is always null from Supabase's perspective.
    // The controller fetches friend IDs server-side with the service key
    // and passes them here as SERVER_FRIEND_IDS.
    if (typeof SERVER_FRIEND_IDS !== 'undefined' && Array.isArray(SERVER_FRIEND_IDS)) {
        friendSet = new Set(SERVER_FRIEND_IDS);
        console.log('[Friends] loaded', friendSet.size, 'friend(s) from server:', [...friendSet]);
    } else {
        friendSet = new Set();
        console.warn('[Friends] SERVER_FRIEND_IDS not defined — friends tab will be empty.');
    }
}

async function toggleFollow(userId, btn) {
    if (!currentUser.id) return;
    const isFollowing = followingSet.has(userId);
    try {
        if (isFollowing) {
            await _sb.from('follows')
                .delete().eq('follower_id', currentUser.id).eq('following_id', userId);
            followingSet.delete(userId);
        } else {
            await _sb.from('follows')
                .insert({ follower_id: currentUser.id, following_id: userId });
            followingSet.add(userId);
        }
        document.querySelectorAll(`[data-follow-uid="${userId}"]`).forEach(b => {
            const nowFollowing = followingSet.has(userId);
            b.textContent = nowFollowing ? 'Following' : '+ Follow';
            b.classList.toggle('following', nowFollowing);
        });
    } catch(e) { console.error(e); }
}

// ── TABS ──────────────────────────────────────────────────────
function switchTab(tab) {
    currentTab = tab;
    ['for_you','following','friends'].forEach(t => {
        const map = { for_you:'tabForYou', following:'tabFollowing', friends:'tabFriends' };
        document.getElementById(map[t])?.classList.toggle('active', t === tab);
    });
    loadFeed();
}

// ── FEED LOAD ─────────────────────────────────────────────────
async function loadFeed() {
    const feed = document.getElementById('feed');
    feed.innerHTML = '<div class="loading-state">Loading posts...</div>';
    try {
        const SEL = '*, profiles(id, first_name, last_name, username, profile_photo_url)';
        const allMap = new Map();

        // Build all post queries for this tab, run them IN PARALLEL
        const queries = [];

        if (currentTab === 'for_you') {
            queries.push(
                _sb.from('posts').select(SEL)
                    .eq('visibility', 'public')
                    .order('created_at', { ascending: false }).limit(40)
            );
            if (currentUser.id) {
                queries.push(
                    _sb.from('posts').select(SEL)
                        .eq('user_id', currentUser.id)
                        .order('created_at', { ascending: false }).limit(20)
                );
            }
            if (friendSet.size) {
                queries.push(
                    _sb.from('posts').select(SEL)
                        .in('user_id', [...friendSet])
                        .eq('visibility', 'friends')
                        .order('created_at', { ascending: false }).limit(20)
                );
            }
        } else if (currentTab === 'following') {
            if (!followingSet.size) {
                feed.innerHTML = "<div class=\"feed-empty\"><div class=\"ei\">&#128065;</div><p>You're not following anyone yet.</p></div>";
                return;
            }
            queries.push(
                _sb.from('posts').select(SEL)
                    .in('user_id', [...followingSet])
                    .in('visibility', ['public', 'friends'])
                    .order('created_at', { ascending: false }).limit(40)
            );
            if (currentUser.id) {
                queries.push(
                    _sb.from('posts').select(SEL)
                        .eq('user_id', currentUser.id)
                        .order('created_at', { ascending: false }).limit(10)
                );
            }
        } else if (currentTab === 'friends') {
            if (!friendSet.size) {
                feed.innerHTML = "<div class=\"feed-empty\"><div class=\"ei\">&#128101;</div><p>No friends added yet. Add friends to see their posts here!</p></div>";
                return;
            }
            queries.push(
                _sb.from('posts').select(SEL)
                    .in('user_id', [...friendSet])
                    .in('visibility', ['public', 'friends'])
                    .order('created_at', { ascending: false }).limit(40)
            );
            if (currentUser.id) {
                queries.push(
                    _sb.from('posts').select(SEL)
                        .eq('user_id', currentUser.id)
                        .order('created_at', { ascending: false }).limit(10)
                );
            }
        }

        // Run ALL post queries at the same time
        const results = await Promise.all(queries);
        results.forEach(({ data, error }) => {
            if (error) console.warn('[feed query error]', error.message);
            (data||[]).forEach(p => allMap.set(p.id, p));
        });

        const all = [...allMap.values()].sort((a,b) =>
            new Date(b.created_at) - new Date(a.created_at));

        if (!all.length) {
            feed.innerHTML = "<div class=\"feed-empty\"><div class=\"ei\">&#128205;</div><p>No posts here yet. Be the first to share something!</p></div>";
            return;
        }

        // Render posts immediately with zero counts so the user sees content fast
        feed.innerHTML = all.map(p => postCardHTML(p, 0, false, 0, null)).join('');

        // Then load likes/comments/shares in parallel in the background
        const postIds   = all.map(p => p.id);
        const sharedIds = all.filter(p => p.shared_post_id).map(p => p.shared_post_id);

        const [likeData, commentData, myLikeData, sharedData] = await Promise.all([
            _sb.from('post_likes').select('post_id').in('post_id', postIds),
            _sb.from('post_comments').select('post_id').in('post_id', postIds),
            currentUser.id
                ? _sb.from('post_likes').select('post_id')
                    .in('post_id', postIds).eq('user_id', currentUser.id)
                : Promise.resolve({ data: [] }),
            sharedIds.length
                ? _sb.from('posts').select(SEL).in('id', sharedIds)
                : Promise.resolve({ data: [] }),
        ]);

        const likeCounts     = {};
        const commentCounts  = {};
        const myLikedSet     = new Set();
        const sharedPostsMap = {};
        (likeData.data||[]).forEach(r    => { likeCounts[r.post_id]    = (likeCounts[r.post_id]||0)+1; });
        (commentData.data||[]).forEach(r => { commentCounts[r.post_id] = (commentCounts[r.post_id]||0)+1; });
        (myLikeData.data||[]).forEach(r  => myLikedSet.add(r.post_id));
        (sharedData.data||[]).forEach(p  => sharedPostsMap[p.id] = p);

        // Re-render with full counts and shared post embeds
        feed.innerHTML = all.map(p =>
            postCardHTML(p, likeCounts[p.id]||0, myLikedSet.has(p.id),
                commentCounts[p.id]||0, sharedPostsMap[p.shared_post_id])
        ).join('');

    } catch(err) {
        feed.innerHTML = `<div class="alert-error">❌ Failed to load: ${escH(err.message)}</div>`;
    }
}

// ── POST CARD HTML ────────────────────────────────────────────
function postCardHTML(post, likeCount, isLiked, commentCount, sharedOriginal) {
    const author      = post.profiles || {};
    const isOwn       = post.user_id === currentUser.id;
    const isFollowing = followingSet.has(post.user_id);
    const name        = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'Unknown';
    const ago         = timeAgo(post.created_at);
    const visIcon     = { public:'🌐', friends:'👥', only_me:'🔒' }[post.visibility] || '🌐';

    const media    = parseJSON(post.media_urls,  []);
    const files    = parseJSON(post.file_urls,   []);
    const linkMeta = parseJSON(post.link_meta,   null);

    // ── Shared post embed (like Facebook repost) ──
    let sharedHTML = '';
    if (post.shared_post_id && sharedOriginal) {
        const og       = sharedOriginal;
        const ogAuthor = og.profiles || {};
        const ogName   = `${ogAuthor.first_name||''} ${ogAuthor.last_name||''}`.trim() || ogAuthor.username || 'Unknown';
        const ogMedia  = parseJSON(og.media_urls, []);
        const ogFiles  = parseJSON(og.file_urls,  []);
        const ogLink   = parseJSON(og.link_meta,  null);
        const ogText   = og.content || '';

        let ogMediaHTML = '';
        if (ogMedia.length) {
            ogMediaHTML = buildMediaGrid(ogMedia, og.id + '_og');
        }
        let ogLinkHTML = '';
        if (ogLink?.url) {
            ogLinkHTML = `<a class="post-link-preview" href="${escH(ogLink.url)}" target="_blank" rel="noopener" style="margin:8px 0 0;">
                ${ogLink.image ? `<img class="post-link-img" src="${escH(ogLink.image)}" alt="">` : ''}
                <div class="post-link-info">
                    <div class="post-link-title">${escH(ogLink.title||ogLink.url)}</div>
                    <div class="post-link-url">${escH(ogLink.url)}</div>
                </div>
            </a>`;
        }

        sharedHTML = `
        <div class="shared-post-embed">
            <div class="shared-post-header">
                <a href="/profile/${escH(ogAuthor.username||og.user_id)}" style="text-decoration:none;display:flex;align-items:center;gap:8px;">
                    ${avatarHTML(ogAuthor, 32)}
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--text-primary);">${escH(ogName)}</div>
                        <div style="font-size:11px;color:var(--text-light);">${timeAgo(og.created_at)}</div>
                    </div>
                </a>
            </div>
            ${ogText ? `<div class="post-text" style="font-size:14px;margin-top:8px;">${escH(ogText)}</div>` : ''}
            ${ogMediaHTML}
            ${ogLinkHTML}
        </div>`;
    }

    // ── Media grid ──
    let mediaHTML = '';
    if (media.length) {
        mediaHTML = buildMediaGrid(media, post.id);
    }

    // ── File rows ──
    let filesHTML = '';
    if (files.length) {
        filesHTML = `<div class="post-files">` +
            files.map(f => {
                const fname = f.name || f.url?.split('/').pop() || 'File';
                const fsize = f.size ? formatBytes(f.size) : '';
                return `<a class="post-file-row" href="${escH(f.url)}" target="_blank" download>
                    <span class="post-file-icon">${fileEmojiFromName(fname)}</span>
                    <span class="post-file-name">${escH(fname)}</span>
                    ${fsize ? `<span class="post-file-size">${fsize}</span>` : ''}
                </a>`;
            }).join('') + `</div>`;
    }

    // ── Link preview ──
    let linkHTML = '';
    if (linkMeta?.url) {
        linkHTML = `<a class="post-link-preview" href="${escH(linkMeta.url)}" target="_blank" rel="noopener">
            ${linkMeta.image ? `<img class="post-link-img" src="${escH(linkMeta.image)}" alt="">` : ''}
            <div class="post-link-info">
                <div class="post-link-title">${escH(linkMeta.title||linkMeta.url)}</div>
                <div class="post-link-url">${escH(linkMeta.url)}</div>
            </div>
        </a>`;
    }

    // ── Post text ──
    const text     = post.content || '';
    const longText = text.length > 300;
    const textHTML = text ? `
        <div class="post-text ${longText ? 'collapsed' : ''}" id="postText-${post.id}">${escH(text)}</div>
        ${longText ? `<button class="post-see-more" onclick="togglePostText('${post.id}',this)">See more</button>` : ''}
    ` : '';

    const menuItems = isOwn
        ? `<button class="post-menu-item" onclick="openEditPost('${post.id}')">✏️ Edit Post</button>
           <button class="post-menu-item danger" onclick="deletePost('${post.id}')">🗑️ Delete Post</button>`
        : `<button class="post-menu-item" onclick="openReportModal('${post.id}')">🚩 Report Post</button>`;

    const followBtn = (!isOwn && post.user_id) ? `
        <button class="post-follow-btn ${isFollowing ? 'following' : ''}"
            data-follow-uid="${post.user_id}"
            onclick="toggleFollow('${post.user_id}', this)">
            ${isFollowing ? 'Following' : '+ Follow'}
        </button>` : '';

    const commentLabel = commentCount > 0
        ? `💬 ${commentCount} comment${commentCount!==1?'s':''}` : '';
    const likeLabel = likeCount > 0
        ? `❤️ ${likeCount} like${likeCount!==1?'s':''}` : '';

    return `
    <div class="post-card" id="post-${post.id}">
        <div class="post-header">
            <div class="post-author">
                <a href="/profile/${escH(author.username||post.user_id)}"
                   class="post-author-avatar-link" title="View profile"
                   onclick="event.stopPropagation()">
                    ${avatarHTML(author, 40)}
                </a>
                <div>
                    <div class="post-author-name">
                        <a href="/profile/${escH(author.username||post.user_id)}"
                           class="post-author-name-link"
                           onclick="event.stopPropagation()">${escH(name)}</a>
                        ${followBtn}
                    </div>
                    <div class="post-author-meta">
                        <span>${ago}</span>
                        <span>·</span>
                        <span class="post-vis-badge" title="${post.visibility}">${visIcon}</span>
                    </div>
                </div>
            </div>
            <div class="post-menu-wrap">
                <button class="post-menu-btn" onclick="togglePostMenu('${post.id}')">⋯</button>
                <div class="post-menu-dropdown" id="postMenu-${post.id}">
                    ${menuItems}
                </div>
            </div>
        </div>

        ${text || media.length || files.length || linkMeta || sharedHTML ? `<div class="post-body">
            ${textHTML}
        </div>` : ''}

        ${mediaHTML}
        ${filesHTML}
        ${linkHTML}
        ${sharedHTML ? `<div style="padding:0 16px 12px;">${sharedHTML}</div>` : ''}

        <div class="post-counts">
            <div class="post-counts-likes" onclick="openLikesModal('${post.id}')" style="cursor:pointer;">
                ${likeLabel}
            </div>
            <div class="post-counts-comments" onclick="openComments('${post.id}')" id="commentCount-${post.id}">
                ${commentLabel}
            </div>
        </div>

        <div class="post-actions-bar">
            <button class="post-action-btn ${isLiked ? 'liked' : ''}"
                id="likeBtn-${post.id}"
                onclick="toggleLike('${post.id}', this)">
                <svg viewBox="0 0 24 24" fill="${isLiked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                </svg>
                Like
            </button>
            <button class="post-action-btn" onclick="openComments('${post.id}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                Comment
            </button>
            <button class="post-action-btn" onclick="openShareModal('${post.id}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Share
            </button>
        </div>
    </div>`;
}

// ── BUILD MEDIA GRID (with lightbox) ─────────────────────────
function buildMediaGrid(media, postId) {
    const cls = media.length === 1 ? 'count-1' :
                media.length === 2 ? 'count-2' :
                media.length === 3 ? 'count-3' :
                media.length === 4 ? 'count-4' : 'count-many';
    const shown     = media.slice(0, 5);
    const moreCount = media.length - 5;

    // FIX: imageUrls is a plain array (not pre-stringified).
    // We pass it via a data attribute to avoid quoting hell in onclick.
    const imageUrls = media.filter(u => !/\.(mp4|mov|webm|ogg)(\?|$)/i.test(u));

    // Build a per-image index map: shown[i] → index inside imageUrls
    let imgCounter = 0;

    return `<div class="post-media ${cls}">` +
        shown.map((url, i) => {
            const isVideo = /\.(mp4|mov|webm|ogg)(\?|$)/i.test(url);
            const inner   = isVideo
                ? `<video src="${escH(url)}" controls preload="none"></video>`
                : `<img src="${escH(url)}" alt="" loading="lazy">`;
            const overlay = (i === 4 && moreCount > 0)
                ? `<div class="media-more-overlay">+${moreCount}</div>` : '';

            if (isVideo) {
                return `<div class="post-media-item">${inner}${overlay}</div>`;
            }
            // FIX: use a data attribute for the URL list; pass the correct image index
            const imgIdx = imgCounter++;
            // Encode the array as a base64 data attribute to avoid any quoting issues
            const safeJson = btoa(unescape(encodeURIComponent(JSON.stringify(imageUrls))));
            return `<div class="post-media-item" style="cursor:zoom-in;"
                onclick="openLightboxB64('${safeJson}', ${imgIdx})"
            >${inner}${overlay}</div>`;
        }).join('') + `</div>`;
}

// FIX: separate lightbox opener that decodes the base64-encoded URL list safely
function openLightboxB64(b64, startIndex) {
    try {
        const json = decodeURIComponent(escape(atob(b64)));
        lightboxImages = JSON.parse(json);
    } catch(e) {
        console.error('lightbox decode error', e);
        return;
    }
    lightboxIndex = startIndex;
    renderLightboxImage();
    document.getElementById('lightbox')?.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function togglePostText(postId, btn) {
    const el = document.getElementById(`postText-${postId}`);
    if (!el) return;
    const isCollapsed = el.classList.contains('collapsed');
    el.classList.toggle('collapsed', !isCollapsed);
    btn.textContent = isCollapsed ? 'See less' : 'See more';
}

// ── LIGHTBOX ──────────────────────────────────────────────────
function openLightbox(imagesJson, startIndex) {
    // Legacy path — safe JSON parse
    try {
        lightboxImages = typeof imagesJson === 'string' ? JSON.parse(imagesJson) : imagesJson;
    } catch(e) { console.error('lightbox parse error', e); return; }
    lightboxIndex = startIndex;
    renderLightboxImage();
    document.getElementById('lightbox')?.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function renderLightboxImage() {
    const img = document.getElementById('lightboxImg');
    const counter = document.getElementById('lightboxCounter');
    if (img) img.src = lightboxImages[lightboxIndex] || '';
    if (counter) counter.textContent = `${lightboxIndex + 1} / ${lightboxImages.length}`;
    document.getElementById('lightboxPrev').style.display = lightboxImages.length > 1 ? '' : 'none';
    document.getElementById('lightboxNext').style.display = lightboxImages.length > 1 ? '' : 'none';
}

function lightboxNext() {
    lightboxIndex = (lightboxIndex + 1) % lightboxImages.length;
    renderLightboxImage();
}
function lightboxPrev() {
    lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length;
    renderLightboxImage();
}
function closeLightbox() {
    document.getElementById('lightbox')?.classList.remove('open');
    document.body.style.overflow = '';
}

// ── LIKE ──────────────────────────────────────────────────────
async function toggleLike(postId, btn) {
    if (!currentUser.id) return;
    const isLiked = btn.classList.contains('liked');
    btn.classList.toggle('liked', !isLiked);
    btn.querySelector('svg').setAttribute('fill', !isLiked ? 'currentColor' : 'none');
    try {
        if (isLiked) {
            await _sb.from('post_likes').delete()
                .eq('post_id', postId).eq('user_id', currentUser.id);
        } else {
            await _sb.from('post_likes').insert({ post_id: postId, user_id: currentUser.id });
        }
        const { data } = await _sb.from('post_likes').select('post_id').eq('post_id', postId);
        const count = data?.length || 0;
        const countEl = document.querySelector(`#post-${postId} .post-counts-likes`);
        if (countEl) countEl.innerHTML = count > 0 ? `❤️ ${count} like${count!==1?'s':''}` : '';
    } catch(e) {
        btn.classList.toggle('liked', isLiked);
    }
}

// ── LIKES MODAL ───────────────────────────────────────────────
async function openLikesModal(postId) {
    document.getElementById('likesModal').classList.add('open');
    document.getElementById('likesModalList').innerHTML = '<div class="res-loading-sm">Loading…</div>';
    try {
        const { data, error } = await _sb.from('post_likes')
            .select('user_id, profiles(id, first_name, last_name, username, profile_photo_url)')
            .eq('post_id', postId);
        if (error) throw error;
        if (!data?.length) {
            document.getElementById('likesModalList').innerHTML =
                '<div class="res-loading-sm">No likes yet.</div>';
            return;
        }
        document.getElementById('likesModalList').innerHTML = data.map(r => {
            const u    = r.profiles || {};
            const name = `${u.first_name||''} ${u.last_name||''}`.trim() || u.username || 'User';
            return `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
                ${avatarHTML(u, 36)}
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text-primary);">${escH(name)}</div>
                    <div style="font-size:11px;color:var(--text-light);">@${escH(u.username||'')}</div>
                </div>
            </div>`;
        }).join('');
    } catch(e) {
        document.getElementById('likesModalList').innerHTML =
            '<div class="res-loading-sm">Failed to load.</div>';
    }
}
function closeLikesModal() {
    document.getElementById('likesModal').classList.remove('open');
}

// ── COMMENTS ─────────────────────────────────────────────────
function openComments(postId) {
    activePostId = postId;
    const overlay = document.getElementById('commentsOverlay');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    loadComments(postId);
}

function closeComments(e, force) {
    if (!force && e && e.target !== document.getElementById('commentsOverlay')) return;
    document.getElementById('commentsOverlay').classList.remove('open');
    document.body.style.overflow = '';
    activePostId = null;
    replyingTo   = null;
}

async function loadComments(postId) {
    const list = document.getElementById('commentsList');
    list.innerHTML = '<div class="res-loading-sm">Loading…</div>';
    try {
        const { data, error } = await _sb.from('post_comments')
            .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
            .eq('post_id', postId)
            .is('parent_id', null)
            .order('created_at', { ascending: true });
        if (error) throw error;

        const commentIds = (data||[]).map(c => c.id);
        let repliesMap = {};
        if (commentIds.length) {
            const { data: replies } = await _sb.from('post_comments')
                .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                .in('parent_id', commentIds)
                .order('created_at', { ascending: true });
            (replies||[]).forEach(r => {
                if (!repliesMap[r.parent_id]) repliesMap[r.parent_id] = [];
                repliesMap[r.parent_id].push(r);
            });
        }

        // FIX: Update comment count indicator on the post card
        const totalReplies = Object.values(repliesMap).reduce((s,a) => s + a.length, 0);
        const total = (data?.length||0) + totalReplies;
        updateCommentCount(postId, total);

        if (!data?.length) {
            list.innerHTML = '<div class="res-loading-sm">No comments yet — be the first!</div>';
            return;
        }
        list.innerHTML = data.map(c => commentHTML(c, repliesMap[c.id]||[])).join('');
    } catch(e) {
        list.innerHTML = '<div class="res-loading-sm">Failed to load comments.</div>';
    }
}

// FIX: Central function to update comment count on the post card
function updateCommentCount(postId, total) {
    const countEl = document.getElementById(`commentCount-${postId}`);
    if (countEl) {
        countEl.textContent = total > 0
            ? `💬 ${total} comment${total!==1?'s':''}` : '';
    }
}

function commentHTML(c, replies = []) {
    const author  = c.profiles || {};
    const name    = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'User';
    const isOwn   = c.user_id === currentUser.id;
    const repliesHTML = replies.map(r => commentHTML(r, [], true)).join('');

    return `
    <div class="comment-item" id="comment-${c.id}">
        ${avatarHTML(author, 34)}
        <div class="comment-body">
            <div class="comment-bubble">
                <div class="comment-name">${escH(name)}</div>
                <div class="comment-text" id="ctext-${c.id}">${escH(c.content)}</div>
            </div>
            <div class="comment-meta">
                <span>${timeAgo(c.created_at)}</span>
                <button class="comment-action-btn" onclick="startReply('${c.id}','${escH(name)}')">Reply</button>
                ${isOwn ? `
                    <button class="comment-action-btn" onclick="startEditComment('${c.id}')">Edit</button>
                    <button class="comment-action-btn danger" onclick="deleteComment('${c.id}')">Delete</button>
                ` : ''}
            </div>
            ${replies.length ? `<div class="comment-replies">${repliesHTML}</div>` : ''}
            <div id="replyBox-${c.id}" style="display:none;" class="reply-input-wrap">
                <textarea class="reply-textarea" id="replyInput-${c.id}"
                    placeholder="Reply to ${escH(name)}…"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();submitReply('${c.id}');}"></textarea>
                <button class="reply-send-btn" onclick="submitReply('${c.id}')">Send</button>
            </div>
        </div>
    </div>`;
}

function startReply(commentId, authorName) {
    document.querySelectorAll('[id^="replyBox-"]').forEach(b => b.style.display = 'none');
    const box = document.getElementById(`replyBox-${commentId}`);
    if (box) { box.style.display = 'flex'; document.getElementById(`replyInput-${commentId}`)?.focus(); }
    replyingTo = { commentId, authorName };
}

async function submitReply(parentId) {
    const inp  = document.getElementById(`replyInput-${parentId}`);
    const text = inp?.value.trim();
    if (!text || !activePostId || !currentUser.id) return;
    try {
        const { error } = await _sb.from('post_comments').insert({
            post_id: activePostId, user_id: currentUser.id,
            content: text, parent_id: parentId
        });
        if (error) throw error;
        if (inp) inp.value = '';
        loadComments(activePostId);
    } catch(e) { alert('Reply failed: ' + e.message); }
}

async function submitComment() {
    const input = document.getElementById('commentInput');
    const text  = input?.value.trim();
    if (!text || !activePostId || !currentUser.id) return;
    const btn = document.querySelector('.comments-send-btn');
    if (btn) btn.disabled = true;
    try {
        const { error } = await _sb.from('post_comments').insert({
            post_id: activePostId, user_id: currentUser.id, content: text
        });
        if (error) throw error;
        input.value = '';
        input.style.height = '';
        loadComments(activePostId);
    } catch(e) { alert('Comment failed: ' + e.message); }
    finally { if (btn) btn.disabled = false; }
}

function startEditComment(commentId) {
    const textEl = document.getElementById(`ctext-${commentId}`);
    if (!textEl) return;
    const original = textEl.textContent;
    textEl.innerHTML = `<textarea class="reply-textarea" style="width:100%;min-height:40px;"
        id="cedit-${commentId}">${escH(original)}</textarea>
        <div style="display:flex;gap:6px;margin-top:6px;justify-content:flex-end;">
            <button class="comment-action-btn" onclick="cancelEditComment('${commentId}','${escH(original)}')">Cancel</button>
            <button class="reply-send-btn" style="padding:5px 12px;font-size:12px;"
                onclick="saveEditComment('${commentId}')">Save</button>
        </div>`;
}
function cancelEditComment(commentId, original) {
    const el = document.getElementById(`ctext-${commentId}`);
    if (el) el.innerHTML = escH(original);
}
async function saveEditComment(commentId) {
    const inp  = document.getElementById(`cedit-${commentId}`);
    const text = inp?.value.trim();
    if (!text) return;
    try {
        const { error } = await _sb.from('post_comments').update({ content: text }).eq('id', commentId);
        if (error) throw error;
        const el = document.getElementById(`ctext-${commentId}`);
        if (el) el.textContent = text;
    } catch(e) { alert('Edit failed: ' + e.message); }
}
async function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;
    try {
        const { error } = await _sb.from('post_comments').delete().eq('id', commentId);
        if (error) throw error;
        document.getElementById(`comment-${commentId}`)?.remove();
        if (activePostId) loadComments(activePostId);
    } catch(e) { alert('Delete failed: ' + e.message); }
}

// ── COMPOSER ─────────────────────────────────────────────────
function expandComposer() {
    if (composerOpen) return;
    composerOpen = true;
    document.getElementById('cpPlaceholder').parentElement.style.display = 'none';
    document.getElementById('cpExpanded').style.display = '';
    document.getElementById('postContent')?.focus();
}

function cancelComposer() {
    composerOpen = false;
    document.getElementById('cpExpanded').style.display = 'none';
    document.getElementById('cpPlaceholder').parentElement.style.display = '';
    document.getElementById('postContent').value = '';
    stagedMedia = []; stagedFiles = []; stagedLink = null;
    document.getElementById('cpMediaPreview').innerHTML = '';
    document.getElementById('cpMediaPreview').className = 'cp-media-preview';
    document.getElementById('cpLinkPreview').style.display = 'none';
    document.getElementById('cpLinkPreview').innerHTML = '';
    document.getElementById('cpLinkRow').style.display = 'none';
    const chips = document.getElementById('cpFileChips');
    if (chips) chips.innerHTML = '';
}

function autoResizeCp(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function toggleVisMenu() {
    const menu = document.getElementById('cpVisMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
function setVisibility(val, icon, label) {
    currentVis = val;
    document.getElementById('cpVisIcon').textContent  = icon;
    document.getElementById('cpVisLabel').textContent = label;
    document.getElementById('cpVisMenu').style.display = 'none';
}

function toggleLinkInput() {
    const row = document.getElementById('cpLinkRow');
    row.style.display = row.style.display === 'none' ? '' : 'none';
    if (row.style.display !== 'none') document.getElementById('cpLinkInput')?.focus();
}

// FIX: Fetch OG metadata from our Laravel backend.
// Shows a URL card immediately so the link is always visible even if OG fetch fails.
async function fetchLinkPreview(url) {
    if (!url) return;
    // Immediately show a plain URL card — visible right away
    stagedLink = { url, title: url, image: null };
    renderLinkPreview();
    // Close the input row
    document.getElementById('cpLinkRow').style.display = 'none';
    // Then try to enrich with OG data
    try {
        const res  = await fetch(`/api/og-preview?url=${encodeURIComponent(url)}`, {
            headers: { 'X-CSRF-TOKEN': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' }
        });
        if (res.ok) {
            const data = await res.json();
            if (data && !data.error) {
                stagedLink = {
                    url:   data.url   || url,
                    title: data.title || url,
                    image: data.image || null,
                };
                renderLinkPreview();
            }
        }
    } catch(e) {
        // Keep the plain URL card — no action needed
        console.warn('[OG preview] fetch failed, using plain URL card', e.message);
    }
}

function renderLinkPreview() {
    if (!stagedLink) return;
    const el = document.getElementById('cpLinkPreview');
    el.style.display = '';
    el.innerHTML = `
        <div class="cp-link-preview-inner">
            ${stagedLink.image ? `<img class="cp-link-preview-thumb" src="${escH(stagedLink.image)}" alt="">` : ''}
            <div class="cp-link-preview-info">
                <div class="cp-link-preview-title">${escH(stagedLink.title||stagedLink.url)}</div>
                <div class="cp-link-preview-url">${escH(stagedLink.url)}</div>
            </div>
            <button class="cp-link-preview-remove" onclick="removeLinkPreview()">✕</button>
        </div>`;
}
function removeLinkPreview() {
    stagedLink = null;
    document.getElementById('cpLinkPreview').style.display = 'none';
    document.getElementById('cpLinkPreview').innerHTML = '';
}

function handleMediaFiles(fileList) {
    Array.from(fileList).forEach(file => {
        const url  = URL.createObjectURL(file);
        const type = file.type.startsWith('video') ? 'video' : 'image';
        if (!stagedMedia.find(m => m.file.name === file.name && m.file.size === file.size))
            stagedMedia.push({ file, objectUrl: url, type });
    });
    renderMediaPreview();
}

function renderMediaPreview() {
    const grid = document.getElementById('cpMediaPreview');
    const n    = stagedMedia.length;
    grid.className = `cp-media-preview count-${n > 4 ? 'many' : n}`;
    grid.innerHTML = stagedMedia.map((m, i) => `
        <div class="cp-media-item">
            ${m.type === 'video'
                ? `<video src="${m.objectUrl}" preload="metadata"></video>`
                : `<img src="${m.objectUrl}" alt="">`}
            <button class="cp-remove-media" onclick="removeMedia(${i})">✕</button>
        </div>`).join('');
}
function removeMedia(i) {
    URL.revokeObjectURL(stagedMedia[i]?.objectUrl);
    stagedMedia.splice(i, 1);
    renderMediaPreview();
}

function handleAttachFiles(fileList) {
    Array.from(fileList).forEach(f => {
        if (!stagedFiles.find(x => x.file.name === f.name && x.file.size === f.size))
            stagedFiles.push({ file: f, name: f.name, size: f.size });
    });
    renderFileChips();
}
function renderFileChips() {
    let chips = document.getElementById('cpFileChips');
    if (!chips) {
        chips = document.createElement('div');
        chips.id = 'cpFileChips';
        chips.className = 'cp-file-chips';
        document.getElementById('cpMediaPreview').insertAdjacentElement('afterend', chips);
    }
    chips.innerHTML = stagedFiles.map((f, i) => `
        <div class="cp-file-chip">
            ${fileEmojiFromName(f.name)} ${escH(f.name)}
            <button onclick="removeFile(${i})">✕</button>
        </div>`).join('');
}
function removeFile(i) { stagedFiles.splice(i, 1); renderFileChips(); }

// ── CREATE POST ───────────────────────────────────────────────
async function createPost() {
    if (!currentUser.id) return;
    const text = document.getElementById('postContent')?.value.trim() || '';
    if (!text && !stagedMedia.length && !stagedFiles.length && !stagedLink) {
        alert('Please add some content, a photo/video, file, or link to post.');
        return;
    }
    const btn = document.getElementById('postButton');
    btn.disabled = true; btn.textContent = 'Posting…';
    try {
        const mediaUrls = [];
        const fileUrls  = [];
        for (const m of stagedMedia) {
            const ext  = m.file.name.split('.').pop();
            const path = `${currentUser.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            const { error: upErr } = await _sb.storage.from('posts').upload(path, m.file, { upsert: true });
            if (upErr) throw upErr;
            const { data: urlData } = _sb.storage.from('posts').getPublicUrl(path);
            mediaUrls.push(urlData.publicUrl);
        }
        for (const f of stagedFiles) {
            const ext  = f.file.name.split('.').pop();
            const path = `${currentUser.id}/files/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            const { error: upErr } = await _sb.storage.from('posts').upload(path, f.file, { upsert: true });
            if (upErr) throw upErr;
            const { data: urlData } = _sb.storage.from('posts').getPublicUrl(path);
            fileUrls.push({ url: urlData.publicUrl, name: f.name, size: f.size });
        }
        const { error } = await _sb.from('posts').insert({
            user_id:    currentUser.id,
            content:    text || null,
            visibility: currentVis,
            media_urls: mediaUrls.length ? mediaUrls : null,
            file_urls:  fileUrls.length  ? fileUrls  : null,
            link_meta:  stagedLink       ? stagedLink : null,
        });
        if (error) throw error;
        cancelComposer();
        loadFeed();
    } catch(e) {
        alert('Post failed: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Post';
    }
}

// ── EDIT POST (with media/file/link management) ───────────────
async function openEditPost(postId) {
    editingPostId   = postId;
    editStagedMedia = [];
    editStagedFiles = [];

    // Fetch full post data from Supabase
    const { data: post } = await _sb.from('posts').select('*').eq('id', postId).single();
    editExistingMedia = parseJSON(post?.media_urls, []);
    editExistingFiles = parseJSON(post?.file_urls,  []);
    editStagedLink    = parseJSON(post?.link_meta,  null);

    document.getElementById('editContent').value = post?.content || '';
    document.getElementById('editVisSelect').value = post?.visibility || 'public';

    renderEditMediaPreview();
    renderEditFileChips();
    renderEditLinkPreview();

    // Reset link input
    const linkRow = document.getElementById('editLinkRow');
    if (linkRow) linkRow.style.display = 'none';
    const linkInput = document.getElementById('editLinkInput');
    if (linkInput) linkInput.value = '';

    document.getElementById('editModal').classList.add('open');
    document.getElementById(`postMenu-${postId}`)?.classList.remove('open');
}

function renderEditMediaPreview() {
    const grid = document.getElementById('editMediaPreview');
    if (!grid) return;
    const allItems = [
        ...editExistingMedia.map((url, i) => ({
            type: 'existing', url, i,
            isVideo: /\.(mp4|mov|webm|ogg)(\?|$)/i.test(url)
        })),
        ...editStagedMedia.map((m, i) => ({
            type: 'new', url: m.objectUrl, i,
            isVideo: m.type === 'video'
        }))
    ];
    const n = allItems.length;
    grid.className = `cp-media-preview count-${n > 4 ? 'many' : n || 1}`;
    grid.innerHTML = allItems.map(item => `
        <div class="cp-media-item">
            ${item.isVideo
                ? `<video src="${escH(item.url)}" preload="metadata"></video>`
                : `<img src="${escH(item.url)}" alt="">`}
            <button class="cp-remove-media"
                onclick="${item.type === 'existing'
                    ? `removeEditExistingMedia(${item.i})`
                    : `removeEditNewMedia(${item.i})`}">✕</button>
        </div>`).join('');
}
function removeEditExistingMedia(i) { editExistingMedia.splice(i, 1); renderEditMediaPreview(); }
function removeEditNewMedia(i) {
    URL.revokeObjectURL(editStagedMedia[i]?.objectUrl);
    editStagedMedia.splice(i, 1);
    renderEditMediaPreview();
}
function handleEditMediaFiles(fileList) {
    Array.from(fileList).forEach(file => {
        const url  = URL.createObjectURL(file);
        const type = file.type.startsWith('video') ? 'video' : 'image';
        editStagedMedia.push({ file, objectUrl: url, type });
    });
    renderEditMediaPreview();
}

function renderEditFileChips() {
    const wrap = document.getElementById('editFileChips');
    if (!wrap) return;
    const existing = editExistingFiles.map((f, i) => `
        <div class="cp-file-chip">
            ${fileEmojiFromName(f.name||'')} ${escH(f.name||f.url?.split('/').pop()||'File')}
            <button onclick="removeEditExistingFile(${i})">✕</button>
        </div>`).join('');
    const newFiles = editStagedFiles.map((f, i) => `
        <div class="cp-file-chip" style="border-color:var(--primary);">
            ${fileEmojiFromName(f.name)} ${escH(f.name)} <small>(new)</small>
            <button onclick="removeEditNewFile(${i})">✕</button>
        </div>`).join('');
    wrap.innerHTML = existing + newFiles;
}
function removeEditExistingFile(i) { editExistingFiles.splice(i, 1); renderEditFileChips(); }
function handleEditFileAttach(fileList) {
    Array.from(fileList).forEach(f => editStagedFiles.push({ file: f, name: f.name, size: f.size }));
    renderEditFileChips();
}
function removeEditNewFile(i) { editStagedFiles.splice(i, 1); renderEditFileChips(); }

// ── EDIT MODAL LINK ───────────────────────────────────────────
function toggleEditLinkInput() {
    const row = document.getElementById('editLinkRow');
    if (!row) return;
    row.style.display = row.style.display === 'none' ? '' : 'none';
    if (row.style.display !== 'none') {
        const inp = document.getElementById('editLinkInput');
        if (inp) { inp.value = editStagedLink?.url || ''; inp.focus(); }
    }
}

async function fetchEditLinkPreview(url) {
    if (!url) return;
    editStagedLink = { url, title: url, image: null };
    renderEditLinkPreview();
    document.getElementById('editLinkRow').style.display = 'none';
    try {
        const res = await fetch(`/api/og-preview?url=${encodeURIComponent(url)}`, {
            headers: { 'X-CSRF-TOKEN': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '' }
        });
        if (res.ok) {
            const data = await res.json();
            if (data && !data.error) {
                editStagedLink = { url: data.url||url, title: data.title||url, image: data.image||null };
                renderEditLinkPreview();
            }
        }
    } catch(e) { console.warn('[Edit OG preview] failed, keeping plain URL', e.message); }
}

function renderEditLinkPreview() {
    const el = document.getElementById('editLinkPreview');
    if (!el) return;
    if (!editStagedLink) { el.style.display = 'none'; el.innerHTML = ''; return; }
    el.style.display = '';
    el.innerHTML = `
        <div class="cp-link-preview-inner">
            ${editStagedLink.image ? `<img class="cp-link-preview-thumb" src="${escH(editStagedLink.image)}" alt="">` : ''}
            <div class="cp-link-preview-info">
                <div class="cp-link-preview-title">${escH(editStagedLink.title||editStagedLink.url)}</div>
                <div class="cp-link-preview-url">${escH(editStagedLink.url)}</div>
            </div>
            <button class="cp-link-preview-remove" onclick="removeEditLinkPreview()">&#10005;</button>
        </div>`;
}
function removeEditLinkPreview() {
    editStagedLink = null;
    renderEditLinkPreview();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    editingPostId = null;
    editStagedMedia.forEach(m => URL.revokeObjectURL(m.objectUrl));
    editStagedMedia = []; editStagedFiles = [];
    editExistingMedia = []; editExistingFiles = [];
    editStagedLink = null;
    const el = document.getElementById('editLinkPreview');
    if (el) { el.style.display = 'none'; el.innerHTML = ''; }
    const row = document.getElementById('editLinkRow');
    if (row) row.style.display = 'none';
}

async function saveEdit() {
    if (!editingPostId) return;
    const content = document.getElementById('editContent').value.trim();
    const vis     = document.getElementById('editVisSelect').value;
    const btn     = document.querySelector('#editModal .btn-primary');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
        // Upload new media
        const finalMedia = [...editExistingMedia];
        for (const m of editStagedMedia) {
            const ext  = m.file.name.split('.').pop();
            const path = `${currentUser.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            const { error: upErr } = await _sb.storage.from('posts').upload(path, m.file, { upsert: true });
            if (upErr) throw upErr;
            const { data: urlData } = _sb.storage.from('posts').getPublicUrl(path);
            finalMedia.push(urlData.publicUrl);
        }
        // Upload new files
        const finalFiles = [...editExistingFiles];
        for (const f of editStagedFiles) {
            const ext  = f.file.name.split('.').pop();
            const path = `${currentUser.id}/files/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            const { error: upErr } = await _sb.storage.from('posts').upload(path, f.file, { upsert: true });
            if (upErr) throw upErr;
            const { data: urlData } = _sb.storage.from('posts').getPublicUrl(path);
            finalFiles.push({ url: urlData.publicUrl, name: f.name, size: f.size });
        }

        const { error } = await _sb.from('posts').update({
            content:    content || null,
            visibility: vis,
            media_urls: finalMedia.length ? finalMedia : null,
            file_urls:  finalFiles.length ? finalFiles : null,
            link_meta:  editStagedLink    ? editStagedLink : null,
        }).eq('id', editingPostId);
        if (error) throw error;
        closeEditModal();
        loadFeed();
    } catch(e) { alert('Save failed: ' + e.message); }
    finally { btn.disabled = false; btn.textContent = 'Save Changes'; }
}

// ── DELETE POST ───────────────────────────────────────────────
async function deletePost(postId) {
    document.getElementById(`postMenu-${postId}`)?.classList.remove('open');
    if (!confirm('Delete this post? This cannot be undone.')) return;
    try {
        const { error } = await _sb.from('posts').delete().eq('id', postId);
        if (error) throw error;
        document.getElementById(`post-${postId}`)?.remove();
    } catch(e) { alert('Delete failed: ' + e.message); }
}

function togglePostMenu(postId) {
    const menu = document.getElementById(`postMenu-${postId}`);
    const isOpen = menu?.classList.contains('open');
    document.querySelectorAll('.post-menu-dropdown.open').forEach(m => m.classList.remove('open'));
    if (!isOpen) menu?.classList.add('open');
}

// ── REPORT ────────────────────────────────────────────────────
function openReportModal(postId) {
    activeReportId = postId;
    document.getElementById(`postMenu-${postId}`)?.classList.remove('open');
    document.getElementById('reportModal').classList.add('open');
    document.querySelectorAll('input[name="postReportReason"]').forEach(r => r.checked = false);
    document.getElementById('reportDetails').value = '';
}
function closeReportModal() {
    document.getElementById('reportModal').classList.remove('open');
    activeReportId = null;
}
async function submitPostReport() {
    const reason = document.querySelector('input[name="postReportReason"]:checked');
    if (!reason) { alert('Please select a reason.'); return; }
    const details    = document.getElementById('reportDetails').value.trim();
    const fullReason = details ? `${reason.value}: ${details}` : reason.value;
    try {
        const { error } = await _sb.from('reports').insert({
            reported_by: currentUser.id, reported_content_type: 'post',
            reported_content_id: activeReportId, reason: fullReason, status: 'pending'
        });
        if (error) throw error;
        closeReportModal();
        alert('✅ Report submitted.');
    } catch(e) { alert('Report failed: ' + e.message); }
}

// ── SHARE ─────────────────────────────────────────────────────
function openShareModal(postId) {
    activeShareId = postId;
    document.getElementById('shareModal').classList.add('open');
    document.getElementById('shareConfirmMsg').textContent = '';
}
function closeShareModal() {
    document.getElementById('shareModal').classList.remove('open');
    activeShareId = null;
}
async function shareToFeed() {
    if (!activeShareId || !currentUser.id) return;
    try {
        const { error } = await _sb.from('posts').insert({
            user_id: currentUser.id, content: null,
            shared_post_id: activeShareId, visibility: 'public'
        });
        if (error) throw error;
        document.getElementById('shareConfirmMsg').textContent = '✅ Shared to your feed!';
        setTimeout(closeShareModal, 1200);
        loadFeed();
    } catch(e) { alert('Share failed: ' + e.message); }
}
function copyPostLink() {
    const url = `${window.location.origin}/posts/${activeShareId}`;
    navigator.clipboard.writeText(url).then(() => {
        document.getElementById('shareConfirmMsg').textContent = '✅ Link copied!';
        setTimeout(closeShareModal, 1200);
    });
}

// ── WHO TO FOLLOW ─────────────────────────────────────────────
async function loadWhoToFollow() {
    if (!currentUser.id) return;
    const el = document.getElementById('whoToFollow');
    try {
        const { data } = await _sb.from('profiles')
            .select('id, first_name, last_name, username, profile_photo_url')
            .neq('id', currentUser.id).limit(20);
        const suggestions = (data||[]).filter(u => !followingSet.has(u.id)).slice(0, 5);
        if (!suggestions.length) { el.innerHTML = '<div class="res-empty-small">Nothing to suggest right now.</div>'; return; }
        el.innerHTML = suggestions.map(u => {
            const name = `${u.first_name||''} ${u.last_name||''}`.trim() || u.username;
            const profileUrl = `/profile/${escH(u.username||u.id)}`;
            return `<div class="wtf-item">
                <a href="${profileUrl}" class="wtf-avatar" style="text-decoration:none;color:inherit;">
                    ${u.profile_photo_url
                        ? `<img src="${escH(u.profile_photo_url)}" alt="${escH(name)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
                        : ((u.first_name||'?')[0]+(u.last_name||'?')[0]).toUpperCase()}
                </a>
                <a href="${profileUrl}" class="wtf-info-link">
                    <div class="wtf-name">${escH(name)}</div>
                    <div class="wtf-sub">@${escH(u.username||'')}</div>
                </a>
                <button class="wtf-follow-btn ${followingSet.has(u.id) ? 'following' : ''}"
                    data-follow-uid="${u.id}"
                    onclick="event.preventDefault(); toggleFollow('${u.id}', this)">
                    ${followingSet.has(u.id) ? 'Following' : '+ Follow'}
                </button>
            </div>`;
        }).join('');
    } catch(e) {}
}

// ── TRENDING ─────────────────────────────────────────────────
async function loadTrending() {
    const el = document.getElementById('trendingList');
    try {
        const { data } = await _sb.from('posts').select('content')
            .eq('visibility', 'public')
            .order('created_at', { ascending: false }).limit(100);
        const counts = {};
        (data||[]).forEach(p => {
            const matches = (p.content||'').match(/#[a-zA-Z0-9_]+/g) || [];
            matches.forEach(tag => { counts[tag] = (counts[tag]||0) + 1; });
        });
        const sorted = Object.entries(counts).sort((a,b) => b[1]-a[1]).slice(0,8);
        if (!sorted.length) { el.innerHTML = '<div class="res-empty-small">No trending topics yet.</div>'; return; }
        el.innerHTML = sorted.map(([tag, n], i) => `
            <div class="trending-item" onclick="searchByTag('${escH(tag)}')">
                <span class="trending-rank">${i+1}</span>
                <span class="trending-tag">${escH(tag)}</span>
                <span class="trending-count">${n} post${n!==1?'s':''}</span>
            </div>`).join('');
    } catch(e) {}
}
function searchByTag(tag) {}

// ── KEYBOARD SHORTCUTS ────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('likesModal')?.classList.contains('open'))  { closeLikesModal(); return; }
    if (document.getElementById('reportModal').classList.contains('open'))  { closeReportModal(); return; }
    if (document.getElementById('editModal').classList.contains('open'))    { closeEditModal(); return; }
    if (document.getElementById('shareModal').classList.contains('open'))   { closeShareModal(); return; }
    if (document.getElementById('commentsOverlay').classList.contains('open')) { closeComments(null,true); return; }
    if (document.getElementById('lightbox')?.classList.contains('open'))   { closeLightbox(); return; }
});

// ── HELPERS ───────────────────────────────────────────────────
function parseJSON(val, fallback) {
    if (!val) return fallback;
    if (typeof val === 'object') return val;
    try { return JSON.parse(val); } catch(e) { return fallback; }
}
function timeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return `${Math.floor(s/60)}m ago`;
    if (s < 86400)  return `${Math.floor(s/3600)}h ago`;
    if (s < 604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}
function formatBytes(b) {
    if (!b) return '';
    if (b < 1024)    return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}
function fileEmojiFromName(name) {
    const ext = (name||'').split('.').pop().toLowerCase();
    return { pdf:'📄', doc:'📝', docx:'📝', ppt:'📊', pptx:'📊',
             mp4:'🎬', mov:'🎬', webm:'🎬', jpg:'🖼️', jpeg:'🖼️',
             png:'🖼️', gif:'🖼️', webp:'🖼️', zip:'🗜️', rar:'🗜️' }[ext] || '📎';
}
function escH(t) {
    if (t === null || t === undefined) return '';
    if (typeof t !== 'string') t = String(t);
    const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
}
