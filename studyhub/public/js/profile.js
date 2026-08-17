/* ============================================================
   PROFILE PAGE — profile.js
   Posts rendered like the newsfeed: votes, comments drawer,
   edit modal with media/file/link support, delete.
============================================================ */

const SB_URL =
    document.querySelector('meta[name="data-supabase-url"]')?.content || "";

const SB_KEY =
    document.querySelector('meta[name="data-supabase-key"]')?.content || "";

const SB_SVC =
    document.querySelector('meta[name="data-supabase-service-key"]')?.content || "";

const currentUser = {
    id:                document.querySelector('meta[name="data-user-id"]')?.content || "",
    first_name:        document.querySelector('meta[name="data-user-first-name"]')?.content || "",
    last_name:         document.querySelector('meta[name="data-user-last-name"]')?.content || "",
    username:          document.querySelector('meta[name="data-user-username"]')?.content || "",
    profile_photo_url: document.querySelector('meta[name="data-user-photo"]')?.content || "",
};

const profileData = window.profileData || {};

/* ── Supabase JS client (reuse global if newsfeed loaded it) ─ */
let _sb = null;
if (typeof supabase !== "undefined" && typeof SUPABASE_URL !== "undefined") {
    _sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
} else {
    /* Minimal fetch helpers when the Supabase CDN is not on the page */
    _sb = null;
}

/* ── State ── */
let editingPostId    = null;
let editStagedMedia  = [];
let editStagedFiles  = [];
let editExistingMedia = [];
let editExistingFiles = [];
let editStagedLink   = null;
let editStagedTags   = [];
let activePostId     = null;

let lightboxImages = [];
let lightboxIndex  = 0;

const SUBJECT_TAGS = [
    { label: "Mathematics",  value: "Mathematics",  cls: "tag-color-math"        },
    { label: "Physics",      value: "Physics",       cls: "tag-color-physics"     },
    { label: "Chemistry",    value: "Chemistry",     cls: "tag-color-chemistry"   },
    { label: "Biology",      value: "Biology",       cls: "tag-color-biology"     },
    { label: "Engineering",  value: "Engineering",   cls: "tag-color-engineering" },
    { label: "CS / IT",      value: "CS",            cls: "tag-color-cs"          },
    { label: "History",      value: "History",       cls: "tag-color-history"     },
    { label: "Literature",   value: "Literature",    cls: "tag-color-literature"  },
    { label: "Economics",    value: "Economics",     cls: "tag-color-economics"   },
    { label: "Other",        value: "Other",         cls: "tag-color-other"       },
];

/* ============================================================
   INIT
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    renderUserUI();
    loadProfilePosts();
    loadProfileStats();
    initNotifications?.();

    const photoBtn   = document.getElementById("profilePhotoButton");
    const photoInput = document.getElementById("profilePhotoInput");
    if (photoBtn)   photoBtn.addEventListener("click", () => photoInput?.click());
    if (photoInput) photoInput.addEventListener("change", handleProfilePhotoChange);

    /* Close dropdowns on outside click */
    document.addEventListener("click", e => {
        if (!e.target.closest(".post-menu-wrap")) {
            document.querySelectorAll(".post-menu-dropdown.open")
                .forEach(el => el.classList.remove("open"));
        }
    });

    /* Lightbox keyboard nav */
    document.addEventListener("keydown", e => {
        const lb = document.getElementById("pf-lightbox");
        if (!lb?.classList.contains("open")) return;
        if (e.key === "ArrowRight") pfLightboxNext();
        if (e.key === "ArrowLeft")  pfLightboxPrev();
        if (e.key === "Escape")     pfCloseLightbox();
    });
});

/* ============================================================
   USER UI
============================================================ */
function renderUserUI() {
    const firstName = profileData.first_name || currentUser.first_name;
    const lastName  = profileData.last_name  || currentUser.last_name;
    const username  = profileData.username   || currentUser.username;
    const photoUrl  = profileData.profile_photo_url || currentUser.profile_photo_url;
    const fullName  = profileData.display_name || `${firstName} ${lastName}`.trim() || username || "You";
    const initials  = mkInitials(firstName, lastName);

    setAvatar("profileAvatarLarge", photoUrl, fullName, initials);
    setAvatar("sidebarAvatar",      photoUrl, fullName, initials);
    setAvatar("topBarAvatar",       photoUrl, fullName, initials);

    setText("profileFullName", fullName);
    setText("profileUsername", username ? `@${username}` : "");
    setText("profileBio", profileData.bio || "");
}

/* ============================================================
   PROFILE STATS  (followers, following, joined date)
============================================================ */
async function loadProfileStats() {
    if (!currentUser.id) return;

    try {
        /* Run all three fetches in parallel */
        const [profileRes, followersRes, followingRes] = await Promise.all([
            /* Profile row — for created_at (joined date) */
            sbSvcFetch(`${SB_URL}/rest/v1/profiles?id=eq.${currentUser.id}&select=created_at`),
            /* Count of people who follow this user */
            sbSvcFetch(`${SB_URL}/rest/v1/follows?following_id=eq.${currentUser.id}&select=follower_id`),
            /* Count of people this user follows */
            sbSvcFetch(`${SB_URL}/rest/v1/follows?follower_id=eq.${currentUser.id}&select=following_id`),
        ]);

        const profileRows  = await profileRes.json();
        const followersRows = await followersRes.json();
        const followingRows = await followingRes.json();

        /* ── Joined date ── */
        const createdAt = profileRows?.[0]?.created_at;
        if (createdAt) {
            const d = new Date(createdAt);
            const formatted = d.toLocaleDateString("en-US", { year: "numeric", month: "long" });
            setText("profileJoinedDate", formatted);
        }

        /* ── Follower / following counts ── */
        const followerCount = Array.isArray(followersRows) ? followersRows.length : 0;
        const followingCount = Array.isArray(followingRows) ? followingRows.length : 0;

        setText("followerCount",  String(followerCount));
        setText("followingCount", String(followingCount));

    } catch(err) {
        console.warn("[loadProfileStats]", err.message);
        /* Leave the — placeholders as-is on error */
    }
}

