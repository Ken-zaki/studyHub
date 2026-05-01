// ═══════════════════════════════════════════════════════════════════
// CONFIG  (injected from blade via dashboard.blade.php inline script)
// SB_URL, SB_ANON, SB_SVC, UID are set as globals before this file loads
// ═══════════════════════════════════════════════════════════════════
const TABLE = "calendar_events";

// ═══════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════
let curDate = new Date();
let allEvents = [];
let expanded = [];
let filters = { todo: true, class: true, group: true, event: true };
let selectMode = false;
let selIds = new Set();
let editId = null;
let pendDel = null;
let curView = "month"; // 'month' | 'week'

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
// CONSTANTS
// ═══════════════════════════════════════════════════════════════════
const CC = {
    todo: "#dc2626",
    class: "#0f766e",
    group: "#7c3aed",
    event: "#1a5f7a",
};
const CB = {
    todo: "rgba(255,107,107,.13)",
    class: "rgba(42,157,143,.13)",
    group: "rgba(124,77,202,.13)",
    event: "rgba(26,95,122,.11)",
};
const CI = { todo: "📌", class: "📗", group: "👥", event: "📅" };
const CL = {
    todo: "To Do",
    class: "Class",
    group: "Study Group",
    event: "Event",
};

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async () => {
    wireUI();
    try {
        await dbLoad();
        expandAll();
    } catch (err) {
        document.getElementById("calDays").innerHTML =
            `<div class="state-box err" style="grid-column:1/-1">⚠️ ${esc(err.message)}</div>`;
        return;
    }
    redraw();
});

function wireUI() {
    // Navigation
    document.getElementById("btnPrev").onclick = () => {
        navigate(-1);
    };
    document.getElementById("btnNext").onclick = () => {
        navigate(+1);
    };
    document.getElementById("btnToday").onclick = () => {
        curDate = new Date();
        expandAll();
        redraw();
    };

    // View toggle
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

    // Add event
    document.getElementById("btnAdd").onclick = () => openEvModal();

    // Popover
    document.getElementById("btnPopClose").addEventListener("click", (e) => {
        e.stopPropagation();
        closePopover();
    });
    document.getElementById("btnPopAdd").onclick = () => {
        const d = document.getElementById("btnPopAdd").dataset.date;
        closePopover();
        openEvModal(null, d);
    };

    // Select mode
    document.getElementById("btnSelectMode").onclick = toggleSelectMode;
    document.getElementById("btnBulkCancel").onclick = exitSelectMode;
    document.getElementById("btnBulkDelete").onclick = promptBulkDelete;

    // Event modal
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

    // Confirm modal
    document.getElementById("btnConfirmClose").onclick = closeConfirm;
    document.getElementById("btnConfirmCancel").onclick = closeConfirm;
    document.getElementById("btnConfirmDel").onclick = execDelete;

    // Close popover on outside click
    document.addEventListener("click", (e) => {
        if (!document.getElementById("dayPopover").classList.contains("open"))
            return;
        if (!e.target.closest(".cal-day") && !e.target.closest("#dayPopover")) {
            closePopover();
        }
    });

    // All events search
    document
        .getElementById("allEventsWidget")
        .addEventListener("input", (e) => {
            if (e.target.id === "allEvSearch") renderAllEvents();
        });
}

// Navigate: month → shift month; week → shift week
function navigate(dir) {
    if (curView === "month") {
        curDate.setMonth(curDate.getMonth() + dir);
    } else {
        curDate.setDate(curDate.getDate() + dir * 7);
    }
    expandAll();
    redraw();
}

function redraw() {
    updateTitle();
    if (curView === "month") {
        document.getElementById("monthView").style.display = "";
        document.getElementById("weekView").style.display = "none";
        renderCal();
    } else {
        document.getElementById("monthView").style.display = "none";
        document.getElementById("weekView").style.display = "block";
        renderWeek();
    }
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
    } else {
        const { start, end } = weekRange(curDate);
        const opts = { month: "short", day: "numeric" };
        document.getElementById("calTitle").textContent =
            `${start.toLocaleDateString("en-US", opts)} – ${end.toLocaleDateString("en-US", { ...opts, year: "numeric" })}`;
    }
}

