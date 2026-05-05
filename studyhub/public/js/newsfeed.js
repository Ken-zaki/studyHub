/* ============================================================
   public/js/newsfeed.js  — StudyHub Newsfeed
   ============================================================ */

const _sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// ── STATE ────────────────────────────────────────────────────
let currentTab       = 'for_you';   // 'for_you' | 'following' | 'friends'
let currentVis       = 'public';    // post visibility
let stagedMedia      = [];          // { file, objectUrl, type:'image'|'video' }
let stagedFiles      = [];          // { file, name, size }
let stagedLink       = null;        // { url, title, image }
let composerOpen     = false;
let activePostId     = null;        // for comments drawer
let activeReportId   = null;        // for report modal
let activeShareId    = null;        // for share modal
let editingPostId    = null;
let followingSet     = new Set();   // user IDs the current user follows
let friendSet        = new Set();   // accepted friends (separate from follows)
let replyingTo       = null;        // { commentId, authorName }

const STUDY_TIPS = [
    "Use the Pomodoro Technique: 25 min focus, 5 min break.",
    "Teach what you learned \u2014 it's the best way to remember it.",
    "Spaced repetition beats cramming every single time.",
    "Review your notes within 24 hours to retain 80% more.",
    "Sleep consolidates memory \u2014 don't pull all-nighters before exams.",
    "Write by hand when studying; it boosts recall over typing.",
    'Ask "why?" for every fact \u2014 understanding > memorising.',
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

    // Close menus on outside click
    document.addEventListener('click', e => {
        // Visibility menu
        if (!e.target.closest('.cp-vis-wrap')) {
            document.getElementById('cpVisMenu').style.display = 'none';
        }
        // Post dropdown menus
        if (!e.target.closest('.post-menu-wrap')) {
            document.querySelectorAll('.post-menu-dropdown.open')
                .forEach(el => el.classList.remove('open'));
        }
    });
});

