// ═══════════════════════════════════════════════════════════════════
// CONFIG  — SB_URL, SB_ANON, SB_SVC, UID injected from blade
// ═══════════════════════════════════════════════════════════════════
const TABLE = "calendar_events";
const TASK_TABLE = "tasks";

// ═══════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════
let curDate = new Date();
let allEvents = [];
let expanded = [];
let filters = { class: true, group: true, event: true };
let selectMode = false;
let selPopoverEl = null;
let selIds = new Set();
let selTaskIds = new Set(); // ← NEW: tracks selected task IDs
let editId = null;
let pendDel = null;
let curView = "month"; // 'month' | 'week' | 'day'

// Task state
let allTasks = [];
let editTaskId = null;
let taskFilterPri = "all";
let taskFilterStatus = "active";
let taskSort = "due";
let taskLabelFilter = null;

// ═══════════════════════════════════════════════════════════════════
// SUPABASE HELPERS
// ═══════════════════════════════════════════════════════════════════
const hdrs = (write = false) => ({
    apikey: write ? SB_SVC : SB_ANON,
    Authorization: `Bearer ${write ? SB_SVC : SB_ANON}`,
    "Content-Type": "application/json",
    "Accept-Profile": "public",
    "Content-Profile": "public",
});

async function sbReq(path, opts = {}) {
    const r = await fetch(`${SB_URL}/rest/v1/${path}`, opts);
    if (!r.ok) {
        const e = await r.json().catch(() => ({}));
        throw new Error(e.message || e.error || `HTTP ${r.status}`);
    }
    return r.status === 204 ? null : r.json();
}

async function dbLoad() {
    allEvents = await sbReq(
        `${TABLE}?user_id=eq.${UID}&order=event_date.asc,event_time.asc`,
        { headers: hdrs() },
    );
}

async function dbInsert(data) {
    const [row] = await sbReq(TABLE, {
        method: "POST",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify({ ...data, user_id: UID }),
    });
    return row;
}

async function dbUpdate(id, data) {
    const [row] = await sbReq(`${TABLE}?id=eq.${id}`, {
        method: "PATCH",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify(data),
    });
    return row;
}

async function dbDelete(ids) {
    const list = ids.map((i) => `"${i}"`).join(",");
    await sbReq(`${TABLE}?id=in.(${list})`, {
        method: "DELETE",
        headers: hdrs(true),
    });
}

// ── Task DB helpers ──────────────────────────────────────────────
async function taskLoad() {
    allTasks = await sbReq(
        `${TASK_TABLE}?user_id=eq.${UID}&order=created_at.desc`,
        { headers: hdrs() },
    );
    if (!Array.isArray(allTasks)) allTasks = [];
}

async function taskInsert(data) {
    const [row] = await sbReq(TASK_TABLE, {
        method: "POST",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify({ ...data, user_id: UID }),
    });
    return row;
}

async function taskUpdate(id, data) {
    const [row] = await sbReq(`${TASK_TABLE}?id=eq.${id}`, {
        method: "PATCH",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify(data),
    });
    return row;
}

async function taskDelete(id) {
    await sbReq(`${TASK_TABLE}?id=eq.${id}`, {
        method: "DELETE",
        headers: hdrs(true),
    });
}

// ═══════════════════════════════════════════════════════════════════
// RECURRING EXPANSION
// ═══════════════════════════════════════════════════════════════════
const DN = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function expandAll() {
    const vs = new Date(curDate.getFullYear(), curDate.getMonth() - 1, 1);
    const ve = new Date(curDate.getFullYear(), curDate.getMonth() + 2, 0);
    const rows = [];
    for (const ev of allEvents) {
        if (!ev.is_recurring || !ev.recur_days?.length) {
            rows.push({ ...ev, idate: ev.event_date });
            continue;
        }
        const start = new Date(ev.event_date + "T00:00:00");
        const rEnd = ev.recur_end ? new Date(ev.recur_end + "T00:00:00") : ve;
        const walkEnd = rEnd < ve ? rEnd : ve;
        let c = start < vs ? new Date(vs) : new Date(start);
        while (c <= walkEnd) {
            if (ev.recur_days.includes(DN[c.getDay()]))
                rows.push({ ...ev, idate: fd(c) });
            c.setDate(c.getDate() + 1);
        }
    }
    expanded = rows;
}

// ═══════════════════════════════════════════════════════════════════
// COLOUR CONSTANTS
// ═══════════════════════════════════════════════════════════════════
const CC = {
    class: "#0f766e",
    group: "#7c3aed",
    event: "#1a5f7a",
};
const CB = {
    class: "rgba(42,157,143,.13)",
    group: "rgba(124,77,202,.13)",
    event: "rgba(26,95,122,.11)",
};
const CI = { class: "📗", group: "👥", event: "📅" };
const CL = {
    class: "Class",
    group: "Study Group",
    event: "Event",
};

const PRI_COLOR = { high: "#dc2626", medium: "#d97706", low: "#16a34a" };
const PRI_BG = {
    high: "rgba(220,38,38,.1)",
    medium: "rgba(217,119,6,.1)",
    low: "rgba(22,163,74,.1)",
};
const PRI_ICON = { high: "🔴", medium: "🟡", low: "🟢" };

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async () => {
    wireStaticUI();
    setTimeout(wireInjectedButtons, 0);

    try {
        await Promise.all([dbLoad(), taskLoad()]);
        expandAll();
    } catch (err) {
        const el = document.getElementById("calDays");
        if (el)
            el.innerHTML = `<div class="state-box err" style="grid-column:1/-1">⚠️ ${esc(err.message)}</div>`;
        const tl = document.getElementById("taskList");
        if (tl)
            tl.innerHTML = `<div class="state-box err">⚠️ Could not load tasks: ${esc(err.message)}</div>`;
        return;
    }
    redraw();
    renderTaskManager();
    initNotifications();
});

// ═══════════════════════════════════════════════════════════════════
// WIRE UI
// ═══════════════════════════════════════════════════════════════════
function wireStaticUI() {
    document.getElementById("btnPrev").onclick = () => navigate(-1);
    document.getElementById("btnNext").onclick = () => navigate(+1);
    document.getElementById("btnToday").onclick = () => {
        curDate = new Date();
        expandAll();
        redraw();
    };

    document.querySelectorAll(".view-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
            document
                .querySelectorAll(".view-btn")
                .forEach((b) => b.classList.remove("active"));
            btn.classList.add("active");
            curView = btn.dataset.view;
            redraw();
        });
    });

    document.getElementById("btnPopClose").addEventListener("click", (e) => {
        e.stopPropagation();
        closePopover();
    });
    document.getElementById("btnPopAdd").onclick = () => {
        const d = document.getElementById("btnPopAdd").dataset.date;
        closePopover();
        openEvModal(null, d);
    };

    document.getElementById("btnModalClose").onclick = closeEvModal;
    document.getElementById("btnModalCancel").onclick = closeEvModal;
    document.getElementById("btnSaveEv").onclick = saveEv;
    document.getElementById("btnDelEv").onclick = () =>
        promptSingleDelete(editId);
    document.getElementById("evRecur").onchange = (e) => {
        document.getElementById("recurOpts").style.display = e.target.checked
            ? "block"
            : "none";
    };
    document
        .querySelectorAll(".rday")
        .forEach((b) => (b.onclick = () => b.classList.toggle("sel")));

    document.getElementById("btnTaskModalClose").onclick = closeTaskModal;
    document.getElementById("btnTaskModalCancel").onclick = closeTaskModal;
    document.getElementById("btnSaveTask").onclick = saveTask;
    document.getElementById("btnDelTask").onclick = () =>
        promptDeleteTask(editTaskId);

    document.querySelectorAll(".priority-option").forEach((lbl) => {
        lbl.addEventListener("click", () => {
            document
                .querySelectorAll(".priority-option")
                .forEach((l) => l.classList.remove("sel"));
            lbl.classList.add("sel");
            lbl.querySelector("input").checked = true;
        });
    });

    document.getElementById("taskSort").onchange = (e) => {
        taskSort = e.target.value;
        renderTaskManager();
    };
    document.getElementById("taskFilterPriority").onchange = (e) => {
        taskFilterPri = e.target.value;
        renderTaskManager();
    };
    document.getElementById("taskFilterStatus").onchange = (e) => {
        taskFilterStatus = e.target.value;
        renderTaskManager();
    };

    document.getElementById("btnConfirmClose").onclick = closeConfirm;
    document.getElementById("btnConfirmCancel").onclick = closeConfirm;
    document.getElementById("btnConfirmDel").onclick = execDelete;

    document.addEventListener("click", (e) => {
        if (!document.getElementById("dayPopover").classList.contains("open"))
            return;
        if (!e.target.closest(".cal-day") && !e.target.closest("#dayPopover"))
            closePopover();
    });

    document
        .getElementById("allEventsWidget")
        .addEventListener("input", (e) => {
            if (e.target.id === "allEvSearch") renderAllEvents();
        });
}

function wireInjectedButtons() {
    const btnAdd = document.getElementById("btnAdd");
    const btnAddTask = document.getElementById("btnAddTask");
    const btnSelectMode = document.getElementById("btnSelectMode");
    const btnBulkCancel = document.getElementById("btnBulkCancel");
    const btnBulkDelete = document.getElementById("btnBulkDelete");

    if (btnAdd) btnAdd.onclick = () => openEvModal();
    if (btnAddTask) btnAddTask.onclick = () => openTaskModal();
    if (btnSelectMode) btnSelectMode.onclick = toggleSelectMode;
    if (btnBulkCancel) btnBulkCancel.onclick = exitSelectMode;
    if (btnBulkDelete) btnBulkDelete.onclick = promptBulkDelete;
}