/* ── Follow modal (followers / following list) ── */
async function openFollowModal(type) {
    const modal    = document.getElementById("followModal");
    const titleEl  = document.getElementById("followModalTitle");
    const listEl   = document.getElementById("followModalList");
    if (!modal) return;

    titleEl.textContent = type === "followers" ? "Followers" : "Following";
    listEl.innerHTML = `<div class="loading"><div class="loading-spinner"></div></div>`;
    modal.classList.add("open");

    try {
        let users = [];

        if (type === "followers") {
            /* Who follows me → join profiles on follower_id */
            const res  = await sbSvcFetch(
                `${SB_URL}/rest/v1/follows?following_id=eq.${currentUser.id}&select=follower_id,profiles!follower_id(id,first_name,last_name,username,profile_photo_url)`
            );
            const rows = await res.json();
            users = (Array.isArray(rows) ? rows : []).map(r => r.profiles).filter(Boolean);
        } else {
            /* Who I follow → join profiles on following_id */
            const res  = await sbSvcFetch(
                `${SB_URL}/rest/v1/follows?follower_id=eq.${currentUser.id}&select=following_id,profiles!following_id(id,first_name,last_name,username,profile_photo_url)`
            );
            const rows = await res.json();
            users = (Array.isArray(rows) ? rows : []).map(r => r.profiles).filter(Boolean);
        }

        if (!users.length) {
            listEl.innerHTML = `<div class="follow-modal-empty">Nobody here yet.</div>`;
            return;
        }

        listEl.innerHTML = users.map(u => {
            const fullName = `${u.first_name || ""} ${u.last_name || ""}`.trim() || u.username || "User";
            const uname    = u.username ? `@${u.username}` : "";
            const initials = ((u.first_name || "?")[0] + (u.last_name || "?")[0]).toUpperCase();
            const avInner  = u.profile_photo_url
                ? `<img src="${escH(u.profile_photo_url)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
                : initials;
            const href = `/profile/${escH(u.username || u.id)}`;
            return `
            <a class="follow-modal-item" href="${href}">
                <div class="follow-modal-avatar">${avInner}</div>
                <div class="follow-modal-info">
                    <div class="follow-modal-name">${escH(fullName)}</div>
                    <div class="follow-modal-username">${escH(uname)}</div>
                </div>
                <svg class="follow-modal-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>`;
        }).join("");

    } catch(err) {
        listEl.innerHTML = `<div class="follow-modal-empty">Failed to load.</div>`;
        console.warn("[followModal]", err.message);
    }
}

function closeFollowModal() {
    document.getElementById("followModal")?.classList.remove("open");
}