// ── AVATAR HELPER ─────────────────────────────────────────────
function renderAvatar(user, elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (user?.profile_photo_url) {
        el.innerHTML = `<img src="${escH(user.profile_photo_url)}" alt="">`;
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
    try {
        // Load who we follow
        const { data: fData } = await _sb.from('follows')
            .select('following_id').eq('follower_id', currentUser.id);
        followingSet = new Set((fData||[]).map(r => r.following_id));

        // Load accepted friends from user_friends table
        // The friends table stores bidirectional relationships — fetch both directions
        const { data: fFriends } = await _sb.from('user_friends')
            .select('user_id, friend_id')
            .or(`user_id.eq.${currentUser.id},friend_id.eq.${currentUser.id}`);
        friendSet = new Set();
        (fFriends||[]).forEach(row => {
            const otherId = row.user_id === currentUser.id ? row.friend_id : row.user_id;
            friendSet.add(otherId);
        });
    } catch(e) {
        // user_friends table may use a different name — try fallback
        try {
            const { data: fFriends } = await _sb.from('friendships')
                .select('user_id, friend_id')
                .or(`user_id.eq.${currentUser.id},friend_id.eq.${currentUser.id}`);
            (fFriends||[]).forEach(row => {
                const otherId = row.user_id === currentUser.id ? row.friend_id : row.user_id;
                friendSet.add(otherId);
            });
        } catch(e2) {}
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
        // Update all follow buttons for this user on the page
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
    feed.innerHTML = '<div class="loading-state">Loading posts…</div>';
    try {
        const allMap = new Map();

        if (currentTab === 'for_you') {
            // ── FOR YOU ──────────────────────────────────────────
            // 1. All public posts from anyone
            const { data: publicPosts, error: pubErr } = await _sb.from('posts')
                .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                .eq('visibility', 'public')
                .order('created_at', { ascending: false })
                .limit(40);
            if (pubErr) throw pubErr;
            (publicPosts||[]).forEach(p => allMap.set(p.id, p));

            // 2. Own posts of ALL visibilities (public, friends, only_me)
            if (currentUser.id) {
                const { data: ownPosts } = await _sb.from('posts')
                    .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                    .eq('user_id', currentUser.id)
                    .order('created_at', { ascending: false })
                    .limit(20);
                (ownPosts||[]).forEach(p => allMap.set(p.id, p));
            }

            // 3. Friends-visibility posts from actual friends
            if (friendSet.size) {
                const { data: friendPosts } = await _sb.from('posts')
                    .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                    .in('user_id', [...friendSet])
                    .eq('visibility', 'friends')
                    .order('created_at', { ascending: false })
                    .limit(20);
                (friendPosts||[]).forEach(p => allMap.set(p.id, p));
            }

        } else if (currentTab === 'following') {
            // ── FOLLOWING ────────────────────────────────────────
            // Posts from people I follow — public and friends-visibility only (not only_me)
            if (!followingSet.size) {
                feed.innerHTML = `<div class="feed-empty"><div class="ei">👁</div>
                    <p>You're not following anyone yet.<br>Follow people to see their posts here.</p></div>`;
                return;
            }
            const { data: followedPosts, error: folErr } = await _sb.from('posts')
                .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                .in('user_id', [...followingSet])
                .in('visibility', ['public', 'friends'])
                .order('created_at', { ascending: false })
                .limit(40);
            if (folErr) throw folErr;
            (followedPosts||[]).forEach(p => allMap.set(p.id, p));

            // Also show own posts (all visibilities) in following tab
            if (currentUser.id) {
                const { data: ownPosts } = await _sb.from('posts')
                    .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                    .eq('user_id', currentUser.id)
                    .order('created_at', { ascending: false })
                    .limit(10);
                (ownPosts||[]).forEach(p => allMap.set(p.id, p));
            }

        } else if (currentTab === 'friends') {
            // ── FRIENDS ──────────────────────────────────────────
            // Only posts from actual friends (accepted friend requests)
            // Shows their public + friends-visibility posts, NOT only_me
            if (!friendSet.size) {
                feed.innerHTML = `<div class="feed-empty"><div class="ei">👥</div>
                    <p>No friends added yet. Add friends to see their posts here!</p></div>`;
                return;
            }
            const { data: friendsPosts, error: frErr } = await _sb.from('posts')
                .select('*, profiles(id, first_name, last_name, username, profile_photo_url)')
                .in('user_id', [...friendSet])
                .in('visibility', ['public', 'friends'])
                .order('created_at', { ascending: false })
                .limit(40);
            if (frErr) throw frErr;
            (friendsPosts||[]).forEach(p => allMap.set(p.id, p));
        }

        const all = [...allMap.values()].sort((a,b) =>
            new Date(b.created_at) - new Date(a.created_at));

        if (!all.length) {
            feed.innerHTML = `<div class="feed-empty"><div class="ei">📭</div>
                <p>No posts here yet. Be the first to share something!</p></div>`;
            return;
        }

        // Fetch like counts + own likes in one go
        const postIds = all.map(p => p.id);
        const [likeData, myLikeData] = await Promise.all([
            _sb.from('post_likes').select('post_id').in('post_id', postIds),
            currentUser.id
                ? _sb.from('post_likes').select('post_id')
                    .in('post_id', postIds).eq('user_id', currentUser.id)
                : Promise.resolve({ data: [] })
        ]);

        const likeCounts  = {};
        const myLikedSet  = new Set();
        (likeData.data||[]).forEach(r => { likeCounts[r.post_id] = (likeCounts[r.post_id]||0)+1; });
        (myLikeData.data||[]).forEach(r => myLikedSet.add(r.post_id));

        feed.innerHTML = all.map(p =>
            postCardHTML(p, likeCounts[p.id]||0, myLikedSet.has(p.id))
        ).join('');

    } catch(err) {
        feed.innerHTML = `<div class="alert-error">❌ Failed to load: ${escH(err.message)}</div>`;
    }
}

// ── POST CARD HTML ────────────────────────────────────────────
function postCardHTML(post, likeCount, isLiked) {
    const author    = post.profiles || {};
    const isOwn     = post.user_id === currentUser.id;
    const isFollowing = followingSet.has(post.user_id);
    const name      = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'Unknown';
    const ago       = timeAgo(post.created_at);
    const visIcon   = { public:'🌐', friends:'👥', only_me:'🔒' }[post.visibility] || '🌐';

    // Media attachments
    const media  = post.media_urls  ? (Array.isArray(post.media_urls)  ? post.media_urls  : JSON.parse(post.media_urls))  : [];
    const files  = post.file_urls   ? (Array.isArray(post.file_urls)   ? post.file_urls   : JSON.parse(post.file_urls))   : [];
    const linkMeta = post.link_meta ? (typeof post.link_meta === 'object' ? post.link_meta : JSON.parse(post.link_meta))  : null;

    // Media grid
    let mediaHTML = '';
    if (media.length) {
        const cls = media.length === 1 ? 'count-1' :
                    media.length === 2 ? 'count-2' :
                    media.length === 3 ? 'count-3' :
                    media.length === 4 ? 'count-4' : 'count-many';
        const shown   = media.slice(0, 5);
        const moreCount = media.length - 5;
        mediaHTML = `<div class="post-media ${cls}">` +
            shown.map((url, i) => {
                const isVideo = /\.(mp4|mov|webm|ogg)(\?|$)/i.test(url);
                const inner   = isVideo
                    ? `<video src="${escH(url)}" controls preload="none"></video>`
                    : `<img src="${escH(url)}" alt="" loading="lazy">`;
                const overlay = (i === 4 && moreCount > 0)
                    ? `<div class="media-more-overlay">+${moreCount}</div>` : '';
                return `<div class="post-media-item">${inner}${overlay}</div>`;
            }).join('') + `</div>`;
    }

    // File rows
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

    // Link preview
    let linkHTML = '';
    if (linkMeta?.url) {
        linkHTML = `<a class="post-link-preview" href="${escH(linkMeta.url)}" target="_blank" rel="noopener">
            ${linkMeta.image ? `<img class="post-link-img" src="${escH(linkMeta.image)}" alt="">` : ''}
            <div class="post-link-info">
                <div class="post-link-title">${escH(linkMeta.title || linkMeta.url)}</div>
                <div class="post-link-url">${escH(linkMeta.url)}</div>
            </div>
        </a>`;
    }

    // Post text (collapsible if long)
    const text     = post.content || '';
    const longText = text.length > 300;
    const textHTML = text ? `
        <div class="post-text ${longText ? 'collapsed' : ''}" id="postText-${post.id}">${escH(text)}</div>
        ${longText ? `<button class="post-see-more" onclick="togglePostText('${post.id}',this)">See more</button>` : ''}
    ` : '';

    // Dropdown menu
    const menuItems = isOwn
        ? `<button class="post-menu-item" onclick="openEditPost('${post.id}')">✏️ Edit Post</button>
           <button class="post-menu-item danger" onclick="deletePost('${post.id}')">🗑️ Delete Post</button>`
        : `<button class="post-menu-item" onclick="openReportModal('${post.id}')">🚩 Report Post</button>`;

    // Follow / unfollow button (only for others)
    const followBtn = (!isOwn && post.user_id) ? `
        <button class="post-follow-btn ${isFollowing ? 'following' : ''}"
            data-follow-uid="${post.user_id}"
            onclick="toggleFollow('${post.user_id}', this)">
            ${isFollowing ? 'Following' : '+ Follow'}
        </button>` : '';

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

        ${text || media.length || files.length || linkMeta ? `<div class="post-body">
            ${textHTML}
        </div>` : ''}

        ${mediaHTML}
        ${filesHTML}
        ${linkHTML}

        <div class="post-counts">
            <div class="post-counts-likes" onclick="openLikesModal('${post.id}')">
                ${likeCount > 0 ? `❤️ ${likeCount} like${likeCount!==1?'s':''}` : ''}
            </div>
            <div class="post-counts-comments" onclick="openComments('${post.id}')">
                <!-- comment count loaded lazily -->
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

function togglePostText(postId, btn) {
    const el = document.getElementById(`postText-${postId}`);
    if (!el) return;
    const isCollapsed = el.classList.contains('collapsed');
    el.classList.toggle('collapsed', !isCollapsed);
    btn.textContent = isCollapsed ? 'See less' : 'See more';
}

// ── LIKE ──────────────────────────────────────────────────────
async function toggleLike(postId, btn) {
    if (!currentUser.id) return;
    const isLiked = btn.classList.contains('liked');
    // Optimistic UI
    btn.classList.toggle('liked', !isLiked);
    btn.querySelector('svg').setAttribute('fill', !isLiked ? 'currentColor' : 'none');

    try {
        if (isLiked) {
            await _sb.from('post_likes').delete()
                .eq('post_id', postId).eq('user_id', currentUser.id);
        } else {
            await _sb.from('post_likes').insert({ post_id: postId, user_id: currentUser.id });
        }
        // Refresh like count
        const { data } = await _sb.from('post_likes').select('post_id').eq('post_id', postId);
        const count = data?.length || 0;
        const countEl = document.querySelector(`#post-${postId} .post-counts-likes`);
        if (countEl) countEl.innerHTML = count > 0 ? `❤️ ${count} like${count!==1?'s':''}` : '';
    } catch(e) {
        // Revert on error
        btn.classList.toggle('liked', isLiked);
    }
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

        // Load replies for each comment
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

        // Update comment count on card
        const total = (data?.length||0) + Object.values(repliesMap).flat().length;
        const countEl = document.querySelector(`#post-${postId} .post-counts-comments`);
        if (countEl) countEl.textContent = total ? `💬 ${total} comment${total!==1?'s':''}` : '';

        if (!data?.length) {
            list.innerHTML = '<div class="res-loading-sm">No comments yet — be the first!</div>';
            return;
        }

        list.innerHTML = data.map(c => commentHTML(c, repliesMap[c.id]||[])).join('');
    } catch(e) {
        list.innerHTML = '<div class="res-loading-sm">Failed to load comments.</div>';
    }
}

function commentHTML(c, replies = []) {
    const author  = c.profiles || {};
    const name    = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'User';
    const initials= ((author.first_name||'?')[0]+(author.last_name||'?')[0]).toUpperCase();
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
    // Hide all other reply boxes
    document.querySelectorAll('[id^="replyBox-"]').forEach(b => b.style.display = 'none');
    const box = document.getElementById(`replyBox-${commentId}`);
    if (box) { box.style.display = 'flex'; document.getElementById(`replyInput-${commentId}`)?.focus(); }
    replyingTo = { commentId, authorName };
}

async function submitReply(parentId) {
    const inp   = document.getElementById(`replyInput-${parentId}`);
    const text  = inp?.value.trim();
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
}

function autoResizeCp(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

// Visibility
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

// Link
function toggleLinkInput() {
    const row = document.getElementById('cpLinkRow');
    row.style.display = row.style.display === 'none' ? '' : 'none';
    if (row.style.display !== 'none') document.getElementById('cpLinkInput')?.focus();
}

async function fetchLinkPreview(url) {
    if (!url) return;
    stagedLink = { url, title: url, image: null };
    renderLinkPreview();
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

// Media files
function handleMediaFiles(fileList) {
    Array.from(fileList).forEach(file => {
        const url  = URL.createObjectURL(file);
        const type = file.type.startsWith('video') ? 'video' : 'image';
        if (!stagedMedia.find(m => m.file.name === file.name && m.file.size === file.size)) {
            stagedMedia.push({ file, objectUrl: url, type });
        }
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

// Attach files
function handleAttachFiles(fileList) {
    Array.from(fileList).forEach(f => {
        if (!stagedFiles.find(x => x.file.name === f.name && x.file.size === f.size)) {
            stagedFiles.push({ file: f, name: f.name, size: f.size });
        }
    });
    renderFileChips();
}
function renderFileChips() {
    const wrap = document.getElementById('cpMediaPreview');
    // append chips after media grid — use a separate div
    let chips = document.getElementById('cpFileChips');
    if (!chips) {
        chips = document.createElement('div');
        chips.id = 'cpFileChips';
        chips.className = 'cp-file-chips';
        wrap.insertAdjacentElement('afterend', chips);
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

    // At least one of: text, media, file, link
    if (!text && !stagedMedia.length && !stagedFiles.length && !stagedLink) {
        alert('Please add some content, a photo/video, file, or link to post.');
        return;
    }

    const btn = document.getElementById('postButton');
    btn.disabled = true; btn.textContent = 'Posting…';

    try {
        const mediaUrls = [];
        const fileUrls  = [];

        // Upload media
        for (const m of stagedMedia) {
            const ext  = m.file.name.split('.').pop();
            const path = `${currentUser.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            const { error: upErr } = await _sb.storage.from('posts').upload(path, m.file, { upsert: true });
            if (upErr) throw upErr;
            const { data: urlData } = _sb.storage.from('posts').getPublicUrl(path);
            mediaUrls.push(urlData.publicUrl);
        }
        // Upload files
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

// ── EDIT POST ─────────────────────────────────────────────────
function openEditPost(postId) {
    editingPostId = postId;
    const card    = document.getElementById(`post-${postId}`);
    const textEl  = card?.querySelector('.post-text');
    const content = textEl?.textContent?.trim() || '';

    document.getElementById('editContent').value = content;
    document.getElementById('editModal').classList.add('open');
    document.getElementById('postMenu-' + postId)?.classList.remove('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    editingPostId = null;
}
async function saveEdit() {
    if (!editingPostId) return;
    const content = document.getElementById('editContent').value.trim();
    const vis     = document.getElementById('editVisSelect').value;
    const btn     = document.querySelector('#editModal .btn-primary');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
        const { error } = await _sb.from('posts').update({ content: content||null, visibility: vis })
            .eq('id', editingPostId);
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

// ── POST MENU ─────────────────────────────────────────────────
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
            reported_by:           currentUser.id,
            reported_content_type: 'post',
            reported_content_id:   activeReportId,
            reason:                fullReason,
            status:                'pending'
        });
        if (error) throw error;
        closeReportModal();
        alert('✅ Report submitted. Our team will review it.');
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
            user_id:   currentUser.id,
            content:   null,
            shared_post_id: activeShareId,
            visibility: 'public'
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

function openLikesModal() { /* future: show who liked */ }

// ── WHO TO FOLLOW ─────────────────────────────────────────────
async function loadWhoToFollow() {
    if (!currentUser.id) return;
    const el = document.getElementById('whoToFollow');
    try {
        // Fetch random users not already followed and not self
        const { data } = await _sb.from('profiles')
            .select('id, first_name, last_name, username, profile_photo_url')
            .neq('id', currentUser.id)
            .limit(20);

        const suggestions = (data||[])
            .filter(u => !followingSet.has(u.id))
            .slice(0, 5);

        if (!suggestions.length) { el.innerHTML = '<div class="res-empty-small">Nothing to suggest right now.</div>'; return; }

        el.innerHTML = suggestions.map(u => {
            const name     = `${u.first_name||''} ${u.last_name||''}`.trim() || u.username;
            const initials = ((u.first_name||'?')[0]+(u.last_name||'?')[0]).toUpperCase();
            const profileUrl = `/profile/${escH(u.username||u.id)}`;
            return `
            <div class="wtf-item">
                <!-- Avatar — clicks to profile -->
                <a href="${profileUrl}" class="wtf-avatar" title="View ${escH(name)}'s profile"
                   style="text-decoration:none;color:inherit;">
                    ${u.profile_photo_url
                        ? `<img src="${escH(u.profile_photo_url)}" alt="${escH(name)}">`
                        : initials}
                </a>
                <!-- Name + username — clicks to profile -->
                <a href="${profileUrl}" class="wtf-info-link"
                   title="View ${escH(name)}'s profile">
                    <div class="wtf-name">${escH(name)}</div>
                    <div class="wtf-sub">@${escH(u.username||'')}</div>
                </a>
                <!-- Follow button — stops propagation so it doesn't navigate -->
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
    // Simple: fetch recent posts and extract hashtags
    try {
        const { data } = await _sb.from('posts').select('content')
            .eq('visibility', 'public')
            .order('created_at', { ascending: false }).limit(100);
        const counts = {};
        (data||[]).forEach(p => {
            const matches = (p.content||'').match(/#[a-zA-Z0-9_]+/g) || [];
            matches.forEach(tag => { counts[tag] = (counts[tag]||0) + 1; });
        });
        const sorted = Object.entries(counts)
            .sort((a,b) => b[1]-a[1]).slice(0,8);

        if (!sorted.length) {
            el.innerHTML = '<div class="res-empty-small">No trending topics yet.</div>';
            return;
        }
        el.innerHTML = sorted.map(([tag, n], i) => `
            <div class="trending-item" onclick="searchByTag('${escH(tag)}')">
                <span class="trending-rank">${i+1}</span>
                <span class="trending-tag">${escH(tag)}</span>
                <span class="trending-count">${n} post${n!==1?'s':''}</span>
            </div>`).join('');
    } catch(e) {}
}

function searchByTag(tag) { /* future: filter feed by tag */ }

// ── KEYBOARD SHORTCUTS ────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('reportModal').classList.contains('open')) { closeReportModal(); return; }
    if (document.getElementById('editModal').classList.contains('open'))   { closeEditModal(); return; }
    if (document.getElementById('shareModal').classList.contains('open'))  { closeShareModal(); return; }
    if (document.getElementById('commentsOverlay').classList.contains('open')) { closeComments(null,true); return; }
});

// ── HELPERS ───────────────────────────────────────────────────
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
