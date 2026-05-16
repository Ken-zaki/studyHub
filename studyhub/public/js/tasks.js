// tasks.js
// ═══════════════════════════════════════════════════════════════════
// Tasks page — requires studyhub-core.js loaded first
// (studyhub-core provides: allTasks, taskLoad, taskInsert, taskUpdate,
//  taskDelete, selectMode, selTaskIds, pendDel, editTaskId,
//  taskFilterPri, taskFilterStatus, taskSort, taskLabelFilter,
//  PRI_COLOR, PRI_BG, PRI_ICON, esc, fmt12)
//
// Changes from previous version (FR-4.1, FR-4.4, FR-4.5):
//  - Status system: 'todo' | 'in_progress' | 'done'  (replaces completed_at toggle)
//  - Subtasks: addSubtask(), removeSubtask(), saveSubtasks(), loadSubtasks()
//  - Sidebar stats: updateSidebarStats() → #statTodo, #statInProgress, #statDone
//  - Subject tag color dot in task rows (FR-4.5)
//  - Status pill in task rows (FR-4.4)
//  - Status radio buttons in modal sync with setTaskStatus() from blade
// ═══════════════════════════════════════════════════════════════════

// ── Module-level subtask state ───────────────────────────────────
// Tracks subtasks for the task currently open in the modal.
// Each entry: { id, task_id, title, status, isNew, isDeleted }
let _subtasks = [];

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async () => {
    wireTaskUI();

    try {
        await taskLoad();
    } catch (err) {
        const tl = document.getElementById("taskList");
        if (tl)
            tl.innerHTML = `<div class="state-box err">⚠️ Could not load tasks: ${esc(err.message)}</div>`;
        return;
    }

    renderTaskManager();
    initNotifications?.();
});

// ═══════════════════════════════════════════════════════════════════
// WIRE UI
// ═══════════════════════════════════════════════════════════════════
function wireTaskUI() {
    // Task modal buttons
    document.getElementById("btnTaskModalClose").onclick = closeTaskModal;
    document.getElementById("btnTaskModalCancel").onclick = closeTaskModal;
    document.getElementById("btnSaveTask").onclick = saveTask;
    document.getElementById("btnDelTask").onclick = () =>
        promptDeleteTask(editTaskId);

    // Priority selector (unchanged)
    document.querySelectorAll(".priority-option").forEach((lbl) => {
        lbl.addEventListener("click", () => {
            document
                .querySelectorAll(".priority-option")
                .forEach((l) => l.classList.remove("sel"));
            lbl.classList.add("sel");
            lbl.querySelector("input").checked = true;
        });
    });

    // Status option selector (FR-4.4) — visual highlight on click
    // The blade also wires onclick="setTaskStatus(...)" on each label,
    // but we add the class toggle here for the 'sel' highlight.
    document.querySelectorAll(".status-option").forEach((lbl) => {
        lbl.addEventListener("click", () => {
            document
                .querySelectorAll(".status-option")
                .forEach((l) => l.classList.remove("sel"));
            lbl.classList.add("sel");
            lbl.querySelector("input").checked = true;
        });
    });

    // Filter / sort controls
    document.getElementById("taskSort").onchange = (e) => {
        taskSort = e.target.value;
        renderTaskManager();
    };
    document.getElementById("taskFilterPriority").onchange = (e) => {
        taskFilterPri = e.target.value;
        renderTaskManager();
    };
    // FR-4.4: status filter now handles 'todo' | 'in_progress' | 'done' | 'all'
    document.getElementById("taskFilterStatus").onchange = (e) => {
        taskFilterStatus = e.target.value;
        renderTaskManager();
    };

    // Confirm modal
    document.getElementById("btnConfirmClose").onclick = closeConfirm;
    document.getElementById("btnConfirmCancel").onclick = closeConfirm;
    document.getElementById("btnConfirmDel").onclick = execDelete;

    // Add / select-mode buttons (injected by blade)
    setTimeout(() => {
        const btnAddTask = document.getElementById("btnAddTask");
        const btnSelectMode = document.getElementById("btnSelectMode");
        const btnBulkCancel = document.getElementById("btnBulkCancel");
        const btnBulkDelete = document.getElementById("btnBulkDelete");

        if (btnAddTask) btnAddTask.onclick = () => openTaskModal();
        if (btnSelectMode) btnSelectMode.onclick = toggleSelectMode;
        if (btnBulkCancel) btnBulkCancel.onclick = exitSelectMode;
        if (btnBulkDelete) btnBulkDelete.onclick = promptBulkDelete;
    }, 0);
}