async function loadProfilePosts() {
    const feed = document.getElementById("profileFeed");
    if (!feed || !currentUser.id) return;

    try {
        const res   = await sbSvcFetch(
            `${SB_URL}/rest/v1/posts?select=*&user_id=eq.${currentUser.id}&order=created_at.desc`
        );
        const posts = await res.json();

        setText("statPostCount",  String(posts.length));
        setText("postCountBadge", `${posts.length} post${posts.length !== 1 ? "s" : ""}`);

        if (!posts.length) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">✏️</div>
                    <h3>No posts yet</h3>
                    <p>Your posts will appear here once you share something.</p>
                </div>`;
            return;
        }

        /* Render skeleton cards first */
        feed.innerHTML = posts.map(p => postCardHTML(p, 0, 0, 0, 0, 0)).join("");

        /* Fetch votes + comment counts in parallel */
        const postIds = posts.map(p => p.id);
        const idsParam = postIds.map(id => `"${id}"`).join(",");

        const [votesRes, commentsRes, myVotesRes] = await Promise.all([
            sbSvcFetch(`${SB_URL}/rest/v1/posts_votes?select=post_id,vote&post_id=in.(${postIds.join(",")})`),
            sbSvcFetch(`${SB_URL}/rest/v1/post_comments?select=post_id&post_id=in.(${postIds.join(",")})`),
            sbSvcFetch(`${SB_URL}/rest/v1/posts_votes?select=post_id,vote&post_id=in.(${postIds.join(",")})&user_id=eq.${currentUser.id}`),
        ]);

        const votes    = await votesRes.json();
        const comments = await commentsRes.json();
        const myVotes  = await myVotesRes.json();

        const scores = {}, upCounts = {}, downCounts = {}, commentCounts = {}, myVoteMap = {};

        (Array.isArray(votes) ? votes : []).forEach(r => {
            scores[r.post_id]    = (scores[r.post_id]    || 0) + r.vote;
            if (r.vote ===  1) upCounts[r.post_id]   = (upCounts[r.post_id]   || 0) + 1;
            if (r.vote === -1) downCounts[r.post_id]  = (downCounts[r.post_id] || 0) + 1;
        });
        (Array.isArray(comments) ? comments : []).forEach(r => {
            commentCounts[r.post_id] = (commentCounts[r.post_id] || 0) + 1;
        });
        (Array.isArray(myVotes) ? myVotes : []).forEach(r => {
            myVoteMap[r.post_id] = r.vote;
        });

        feed.innerHTML = posts.map(p =>
            postCardHTML(p, scores[p.id] || 0, commentCounts[p.id] || 0,
                myVoteMap[p.id] || 0, upCounts[p.id] || 0, downCounts[p.id] || 0)
        ).join("");

    } catch (err) {
        feed.innerHTML = `<div class="error-state">${escH(err.message)}</div>`;
    }
}

/* ============================================================
   POST CARD HTML  (newsfeed-style)
============================================================ */
function postCardHTML(post, score, commentCount, myVote, upvoteCount, downvoteCount) {
    const author   = currentUser;
    const fullName = profileData.display_name ||
        `${currentUser.first_name} ${currentUser.last_name}`.trim() ||
        currentUser.username || "You";
    const photo    = currentUser.profile_photo_url;
    const ago      = timeAgo(post.created_at);
    const visMap   = { public: "🌐", only_me: "🔒" };
    const visIcon  = visMap[post.visibility] || "🌐";

    const media    = safeJSON(post.media_urls, []);
    const files    = safeJSON(post.file_urls,  []);
    const linkMeta = safeJSON(post.link_meta,  null);
    const tags     = safeJSON(post.tags,       []);

    const upvoted   = myVote ===  1;
    const downvoted = myVote === -1;

    /* Tags row */
    let tagsHTML = "";
    if (tags.length) {
        const tagDefs = Object.fromEntries(SUBJECT_TAGS.map(t => [t.value, t]));
        tagsHTML = `<div class="post-tags-row">${
            tags.map(t => {
                const def = tagDefs[t] || { label: t, cls: "tag-color-other" };
                return `<span class="post-tag-badge ${def.cls}">${escH(def.label)}</span>`;
            }).join("")
        }</div>`;
    }

    /* Media grid */
    const mediaHTML = media.length ? buildMediaGrid(media, post.id) : "";

    /* Files */
    let filesHTML = "";
    if (files.length) {
        filesHTML = `<div class="post-files">${files.map(f => {
            const fname = f.name || (f.url || "").split("/").pop() || "File";
            const fsize = f.size ? formatBytes(f.size) : "";
            return `<a class="post-file-row" href="${escH(f.url)}" target="_blank" download>
                <span class="post-file-icon">${fileEmojiFromName(fname)}</span>
                <span class="post-file-name">${escH(fname)}</span>
                ${fsize ? `<span class="post-file-size">${fsize}</span>` : ""}
            </a>`;
        }).join("")}</div>`;
    }

    /* Link preview */
    let linkHTML = "";
    if (linkMeta && linkMeta.url) {
        linkHTML = `<a class="post-link-preview" href="${escH(linkMeta.url)}" target="_blank" rel="noopener">
            ${linkMeta.image ? `<img class="post-link-img" src="${escH(linkMeta.image)}" alt="">` : ""}
            <div class="post-link-info">
                <div class="post-link-title">${escH(linkMeta.title || linkMeta.url)}</div>
                <div class="post-link-url">${escH(linkMeta.url)}</div>
            </div>
        </a>`;
    }

    /* Text with "see more" */
    const text     = post.content || "";
    const longText = text.length > 300;
    const textHTML = text ? `
        <div class="post-text${longText ? " collapsed" : ""}" id="pfPostText-${post.id}">${escH(text)}</div>
        ${longText ? `<button class="post-see-more" onclick="pfToggleText('${post.id}',this)">See more</button>` : ""}
    ` : "";

    /* Avatar HTML */
    const avatarInner = photo
        ? `<img src="${escH(photo)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
        : mkInitials(currentUser.first_name, currentUser.last_name);

    const commentLabel = commentCount > 0
        ? `${commentCount} comment${commentCount !== 1 ? "s" : ""}` : "Comment";

    return `
    <div class="post-card" id="post-${post.id}">
      <div class="post-header">
        <div class="post-author">
          <div class="post-avatar">${avatarInner}</div>
          <div>
            <div class="post-author-name">${escH(fullName)}</div>
            <div class="post-author-meta">
              <span>${ago}</span>
              <span>&middot;</span>
              <span class="post-vis-badge" title="${post.visibility}">${visIcon}</span>
            </div>
          </div>
        </div>
        <div class="post-menu-wrap">
          <button class="post-menu-btn" onclick="pfToggleMenu('${post.id}')">&#8943;</button>
          <div class="post-menu-dropdown" id="postMenu-${post.id}">
            <button class="post-menu-item" onclick="pfOpenEdit('${post.id}')">✏️ Edit Post</button>
            <button class="post-menu-item danger" onclick="pfDeletePost('${post.id}')">🗑 Delete Post</button>
          </div>
        </div>
      </div>

      <div class="post-body">
        ${tagsHTML}
        ${textHTML}
      </div>

      ${mediaHTML}
      ${filesHTML}
      ${linkHTML}

      <div class="post-action-bar-b">
        <div class="vote-group-b">
          <button class="vote-btn-b ${upvoted ? "upvoted-b" : ""}" id="pfUpBtn-${post.id}"
            onclick="pfCastVote('${post.id}', 1)" title="Upvote">
            <svg viewBox="0 0 24 24" fill="${upvoted ? "currentColor" : "none"}" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><polyline points="18 15 12 9 6 15"/></svg>
            <span class="vote-count" id="pfUpCount-${post.id}">${upvoteCount}</span>
          </button>
          <button class="vote-btn-b ${downvoted ? "downvoted-b" : ""}" id="pfDownBtn-${post.id}"
            onclick="pfCastVote('${post.id}', -1)" title="Downvote">
            <svg viewBox="0 0 24 24" fill="${downvoted ? "currentColor" : "none"}" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><polyline points="6 9 12 15 18 9"/></svg>
            <span class="vote-count" id="pfDownCount-${post.id}">${downvoteCount}</span>
          </button>
        </div>
        <div style="display:flex;gap:2px;">
          <button class="post-action-btn-b" onclick="pfOpenComments('${post.id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <span id="pfCommentLabel-${post.id}">${commentLabel}</span>
          </button>
          <button class="post-action-btn-b" onclick="pfSharePost('${post.id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Share
          </button>
          <button class="post-action-btn-b delete-btn-b" onclick="pfDeletePost('${post.id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            Delete
          </button>
        </div>
      </div>
    </div>`;
}

/* ============================================================
   VOTING
============================================================ */
async function pfCastVote(postId, value) {
    if (!currentUser.id) return;

    const upBtn      = document.getElementById(`pfUpBtn-${postId}`);
    const downBtn    = document.getElementById(`pfDownBtn-${postId}`);
    const upCountEl  = document.getElementById(`pfUpCount-${postId}`);
    const downCountEl= document.getElementById(`pfDownCount-${postId}`);

    const prevUp     = upBtn?.classList.contains("upvoted-b");
    const prevDown   = downBtn?.classList.contains("downvoted-b");
    const prevVote   = prevUp ? 1 : prevDown ? -1 : 0;
    const newVote    = (value === 1 && prevUp) || (value === -1 && prevDown) ? 0 : value;

    const prevUpCount   = parseInt(upCountEl?.textContent   || "0");
    const prevDownCount = parseInt(downCountEl?.textContent || "0");

    /* Optimistic UI */
    if (upCountEl) {
        upCountEl.textContent = Math.max(0,
            prevUpCount + (newVote === 1 ? 1 : 0) - (prevVote === 1 ? 1 : 0));
    }
    if (downCountEl) {
        downCountEl.textContent = Math.max(0,
            prevDownCount + (newVote === -1 ? 1 : 0) - (prevVote === -1 ? 1 : 0));
    }
    upBtn?.classList.toggle("upvoted-b",   newVote ===  1);
    downBtn?.classList.toggle("downvoted-b", newVote === -1);
    if (upBtn)   upBtn.querySelector("svg").setAttribute("fill",   newVote ===  1 ? "currentColor" : "none");
    if (downBtn) downBtn.querySelector("svg").setAttribute("fill", newVote === -1 ? "currentColor" : "none");

    try {
        if (_sb) {
            if (newVote === 0) {
                await _sb.from("posts_votes").delete()
                    .eq("post_id", postId).eq("user_id", currentUser.id);
            } else {
                await _sb.from("posts_votes").upsert(
                    { post_id: postId, user_id: currentUser.id, vote: newVote },
                    { onConflict: "post_id,user_id" }
                );
            }
        } else {
            /* REST fallback */
            if (newVote === 0) {
                await fetch(`${SB_URL}/rest/v1/posts_votes?post_id=eq.${postId}&user_id=eq.${currentUser.id}`, {
                    method: "DELETE",
                    headers: { apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}` },
                });
            } else {
                await fetch(`${SB_URL}/rest/v1/posts_votes`, {
                    method: "POST",
                    headers: {
                        apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}`,
                        "Content-Type": "application/json",
                        Prefer: "resolution=merge-duplicates",
                    },
                    body: JSON.stringify({ post_id: postId, user_id: currentUser.id, vote: newVote }),
                });
            }
        }
    } catch(e) {
        console.error("[vote]", e);
        /* Rollback */
        if (upCountEl)   upCountEl.textContent   = prevUpCount;
        if (downCountEl) downCountEl.textContent = prevDownCount;
        upBtn?.classList.toggle("upvoted-b",   prevVote ===  1);
        downBtn?.classList.toggle("downvoted-b", prevVote === -1);
        if (upBtn)   upBtn.querySelector("svg").setAttribute("fill",   prevVote ===  1 ? "currentColor" : "none");
        if (downBtn) downBtn.querySelector("svg").setAttribute("fill", prevVote === -1 ? "currentColor" : "none");
    }
}

