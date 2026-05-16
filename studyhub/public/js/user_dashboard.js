// dashboard.js
// ═══════════════════════════════════════════════════════════════════
// Dashboard page — requires studyhub-core.js loaded first.
// Read-only summary widgets only. No modals, no select mode.
// Clicks navigate to /calendar or /tasks.
// ═══════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async () => {
    try {
        await Promise.all([dbLoad(), taskLoad()]);
        expandAll();
    } catch (err) {
        const el = document.getElementById("upcomingList");
        if (el)
            el.innerHTML = `<div class="state-box err">⚠️ Could not load data: ${esc(err.message)}</div>`;
        return;
    }

    // ── existing widgets ──────────────────────────────────────────
    renderDeadlines();
    renderUpcoming();
    renderTaskSummary();
    renderMyCalendars();

    // ── new widgets ───────────────────────────────────────────────
    renderMetricRow(); // FR-2.2 / FR-2.5 — 4 stat cards at top
    renderTodaySchedule(); // FR-2.1 — today's study sessions
    renderUpcomingTasks(); // FR-2.2 — task list with priority + tags
    renderStudyGroups(); // FR-2.3 — active study groups
    renderWeeklySummary(); // FR-2.5 — progress bars
    renderMiniCal(); // mini calendar grid

    initNotifications?.();
});

// ═══════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════

/** Today midnight (local). */
function todayMidnight() {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
}

/** Days from today (negative = past). */
function daysFromToday(dateStr) {
    const d = new Date(dateStr + "T00:00:00");
    return Math.ceil((d - todayMidnight()) / 86400000);
}

