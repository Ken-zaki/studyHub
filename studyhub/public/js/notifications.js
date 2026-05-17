// ================================================================
// notifications.js — StudyHub Notification System
// Requires: studyhub-core.js loaded first (hdrs, sbReq, UID, SB_URL,
//           SB_ANON, SB_SVC, allEvents, allTasks, expanded)
//
// Handles:
//  • FR-3.6  Per-event reminder_minutes (user-configured: 10/30/60/1440)
//  • FR-3.4  Exam + Deadline categories in scheduled triggers
//  • Scheduled triggers: day_before, 12hr, 1hr, 30min, overdue, 1hr_after
//  • Real-time poll: new DMs (source_type="message")
//  • Real-time poll: friend_request_received / friend_request_accepted
// ================================================================

const NOTIF = {
    checkMs: 5_000, // scheduled-reminder check interval
    maxPanel: 30, // max rows shown in dropdown
    table: "notifications",
    windowMs: 90_000, // ±90 s fire window for non-overdue triggers

    // ── Standard scheduled triggers (used when event has NO reminder_minutes) ──
    triggers: [
        {
            key: "day_before",
            label: "1 day before",
            offsetMs: 24 * 60 * 60 * 1000,
        },
        {
            key: "12hr",
            label: "12 hours before",
            offsetMs: 12 * 60 * 60 * 1000,
        },
        { key: "1hr", label: "1 hour before", offsetMs: 1 * 60 * 60 * 1000 },
        { key: "30min", label: "30 min before", offsetMs: 30 * 60 * 1000 },
        { key: "overdue", label: "Overdue", offsetMs: 0 },
        {
            key: "1hr_after",
            label: "1 hour after",
            offsetMs: -1 * 60 * 60 * 1000,
        },
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
    setInterval(_loadAndRender, NOTIF.checkMs);
}

// ================================================================
// TICK — fires scheduled reminders for events AND tasks
// ================================================================
async function _tick() {
    if (typeof UID === "undefined" || !UID) return;

    const nowMs = Date.now();

    // ── Collect event candidates ─────────────────────────────────
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

        // FR-3.6 — per-event reminder_minutes overrides default trigger set
        const customReminder = Number(ev.reminder_minutes) || null;

        candidates.push({
            source_type: "event",
            source_id:
                ev.is_recurring && ev.recur_days?.length
                    ? `${ev.id}_${dateStr}`
                    : String(ev.id),
            title: ev.title,
            category: ev.category,
            subject_name: ev.subject_name || null,
            dueMs,
            customReminder, // minutes, e.g. 10 | 30 | 60 | 1440
        });
    });

    // ── Collect task candidates ──────────────────────────────────
    if (typeof allTasks !== "undefined") {
        allTasks.forEach((t) => {
            // Skip completed tasks (status = 'done' or legacy completed_at)
            if (t.status === "done" || t.completed_at || !t.due_date) return;

            const dueMs = t.due_time
                ? new Date(`${t.due_date}T${t.due_time}`).getTime()
                : new Date(`${t.due_date}T23:59:00`).getTime();

            candidates.push({
                source_type: "task",
                source_id: String(t.id),
                title: t.title,
                priority: t.priority || "low",
                subject_name: t.label || null, // FR-4.5 subject tag
                dueMs,
                customReminder: null, // tasks use standard triggers
            });
        });
    }

    // ── Evaluate each candidate against its trigger set ─────────
    const toInsert = [];

    for (const item of candidates) {
        const triggerSet = _triggersFor(item);

        for (const trig of triggerSet) {
            const shouldFire = _shouldFire(nowMs, item.dueMs, trig);
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
                urgency: _urgency(item, trig),
            });
        }
    }

    if (toInsert.length) await _sbInsert(toInsert);
    await _loadAndRender();
}