/* ============================================================
   MEDIA GRID  (identical to newsfeed helper)
============================================================ */
function buildMediaGrid(media, postId) {
    const cls   = media.length === 1 ? "count-1" : media.length === 2 ? "count-2" :
                  media.length === 3 ? "count-3" : media.length === 4 ? "count-4" : "count-many";
    const shown = media.slice(0, 5);
    const more  = media.length - 5;
    const imgUrls = media.filter(u => !/\.(mp4|mov|webm|ogg)(\?|$)/i.test(u));
    let imgIdx  = 0;

    return `<div class="post-media ${cls}">` +
        shown.map((url, i) => {
            const isVideo = /\.(mp4|mov|webm|ogg)(\?|$)/i.test(url);
            const inner   = isVideo
                ? `<video src="${escH(url)}" controls preload="none"></video>`
                : `<img src="${escH(url)}" alt="" loading="lazy">`;
            const overlay = (i === 4 && more > 0)
                ? `<div class="media-more-overlay">+${more}</div>` : "";
            if (isVideo) return `<div class="post-media-item">${inner}${overlay}</div>`;
            const idx = imgIdx++;
            const b64 = btoa(unescape(encodeURIComponent(JSON.stringify(imgUrls))));
            return `<div class="post-media-item" style="cursor:zoom-in;"
                onclick="pfOpenLightbox('${b64}',${idx})">${inner}${overlay}</div>`;
        }).join("") + "</div>";
}

/* ============================================================
   LIGHTBOX
============================================================ */
function pfOpenLightbox(b64, startIndex) {
    try { lightboxImages = JSON.parse(decodeURIComponent(escape(atob(b64)))); }
    catch(e) { console.error("lightbox decode", e); return; }
    lightboxIndex = startIndex;
    pfRenderLightbox();
    document.getElementById("pf-lightbox")?.classList.add("open");
    document.body.style.overflow = "hidden";
}
function pfRenderLightbox() {
    const img     = document.getElementById("pf-lightboxImg");
    const counter = document.getElementById("pf-lightboxCounter");
    if (img) img.src = lightboxImages[lightboxIndex] || "";
    if (counter) counter.textContent = `${lightboxIndex + 1} / ${lightboxImages.length}`;
    document.getElementById("pf-lightboxPrev").style.display = lightboxImages.length > 1 ? "" : "none";
    document.getElementById("pf-lightboxNext").style.display = lightboxImages.length > 1 ? "" : "none";
}
function pfLightboxNext() { lightboxIndex = (lightboxIndex + 1) % lightboxImages.length; pfRenderLightbox(); }
function pfLightboxPrev() { lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length; pfRenderLightbox(); }
function pfCloseLightbox() { document.getElementById("pf-lightbox")?.classList.remove("open"); document.body.style.overflow = ""; }

/* ============================================================
   TEXT EXPAND
============================================================ */
function pfToggleText(postId, btn) {
    const el = document.getElementById(`pfPostText-${postId}`);
    if (!el) return;
    const wasCollapsed = el.classList.contains("collapsed");
    el.classList.toggle("collapsed", !wasCollapsed);
    btn.textContent = wasCollapsed ? "See less" : "See more";
}

/* ============================================================
   POST MENU
============================================================ */
function pfToggleMenu(postId) {
    const menu   = document.getElementById(`postMenu-${postId}`);
    const isOpen = menu?.classList.contains("open");
    document.querySelectorAll(".post-menu-dropdown.open").forEach(m => m.classList.remove("open"));
    if (!isOpen) menu?.classList.add("open");
}