// ═══════════════════════════════════════════════════════════════════
// MONTH CALENDAR
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
        const chips = vis
            .slice(0, 3)
            .map(
                (e) =>
                    `<div class="day-event-chip chip-${e.category}">${esc(e.title)}</div>`,
            )
            .join("");
        const more =
            vis.length > 3
                ? `<div class="day-event-chip chip-event">+${vis.length - 3} more</div>`
                : "";

        div.innerHTML = `<div class="day-check"></div>
             <div class="day-num">${dn}</div>
             <div class="day-events">${chips}${more}</div>`;

        if (!isOther) {
            div.addEventListener("click", (e) => {
                if (selectMode) {
                    toggleDaySel(ds, div);
                    return;
                }
                e.stopPropagation();
                openPopover(div, ds, cell);
            });
            div.querySelector(".day-check").addEventListener("click", (e) => {
                e.stopPropagation();
                toggleDaySel(ds, div);
            });
        }
        grid.appendChild(div);
    }
}

function isDaySel(ds) {
    return evForDate(ds).some((e) => selIds.has(e.id));
}

// ═══════════════════════════════════════════════════════════════════
// WEEK VIEW
// ═══════════════════════════════════════════════════════════════════
function weekRange(ref) {
    const d = new Date(ref);
    const day = d.getDay();
    const start = new Date(d);
    start.setDate(d.getDate() - day);
    start.setHours(0, 0, 0, 0);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return { start, end };
}

