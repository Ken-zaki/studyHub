// tasks.js
// ═══════════════════════════════════════════════════════════════════
// Tasks page — requires studyhub-core.js loaded first
// ═══════════════════════════════════════════════════════════════════

// ── Module-level state ──────────────────────────────────────────
let _subtasks = [];
let _allSubjects = [];
let _editSubjectId = null;
let _subjectColorCache = {};
let _expandedTaskIds = new Set(); // tracks which task rows are expanded

const SUBJECT_PALETTE = [
    "#ef4444",
    "#f97316",
    "#f59e0b",
    "#84cc16",
    "#22c55e",
    "#14b8a6",
    "#06b6d4",
    "#3b82f6",
    "#6366f1",
    "#8b5cf6",
    "#d946ef",
    "#ec4899",
    "#64748b",
    "#78716c",
    "#0ea5e9",
    "#10b981",
];

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async () => {
    wireTaskUI();
    wireSubjectUI();

    try {
        await Promise.all([taskLoad(), loadSubjects()]);
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
// WIRE TASK UI
// ═══════════════════════════════════════════════════════════════════
function wireTaskUI() {
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
        setStatusTab(e.target.value);
    };

    document.getElementById("btnConfirmClose").onclick = closeConfirm;
    document.getElementById("btnConfirmCancel").onclick = closeConfirm;
    document.getElementById("btnConfirmDel").onclick = execDelete;

    const sel = document.getElementById("taskLabelSelect");
    if (sel) sel.addEventListener("change", updateTaskLabelDot);

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
// WIRE SUBJECT UI
// ═══════════════════════════════════════════════════════════════════
function wireSubjectUI() {
    document
        .getElementById("btnAddSubjectTag")
        ?.addEventListener("click", () => openSubjectModal());
    document
        .getElementById("btnSubjectModalClose")
        ?.addEventListener("click", closeSubjectModal);
    document
        .getElementById("btnSubjectModalCancel")
        ?.addEventListener("click", closeSubjectModal);
    document
        .getElementById("btnSaveSubject")
        ?.addEventListener("click", saveSubject);
    document
        .getElementById("btnDelSubject")
        ?.addEventListener("click", () => promptDeleteSubject(_editSubjectId));

    document.getElementById("subjectName")?.addEventListener("input", (e) => {
        const el = document.getElementById("subjectPreviewName");
        if (el) el.textContent = e.target.value || "Subject";
    });

    buildColorPicker();

    document.getElementById("subjectModal")?.addEventListener("click", (e) => {
        if (e.target === document.getElementById("subjectModal"))
            closeSubjectModal();
    });
    document.getElementById("taskModal")?.addEventListener("click", (e) => {
        if (e.target === document.getElementById("taskModal")) closeTaskModal();
    });
}

// ── Colour palette ──────────────────────────────────────────────
function buildColorPicker() {
    const picker = document.getElementById("subjectColorPicker");
    if (!picker) return;
    picker.innerHTML = SUBJECT_PALETTE.map(
        (
            hex,
        ) => `<button type="button" data-color="${hex}" onclick="selectSubjectColor('${hex}')"
            title="${hex}"
            style="width:28px;height:28px;border-radius:50%;border:3px solid transparent;
                   background:${hex};cursor:pointer;transition:.15s;outline:none;"></button>`,
    ).join("");
    selectSubjectColor(SUBJECT_PALETTE[5]);
}

function selectSubjectColor(hex) {
    document.getElementById("subjectColorValue").value = hex;
    document.querySelectorAll("#subjectColorPicker button").forEach((btn) => {
        const active = btn.dataset.color === hex;
        btn.style.border = active
            ? "3px solid var(--text-primary,#1e293b)"
            : "3px solid transparent";
        btn.style.transform = active ? "scale(1.18)" : "scale(1)";
    });
    const dot = document.getElementById("subjectPreviewDot");
    const chip = document.getElementById("subjectPreviewChip");
    if (dot) dot.style.background = hex;
    if (chip) {
        chip.style.background = hex + "22";
        chip.style.color = hex;
    }
}

// ═══════════════════════════════════════════════════════════════════
// SUBJECT CRUD
// ═══════════════════════════════════════════════════════════════════
async function loadSubjects() {
    try {
        _allSubjects = await sbReq(
            `user_subject_colors?user_id=eq.${UID}&order=subject_name.asc&select=*`,
            { headers: hdrs(true) },
        );
        if (!Array.isArray(_allSubjects)) _allSubjects = [];
    } catch (err) {
        console.error("loadSubjects:", err);
        _allSubjects = [];
    }
    rebuildColorCache();
    renderSubjectTagsCard();
    loadSubjectsIntoSelect();
}

async function insertSubject(name, colorHex) {
    const result = await sbReq("user_subject_colors", {
        method: "POST",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify({
            user_id: UID,
            subject_name: name,
            color_hex: colorHex,
        }),
    });
    return Array.isArray(result) ? result[0] : result;
}

async function updateSubject(id, name, colorHex) {
    await sbReq(`user_subject_colors?id=eq.${id}`, {
        method: "PATCH",
        headers: { ...hdrs(true), Prefer: "return=minimal" },
        body: JSON.stringify({ subject_name: name, color_hex: colorHex }),
    });
}

async function deleteSubject(id) {
    await sbReq(`user_subject_colors?id=eq.${id}`, {
        method: "DELETE",
        headers: hdrs(true),
    });
}

function rebuildColorCache() {
    _subjectColorCache = {};
    _allSubjects.forEach((s) => {
        _subjectColorCache[s.subject_name] = s.color_hex;
    });
}

// ═══════════════════════════════════════════════════════════════════
// SUBJECT TAGS CARD
// ═══════════════════════════════════════════════════════════════════
function renderSubjectTagsCard() {
    const el = document.getElementById("subjectTagsList");
    if (!el) return;

    if (!_allSubjects.length) {
        el.innerHTML = `<div style="text-align:center;padding:18px 0;">
            <div style="font-size:28px;margin-bottom:6px;">🏷️</div>
            <div style="font-size:12px;color:var(--text-light);">No subjects yet.<br>Add one to get started.</div>
        </div>`;
        return;
    }

    el.innerHTML = _allSubjects
        .map(
            (s) => `
        <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;
                    border-radius:10px;border:1px solid var(--border);background:var(--bg-main);transition:.15s;"
             onmouseover="this.style.borderColor='${s.color_hex}66'"
             onmouseout="this.style.borderColor='var(--border)'">
            <span style="width:11px;height:11px;border-radius:50%;flex-shrink:0;
                          background:${s.color_hex};box-shadow:0 0 0 2px ${s.color_hex}33;"></span>
            <span style="flex:1;font-size:13px;font-weight:600;color:var(--text-primary);
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(s.subject_name)}</span>
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;
                          background:${s.color_hex}22;color:${s.color_hex};flex-shrink:0;">tag</span>
            <button onclick="openSubjectModal('${s.id}')" title="Edit subject"
                style="padding:3px;background:none;border:none;cursor:pointer;
                       color:var(--text-light);border-radius:5px;flex-shrink:0;
                       display:flex;align-items:center;transition:.15s;"
                onmouseover="this.style.color='var(--primary,#1a5f7a)'"
                onmouseout="this.style.color='var(--text-light)'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </button>
        </div>`,
        )
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// SUBJECT SELECT IN TASK MODAL
// ═══════════════════════════════════════════════════════════════════
function loadSubjectsIntoSelect(selectedValue = "") {
    const sel = document.getElementById("taskLabelSelect");
    if (!sel) return;
    sel.innerHTML =
        `<option value="">— None —</option>` +
        _allSubjects
            .map(
                (s) =>
                    `<option value="${esc(s.subject_name)}" data-color="${s.color_hex}"
             ${s.subject_name === selectedValue ? "selected" : ""}>${esc(s.subject_name)}</option>`,
            )
            .join("");
    updateTaskLabelDot();
}

function updateTaskLabelDot() {
    const sel = document.getElementById("taskLabelSelect");
    const dot = document.getElementById("taskLabelDot");
    if (!sel || !dot) return;
    const color = sel.options[sel.selectedIndex]?.dataset?.color;
    if (color && sel.value) {
        dot.style.background = color;
        dot.style.display = "inline-block";
        sel.style.paddingLeft = "28px";
    } else {
        dot.style.display = "none";
        sel.style.paddingLeft = "12px";
    }
}

// ═══════════════════════════════════════════════════════════════════
// SUBJECT MODAL
// ═══════════════════════════════════════════════════════════════════
function openSubjectModal(id = null) {
    _editSubjectId = id;
    const nameEl = document.getElementById("subjectName");
    const btnDel = document.getElementById("btnDelSubject");
    const titleEl = document.getElementById("subjectModalTitle");

    nameEl.value = "";
    buildColorPicker();

    if (id) {
        const s = _allSubjects.find((x) => x.id === id);
        if (!s) return;
        if (titleEl) titleEl.textContent = "Edit Subject";
        nameEl.value = s.subject_name;
        selectSubjectColor(s.color_hex);
        const pname = document.getElementById("subjectPreviewName");
        if (pname) pname.textContent = s.subject_name;
        if (btnDel) btnDel.style.display = "block";
    } else {
        if (titleEl) titleEl.textContent = "Add Subject";
        if (btnDel) btnDel.style.display = "none";
        const pname = document.getElementById("subjectPreviewName");
        if (pname) pname.textContent = "Subject";
    }

    document.getElementById("subjectModal").classList.add("open");
    setTimeout(() => nameEl.focus(), 80);
}

function closeSubjectModal() {
    document.getElementById("subjectModal").classList.remove("open");
    _editSubjectId = null;
}

async function saveSubject() {
    const name = document.getElementById("subjectName").value.trim();
    const color =
        document.getElementById("subjectColorValue").value || "#9ca3af";

    if (!name) {
        document.getElementById("subjectName").focus();
        return;
    }

    const btn = document.getElementById("btnSaveSubject");
    btn.textContent = "Saving…";
    btn.disabled = true;

    try {
        if (_editSubjectId) {
            await updateSubject(_editSubjectId, name, color);
            const i = _allSubjects.findIndex((s) => s.id === _editSubjectId);
            if (i !== -1)
                _allSubjects[i] = {
                    ..._allSubjects[i],
                    subject_name: name,
                    color_hex: color,
                };
        } else {
            const row = await insertSubject(name, color);
            _allSubjects.push(row);
            _allSubjects.sort((a, b) =>
                a.subject_name.localeCompare(b.subject_name),
            );
        }
        rebuildColorCache();
        closeSubjectModal();
        renderSubjectTagsCard();
        loadSubjectsIntoSelect();
        renderTaskManager();
    } catch (err) {
        alert("Save failed: " + err.message);
    } finally {
        btn.textContent = "Save Subject";
        btn.disabled = false;
    }
}

function promptDeleteSubject(id) {
    const s = _allSubjects.find((x) => x.id === id);
    if (!s) return;
    if (
        !confirm(
            `Delete subject "${s.subject_name}"?\n\nExisting tasks won't be deleted but will lose this subject tag.`,
        )
    )
        return;
    execDeleteSubject(id);
}

async function execDeleteSubject(id) {
    try {
        await deleteSubject(id);
        _allSubjects = _allSubjects.filter((s) => s.id !== id);
        rebuildColorCache();
        closeSubjectModal();
        renderSubjectTagsCard();
        loadSubjectsIntoSelect();
        renderTaskManager();
    } catch (err) {
        alert("Delete failed: " + err.message);
    }
}

// ═══════════════════════════════════════════════════════════════════
// STATUS TAB STRIP
// ═══════════════════════════════════════════════════════════════════
function setStatusTab(status) {
    const dropdown = document.getElementById("taskFilterStatus");
    if (dropdown) dropdown.value = status;

    document.querySelectorAll(".status-tab").forEach((btn) => {
        const active = btn.dataset.status === status;
        btn.classList.toggle("active", active);
        btn.style.color = active
            ? "var(--primary,#1a5f7a)"
            : "var(--text-secondary)";
        btn.style.borderBottom = active
            ? "2px solid var(--primary,#1a5f7a)"
            : "2px solid transparent";
    });

    taskFilterStatus = status;
    renderTaskManager();
}

function filterTasks() {
    const dropdown = document.getElementById("taskFilterStatus");
    if (dropdown) taskFilterStatus = dropdown.value;
    renderTaskManager();
}

// ═══════════════════════════════════════════════════════════════════
// STATUS DISPLAY HELPERS
// ═══════════════════════════════════════════════════════════════════
const STATUS_LABEL = {
    "to-do": "To-do",
    "in-progress": "In Progress",
    done: "Done",
};
const STATUS_BG = {
    "to-do": "rgba(107,114,128,0.12)",
    "in-progress": "rgba(139,92,246,0.12)",
    done: "rgba(42,157,143,0.12)",
};
const STATUS_COLOR = {
    "to-do": "#6b7280",
    "in-progress": "#8b5cf6",
    done: "#2a9d8f",
};
const STATUS_ICON = { "to-do": "📋", "in-progress": "🔄", done: "✅" };

function isDone(t) {
    return (t.status || "to-do") === "done";
}

// ═══════════════════════════════════════════════════════════════════
// RENDER TASK MANAGER
// ═══════════════════════════════════════════════════════════════════
function renderTaskManager() {
    const total = allTasks.length;
    const doneCount = allTasks.filter(isDone).length;

    document.getElementById("taskCountBadge").textContent = allTasks.filter(
        (t) => (t.status || "to-do") !== "done",
    ).length;

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

    updateSidebarStats();

    const labels = [
        ...new Set(allTasks.filter((t) => t.label).map((t) => t.label)),
    ];
    document.getElementById("taskLabelChips").innerHTML =
        labels
            .map(
                (l) =>
                    `<button onclick="setLabelFilter('${esc(l)}')"
             style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);
                    font-size:12px;cursor:pointer;font-family:inherit;
                    background:${taskLabelFilter === l ? "var(--primary,#1a5f7a)" : "var(--bg-main)"};
                    color:${taskLabelFilter === l ? "white" : "var(--text-secondary)"};">
                ${esc(l)}
            </button>`,
            )
            .join("") +
        (taskLabelFilter
            ? `<button onclick="setLabelFilter(null)"
               style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);
                      font-size:12px;cursor:pointer;font-family:inherit;
                      background:transparent;color:var(--text-secondary);">✕ Clear</button>`
            : "");

    let tasks = [...allTasks];
    if (taskFilterStatus === "to-do")
        tasks = tasks.filter((t) => (t.status || "to-do") === "to-do");
    if (taskFilterStatus === "in-progress")
        tasks = tasks.filter((t) => (t.status || "to-do") === "in-progress");
    if (taskFilterStatus === "done")
        tasks = tasks.filter((t) => (t.status || "to-do") === "done");
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
        el.innerHTML = `<div class="state-box">${
            total === 0
                ? "No tasks yet. Add your first task! ✅"
                : "No tasks match your filters."
        }</div>`;
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
                    `<div style="font-size:11px;font-weight:600;color:var(--text-light);
                         text-transform:uppercase;letter-spacing:.05em;padding:10px 0 4px;">${date}</div>` +
                    items.map(taskItemHTML).join(""),
            )
            .join("");
    } else {
        el.innerHTML = tasks.map(taskItemHTML).join("");
    }
}

function updateSidebarStats() {
    const todo = allTasks.filter(
        (t) => (t.status || "to-do") === "to-do",
    ).length;
    const inProgress = allTasks.filter(
        (t) => (t.status || "to-do") === "in-progress",
    ).length;
    const done = allTasks.filter(
        (t) => (t.status || "to-do") === "done",
    ).length;

    const eltodo = document.getElementById("statTodo");
    const elIP = document.getElementById("statInProgress");
    const elDone = document.getElementById("statDone");
    if (eltodo) eltodo.textContent = todo;
    if (elIP) elIP.textContent = inProgress;
    if (elDone) elDone.textContent = done;
}

// ═══════════════════════════════════════════════════════════════════
// TASK ROW HTML
// ═══════════════════════════════════════════════════════════════════
function taskItemHTML(t) {
    const status = t.status || "to-do";
    const isDoneT = status === "done";
    const isExp = _expandedTaskIds.has(t.id);
    const subCount = t.subtask_count || 0;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = t.due_date ? new Date(t.due_date + "T00:00:00") : null;
    const diff = due ? Math.ceil((due - today) / 86400000) : null;
    const overdue = diff !== null && diff < 0 && !isDoneT;
    const isDueToday = diff === 0 && !isDoneT;
    const isSel = selectMode && selTaskIds.has(t.id);

    // ── circle check button (cycles status when not in select mode) ──
    const checkBtn = selectMode
        ? `<button class="task-check-btn" onclick="event.stopPropagation();toggleTaskSel('${t.id}')"
                style="width:20px;height:20px;border-radius:4px;flex-shrink:0;cursor:pointer;
                       border:2px solid ${PRI_COLOR[t.priority || "low"]};
                       background:${isSel ? PRI_COLOR[t.priority || "low"] : "transparent"};
                       display:flex;align-items:center;justify-content:center;">
                ${isSel ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
           </button>`
        : `<button class="task-check-btn" title="Cycle status" onclick="event.stopPropagation();cycleTaskStatus('${t.id}')"
                style="width:20px;height:20px;border-radius:50%;flex-shrink:0;cursor:pointer;
                       border:2px solid ${PRI_COLOR[t.priority || "low"]};
                       background:${isDoneT ? PRI_COLOR[t.priority || "low"] : status === "in-progress" ? "rgba(139,92,246,0.18)" : "transparent"};
                       display:flex;align-items:center;justify-content:center;">
                ${
                    isDoneT
                        ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>'
                        : status === "in-progress"
                          ? '<svg viewBox="0 0 12 12" fill="none" stroke="#8b5cf6" stroke-width="2.5" width="8" height="8"><polyline points="1 5 5 9 11 1"/></svg>'
                          : ""
                }
           </button>`;

    // ── inline status dropdown ──
    const statusDropdown = !selectMode
        ? `
        <select onchange="changeTaskStatus('${t.id}', this.value)"
                onclick="event.stopPropagation()"
                style="padding:3px 8px;border-radius:99px;font-size:11px;font-weight:600;
                       border:none;cursor:pointer;outline:none;font-family:inherit;
                       background:${STATUS_BG[status]};color:${STATUS_COLOR[status]};">
            <option value="to-do"       ${status === "to-do" ? "selected" : ""}>📋 To-do</option>
            <option value="in-progress" ${status === "in-progress" ? "selected" : ""}>🔄 In Progress</option>
            <option value="done"        ${status === "done" ? "selected" : ""}>✅ Done</option>
        </select>`
        : "";

    const priPill = `<span style="padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;
                                   background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]};">
                         ${PRI_ICON[t.priority || "low"]} ${t.priority || "low"}
                     </span>`;

    const subjectColor = getSubjectColor(t.label);
    const labelChip = t.label
        ? `<span style="padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;
                         background:${subjectColor}22;color:${subjectColor};
                         display:inline-flex;align-items:center;gap:4px;">
                <span style="width:7px;height:7px;border-radius:50%;background:${subjectColor};display:inline-block;"></span>
                ${esc(t.label)}
           </span>`
        : "";

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

    // ── expand toggle — only shown if there are subtasks ──
    const expandBtn =
        subCount > 0 && !selectMode
            ? `<button onclick="event.stopPropagation();toggleExpandTask('${t.id}')"
                title="${isExp ? "Collapse subtasks" : "Show subtasks"}"
                style="display:flex;align-items:center;gap:4px;padding:3px 8px;
                       border-radius:99px;border:1px solid var(--border);background:var(--bg-main);
                       font-size:11px;font-weight:600;color:var(--text-secondary);cursor:pointer;
                       flex-shrink:0;transition:.15s;"
                onmouseover="this.style.borderColor='var(--primary,#1a5f7a)';this.style.color='var(--primary,#1a5f7a)'"
                onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     width="11" height="11"
                     style="transition:.2s;transform:${isExp ? "rotate(180deg)" : "rotate(0deg)"}">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
                ${subCount} subtask${subCount !== 1 ? "s" : ""}
            </button>`
            : subCount > 0
              ? `<span style="font-size:11px;color:var(--text-secondary);">↳ ${subCount} subtask${subCount !== 1 ? "s" : ""}</span>`
              : "";

    // ── subtask panel (only rendered when expanded) ──
    const subtaskPanel = isExp
        ? `<div class="subtask-panel" data-task-id="${t.id}"
                style="margin-left:32px;margin-top:6px;padding:10px 12px;
                       border-radius:10px;background:var(--bg-main);
                       border:1px solid var(--border);display:flex;flex-direction:column;gap:6px;">
                <div id="subtask-panel-inner-${t.id}">
                    <div style="font-size:12px;color:var(--text-light);padding:4px 0;">Loading subtasks…</div>
                </div>
            </div>`
        : "";

    return `<div class="task-item ${isDoneT && !selectMode ? "task-done" : ""} ${isSel ? "item-sel" : ""}"
        data-id="${t.id}"
        style="flex-direction:column;align-items:stretch;gap:0;
               ${isSel ? "background:rgba(26,95,122,0.07);border-radius:8px;" : ""}">
        <div style="display:flex;align-items:center;gap:8px;">
            ${checkBtn}
            <div style="flex:1;min-width:0;cursor:pointer;" onclick="${selectMode ? `event.stopPropagation();toggleTaskSel('${t.id}')` : `openTaskModal('${t.id}')`}">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span style="font-size:13px;font-weight:600;color:var(--text-primary);
                                  ${isDoneT && !selectMode ? "text-decoration:line-through;opacity:.45;" : ""}">
                        ${esc(t.title)}
                    </span>
                    ${priPill}
                    ${labelChip}
                </div>
                <div style="display:flex;gap:10px;margin-top:3px;flex-wrap:wrap;align-items:center;">
                    ${dueDisplay}
                    ${notesSnippet}
                </div>
            </div>
            ${expandBtn}
            ${statusDropdown}
            <button onclick="promptDeleteTask('${t.id}')"
                style="background:none;border:none;cursor:pointer;color:var(--text-light);
                       padding:4px;border-radius:4px;flex-shrink:0;display:flex;align-items:center;"
                title="Delete task">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14H6L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                </svg>
            </button>
        </div>
        ${subtaskPanel}
    </div>`;
}

function getSubjectColor(subjectName) {
    return _subjectColorCache[subjectName] || "#9ca3af";
}
function setLabelFilter(label) {
    taskLabelFilter = label;
    renderTaskManager();
}

// ═══════════════════════════════════════════════════════════════════
// SUBTASK EXPAND / COLLAPSE
// ═══════════════════════════════════════════════════════════════════
async function toggleExpandTask(taskId) {
    if (_expandedTaskIds.has(taskId)) {
        _expandedTaskIds.delete(taskId);
        renderTaskManager();
        return;
    }

    _expandedTaskIds.add(taskId);
    renderTaskManager(); // renders panel with "Loading…"

    try {
        const subs = await sbReq(
            `subtasks?task_id=eq.${taskId}&order=created_at.asc&select=*`,
            { headers: hdrs(true) },
        );
        renderSubtaskPanel(taskId, Array.isArray(subs) ? subs : []);
    } catch (err) {
        const el = document.getElementById(`subtask-panel-inner-${taskId}`);
        if (el)
            el.innerHTML = `<div style="font-size:12px;color:#dc2626;">Failed to load subtasks.</div>`;
    }
}

function renderSubtaskPanel(taskId, subtasks) {
    const el = document.getElementById(`subtask-panel-inner-${taskId}`);
    if (!el) return;

    if (!subtasks.length) {
        el.innerHTML = `<div style="font-size:12px;color:var(--text-light);padding:2px 0;">No subtasks yet. Click Edit to add some.</div>`;
        return;
    }

    el.innerHTML = subtasks
        .map((s) => {
            const done = s.status === "done";
            const sColor = STATUS_COLOR[s.status || "to-do"];
            const sBg = STATUS_BG[s.status || "to-do"];

            return `<div style="display:flex;align-items:center;gap:8px;padding:5px 0;
                             border-bottom:1px solid var(--border);"
                     data-subtask-id="${s.id}">
            <!-- circle check -->
            <button onclick="cycleSubtaskStatus('${taskId}', '${s.id}', this)"
                title="Cycle status"
                style="width:16px;height:16px;border-radius:50%;flex-shrink:0;cursor:pointer;
                       border:2px solid ${done ? "#2a9d8f" : "#9ca3af"};
                       background:${done ? "#2a9d8f" : "transparent"};
                       display:flex;align-items:center;justify-content:center;padding:0;">
                ${done ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="8" height="8"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
            </button>
            <!-- title -->
            <span style="flex:1;font-size:12px;font-weight:500;color:var(--text-primary);
                          ${done ? "text-decoration:line-through;opacity:.45;" : ""}">
                ${esc(s.title)}
            </span>
            <!-- status dropdown -->
            <select onchange="changeSubtaskStatus('${taskId}', '${s.id}', this.value)"
                    style="padding:2px 6px;border-radius:99px;font-size:11px;font-weight:600;
                           border:none;cursor:pointer;outline:none;font-family:inherit;
                           background:${sBg};color:${sColor};">
                <option value="to-do"       ${(s.status || "to-do") === "to-do" ? "selected" : ""}>📋 To-do</option>
                <option value="in-progress" ${(s.status || "to-do") === "in-progress" ? "selected" : ""}>🔄 In Progress</option>
                <option value="done"        ${(s.status || "to-do") === "done" ? "selected" : ""}>✅ Done</option>
            </select>
        </div>`;
        })
        .join("");
}

// cycle subtask status via the circle button (to-do → in-progress → done → to-do)
async function cycleSubtaskStatus(taskId, subtaskId, btn) {
    const CYCLE = {
        "to-do": "in-progress",
        "in-progress": "done",
        done: "to-do",
    };
    const row = btn.closest("[data-subtask-id]");
    const sel = row?.querySelector("select");
    const cur = sel?.value || "to-do";
    const next = CYCLE[cur];
    await changeSubtaskStatus(taskId, subtaskId, next);
}

async function changeSubtaskStatus(taskId, subtaskId, newStatus) {
    try {
        await sbReq(`subtasks?id=eq.${subtaskId}`, {
            method: "PATCH",
            headers: { ...hdrs(true), Prefer: "return=minimal" },
            body: JSON.stringify({ status: newStatus }),
        });

        // reload the panel
        const subs = await sbReq(
            `subtasks?task_id=eq.${taskId}&order=created_at.asc&select=*`,
            { headers: hdrs(true) },
        );
        renderSubtaskPanel(taskId, Array.isArray(subs) ? subs : []);
    } catch (err) {
        alert("Failed to update subtask: " + err.message);
    }
}

// ═══════════════════════════════════════════════════════════════════
// STATUS CYCLE on task row circle button
// ═══════════════════════════════════════════════════════════════════
const STATUS_CYCLE = {
    "to-do": "in-progress",
    "in-progress": "done",
    done: "to-do",
};

async function cycleTaskStatus(id) {
    const t = allTasks.find((x) => x.id === id);
    if (!t) return;
    const next = STATUS_CYCLE[t.status || "to-do"];
    await changeTaskStatus(id, next);
}

async function changeTaskStatus(id, newStatus) {
    try {
        await sbReq(`${TASK_TABLE}?id=eq.${id}`, {
            method: "PATCH",
            headers: { ...hdrs(true), Prefer: "return=minimal" },
            body: JSON.stringify({ status: newStatus }),
        });
        const i = allTasks.findIndex((x) => x.id === id);
        if (i !== -1) allTasks[i] = { ...allTasks[i], status: newStatus };
        renderTaskManager();
    } catch (err) {
        alert("Failed to update status: " + err.message);
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
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            style="width:15px;height:15px;flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg> Selecting…`;
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
            style="width:15px;height:15px;flex-shrink:0">
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
// SUBTASKS IN MODAL  (add / edit)
// ═══════════════════════════════════════════════════════════════════
function addSubtask() {
    const tempId = "new-" + Date.now();
    _subtasks.push({ id: tempId, title: "", status: "to-do", isNew: true });
    renderSubtaskList();
    const inputs = document.querySelectorAll(".subtask-title");
    if (inputs.length) inputs[inputs.length - 1].focus();
}

function removeSubtask(btn) {
    const row = btn.closest(".subtask-row");
    const rowId = row?.dataset.id;
    if (!rowId) return;
    const sub = _subtasks.find((s) => s.id === rowId);
    if (!sub) return;
    sub.isNew
        ? (_subtasks = _subtasks.filter((s) => s.id !== rowId))
        : (sub.isDeleted = true);
    renderSubtaskList();
}

function renderSubtaskList() {
    const el = document.getElementById("subtaskList");
    if (!el) return;
    const visible = _subtasks.filter((s) => !s.isDeleted);
    if (!visible.length) {
        el.innerHTML = `<div style="font-size:12px;color:var(--text-light);padding:4px 0;">
            No subtasks yet. Click "+ Add subtask" to add one.</div>`;
        return;
    }
    el.innerHTML = visible
        .map(
            (s) => `
        <div class="subtask-row" data-id="${s.id}">
            <input type="checkbox" class="subtask-check"
                ${s.status === "done" ? "checked" : ""}
                onchange="toggleSubtaskDone('${s.id}', this.checked)">
            <input type="text" class="subtask-title form-input"
                value="${esc(s.title)}"
                placeholder="Subtask…"
                oninput="updateSubtaskTitle('${s.id}', this.value)"
                style="${s.status === "done" ? "text-decoration:line-through;opacity:.5;" : ""}">
            <button onclick="removeSubtask(this)" title="Remove subtask">✕</button>
        </div>`,
        )
        .join("");
}

function toggleSubtaskDone(id, checked) {
    const sub = _subtasks.find((s) => s.id === id);
    if (sub) sub.status = checked ? "done" : "to-do";
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

async function loadSubtasks(taskId) {
    _subtasks = [];
    try {
        const result = await sbReq(
            `subtasks?task_id=eq.${taskId}&order=created_at.asc&select=*`,
            { headers: hdrs(true) },
        );
        _subtasks = Array.isArray(result) ? result : [];
    } catch (_) {
        /* non-fatal */
    }
}

async function saveSubtasks(taskId) {
    const toDelete = _subtasks.filter((s) => s.isDeleted && !s.isNew);
    const toInsert = _subtasks.filter(
        (s) => s.isNew && !s.isDeleted && s.title.trim(),
    );
    const toUpdate = _subtasks.filter((s) => !s.isNew && !s.isDeleted);

    await Promise.all(
        toDelete.map((s) =>
            sbReq(`subtasks?id=eq.${s.id}`, {
                method: "DELETE",
                headers: hdrs(true),
            }),
        ),
    );

    if (toInsert.length) {
        await sbReq("subtasks", {
            method: "POST",
            headers: { ...hdrs(true), Prefer: "return=minimal" },
            body: JSON.stringify(
                toInsert.map((s) => ({
                    task_id: taskId,
                    title: s.title.trim(),
                    status: s.status || "to-do",
                })),
            ),
        });
    }

    await Promise.all(
        toUpdate.map((s) =>
            sbReq(`subtasks?id=eq.${s.id}`, {
                method: "PATCH",
                headers: { ...hdrs(true), Prefer: "return=minimal" },
                body: JSON.stringify({ title: s.title, status: s.status }),
            }),
        ),
    );
}

// ═══════════════════════════════════════════════════════════════════
// SAVE TASK  ← main fix: status NOT sent (DB default handles it),
//              and we use Prefer: return=minimal for update to avoid
//              the empty-array destructure crash
// ═══════════════════════════════════════════════════════════════════
async function saveTask() {
    const title = document.getElementById("taskTitle").value.trim();
    if (!title) return alert("Please enter a task title.");

    const pri =
        document.querySelector('input[name="taskPriority"]:checked')?.value ||
        "low";
    const label = document.getElementById("taskLabelSelect")?.value || null;

    // NOTE: status is intentionally omitted here.
    // - New tasks get the DB column default ("to-do").
    // - Existing tasks keep whatever status is already stored;
    //   status is changed via the inline dropdown on the task row.
    const data = {
        title,
        priority: pri,
        due_date: document.getElementById("taskDueDate").value || null,
        due_time: document.getElementById("taskDueTime").value || null,
        label: label || null,
        notes: document.getElementById("taskNotes").value.trim() || null,
    };

    const btn = document.getElementById("btnSaveTask");
    btn.textContent = "Saving…";
    btn.disabled = true;

    try {
        let savedId = editTaskId;

        if (editTaskId) {
            // PATCH — use return=minimal to avoid empty-array crash
            await sbReq(`${TASK_TABLE}?id=eq.${editTaskId}`, {
                method: "PATCH",
                headers: { ...hdrs(true), Prefer: "return=minimal" },
                body: JSON.stringify(data),
            });
            const i = allTasks.findIndex((x) => x.id === editTaskId);
            if (i !== -1) allTasks[i] = { ...allTasks[i], ...data };
        } else {
            // INSERT — return=representation so we get the new row with its id
            const result = await sbReq(TASK_TABLE, {
                method: "POST",
                headers: { ...hdrs(true), Prefer: "return=representation" },
                body: JSON.stringify({ ...data, user_id: UID }),
            });
            const row = Array.isArray(result) ? result[0] : result;
            if (!row || !row.id)
                throw new Error(
                    "Insert succeeded but returned no row. Check RLS policies.",
                );
            allTasks.unshift(row);
            savedId = row.id;
        }

        // save subtasks only after we have a confirmed savedId
        if (savedId) {
            await saveSubtasks(savedId);
            const ti = allTasks.findIndex((x) => x.id === savedId);
            if (ti !== -1) {
                allTasks[ti].subtask_count = _subtasks.filter(
                    (s) => !s.isDeleted,
                ).length;
            }
        }

        closeTaskModal();
        renderTaskManager();
    } catch (err) {
        console.error("saveTask error:", err);
        alert("Save failed: " + err.message);
    } finally {
        btn.textContent = "Save Task";
        btn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════
// TASK MODAL  (open / close / reset)
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
        document.getElementById("taskNotes").value = t.notes || "";

        loadSubjectsIntoSelect(t.label || "");

        const pri = t.priority || "low";
        document.querySelectorAll(".priority-option").forEach((l) => {
            const active = l.dataset.p === pri;
            l.classList.toggle("sel", active);
            l.querySelector("input").checked = active;
        });

        document.getElementById("btnDelTask").style.display = "block";

        await loadSubtasks(id);
        renderSubtaskList();
    } else {
        document.getElementById("taskModalTitle").textContent = "Add Task";
        document.getElementById("btnDelTask").style.display = "none";
        loadSubjectsIntoSelect("");
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
    ["taskTitle", "taskDueDate", "taskDueTime", "taskNotes"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });

    const sel = document.getElementById("taskLabelSelect");
    if (sel) {
        sel.value = "";
        updateTaskLabelDot();
    }

    document.querySelectorAll(".priority-option").forEach((l) => {
        l.classList.toggle("sel", l.dataset.p === "low");
        l.querySelector("input").checked = l.dataset.p === "low";
    });

    _subtasks = [];
    const subList = document.getElementById("subtaskList");
    if (subList) subList.innerHTML = "";

    document.getElementById("btnDelTask").style.display = "none";
}

// ═══════════════════════════════════════════════════════════════════
// DELETE TASK
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
                <svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7">
                    <polyline points="1 4 4 7 9 1"/></svg>
            </div>
            <div style="width:8px;height:8px;border-radius:50%;background:${priColor};flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);
                             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    ${PRI_ICON[t.priority || "low"]} ${esc(t.title)}
                </div>
                <div style="font-size:11px;color:var(--text-light);">
                    Task · ${t.priority || "low"} · ${STATUS_LABEL[t.status || "to-do"]} · ${dl}
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
        chk.innerHTML = `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7">
            <polyline points="1 4 4 7 9 1"/></svg>`;
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