function wireUI() {
    wireStaticUI();
    wireInjectedButtons();
}

// ═══════════════════════════════════════════════════════════════════
// NAVIGATE & REDRAW
// ═══════════════════════════════════════════════════════════════════
function navigate(dir) {
    if (curView === "month") {
        curDate.setMonth(curDate.getMonth() + dir);
    } else if (curView === "week") {
        curDate.setDate(curDate.getDate() + dir * 7);
    } else {
        curDate.setDate(curDate.getDate() + dir);
    }
    expandAll();
    redraw();
}

function redraw() {
    updateTitle();
    document.getElementById("monthView").style.display =
        curView === "month" ? "" : "none";
    document.getElementById("weekView").style.display =
        curView === "week" ? "block" : "none";
    document.getElementById("dayView").style.display =
        curView === "day" ? "block" : "none";

    if (curView === "month") renderCal();
    else if (curView === "week") renderWeek();
    else renderDay();

    renderDeadlines();
    renderMyCalendars();
    renderUpcoming();
    renderAllEvents();
}

function updateTitle() {
    if (curView === "month") {
        document.getElementById("calTitle").textContent = new Date(
            curDate.getFullYear(),
            curDate.getMonth(),
            1,
        ).toLocaleDateString("en-US", { month: "long", year: "numeric" });
    } else if (curView === "week") {
        const { start, end } = weekRange(curDate);
        const o = { month: "short", day: "numeric" };
        document.getElementById("calTitle").textContent =
            `${start.toLocaleDateString("en-US", o)} – ${end.toLocaleDateString("en-US", { ...o, year: "numeric" })}`;
    } else {
        document.getElementById("calTitle").textContent =
            curDate.toLocaleDateString("en-US", {
                weekday: "long",
                month: "long",
                day: "numeric",
                year: "numeric",
            });
    }
}

// ═══════════════════════════════════════════════════════════════════
// MONTH VIEW
// ═══════════════════════════════════════════════════════════════════
function renderCal() {
    const y = curDate.getFullYear(),
        m = curDate.getMonth(),
        today = new Date();
    const first = new Date(y, m, 1).getDay();
    const dim = new Date(y, m + 1, 0).getDate();
    const prev = new Date(y, m, 0).getDate();
    const total = Math.ceil((first + dim) / 7) * 7;
    const grid = document.getElementById("calDays");
    grid.innerHTML = "";

    for (let i = 0; i < total; i++) {
        let dn,
            mo = 0;
        if (i < first) {
            dn = prev - first + i + 1;
            mo = -1;
        } else if (i >= first + dim) {
            dn = i - first - dim + 1;
            mo = 1;
        } else {
            dn = i - first + 1;
        }

        const cell = new Date(y, m + mo, dn),
            ds = fd(cell);
        const isToday = sameDay(cell, today),
            isOther = mo !== 0;
        const devs = evForDate(ds);

        const div = document.createElement("div");
        div.className =
            "cal-day" +
            (isOther ? " other-month" : "") +
            (isToday ? " today" : "") +
            (isDaySel(ds) ? " day-sel" : "");
        div.dataset.date = ds;

        const vis = devs.filter((e) => filters[e.category]);

        // ── Individual event chips — selectable in select mode ──
        const chips = vis
            .slice(0, 3)
            .map(
                (e) =>
                    `<div class="day-event-chip chip-${e.category}" title="${esc(e.title)}">${esc(e.title)}</div>`,
            )
            .join("");
        const more =
            vis.length > 3
                ? `<div class="day-event-chip chip-event">+${vis.length - 3} more</div>`
                : "";

        // Task dots for this day
        const dayTasks = tasksForDate(ds).filter((t) => !t.completed_at);
        const taskDots = dayTasks.length
            ? `<div style="display:flex;gap:2px;flex-wrap:wrap;margin-top:2px;">${dayTasks
                  .slice(0, 4)
                  .map(
                      (t) =>
                          `<div style="width:6px;height:6px;border-radius:50%;background:${PRI_COLOR[t.priority || "low"]}" title="${esc(t.title)}"></div>`,
                  )
                  .join("")}</div>`
            : "";

        div.innerHTML = `<div class="day-check"></div>
            <div class="day-num">${dn}</div>
            <div class="day-events">${chips}${more}${taskDots}</div>`;

        if (!isOther) {
            div.addEventListener("click", (e) => {
                if (selectMode) {
                    e.stopPropagation();
                    openSelectPopover(div, ds, cell);
                } else {
                    e.stopPropagation();
                    openPopover(div, ds, cell);
                }
            });
            div.querySelector(".day-check").addEventListener("click", (e) => {
                e.stopPropagation();
                if (!selectMode) toggleSelectMode();
                toggleDaySel(ds, div);
            });
        }
        grid.appendChild(div);
    }
}

// Called when a chip on the month calendar is clicked
function handleChipClick(e, id) {
    e.stopPropagation();
    if (selectMode) {
        toggleEventSel(id);
    } else {
        openEvModal(id);
    }
}

// Called when a task dot on the month calendar is clicked
function handleTaskDotClick(e, id) {
    e.stopPropagation();
    if (selectMode) {
        toggleTaskSel(id);
    } else {
        openTaskModal(id);
    }
}

function isDaySel(ds) {
    // Day is "selected" if any of its events or tasks are selected
    const evSel = evForDate(ds).some((e) => selIds.has(e.id));
    const tSel = tasksForDate(ds).some((t) => selTaskIds.has(t.id));
    return evSel || tSel;
}

// ═══════════════════════════════════════════════════════════════════
// WEEK VIEW
// ═══════════════════════════════════════════════════════════════════
function weekRange(ref) {
    const d = new Date(ref),
        day = d.getDay();
    const start = new Date(d);
    start.setDate(d.getDate() - day);
    start.setHours(0, 0, 0, 0);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return { start, end };
}