function renderWeek() {
    const today = new Date();
    const now = new Date();
    const { start } = weekRange(curDate);
    const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const HOUR_H = 64;
    const TOTAL_H = 24 * HOUR_H;

    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        days.push(d);
    }

    // Summary bar
    const counts = { todo: 0, class: 0, group: 0, event: 0 };
    days.forEach((d) =>
        evForDate(fd(d))
            .filter((e) => filters[e.category])
            .forEach((e) => counts[e.category]++),
    );
    const sumColors = {
        todo: "#dc2626",
        class: "#0f766e",
        group: "#7c3aed",
        event: "#1a5f7a",
    };
    const sumLabels = {
        todo: "to do",
        class: "class",
        group: "study group",
        event: "event",
    };
    document.getElementById("weekSummaryBar").innerHTML = Object.entries(counts)
        .filter(([, n]) => n > 0)
        .map(
            ([cat, n]) => `
            <div style="display:flex;align-items:center;gap:5px;padding:5px 12px;
                        border-radius:20px;font-size:13px;font-weight:500;
                        border:1px solid var(--border);background:white;color:var(--text-secondary)">
                <div style="width:8px;height:8px;border-radius:50%;background:${sumColors[cat]}"></div>
                ${n} ${sumLabels[cat]}${n !== 1 ? "s" : ""}
            </div>`,
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

    // Sticky header
    html += `<div style="display:grid;grid-template-columns:52px repeat(7,1fr);
                          border-bottom:2px solid var(--border);background:var(--bg-main);
                          position:sticky;top:0;z-index:10;">
                <div style="border-right:1px solid var(--border);"></div>`;
    days.forEach((d) => {
        const isTod = sameDay(d, today);
        html += `<div style="padding:10px 6px;text-align:center;
                              border-right:1px solid var(--border);
                              background:${isTod ? "rgba(26,95,122,0.06)" : "transparent"};">
            <div style="font-size:11px;font-weight:600;color:var(--text-light);
                        text-transform:uppercase;letter-spacing:.04em;">
                ${dayNames[d.getDay()]}
            </div>
            <div style="font-size:20px;font-weight:600;margin-top:2px;
                        color:${isTod ? "var(--primary)" : "var(--text-primary)"};">
                ${d.getDate()}
            </div>
        </div>`;
    });
    html += `</div>`;

    // All-day row
    if (hasUntimed) {
        html += `<div style="display:grid;grid-template-columns:52px repeat(7,1fr);
                              border-bottom:1px solid var(--border);background:var(--bg-main);min-height:32px;">
            <div style="border-right:1px solid var(--border);padding:6px 4px;
                        font-size:10px;color:var(--text-light);text-align:right;
                        white-space:nowrap;">all day</div>`;
        days.forEach((d) => {
            const untimed = evForDate(fd(d)).filter(
                (e) => filters[e.category] && !e.event_time,
            );
            html += `<div style="border-right:1px solid var(--border);padding:3px 2px;
                                  min-height:32px;" onclick="openEvModal(null,'${fd(d)}')">`;
            untimed.forEach((ev) => {
                html += `<div style="font-size:10px;font-weight:600;padding:3px 6px;margin-bottom:2px;
                                     border-radius:4px;cursor:pointer;width:100%;box-sizing:border-box;
                                     white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                     background:${CB[ev.category]};color:${CC[ev.category]};
                                     border-left:3px solid ${CC[ev.category]};"
                              onclick="event.stopPropagation();openEvModal('${ev.id}')"
                              title="${esc(ev.title)}">${esc(ev.title)}</div>`;
            });
            html += `</div>`;
        });
        html += `</div>`;
    }

    // Scrollable time grid
    html += `<div id="weekScrollBody" style="overflow-y:auto;max-height:580px;">
                <div style="display:grid;grid-template-columns:52px repeat(7,1fr);position:relative;">`;

    // Time gutter
    html += `<div style="position:relative;height:${TOTAL_H}px;border-right:1px solid var(--border);background:var(--bg-main);">`;
    for (let h = 0; h < 24; h++) {
        if (h === 0) continue;
        const label = h < 12 ? `${h} AM` : h === 12 ? "12 PM" : `${h - 12} PM`;
        html += `<div style="position:absolute;top:${h * HOUR_H}px;right:6px;
                              font-size:10px;color:var(--text-light);
                              transform:translateY(-50%);white-space:nowrap;">
                    ${label}
                 </div>`;
    }
    html += `</div>`;

    // Day columns
    days.forEach((d) => {
        const ds = fd(d);
        const isTod = sameDay(d, today);
        const allEvs = evForDate(ds).filter(
            (e) => filters[e.category] && e.event_time,
        );

        html += `<div style="position:relative;height:${TOTAL_H}px;
                              border-right:1px solid var(--border);
                              background:${isTod ? "rgba(26,95,122,0.015)" : "white"};"
                     onclick="openEvModal(null,'${ds}')">`;

        for (let h = 0; h < 24; h++) {
            html += `<div style="position:absolute;top:${h * HOUR_H}px;left:0;right:0;
                                  border-top:1px solid ${h === 0 ? "transparent" : "var(--border)"};
                                  pointer-events:none;"></div>
                     <div style="position:absolute;top:${h * HOUR_H + HOUR_H / 2}px;left:0;right:0;
                                  border-top:1px dashed rgba(0,0,0,0.05);
                                  pointer-events:none;"></div>`;
        }

        if (isTod) {
            const pct = (now.getHours() * 60 + now.getMinutes()) / 60;
            html += `<div style="position:absolute;top:${pct * HOUR_H}px;left:0;right:0;
                                  border-top:2px solid var(--accent);z-index:4;pointer-events:none;">
                        <div style="position:absolute;left:-1px;top:-4px;width:8px;height:8px;
                                    border-radius:50%;background:var(--accent);"></div>
                     </div>`;
        }

        // Overlap detection
        const sorted = [...allEvs].sort((a, b) =>
            a.event_time.localeCompare(b.event_time),
        );

        function getEventBounds(ev) {
            const [sh, sm] = ev.event_time.split(":").map(Number);
            const startMin = sh * 60 + sm;
            const topPx = (startMin / 60) * HOUR_H;
            let heightPx;
            if (ev.end_time) {
                const [eh, em] = ev.end_time.split(":").map(Number);
                const durMin = eh * 60 + em - startMin;
                heightPx = Math.max(28, (durMin / 60) * HOUR_H);
            } else {
                heightPx = HOUR_H;
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
            const overlapping = placed.filter(
                (p) => bounds.startMin < p.endMin && bounds.endMin > p.startMin,
            );
            const usedCols = new Set(overlapping.map((p) => p.col));
            let col = 0;
            while (usedCols.has(col)) col++;
            placed.push({ ev, ...bounds, col });
        });

        placed.forEach((item) => {
            const overlapping = placed.filter(
                (p) => item.startMin < p.endMin && item.endMin > p.startMin,
            );
            item.totalCols = Math.max(...overlapping.map((p) => p.col)) + 1;
        });

        placed.forEach(({ ev, topPx, heightPx, col, totalCols }) => {
            const colW = `calc((100% - 2px) / ${totalCols})`;
            const colL = `calc(1px + (100% - 2px) / ${totalCols} * ${col})`;
            const timeLabel = fmt12(ev.event_time);
            const endLabel = ev.end_time ? ` – ${fmt12(ev.end_time)}` : "";
            const isTodo = ev.category === "todo";
            const minH = Math.max(28, heightPx);

            if (isTodo) {
                html += `<div style="position:absolute;top:${topPx}px;left:0;right:0;
                                      border-top:1.5px dashed ${CC.todo};opacity:.5;
                                      pointer-events:none;z-index:2;"></div>
                         <div style="position:absolute;top:${topPx}px;left:${colL};width:${colW};
                                      height:${minH}px;z-index:3;box-sizing:border-box;
                                      background:${CB.todo};border-left:3px solid ${CC.todo};
                                      border-radius:4px;padding:3px 6px;cursor:pointer;overflow:hidden;"
                              onclick="event.stopPropagation();openEvModal('${ev.id}')"
                              title="${esc(ev.title)} · ${timeLabel}">
                            <div style="font-size:10px;font-weight:700;color:${CC.todo};
                                         white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                         line-height:1.3;">📌 ${esc(ev.title)}</div>
                            ${minH >= 44 ? `<div style="font-size:9px;color:${CC.todo};opacity:.7;margin-top:2px;">${timeLabel}</div>` : ""}
                         </div>`;
            } else {
                html += `<div style="position:absolute;top:${topPx}px;left:${colL};width:${colW};
                                      height:${minH}px;z-index:2;box-sizing:border-box;
                                      background:${CB[ev.category]};
                                      border-left:3px solid ${CC[ev.category]};
                                      border-radius:4px;padding:4px 6px;cursor:pointer;
                                      overflow:hidden;display:flex;flex-direction:column;
                                      justify-content:flex-start;"
                              onclick="event.stopPropagation();openEvModal('${ev.id}')"
                              title="${esc(ev.title)} · ${timeLabel}${endLabel}">
                            <div style="font-size:10px;font-weight:700;color:${CC[ev.category]};
                                         white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                         line-height:1.3;">${esc(ev.title)}</div>
                            ${minH >= 44 ? `<div style="font-size:9px;color:${CC[ev.category]};opacity:.75;margin-top:2px;">${timeLabel}${endLabel}</div>` : ""}
                         </div>`;
            }
        });

        html += `</div>`;
    });

    html += `</div></div></div>`;

    document.getElementById("weekGrid").innerHTML = html;

    const scrollBody = document.getElementById("weekScrollBody");
    if (scrollBody) scrollBody.scrollTop = scrollToHour * HOUR_H;
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
    const el = document.getElementById("popEvents");
    el.innerHTML = evs.length
        ? evs
              .map(
                  (ev) => `
            <div class="popover-event" onclick="openEvModal('${ev.id}')">
                <div class="popover-event-dot" style="background:${CC[ev.category]}"></div>
                <div class="popover-event-info">
                    <div class="popover-event-title">${esc(ev.title)}</div>
                    ${ev.event_time ? `<div class="popover-event-time">${fmt12(ev.event_time)}</div>` : ""}
                </div>
                <button class="popover-ev-del" title="Delete"
                        onclick="event.stopPropagation();promptSingleDelete('${ev.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                </button>
            </div>`,
              )
              .join("")
        : '<div class="popover-empty">No events on this day</div>';

    const r = dayEl.getBoundingClientRect();
    let l = r.left + r.width / 2 - 142;
    let t = r.bottom + 8;
    l = Math.max(16, Math.min(l, window.innerWidth - 300));
    if (t + 320 > window.innerHeight - 16) t = r.top - 330;
    pop.style.left = l + "px";
    pop.style.top = t + "px";
    pop.classList.add("open");
}