// ═══════════════════════════════════════════════════════════════════
// HELPERS — status display
// ═══════════════════════════════════════════════════════════════════

// Maps status value → human label
const STATUS_LABEL = {
    todo: "To-do",
    in_progress: "In Progress",
    done: "Done",
};

// Maps status value → pill background color
const STATUS_BG = {
    todo: "rgba(107,114,128,0.12)", // gray
    in_progress: "rgba(139,92,246,0.12)", // purple
    done: "rgba(42,157,143,0.12)", // teal
};

// Maps status value → pill text color
const STATUS_COLOR = {
    todo: "#6b7280",
    in_progress: "#8b5cf6",
    done: "#2a9d8f",
};

// Maps status value → icon
const STATUS_ICON = {
    todo: "📋",
    in_progress: "🔄",
    done: "✅",
};

// Returns true if a task counts as "completed" for progress bar purposes
function isDone(t) {
    return (t.status || "todo") === "done";
}

// ═══════════════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════════════
function renderTaskManager() {
    const total = allTasks.length;
    const doneCount = allTasks.filter(isDone).length;

    document.getElementById("taskCountBadge").textContent = allTasks.filter(
        (t) => (t.status || "todo") !== "done",
    ).length;

    // Progress bar (FR-4.4)
    const progEl = document.getElementById("taskProgress");
    if (total > 0) {
        progEl.style.display = "block";
        document.getElementById("taskProgressLabel").textContent =
            `${doneCount} / ${total} done`;
        document.getElementById("taskProgressBar").style.width =
            `${Math.round((doneCount / total) * 100)}%`;
    } else {
        progEl.style.display = "none";
    }

    // Sidebar stats (FR-4.4)
    updateSidebarStats();

    // Subject chips (FR-4.5) — renamed from "label chips" but same logic
    const labels = [
        ...new Set(allTasks.filter((t) => t.label).map((t) => t.label)),
    ];
    document.getElementById("taskLabelChips").innerHTML =
        labels
            .map(
                (l) =>
                    `<button onclick="setLabelFilter('${esc(l)}')"
                        style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);
                               font-size:12px;cursor:pointer;
                               background:${taskLabelFilter === l ? "var(--primary,#1a5f7a)" : "var(--bg-main)"};
                               color:${taskLabelFilter === l ? "white" : "var(--text-secondary)"};">
                        ${esc(l)}
                    </button>`,
            )
            .join("") +
        (taskLabelFilter
            ? `<button onclick="setLabelFilter(null)"
                style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);
                       font-size:12px;cursor:pointer;background:transparent;color:var(--text-secondary);">
                ✕ Clear
               </button>`
            : "");

    // Apply filters
    let tasks = [...allTasks];

    // FR-4.4: filter by the three status values
    if (taskFilterStatus === "todo")
        tasks = tasks.filter((t) => (t.status || "todo") === "todo");
    if (taskFilterStatus === "in_progress")
        tasks = tasks.filter((t) => (t.status || "todo") === "in_progress");
    if (taskFilterStatus === "done")
        tasks = tasks.filter((t) => (t.status || "todo") === "done");

    if (taskFilterPri !== "all")
        tasks = tasks.filter((t) => t.priority === taskFilterPri);
    if (taskLabelFilter)
        tasks = tasks.filter((t) => t.label === taskLabelFilter);

    // Sort
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
        el.innerHTML = `<div class="state-box">${
            total === 0
                ? "No tasks yet. Add your first task! ✅"
                : "No tasks match your filters."
        }</div>`;
        return;
    }

    // Group by due date when sorting by due
    if (taskSort === "due") {
        const groups = {};
        tasks.forEach((t) => {
            const key = t.due_date
                ? new Date(t.due_date + "T00:00:00").toLocaleDateString(
                      "en-US",
                      {
                          weekday: "short",
                          month: "short",
                          day: "numeric",
                      },
                  )
                : "No due date";
            if (!groups[key]) groups[key] = [];
            groups[key].push(t);
        });
        el.innerHTML = Object.entries(groups)
            .map(
                ([date, items]) =>
                    `<div style="font-size:11px;font-weight:600;color:var(--text-light);
                                 text-transform:uppercase;letter-spacing:.05em;padding:10px 0 4px;">
                        ${date}
                     </div>` + items.map(taskItemHTML).join(""),
            )
            .join("");
    } else {
        el.innerHTML = tasks.map(taskItemHTML).join("");
    }
}