function renderWeek() {
    const today = new Date(),
        now = new Date();
    const { start } = weekRange(curDate);
    const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const HOUR_H = 64,
        TOTAL_H = 24 * HOUR_H;

    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        days.push(d);
    }

    const counts = { class: 0, group: 0, event: 0 };
    days.forEach((d) =>
        evForDate(fd(d))
            .filter((e) => filters[e.category])
            .forEach((e) => counts[e.category]++),
    );
    const sumLabels = { class: "class", group: "study group", event: "event" };
    document.getElementById("weekSummaryBar").innerHTML = Object.entries(counts)
        .filter(([, n]) => n > 0)
        .map(
            ([cat, n]) =>
                `<div style="display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:13px;font-weight:500;border:1px solid var(--border);background:white;color:var(--text-secondary)">
            <div style="width:8px;height:8px;border-radius:50%;background:${CC[cat]}"></div>${n} ${sumLabels[cat]}${n !== 1 ? "s" : ""}</div>`,
        )
        .join("");

    const scrollToHour = sameDay(now, today)
        ? Math.max(0, now.getHours() - 1)
        : 7;
    const hasUntimed = days.some(
        (d) =>
            evForDate(fd(d)).filter((e) => filters[e.category] && !e.event_time)
                .length > 0,
    );

    let html = `<div style="display:flex;flex-direction:column;border:1px solid var(--border);border-radius:16px;overflow:hidden;">`;
    html += `<div style="display:grid;grid-template-columns:52px repeat(7,1fr);border-bottom:2px solid var(--border);background:var(--bg-main);position:sticky;top:0;z-index:10;"><div style="border-right:1px solid var(--border);"></div>`;
    days.forEach((d) => {
        const isTod = sameDay(d, today);
        html += `<div style="padding:10px 6px;text-align:center;border-right:1px solid var(--border);background:${isTod ? "rgba(26,95,122,0.06)" : "transparent"};">
            <div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;">${dayNames[d.getDay()]}</div>
            <div style="font-size:20px;font-weight:600;margin-top:2px;color:${isTod ? "var(--primary)" : "var(--text-primary)"};">${d.getDate()}</div></div>`;
    });
    html += `</div>`;

    const hasUntimedTasks = days.some((d) => tasksForDate(fd(d)).length > 0);
    if (hasUntimed || hasUntimedTasks) {
        html += `<div style="display:grid;grid-template-columns:52px repeat(7,1fr);border-bottom:1px solid var(--border);background:var(--bg-main);min-height:32px;"><div style="border-right:1px solid var(--border);padding:6px 4px;font-size:10px;color:var(--text-light);text-align:right;white-space:nowrap;">all day</div>`;
        days.forEach((d) => {
            const ds = fd(d);
            const untimed = evForDate(ds).filter(
                (e) => filters[e.category] && !e.event_time,
            );
            const dayT = tasksForDate(ds).filter((t) => !t.completed_at);
            html += `<div style="border-right:1px solid var(--border);padding:3px 2px;min-height:32px;" onclick="openEvModal(null,'${ds}')">`;
            untimed.forEach((ev) => {
                html += `<div style="font-size:10px;font-weight:600;padding:3px 6px;margin-bottom:2px;border-radius:4px;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:${CB[ev.category]};color:${CC[ev.category]};border-left:3px solid ${CC[ev.category]};"
                    onclick="event.stopPropagation();openEvModal('${ev.id}')" title="${esc(ev.title)}">${esc(ev.title)}</div>`;
            });
            dayT.forEach((t) => {
                html += `<div style="font-size:10px;font-weight:600;padding:3px 6px;margin-bottom:2px;border-radius:4px;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]};border-left:3px solid ${PRI_COLOR[t.priority || "low"]};"
                    onclick="event.stopPropagation();openTaskModal('${t.id}')" title="${esc(t.title)}">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>`;
            });
            html += `</div>`;
        });
        html += `</div>`;
    }

    html += `<div id="weekScrollBody" style="overflow-y:auto;max-height:580px;"><div style="display:grid;grid-template-columns:52px repeat(7,1fr);position:relative;">`;
    html += `<div style="position:relative;height:${TOTAL_H}px;border-right:1px solid var(--border);background:var(--bg-main);">`;
    for (let h = 1; h < 24; h++) {
        const label = h < 12 ? `${h} AM` : h === 12 ? "12 PM" : `${h - 12} PM`;
        html += `<div style="position:absolute;top:${h * HOUR_H}px;right:6px;font-size:10px;color:var(--text-light);transform:translateY(-50%);white-space:nowrap;">${label}</div>`;
    }
    html += `</div>`;

    days.forEach((d) => {
        const ds = fd(d);
        const isTod = sameDay(d, today);
        const allEvs = evForDate(ds).filter(
            (e) => filters[e.category] && e.event_time,
        );
        const timedTasks = allTasks.filter(
            (t) => t.due_date === ds && t.due_time && !t.completed_at,
        );

        html += `<div style="position:relative;height:${TOTAL_H}px;border-right:1px solid var(--border);background:${isTod ? "rgba(26,95,122,0.015)" : "white"};" onclick="openEvModal(null,'${ds}')">`;
        for (let h = 0; h < 24; h++) {
            html += `<div style="position:absolute;top:${h * HOUR_H}px;left:0;right:0;border-top:1px solid ${h === 0 ? "transparent" : "var(--border)"};pointer-events:none;"></div>
                     <div style="position:absolute;top:${h * HOUR_H + HOUR_H / 2}px;left:0;right:0;border-top:1px dashed rgba(0,0,0,0.05);pointer-events:none;"></div>`;
        }
        if (isTod) {
            const pct = (now.getHours() * 60 + now.getMinutes()) / 60;
            html += `<div style="position:absolute;top:${pct * HOUR_H}px;left:0;right:0;border-top:2px solid var(--accent);z-index:4;pointer-events:none;"><div style="position:absolute;left:-1px;top:-4px;width:8px;height:8px;border-radius:50%;background:var(--accent);"></div></div>`;
        }

        const sorted = [...allEvs].sort((a, b) =>
            a.event_time.localeCompare(b.event_time),
        );
        function getEventBounds(ev) {
            const [sh, sm] = ev.event_time.split(":").map(Number);
            const startMin = sh * 60 + sm;
            const topPx = (startMin / 60) * HOUR_H;
            let heightPx = HOUR_H;
            if (ev.end_time) {
                const [eh, em] = ev.end_time.split(":").map(Number);
                heightPx = Math.max(
                    28,
                    ((eh * 60 + em - startMin) / 60) * HOUR_H,
                );
            }
            return {
                topPx,
                heightPx,
                startMin,
                endMin: startMin + (heightPx / HOUR_H) * 60,
            };
        }
        const placed = [];
        sorted.forEach((ev) => {
            const bounds = getEventBounds(ev);
            const over = placed.filter(
                (p) => bounds.startMin < p.endMin && bounds.endMin > p.startMin,
            );
            const used = new Set(over.map((p) => p.col));
            let col = 0;
            while (used.has(col)) col++;
            placed.push({ ev, ...bounds, col });
        });
        placed.forEach((item) => {
            const over = placed.filter(
                (p) => item.startMin < p.endMin && item.endMin > p.startMin,
            );
            item.totalCols = Math.max(...over.map((p) => p.col)) + 1;
        });
        placed.forEach(({ ev, topPx, heightPx, col, totalCols }) => {
            const colW = `calc((100% - 2px) / ${totalCols})`;
            const colL = `calc(1px + (100% - 2px) / ${totalCols} * ${col})`;
            const minH = Math.max(28, heightPx);
            const timeLabel = fmt12(ev.event_time);
            const endLabel = ev.end_time ? ` – ${fmt12(ev.end_time)}` : "";
            html += `<div style="position:absolute;top:${topPx}px;left:${colL};width:${colW};height:${minH}px;z-index:2;box-sizing:border-box;background:${CB[ev.category]};border-left:3px solid ${CC[ev.category]};border-radius:4px;padding:4px 6px;cursor:pointer;overflow:hidden;"
                onclick="event.stopPropagation();openEvModal('${ev.id}')" title="${esc(ev.title)} · ${timeLabel}${endLabel}">
                <div style="font-size:10px;font-weight:700;color:${CC[ev.category]};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ev.title)}</div>
                ${minH >= 44 ? `<div style="font-size:9px;color:${CC[ev.category]};opacity:.75;margin-top:2px;">${timeLabel}${endLabel}</div>` : ""}</div>`;
        });

        timedTasks.forEach((t) => {
            const [sh, sm] = t.due_time.split(":").map(Number);
            const topPx = ((sh * 60 + sm) / 60) * HOUR_H;
            html += `<div style="position:absolute;top:${topPx}px;left:2px;right:2px;height:28px;z-index:3;box-sizing:border-box;background:${PRI_BG[t.priority || "low"]};border-left:3px solid ${PRI_COLOR[t.priority || "low"]};border-radius:4px;padding:3px 6px;cursor:pointer;overflow:hidden;"
                onclick="event.stopPropagation();openTaskModal('${t.id}')" title="${esc(t.title)}">
                <div style="font-size:10px;font-weight:700;color:${PRI_COLOR[t.priority || "low"]};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div></div>`;
        });

        html += `</div>`;
    });
    html += `</div></div></div>`;
    document.getElementById("weekGrid").innerHTML = html;
    const sb = document.getElementById("weekScrollBody");
    if (sb) sb.scrollTop = scrollToHour * HOUR_H;
}