function closePopover() {
    document.getElementById("dayPopover").classList.remove("open");
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
    } else {
        exitSelectMode();
    }
}

function exitSelectMode() {
    selectMode = false;
    selIds.clear();
    document.body.classList.remove("select-mode");
    const btn = document.getElementById("btnSelectMode");
    btn.classList.remove("active");
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Select`;
    document
        .querySelectorAll(".cal-day.day-sel")
        .forEach((d) => d.classList.remove("day-sel"));
    document
        .querySelectorAll(".upcoming-item.item-sel")
        .forEach((i) => i.classList.remove("item-sel"));
    updateBulkBar();
}

function toggleDaySel(ds, div) {
    const ids = evForDate(ds).map((e) => e.id);
    const allIn = ids.every((id) => selIds.has(id));
    ids.forEach((id) => (allIn ? selIds.delete(id) : selIds.add(id)));
    div.classList.toggle("day-sel", !allIn);
    syncUpcomingSel();
    updateBulkBar();
}

function toggleItemSel(id, el) {
    selIds.has(id) ? selIds.delete(id) : selIds.add(id);
    el.classList.toggle("item-sel", selIds.has(id));
    renderCal();
    updateBulkBar();
}

function syncUpcomingSel() {
    document.querySelectorAll(".upcoming-item[data-id]").forEach((el) => {
        el.classList.toggle("item-sel", selIds.has(el.dataset.id));
    });
    document.querySelectorAll(".all-ev-item[data-id]").forEach((el) => {
        el.classList.toggle("item-sel", selIds.has(el.dataset.id));
    });
}

function updateBulkBar() {
    const n = selIds.size;
    document.getElementById("bulkCount").textContent =
        `${n} event${n !== 1 ? "s" : ""} selected`;
    document
        .getElementById("bulkBar")
        .classList.toggle("visible", n > 0 && selectMode);
}

// ═══════════════════════════════════════════════════════════════════
// UPCOMING LIST
// ═══════════════════════════════════════════════════════════════════
function renderUpcoming() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const wEnd = new Date(today);
    wEnd.setDate(today.getDate() + 7);
    const list = expanded
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

    const el = document.getElementById("upcomingList");
    if (!list.length) {
        el.innerHTML =
            '<div class="state-box">No upcoming events this week 🎉</div>';
        return;
    }
    el.innerHTML = list
        .map((ev) => {
            const d = new Date(ev.idate + "T00:00:00");
            const dl = d.toLocaleDateString("en-US", {
                weekday: "short",
                month: "short",
                day: "numeric",
            });
            const sel = selIds.has(ev.id);
            return `<div class="upcoming-item ${sel ? "item-sel" : ""}" data-id="${ev.id}"
                     onclick="handleItemClick(event,'${ev.id}',this)">
            <div class="upcoming-check"></div>
            <div class="upcoming-dot" style="background:${CC[ev.category]}"></div>
            <div class="upcoming-info">
                <div class="upcoming-title">${esc(ev.title)}</div>
                <div class="upcoming-sub">${dl}${ev.event_time ? " · " + fmt12(ev.event_time) : ""}</div>
            </div>
            <span class="upcoming-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">
                ${CI[ev.category]} ${CL[ev.category]}
            </span>
        </div>`;
        })
        .join("");
}

function handleItemClick(e, id, el) {
    if (selectMode) {
        toggleItemSel(id, el);
        return;
    }
    openEvModal(id);
}

// ═══════════════════════════════════════════════════════════════════
// RIGHT SIDEBAR WIDGETS
// ═══════════════════════════════════════════════════════════════════
function renderDeadlines() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const items = allEvents
        .filter((e) => e.category === "todo")
        .sort((a, b) => (a.event_date > b.event_date ? 1 : -1))
        .slice(0, 7);
    if (!items.length) {
        document.getElementById("deadlinesList").innerHTML =
            '<div class="state-box">No deadlines 🎉</div>';
        return;
    }
    document.getElementById("deadlinesList").innerHTML = items
        .map((e) => {
            const due = new Date(e.event_date + "T00:00:00");
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
            return `<div class="deadline-item">
            <div class="deadline-icon">📌</div>
            <div class="deadline-info">
                <div class="deadline-title">${esc(e.title)}</div>
                <div class="deadline-subject">${due.toLocaleDateString("en-US", { month: "short", day: "numeric" })}${e.event_time ? " · " + fmt12(e.event_time) : ""}</div>
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
            key: "todo",
            label: "To Do",
            color: "#dc2626",
            meta: `${cntW("todo")} due this week`,
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
            (c) => `
        <div class="cal-category ${filters[c.key] ? "active" : ""}" style="color:${c.color}"
             onclick="toggleFilter('${c.key}')">
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
        el.innerHTML = `<div class="all-ev-empty">${q ? "No events match your search." : "No events yet."}</div>`;
        return;
    }

    const groups = {};
    evs.forEach((ev) => {
        const d = new Date(ev.event_date + "T00:00:00");
        const key = d.toLocaleDateString("en-US", {
            month: "long",
            year: "numeric",
        });
        if (!groups[key]) groups[key] = [];
        groups[key].push(ev);
    });

    let html = "";
    Object.entries(groups).forEach(([month, items]) => {
        html += `<div class="all-ev-group-label">${month}</div>`;
        items.forEach((ev) => {
            const d = new Date(ev.event_date + "T00:00:00");
            const dl = d.toLocaleDateString("en-US", {
                weekday: "short",
                month: "short",
                day: "numeric",
            });
            const sel = selIds.has(ev.id);
            html += `<div class="all-ev-item ${sel ? "item-sel" : ""}" data-id="${ev.id}"
                          onclick="handleAllEvClick(event,'${ev.id}',this)">
                <div class="all-ev-check"></div>
                <div class="all-ev-dot" style="background:${CC[ev.category]}"></div>
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

function handleAllEvClick(e, id, el) {
    if (selectMode) {
        toggleItemSel(id, el);
        return;
    }
    openEvModal(id);
}

function toggleFilter(key) {
    filters[key] = !filters[key];
    renderCal();
    renderMyCalendars();
    renderUpcoming();
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

        if (ev.category === "todo") {
            document.getElementById("evTimeTodo").value = st;
        } else if (ev.category === "class") {
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
    ["todo", "class", "group", "event"].forEach((c) => {
        const key = c.charAt(0).toUpperCase() + c.slice(1);
        const el = document.getElementById(`timeField${key}`);
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
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
    document.getElementById("evDate").value = "";
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
    const desc = document.getElementById("evDesc").value.trim() || null;
    const recur = document.getElementById("evRecur").checked;
    const rDays = recur
        ? [...document.querySelectorAll(".rday.sel")].map((b) => b.dataset.d)
        : null;
    const rEnd = recur
        ? document.getElementById("evRecurEnd").value || null
        : null;

    let startTime = null,
        endTime = null;
    if (cat === "todo") {
        startTime = document.getElementById("evTimeTodo").value || null;
    } else if (cat === "class") {
        startTime = document.getElementById("evTimeStart").value || null;
        endTime = document.getElementById("evTimeEnd").value || null;
    } else if (cat === "group") {
        startTime = document.getElementById("evTimeStartGroup").value || null;
        endTime = document.getElementById("evTimeEndGroup").value || null;
    } else if (cat === "event") {
        startTime = document.getElementById("evTimeStartEvent").value || null;
        endTime = document.getElementById("evTimeEndEvent").value || null;
    }

    if (!title) return alert("Please enter a title.");
    if (!date) return alert("Please select a date.");
    if (cat === "class" && (!startTime || !endTime))
        return alert("Class schedule requires both start and end time.");
    if (cat === "group" && !startTime)
        return alert("Study group requires a start time.");
    if (recur && rDays && !rDays.length)
        return alert("Select at least one repeat day.");

    const data = {
        title,
        event_date: date,
        category: cat,
        description: desc,
        event_time: startTime,
        end_time: endTime,
        is_recurring: recur,
        recur_days: rDays,
        recur_end: rEnd,
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
        `Are you sure you want to delete <span class="confirm-count">"${esc(ev?.title || "this event")}"</span>? This cannot be undone.`;
    document.getElementById("confirmModal").classList.add("open");
}

function promptBulkDelete() {
    if (!selIds.size) return;
    const n = selIds.size;
    pendDel = { mode: "bulk", ids: [...selIds] };
    document.getElementById("confirmTitle").textContent =
        `Delete ${n} Event${n !== 1 ? "s" : ""}`;
    document.getElementById("confirmBody").innerHTML =
        `Are you sure you want to permanently delete <span class="confirm-count">${n} event${n !== 1 ? "s" : ""}</span>? This cannot be undone.`;
    document.getElementById("confirmModal").classList.add("open");
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
        await dbDelete(pendDel.ids);
        const set = new Set(pendDel.ids);
        allEvents = allEvents.filter((e) => !set.has(e.id));
        if (pendDel.mode === "bulk") exitSelectMode();
        expandAll();
        closeConfirm();
        redraw();
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
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
}