/** Human-readable due label. */
function dueLabel(dateStr) {
    const diff = daysFromToday(dateStr);
    if (diff < 0) return "Overdue";
    if (diff === 0) return "Today";
    if (diff === 1) return "Tomorrow";
    const d = new Date(dateStr + "T00:00:00");
    return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

/** Colour set for priority badges in tasks list. */
const TASK_TAG_STYLE = {
    high: "background:#FAECE7;color:#993C1D",
    medium: "background:#FAEEDA;color:#854F0B",
    low: "background:#E1F5EE;color:#0F6E56",
};

/** Colour bar for study-session / event categories. */
const SESSION_BAR_COLOR = {
    class: "#185FA5",
    group: "#7c3aed",
    event: "#0F6E56",
    todo: "#EF9F27",
};

/** Badge text + style for a session relative to now. */
function sessionBadge(dateStr, timeStr) {
    const diff = daysFromToday(dateStr);
    if (diff < 0)
        return { label: "Past", style: "background:#f1f5f9;color:#64748b" };
    if (diff > 0)
        return { label: "Upcoming", style: "background:#E1F5EE;color:#0F6E56" };

    // same-day — check time if available
    if (timeStr) {
        const [hh, mm] = timeStr.split(":").map(Number);
        const now = new Date();
        const start = new Date();
        start.setHours(hh, mm, 0, 0);
        const end = new Date(start);
        end.setHours(hh + 1, mm, 0, 0); // rough 1-hr block
        if (now >= start && now <= end)
            return { label: "Now", style: "background:#EEEDFE;color:#534AB7" };
        if (now < start)
            return {
                label: "Today",
                style: "background:#E6F1FB;color:#185FA5",
            };
    }
    return { label: "Today", style: "background:#E6F1FB;color:#185FA5" };
}

// ═══════════════════════════════════════════════════════════════════
// FR-2.2 / FR-2.5  METRIC ROW  (4 stat cards)
// ═══════════════════════════════════════════════════════════════════
function renderMetricRow() {
    const row = document.getElementById("dashMetricRow");
    if (!row) return;

    const today = todayMidnight();

    // Tasks due today (not completed)
    const tasksDueToday = allTasks.filter(
        (t) => !t.completed_at && t.due_date && daysFromToday(t.due_date) === 0,
    ).length;
    const highPrioToday = allTasks.filter(
        (t) =>
            !t.completed_at &&
            t.due_date &&
            daysFromToday(t.due_date) === 0 &&
            t.priority === "high",
    ).length;

    // Weekly progress: tasks completed this week vs total with due this week
    const weekStart = new Date(today);
    weekStart.setDate(today.getDate() - today.getDay());
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);
    const weekTasks = allTasks.filter(
        (t) =>
            t.due_date &&
            new Date(t.due_date + "T00:00:00") >= weekStart &&
            new Date(t.due_date + "T00:00:00") <= weekEnd,
    );
    const weekDone = weekTasks.filter((t) => t.completed_at).length;
    const weekPct = weekTasks.length
        ? Math.round((weekDone / weekTasks.length) * 100)
        : 0;

    // Study time today — sum focus_sessions where session_date = today (if your schema has it)
    // Falls back to "–" if the data isn't available yet
    let studyTimeStr = "–";
    let studyGoalStr = "";
    if (typeof allFocusSessions !== "undefined") {
        const todayStr = today.toISOString().split("T")[0];
        const minsToday = allFocusSessions
            .filter((s) => s.session_date === todayStr)
            .reduce((sum, s) => sum + (s.duration_minutes || 0), 0);
        if (minsToday > 0) {
            const h = Math.floor(minsToday / 60);
            const m = minsToday % 60;
            studyTimeStr =
                h > 0 ? `${h}h ${m > 0 ? m + "m" : ""}`.trim() : `${m}m`;
        } else {
            studyTimeStr = "0h";
        }
        studyGoalStr = "Goal: 4h"; // adjust or pull from user prefs
    }

    // Active study groups — events of category "group" coming up
    const activeGroups = [
        ...new Set(
            expanded
                .filter(
                    (e) =>
                        e.category === "group" && daysFromToday(e.idate) >= 0,
                )
                .map((e) => e.group_id || e.title), // deduplicate by group_id if available
        ),
    ].length;
    const nextGroup = expanded
        .filter((e) => e.category === "group" && daysFromToday(e.idate) >= 0)
        .sort((a, b) => (a.idate > b.idate ? 1 : -1))[0];
    const nextGroupLabel = nextGroup
        ? daysFromToday(nextGroup.idate) === 0
            ? "Session today"
            : `Next: ${dueLabel(nextGroup.idate)}`
        : "No upcoming sessions";

    const cards = row.querySelectorAll(".dash-metric-card");

    function setCard(card, val, label, sub, color) {
        if (!card) return;
        card.querySelector(".dash-metric-val").textContent = val;
        card.querySelector(".dash-metric-val").style.color = color;
        card.querySelector(".dash-metric-label").textContent = label;
        card.querySelector(".dash-metric-sub").textContent = sub;
    }

    setCard(
        cards[0],
        tasksDueToday,
        "Tasks due today",
        highPrioToday
            ? `${highPrioToday} high priority`
            : "No high-priority tasks",
        "var(--accent,#ff6b6b)",
    );
    setCard(
        cards[1],
        weekPct + "%",
        "Weekly progress",
        weekTasks.length
            ? `${weekDone} of ${weekTasks.length} tasks done`
            : "No tasks this week",
        "var(--primary,#1a5f7a)",
    );
    setCard(
        cards[2],
        studyTimeStr,
        "Study time today",
        studyGoalStr || "Track with focus mode",
        "var(--secondary,#2a9d8f)",
    );
    setCard(
        cards[3],
        activeGroups || "–",
        "Active study groups",
        nextGroupLabel,
        "var(--primary,#1a5f7a)",
    );
}