// ═══════════════════════════════════════════════════════════════════
// DAY VIEW
// ═══════════════════════════════════════════════════════════════════
function renderDay() {
    const today = new Date(),
        now = new Date();
    const ds = fd(curDate);
    const HOUR_H = 64,
        TOTAL_H = 24 * HOUR_H;
    const isTod = sameDay(curDate, today);

    const dayEvs = evForDate(ds).filter((e) => filters[e.category]);
    const timedEvs = dayEvs
        .filter((e) => e.event_time)
        .sort((a, b) => a.event_time.localeCompare(b.event_time));
    const untimedEvs = dayEvs.filter((e) => !e.event_time);
    const dayTasks = allTasks.filter((t) => t.due_date === ds);
    const timedT = dayTasks.filter((t) => t.due_time && !t.completed_at);
    const untimedT = dayTasks.filter((t) => !t.due_time);

    function getBounds(time, endTime) {
        const [sh, sm] = time.split(":").map(Number);
        const startMin = sh * 60 + sm;
        let heightPx = HOUR_H;
        if (endTime) {
            const [eh, em] = endTime.split(":").map(Number);
            heightPx = Math.max(28, ((eh * 60 + em - startMin) / 60) * HOUR_H);
        }
        return {
            topPx: (startMin / 60) * HOUR_H,
            heightPx,
            startMin,
            endMin: startMin + (heightPx / HOUR_H) * 60,
        };
    }

    const placed = [];
    timedEvs.forEach((ev) => {
        const b = getBounds(ev.event_time, ev.end_time);
        const over = placed.filter(
            (p) => b.startMin < p.endMin && b.endMin > p.startMin,
        );
        const used = new Set(over.map((p) => p.col));
        let col = 0;
        while (used.has(col)) col++;
        placed.push({ ev, ...b, col, isTask: false });
    });
    timedT.forEach((t) => {
        const b = getBounds(t.due_time, null);
        const over = placed.filter(
            (p) => b.startMin < p.endMin && b.endMin > p.startMin,
        );
        const used = new Set(over.map((p) => p.col));
        let col = 0;
        while (used.has(col)) col++;
        placed.push({ task: t, ...b, col, isTask: true });
    });
    placed.forEach((item) => {
        const over = placed.filter(
            (p) => item.startMin < p.endMin && item.endMin > p.startMin,
        );
        item.totalCols = Math.max(...over.map((p) => p.col)) + 1;
    });

    let timedItemsHtml = "";
    placed.forEach(({ ev, task, topPx, heightPx, col, totalCols, isTask }) => {
        const colW = `calc((100% - 4px) / ${totalCols})`;
        const colL = `calc(2px + (100% - 4px) / ${totalCols} * ${col})`;
        const minH = Math.max(36, heightPx);
        if (isTask) {
            const t = task;
            timedItemsHtml += `<div onclick="event.stopPropagation();openTaskModal('${t.id}')"
                style="position:absolute;top:${topPx}px;left:${colL};width:${colW};height:${minH}px;z-index:3;box-sizing:border-box;
                background:${PRI_BG[t.priority || "low"]};border-left:4px solid ${PRI_COLOR[t.priority || "low"]};border-radius:6px;padding:6px 8px;cursor:pointer;overflow:hidden;">
                <div style="font-size:12px;font-weight:700;color:${PRI_COLOR[t.priority || "low"]};">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                ${minH >= 52 ? `<div style="font-size:11px;color:${PRI_COLOR[t.priority || "low"]};opacity:.7;margin-top:3px;">${fmt12(t.due_time)}</div>` : ""}</div>`;
        } else {
            timedItemsHtml += `<div onclick="event.stopPropagation();openEvModal('${ev.id}')"
                style="position:absolute;top:${topPx}px;left:${colL};width:${colW};height:${minH}px;z-index:2;box-sizing:border-box;
                background:${CB[ev.category]};border-left:4px solid ${CC[ev.category]};border-radius:6px;padding:6px 8px;cursor:pointer;overflow:hidden;">
                <div style="font-size:12px;font-weight:700;color:${CC[ev.category]};">${esc(ev.title)}</div>
                ${minH >= 52 ? `<div style="font-size:11px;color:${CC[ev.category]};opacity:.75;margin-top:3px;">${fmt12(ev.event_time)}${ev.end_time ? ` – ${fmt12(ev.end_time)}` : ""}</div>` : ""}</div>`;
        }
    });

    let alldayHtml = "";
    if (untimedEvs.length || untimedT.length) {
        alldayHtml += `<div style="background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:12px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">All day</div>`;
        untimedEvs.forEach((ev) => {
            alldayHtml += `<div onclick="openEvModal('${ev.id}')" style="padding:6px 10px;border-radius:6px;background:${CB[ev.category]};border-left:3px solid ${CC[ev.category]};color:${CC[ev.category]};font-size:13px;font-weight:600;cursor:pointer;margin-bottom:4px;">${CI[ev.category]} ${esc(ev.title)}</div>`;
        });
        untimedT.forEach((t) => {
            alldayHtml += `<div onclick="openTaskModal('${t.id}')" style="padding:6px 10px;border-radius:6px;background:${PRI_BG[t.priority || "low"]};border-left:3px solid ${PRI_COLOR[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]};font-size:13px;font-weight:600;cursor:pointer;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
                ${PRI_ICON[t.priority || "low"]} ${esc(t.title)}
                ${t.completed_at ? `<span style="font-size:11px;opacity:.6;">✓ Done</span>` : ""}
            </div>`;
        });
        alldayHtml += `</div>`;
    }

    let hourLinesHtml = "";
    for (let h = 0; h < 24; h++) {
        hourLinesHtml += `<div style="position:absolute;top:${h * HOUR_H}px;left:0;right:0;border-top:1px solid ${h === 0 ? "transparent" : "var(--border)"};pointer-events:none;"></div>
                 <div style="position:absolute;top:${h * HOUR_H + HOUR_H / 2}px;left:0;right:0;border-top:1px dashed rgba(0,0,0,0.05);pointer-events:none;"></div>`;
    }

    let nowLineHtml = "";
    if (isTod) {
        const pct = (now.getHours() * 60 + now.getMinutes()) / 60;
        nowLineHtml = `<div style="position:absolute;top:${pct * HOUR_H}px;left:0;right:0;border-top:2px solid var(--accent);z-index:4;pointer-events:none;"><div style="position:absolute;left:-1px;top:-4px;width:8px;height:8px;border-radius:50%;background:var(--accent);"></div></div>`;
    }

    let summaryHtml = `<div style="background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Summary</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px;">📅 ${dayEvs.length} event${dayEvs.length !== 1 ? "s" : ""}</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px;">✅ ${dayTasks.length} task${dayTasks.length !== 1 ? "s" : ""}</div>
        ${dayTasks.length ? `<div style="font-size:13px;color:#0f766e;">✓ ${dayTasks.filter((t) => t.completed_at).length} completed</div>` : ""}
    </div>`;

    let tasksListHtml = "";
    if (dayTasks.length) {
        tasksListHtml = `<div style="background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Tasks</div>`;
        dayTasks.forEach((t) => {
            tasksListHtml += `<div onclick="openTaskModal('${t.id}')" style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border);cursor:pointer;">
                <div onclick="event.stopPropagation();toggleTaskDone('${t.id}')" style="width:16px;height:16px;border-radius:50%;border:2px solid ${PRI_COLOR[t.priority || "low"]};flex-shrink:0;display:flex;align-items:center;justify-content:center;cursor:pointer;background:${t.completed_at ? PRI_COLOR[t.priority || "low"] : "transparent"}">
                    ${t.completed_at ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;${t.completed_at ? "text-decoration:line-through;opacity:.5;" : ""}">${esc(t.title)}</div>
                    ${t.due_time ? `<div style="font-size:11px;color:var(--text-secondary);">${fmt12(t.due_time)}</div>` : ""}
                </div>
            </div>`;
        });
        tasksListHtml += `</div>`;
    }

    const html = `
        <div style="display:flex;gap:16px;padding:4px 0;">
            <div style="flex:1;min-width:0;">
                ${alldayHtml}
                <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
                    <div class="day-view-scroll" id="dayScrollBody" style="overflow-y:auto;max-height:580px;">
                        <div style="display:flex;position:relative;">
                            <div style="width:52px;flex-shrink:0;position:relative;height:${TOTAL_H}px;background:var(--bg-main);border-right:1px solid var(--border);">
                                ${Array.from({ length: 23 }, (_, h) => {
                                    const hour = h + 1;
                                    const label =
                                        hour < 12
                                            ? `${hour} AM`
                                            : hour === 12
                                              ? "12 PM"
                                              : `${hour - 12} PM`;
                                    return `<div style="position:absolute;top:${hour * HOUR_H}px;right:6px;font-size:10px;color:var(--text-light);transform:translateY(-50%);white-space:nowrap;">${label}</div>`;
                                }).join("")}
                            </div>
                            <div style="flex:1;position:relative;height:${TOTAL_H}px;background:${isTod ? "rgba(26,95,122,0.015)" : "white"};" onclick="openEvModal(null,'${ds}')">
                                ${hourLinesHtml}
                                ${nowLineHtml}
                                ${timedItemsHtml}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="width:220px;flex-shrink:0;">
                ${summaryHtml}
                ${tasksListHtml}
            </div>
        </div>`;

    const container = document.getElementById("dayGrid");
    container.innerHTML = html;

    const scrollTarget = isTod ? Math.max(0, now.getHours() - 1) : 7;
    const scrollBody = document.getElementById("dayScrollBody");
    if (scrollBody) {
        setTimeout(() => {
            scrollBody.scrollTop = scrollTarget * HOUR_H;
        }, 50);
    }
}

// ═══════════════════════════════════════════════════════════════════
// DAY POPOVER
// ═══════════════════════════════════════════════════════════════════
function openPopover(dayEl, ds, cell) {
    closePopover();
    const pop = document.getElementById("dayPopover");
    document.getElementById("popDate").textContent = cell.toLocaleDateString(
        "en-US",
        { weekday: "long", month: "long", day: "numeric" },
    );
    document.getElementById("btnPopAdd").dataset.date = ds;

    const evs = evForDate(ds).filter((e) => filters[e.category]);
    const tasks = allTasks.filter((t) => t.due_date === ds);
    const el = document.getElementById("popEvents");

    let inner = "";
    evs.forEach((ev) => {
        inner += `<div class="popover-event" onclick="openEvModal('${ev.id}')">
            <div class="popover-event-dot" style="background:${CC[ev.category]}"></div>
            <div class="popover-event-info">
                <div class="popover-event-title">${esc(ev.title)}</div>
                ${ev.event_time ? `<div class="popover-event-time">${fmt12(ev.event_time)}</div>` : ""}
            </div>
            <button class="popover-ev-del" onclick="event.stopPropagation();promptSingleDelete('${ev.id}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button></div>`;
    });
    tasks.forEach((t) => {
        inner += `<div class="popover-event" onclick="openTaskModal('${t.id}')">
            <div class="popover-event-dot" style="background:${PRI_COLOR[t.priority || "low"]}"></div>
            <div class="popover-event-info">
                <div class="popover-event-title" style="text-decoration:${t.completed_at ? "line-through" : "none"};opacity:${t.completed_at ? ".5" : "1"}">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                ${t.due_time ? `<div class="popover-event-time">${fmt12(t.due_time)}</div>` : ""}
            </div></div>`;
    });
    el.innerHTML =
        inner || '<div class="popover-empty">No events on this day</div>';

    const r = dayEl.getBoundingClientRect();
    let l = r.left + r.width / 2 - 142,
        t2 = r.bottom + 8;
    l = Math.max(16, Math.min(l, window.innerWidth - 300));
    if (t2 + 320 > window.innerHeight - 16) t2 = r.top - 330;
    pop.style.left = l + "px";
    pop.style.top = t2 + "px";
    pop.classList.add("open");
}

function closePopover() {
    document.getElementById("dayPopover").classList.remove("open");
    closeSelectPopover();
}