// ── Sidebar stats (FR-4.4) ──────────────────────────────────────
function updateSidebarStats() {
    const todo = allTasks.filter((t) => (t.status || "todo") === "todo").length;
    const inProgress = allTasks.filter(
        (t) => (t.status || "todo") === "in_progress",
    ).length;
    const done = allTasks.filter((t) => (t.status || "todo") === "done").length;

    const elTodo = document.getElementById("statTodo");
    const elIP = document.getElementById("statInProgress");
    const elDone = document.getElementById("statDone");

    if (elTodo) elTodo.textContent = todo;
    if (elIP) elIP.textContent = inProgress;
    if (elDone) elDone.textContent = done;
}

// ── filterTasks() — called by blade's setStatusTab() ───────────
// Syncs the dropdown to the tab strip value then re-renders.
function filterTasks() {
    const dropdown = document.getElementById("taskFilterStatus");
    if (dropdown) taskFilterStatus = dropdown.value;
    renderTaskManager();
}

// ═══════════════════════════════════════════════════════════════════
// TASK ROW HTML
// ═══════════════════════════════════════════════════════════════════
function taskItemHTML(t) {
    const status = t.status || "todo";
    const isDoneT = status === "done";

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = t.due_date ? new Date(t.due_date + "T00:00:00") : null;
    const diff = due ? Math.ceil((due - today) / 86400000) : null;
    const overdue = diff !== null && diff < 0 && !isDoneT;
    const isDueToday = diff === 0 && !isDoneT;
    const isSel = selectMode && selTaskIds.has(t.id);

    // ── Check button ───────────────────────────────────────────
    // In select mode: checkbox to pick the task for bulk ops.
    // In normal mode: clicking cycles status todo → in_progress → done → todo.
    const checkBtn = selectMode
        ? `<button class="task-check-btn" onclick="event.stopPropagation();toggleTaskSel('${t.id}')"
                style="width:20px;height:20px;border-radius:4px;flex-shrink:0;cursor:pointer;
                       border:2px solid ${PRI_COLOR[t.priority || "low"]};
                       background:${isSel ? PRI_COLOR[t.priority || "low"] : "transparent"};
                       display:flex;align-items:center;justify-content:center;">
                ${isSel ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
           </button>`
        : `<button class="task-check-btn" title="Cycle status" onclick="cycleTaskStatus('${t.id}')"
                style="width:20px;height:20px;border-radius:50%;flex-shrink:0;cursor:pointer;
                       border:2px solid ${PRI_COLOR[t.priority || "low"]};
                       background:${isDoneT ? PRI_COLOR[t.priority || "low"] : status === "in_progress" ? "rgba(139,92,246,0.18)" : "transparent"};
                       display:flex;align-items:center;justify-content:center;">
                ${
                    isDoneT
                        ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>'
                        : status === "in_progress"
                          ? '<svg viewBox="0 0 12 12" fill="none" stroke="#8b5cf6" stroke-width="2.5" width="8" height="8"><polyline points="1 5 5 9 11 1"/></svg>'
                          : ""
                }
           </button>`;

    const bodyClick = selectMode
        ? `onclick="event.stopPropagation();toggleTaskSel('${t.id}')" style="flex:1;min-width:0;cursor:pointer;"`
        : `onclick="openTaskModal('${t.id}')" style="flex:1;min-width:0;cursor:pointer;"`;

    // ── Status pill (FR-4.4) ────────────────────────────────────
    const statusPill = `<span style="padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;
                                      background:${STATUS_BG[status]};color:${STATUS_COLOR[status]};">
                            ${STATUS_ICON[status]} ${STATUS_LABEL[status]}
                        </span>`;

    // ── Priority pill (unchanged) ───────────────────────────────
    const priPill = `<span style="padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;
                                   background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]};">
                         ${PRI_ICON[t.priority || "low"]} ${t.priority || "low"}
                     </span>`;

    // ── Subject tag chip (FR-4.5) ────────────────────────────────
    // If the user has a color set for this subject in user_subject_colors,
    // subjectColor() returns it; otherwise falls back to a neutral chip.
    const labelChip = t.label
        ? `<span style="padding:2px 7px;border-radius:99px;font-size:11px;
                         background:var(--border);color:var(--text-secondary);
                         display:inline-flex;align-items:center;gap:4px;">
                <span style="width:7px;height:7px;border-radius:50%;
                              background:${getSubjectColor(t.label)};display:inline-block;"></span>
                ${esc(t.label)}
           </span>`
        : "";

    // ── Due date display ────────────────────────────────────────
    const dueDisplay = due
        ? `<span style="font-size:11px;color:${overdue ? "#dc2626" : isDueToday ? "#d97706" : "var(--text-secondary)"};">
               ${overdue ? "⚠️ Overdue" : isDueToday ? "⏰ Due today" : `📅 ${due.toLocaleDateString("en-US", { month: "short", day: "numeric" })}`}
               ${t.due_time ? " · " + fmt12(t.due_time) : ""}
           </span>`
        : "";

    const notesSnippet = t.notes
        ? `<span style="font-size:11px;color:var(--text-secondary);white-space:nowrap;
                          overflow:hidden;text-overflow:ellipsis;max-width:200px;">
               📝 ${esc(t.notes)}
           </span>`
        : "";

    // ── Subtask count badge (FR-4.1) ────────────────────────────
    // t.subtask_count is set by taskLoad() if the Supabase query joins subtasks.
    // Falls back to 0 so the row doesn't break if the join isn't ready yet.
    const subCount = t.subtask_count || 0;
    const subBadge =
        subCount > 0
            ? `<span style="font-size:11px;color:var(--text-secondary);margin-left:4px;">
               ↳ ${subCount} subtask${subCount !== 1 ? "s" : ""}
           </span>`
            : "";

    return `<div class="task-item ${isDoneT && !selectMode ? "task-done" : ""} ${isSel ? "item-sel" : ""}"
        data-id="${t.id}"
        style="${isSel ? "background:rgba(26,95,122,0.07);border-radius:8px;" : ""}">
        ${checkBtn}
        <div class="task-body" ${bodyClick}>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:600;color:var(--text-primary);
                              ${isDoneT && !selectMode ? "text-decoration:line-through;opacity:.45;" : ""}">
                    ${esc(t.title)}
                </span>
                ${statusPill}
                ${priPill}
                ${labelChip}
            </div>
            <div style="display:flex;gap:10px;margin-top:3px;flex-wrap:wrap;align-items:center;">
                ${dueDisplay}
                ${notesSnippet}
                ${subBadge}
            </div>
        </div>
        <button onclick="promptDeleteTask('${t.id}')"
            style="background:none;border:none;cursor:pointer;color:var(--text-light);
                   padding:4px;border-radius:4px;flex-shrink:0;" title="Delete task">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6M14 11v6"/>
                <path d="M9 6V4h6v2"/>
            </svg>
        </button>
    </div>`;
}