// ═══════════════════════════════════════════════════════════════════
// FR-2.1  TODAY'S SCHEDULE
// ═══════════════════════════════════════════════════════════════════
function renderTodaySchedule() {
    const el = document.getElementById("todayScheduleList");
    if (!el) return;

    const todayStr = todayMidnight().toISOString().split("T")[0];

    // Gather today's calendar events (all non-todo categories)
    const sessions = expanded
        .filter((e) => e.idate === todayStr && e.category !== "todo")
        .sort((a, b) =>
            (a.event_time || "00:00") > (b.event_time || "00:00") ? 1 : -1,
        );

    if (!sessions.length) {
        el.innerHTML = `<div style="text-align:center;padding:28px 0;color:var(--text-light);font-size:13px;">
            No sessions scheduled for today.<br>
            <a href="/calendar" style="color:var(--primary,#1a5f7a);font-weight:600;text-decoration:none;margin-top:6px;display:inline-block;">
                + Add to calendar
            </a>
        </div>`;
        return;
    }

    el.innerHTML = sessions
        .map((ev) => {
            const barColor = SESSION_BAR_COLOR[ev.category] || "#888";
            const badge = sessionBadge(ev.idate, ev.event_time);
            const timeStr = ev.event_time ? fmt12(ev.event_time) : "";
            const endStr = ev.event_end_time
                ? ` – ${fmt12(ev.event_end_time)}`
                : "";
            const subject = ev.subject || ev.label || "";

            return `<a href="/calendar" class="dash-session-item" style="text-decoration:none;">
            <div class="dash-session-bar" style="background:${barColor};"></div>
            <div class="dash-session-info">
                <div class="dash-session-title">${esc(ev.title)}</div>
                <div class="dash-session-time">
                    ${timeStr}${endStr}${subject ? ` &nbsp;·&nbsp; <span style="color:var(--text-light)">${esc(subject)}</span>` : ""}
                </div>
            </div>
            <span class="dash-session-badge" style="${badge.style}">${badge.label}</span>
        </a>`;
        })
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// FR-2.2  UPCOMING TASKS  (priority dots + subject tags)
// ═══════════════════════════════════════════════════════════════════
function renderUpcomingTasks() {
    const el = document.getElementById("upcomingTasksList");
    if (!el) return;

    const today = todayMidnight();
    const cutoff = new Date(today);
    cutoff.setDate(today.getDate() + 7);

    // Show: overdue + due within 7 days, not yet completed
    const tasks = allTasks
        .filter((t) => !t.completed_at && t.due_date)
        .filter((t) => new Date(t.due_date + "T00:00:00") <= cutoff)
        .sort((a, b) => {
            // overdue first, then by date, then by priority
            const da = new Date(a.due_date + "T00:00:00");
            const db = new Date(b.due_date + "T00:00:00");
            if (da - db !== 0) return da - db;
            const pOrder = { high: 0, medium: 1, low: 2 };
            return (pOrder[a.priority] ?? 2) - (pOrder[b.priority] ?? 2);
        })
        .slice(0, 6);

    if (!tasks.length) {
        el.innerHTML = `<div style="text-align:center;padding:28px 0;color:var(--text-light);font-size:13px;">
            All caught up — no tasks due this week 🎉
        </div>`;
        return;
    }

    const priDotClass = {
        high: "dash-priority-high",
        medium: "dash-priority-med",
        low: "dash-priority-low",
    };

    el.innerHTML = tasks
        .map((t) => {
            const pri = t.priority || "low";
            const diff = daysFromToday(t.due_date);
            const label = dueLabel(t.due_date);
            const isOverdue = diff < 0;
            const dueStyle = isOverdue
                ? "color:#dc2626;font-weight:600;"
                : diff === 0
                  ? "color:var(--accent,#ff6b6b);font-weight:600;"
                  : "";

            // Subject / label tag — use t.label, t.subject, or t.category_label if available
            const subjectTag = t.label || t.subject || "";
            const tagStyle = TASK_TAG_STYLE[pri] || TASK_TAG_STYLE.low;

            return `<a href="/tasks" class="dash-task-item" style="text-decoration:none;">
            <div class="dash-task-check${t.completed_at ? " done" : ""}"></div>
            <div class="dash-priority-dot ${priDotClass[pri] || "dash-priority-low"}"></div>
            <span class="dash-task-name${t.completed_at ? " done" : ""}">${esc(t.title)}</span>
            ${subjectTag ? `<span class="dash-task-tag" style="${tagStyle}">${esc(subjectTag)}</span>` : ""}
            <span class="dash-task-due" style="${dueStyle}">${label}</span>
        </a>`;
        })
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// FR-2.3  ACTIVE STUDY GROUPS
// ═══════════════════════════════════════════════════════════════════
async function renderStudyGroups() {
    const el = document.getElementById("activeStudyGroups");
    if (!el) return;

    el.innerHTML = `<div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">Loading…</div>`;

    let groups = [];
    try {
        const res = await fetch("/study-groups/api/groups");
        if (!res.ok) throw new Error("Network error");
        const data = await res.json();
        groups = data.groups ?? [];
    } catch (e) {
        el.innerHTML = `<div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
            Could not load study groups.</div>`;
        return;
    }

    if (!groups.length) {
        el.innerHTML = `<div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
            No active study groups.<br>
            <a href="/study-groups" style="color:var(--primary,#1a5f7a);font-weight:600;
               text-decoration:none;margin-top:6px;display:inline-block;">
                Find or create a group
            </a>
        </div>`;
        return;
    }

    const avatarPalette = [
        { bg: "#EEEDFE", fg: "#534AB7" },
        { bg: "#E6F1FB", fg: "#185FA5" },
        { bg: "#E1F5EE", fg: "#0F6E56" },
        { bg: "#FAEEDA", fg: "#854F0B" },
        { bg: "#FAECE7", fg: "#993C1D" },
    ];

    el.innerHTML = groups
        .map((g, i) => {
            const palette = avatarPalette[i % avatarPalette.length];
            const initial = (g.name || "G").charAt(0).toUpperCase();
            const subject = g.subject
                ? `<span style="opacity:.7;">${esc(g.subject)}</span>`
                : "";
            const memberLabel = `${g.members_count ?? 0} member${g.members_count !== 1 ? "s" : ""}`;

            return `<a href="/study-groups/${g.id}" class="dash-group-item" style="text-decoration:none;">
            <div class="dash-group-avatar" style="background:${palette.bg};color:${palette.fg};">
                ${initial}
            </div>
            <div class="dash-group-info">
                <div class="dash-group-name">${esc(g.name)}</div>
                <div class="dash-group-meta">${memberLabel}${g.subject ? " &nbsp;·&nbsp; " + esc(g.subject) : ""}</div>
            </div>
            ${g.is_admin ? `<span class="dash-group-badge" style="background:#e6f1fb;color:#185fa5;">Admin</span>` : ""}
        </a>`;
        })
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// FR-2.5  WEEKLY SUMMARY  (progress bars)
// ═══════════════════════════════════════════════════════════════════
function renderWeeklySummary() {
    // All three IDs are in the blade already; just populate them.
    const taskValEl = document.getElementById("wsSummTaskVal");
    const taskBarEl = document.getElementById("wsSummTaskBar");
    const hoursValEl = document.getElementById("wsSummHoursVal");
    const hoursBarEl = document.getElementById("wsSummHoursBar");
    const focusValEl = document.getElementById("wsSummFocusVal");
    const focusBarEl = document.getElementById("wsSummFocusBar");

    // ── Tasks ────────────────────────────────────────────────────
    const today = todayMidnight();
    const weekStart = new Date(today);
    weekStart.setDate(today.getDate() - today.getDay());
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);

    const weekTasks = allTasks.filter((t) => {
        if (!t.due_date) return false;
        const d = new Date(t.due_date + "T00:00:00");
        return d >= weekStart && d <= weekEnd;
    });
    const weekDone = weekTasks.filter((t) => t.completed_at).length;
    const taskPct = weekTasks.length
        ? Math.round((weekDone / weekTasks.length) * 100)
        : 0;

    if (taskValEl) taskValEl.textContent = `${weekDone} / ${weekTasks.length}`;
    if (taskBarEl) taskBarEl.style.width = `${taskPct}%`;

    // ── Study hours ──────────────────────────────────────────────
    // Uses allFocusSessions if available (from studyhub-core.js)
    let weekMins = 0;
    const GOAL_HRS = 20; // adjust or pull from user prefs
    if (typeof allFocusSessions !== "undefined") {
        const wsStr = weekStart.toISOString().split("T")[0];
        const weStr = weekEnd.toISOString().split("T")[0];
        weekMins = allFocusSessions
            .filter((s) => s.session_date >= wsStr && s.session_date <= weStr)
            .reduce((sum, s) => sum + (s.duration_minutes || 0), 0);
    }
    const weekHrs = (weekMins / 60).toFixed(1);
    const hoursPct = Math.min(
        Math.round((weekMins / 60 / GOAL_HRS) * 100),
        100,
    );

    if (hoursValEl) hoursValEl.textContent = `${weekHrs} / ${GOAL_HRS}h`;
    if (hoursBarEl) hoursBarEl.style.width = `${hoursPct}%`;

    // ── Focus sessions count ─────────────────────────────────────
    const FOCUS_GOAL = 8;
    let focusCount = 0;
    if (typeof allFocusSessions !== "undefined") {
        const wsStr = weekStart.toISOString().split("T")[0];
        const weStr = weekEnd.toISOString().split("T")[0];
        focusCount = allFocusSessions.filter(
            (s) => s.session_date >= wsStr && s.session_date <= weStr,
        ).length;
    }
    const focusPct = Math.min(Math.round((focusCount / FOCUS_GOAL) * 100), 100);

    if (focusValEl) focusValEl.textContent = `${focusCount} / ${FOCUS_GOAL}`;
    if (focusBarEl) focusBarEl.style.width = `${focusPct}%`;
}

// ═══════════════════════════════════════════════════════════════════
// DEADLINES WIDGET
// ═══════════════════════════════════════════════════════════════════
function renderDeadlines() {
    const el = document.getElementById("deadlinesList");
    if (!el) return;

    const today = todayMidnight();

    const items = [
        ...allEvents
            .filter((e) => e.category === "todo")
            .sort((a, b) => (a.event_date > b.event_date ? 1 : -1))
            .slice(0, 4)
            .map((e) => ({
                title: e.title,
                date: e.event_date,
                time: e.event_time,
                isTask: false,
                id: e.id,
            })),
        ...allTasks
            .filter((t) => t.due_date && !t.completed_at)
            .sort((a, b) => (a.due_date > b.due_date ? 1 : -1))
            .slice(0, 4)
            .map((t) => ({
                title: t.title,
                date: t.due_date,
                time: t.due_time,
                isTask: true,
                priority: t.priority,
                id: t.id,
            })),
    ]
        .sort((a, b) => (a.date > b.date ? 1 : -1))
        .slice(0, 7);

    if (!items.length) {
        el.innerHTML = '<div class="state-box">No deadlines 🎉</div>';
        return;
    }

    el.innerHTML = items
        .map((e) => {
            const due = new Date(e.date + "T00:00:00");
            const diff = Math.ceil((due - today) / 86400000);
            let cls = "due-normal",
                lbl = `${diff}d left`;
            if (diff <= 0) {
                cls = "due-urgent";
                lbl = "Overdue!";
            } else if (diff === 1) {
                cls = "due-urgent";
                lbl = "Tomorrow";
            } else if (diff <= 3) {
                cls = "due-soon";
            }
            const icon = e.isTask ? PRI_ICON[e.priority || "low"] : "📌";
            const href = e.isTask ? "/tasks" : "/calendar";
            return `<a href="${href}" class="deadline-item" style="text-decoration:none;">
            <div class="deadline-icon">${icon}</div>
            <div class="deadline-info">
                <div class="deadline-title">${esc(e.title)}</div>
                <div class="deadline-subject">${due.toLocaleDateString("en-US", { month: "short", day: "numeric" })}${e.time ? " · " + fmt12(e.time) : ""}</div>
            </div>
            <div class="deadline-due ${cls}">${lbl}</div>
        </a>`;
        })
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// UPCOMING WIDGET  (next 7 days, events + tasks)
// ═══════════════════════════════════════════════════════════════════
function renderUpcoming() {
    const el = document.getElementById("upcomingList");
    if (!el) return;

    const today = todayMidnight();
    const wEnd = new Date(today);
    wEnd.setDate(today.getDate() + 7);

    const evList = expanded
        .filter((e) => filters[e.category])
        .filter((e) => {
            const d = new Date(e.idate + "T00:00:00");
            return d >= today && d <= wEnd;
        })
        .sort((a, b) =>
            a.idate + (a.event_time || "") > b.idate + (b.event_time || "")
                ? 1
                : -1,
        );

    const taskList = allTasks
        .filter((t) => !t.completed_at && t.due_date)
        .filter((t) => {
            const d = new Date(t.due_date + "T00:00:00");
            return d >= today && d <= wEnd;
        })
        .sort((a, b) =>
            a.due_date + (a.due_time || "") > b.due_date + (b.due_time || "")
                ? 1
                : -1,
        );

    if (!evList.length && !taskList.length) {
        el.innerHTML =
            '<div class="state-box">Nothing coming up this week 🎉</div>';
        return;
    }

    let html = "";
    evList.forEach((ev) => {
        const dl = new Date(ev.idate + "T00:00:00").toLocaleDateString(
            "en-US",
            {
                weekday: "short",
                month: "short",
                day: "numeric",
            },
        );
        html += `<a href="/calendar" class="upcoming-item" style="text-decoration:none;">
            <div class="upcoming-dot" style="background:${CC[ev.category]}"></div>
            <div class="upcoming-info">
                <div class="upcoming-title">${esc(ev.title)}</div>
                <div class="upcoming-sub">${dl}${ev.event_time ? " · " + fmt12(ev.event_time) : ""}</div>
            </div>
            <span class="upcoming-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">${CI[ev.category]} ${CL[ev.category]}</span>
        </a>`;
    });
    taskList.forEach((t) => {
        const dl = new Date(t.due_date + "T00:00:00").toLocaleDateString(
            "en-US",
            {
                weekday: "short",
                month: "short",
                day: "numeric",
            },
        );
        html += `<a href="/tasks" class="upcoming-item" style="text-decoration:none;">
            <div class="upcoming-dot" style="background:${PRI_COLOR[t.priority || "low"]}"></div>
            <div class="upcoming-info">
                <div class="upcoming-title">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                <div class="upcoming-sub">${dl}${t.due_time ? " · " + fmt12(t.due_time) : ""}${t.label ? " · " + esc(t.label) : ""}</div>
            </div>
            <span class="upcoming-tag" style="background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]}">✅ Task</span>
        </a>`;
    });
    el.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════════
// TASK SUMMARY WIDGET  (progress bar + active task count)
// ═══════════════════════════════════════════════════════════════════
function renderTaskSummary() {
    const total = allTasks.length;
    const completed = allTasks.filter((t) => t.completed_at).length;
    const active = total - completed;
    const overdue = allTasks.filter((t) => {
        if (t.completed_at || !t.due_date) return false;
        return new Date(t.due_date + "T00:00:00") < todayMidnight();
    }).length;

    const badge = document.getElementById("taskCountBadge");
    if (badge) badge.textContent = active;

    const progEl = document.getElementById("taskProgress");
    if (progEl) {
        if (total > 0) {
            progEl.style.display = "block";
            const lbl = document.getElementById("taskProgressLabel");
            const bar = document.getElementById("taskProgressBar");
            if (lbl) lbl.textContent = `${completed} / ${total} done`;
            if (bar)
                bar.style.width = `${Math.round((completed / total) * 100)}%`;
        } else {
            progEl.style.display = "none";
        }
    }

    const statsEl = document.getElementById("taskSummaryStats");
    if (statsEl) {
        statsEl.innerHTML = `
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:80px;background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:12px 14px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:var(--text-primary);">${active}</div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">Active</div>
                </div>
                <div style="flex:1;min-width:80px;background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:12px 14px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#0f766e;">${completed}</div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">Done</div>
                </div>
                ${
                    overdue
                        ? `
                <div style="flex:1;min-width:80px;background:rgba(220,38,38,.07);border:1px solid rgba(220,38,38,.2);border-radius:10px;padding:12px 14px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#dc2626;">${overdue}</div>
                    <div style="font-size:11px;color:#dc2626;margin-top:2px;">Overdue</div>
                </div>`
                        : ""
                }
            </div>
            <a href="/tasks" style="display:block;margin-top:10px;text-align:center;font-size:12px;color:var(--primary,#1a5f7a);font-weight:600;text-decoration:none;padding:6px;border-radius:8px;border:1px solid var(--border);">
                View all tasks →
            </a>`;
    }
}

// ═══════════════════════════════════════════════════════════════════
// MY CALENDARS WIDGET
// ═══════════════════════════════════════════════════════════════════
function renderMyCalendars() {
    const el = document.getElementById("myCalendars");
    if (!el) return;

    const today = todayMidnight();
    const wEnd = new Date(today);
    wEnd.setDate(today.getDate() + 7);

    const cntW = (cat) =>
        expanded.filter(
            (e) =>
                e.category === cat &&
                new Date(e.idate + "T00:00:00") >= today &&
                new Date(e.idate + "T00:00:00") <= wEnd,
        ).length;

    el.innerHTML = [
        {
            key: "class",
            label: "Class Schedule",
            color: "#0f766e",
            meta: "Your enrolled classes",
        },
        {
            key: "group",
            label: "Study Groups",
            color: "#7c3aed",
            meta: `${cntW("group")} events this week`,
        },
        {
            key: "event",
            label: "Events",
            color: "#1a5f7a",
            meta: "School & personal events",
        },
    ]
        .map(
            (c) =>
                `<div class="cal-category ${filters[c.key] ? "active" : ""}" style="color:${c.color};cursor:pointer;" onclick="toggleFilter('${c.key}')">
            <div class="cal-category-dot" style="background:${c.color}"></div>
            <div class="cal-category-info">
                <div class="cal-category-name">${c.label}</div>
                <div class="cal-category-meta">${c.meta}</div>
            </div>
            <div class="cal-category-toggle"></div>
        </div>`,
        )
        .join("");
}

function toggleFilter(key) {
    filters[key] = !filters[key];
    renderMyCalendars();
    renderUpcoming();
}

// ═══════════════════════════════════════════════════════════════════
// MINI CALENDAR  (dashboard centre column)
// ═══════════════════════════════════════════════════════════════════
function renderMiniCal() {
    const grid = document.getElementById("dashMiniCalGrid");
    if (!grid) return;

    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth(); // 0-based

    // First day of the month and how many days it has
    const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Build a set of dates that have events this month for dot markers
    const monthStr = `${year}-${String(month + 1).padStart(2, "0")}`;
    const eventDates = new Set(
        expanded
            .filter((e) => e.idate && e.idate.startsWith(monthStr))
            .map((e) => parseInt(e.idate.split("-")[2], 10)),
    );

    let html = "";

    // Leading blank cells
    for (let i = 0; i < firstDay; i++) {
        html += `<div class="mini-cal-cell other-month"></div>`;
    }

    // Day cells
    for (let d = 1; d <= daysInMonth; d++) {
        const isToday = d === today.getDate();
        const hasEvent = eventDates.has(d);
        const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;

        html += `<a href="/calendar" class="mini-cal-cell${isToday ? " today" : ""}" style="text-decoration:none;" title="${dateStr}">
            ${d}
            ${hasEvent ? `<span class="event-dot"></span>` : ""}
        </a>`;
    }

    grid.innerHTML = html;
}