function openSelectPopover(dayEl, ds, cell) {
    closeSelectPopover();

    const evs = evForDate(ds).filter((e) => filters[e.category]);
    const tasks = allTasks.filter((t) => t.due_date === ds);
    if (!evs.length && !tasks.length) return;

    const pop = document.createElement("div");
    pop.id = "selDayPop";
    pop.style.cssText = `
        position:fixed;z-index:9999;background:var(--bg-main,#fff);
        border:1px solid var(--border,rgba(0,0,0,.18));border-radius:10px;
        padding:10px 12px;width:230px;box-shadow:0 6px 20px rgba(0,0,0,.12);
    `;

    const dateLabel = cell.toLocaleDateString("en-US", {
        weekday: "short",
        month: "short",
        day: "numeric",
    });
    let html = `<div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);">${dateLabel}</div>`;

    evs.forEach((ev) => {
        const isSel = selIds.has(ev.id);
        html += `<div onclick="toggleEventSel('${ev.id}');renderSelPopItems()"
            style="display:flex;align-items:center;gap:8px;padding:6px 2px;border-bottom:1px solid rgba(0,0,0,.05);cursor:pointer;">
            <div style="width:15px;height:15px;border-radius:3px;border:1.5px solid ${CC[ev.category]};flex-shrink:0;
                display:flex;align-items:center;justify-content:center;background:${isSel ? CC[ev.category] : "transparent"};"
                class="spop-chk" data-type="ev" data-id="${ev.id}" data-color="${CC[ev.category]}">
                ${isSel ? `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>` : ""}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ev.title)}</div>
                <div style="font-size:10px;color:var(--text-light);">${ev.event_time ? fmt12(ev.event_time) + " · " : ""}${CL[ev.category]}</div>
            </div>
        </div>`;
    });

    tasks.forEach((t) => {
        const isSel = selTaskIds.has(t.id);
        html += `<div onclick="toggleTaskSel('${t.id}');renderSelPopItems()"
            style="display:flex;align-items:center;gap:8px;padding:6px 2px;border-bottom:1px solid rgba(0,0,0,.05);cursor:pointer;">
            <div style="width:15px;height:15px;border-radius:3px;border:1.5px solid ${PRI_COLOR[t.priority || "low"]};flex-shrink:0;
                display:flex;align-items:center;justify-content:center;background:${isSel ? PRI_COLOR[t.priority || "low"] : "transparent"};"
                class="spop-chk" data-type="task" data-id="${t.id}" data-color="${PRI_COLOR[t.priority || "low"]}">
                ${isSel ? `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>` : ""}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                <div style="font-size:10px;color:var(--text-light);">Task · ${t.priority || "low"} priority${t.due_time ? " · " + fmt12(t.due_time) : ""}</div>
            </div>
        </div>`;
    });

    pop.innerHTML = html;
    document.body.appendChild(pop);
    selPopoverEl = pop;

    // expose a re-render helper so toggleEventSel/toggleTaskSel can update checkboxes
    window.renderSelPopItems = () => {
        pop.querySelectorAll(".spop-chk").forEach((chk) => {
            const isEv = chk.dataset.type === "ev";
            const sel = isEv
                ? selIds.has(chk.dataset.id)
                : selTaskIds.has(chk.dataset.id);
            chk.style.background = sel ? chk.dataset.color : "transparent";
            chk.innerHTML = sel
                ? `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>`
                : "";
        });
        updateBulkBar();
        // refresh calendar dots/chips without destroying popup
        renderCal();
    };

    // position
    const r = dayEl.getBoundingClientRect();
    let left = r.right + 6;
    let top = r.top;
    if (left + 240 > window.innerWidth - 12) left = r.left - 240;
    if (top + pop.offsetHeight + 40 > window.innerHeight)
        top = Math.max(8, window.innerHeight - pop.offsetHeight - 16);
    pop.style.left = left + "px";
    pop.style.top = top + "px";

    setTimeout(() => {
        document.addEventListener("click", closeSelPopoverOutside);
    }, 0);
}

function closeSelPopoverOutside(e) {
    if (selPopoverEl && !selPopoverEl.contains(e.target)) {
        closeSelectPopover();
    }
}

function closeSelectPopover() {
    if (selPopoverEl) {
        selPopoverEl.remove();
        selPopoverEl = null;
        document.removeEventListener("click", closeSelPopoverOutside);
        window.renderSelPopItems = null;
    }
}

// ═══════════════════════════════════════════════════════════════════
// SELECT MODE
// ═══════════════════════════════════════════════════════════════════
function toggleSelectMode() {
    selectMode = !selectMode;
    const btn = document.getElementById("btnSelectMode");
    if (selectMode) {
        document.body.classList.add("select-mode");
        btn.classList.add("active");
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="20 6 9 17 4 12"/></svg> Selecting…`;
        // Re-render all panels so checkboxes appear immediately
        renderCal();
        renderTaskManager();
        renderAllEvents();
    } else {
        exitSelectMode();
    }
}

function exitSelectMode() {
    selectMode = false;
    selIds.clear();
    selTaskIds.clear(); // ← clear task selection too
    document.body.classList.remove("select-mode");
    const btn = document.getElementById("btnSelectMode");
    if (!btn) return;
    btn.classList.remove("active");
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Select`;
    document
        .querySelectorAll(".cal-day.day-sel")
        .forEach((d) => d.classList.remove("day-sel"));
    updateBulkBar();
    // Re-render task manager and all events to remove selection UI
    renderTaskManager();
    renderAllEvents();
    closeSelectPopover();
}

// Toggle ALL events for a day (via day-check checkbox)
function toggleDaySel(ds, div) {
    const ids = evForDate(ds).map((e) => e.id);
    const taskIds = tasksForDate(ds).map((t) => t.id);
    const allEvIn = ids.every((id) => selIds.has(id));
    const allTaskIn = taskIds.every((id) => selTaskIds.has(id));
    const allIn = allEvIn && allTaskIn;
    ids.forEach((id) => (allIn ? selIds.delete(id) : selIds.add(id)));
    taskIds.forEach((id) =>
        allIn ? selTaskIds.delete(id) : selTaskIds.add(id),
    );
    div.classList.toggle("day-sel", !allIn);
    updateBulkBar();
    renderTaskManager();
    renderAllEvents();
}

// Toggle a single event (chip click / all-events row click)
function toggleEventSel(id) {
    selIds.has(id) ? selIds.delete(id) : selIds.add(id);
    updateBulkBar();
    renderCal(); // refresh chips
    renderAllEvents(); // refresh all-events list
}

// Toggle a single task (task item click / task dot click)
function toggleTaskSel(id) {
    selTaskIds.has(id) ? selTaskIds.delete(id) : selTaskIds.add(id);
    updateBulkBar();
    renderCal(); // refresh task dots
    renderTaskManager(); // refresh task list
}

function toggleItemSel(id, el) {
    selIds.has(id) ? selIds.delete(id) : selIds.add(id);
    el.classList.toggle("item-sel", selIds.has(id));
    renderCal();
    updateBulkBar();
}

function updateBulkBar() {
    const n = selIds.size + selTaskIds.size;
    const countEl = document.getElementById("bulkCount");
    if (countEl) {
        const evPart = selIds.size
            ? `${selIds.size} event${selIds.size !== 1 ? "s" : ""}`
            : "";
        const tPart = selTaskIds.size
            ? `${selTaskIds.size} task${selTaskIds.size !== 1 ? "s" : ""}`
            : "";
        countEl.textContent =
            [evPart, tPart].filter(Boolean).join(" + ") + " selected";
    }
    const bar = document.getElementById("bulkBar");
    if (bar) bar.classList.toggle("visible", n > 0 && selectMode);
}

// ═══════════════════════════════════════════════════════════════════
// UPCOMING LIST
// ═══════════════════════════════════════════════════════════════════
function renderUpcoming() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
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

    const el = document.getElementById("upcomingList");
    if (!evList.length && !taskList.length) {
        el.innerHTML =
            '<div class="state-box">Nothing coming up this week 🎉</div>';
        return;
    }

    let html = "";
    evList.forEach((ev) => {
        const d = new Date(ev.idate + "T00:00:00");
        const dl = d.toLocaleDateString("en-US", {
            weekday: "short",
            month: "short",
            day: "numeric",
        });
        html += `<div class="upcoming-item" data-id="${ev.id}" onclick="openEvModal('${ev.id}')">
            <div class="upcoming-dot" style="background:${CC[ev.category]}"></div>
            <div class="upcoming-info">
                <div class="upcoming-title">${esc(ev.title)}</div>
                <div class="upcoming-sub">${dl}${ev.event_time ? " · " + fmt12(ev.event_time) : ""}</div>
            </div>
            <span class="upcoming-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">${CI[ev.category]} ${CL[ev.category]}</span>
        </div>`;
    });
    taskList.forEach((t) => {
        const d = new Date(t.due_date + "T00:00:00");
        const dl = d.toLocaleDateString("en-US", {
            weekday: "short",
            month: "short",
            day: "numeric",
        });
        html += `<div class="upcoming-item" onclick="openTaskModal('${t.id}')">
            <div class="upcoming-dot" style="background:${PRI_COLOR[t.priority || "low"]}"></div>
            <div class="upcoming-info">
                <div class="upcoming-title">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                <div class="upcoming-sub">${dl}${t.due_time ? " · " + fmt12(t.due_time) : ""}${t.label ? ` · ${esc(t.label)}` : ""}</div>
            </div>
            <span class="upcoming-tag" style="background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]}">✅ Task</span>
        </div>`;
    });
    el.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════════
// RIGHT SIDEBAR WIDGETS
// ═══════════════════════════════════════════════════════════════════
function renderDeadlines() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const evItems = allEvents
        .filter((e) => e.category === "todo")
        .sort((a, b) => (a.event_date > b.event_date ? 1 : -1))
        .slice(0, 4);
    const taskItems = allTasks
        .filter((t) => t.due_date && !t.completed_at)
        .sort((a, b) => (a.due_date > b.due_date ? 1 : -1))
        .slice(0, 4);

    const items = [
        ...evItems.map((e) => ({
            title: e.title,
            date: e.event_date,
            time: e.event_time,
            isTask: false,
        })),
        ...taskItems.map((t) => ({
            title: t.title,
            date: t.due_date,
            time: t.due_time,
            isTask: true,
            priority: t.priority,
        })),
    ]
        .sort((a, b) => (a.date > b.date ? 1 : -1))
        .slice(0, 7);

    if (!items.length) {
        document.getElementById("deadlinesList").innerHTML =
            '<div class="state-box">No deadlines 🎉</div>';
        return;
    }
    document.getElementById("deadlinesList").innerHTML = items
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
            } else if (diff <= 3) cls = "due-soon";
            const icon = e.isTask
                ? PRI_ICON[e.priority || "low"] || "✅"
                : "📌";
            return `<div class="deadline-item">
            <div class="deadline-icon">${icon}</div>
            <div class="deadline-info">
                <div class="deadline-title">${esc(e.title)}</div>
                <div class="deadline-subject">${due.toLocaleDateString("en-US", { month: "short", day: "numeric" })}${e.time ? " · " + fmt12(e.time) : ""}</div>
            </div>
            <div class="deadline-due ${cls}">${lbl}</div>
        </div>`;
        })
        .join("");
}

