// ================================================================
// notifications.js — StudyHub Notification System
//
// Handles:
//  • Scheduled event/task reminders (day_before, 3hr, 1hr, 30min, overdue)
//  • Real-time: new direct messages    (source_type = "message")
//  • Real-time: friend request received (trigger = "friend_request_received")
//  • Real-time: friend request accepted (trigger = "friend_request_accepted")
// ================================================================

const NOTIF = {
    checkMs: 60_000,
    maxPanel: 30,
    table: "notifications",
    windowMs: 90_000, // ±90s window for non-overdue scheduled triggers

    triggers: [
        {
            key: "day_before",
            label: "1 day before",
            offsetMs: 24 * 60 * 60 * 1000,
        },
        { key: "3hr", label: "3 hours before", offsetMs: 3 * 60 * 60 * 1000 },
        { key: "1hr", label: "1 hour before", offsetMs: 1 * 60 * 60 * 1000 },
        { key: "30min", label: "30 min before", offsetMs: 30 * 60 * 1000 },
        { key: "overdue", label: "Overdue", offsetMs: 0 },
    ],
};

let _dropdown = null;
let _badgeEl = null;
let _cached = [];

// ================================================================
// PUBLIC ENTRY
// ================================================================
function initNotifications() {
    _buildDropdown();
    _wireBell();
    _tick();
    setInterval(_tick, NOTIF.checkMs);
}

// ================================================================
// TICK — only handles scheduled event/task reminders
// Real-time notifications (messages, friend requests) are inserted
// server-side in PHP; we just poll and render them here.
// ================================================================
async function _tick() {
    if (typeof UID === "undefined" || !UID) return;

    const nowMs = Date.now();

    const evSource =
        typeof expanded !== "undefined" && expanded.length
            ? expanded
            : typeof allEvents !== "undefined"
              ? allEvents
              : [];

    const candidates = [];

    evSource.forEach((ev) => {
        const dateStr = ev.idate || ev.event_date;
        if (!dateStr) return;
        const dueMs = ev.event_time
            ? new Date(`${dateStr}T${ev.event_time}`).getTime()
            : new Date(`${dateStr}T23:59:00`).getTime();
        candidates.push({
            source_type: "event",
            source_id: String(ev.id),
            title: ev.title,
            category: ev.category,
            dueMs,
        });
    });

    if (typeof allTasks !== "undefined") {
        allTasks.forEach((t) => {
            if (t.completed_at || !t.due_date) return;
            const dueMs = t.due_time
                ? new Date(`${t.due_date}T${t.due_time}`).getTime()
                : new Date(`${t.due_date}T23:59:00`).getTime();
            candidates.push({
                source_type: "task",
                source_id: String(t.id),
                title: t.title,
                priority: t.priority || "low",
                dueMs,
            });
        });
    }

    const toInsert = [];

    for (const item of candidates) {
        for (const trig of NOTIF.triggers) {
            let shouldFire = false;

            if (trig.key === "overdue") {
                const msSinceDue = nowMs - item.dueMs;
                shouldFire = msSinceDue >= 0 && msSinceDue <= NOTIF.windowMs;
            } else {
                const fireAtMs = item.dueMs - trig.offsetMs;
                shouldFire = Math.abs(nowMs - fireAtMs) <= NOTIF.windowMs;
            }

            if (!shouldFire) continue;

            const dueDate = new Date(item.dueMs);
            toInsert.push({
                user_id: UID,
                source_type: item.source_type,
                source_id: item.source_id,
                trigger: trig.key,
                title: _buildTitle(item, trig),
                body: _buildBody(item, dueDate),
                icon: _itemIcon(item),
                urgency:
                    trig.key === "overdue" || item.priority === "high"
                        ? "urgent"
                        : "info",
            });
        }
    }

    if (toInsert.length) {
        await _sbInsert(toInsert);
    }

    await _loadAndRender();
}

// ================================================================
// SUPABASE HELPERS
// ================================================================
function _headers(write = false) {
    const key =
        write && typeof SB_SVC !== "undefined" && SB_SVC ? SB_SVC : SB_ANON;
    return {
        apikey: key,
        Authorization: `Bearer ${key}`,
        "Content-Type": "application/json",
        "Accept-Profile": "public",
        "Content-Profile": "public",
    };
}

async function _sbInsert(rows) {
    try {
        const res = await fetch(`${SB_URL}/rest/v1/${NOTIF.table}`, {
            method: "POST",
            headers: {
                ..._headers(true),
                Prefer: "resolution=ignore-duplicates",
            },
            body: JSON.stringify(rows),
        });
        if (!res.ok && res.status !== 409) {
            const err = await res.json().catch(() => ({}));
            console.warn("[notifications] insert failed:", res.status, err);
        }
    } catch (e) {
        console.warn("[notifications] insert error:", e);
    }
}