// ── Subject color lookup (FR-4.5 + FR-3.3) ─────────────────────
// Returns the hex color for a subject name if it was saved to
// user_subject_colors in Supabase. Falls back to a neutral gray.
// _subjectColorCache is populated by loadSubjectColors() on boot.
let _subjectColorCache = {};

async function loadSubjectColors() {
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/user_subject_colors?user_id=eq.${UID}&select=subject_name,color_hex`,
            {
                headers: {
                    apikey: SB_ANON,
                    Authorization: `Bearer ${SB_ANON}`,
                },
            },
        );
        if (!res.ok) return;
        const rows = await res.json();
        rows.forEach((r) => {
            _subjectColorCache[r.subject_name] = r.color_hex;
        });
    } catch (_) {
        // Non-fatal — color dots just show gray if table doesn't exist yet
    }
}

function getSubjectColor(subjectName) {
    return _subjectColorCache[subjectName] || "#9ca3af";
}

// Load colors on boot (after DOMContentLoaded fires taskLoad)
document.addEventListener("DOMContentLoaded", () => loadSubjectColors());

function setLabelFilter(label) {
    taskLabelFilter = label;
    renderTaskManager();
}

// ═══════════════════════════════════════════════════════════════════
// STATUS CYCLE  (FR-4.4)
// Clicking the circle button on a task row cycles:
//   todo → in_progress → done → todo
// ═══════════════════════════════════════════════════════════════════
const STATUS_CYCLE = { todo: "in_progress", in_progress: "done", done: "todo" };

async function cycleTaskStatus(id) {
    const t = allTasks.find((x) => x.id === id);
    if (!t) return;
    const next = STATUS_CYCLE[t.status || "todo"];
    try {
        await taskUpdate(id, { status: next });
        const i = allTasks.findIndex((x) => x.id === id);
        if (i !== -1) allTasks[i] = { ...allTasks[i], status: next };
        renderTaskManager();
    } catch (err) {
        alert("Failed to update status: " + err.message);
    }
}

// ═══════════════════════════════════════════════════════════════════
// SELECT MODE  (unchanged from original)
// ═══════════════════════════════════════════════════════════════════
function toggleSelectMode() {
    selectMode = !selectMode;
    const btn = document.getElementById("btnSelectMode");
    if (selectMode) {
        document.body.classList.add("select-mode");
        btn.classList.add("active");
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            style="width:15px;height:15px"><polyline points="20 6 9 17 4 12"/></svg> Selecting…`;
        renderTaskManager();
    } else {
        exitSelectMode();
    }
}