// ── Which triggers apply to this candidate? ──────────────────────
// FR-3.6: if the event has reminder_minutes set, use ONLY that single
// custom trigger (plus overdue/1hr_after which always apply).
function _triggersFor(item) {
    if (item.source_type !== "event") {
        return NOTIF.triggers;
    }

    if (!item.customReminder) {
        return NOTIF.triggers;
    }

    const customMs = item.customReminder * 60 * 1000;
    const customTrigger = {
        key: `custom_${item.customReminder}min`,
        label: _reminderLabel(item.customReminder),
        offsetMs: customMs,
        custom: true,
    };

    const overdueOnly = NOTIF.triggers.filter((t) => t.key === "overdue");
    const alreadyCovered = customMs === 0;
    return alreadyCovered ? overdueOnly : [customTrigger, ...overdueOnly];
}

function _reminderLabel(minutes) {
    if (minutes >= 1440)
        return `${minutes / 1440} day${minutes > 1440 ? "s" : ""} before`;
    if (minutes >= 60)
        return `${minutes / 60} hour${minutes > 60 ? "s" : ""} before`;
    return `${minutes} min before`;
}

// ── Fire-window logic ────────────────────────────────────────────
function _shouldFire(nowMs, dueMs, trig) {
    if (trig.key === "overdue") {
        const msSinceDue = nowMs - dueMs;
        return msSinceDue >= 0 && msSinceDue <= NOTIF.windowMs;
    }
    if (trig.key === "1hr_after") {
        const fireAtMs = dueMs - trig.offsetMs; // offsetMs is negative → adds 1hr
        return nowMs >= fireAtMs && nowMs - fireAtMs <= NOTIF.windowMs;
    }
    // Standard or custom "before" trigger
    const fireAtMs = dueMs - trig.offsetMs;
    return Math.abs(nowMs - fireAtMs) <= NOTIF.windowMs;
}

// ── Urgency ──────────────────────────────────────────────────────
function _urgency(item, trig) {
    if (trig.key === "overdue" || trig.key === "1hr_after") return "urgent";
    if (item.source_type === "task" && item.priority === "high")
        return "urgent";
    if (item.category === "exam" || item.category === "deadline")
        return "urgent";
    return "info";
}