function renderMyCalendars() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const wEnd = new Date(today);
    wEnd.setDate(today.getDate() + 7);
    const cntW = (cat) =>
        expanded.filter(
            (e) =>
                e.category === cat &&
                new Date(e.idate + "T00:00:00") >= today &&
                new Date(e.idate + "T00:00:00") <= wEnd,
        ).length;
    const cats = [
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
    ];
    document.getElementById("myCalendars").innerHTML = cats
        .map(
            (c) =>
                `<div class="cal-category ${filters[c.key] ? "active" : ""}" style="color:${c.color}" onclick="toggleFilter('${c.key}')">
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

// ═══════════════════════════════════════════════════════════════════
// ALL EVENTS LIST — with per-item selection in select mode
// ═══════════════════════════════════════════════════════════════════
function renderAllEvents() {
    const el = document.getElementById("allEventsList");
    if (!el) return;
    const q = (document.getElementById("allEvSearch")?.value || "")
        .toLowerCase()
        .trim();
    const evs = allEvents
        .filter((e) => filters[e.category])
        .filter(
            (e) =>
                !q ||
                e.title.toLowerCase().includes(q) ||
                (e.description || "").toLowerCase().includes(q),
        )
        .sort((a, b) =>
            a.event_date > b.event_date
                ? 1
                : a.event_date < b.event_date
                  ? -1
                  : (a.event_time || "") > (b.event_time || "")
                    ? 1
                    : -1,
        );

    if (!evs.length) {
        el.innerHTML = `<div class="all-ev-empty">${q ? "No events match." : "No events yet."}</div>`;
        return;
    }
    const groups = {};
    evs.forEach((ev) => {
        const key = new Date(ev.event_date + "T00:00:00").toLocaleDateString(
            "en-US",
            { month: "long", year: "numeric" },
        );
        if (!groups[key]) groups[key] = [];
        groups[key].push(ev);
    });

    let html = "";
    Object.entries(groups).forEach(([month, items]) => {
        html += `<div class="all-ev-group-label">${month}</div>`;
        items.forEach((ev) => {
            const isSel = selectMode && selIds.has(ev.id);
            const dl = new Date(ev.event_date + "T00:00:00").toLocaleDateString(
                "en-US",
                { weekday: "short", month: "short", day: "numeric" },
            );

            // Selection checkbox shown in select mode
            const checkbox = selectMode
                ? `<div onclick="event.stopPropagation();toggleEventSel('${ev.id}')"
                    style="width:17px;height:17px;border-radius:4px;border:2px solid ${CC[ev.category]};flex-shrink:0;
                    display:flex;align-items:center;justify-content:center;cursor:pointer;
                    background:${isSel ? CC[ev.category] : "transparent"};">
                    ${isSel ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
                   </div>`
                : `<div class="all-ev-dot" style="background:${CC[ev.category]}"></div>`;

            html += `<div class="all-ev-item ${isSel ? "item-sel" : ""}" data-id="${ev.id}"
                style="${isSel ? "background:rgba(26,95,122,0.07);" : ""}"
                onclick="${selectMode ? `toggleEventSel('${ev.id}')` : `openEvModal('${ev.id}')`}">
                ${checkbox}
                <div class="all-ev-info">
                    <div class="all-ev-title" title="${esc(ev.title)}">${esc(ev.title)}</div>
                    <div class="all-ev-sub">${dl}${ev.event_time ? " · " + fmt12(ev.event_time) : ""}</div>
                </div>
                <span class="all-ev-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">${CI[ev.category]}</span>
            </div>`;
        });
    });
    el.innerHTML = html;
}

function toggleFilter(key) {
    filters[key] = !filters[key];
    renderCal();
    renderMyCalendars();
    renderUpcoming();
}

// ═══════════════════════════════════════════════════════════════════
// TASK MANAGER — with per-item selection in select mode
// ═══════════════════════════════════════════════════════════════════
function tasksForDate(ds) {
    return allTasks.filter((t) => t.due_date === ds);
}

function renderTaskManager() {
    const total = allTasks.length;
    const completed = allTasks.filter((t) => t.completed_at).length;
    const active = total - completed;

    document.getElementById("taskCountBadge").textContent = active;

    const progEl = document.getElementById("taskProgress");
    if (total > 0) {
        progEl.style.display = "block";
        document.getElementById("taskProgressLabel").textContent =
            `${completed} / ${total} done`;
        document.getElementById("taskProgressBar").style.width =
            `${Math.round((completed / total) * 100)}%`;
    } else {
        progEl.style.display = "none";
    }

    const labels = [
        ...new Set(allTasks.filter((t) => t.label).map((t) => t.label)),
    ];
    document.getElementById("taskLabelChips").innerHTML =
        labels
            .map(
                (l) =>
                    `<button onclick="setLabelFilter('${esc(l)}')" style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);font-size:12px;cursor:pointer;background:${taskLabelFilter === l ? "var(--primary,#1a5f7a)" : "var(--bg-main)"};color:${taskLabelFilter === l ? "white" : "var(--text-secondary)"};">${esc(l)}</button>`,
            )
            .join("") +
        (taskLabelFilter
            ? `<button onclick="setLabelFilter(null)" style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);font-size:12px;cursor:pointer;background:transparent;color:var(--text-secondary);">✕ Clear</button>`
            : "");

    let tasks = [...allTasks];
    if (taskFilterStatus === "active")
        tasks = tasks.filter((t) => !t.completed_at);
    if (taskFilterStatus === "completed")
        tasks = tasks.filter((t) => !!t.completed_at);
    if (taskFilterPri !== "all")
        tasks = tasks.filter((t) => t.priority === taskFilterPri);
    if (taskLabelFilter)
        tasks = tasks.filter((t) => t.label === taskLabelFilter);

    if (taskSort === "due")
        tasks.sort((a, b) =>
            (a.due_date || "9999") > (b.due_date || "9999") ? 1 : -1,
        );
    if (taskSort === "priority")
        tasks.sort((a, b) => {
            const o = { high: 0, medium: 1, low: 2 };
            return (
                (o[a.priority || "low"] || 0) - (o[b.priority || "low"] || 0)
            );
        });
    if (taskSort === "label")
        tasks.sort((a, b) => (a.label || "").localeCompare(b.label || ""));
    if (taskSort === "created")
        tasks.sort((a, b) => (a.created_at > b.created_at ? -1 : 1));

    const el = document.getElementById("taskList");
    if (!tasks.length) {
        el.innerHTML = `<div class="state-box">${total === 0 ? "No tasks yet. Add your first task! ✅" : "No tasks match your filters."}</div>`;
        return;
    }

    if (taskSort === "due") {
        const groups = {};
        tasks.forEach((t) => {
            const key = t.due_date
                ? new Date(t.due_date + "T00:00:00").toLocaleDateString(
                      "en-US",
                      { weekday: "short", month: "short", day: "numeric" },
                  )
                : "No due date";
            if (!groups[key]) groups[key] = [];
            groups[key].push(t);
        });
        el.innerHTML = Object.entries(groups)
            .map(
                ([date, items]) =>
                    `<div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;padding:10px 0 4px;">${date}</div>` +
                    items.map(taskItemHTML).join(""),
            )
            .join("");
    } else {
        el.innerHTML = tasks.map(taskItemHTML).join("");
    }
}