function exitSelectMode() {
    selectMode = false;
    selTaskIds.clear();
    document.body.classList.remove("select-mode");
    const btn = document.getElementById("btnSelectMode");
    if (btn) {
        btn.classList.remove("active");
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            style="width:15px;height:15px">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg> Select`;
    }
    updateBulkBar();
    renderTaskManager();
}

function toggleTaskSel(id) {
    selTaskIds.has(id) ? selTaskIds.delete(id) : selTaskIds.add(id);
    updateBulkBar();
    renderTaskManager();
}

function updateBulkBar() {
    const n = selTaskIds.size;
    const countEl = document.getElementById("bulkCount");
    if (countEl)
        countEl.textContent = `${n} task${n !== 1 ? "s" : ""} selected`;
    const bar = document.getElementById("bulkBar");
    if (bar) bar.classList.toggle("visible", n > 0 && selectMode);
}

// ═══════════════════════════════════════════════════════════════════
// SUBTASKS  (FR-4.1)
// ═══════════════════════════════════════════════════════════════════

// Called by the "+ Add subtask" button in the modal (blade wires onclick)
function addSubtask() {
    const tempId = "new-" + Date.now();
    _subtasks.push({ id: tempId, title: "", status: "todo", isNew: true });
    renderSubtaskList();
    // Focus the new input
    const inputs = document.querySelectorAll(".subtask-title");
    if (inputs.length) inputs[inputs.length - 1].focus();
}

// Called by the ✕ button on each subtask row
function removeSubtask(btn) {
    const row = btn.closest(".subtask-row");
    const rowId = row?.dataset.id;
    if (!rowId) return;
    const sub = _subtasks.find((s) => s.id === rowId);
    if (!sub) return;
    if (sub.isNew) {
        // Brand-new (not saved yet) — just remove from array
        _subtasks = _subtasks.filter((s) => s.id !== rowId);
    } else {
        // Already in DB — mark for deletion on save
        sub.isDeleted = true;
    }
    renderSubtaskList();
}

// Renders the subtask list inside the modal (#subtaskList)
function renderSubtaskList() {
    const el = document.getElementById("subtaskList");
    if (!el) return;

    const visible = _subtasks.filter((s) => !s.isDeleted);
    if (!visible.length) {
        el.innerHTML = `<div style="font-size:12px;color:var(--text-light);padding:4px 0;">
            No subtasks yet. Click "+ Add subtask" to add one.
        </div>`;
        return;
    }

    el.innerHTML = visible
        .map(
            (s) => `
        <div class="subtask-row" data-id="${s.id}"
            style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" class="subtask-check"
                ${s.status === "done" ? "checked" : ""}
                onchange="toggleSubtaskDone('${s.id}', this.checked)"
                style="width:15px;height:15px;flex-shrink:0;cursor:pointer;
                       accent-color:var(--primary,#1a5f7a);">
            <input type="text" class="subtask-title form-input"
                value="${esc(s.title)}"
                placeholder="Subtask…"
                oninput="updateSubtaskTitle('${s.id}', this.value)"
                style="flex:1;padding:6px 10px;font-size:13px;
                       ${s.status === "done" ? "text-decoration:line-through;opacity:.5;" : ""}">
            <button onclick="removeSubtask(this)"
                style="background:none;border:none;cursor:pointer;
                       color:var(--text-light);padding:4px;border-radius:4px;
                       flex-shrink:0;font-size:14px;line-height:1;" title="Remove subtask">
                ✕
            </button>
        </div>
    `,
        )
        .join("");
}

function toggleSubtaskDone(id, checked) {
    const sub = _subtasks.find((s) => s.id === id);
    if (sub) sub.status = checked ? "done" : "todo";
    // Re-render just the input style without a full re-render (avoids losing focus)
    const row = document.querySelector(`.subtask-row[data-id="${id}"]`);
    const input = row?.querySelector(".subtask-title");
    if (input) {
        input.style.textDecoration = checked ? "line-through" : "";
        input.style.opacity = checked ? "0.5" : "1";
    }
}

function updateSubtaskTitle(id, value) {
    const sub = _subtasks.find((s) => s.id === id);
    if (sub) sub.title = value;
}

// Loads subtasks from Supabase for a given task ID
async function loadSubtasks(taskId) {
    _subtasks = [];
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/subtasks?task_id=eq.${taskId}&order=created_at.asc&select=*`,
            {
                headers: {
                    apikey: SB_ANON,
                    Authorization: `Bearer ${SB_ANON}`,
                },
            },
        );
        if (!res.ok) return;
        _subtasks = await res.json();
    } catch (_) {
        // Non-fatal — subtasks table may not exist yet
    }
}