// ================================================================
// SUPABASE HELPERS  (thin wrappers — sbReq comes from studyhub-core)
// ================================================================
async function _sbInsert(rows) {
    try {
        // Use raw fetch so we can set Prefer: ignore-duplicates
        const res = await fetch(`${SB_URL}/rest/v1/${NOTIF.table}`, {
            method: "POST",
            headers: {
                ...hdrs(true),
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
        _cached = await sbReq(
            `${NOTIF.table}?user_id=eq.${UID}&order=created_at.desc&limit=${NOTIF.maxPanel}`,
            { headers: hdrs() },
        );
        if (!Array.isArray(_cached)) _cached = [];
    } catch (e) {
        console.warn("[notifications] load error:", e);
    }
    _renderList();
    _updateBadge();
}

async function _sbPatch(filter, data) {
    try {
        await sbReq(`${NOTIF.table}?${filter}`, {
            method: "PATCH",
            headers: hdrs(true),
            body: JSON.stringify(data),
        });
    } catch (e) {
        console.warn("[notifications] patch error:", e);
    }
}

// ── Public mark-read helpers (called from inline onclick) ────────
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
        background:var(--bg-card,#fff);
        border:1px solid var(--border,rgba(0,0,0,.14));
        border-radius:16px;
        box-shadow:0 12px 40px rgba(0,0,0,.16);
        flex-direction:column;overflow:hidden;
    `;
    _dropdown.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;
            padding:14px 16px 12px;
            border-bottom:1px solid var(--border,rgba(0,0,0,.08));flex-shrink:0;">
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
                style="font-size:13px;font-weight:600;
                    color:var(--primary,#1a5f7a);text-decoration:none;">
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
            <div style="padding:40px 20px;text-align:center;
                color:var(--text-secondary,#777);">
                <div style="font-size:30px;margin-bottom:8px;">🎉</div>
                <div style="font-size:13px;font-weight:600;">All caught up!</div>
                <div style="font-size:12px;margin-top:4px;opacity:.7;">
                    No notifications yet.
                </div>
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

            return `
        <div onclick="${clickJs}"
            style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
                border-bottom:1px solid var(--border,rgba(0,0,0,.06));
                border-left:3px solid ${n.read ? "transparent" : border};
                background:${bg};cursor:pointer;transition:background .12s;"
            onmouseenter="this.style.background='rgba(26,95,122,0.07)'"
            onmouseleave="this.style.background='${bg}'">
            <div style="font-size:22px;flex-shrink:0;line-height:1.2;margin-top:1px;">
                ${n.icon || "🔔"}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:${n.read ? "500" : "700"};
                    color:var(--text-primary,#111);
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    ${_esc(n.title)}
                </div>
                ${
                    n.body
                        ? `
                <div style="font-size:11px;color:var(--text-secondary,#666);
                    margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    ${_esc(n.body)}
                </div>`
                        : ""
                }
                <div style="font-size:10px;color:var(--text-light,#aaa);margin-top:5px;">
                    ${ago}
                </div>
            </div>
            ${
                !n.read
                    ? `
            <div style="width:8px;height:8px;border-radius:50%;
                background:var(--primary,#1a5f7a);flex-shrink:0;margin-top:5px;">
            </div>`
                    : ""
            }
        </div>`;
        })
        .join("");
}

// ================================================================
// CLICK-THROUGH URLS
// ================================================================
function _notifClickUrl(n) {
    switch (n.source_type) {
        case "event":
        case "task":
            // Deep-link to the relevant page
            return n.source_type === "task" ? "/tasks" : "/calendar";
        case "message":
            return "/messages";
        case "friend_request":
            if (n.trigger === "friend_request_received")
                return "/friend-requests?tab=requests";
            if (n.trigger === "friend_request_accepted") return "/messages";
            return "/friend-requests";
        case "study_group":
            return n.source_id
                ? `/study-groups#${n.source_id}`
                : "/study-groups";
        case "post":
            return n.source_id ? `/newsfeed#post-${n.source_id}` : "/newsfeed";
        case "follow":
            return `/profile/${n.source_id}`;
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
        return;
    }
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

// ================================================================
// WIRE BELL
// ================================================================
function _wireBell() {
    const bellLink =
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
    _dropdown.style.top = `${r.bottom + 8}px`;
    _dropdown.style.left = `${left}px`;
    _dropdown.style.display = "flex";
    _renderList();
}

// ================================================================
// CONTENT BUILDERS
// ================================================================
function _buildTitle(item, trig) {
    if (trig.key === "overdue") return `⚠️ Overdue: ${item.title}`;
    if (trig.key === "1hr_after") return `🕐 1 hour ago: ${item.title}`;

    const prefix = item.source_type === "task" ? "✅" : _evIcon(item.category);
    return `${prefix} ${trig.label}: ${item.title}`;
}

function _buildBody(item, dueDate) {
    const parts = [];

    // FR-3.3 / FR-4.5 — subject name if available
    if (item.subject_name) {
        parts.push(`📚 ${item.subject_name}`);
    }

    // Category or priority context
    if (item.source_type === "event" && item.category) {
        parts.push(_catLabel(item.category));
    } else if (item.source_type === "task" && item.priority) {
        parts.push(
            `${PRI_ICON[item.priority] || "✅"} ${item.priority} priority`,
        );
    }

    // Due date/time
    const isEOD = dueDate.getHours() === 23 && dueDate.getMinutes() === 59;
    const timeStr = isEOD
        ? "all day"
        : _fmt12h(
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
    if (item.source_type === "task") return PRI_ICON[item.priority] || "✅";
    return _evIcon(item.category);
}

// ================================================================
// UTILS  (local — not duplicating studyhub-core exports)
// ================================================================

// FR-3.4 exam/deadline added to icon and label maps
function _evIcon(cat) {
    // CC / CI constants are defined in studyhub-core.js
    return (
        (typeof CI !== "undefined" && CI[cat]) ||
        { class: "📗", group: "👥", event: "📅", exam: "📝", deadline: "📌" }[
            cat
        ] ||
        "📅"
    );
}

function _catLabel(cat) {
    return (
        (typeof CL !== "undefined" && CL[cat]) ||
        {
            class: "Class",
            group: "Study Group",
            event: "Event",
            exam: "Exam",
            deadline: "Deadline",
        }[cat] ||
        cat
    );
}

// Local 12-hour formatter (avoids relying on core's fmt12 name which may differ)
function _fmt12h(t) {
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