async function _loadAndRender() {
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/${NOTIF.table}?user_id=eq.${UID}&order=created_at.desc&limit=${NOTIF.maxPanel}`,
            { headers: _headers() },
        );
        if (res.ok) {
            _cached = await res.json();
        } else {
            const err = await res.json().catch(() => ({}));
            console.warn("[notifications] load failed:", res.status, err);
        }
    } catch (e) {
        console.warn("[notifications] load error:", e);
    }
    _renderList();
    _updateBadge();
}

async function _sbPatch(filter, data) {
    try {
        const res = await fetch(`${SB_URL}/rest/v1/${NOTIF.table}?${filter}`, {
            method: "PATCH",
            headers: _headers(true),
            body: JSON.stringify(data),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            console.warn("[notifications] patch failed:", res.status, err);
        }
    } catch (e) {
        console.warn("[notifications] patch error:", e);
    }
}

async function markNotifRead(id) {
    await _sbPatch(`id=eq.${id}`, { read: true });
    const n = _cached.find((x) => x.id === id);
    if (n) n.read = true;
    _renderList();
    _updateBadge();
}

async function markAllNotifsRead() {
    await _sbPatch(`user_id=eq.${UID}&read=eq.false`, { read: true });
    _cached.forEach((n) => (n.read = true));
    _renderList();
    _updateBadge();
}

// ================================================================
// BUILD DROPDOWN
// ================================================================
function _buildDropdown() {
    document.getElementById("notifDropdown")?.remove();

    _dropdown = document.createElement("div");
    _dropdown.id = "notifDropdown";
    _dropdown.style.cssText = `
        display:none;position:fixed;z-index:99999;width:360px;max-height:520px;
        background:var(--bg-card,#fff);border:1px solid var(--border,rgba(0,0,0,.14));
        border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.16);
        flex-direction:column;overflow:hidden;
    `;
    _dropdown.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;
            padding:14px 16px 12px;border-bottom:1px solid var(--border,rgba(0,0,0,.08));flex-shrink:0;">
            <span style="font-size:15px;font-weight:700;color:var(--text-primary,#111);">
                🔔 Notifications
            </span>
            <button onclick="markAllNotifsRead()"
                style="font-size:12px;color:var(--primary,#1a5f7a);background:none;border:none;
                cursor:pointer;font-weight:600;padding:4px 8px;border-radius:6px;"
                onmouseenter="this.style.background='var(--border,rgba(0,0,0,.07))'"
                onmouseleave="this.style.background='none'">
                Mark all read
            </button>
        </div>
        <div id="notifDropList" style="overflow-y:auto;flex:1;min-height:0;"></div>
        <div style="border-top:1px solid var(--border,rgba(0,0,0,.08));
            padding:11px 16px;flex-shrink:0;text-align:center;">
            <a href="/notifications"
                style="font-size:13px;font-weight:600;color:var(--primary,#1a5f7a);text-decoration:none;">
                See all notifications →
            </a>
        </div>
    `;
    document.body.appendChild(_dropdown);

    document.addEventListener("click", (e) => {
        if (
            _dropdown.style.display === "flex" &&
            !_dropdown.contains(e.target) &&
            !e.target.closest("[data-notif-bell]")
        ) {
            _dropdown.style.display = "none";
        }
    });
}