// Saves all subtasks after the parent task is saved
async function saveSubtasks(taskId) {
    const toDelete = _subtasks.filter((s) => s.isDeleted && !s.isNew);
    const toInsert = _subtasks.filter(
        (s) => s.isNew && !s.isDeleted && s.title.trim(),
    );
    const toUpdate = _subtasks.filter((s) => !s.isNew && !s.isDeleted);

    const headers = {
        "Content-Type": "application/json",
        apikey: SB_ANON,
        Authorization: `Bearer ${SB_ANON}`,
        Prefer: "return=minimal",
    };

    // Delete removed subtasks
    await Promise.all(
        toDelete.map((s) =>
            fetch(`${SB_URL}/rest/v1/subtasks?id=eq.${s.id}`, {
                method: "DELETE",
                headers,
            }),
        ),
    );

    // Insert new subtasks
    if (toInsert.length) {
        await fetch(`${SB_URL}/rest/v1/subtasks`, {
            method: "POST",
            headers: { ...headers, Prefer: "return=minimal" },
            body: JSON.stringify(
                toInsert.map((s) => ({
                    task_id: taskId,
                    title: s.title.trim(),
                    status: s.status || "todo",
                })),
            ),
        });
    }

    // Update existing subtasks (title + status may have changed)
    await Promise.all(
        toUpdate.map((s) =>
            fetch(`${SB_URL}/rest/v1/subtasks?id=eq.${s.id}`, {
                method: "PATCH",
                headers,
                body: JSON.stringify({ title: s.title, status: s.status }),
            }),
        ),
    );
}