/* ============================================================
   COMMENTS DRAWER
============================================================ */
function pfOpenComments(postId) {
    activePostId = postId;
    document.getElementById("pf-commentsOverlay").classList.add("open");
    document.body.style.overflow = "hidden";
    pfLoadComments(postId);

    /* Populate drawer avatar */
    const av = document.getElementById("pf-commentsAvatar");
    if (av) {
        av.innerHTML = currentUser.profile_photo_url
            ? `<img src="${escH(currentUser.profile_photo_url)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
            : mkInitials(currentUser.first_name, currentUser.last_name);
    }
}
function pfCloseComments(e, force) {
    if (!force && e && e.target !== document.getElementById("pf-commentsOverlay")) return;
    document.getElementById("pf-commentsOverlay").classList.remove("open");
    document.body.style.overflow = "";
    activePostId = null;
}

async function pfLoadComments(postId) {
    const list = document.getElementById("pf-commentsList");
    list.innerHTML = `<div class="res-loading-sm">Loading…</div>`;
    try {
        let url = `${SB_URL}/rest/v1/post_comments?select=*,profiles(id,first_name,last_name,username,profile_photo_url)&post_id=eq.${postId}&parent_id=is.null&order=created_at.asc`;
        const r = await sbSvcFetch(url);
        const comments = await r.json();

        if (!Array.isArray(comments) || !comments.length) {
            list.innerHTML = `<div class="res-loading-sm">No comments yet!</div>`;
            pfUpdateCommentLabel(postId, 0);
            return;
        }

        /* Fetch replies */
        const parentIds = comments.map(c => c.id).join(",");
        const rr = await sbSvcFetch(
            `${SB_URL}/rest/v1/post_comments?select=*,profiles(id,first_name,last_name,username,profile_photo_url)&parent_id=in.(${parentIds})&order=created_at.asc`
        );
        const replies = await rr.json();
        const repliesMap = {};
        (Array.isArray(replies) ? replies : []).forEach(rep => {
            if (!repliesMap[rep.parent_id]) repliesMap[rep.parent_id] = [];
            repliesMap[rep.parent_id].push(rep);
        });

        const total = comments.length + Object.values(repliesMap).reduce((s, a) => s + a.length, 0);
        pfUpdateCommentLabel(postId, total);
        list.innerHTML = comments.map(c => pfCommentHTML(c, repliesMap[c.id] || [])).join("");
    } catch(e) {
        list.innerHTML = `<div class="res-loading-sm">Failed to load.</div>`;
    }
}

function pfUpdateCommentLabel(postId, total) {
    const el = document.getElementById(`pfCommentLabel-${postId}`);
    if (el) el.textContent = total > 0 ? `${total} comment${total !== 1 ? "s" : ""}` : "Comment";
}

function pfCommentHTML(c, replies) {
    const author = c.profiles || {};
    const name   = pfDisplayName(author);
    const isOwn  = c.user_id === currentUser.id;
    const avInner = author.profile_photo_url
        ? `<img src="${escH(author.profile_photo_url)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
        : ((author.first_name || "?")[0] + (author.last_name || "?")[0]).toUpperCase();

    const repliesHTML = (replies || []).map(r => pfCommentHTML(r, [])).join("");

    return `<div class="comment-item" id="pf-comment-${c.id}">
        <div class="comment-avatar">${avInner}</div>
        <div class="comment-body">
            <div class="comment-bubble">
                <div class="comment-name">${escH(name)}</div>
                <div class="comment-text" id="pf-ctext-${c.id}">${escH(c.content)}</div>
            </div>
            <div class="comment-meta">
                <span>${timeAgo(c.created_at)}</span>
                <button class="comment-action-btn" onclick="pfStartReply('${c.id}','${escH(name)}')">Reply</button>
                ${isOwn
                    ? `<button class="comment-action-btn" onclick="pfEditComment('${c.id}')">Edit</button>
                       <button class="comment-action-btn danger" onclick="pfDeleteComment('${c.id}')">Delete</button>`
                    : ""}
            </div>
            ${repliesHTML ? `<div class="comment-replies">${repliesHTML}</div>` : ""}
            <div id="pf-replyBox-${c.id}" class="reply-input-wrap" style="display:none;">
                <textarea class="reply-textarea" id="pf-replyInput-${c.id}"
                    placeholder="Reply to ${escH(name)}…"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();pfSubmitReply('${c.id}');}"></textarea>
                <button class="reply-send-btn" onclick="pfSubmitReply('${c.id}')">Send</button>
            </div>
        </div>
    </div>`;
}

function pfDisplayName(author) {
    if (!author) return "@unknown";
    if (author.username) return "@" + author.username;
    const n = ((author.first_name || "") + (author.last_name || "")).toLowerCase();
    return n ? "@" + n : "@unknown";
}

function pfStartReply(commentId, authorName) {
    document.querySelectorAll(`[id^="pf-replyBox-"]`).forEach(b => b.style.display = "none");
    const box = document.getElementById(`pf-replyBox-${commentId}`);
    if (box) { box.style.display = "flex"; document.getElementById(`pf-replyInput-${commentId}`)?.focus(); }
}

async function pfSubmitReply(parentId) {
    const inp  = document.getElementById(`pf-replyInput-${parentId}`);
    const text = inp?.value.trim();
    if (!text || !activePostId || !currentUser.id) return;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/post_comments`, {
            method: "POST",
            headers: {
                apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}`,
                "Content-Type": "application/json", Prefer: "return=representation",
            },
            body: JSON.stringify({ post_id: activePostId, user_id: currentUser.id, content: text, parent_id: parentId }),
        });
        if (!res.ok) throw new Error("Reply failed");
        if (inp) inp.value = "";
        pfLoadComments(activePostId);
    } catch(e) { alert(e.message); }
}

async function pfSubmitComment() {
    const input = document.getElementById("pf-commentInput");
    const text  = input?.value.trim();
    if (!text || !activePostId || !currentUser.id) return;
    const btn = document.getElementById("pf-commentSend");
    if (btn) btn.disabled = true;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/post_comments`, {
            method: "POST",
            headers: {
                apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}`,
                "Content-Type": "application/json", Prefer: "return=representation",
            },
            body: JSON.stringify({ post_id: activePostId, user_id: currentUser.id, content: text }),
        });
        if (!res.ok) throw new Error("Comment failed");
        input.value = "";
        pfLoadComments(activePostId);
    } catch(e) { alert(e.message); }
    finally { if (btn) btn.disabled = false; }
}

