// tasks.js
// ═══════════════════════════════════════════════════════════════════
// Tasks page — requires studyhub-core.js loaded first
// (studyhub-core provides: allTasks, taskLoad, taskInsert, taskUpdate,
//  taskDelete, selectMode, selTaskIds, pendDel, editTaskId,
//  taskFilterPri, taskFilterStatus, taskSort, taskLabelFilter,
//  PRI_COLOR, PRI_BG, PRI_ICON, esc, fmt12)
// ═══════════════════════════════════════════════════════════════════

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

    // Priority selector
    document.querySelectorAll(".priority-option").forEach((lbl) => {
        lbl.addEventListener("click", () => {
            document
                .querySelectorAll(".priority-option")
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
// RENDER
// ═══════════════════════════════════════════════════════════════════
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

    // Label chips
    const labels = [
        ...new Set(allTasks.filter((t) => t.label).map((t) => t.label)),
    ];
    document.getElementById("taskLabelChips").innerHTML =
        labels
            .map(
                (l) =>
                    `<button onclick="setLabelFilter('${esc(l)}')" style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);font-size:12px;cursor:pointer;
                    background:${taskLabelFilter === l ? "var(--primary,#1a5f7a)" : "var(--bg-main)"};
                    color:${taskLabelFilter === l ? "white" : "var(--text-secondary)"};">${esc(l)}</button>`,
            )
            .join("") +
        (taskLabelFilter
            ? `<button onclick="setLabelFilter(null)" style="padding:4px 10px;border-radius:99px;border:1px solid var(--border);font-size:12px;cursor:pointer;background:transparent;color:var(--text-secondary);">✕ Clear</button>`
            : "");

    // Apply filters
    let tasks = [...allTasks];
    if (taskFilterStatus === "active")
        tasks = tasks.filter((t) => !t.completed_at);
    if (taskFilterStatus === "completed")
        tasks = tasks.filter((t) => !!t.completed_at);
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
        <button onclick="promptDeleteTask('${t.id}')" style="background:none;border:none;cursor:pointer;color:var(--text-light);padding:4px;border-radius:4px;flex-shrink:0;" title="Delete task">
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

// ═══════════════════════════════════════════════════════════════════
// SELECT MODE  (tasks-only version — no calendar panels to refresh)
// ═══════════════════════════════════════════════════════════════════
function toggleSelectMode() {
    selectMode = !selectMode;
    const btn = document.getElementById("btnSelectMode");
    if (selectMode) {
        document.body.classList.add("select-mode");
        btn.classList.add("active");
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="20 6 9 17 4 12"/></svg> Selecting…`;
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
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Select`;
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
// CRUD ACTIONS
// ═══════════════════════════════════════════════════════════════════
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
    } catch (err) {
        alert("Failed: " + err.message);
    }
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

// ═══════════════════════════════════════════════════════════════════
// DELETE
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
        chk.innerHTML = `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>`;
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
            // Single delete
            await taskDelete(pendDel.ids[0]);
            allTasks = allTasks.filter((t) => t.id !== pendDel.ids[0]);
        } else {
            // Bulk delete
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
