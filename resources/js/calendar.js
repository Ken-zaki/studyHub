// calendar.js
// Depends on studyhub-core.js being loaded first (provides all state,
// constants, DB helpers, utils, and expandAll).
// ═══════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════

// Subject cache — populated once on boot, refreshed on add/delete
let userSubjects = [];

const PRESET_COLORS = [
    "#1a5f7a",
    "#0f766e",
    "#7c3aed",
    "#dc2626",
    "#d97706",
    "#16a34a",
    "#0284c7",
    "#db2777",
    "#9333ea",
    "#ea580c",
];

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async () => {
    wireStaticUI();
    setTimeout(wireInjectedButtons, 0);
    try {
        await Promise.all([dbLoad(), taskLoad(), loadSubjects()]);
        expandAll();
    } catch (err) {
        const el = document.getElementById("calDays");
        if (el)
            el.innerHTML = `<div class="state-box err" style="grid-column:1/-1">⚠️ ${esc(err.message)}</div>`;
        return;
    }
    redraw();
    initNotifications?.();
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

    // Event modal
    document.getElementById("btnModalClose").onclick = closeEvModal;
    document.getElementById("btnModalCancel").onclick = closeEvModal;
    document.getElementById("btnSaveEv").onclick = saveEv;
    document.getElementById("btnDelEv").onclick = () =>
        promptSingleDelete(editId);
    document.getElementById("evRecur").onchange = (e) =>
        (document.getElementById("recurOpts").style.display = e.target.checked
            ? "block"
            : "none");
    document
        .querySelectorAll(".rday")
        .forEach((b) => (b.onclick = () => b.classList.toggle("sel")));
    document.getElementById("evCat").onchange = () => updateModalFields();

    // Task modal
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

    // Confirm modal
    document.getElementById("btnConfirmClose").onclick = closeConfirm;
    document.getElementById("btnConfirmCancel").onclick = closeConfirm;
    document.getElementById("btnConfirmDel").onclick = execDelete;

    // Reminder toggle
    const reminderChk = document.getElementById("evReminder");
    if (reminderChk) {
        reminderChk.onchange = () => {
            document.getElementById("reminderOpts").style.display =
                reminderChk.checked ? "block" : "none";
        };
    }

    // Close popover on outside click
    document.addEventListener("click", (e) => {
        if (!document.getElementById("dayPopover").classList.contains("open"))
            return;
        if (!e.target.closest(".cal-day") && !e.target.closest("#dayPopover"))
            closePopover();
    });

    document
        .getElementById("allEventsWidget")
        ?.addEventListener("input", (e) => {
            if (e.target.id === "allEvSearch") renderAllEvents();
        });
}

function wireInjectedButtons() {
    const b = (id) => document.getElementById(id);
    if (b("btnAdd")) b("btnAdd").onclick = () => openEvModal();
    if (b("btnSelectMode")) b("btnSelectMode").onclick = toggleSelectMode;
    if (b("btnBulkCancel")) b("btnBulkCancel").onclick = exitSelectMode;
    if (b("btnBulkDelete")) b("btnBulkDelete").onclick = promptBulkDelete;
}

// ═══════════════════════════════════════════════════════════════════
// NAVIGATE & REDRAW
// ═══════════════════════════════════════════════════════════════════
function navigate(dir) {
    if (curView === "month") curDate.setMonth(curDate.getMonth() + dir);
    else if (curView === "week") curDate.setDate(curDate.getDate() + dir * 7);
    else curDate.setDate(curDate.getDate() + dir);
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
    renderFilters(); // category checkboxes → #calFilters
    renderSubjects(); // subject color list → #mySubjectsList
    renderUpcoming();
    renderAllEvents();
}