function taskItemHTML(t) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = t.due_date ? new Date(t.due_date + "T00:00:00") : null;
    const diff = due ? Math.ceil((due - today) / 86400000) : null;
    const overdue = diff !== null && diff < 0 && !t.completed_at;
    const isDueToday = diff === 0 && !t.completed_at;
    const isSel = selectMode && selTaskIds.has(t.id);

    // In select mode: square checkbox for selection; otherwise circular complete button
    const checkBtn = selectMode
        ? `<button class="task-check-btn" onclick="event.stopPropagation();toggleTaskSel('${t.id}')"
            style="width:20px;height:20px;border-radius:4px;border:2px solid ${PRI_COLOR[t.priority || "low"]};flex-shrink:0;
            background:${isSel ? PRI_COLOR[t.priority || "low"] : "transparent"};cursor:pointer;display:flex;align-items:center;justify-content:center;">
            ${isSel ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
           </button>`
        : `<button class="task-check-btn" onclick="toggleTaskDone('${t.id}')"
            style="width:20px;height:20px;border-radius:50%;border:2px solid ${PRI_COLOR[t.priority || "low"]};flex-shrink:0;
            background:${t.completed_at ? PRI_COLOR[t.priority || "low"] : "transparent"};cursor:pointer;display:flex;align-items:center;justify-content:center;">
            ${t.completed_at ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
           </button>`;

    // In select mode clicking the body also toggles selection; otherwise opens modal
    const bodyClick = selectMode
        ? `onclick="event.stopPropagation();toggleTaskSel('${t.id}')" style="flex:1;min-width:0;cursor:pointer;"`
        : `onclick="openTaskModal('${t.id}')" style="flex:1;min-width:0;cursor:pointer;"`;

    return `<div class="task-item ${t.completed_at && !selectMode ? "task-done" : ""} ${isSel ? "item-sel" : ""}"
        data-id="${t.id}"
        style="${isSel ? "background:rgba(26,95,122,0.07);border-radius:8px;" : ""}">
        ${checkBtn}
        <div class="task-body" ${bodyClick}>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:600;color:var(--text-primary);${t.completed_at && !selectMode ? "text-decoration:line-through;opacity:.45;" : ""}">${esc(t.title)}</span>
                <span style="padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]}">${PRI_ICON[t.priority || "low"]} ${t.priority || "low"}</span>
                ${t.label ? `<span style="padding:2px 7px;border-radius:99px;font-size:11px;background:var(--border);color:var(--text-secondary);">${esc(t.label)}</span>` : ""}
            </div>
            <div style="display:flex;gap:10px;margin-top:3px;flex-wrap:wrap;">
                ${
                    due
                        ? `<span style="font-size:11px;color:${overdue ? "#dc2626" : isDueToday ? "#d97706" : "var(--text-secondary)"};">
                    ${overdue ? "⚠️ Overdue" : isDueToday ? "⏰ Due today" : `📅 ${due.toLocaleDateString("en-US", { month: "short", day: "numeric" })}`}
                    ${t.due_time ? " · " + fmt12(t.due_time) : ""}</span>`
                        : ""
                }
                ${t.notes ? `<span style="font-size:11px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">📝 ${esc(t.notes)}</span>` : ""}
            </div>
        </div>
        <button onclick="promptDeleteTask('${t.id}')" style="background:none;border:none;cursor:pointer;color:var(--text-light);padding:4px;border-radius:4px;flex-shrink:0;"
            title="Delete task">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
            </svg>
        </button>
    </div>`;
}

function setLabelFilter(label) {
    taskLabelFilter = label;
    renderTaskManager();
}

async function toggleTaskDone(id) {
    const t = allTasks.find((x) => x.id === id);
    if (!t) return;
    const data = t.completed_at
        ? { completed_at: null }
        : { completed_at: new Date().toISOString() };
    try {
        await taskUpdate(id, data);
        const i = allTasks.findIndex((x) => x.id === id);
        if (i !== -1) allTasks[i] = { ...allTasks[i], ...data };
        renderTaskManager();
        renderDeadlines();
        renderUpcoming();
        if (curView === "month") renderCal();
        else if (curView === "week") renderWeek();
        else renderDay();
    } catch (err) {
        alert("Failed: " + err.message);
    }
}

// ── Task Modal ──────────────────────────────────────────────────
function openTaskModal(id = null) {
    editTaskId = id;
    resetTaskForm();
    if (id) {
        const t = allTasks.find((x) => x.id === id);
        if (!t) return;
        document.getElementById("taskModalTitle").textContent = "Edit Task";
        document.getElementById("taskTitle").value = t.title;
        document.getElementById("taskDueDate").value = t.due_date || "";
        document.getElementById("taskDueTime").value = t.due_time
            ? t.due_time.slice(0, 5)
            : "";
        document.getElementById("taskLabel").value = t.label || "";
        document.getElementById("taskNotes").value = t.notes || "";
        const pri = t.priority || "low";
        document.querySelectorAll(".priority-option").forEach((l) => {
            const active = l.dataset.p === pri;
            l.classList.toggle("sel", active);
            l.querySelector("input").checked = active;
        });
        document.getElementById("btnDelTask").style.display = "block";
    } else {
        document.getElementById("taskModalTitle").textContent = "Add Task";
        document.getElementById("btnDelTask").style.display = "none";
    }
    document.getElementById("taskModal").classList.add("open");
}

function closeTaskModal() {
    document.getElementById("taskModal").classList.remove("open");
    editTaskId = null;
}