function pfEditComment(commentId) {
    const el = document.getElementById(`pf-ctext-${commentId}`);
    if (!el) return;
    const orig = el.textContent;
    el.innerHTML = `<textarea class="reply-textarea" style="width:100%;min-height:40px;" id="pf-cedit-${commentId}">${escH(orig)}</textarea>
        <div style="display:flex;gap:6px;margin-top:6px;justify-content:flex-end;">
            <button class="comment-action-btn" onclick="pfCancelEdit('${commentId}','${escH(orig)}')">Cancel</button>
            <button class="reply-send-btn" style="padding:5px 12px;font-size:12px;" onclick="pfSaveEdit('${commentId}')">Save</button>
        </div>`;
}
function pfCancelEdit(commentId, orig) {
    const el = document.getElementById(`pf-ctext-${commentId}`);
    if (el) el.innerHTML = escH(orig);
}
async function pfSaveEdit(commentId) {
    const inp = document.getElementById(`pf-cedit-${commentId}`);
    const text = inp?.value.trim();
    if (!text) return;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/post_comments?id=eq.${commentId}`, {
            method: "PATCH",
            headers: {
                apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}`,
                "Content-Type": "application/json", Prefer: "return=representation",
            },
            body: JSON.stringify({ content: text }),
        });
        if (!res.ok) throw new Error("Edit failed");
        const el = document.getElementById(`pf-ctext-${commentId}`);
        if (el) el.textContent = text;
    } catch(e) { alert(e.message); }
}
async function pfDeleteComment(commentId) {
    if (!confirm("Delete this comment?")) return;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/post_comments?id=eq.${commentId}`, {
            method: "DELETE",
            headers: { apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}` },
        });
        if (!res.ok) throw new Error("Delete failed");
        document.getElementById(`pf-comment-${commentId}`)?.remove();
        if (activePostId) pfLoadComments(activePostId);
    } catch(e) { alert(e.message); }
}

/* ============================================================
   SHARE
============================================================ */
function pfSharePost(postId) {
    navigator.clipboard.writeText(`${window.location.origin}/newsfeed#post-${postId}`)
        .then(() => alert("Post link copied!"))
        .catch(() => alert("Could not copy link."));
}

/* ============================================================
   EDIT POST
============================================================ */
async function pfOpenEdit(postId) {
    document.getElementById(`postMenu-${postId}`)?.classList.remove("open");
    editingPostId    = postId;
    editStagedMedia  = [];
    editStagedFiles  = [];

    try {
        const res  = await sbSvcFetch(`${SB_URL}/rest/v1/posts?id=eq.${postId}&select=*`);
        const data = await res.json();
        const post = Array.isArray(data) ? data[0] : data;

        editExistingMedia = safeJSON(post?.media_urls, []);
        editExistingFiles = safeJSON(post?.file_urls,  []);
        editStagedLink    = safeJSON(post?.link_meta,  null);
        editStagedTags    = safeJSON(post?.tags,       []);

        document.getElementById("pf-editContent").value   = post?.content  || "";
        document.getElementById("pf-editVis").value       = post?.visibility || "public";

        pfRenderEditMedia();
        pfRenderEditFiles();
        pfRenderEditLink();
        pfRenderEditTags();

        const lr = document.getElementById("pf-editLinkRow");
        if (lr) lr.style.display = "none";

        document.getElementById("pf-editModal").classList.add("open");
    } catch(e) { alert("Could not load post: " + e.message); }
}

function pfCloseEdit() {
    document.getElementById("pf-editModal").classList.remove("open");
    editingPostId = null;
    editStagedMedia.forEach(m => URL.revokeObjectURL(m.objectUrl));
    editStagedMedia  = [];
    editStagedFiles  = [];
    editExistingMedia = [];
    editExistingFiles = [];
    editStagedLink   = null;
    editStagedTags   = [];
}

