// studyhub-core.js
// ═══════════════════════════════════════════════════════════════════
// Shared by ALL pages: dashboard, calendar, tasks
// Requires SB_URL, SB_ANON, SB_SVC, UID injected from blade
// ═══════════════════════════════════════════════════════════════════

const TABLE = "calendar_events";
const TASK_TABLE = "tasks";

// ═══════════════════════════════════════════════════════════════════
// SHARED STATE
// ═══════════════════════════════════════════════════════════════════
let curDate = new Date();
let allEvents = [];
let expanded = [];
let filters = {
    class: true,
    group: true,
    event: true,
    exam: true,
    deadline: true,
};
let selectMode = false;
let selPopoverEl = null;
let selIds = new Set();
let selTaskIds = new Set();
let editId = null;
let pendDel = null;
let curView = "month"; // 'month' | 'week' | 'day'

// Task state
let allTasks = [];
let editTaskId = null;
let taskFilterPri = "all";
let taskFilterStatus = "all"; // ← must be "all", never "active"
let taskSort = "created"; // ← default to newest so tasks always appear
let taskLabelFilter = null;

// ═══════════════════════════════════════════════════════════════════
// COLOUR / ICON CONSTANTS  (used by calendar AND tasks)
// ═══════════════════════════════════════════════════════════════════
const CC = {
    class: "#0f766e",
    group: "#7c3aed",
    event: "#1a5f7a",
    exam: "#dc2626",
    deadline: "#d97706",
};
const CB = {
    class: "rgba(42,157,143,.13)",
    group: "rgba(124,77,202,.13)",
    event: "rgba(26,95,122,.11)",
    exam: "rgba(220,38,38,.1)",
    deadline: "rgba(217,119,6,.1)",
};
const CI = {
    class: "📗",
    group: "👥",
    event: "📅",
    exam: "📝",
    deadline: "📌",
};
const CL = {
    class: "Class",
    group: "Study Group",
    event: "Event",
    exam: "Exam",
    deadline: "Deadline",
};

const PRI_COLOR = { high: "#dc2626", medium: "#d97706", low: "#16a34a" };
const PRI_BG = {
    high: "rgba(220,38,38,.1)",
    medium: "rgba(217,119,6,.1)",
    low: "rgba(22,163,74,.1)",
};
const PRI_ICON = { high: "🔴", medium: "🟡", low: "🟢" };

const DN = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

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
    // Handle empty bodies (204 No Content OR empty 200)
    if (r.status === 204) return null;
    const text = await r.text();
    if (!text || !text.trim()) return null;
    return JSON.parse(text);
}

// ── Calendar DB ──────────────────────────────────────────────────
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

// ── Task DB ──────────────────────────────────────────────────────
// ALL task reads and writes use hdrs(true) = service key so RLS never
// blocks them. This is what fixes the "disappears on reload" bug.
async function taskLoad() {
    try {
        const result = await sbReq(
            `${TASK_TABLE}?user_id=eq.${UID}&order=created_at.desc&select=*,subtask_count:subtasks(count)`,
            { headers: hdrs(true) },
        );
        allTasks = (Array.isArray(result) ? result : []).map((t) => ({
            ...t,
            // Supabase returns count as [{ count: N }], normalize to a plain number
            subtask_count: Array.isArray(t.subtask_count)
                ? (t.subtask_count[0]?.count ?? 0)
                : (t.subtask_count ?? 0),
        }));
        console.log(`taskLoad: ${allTasks.length} tasks loaded`);
    } catch (err) {
        console.error("taskLoad failed:", err);
        allTasks = [];
        throw err; // re-throw so the boot handler can show the error state
    }
}

async function taskInsert(data) {
    // Use return=representation and safely unwrap the array
    const result = await sbReq(TASK_TABLE, {
        method: "POST",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify({ ...data, user_id: UID }),
    });
    return Array.isArray(result) ? result[0] : result;
}

async function taskUpdate(id, data) {
    // Use return=minimal — avoids the empty-array destructure crash
    await sbReq(`${TASK_TABLE}?id=eq.${id}`, {
        method: "PATCH",
        headers: { ...hdrs(true), Prefer: "return=minimal" },
        body: JSON.stringify(data),
    });
}

async function taskDelete(id) {
    await sbReq(`${TASK_TABLE}?id=eq.${id}`, {
        method: "DELETE",
        headers: hdrs(true),
    });
}

// ═══════════════════════════════════════════════════════════════════
// UTILS  (used everywhere)
// ═══════════════════════════════════════════════════════════════════
function evForDate(ds) {
    return expanded.filter((e) => e.idate === ds);
}
function tasksForDate(ds) {
    return allTasks.filter((t) => t.due_date === ds);
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

// ═══════════════════════════════════════════════════════════════════
// RECURRING EXPANSION  (needed by calendar page and dashboard)
// ═══════════════════════════════════════════════════════════════════
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