function resetTaskForm() {
    [
        "taskTitle",
        "taskDueDate",
        "taskDueTime",
        "taskLabel",
        "taskNotes",
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
    document.querySelectorAll(".priority-option").forEach((l) => {
        l.classList.toggle("sel", l.dataset.p === "low");
        l.querySelector("input").checked = l.dataset.p === "low";
    });
    document.getElementById("btnDelTask").style.display = "none";
}

async function saveTask() {
    const title = document.getElementById("taskTitle").value.trim();
    if (!title) return alert("Please enter a task title.");
    const pri =
        document.querySelector('input[name="taskPriority"]:checked')?.value ||
        "low";
    const data = {
        title,
        priority: pri,
        due_date: document.getElementById("taskDueDate").value || null,
        due_time: document.getElementById("taskDueTime").value || null,
        label: document.getElementById("taskLabel").value.trim() || null,
        notes: document.getElementById("taskNotes").value.trim() || null,
    };
    const btn = document.getElementById("btnSaveTask");
    btn.textContent = "Saving…";
    btn.disabled = true;
    try {
        if (editTaskId) {
            await taskUpdate(editTaskId, data);
            const i = allTasks.findIndex((x) => x.id === editTaskId);
            if (i !== -1) allTasks[i] = { ...allTasks[i], ...data };
        } else {
            const row = await taskInsert(data);
            allTasks.unshift(row);
        }
        closeTaskModal();
        renderTaskManager();
        renderDeadlines();
        renderUpcoming();
        if (curView === "month") renderCal();
        else if (curView === "week") renderWeek();
        else renderDay();
    } catch (err) {
        alert("Save failed: " + err.message);
    } finally {
        btn.textContent = "Save Task";
        btn.disabled = false;
    }
}

function promptDeleteTask(id) {
    const t = allTasks.find((x) => x.id === id);
    pendDel = { mode: "task", ids: [id] };
    document.getElementById("confirmTitle").textContent = "Delete Task";
    document.getElementById("confirmBody").innerHTML =
        `Are you sure you want to delete <strong>"${esc(t?.title || "this task")}"</strong>? This cannot be undone.`;
    closeTaskModal();
    document.getElementById("confirmModal").classList.add("open");
}

// ═══════════════════════════════════════════════════════════════════
// EVENT MODAL
// ═══════════════════════════════════════════════════════════════════
function openEvModal(id = null, prefill = null) {
    closePopover();
    editId = id;
    resetForm();
    if (prefill) document.getElementById("evDate").value = prefill;
    if (id) {
        const ev = allEvents.find((e) => e.id === id);
        if (!ev) return;
        document.getElementById("modalTitle").textContent = "Edit Event";
        document.getElementById("evTitle").value = ev.title;
        document.getElementById("evDate").value = ev.event_date;
        document.getElementById("evCat").value = ev.category;
        document.getElementById("evDesc").value = ev.description || "";
        updateModalFields();
        const st = ev.event_time ? ev.event_time.slice(0, 5) : "";
        const et = ev.end_time ? ev.end_time.slice(0, 5) : "";
        if (ev.category === "todo")
            document.getElementById("evTimeTodo").value = st;
        else if (ev.category === "class") {
            document.getElementById("evTimeStart").value = st;
            document.getElementById("evTimeEnd").value = et;
        } else if (ev.category === "group") {
            document.getElementById("evTimeStartGroup").value = st;
            document.getElementById("evTimeEndGroup").value = et;
        } else if (ev.category === "event") {
            document.getElementById("evTimeStartEvent").value = st;
            document.getElementById("evTimeEndEvent").value = et;
        }
        document.getElementById("evRecur").checked = !!ev.is_recurring;
        if (ev.is_recurring) {
            document.getElementById("recurOpts").style.display = "block";
            ev.recur_days?.forEach((d) => {
                const b = document.querySelector(`.rday[data-d="${d}"]`);
                if (b) b.classList.add("sel");
            });
            document.getElementById("evRecurEnd").value = ev.recur_end || "";
        }
        document.getElementById("btnDelEv").style.display = "block";
    } else {
        document.getElementById("modalTitle").textContent = "Add Event";
        if (!prefill) document.getElementById("evDate").value = fd(new Date());
        updateModalFields();
    }
    document.getElementById("eventModal").classList.add("open");
}

function closeEvModal() {
    document.getElementById("eventModal").classList.remove("open");
    editId = null;
}

function updateModalFields() {
    const cat = document.getElementById("evCat").value;
    ["class", "group", "event"].forEach((c) => {
        const el = document.getElementById(
            `timeField${c.charAt(0).toUpperCase() + c.slice(1)}`,
        );
        if (el) el.style.display = cat === c ? "" : "none";
    });
}

function resetForm() {
    [
        "evTitle",
        "evDesc",
        "evTimeTodo",
        "evTimeStart",
        "evTimeEnd",
        "evTimeStartGroup",
        "evTimeEndGroup",
        "evTimeStartEvent",
        "evTimeEndEvent",
        "evRecurEnd",
        "evDate",
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
    document.getElementById("evCat").value = "todo";
    document.getElementById("evRecur").checked = false;
    document.getElementById("recurOpts").style.display = "none";
    document
        .querySelectorAll(".rday")
        .forEach((b) => b.classList.remove("sel"));
    document.getElementById("btnDelEv").style.display = "none";
    updateModalFields();
}

async function saveEv() {
    const cat = document.getElementById("evCat").value;
    const title = document.getElementById("evTitle").value.trim();
    const date = document.getElementById("evDate").value;
    if (!title) return alert("Please enter a title.");
    if (!date) return alert("Please select a date.");

    let startTime = null,
        endTime = null;
    if (cat === "todo")
        startTime = document.getElementById("evTimeTodo").value || null;
    if (cat === "class") {
        startTime = document.getElementById("evTimeStart").value || null;
        endTime = document.getElementById("evTimeEnd").value || null;
    } else if (cat === "group") {
        startTime = document.getElementById("evTimeStartGroup").value || null;
        endTime = document.getElementById("evTimeEndGroup").value || null;
    } else if (cat === "event") {
        startTime = document.getElementById("evTimeStartEvent").value || null;
        endTime = document.getElementById("evTimeEndEvent").value || null;
    }

    if (cat === "class" && (!startTime || !endTime))
        return alert("Class requires both start and end time.");
    if (cat === "group" && !startTime)
        return alert("Study group requires a start time.");

    const recur = document.getElementById("evRecur").checked;
    const rDays = recur
        ? [...document.querySelectorAll(".rday.sel")].map((b) => b.dataset.d)
        : null;
    if (recur && rDays && !rDays.length)
        return alert("Select at least one repeat day.");

    const data = {
        title,
        event_date: date,
        category: cat,
        description: document.getElementById("evDesc").value.trim() || null,
        event_time: startTime,
        end_time: endTime,
        is_recurring: recur,
        recur_days: rDays,
        recur_end: recur
            ? document.getElementById("evRecurEnd").value || null
            : null,
    };

    const btn = document.getElementById("btnSaveEv");
    btn.textContent = "Saving…";
    btn.disabled = true;
    try {
        if (editId) {
            const updated = await dbUpdate(editId, data);
            const i = allEvents.findIndex((e) => e.id === editId);
            if (i !== -1)
                allEvents[i] = { ...allEvents[i], ...data, ...(updated || {}) };
        } else {
            const row = await dbInsert(data);
            allEvents.push(row);
        }
        expandAll();
        closeEvModal();
        redraw();
    } catch (err) {
        alert("Save failed: " + err.message);
    } finally {
        btn.textContent = "Save";
        btn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════
// DELETE
// ═══════════════════════════════════════════════════════════════════
function promptSingleDelete(id) {
    closeEvModal();
    closePopover();
    const ev = allEvents.find((e) => e.id === id);
    pendDel = { mode: "single", ids: [id] };
    document.getElementById("confirmTitle").textContent = "Delete Event";
    document.getElementById("confirmBody").innerHTML =
        `Are you sure you want to delete <strong>"${esc(ev?.title || "this event")}"</strong>? This cannot be undone.`;
    document.getElementById("confirmModal").classList.add("open");
}

function promptBulkDelete() {
    const evCount = selIds.size;
    const taskCount = selTaskIds.size;
    if (!evCount && !taskCount) return;

    // Store mutable copies so user can uncheck items in the confirm dialog
    pendDel = {
        mode: "bulk",
        ids: new Set([...selIds]),
        taskIds: new Set([...selTaskIds]),
    };

    document.getElementById("confirmTitle").textContent = `Delete items`;

    // Build checklist HTML
    let listHtml = `<div style="border:1px solid var(--border,rgba(0,0,0,.12));border-radius:8px;overflow:hidden;margin:12px 0;">`;

    [...selIds].forEach((id) => {
        const ev = allEvents.find((e) => e.id === id);
        if (!ev) return;
        const dl = new Date(ev.event_date + "T00:00:00").toLocaleDateString(
            "en-US",
            { month: "short", day: "numeric" },
        );
        listHtml += `<div onclick="toggleConfirmEv('${id}')" id="ci-ev-${id}"
            style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-bottom:1px solid var(--border,rgba(0,0,0,.08));cursor:pointer;background:var(--bg-main);">
            <div id="cichk-ev-${id}" style="width:16px;height:16px;border-radius:4px;border:1.5px solid ${CC[ev.category]};flex-shrink:0;
                display:flex;align-items:center;justify-content:center;background:${CC[ev.category]};">
                <svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>
            </div>
            <div style="width:8px;height:8px;border-radius:50%;background:${CC[ev.category]};flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ev.title)}</div>
                <div style="font-size:11px;color:var(--text-light);">${CL[ev.category]} · ${dl}</div>
            </div>
        </div>`;
    });

    [...selTaskIds].forEach((id) => {
        const t = allTasks.find((x) => x.id === id);
        if (!t) return;
        const dl = t.due_date
            ? new Date(t.due_date + "T00:00:00").toLocaleDateString("en-US", {
                  month: "short",
                  day: "numeric",
              })
            : "No date";
        const priColor = PRI_COLOR[t.priority || "low"];
        listHtml += `<div onclick="toggleConfirmTask('${id}')" id="ci-task-${id}"
            style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-bottom:1px solid var(--border,rgba(0,0,0,.08));cursor:pointer;background:var(--bg-main);">
            <div id="cichk-task-${id}" style="width:16px;height:16px;border-radius:4px;border:1.5px solid ${priColor};flex-shrink:0;
                display:flex;align-items:center;justify-content:center;background:${priColor};">
                <svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>
            </div>
            <div style="width:8px;height:8px;border-radius:50%;background:${priColor};flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                <div style="font-size:11px;color:var(--text-light);">Task · ${t.priority || "low"} · ${dl}</div>
            </div>
        </div>`;
    });

    listHtml += `</div>`;

    document.getElementById("confirmBody").innerHTML =
        `<span id="confirmBodyText">Uncheck anything you want to keep.</span>${listHtml}`;

    updateConfirmCount();
    document.getElementById("confirmModal").classList.add("open");
}

// Toggle an event's checked state inside the confirm dialog
function toggleConfirmEv(id) {
    const chk = document.getElementById(`cichk-ev-${id}`);
    const ev = allEvents.find((e) => e.id === id);
    if (!ev) return;
    if (pendDel.ids.has(id)) {
        pendDel.ids.delete(id);
        chk.style.background = "transparent";
        chk.innerHTML = "";
        document.getElementById(`ci-ev-${id}`).style.opacity = ".45";
    } else {
        pendDel.ids.add(id);
        chk.style.background = CC[ev.category];
        chk.innerHTML = `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>`;
        document.getElementById(`ci-ev-${id}`).style.opacity = "1";
    }
    updateConfirmCount();
}

// Toggle a task's checked state inside the confirm dialog
function toggleConfirmTask(id) {
    const chk = document.getElementById(`cichk-task-${id}`);
    const t = allTasks.find((x) => x.id === id);
    if (!t) return;
    const priColor = PRI_COLOR[t.priority || "low"];
    if (pendDel.taskIds.has(id)) {
        pendDel.taskIds.delete(id);
        chk.style.background = "transparent";
        chk.innerHTML = "";
        document.getElementById(`ci-task-${id}`).style.opacity = ".45";
    } else {
        pendDel.taskIds.add(id);
        chk.style.background = priColor;
        chk.innerHTML = `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>`;
        document.getElementById(`ci-task-${id}`).style.opacity = "1";
    }
    updateConfirmCount();
}

function updateConfirmCount() {
    const n = (pendDel?.ids?.size || 0) + (pendDel?.taskIds?.size || 0);
    const btn = document.getElementById("btnConfirmDel");
    if (btn) {
        btn.textContent = n > 0 ? `Yes, delete ${n}` : "Nothing to delete";
        btn.disabled = n === 0;
    }
    const title = document.getElementById("confirmTitle");
    if (title) title.textContent = `Delete ${n} item${n !== 1 ? "s" : ""}`;
}

function closeConfirm() {
    document.getElementById("confirmModal").classList.remove("open");
    pendDel = null;
}

async function execDelete() {
    if (!pendDel) return;
    const btn = document.getElementById("btnConfirmDel");
    btn.textContent = "Deleting…";
    btn.disabled = true;
    try {
        if (pendDel.mode === "task") {
            // Single task delete
            await taskDelete(pendDel.ids[0]);
            allTasks = allTasks.filter((t) => t.id !== pendDel.ids[0]);
            renderTaskManager();
            renderDeadlines();
            renderUpcoming();
            if (curView === "month") renderCal();
            else if (curView === "week") renderWeek();
            else renderDay();
        } else {
            // Bulk delete: events + tasks
            if (pendDel.ids.size > 0) {
                await dbDelete([...pendDel.ids]);
                const evSet = pendDel.ids;
                allEvents = allEvents.filter((e) => !evSet.has(e.id));
            }
            if (pendDel.taskIds?.size > 0) {
                await Promise.all(
                    [...pendDel.taskIds].map((id) => taskDelete(id)),
                );
                const taskSet = pendDel.taskIds;
                allTasks = allTasks.filter((t) => !taskSet.has(t.id));
            }
            exitSelectMode(); // clears selIds, selTaskIds, re-renders
            expandAll();
            redraw();
            renderTaskManager();
        }
        closeConfirm();
    } catch (err) {
        alert("Delete failed: " + err.message);
    } finally {
        btn.textContent = "Yes, Delete";
        btn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════════════════
function evForDate(ds) {
    return expanded.filter((e) => e.idate === ds);
}
function fd(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}
function sameDay(a, b) {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}
function fmt12(t) {
    if (!t) return "";
    const [h, m] = t.split(":").map(Number);
    return `${h === 0 ? 12 : h > 12 ? h - 12 : h}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
}
function esc(s) {
    if (s == null) return "";
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
}