async function pfSavePost() {
    if (!editingPostId) return;
    const content = document.getElementById("pf-editContent").value.trim();
    const vis     = document.getElementById("pf-editVis").value;
    const btn     = document.getElementById("pf-editSaveBtn");
    btn.disabled  = true;
    btn.textContent = "Saving…";

    try {
        const finalMedia = [...editExistingMedia];
        for (const m of editStagedMedia) {
            const ext  = m.file.name.split(".").pop();
            const path = `${currentUser.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            if (_sb) {
                const u = await _sb.storage.from("posts").upload(path, m.file, { upsert: true });
                if (u.error) throw u.error;
                finalMedia.push(_sb.storage.from("posts").getPublicUrl(path).data.publicUrl);
            }
        }
        const finalFiles = [...editExistingFiles];
        for (const f of editStagedFiles) {
            const ext  = f.file.name.split(".").pop();
            const path = `${currentUser.id}/files/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            if (_sb) {
                const u = await _sb.storage.from("posts").upload(path, f.file, { upsert: true });
                if (u.error) throw u.error;
                finalFiles.push({ url: _sb.storage.from("posts").getPublicUrl(path).data.publicUrl, name: f.name, size: f.size });
            }
        }

        const res = await fetch(`${SB_URL}/rest/v1/posts?id=eq.${editingPostId}`, {
            method: "PATCH",
            headers: {
                apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}`,
                "Content-Type": "application/json", Prefer: "return=representation",
            },
            body: JSON.stringify({
                content:    content || null,
                visibility: vis,
                media_urls: finalMedia.length ? finalMedia : null,
                file_urls:  finalFiles.length ? finalFiles : null,
                link_meta:  editStagedLink   || null,
                tags:       editStagedTags.length ? editStagedTags : null,
            }),
        });
        if (!res.ok) throw new Error("Save failed");
        pfCloseEdit();
        loadProfilePosts();
    } catch(e) { alert(e.message); }
    finally { btn.disabled = false; btn.textContent = "Save Changes"; }
}

/* Edit modal — media */
function pfRenderEditMedia() {
    const grid = document.getElementById("pf-editMediaPreview");
    if (!grid) return;
    const items = [
        ...editExistingMedia.map((url, i) => ({ type: "existing", url, i, isVideo: /\.(mp4|mov|webm|ogg)(\?|$)/i.test(url) })),
        ...editStagedMedia.map((m, i) => ({ type: "new", url: m.objectUrl, i, isVideo: m.type === "video" })),
    ];
    const n = items.length;
    grid.className = `cp-media-preview count-${n > 4 ? "many" : n || 1}`;
    grid.innerHTML = items.map(item => `
        <div class="cp-media-item">
            ${item.isVideo ? `<video src="${escH(item.url)}" preload="metadata"></video>` : `<img src="${escH(item.url)}" alt="">`}
            <button class="cp-remove-media" onclick="${item.type === "existing" ? `pfRemoveExistingMedia(${item.i})` : `pfRemoveNewMedia(${item.i})`}">✕</button>
        </div>`
    ).join("");
}
function pfRemoveExistingMedia(i) { editExistingMedia.splice(i, 1); pfRenderEditMedia(); }
function pfRemoveNewMedia(i)      { URL.revokeObjectURL(editStagedMedia[i]?.objectUrl); editStagedMedia.splice(i, 1); pfRenderEditMedia(); }
function pfHandleEditMedia(fileList) {
    Array.from(fileList).forEach(f => {
        editStagedMedia.push({ file: f, objectUrl: URL.createObjectURL(f), type: f.type.startsWith("video") ? "video" : "image" });
    });
    pfRenderEditMedia();
}

/* Edit modal — files */
function pfRenderEditFiles() {
    const wrap = document.getElementById("pf-editFileChips");
    if (!wrap) return;
    const ex = editExistingFiles.map((f, i) =>
        `<div class="cp-file-chip">${fileEmojiFromName(f.name || "")} ${escH(f.name || f.url?.split("/").pop() || "File")}<button onclick="pfRemoveExistingFile(${i})">✕</button></div>`
    ).join("");
    const nw = editStagedFiles.map((f, i) =>
        `<div class="cp-file-chip" style="border-color:var(--primary);">${fileEmojiFromName(f.name)} ${escH(f.name)} <small>(new)</small><button onclick="pfRemoveNewFile(${i})">✕</button></div>`
    ).join("");
    wrap.innerHTML = ex + nw;
}
function pfRemoveExistingFile(i) { editExistingFiles.splice(i, 1); pfRenderEditFiles(); }
function pfRemoveNewFile(i)      { editStagedFiles.splice(i, 1); pfRenderEditFiles(); }
function pfHandleEditFiles(fileList) {
    Array.from(fileList).forEach(f => editStagedFiles.push({ file: f, name: f.name, size: f.size }));
    pfRenderEditFiles();
}

/* Edit modal — link */
function pfToggleEditLink() {
    const row = document.getElementById("pf-editLinkRow");
    if (!row) return;
    row.style.display = row.style.display === "none" ? "" : "none";
    if (row.style.display !== "none") {
        const inp = document.getElementById("pf-editLinkInput");
        if (inp) { inp.value = editStagedLink?.url || ""; inp.focus(); }
    }
}
async function pfFetchEditLink(url) {
    if (!url) return;
    editStagedLink = { url, title: url, image: null };
    pfRenderEditLink();
    document.getElementById("pf-editLinkRow").style.display = "none";
    try {
        const res = await fetch("/api/og-preview?url=" + encodeURIComponent(url), {
            headers: { "X-CSRF-TOKEN": typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "" },
        });
        if (res.ok) {
            const d = await res.json();
            if (d && !d.error) { editStagedLink = { url: d.url || url, title: d.title || url, image: d.image || null }; pfRenderEditLink(); }
        }
    } catch(e) { console.warn("[EditOG]", e.message); }
}
function pfRenderEditLink() {
    const el = document.getElementById("pf-editLinkPreview");
    if (!el) return;
    if (!editStagedLink) { el.style.display = "none"; el.innerHTML = ""; return; }
    el.style.display = "";
    el.innerHTML = `<div class="cp-link-preview-inner">
        ${editStagedLink.image ? `<img class="cp-link-preview-thumb" src="${escH(editStagedLink.image)}" alt="">` : ""}
        <div class="cp-link-preview-info">
            <div class="cp-link-preview-title">${escH(editStagedLink.title || editStagedLink.url)}</div>
            <div class="cp-link-preview-url">${escH(editStagedLink.url)}</div>
        </div>
        <button class="cp-link-preview-remove" onclick="editStagedLink=null;pfRenderEditLink()">✕</button>
    </div>`;
}

/* Edit modal — tags */
function pfRenderEditTags() {
    const row = document.getElementById("pf-editTagRow");
    if (!row) return;
    row.innerHTML = SUBJECT_TAGS.map(t =>
        `<button class="cp-tag-chip ${editStagedTags.includes(t.value) ? "selected" : ""}"
            onclick="pfToggleEditTag('${t.value}')">${escH(t.label)}</button>`
    ).join("");
}
function pfToggleEditTag(val) {
    if (editStagedTags.includes(val)) editStagedTags = editStagedTags.filter(t => t !== val);
    else editStagedTags.push(val);
    pfRenderEditTags();
}

/* ============================================================
   DELETE POST
============================================================ */
async function pfDeletePost(postId) {
    document.getElementById(`postMenu-${postId}`)?.classList.remove("open");
    if (!confirm("Delete this post? This cannot be undone.")) return;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/posts?id=eq.${postId}`, {
            method: "DELETE",
            headers: { apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}` },
        });
        if (!res.ok) throw new Error("Delete failed");
        document.getElementById(`post-${postId}`)?.remove();
        /* Update count */
        const countEl = document.getElementById("statPostCount");
        const badgeEl = document.getElementById("postCountBadge");
        if (countEl) {
            const n = Math.max(0, parseInt(countEl.textContent || "0") - 1);
            countEl.textContent = String(n);
            if (badgeEl) badgeEl.textContent = `${n} post${n !== 1 ? "s" : ""}`;
        }
        /* Show empty state if no posts remain */
        const feed = document.getElementById("profileFeed");
        if (feed && !feed.querySelector(".post-card")) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">✏️</div>
                    <h3>No posts yet</h3>
                    <p>Your posts will appear here once you share something.</p>
                </div>`;
        }
    } catch(e) { alert(e.message); }
}

/* ============================================================
   PROFILE EDIT
============================================================ */
function openProfileEditModal() {
    document.getElementById("editDisplayName").value = document.getElementById("profileFullName")?.textContent || "";
    document.getElementById("editBio").value         = profileData.bio || "";
    document.getElementById("profileEditModal").classList.add("open");
}
function closeProfileEditModal() {
    document.getElementById("profileEditModal").classList.remove("open");
}
async function saveProfileEdit() {
    const displayName = document.getElementById("editDisplayName").value.trim();
    const bio         = document.getElementById("editBio").value.trim();
    try {
        const res = await fetch(`${SB_URL}/rest/v1/profiles?id=eq.${currentUser.id}`, {
            method: "PATCH",
            headers: {
                apikey: SB_SVC, Authorization: `Bearer ${SB_SVC}`,
                "Content-Type": "application/json", Prefer: "return=representation",
            },
            body: JSON.stringify({ display_name: displayName, bio }),
        });
        if (!res.ok) throw new Error("Failed to update profile");
        profileData.display_name = displayName;
        profileData.bio          = bio;
        setText("profileFullName", displayName);
        setText("profileBio", bio);
        closeProfileEditModal();
    } catch(e) { alert(e.message); }
}

/* ============================================================
   PROFILE PHOTO
============================================================ */
async function handleProfilePhotoChange(event) {
    const file = event.target.files?.[0];
    if (!file || !currentUser.id) return;

    if (!["image/jpeg", "image/png"].includes(file.type)) {
        alert("Only JPEG and PNG photos are supported.");
        event.target.value = "";
        return;
    }

    const btn = document.getElementById("profilePhotoButton");
    if (btn) { btn.disabled = true; btn.textContent = "Uploading…"; }

    try {
        // 1. Upload file to Supabase Storage (profile-photos bucket)
        const ext  = file.name.split(".").pop();
        const path = `${currentUser.id}/profile.${ext}`;

        const uploadRes = await fetch(
            `${SB_URL}/storage/v1/object/profile-photos/${path}`,
            {
                method: "POST",
                headers: {
                    apikey: SB_SVC,
                    Authorization: `Bearer ${SB_SVC}`,
                    "Content-Type": file.type,
                    "x-upsert": "true",
                },
                body: file,
            }
        );
        if (!uploadRes.ok) {
            const err = await uploadRes.json().catch(() => ({}));
            throw new Error(err.message || "Upload failed");
        }

        // 2. Build the public URL
        const publicUrl = `${SB_URL}/storage/v1/object/public/profile-photos/${path}?t=${Date.now()}`;

        // 3. Save the URL to the profiles table in the database
        const patchRes = await fetch(
            `${SB_URL}/rest/v1/profiles?id=eq.${currentUser.id}`,
            {
                method: "PATCH",
                headers: {
                    apikey: SB_SVC,
                    Authorization: `Bearer ${SB_SVC}`,
                    "Content-Type": "application/json",
                    Prefer: "return=representation",
                },
                body: JSON.stringify({ profile_photo_url: publicUrl }),
            }
        );
        if (!patchRes.ok) throw new Error("Could not save photo to profile");

        // 4. Update all avatars visible on the page
        currentUser.profile_photo_url = publicUrl;
        profileData.profile_photo_url = publicUrl;
        ["profileAvatarLarge", "sidebarAvatar", "topBarAvatar"].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML =
                `<img src="${publicUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
        });

    } catch (e) {
        alert("Photo upload failed: " + e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = "Change photo"; }
        event.target.value = "";
    }
}

/* ============================================================
   KEYBOARD SHORTCUTS
============================================================ */
document.addEventListener("keydown", e => {
    if (e.key !== "Escape") return;
    if (document.getElementById("pf-editModal")?.classList.contains("open"))        { pfCloseEdit(); return; }
    if (document.getElementById("pf-commentsOverlay")?.classList.contains("open"))  { pfCloseComments(null, true); return; }
    if (document.getElementById("pf-lightbox")?.classList.contains("open"))         { pfCloseLightbox(); return; }
    if (document.getElementById("profileEditModal")?.classList.contains("open"))    { closeProfileEditModal(); return; }
    if (document.getElementById("followModal")?.classList.contains("open"))         { closeFollowModal(); return; }
});

/* ============================================================
   HELPERS
============================================================ */
function sbFetch(url) {
    return fetch(url, { headers: { apikey: SB_KEY, Authorization: `Bearer ${SB_KEY}` } });
}
function sbSvcFetch(url) {
    const key = SB_SVC || SB_KEY;
    return fetch(url, { headers: { apikey: key, Authorization: `Bearer ${key}` } });
}
function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}
function setAvatar(id, photoUrl, name, initials) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = photoUrl
        ? `<img src="${escH(photoUrl)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
        : initials;
}
function mkInitials(first, last) {
    return (((first || "?")[0]) + ((last || "?")[0])).toUpperCase();
}
function safeJSON(val, fallback) {
    if (!val) return fallback;
    if (typeof val === "object") return val;
    try { return JSON.parse(val); } catch { return fallback; }
}
function timeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return "Just now";
    if (s < 3600)   return `${Math.floor(s / 60)}m ago`;
    if (s < 86400)  return `${Math.floor(s / 3600)}h ago`;
    if (s < 604800) return `${Math.floor(s / 86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}
function formatBytes(b) {
    if (!b) return "";
    if (b < 1024)    return b + " B";
    if (b < 1048576) return (b / 1024).toFixed(1) + " KB";
    return (b / 1048576).toFixed(1) + " MB";
}
function fileEmojiFromName(name) {
    const ext = (name || "").split(".").pop().toLowerCase();
    const map = { pdf:"📄", doc:"📝", docx:"📝", ppt:"📊", pptx:"📊", mp4:"🎬", mov:"🎬", webm:"🎬", jpg:"🖼️", jpeg:"🖼️", png:"🖼️", gif:"🖼️", webp:"🖼️", zip:"🗜️", rar:"🗜️" };
    return map[ext] || "📎";
}
function escH(t) {
    if (t == null) return "";
    const d = document.createElement("div");
    d.textContent = String(t);
    return d.innerHTML;
}