function updateTitle() {
    const el = document.getElementById("calTitle");
    if (curView === "month") {
        el.textContent = new Date(
            curDate.getFullYear(),
            curDate.getMonth(),
            1,
        ).toLocaleDateString("en-US", { month: "long", year: "numeric" });
    } else if (curView === "week") {
        const { start, end } = weekRange(curDate);
        const o = { month: "short", day: "numeric" };
        el.textContent = `${start.toLocaleDateString("en-US", o)} – ${end.toLocaleDateString("en-US", { ...o, year: "numeric" })}`;
    } else {
        el.textContent = curDate.toLocaleDateString("en-US", {
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
    const first = new Date(y, m, 1).getDay(),
        dim = new Date(y, m + 1, 0).getDate();
    const prev = new Date(y, m, 0).getDate(),
        total = Math.ceil((first + dim) / 7) * 7;
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
        } else dn = i - first + 1;

        const cell = new Date(y, m + mo, dn),
            ds = fd(cell);
        const isToday = sameDay(cell, today),
            isOther = mo !== 0;
        const vis = evForDate(ds).filter((e) => filters[e.category]);
        const dayTasks = tasksForDate(ds).filter((t) => !t.completed_at);

        const div = document.createElement("div");
        div.className =
            "cal-day" +
            (isOther ? " other-month" : "") +
            (isToday ? " today" : "") +
            (isDaySel(ds) ? " day-sel" : "");
        div.dataset.date = ds;
        div.innerHTML = `<div class="day-check"></div><div class="day-num">${dn}</div>
            <div class="day-events">
                ${vis
                    .slice(0, 3)
                    .map(
                        (e) =>
                            `<div class="day-event-chip chip-${e.category}" title="${esc(e.title)}">${esc(e.title)}</div>`,
                    )
                    .join("")}
                ${vis.length > 3 ? `<div class="day-event-chip chip-event">+${vis.length - 3} more</div>` : ""}
                ${
                    dayTasks.length
                        ? `<div style="display:flex;gap:2px;flex-wrap:wrap;margin-top:2px;">
                        ${dayTasks
                            .slice(0, 4)
                            .map(
                                (t) =>
                                    `<div style="width:6px;height:6px;border-radius:50%;background:${PRI_COLOR[t.priority || "low"]}" title="${esc(t.title)}"></div>`,
                            )
                            .join("")}
                       </div>`
                        : ""
                }
            </div>`;

        if (!isOther) {
            div.addEventListener("click", (e) => {
                e.stopPropagation();
                selectMode
                    ? openSelectPopover(div, ds, cell)
                    : openPopover(div, ds, cell);
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

function isDaySel(ds) {
    return (
        evForDate(ds).some((e) => selIds.has(e.id)) ||
        tasksForDate(ds).some((t) => selTaskIds.has(t.id))
    );
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

    const counts = { class: 0, group: 0, event: 0, exam: 0, deadline: 0 };
    days.forEach((d) =>
        evForDate(fd(d))
            .filter((e) => filters[e.category])
            .forEach((e) => {
                if (counts[e.category] !== undefined) counts[e.category]++;
            }),
    );
    document.getElementById("weekSummaryBar").innerHTML = Object.entries(counts)
        .filter(([, n]) => n > 0)
        .map(
            ([cat, n]) =>
                `<div style="display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:13px;font-weight:500;border:1px solid var(--border);background:white;color:var(--text-secondary)">
            <div style="width:8px;height:8px;border-radius:50%;background:${CC[cat]}"></div>${n} ${cat === "group" ? "study group" : cat}${n !== 1 ? "s" : ""}</div>`,
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
    const hasUntimedT = days.some((d) => tasksForDate(fd(d)).length > 0);

    let html = `<div style="display:flex;flex-direction:column;border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <div style="display:grid;grid-template-columns:52px repeat(7,1fr);border-bottom:2px solid var(--border);background:var(--bg-main);position:sticky;top:0;z-index:10;">
        <div style="border-right:1px solid var(--border);"></div>`;
    days.forEach((d) => {
        const isTod = sameDay(d, today);
        html += `<div style="padding:10px 6px;text-align:center;border-right:1px solid var(--border);background:${isTod ? "rgba(26,95,122,0.06)" : "transparent"};">
            <div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;">${dayNames[d.getDay()]}</div>
            <div style="font-size:20px;font-weight:600;margin-top:2px;color:${isTod ? "var(--primary)" : "var(--text-primary)"};">${d.getDate()}</div></div>`;
    });
    html += `</div>`;

    if (hasUntimed || hasUntimedT) {
        html += `<div style="display:grid;grid-template-columns:52px repeat(7,1fr);border-bottom:1px solid var(--border);background:var(--bg-main);min-height:32px;">
            <div style="border-right:1px solid var(--border);padding:6px 4px;font-size:10px;color:var(--text-light);text-align:right;white-space:nowrap;">all day</div>`;
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

    html += `<div id="weekScrollBody" style="overflow-y:auto;max-height:580px;"><div style="display:grid;grid-template-columns:52px repeat(7,1fr);position:relative;">
        <div style="position:relative;height:${TOTAL_H}px;border-right:1px solid var(--border);background:var(--bg-main);">`;
    for (let h = 1; h < 24; h++) {
        const l = h < 12 ? `${h} AM` : h === 12 ? "12 PM" : `${h - 12} PM`;
        html += `<div style="position:absolute;top:${h * HOUR_H}px;right:6px;font-size:10px;color:var(--text-light);transform:translateY(-50%);white-space:nowrap;">${l}</div>`;
    }
    html += `</div>`;

    days.forEach((d) => {
        const ds = fd(d),
            isTod = sameDay(d, today);
        const allEvs = evForDate(ds).filter(
            (e) => filters[e.category] && e.event_time,
        );
        const timedT = allTasks.filter(
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
        const placed = [];
        sorted.forEach((ev) => {
            const [sh, sm] = ev.event_time.split(":").map(Number);
            const startMin = sh * 60 + sm,
                topPx = (startMin / 60) * HOUR_H;
            let heightPx = HOUR_H;
            if (ev.end_time) {
                const [eh, em] = ev.end_time.split(":").map(Number);
                heightPx = Math.max(
                    28,
                    ((eh * 60 + em - startMin) / 60) * HOUR_H,
                );
            }
            const endMin = startMin + (heightPx / HOUR_H) * 60;
            const over = placed.filter(
                (p) => startMin < p.endMin && endMin > p.startMin,
            );
            const used = new Set(over.map((p) => p.col));
            let col = 0;
            while (used.has(col)) col++;
            placed.push({ ev, topPx, heightPx, startMin, endMin, col });
        });
        placed.forEach((item) => {
            const over = placed.filter(
                (p) => item.startMin < p.endMin && item.endMin > p.startMin,
            );
            item.totalCols = Math.max(...over.map((p) => p.col)) + 1;
        });
        placed.forEach(({ ev, topPx, heightPx, col, totalCols }) => {
            const colW = `calc((100% - 2px) / ${totalCols})`,
                colL = `calc(1px + (100% - 2px) / ${totalCols} * ${col})`;
            const minH = Math.max(28, heightPx),
                tl = fmt12(ev.event_time),
                el2 = ev.end_time ? ` – ${fmt12(ev.end_time)}` : "";
            html += `<div style="position:absolute;top:${topPx}px;left:${colL};width:${colW};height:${minH}px;z-index:2;box-sizing:border-box;background:${CB[ev.category]};border-left:3px solid ${CC[ev.category]};border-radius:4px;padding:4px 6px;cursor:pointer;overflow:hidden;"
                onclick="event.stopPropagation();openEvModal('${ev.id}')" title="${esc(ev.title)} · ${tl}${el2}">
                <div style="font-size:10px;font-weight:700;color:${CC[ev.category]};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ev.title)}</div>
                ${minH >= 44 ? `<div style="font-size:9px;color:${CC[ev.category]};opacity:.75;margin-top:2px;">${tl}${el2}</div>` : ""}</div>`;
        });
        timedT.forEach((t) => {
            const [sh, sm] = t.due_time.split(":").map(Number),
                topPx = ((sh * 60 + sm) / 60) * HOUR_H;
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
        now = new Date(),
        ds = fd(curDate);
    const HOUR_H = 64,
        TOTAL_H = 24 * HOUR_H,
        isTod = sameDay(curDate, today);
    const dayEvs = evForDate(ds).filter((e) => filters[e.category]);
    const timedEvs = dayEvs
        .filter((e) => e.event_time)
        .sort((a, b) => a.event_time.localeCompare(b.event_time));
    const untimedEvs = dayEvs.filter((e) => !e.event_time);
    const dayTasks = allTasks.filter((t) => t.due_date === ds);
    const timedT = dayTasks.filter((t) => t.due_time && !t.completed_at);
    const untimedT = dayTasks.filter((t) => !t.due_time);

    function getBounds(time, endTime) {
        const [sh, sm] = time.split(":").map(Number),
            startMin = sh * 60 + sm;
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

    let timedHtml = "";
    placed.forEach(({ ev, task, topPx, heightPx, col, totalCols, isTask }) => {
        const colW = `calc((100% - 4px) / ${totalCols})`,
            colL = `calc(2px + (100% - 4px) / ${totalCols} * ${col})`,
            minH = Math.max(36, heightPx);
        if (isTask) {
            const t = task;
            timedHtml += `<div onclick="event.stopPropagation();openTaskModal('${t.id}')" style="position:absolute;top:${topPx}px;left:${colL};width:${colW};height:${minH}px;z-index:3;box-sizing:border-box;background:${PRI_BG[t.priority || "low"]};border-left:4px solid ${PRI_COLOR[t.priority || "low"]};border-radius:6px;padding:6px 8px;cursor:pointer;overflow:hidden;">
                <div style="font-size:12px;font-weight:700;color:${PRI_COLOR[t.priority || "low"]};">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
                ${minH >= 52 ? `<div style="font-size:11px;color:${PRI_COLOR[t.priority || "low"]};opacity:.7;margin-top:3px;">${fmt12(t.due_time)}</div>` : ""}</div>`;
        } else {
            timedHtml += `<div onclick="event.stopPropagation();openEvModal('${ev.id}')" style="position:absolute;top:${topPx}px;left:${colL};width:${colW};height:${minH}px;z-index:2;box-sizing:border-box;background:${CB[ev.category]};border-left:4px solid ${CC[ev.category]};border-radius:6px;padding:6px 8px;cursor:pointer;overflow:hidden;">
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
            ${PRI_ICON[t.priority || "low"]} ${esc(t.title)}${t.completed_at ? `<span style="font-size:11px;opacity:.6;">✓ Done</span>` : ""}</div>`;
        });
        alldayHtml += `</div>`;
    }

    let hourLines = "",
        nowLine = "";
    for (let h = 0; h < 24; h++) {
        hourLines += `<div style="position:absolute;top:${h * HOUR_H}px;left:0;right:0;border-top:1px solid ${h === 0 ? "transparent" : "var(--border)"};pointer-events:none;"></div>
        <div style="position:absolute;top:${h * HOUR_H + HOUR_H / 2}px;left:0;right:0;border-top:1px dashed rgba(0,0,0,0.05);pointer-events:none;"></div>`;
    }
    if (isTod) {
        const pct = (now.getHours() * 60 + now.getMinutes()) / 60;
        nowLine = `<div style="position:absolute;top:${pct * HOUR_H}px;left:0;right:0;border-top:2px solid var(--accent);z-index:4;pointer-events:none;"><div style="position:absolute;left:-1px;top:-4px;width:8px;height:8px;border-radius:50%;background:var(--accent);"></div></div>`;
    }

    const summaryHtml = `<div style="background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Summary</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px;">📅 ${dayEvs.length} event${dayEvs.length !== 1 ? "s" : ""}</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px;">✅ ${dayTasks.length} task${dayTasks.length !== 1 ? "s" : ""}</div>
        ${dayTasks.length ? `<div style="font-size:13px;color:#0f766e;">✓ ${dayTasks.filter((t) => t.completed_at).length} completed</div>` : ""}
    </div>`;

    let tasksHtml = "";
    if (dayTasks.length) {
        tasksHtml = `<div style="background:var(--bg-main);border:1px solid var(--border);border-radius:10px;padding:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Tasks</div>`;
        dayTasks.forEach((t) => {
            tasksHtml += `<div onclick="openTaskModal('${t.id}')" style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border);cursor:pointer;">
            <div onclick="event.stopPropagation();toggleTaskDone('${t.id}')" style="width:16px;height:16px;border-radius:50%;border:2px solid ${PRI_COLOR[t.priority || "low"]};flex-shrink:0;display:flex;align-items:center;justify-content:center;cursor:pointer;background:${t.completed_at ? PRI_COLOR[t.priority || "low"] : "transparent"}">
                ${t.completed_at ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;${t.completed_at ? "text-decoration:line-through;opacity:.5;" : ""}">${esc(t.title)}</div>
                ${t.due_time ? `<div style="font-size:11px;color:var(--text-secondary);">${fmt12(t.due_time)}</div>` : ""}
            </div></div>`;
        });
        tasksHtml += `</div>`;
    }

    document.getElementById("dayGrid").innerHTML =
        `<div style="display:flex;gap:16px;padding:4px 0;">
        <div style="flex:1;min-width:0;">${alldayHtml}
            <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
                <div id="dayScrollBody" style="overflow-y:auto;max-height:580px;">
                    <div style="display:flex;position:relative;">
                        <div style="width:52px;flex-shrink:0;position:relative;height:${TOTAL_H}px;background:var(--bg-main);border-right:1px solid var(--border);">
                            ${Array.from({ length: 23 }, (_, h) => {
                                const hr = h + 1;
                                return `<div style="position:absolute;top:${hr * HOUR_H}px;right:6px;font-size:10px;color:var(--text-light);transform:translateY(-50%);white-space:nowrap;">${hr < 12 ? `${hr} AM` : hr === 12 ? "12 PM" : `${hr - 12} PM`}</div>`;
                            }).join("")}
                        </div>
                        <div style="flex:1;position:relative;height:${TOTAL_H}px;background:${isTod ? "rgba(26,95,122,0.015)" : "white"};" onclick="openEvModal(null,'${ds}')">
                            ${hourLines}${nowLine}${timedHtml}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div style="width:220px;flex-shrink:0;">${summaryHtml}${tasksHtml}</div>
    </div>`;

    const sb = document.getElementById("dayScrollBody");
    if (sb) {
        const st = isTod ? Math.max(0, now.getHours() - 1) : 7;
        setTimeout(() => {
            sb.scrollTop = st * HOUR_H;
        }, 50);
    }
}

// ═══════════════════════════════════════════════════════════════════
// POPOVER
// ═══════════════════════════════════════════════════════════════════
function openPopover(dayEl, ds, cell) {
    closePopover();
    const pop = document.getElementById("dayPopover");
    document.getElementById("popDate").textContent = cell.toLocaleDateString(
        "en-US",
        { weekday: "long", month: "long", day: "numeric" },
    );
    document.getElementById("btnPopAdd").dataset.date = ds;
    const evs = evForDate(ds).filter((e) => filters[e.category]),
        tasks = allTasks.filter((t) => t.due_date === ds);
    let inner = "";
    evs.forEach((ev) => {
        inner += `<div class="popover-event" onclick="openEvModal('${ev.id}')">
        <div class="popover-event-dot" style="background:${CC[ev.category]}"></div>
        <div class="popover-event-info"><div class="popover-event-title">${esc(ev.title)}</div>
        ${ev.event_time ? `<div class="popover-event-time">${fmt12(ev.event_time)}</div>` : ""}</div>
        <button class="popover-ev-del" onclick="event.stopPropagation();promptSingleDelete('${ev.id}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button></div>`;
    });
    tasks.forEach((t) => {
        inner += `<div class="popover-event" onclick="openTaskModal('${t.id}')">
        <div class="popover-event-dot" style="background:${PRI_COLOR[t.priority || "low"]}"></div>
        <div class="popover-event-info"><div class="popover-event-title" style="text-decoration:${t.completed_at ? "line-through" : "none"};opacity:${t.completed_at ? ".5" : "1"}">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
        ${t.due_time ? `<div class="popover-event-time">${fmt12(t.due_time)}</div>` : ""}</div></div>`;
    });
    document.getElementById("popEvents").innerHTML =
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

// ═══════════════════════════════════════════════════════════════════
// SELECT POPOVER
// ═══════════════════════════════════════════════════════════════════
function openSelectPopover(dayEl, ds, cell) {
    closeSelectPopover();
    const evs = evForDate(ds).filter((e) => filters[e.category]),
        tasks = allTasks.filter((t) => t.due_date === ds);
    if (!evs.length && !tasks.length) return;
    const pop = document.createElement("div");
    pop.id = "selDayPop";
    pop.style.cssText = `position:fixed;z-index:9999;background:var(--bg-main,#fff);border:1px solid var(--border,rgba(0,0,0,.18));border-radius:10px;padding:10px 12px;width:230px;box-shadow:0 6px 20px rgba(0,0,0,.12);`;
    const dl = cell.toLocaleDateString("en-US", {
        weekday: "short",
        month: "short",
        day: "numeric",
    });
    let html = `<div style="font-size:11px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);">${dl}</div>`;
    evs.forEach((ev) => {
        const s = selIds.has(ev.id);
        html += `<div onclick="toggleEventSel('${ev.id}');renderSelPopItems()" style="display:flex;align-items:center;gap:8px;padding:6px 2px;border-bottom:1px solid rgba(0,0,0,.05);cursor:pointer;">
            <div style="width:15px;height:15px;border-radius:3px;border:1.5px solid ${CC[ev.category]};flex-shrink:0;display:flex;align-items:center;justify-content:center;background:${s ? CC[ev.category] : "transparent"};"
                class="spop-chk" data-type="ev" data-id="${ev.id}" data-color="${CC[ev.category]}">
                ${s ? `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>` : ""}
            </div>
            <div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ev.title)}</div>
            <div style="font-size:10px;color:var(--text-light);">${ev.event_time ? fmt12(ev.event_time) + " · " : ""}${CL[ev.category]}</div></div></div>`;
    });
    tasks.forEach((t) => {
        const s = selTaskIds.has(t.id);
        html += `<div onclick="toggleTaskSel('${t.id}');renderSelPopItems()" style="display:flex;align-items:center;gap:8px;padding:6px 2px;border-bottom:1px solid rgba(0,0,0,.05);cursor:pointer;">
            <div style="width:15px;height:15px;border-radius:3px;border:1.5px solid ${PRI_COLOR[t.priority || "low"]};flex-shrink:0;display:flex;align-items:center;justify-content:center;background:${s ? PRI_COLOR[t.priority || "low"] : "transparent"};"
                class="spop-chk" data-type="task" data-id="${t.id}" data-color="${PRI_COLOR[t.priority || "low"]}">
                ${s ? `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>` : ""}
            </div>
            <div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div>
            <div style="font-size:10px;color:var(--text-light);">Task · ${t.priority || "low"} priority${t.due_time ? " · " + fmt12(t.due_time) : ""}</div></div></div>`;
    });
    pop.innerHTML = html;
    document.body.appendChild(pop);
    selPopoverEl = pop;
    window.renderSelPopItems = () => {
        pop.querySelectorAll(".spop-chk").forEach((chk) => {
            const sel =
                chk.dataset.type === "ev"
                    ? selIds.has(chk.dataset.id)
                    : selTaskIds.has(chk.dataset.id);
            chk.style.background = sel ? chk.dataset.color : "transparent";
            chk.innerHTML = sel
                ? `<svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg>`
                : "";
        });
        updateBulkBar();
        renderCal();
    };
    const r = dayEl.getBoundingClientRect();
    let left = r.right + 6,
        top = r.top;
    if (left + 240 > window.innerWidth - 12) left = r.left - 240;
    if (top + pop.offsetHeight + 40 > window.innerHeight)
        top = Math.max(8, window.innerHeight - pop.offsetHeight - 16);
    pop.style.left = left + "px";
    pop.style.top = top + "px";
    setTimeout(
        () => document.addEventListener("click", closeSelPopoverOutside),
        0,
    );
}

function closeSelPopoverOutside(e) {
    if (selPopoverEl && !selPopoverEl.contains(e.target)) closeSelectPopover();
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
        renderCal();
        renderAllEvents();
    } else exitSelectMode();
}

function exitSelectMode() {
    selectMode = false;
    selIds.clear();
    selTaskIds.clear();
    document.body.classList.remove("select-mode");
    const btn = document.getElementById("btnSelectMode");
    if (!btn) return;
    btn.classList.remove("active");
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Select`;
    document
        .querySelectorAll(".cal-day.day-sel")
        .forEach((d) => d.classList.remove("day-sel"));
    updateBulkBar();
    renderAllEvents();
    closeSelectPopover();
}

function toggleDaySel(ds, div) {
    const ids = evForDate(ds).map((e) => e.id),
        taskIds = tasksForDate(ds).map((t) => t.id);
    const allIn =
        ids.every((id) => selIds.has(id)) &&
        taskIds.every((id) => selTaskIds.has(id));
    ids.forEach((id) => (allIn ? selIds.delete(id) : selIds.add(id)));
    taskIds.forEach((id) =>
        allIn ? selTaskIds.delete(id) : selTaskIds.add(id),
    );
    div.classList.toggle("day-sel", !allIn);
    updateBulkBar();
    renderAllEvents();
}

function toggleEventSel(id) {
    selIds.has(id) ? selIds.delete(id) : selIds.add(id);
    updateBulkBar();
    renderCal();
    renderAllEvents();
}

function toggleTaskSel(id) {
    selTaskIds.has(id) ? selTaskIds.delete(id) : selTaskIds.add(id);
    updateBulkBar();
    renderCal();
}

function updateBulkBar() {
    const n = selIds.size + selTaskIds.size;
    const c = document.getElementById("bulkCount");
    if (c) {
        const ep = selIds.size
            ? `${selIds.size} event${selIds.size !== 1 ? "s" : ""}`
            : "";
        const tp = selTaskIds.size
            ? `${selTaskIds.size} task${selTaskIds.size !== 1 ? "s" : ""}`
            : "";
        c.textContent = [ep, tp].filter(Boolean).join(" + ") + " selected";
    }
    const bar = document.getElementById("bulkBar");
    if (bar) bar.classList.toggle("visible", n > 0 && selectMode);
}

// ═══════════════════════════════════════════════════════════════════
// SIDEBAR: DEADLINES
// ═══════════════════════════════════════════════════════════════════
function renderDeadlines() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = today.toISOString().slice(0, 10);

    const taskItems = allTasks
        .filter((t) => t.due_date && !t.completed_at)
        .map((t) => ({
            title: t.title,
            date: t.due_date,
            time: t.due_time,
            icon: PRI_ICON[t.priority || "low"],
        }));

    const eventItems = allEvents
        .filter(
            (e) =>
                (e.category === "exam" || e.category === "deadline") &&
                e.event_date >= todayStr,
        )
        .map((e) => ({
            title: e.title,
            date: e.event_date,
            time: e.event_time,
            icon: e.category === "exam" ? "📝" : "📌",
        }));

    const items = [...taskItems, ...eventItems]
        .sort((a, b) => (a.date > b.date ? 1 : -1))
        .slice(0, 7);

    const el = document.getElementById("deadlinesList");
    if (!el) return;

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
            } else if (diff <= 3) cls = "due-soon";
            return `<div class="deadline-item">
            <div class="deadline-icon">${e.icon}</div>
            <div class="deadline-info">
                <div class="deadline-title">${esc(e.title)}</div>
                <div class="deadline-subject">${due.toLocaleDateString("en-US", { month: "short", day: "numeric" })}${e.time ? " · " + fmt12(e.time) : ""}</div>
            </div>
            <div class="deadline-due ${cls}">${lbl}</div>
        </div>`;
        })
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// SIDEBAR: SHOW ON CALENDAR — category filter toggles only
// Renders into #calFilters
// ═══════════════════════════════════════════════════════════════════
function renderFilters() {
    const el = document.getElementById("calFilters");
    if (!el) return;

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

    const categories = [
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
            meta: `${cntW("group")} this week`,
        },
        {
            key: "event",
            label: "Events",
            color: "#1a5f7a",
            meta: "School & personal events",
        },
        {
            key: "exam",
            label: "Exams",
            color: "#dc2626",
            meta: "Scheduled exams",
        },
        {
            key: "deadline",
            label: "Deadlines",
            color: "#d97706",
            meta: "Assignment deadlines",
        },
    ];

    el.innerHTML = categories
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
// SIDEBAR: MY SUBJECTS — color-coded subject list only
// Renders into #mySubjectsList
// ═══════════════════════════════════════════════════════════════════
function renderSubjects() {
    const el = document.getElementById("mySubjectsList");
    if (!el) return;

    if (!userSubjects.length) {
        el.innerHTML = `<div style="font-size:12px;color:var(--text-light);padding:4px 0;">
            No subjects yet — click + Add above.
        </div>`;
        return;
    }

    el.innerHTML = userSubjects
        .map(
            (s) =>
                `<div style="display:flex;align-items:center;gap:10px;padding:6px 2px;border-radius:6px;font-size:13px;color:var(--text-primary);">
                    <div style="width:10px;height:10px;border-radius:50%;background:${esc(s.color_hex)};flex-shrink:0;"></div>
                    <span style="flex:1;">${esc(s.subject_name)}</span>
                    <button onclick="removeSubject('${s.id}')" title="Remove"
                            style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:14px;line-height:1;padding:0 2px;">&times;</button>
                </div>`,
        )
        .join("");
}

// ═══════════════════════════════════════════════════════════════════
// SIDEBAR: UPCOMING THIS WEEK
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
    if (!el) return;
    if (!evList.length && !taskList.length) {
        el.innerHTML =
            '<div class="state-box">Nothing coming up this week 🎉</div>';
        return;
    }
    let html = "";
    evList.forEach((ev) => {
        const dl = new Date(ev.idate + "T00:00:00").toLocaleDateString(
            "en-US",
            { weekday: "short", month: "short", day: "numeric" },
        );
        html += `<div class="upcoming-item" onclick="openEvModal('${ev.id}')"><div class="upcoming-dot" style="background:${CC[ev.category]}"></div><div class="upcoming-info"><div class="upcoming-title">${esc(ev.title)}</div><div class="upcoming-sub">${dl}${ev.event_time ? " · " + fmt12(ev.event_time) : ""}</div></div><span class="upcoming-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">${CI[ev.category]} ${CL[ev.category]}</span></div>`;
    });
    taskList.forEach((t) => {
        const dl = new Date(t.due_date + "T00:00:00").toLocaleDateString(
            "en-US",
            { weekday: "short", month: "short", day: "numeric" },
        );
        html += `<div class="upcoming-item" onclick="openTaskModal('${t.id}')"><div class="upcoming-dot" style="background:${PRI_COLOR[t.priority || "low"]}"></div><div class="upcoming-info"><div class="upcoming-title">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div><div class="upcoming-sub">${dl}${t.due_time ? " · " + fmt12(t.due_time) : ""}${t.label ? " · " + esc(t.label) : ""}</div></div><span class="upcoming-tag" style="background:${PRI_BG[t.priority || "low"]};color:${PRI_COLOR[t.priority || "low"]}">✅ Task</span></div>`;
    });
    el.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════════
// SIDEBAR: ALL EVENTS
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
            const checkbox = selectMode
                ? `<div onclick="event.stopPropagation();toggleEventSel('${ev.id}')" style="width:17px;height:17px;border-radius:4px;border:2px solid ${CC[ev.category]};flex-shrink:0;display:flex;align-items:center;justify-content:center;cursor:pointer;background:${isSel ? CC[ev.category] : "transparent"};">${isSel ? '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" width="10" height="10"><polyline points="2 6 5 9 10 3"/></svg>' : ""}</div>`
                : `<div class="all-ev-dot" style="background:${CC[ev.category]}"></div>`;
            html += `<div class="all-ev-item ${isSel ? "item-sel" : ""}" data-id="${ev.id}" style="${isSel ? "background:rgba(26,95,122,0.07);" : ""}" onclick="${selectMode ? `toggleEventSel('${ev.id}')` : `openEvModal('${ev.id}')`}">${checkbox}<div class="all-ev-info"><div class="all-ev-title" title="${esc(ev.title)}">${esc(ev.title)}</div><div class="all-ev-sub">${dl}${ev.event_time ? " · " + fmt12(ev.event_time) : ""}</div></div><span class="all-ev-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">${CI[ev.category]}</span></div>`;
        });
    });
    el.innerHTML = html;
}

// toggleFilter — updates filters and redraws sidebar consistently
function toggleFilter(key) {
    filters[key] = !filters[key];
    renderCal();
    renderFilters(); // re-render filter toggles only
    renderUpcoming();
    renderAllEvents();
}

// ═══════════════════════════════════════════════════════════════════
// SUBJECT DB HELPERS
// ═══════════════════════════════════════════════════════════════════
async function loadSubjects() {
    try {
        const rows = await sbReq(
            `user_subject_colors?user_id=eq.${UID}&order=subject_name.asc`,
            { headers: hdrs(true) },
        );
        userSubjects = Array.isArray(rows) ? rows : [];
    } catch {
        userSubjects = [];
    }
}

async function insertSubject(name, colorHex) {
    const [row] = await sbReq("user_subject_colors", {
        method: "POST",
        headers: { ...hdrs(true), Prefer: "return=representation" },
        body: JSON.stringify({
            user_id: UID,
            subject_name: name,
            color_hex: colorHex,
        }),
    });
    return row;
}

async function deleteSubject(id) {
    await sbReq(`user_subject_colors?id=eq.${id}`, {
        method: "DELETE",
        headers: hdrs(true),
    });
}

// ═══════════════════════════════════════════════════════════════════
// SUBJECT SELECT IN EVENT MODAL
// ═══════════════════════════════════════════════════════════════════
function populateSubjectSelect(currentValue = "") {
    const sel = document.getElementById("evSubject");
    if (!sel) return;
    sel.innerHTML =
        `<option value="">— No subject —</option>` +
        userSubjects
            .map(
                (s) =>
                    `<option value="${esc(s.subject_name)}" data-color="${esc(s.color_hex)}"
                ${s.subject_name === currentValue ? "selected" : ""}>
                ${esc(s.subject_name)}
            </option>`,
            )
            .join("");
    sel.onchange = () => applySubjectColor(sel);
    applySubjectColor(sel);
}

function applySubjectColor(sel) {
    const opt = sel.options[sel.selectedIndex];
    const color = opt?.dataset?.color || "";
    sel.style.borderLeftColor = color || "var(--border)";
    sel.style.borderLeftWidth = color ? "4px" : "1px";
}

// ═══════════════════════════════════════════════════════════════════
// ADD SUBJECT INLINE FORM
// ═══════════════════════════════════════════════════════════════════
function openAddSubjectForm() {
    if (document.getElementById("addSubjectInlineForm")) {
        closeAddSubjectForm();
        return;
    }
    const container = document.getElementById("addSubjectFormContainer");
    if (!container) return;

    const randomColor =
        PRESET_COLORS[Math.floor(Math.random() * PRESET_COLORS.length)];

    container.innerHTML = `
        <div id="addSubjectInlineForm" style="background:var(--bg-main);border:1px solid var(--border);
             border-radius:10px;padding:14px;margin-top:10px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-light);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">New Subject</div>
            <input id="newSubjectName" type="text" placeholder="Subject name…" maxlength="60"
                   style="width:100%;box-sizing:border-box;padding:7px 10px;font-size:13px;
                          border:1px solid var(--border);border-radius:7px;
                          background:var(--bg-card,#fff);color:var(--text-primary);margin-bottom:10px;"
                   onkeydown="if(event.key==='Enter')saveNewSubject()"/>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                ${PRESET_COLORS.map(
                    (c) =>
                        `<div onclick="selectSubjectColor('${c}')" data-color="${c}" class="subj-color-swatch"
                          style="width:20px;height:20px;border-radius:50%;background:${c};cursor:pointer;
                                 border:2px solid ${c === randomColor ? "#fff" : "transparent"};
                                 box-shadow:${c === randomColor ? "0 0 0 2px " + c : "none"};
                                 transition:transform .1s;"></div>`,
                ).join("")}
            </div>
            <input id="newSubjectColor" type="hidden" value="${randomColor}"/>
            <div style="display:flex;gap:8px;">
                <button onclick="saveNewSubject()"
                        style="flex:1;padding:7px;font-size:13px;font-weight:600;
                               background:var(--primary,#1a5f7a);color:#fff;border:none;border-radius:7px;cursor:pointer;">
                    Add
                </button>
                <button onclick="closeAddSubjectForm()"
                        style="padding:7px 12px;font-size:13px;background:transparent;
                               color:var(--text-secondary);border:1px solid var(--border);border-radius:7px;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>`;

    document.getElementById("newSubjectName").focus();
}

function selectSubjectColor(hex) {
    document.getElementById("newSubjectColor").value = hex;
    document.querySelectorAll(".subj-color-swatch").forEach((sw) => {
        const active = sw.dataset.color === hex;
        sw.style.border = active ? "2px solid #fff" : "2px solid transparent";
        sw.style.boxShadow = active ? `0 0 0 2px ${hex}` : "none";
        sw.style.transform = active ? "scale(1.2)" : "scale(1)";
    });
}

function closeAddSubjectForm() {
    const c = document.getElementById("addSubjectFormContainer");
    if (c) c.innerHTML = "";
}

async function saveNewSubject() {
    const name = document.getElementById("newSubjectName")?.value.trim();
    const color =
        document.getElementById("newSubjectColor")?.value || "#1a5f7a";
    if (!name) {
        document.getElementById("newSubjectName").focus();
        return;
    }
    if (
        userSubjects.some(
            (s) => s.subject_name.toLowerCase() === name.toLowerCase(),
        )
    ) {
        alert(`Subject "${name}" already exists.`);
        return;
    }
    const btn = document.querySelector("#addSubjectInlineForm button");
    if (btn) {
        btn.textContent = "Saving…";
        btn.disabled = true;
    }
    try {
        const row = await insertSubject(name, color);
        userSubjects.push(row);
        userSubjects.sort((a, b) =>
            a.subject_name.localeCompare(b.subject_name),
        );
        closeAddSubjectForm();
        renderSubjects(); // refresh subject list only
        populateSubjectSelect();
    } catch (err) {
        alert("Could not save subject: " + err.message);
        if (btn) {
            btn.textContent = "Add";
            btn.disabled = false;
        }
    }
}

async function removeSubject(id) {
    if (!confirm("Remove this subject?")) return;
    try {
        await deleteSubject(id);
        userSubjects = userSubjects.filter((s) => s.id !== id);
        renderSubjects(); // refresh subject list only
        populateSubjectSelect();
    } catch (err) {
        alert("Could not remove subject: " + err.message);
    }
}

// ═══════════════════════════════════════════════════════════════════
// EVENT MODAL
// ═══════════════════════════════════════════════════════════════════
async function openEvModal(id = null, prefill = null) {
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
        const timeInputId = {
            class: "evTimeStart",
            group: "evTimeStartGroup",
            event: "evTimeStartEvent",
            exam: "evTimeStartEvent",
            deadline: "evTimeStartEvent",
        }[ev.category];
        if (timeInputId) {
            const el = document.getElementById(timeInputId);
            if (el) el.value = st;
        }
        const endInputId = {
            class: "evTimeEnd",
            group: "evTimeEndGroup",
            event: "evTimeEndEvent",
            exam: "evTimeEndEvent",
            deadline: "evTimeEndEvent",
        }[ev.category];
        if (endInputId && ev.end_time) {
            const el = document.getElementById(endInputId);
            if (el) el.value = ev.end_time.slice(0, 5);
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

        const chk = document.getElementById("evReminder");
        const minSel = document.getElementById("evReminderMinutes");
        if (chk && ev.reminder_minutes) {
            chk.checked = true;
            document.getElementById("reminderOpts").style.display = "block";
            if (minSel) minSel.value = String(ev.reminder_minutes);
        }

        populateSubjectSelect(ev.subject_name || "");
        document.getElementById("btnDelEv").style.display = "block";
    } else {
        document.getElementById("modalTitle").textContent = "Add Event";
        if (!prefill) document.getElementById("evDate").value = fd(new Date());
        populateSubjectSelect("");
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
    document.getElementById("timeFieldClass").style.display =
        cat === "class" ? "" : "none";
    document.getElementById("timeFieldGroup").style.display =
        cat === "group" ? "" : "none";
    document.getElementById("timeFieldEvent").style.display =
        cat === "event" || cat === "exam" || cat === "deadline" ? "" : "none";
}

function resetForm() {
    [
        "evTitle",
        "evDesc",
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
    document.getElementById("evCat").value = "class";
    document.getElementById("evRecur").checked = false;
    document.getElementById("recurOpts").style.display = "none";
    document.getElementById("evReminder").checked = false;
    document.getElementById("reminderOpts").style.display = "none";
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

    const timeMap = {
        class: { start: "evTimeStart", end: "evTimeEnd" },
        group: { start: "evTimeStartGroup", end: "evTimeEndGroup" },
        event: { start: "evTimeStartEvent", end: "evTimeEndEvent" },
        exam: { start: "evTimeStartEvent", end: "evTimeEndEvent" },
        deadline: { start: "evTimeStartEvent", end: "evTimeEndEvent" },
    };
    const timeIds = timeMap[cat] || timeMap.event;
    const startTime = document.getElementById(timeIds.start)?.value || null;
    const endTime = document.getElementById(timeIds.end)?.value || null;

    if (cat === "class" && !startTime)
        return alert("Class requires a start time.");
    if (cat === "group" && !startTime)
        return alert("Study group requires a start time.");

    const recur = document.getElementById("evRecur").checked;
    const rDays = recur
        ? [...document.querySelectorAll(".rday.sel")].map((b) => b.dataset.d)
        : null;
    if (recur && rDays && !rDays.length)
        return alert("Select at least one repeat day.");

    const subjectName = document.getElementById("evSubject")?.value || null;
    const reminderChk = document.getElementById("evReminder");
    const reminderMin = document.getElementById("evReminderMinutes");
    const reminderMins =
        reminderChk?.checked && reminderMin?.value
            ? parseInt(reminderMin.value, 10)
            : null;

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
        subject_name: subjectName,
        reminder_minutes: reminderMins,
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
            l.classList.toggle("sel", l.dataset.p === pri);
            l.querySelector("input").checked = l.dataset.p === pri;
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

async function toggleTaskDone(id) {
    const t = allTasks.find((x) => x.id === id);
    if (!t) return;
    const newVal = t.completed_at ? null : new Date().toISOString();
    try {
        await taskUpdate(id, { completed_at: newVal });
        t.completed_at = newVal;
        if (curView === "month") renderCal();
        else if (curView === "week") renderWeek();
        else renderDay();
        renderDeadlines();
        renderUpcoming();
    } catch (err) {
        alert("Could not update task: " + err.message);
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
    if (!selIds.size && !selTaskIds.size) return;
    pendDel = {
        mode: "bulk",
        ids: new Set([...selIds]),
        taskIds: new Set([...selTaskIds]),
    };
    document.getElementById("confirmTitle").textContent = "Delete items";
    let listHtml = `<div style="border:1px solid var(--border,rgba(0,0,0,.12));border-radius:8px;overflow:hidden;margin:12px 0;">`;
    [...selIds].forEach((id) => {
        const ev = allEvents.find((e) => e.id === id);
        if (!ev) return;
        const dl = new Date(ev.event_date + "T00:00:00").toLocaleDateString(
            "en-US",
            { month: "short", day: "numeric" },
        );
        listHtml += `<div onclick="toggleConfirmEv('${id}')" id="ci-ev-${id}" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-bottom:1px solid var(--border,rgba(0,0,0,.08));cursor:pointer;background:var(--bg-main);"><div id="cichk-ev-${id}" style="width:16px;height:16px;border-radius:4px;border:1.5px solid ${CC[ev.category]};flex-shrink:0;display:flex;align-items:center;justify-content:center;background:${CC[ev.category]};"><svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg></div><div style="width:8px;height:8px;border-radius:50%;background:${CC[ev.category]};flex-shrink:0;"></div><div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ev.title)}</div><div style="font-size:11px;color:var(--text-light);">${CL[ev.category]} · ${dl}</div></div></div>`;
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
        const pc = PRI_COLOR[t.priority || "low"];
        listHtml += `<div onclick="toggleConfirmTask('${id}')" id="ci-task-${id}" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-bottom:1px solid var(--border,rgba(0,0,0,.08));cursor:pointer;background:var(--bg-main);"><div id="cichk-task-${id}" style="width:16px;height:16px;border-radius:4px;border:1.5px solid ${pc};flex-shrink:0;display:flex;align-items:center;justify-content:center;background:${pc};"><svg viewBox="0 0 10 8" fill="none" stroke="white" stroke-width="2.5" width="9" height="7"><polyline points="1 4 4 7 9 1"/></svg></div><div style="width:8px;height:8px;border-radius:50%;background:${pc};flex-shrink:0;"></div><div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${PRI_ICON[t.priority || "low"]} ${esc(t.title)}</div><div style="font-size:11px;color:var(--text-light);">Task · ${t.priority || "low"} · ${dl}</div></div></div>`;
    });
    listHtml += `</div>`;
    document.getElementById("confirmBody").innerHTML =
        `<span>Uncheck anything you want to keep.</span>${listHtml}`;
    updateConfirmCount();
    document.getElementById("confirmModal").classList.add("open");
}

function toggleConfirmEv(id) {
    const chk = document.getElementById(`cichk-ev-${id}`),
        ev = allEvents.find((e) => e.id === id);
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

function toggleConfirmTask(id) {
    const chk = document.getElementById(`cichk-task-${id}`),
        t = allTasks.find((x) => x.id === id);
    if (!t) return;
    const pc = PRI_COLOR[t.priority || "low"];
    if (pendDel.taskIds.has(id)) {
        pendDel.taskIds.delete(id);
        chk.style.background = "transparent";
        chk.innerHTML = "";
        document.getElementById(`ci-task-${id}`).style.opacity = ".45";
    } else {
        pendDel.taskIds.add(id);
        chk.style.background = pc;
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
            await taskDelete(pendDel.ids[0]);
            allTasks = allTasks.filter((t) => t.id !== pendDel.ids[0]);
            renderDeadlines();
            renderUpcoming();
            if (curView === "month") renderCal();
            else if (curView === "week") renderWeek();
            else renderDay();
        } else {
            if (pendDel.ids.size > 0) {
                await dbDelete([...pendDel.ids]);
                allEvents = allEvents.filter((e) => !pendDel.ids.has(e.id));
            }
            if (pendDel.taskIds?.size > 0) {
                await Promise.all(
                    [...pendDel.taskIds].map((id) => taskDelete(id)),
                );
                allTasks = allTasks.filter((t) => !pendDel.taskIds.has(t.id));
            }
            exitSelectMode();
            expandAll();
            redraw();
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
// EXPORT TO ICS
// ═══════════════════════════════════════════════════════════════════
function exportToICS() {
    if (!allEvents.length) {
        alert("No events to export.");
        return;
    }
    const pad = (n) => String(n).padStart(2, "0");
    const stamp = () => {
        const d = new Date();
        return `${d.getUTCFullYear()}${pad(d.getUTCMonth() + 1)}${pad(d.getUTCDate())}T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}${pad(d.getUTCSeconds())}Z`;
    };
    const icalDT = (date, time) => {
        const ds = date.replace(/-/g, "");
        if (!time) return ds;
        return `${ds}T${time.slice(0, 5).replace(":", "00")}`;
    };
    const fold = (line) => {
        const out = [];
        while (line.length > 75) {
            out.push(line.slice(0, 75));
            line = " " + line.slice(75);
        }
        out.push(line);
        return out.join("\r\n");
    };
    const escIcal = (s) =>
        (s || "")
            .replace(/\\/g, "\\\\")
            .replace(/;/g, "\\;")
            .replace(/,/g, "\\,")
            .replace(/\n/g, "\\n");

    const lines = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//StudyHub//StudyHub Calendar//EN",
        "CALSCALE:GREGORIAN",
        "METHOD:PUBLISH",
    ];

    allEvents.forEach((ev) => {
        const dtstart = ev.event_time
            ? `DTSTART:${icalDT(ev.event_date, ev.event_time)}`
            : `DTSTART;VALUE=DATE:${icalDT(ev.event_date)}`;
        let dtend = "";
        if (ev.end_time) {
            dtend = `DTEND:${icalDT(ev.event_date, ev.end_time)}`;
        } else if (ev.event_time) {
            const [h, m] = ev.event_time.split(":").map(Number);
            dtend = `DTEND:${icalDT(ev.event_date, `${String((h + 1) % 24).padStart(2, "0")}:${String(m).padStart(2, "0")}`)}`;
        } else {
            const d = new Date(ev.event_date + "T00:00:00");
            d.setDate(d.getDate() + 1);
            dtend = `DTEND;VALUE=DATE:${fd(d).replace(/-/g, "")}`;
        }
        [
            "BEGIN:VEVENT",
            fold(`UID:${ev.id}@studyhub`),
            fold(`DTSTAMP:${stamp()}`),
            fold(dtstart),
            fold(dtend),
            fold(`SUMMARY:${escIcal(ev.title)}`),
            ev.description
                ? fold(`DESCRIPTION:${escIcal(ev.description)}`)
                : null,
            ev.subject_name
                ? fold(`CATEGORIES:${escIcal(ev.subject_name)}`)
                : null,
            "END:VEVENT",
        ]
            .filter(Boolean)
            .forEach((l) => lines.push(l));
    });

    allTasks
        .filter((t) => t.due_date)
        .forEach((t) => {
            [
                "BEGIN:VTODO",
                fold(`UID:task-${t.id}@studyhub`),
                fold(`DTSTAMP:${stamp()}`),
                fold(`SUMMARY:${escIcal(t.title)}`),
                fold(`DUE;VALUE=DATE:${t.due_date.replace(/-/g, "")}`),
                fold(`STATUS:${t.completed_at ? "COMPLETED" : "NEEDS-ACTION"}`),
                t.notes ? fold(`DESCRIPTION:${escIcal(t.notes)}`) : null,
                "END:VTODO",
            ]
                .filter(Boolean)
                .forEach((l) => lines.push(l));
        });

    lines.push("END:VCALENDAR");
    const blob = new Blob([lines.join("\r\n")], {
        type: "text/calendar;charset=utf-8",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "studyhub-calendar.ics";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 10000);
}

// ═══════════════════════════════════════════════════════════════════
// COPY ICAL LINK
// ═══════════════════════════════════════════════════════════════════
async function copyICalLink() {
    const storeKey = `sh_ical_token_${UID}`;
    let token = localStorage.getItem(storeKey);
    if (!token) {
        token =
            crypto.randomUUID?.() ||
            Math.random().toString(36).slice(2) + Date.now().toString(36);
        localStorage.setItem(storeKey, token);
    }
    const httpsLink = `${window.location.origin}/api/ical/${UID}/${token}`;
    try {
        await navigator.clipboard.writeText(httpsLink);
        showToast("iCal link copied!");
    } catch {
        prompt("Copy this iCal link:", httpsLink);
    }
}

// ═══════════════════════════════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════════════════════════════
function showToast(msg, duration = 2500) {
    let toast = document.getElementById("shToast");
    if (!toast) {
        toast = document.createElement("div");
        toast.id = "shToast";
        toast.style.cssText = `position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(60px);
            background:#1a5f7a;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;
            box-shadow:0 4px 16px rgba(0,0,0,.25);z-index:99999;transition:transform .25s ease,opacity .25s ease;
            opacity:0;pointer-events:none;`;
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    requestAnimationFrame(() => {
        toast.style.transform = "translateX(-50%) translateY(0)";
        toast.style.opacity = "1";
    });
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.style.transform = "translateX(-50%) translateY(60px)";
        toast.style.opacity = "0";
    }, duration);
}