// ═══════════════════════════════════════════════════════════════════
// CRUD ACTIONS
// ═══════════════════════════════════════════════════════════════════

// FR-4.4: saveTask now reads status from the status radio group
async function saveTask() {
    const title = document.getElementById("taskTitle").value.trim();
    if (!title) return alert("Please enter a task title.");

    const pri =
        document.querySelector('input[name="taskPriority"]:checked')?.value ||
        "low";

    // FR-4.4: read the selected status
    const status =
        document.querySelector('input[name="taskStatus"]:checked')?.value ||
        "todo";

    const data = {
        title,
        priority: pri,
        status,
        due_date: document.getElementById("taskDueDate").value || null,
        due_time: document.getElementById("taskDueTime").value || null,
        label: document.getElementById("taskLabel").value.trim() || null,
        notes: document.getElementById("taskNotes").value.trim() || null,
    };

    const btn = document.getElementById("btnSaveTask");
    btn.textContent = "Saving…";
    btn.disabled = true;

    try {
        let savedId = editTaskId;

        if (editTaskId) {
            await taskUpdate(editTaskId, data);
            const i = allTasks.findIndex((x) => x.id === editTaskId);
            if (i !== -1) allTasks[i] = { ...allTasks[i], ...data };
        } else {
            const row = await taskInsert(data);
            allTasks.unshift(row);
            savedId = row.id;
        }

        // FR-4.1: save subtasks after parent task is saved
        await saveSubtasks(savedId);

        // Update subtask_count on the in-memory task so the row badge updates
        const doneSubtasks = _subtasks.filter((s) => !s.isDeleted);
        const ti = allTasks.findIndex((x) => x.id === savedId);
        if (ti !== -1) allTasks[ti].subtask_count = doneSubtasks.length;

        closeTaskModal();
        renderTaskManager();
    } catch (err) {
        alert("Save failed: " + err.message);
    } finally {
        btn.textContent = "Save Task";
        btn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════
// TASK MODAL
// ═══════════════════════════════════════════════════════════════════
async function openTaskModal(id = null) {
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

        // Priority
        const pri = t.priority || "low";
        document.querySelectorAll(".priority-option").forEach((l) => {
            const active = l.dataset.p === pri;
            l.classList.toggle("sel", active);
            l.querySelector("input").checked = active;
        });

        // FR-4.4: Status — call blade's setTaskStatus() if available, else do it ourselves
        const status = t.status || "todo";
        if (typeof setTaskStatus === "function") {
            setTaskStatus(status);
        } else {
            document.querySelectorAll(".status-option").forEach((l) => {
                const active = l.dataset.s === status;
                l.classList.toggle("sel", active);
                l.querySelector("input").checked = active;
            });
        }

        document.getElementById("btnDelTask").style.display = "block";

        // FR-4.1: Load subtasks from Supabase
        await loadSubtasks(id);
        renderSubtaskList();
    } else {
        document.getElementById("taskModalTitle").textContent = "Add Task";
        document.getElementById("btnDelTask").style.display = "none";
        _subtasks = [];
        renderSubtaskList();
    }

    document.getElementById("taskModal").classList.add("open");
}

function closeTaskModal() {
    document.getElementById("taskModal").classList.remove("open");
    editTaskId = null;
    _subtasks = [];
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

    // Reset priority to 'low'
    document.querySelectorAll(".priority-option").forEach((l) => {
        l.classList.toggle("sel", l.dataset.p === "low");
        l.querySelector("input").checked = l.dataset.p === "low";
    });

    // FR-4.4: Reset status to 'todo'
    document.querySelectorAll(".status-option").forEach((l) => {
        l.classList.toggle("sel", l.dataset.s === "todo");
        l.querySelector("input").checked = l.dataset.s === "todo";
    });
    if (typeof setTaskStatus === "function") setTaskStatus("todo");

    // FR-4.1: Clear subtask list
    _subtasks = [];
    const subList = document.getElementById("subtaskList");
    if (subList) subList.innerHTML = "";

    document.getElementById("btnDelTask").style.display = "none";
}

// ═══════════════════════════════════════════════════════════════════
// DELETE  (unchanged from original except uses status not completed_at)
// ═══════════════════════════════════════════════════════════════════
function promptDeleteTask(id) {
    const t = allTasks.find((x) => x.id === id);
    pendDel = { mode: "task", ids: [id] };
    document.getElementById("confirmTitle").textContent = "Delete Task";
    document.getElementById("confirmBody").innerHTML =
        `Are you sure you want to delete <strong>"${esc(t?.title || "this task")}"</strong>? This cannot be undone.`;
    closeTaskModal();
    document.getElementById("confirmModal").classList.add("open");
}

function promptBulkDelete() {
    const n = selTaskIds.size;
    if (!n) return;

    pendDel = {
        mode: "bulk",
        ids: new Set(),
        taskIds: new Set([...selTaskIds]),
    };

    let listHtml = `<div style="border:1px solid var(--border,rgba(0,0,0,.12));border-radius:8px;overflow:hidden;margin:12px 0;">`;
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
            style="display:flex;align-items:center;gap:10px;padding:9px 12px;
                   border-bottom:1px solid var(--border,rgba(0,0,0,.08));cursor:pointer;background:var(--bg-main);">
            <div id="cichk-task-${id}"
                style="width:16px;height:16px;border-radius:4px;flex-shrink:0;
                       border:1.5px solid ${priColor};
                       display:flex;align-items:center;justify-content:center;background:${priColor};">
                <svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5"
                    width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>
            </div>
            <div style="width:8px;height:8px;border-radius:50%;background:${priColor};flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);
                             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    ${PRI_ICON[t.priority || "low"]} ${esc(t.title)}
                </div>
                <div style="font-size:11px;color:var(--text-light);">
                    Task · ${t.priority || "low"} · ${STATUS_LABEL[t.status || "todo"]} · ${dl}
                </div>
            </div>
        </div>`;
    });
    listHtml += `</div>`;

    document.getElementById("confirmTitle").textContent =
        `Delete ${n} task${n !== 1 ? "s" : ""}`;
    document.getElementById("confirmBody").innerHTML =
        `<span>Uncheck anything you want to keep.</span>${listHtml}`;
    updateConfirmCount();
    document.getElementById("confirmModal").classList.add("open");
}

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
        chk.innerHTML = `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5"
            width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>`;
        document.getElementById(`ci-task-${id}`).style.opacity = "1";
    }
    updateConfirmCount();
}

function updateConfirmCount() {
    const n = pendDel?.taskIds?.size || 0;
    const btn = document.getElementById("btnConfirmDel");
    if (btn) {
        btn.textContent = n > 0 ? `Yes, delete ${n}` : "Nothing to delete";
        btn.disabled = n === 0;
    }
    const title = document.getElementById("confirmTitle");
    if (title) title.textContent = `Delete ${n} task${n !== 1 ? "s" : ""}`;
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
            await taskDelete(pendDel.ids[0]);
            allTasks = allTasks.filter((t) => t.id !== pendDel.ids[0]);
        } else {
            if (pendDel.taskIds?.size > 0) {
                await Promise.all(
                    [...pendDel.taskIds].map((id) => taskDelete(id)),
                );
                const taskSet = pendDel.taskIds;
                allTasks = allTasks.filter((t) => !taskSet.has(t.id));
            }
            exitSelectMode();
        }
        closeConfirm();
        renderTaskManager();
    } catch (err) {
        alert("Delete failed: " + err.message);
    } finally {
        btn.textContent = "Yes, Delete";
        btn.disabled = false;
    }
}