function _renderList() {
    const list = document.getElementById("notifDropList");
    if (!list) return;

    if (!_cached.length) {
        list.innerHTML = `
            <div style="padding:40px 20px;text-align:center;color:var(--text-secondary,#777);">
                <div style="font-size:30px;margin-bottom:8px;">🎉</div>
                <div style="font-size:13px;font-weight:600;">All caught up!</div>
                <div style="font-size:12px;margin-top:4px;opacity:.7;">No notifications yet.</div>
            </div>`;
        return;
    }

    list.innerHTML = _cached
        .map((n) => {
            const border =
                n.urgency === "urgent" ? "#dc2626" : "var(--primary,#1a5f7a)";
            const bg = n.read ? "transparent" : "rgba(26,95,122,0.045)";
            const ago = _timeAgo(new Date(n.created_at));
            const clickUrl = _notifClickUrl(n);
            const clickJs = clickUrl
                ? `markNotifRead('${n.id}'); window.location='${clickUrl}';`
                : `markNotifRead('${n.id}')`;

            return `<div onclick="${clickJs}" style="
                display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
                border-bottom:1px solid var(--border,rgba(0,0,0,.06));
                border-left:3px solid ${n.read ? "transparent" : border};
                background:${bg};cursor:pointer;transition:background .12s;"
                onmouseenter="this.style.background='rgba(26,95,122,0.07)'"
                onmouseleave="this.style.background='${bg}'">
                <div style="font-size:22px;flex-shrink:0;line-height:1.2;margin-top:1px;">${n.icon || "🔔"}</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:${n.read ? "500" : "700"};
                        color:var(--text-primary,#111);
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        ${_esc(n.title)}
                    </div>
                    ${
                        n.body
                            ? `<div style="font-size:11px;color:var(--text-secondary,#666);
                        margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        ${_esc(n.body)}</div>`
                            : ""
                    }
                    <div style="font-size:10px;color:var(--text-light,#aaa);margin-top:5px;">${ago}</div>
                </div>
                ${
                    !n.read
                        ? `<div style="width:8px;height:8px;border-radius:50%;
                    background:var(--primary,#1a5f7a);flex-shrink:0;margin-top:5px;"></div>`
                        : ""
                }
            </div>`;
        })
        .join("");
}

// ================================================================
// CLICK-THROUGH URLS
// Map notification types to meaningful navigation targets.
// ================================================================
function _notifClickUrl(n) {
    switch (n.source_type) {
        case "message":
            // source_id is the message id; navigate to the conversation with the sender
            // Adjust the URL pattern to match your routes.
            return n.source_id ? `/messages` : null;

        case "friend_request":
            if (n.trigger === "friend_request_received") {
                return `/friend-requests?tab=requests`;
            }
            if (n.trigger === "friend_request_accepted") {
                // source_id is the accepter's user id
                return `/messages`;
            }
            return `/friend-requests`;

        default:
            return null;
    }
}

// ================================================================
// BADGE
// ================================================================
function _updateBadge() {
    if (!_badgeEl) return;
    const unread = _cached.filter((n) => !n.read).length;
    if (unread === 0) {
        _badgeEl.style.display = "none";
    } else {
        Object.assign(_badgeEl.style, {
            display: "inline-flex",
            alignItems: "center",
            justifyContent: "center",
            position: "absolute",
            top: "-5px",
            right: "-5px",
            minWidth: "18px",
            height: "18px",
            padding: "0 4px",
            borderRadius: "99px",
            background: "#dc2626",
            color: "white",
            fontSize: "10px",
            fontWeight: "700",
            border: "2px solid var(--bg-body, white)",
            pointerEvents: "none",
            lineHeight: "1",
        });
        _badgeEl.textContent = unread > 99 ? "99+" : String(unread);
    }
}

// ================================================================
// WIRE BELL
// ================================================================
function _wireBell() {
    let bellLink =
        document.querySelector('a[href*="notification"]') ||
        document.querySelector(".top-bar-btn");

    if (!bellLink) {
        console.warn(
            "[notifications] bell element not found — dropdown won't open",
        );
        return;
    }

    bellLink.setAttribute("data-notif-bell", "true");
    bellLink.style.position = "relative";

    bellLink.addEventListener("click", (e) => {
        e.preventDefault();
        _toggleDropdown(bellLink);
    });

    const staticDot = bellLink.querySelector(".notif-dot");
    const badge = document.createElement("span");
    badge.id = "notifCountBadge";
    badge.style.display = "none";
    if (staticDot) staticDot.replaceWith(badge);
    else bellLink.appendChild(badge);

    _badgeEl = badge;
}

function _toggleDropdown(anchor) {
    if (_dropdown.style.display === "flex") {
        _dropdown.style.display = "none";
        return;
    }
    const r = anchor.getBoundingClientRect();
    let left = r.right - 360;
    if (left < 8) left = 8;
    _dropdown.style.top = r.bottom + 8 + "px";
    _dropdown.style.left = left + "px";
    _dropdown.style.display = "flex";
    _renderList();
}

// ================================================================
// CONTENT BUILDERS (scheduled event/task notifications)
// ================================================================
function _buildTitle(item, trig) {
    if (trig.key === "overdue") return `⚠️ Overdue: ${item.title}`;
    const prefix = item.source_type === "task" ? "✅" : "📅";
    return `${prefix} ${trig.label}: ${item.title}`;
}

function _buildBody(item, dueDate) {
    const parts = [];
    if (item.source_type === "event" && item.category) {
        parts.push(_catLabel(item.category));
    } else if (item.source_type === "task" && item.priority) {
        parts.push(`${_priIcon(item.priority)} ${item.priority} priority`);
    }
    const isEOD = dueDate.getHours() === 23 && dueDate.getMinutes() === 59;
    const timeStr = isEOD
        ? "all day"
        : _fmt12(
              `${String(dueDate.getHours()).padStart(2, "0")}:${String(dueDate.getMinutes()).padStart(2, "0")}`,
          );
    const dateStr = dueDate.toLocaleDateString("en-US", {
        weekday: "short",
        month: "short",
        day: "numeric",
    });
    parts.push(`${dateStr} · ${timeStr}`);
    return parts.join(" · ");
}

function _itemIcon(item) {
    if (item.source_type === "task") return _priIcon(item.priority);
    return _evIcon(item.category);
}

// ================================================================
// UTILS
// ================================================================
function _evIcon(cat) {
    return { class: "📗", group: "👥", event: "📅", todo: "📌" }[cat] || "📅";
}
function _catLabel(cat) {
    return (
        { class: "Class", group: "Study Group", event: "Event", todo: "To-do" }[
            cat
        ] || cat
    );
}
function _priIcon(pri) {
    return { high: "🔴", medium: "🟡", low: "🟢" }[pri] || "✅";
}

function _fmt12(t) {
    if (!t) return "";
    const [h, m] = t.split(":").map(Number);
    return `${h === 0 ? 12 : h > 12 ? h - 12 : h}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
}

function _esc(s) {
    if (s == null) return "";
    const d = document.createElement("div");
    d.textContent = String(s);
    return d.innerHTML;
}

function _timeAgo(date) {
    const diff = Math.floor((Date.now() - date) / 1000);
    if (diff < 10) return "just now";
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}
