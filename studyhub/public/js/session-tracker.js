/**
 * session-tracker.js
 * Focus Mode — Session Tracker
 *
 * Reads data from:
 *   - window.__trackerData  (injected by Blade from PHP session)
 *   - window.FocusMode.state  (live in-page state from focus-mode.js)
 *
 * Updates live every second while Focus Mode is on.
 */
(function () {
    "use strict";

    /* ── Config ─────────────────────────────────────────────── */
    const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const REFRESH_MS = 1000; // live update interval

    /* ── Helpers ────────────────────────────────────────────── */
    function pad(n) { return String(n).padStart(2, "0"); }

    function fmtMins(secs) {
        const m = Math.floor(secs / 60);
        if (m < 60) return m + "m";
        const h = Math.floor(m / 60);
        const rem = m % 60;
        return rem ? `${h}h ${rem}m` : `${h}h`;
    }

    function fmtMinShort(secs) {
        const m = Math.floor(secs / 60);
        return m + "m";
    }

    function getPhTime() {
        // Always compute date in Asia/Manila (UTC+8) regardless of browser locale
        const now = new Date();
        const phOffset = 8 * 60; // minutes
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        return new Date(utc + phOffset * 60000);
    }

    function todayKey() {
        const d = getPhTime();
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    function dayOfWeek() {
        return getPhTime().getDay();
    }

    /* ── Persistent local storage helpers ──────────────────── */
    const LS_KEY = "studyhub_focus_tracker";

    function loadTrackerStore() {
        try {
            return JSON.parse(localStorage.getItem(LS_KEY) || "{}");
        } catch { return {}; }
    }

    function saveTrackerStore(data) {
        try { localStorage.setItem(LS_KEY, JSON.stringify(data)); } catch {}
    }

    /**
     * Returns the tracker store, merging in server-side data
     * (window.__trackerData) so past sessions from previous page
     * loads aren't lost.
     */
    function getStore() {
        const store = loadTrackerStore();
        const server = window.__trackerData || {};

        // Merge server-side cumulative seconds into today's total
        // (server total_seconds is the all-time sum; we keep per-day
        //  entries locally and use the server count as session count floor)
        if (!store.days) store.days = {};
        if (!store.sessions) store.sessions = [];
        if (!store.modeSecs) store.modeSecs = { screenReview: 0, screenFlashcard: 0, screenQuiz: 0 };
        if (typeof store.sessionCount !== "number") {
            store.sessionCount = server.session_count || 0;
        }

        return store;
    }

    /* ── Accumulate live time per screen ────────────────────── */
    let _lastScreen = null;
    let _lastTick   = null;

    function accumulateLiveTime(store) {
        const fm = window.FocusMode;
        if (!fm || !fm.state || !fm.state.focusOn) {
            _lastScreen = null;
            _lastTick   = null;
            return;
        }

        const screen = fm.state.currentScreen;
        const now    = Date.now();

        if (_lastScreen && _lastTick) {
            const elapsed = Math.floor((now - _lastTick) / 1000);
            if (elapsed > 0 && elapsed < 120) { // sanity: ignore gaps > 2min
                // Per-screen mode time
                if (store.modeSecs[_lastScreen] !== undefined) {
                    store.modeSecs[_lastScreen] += elapsed;
                }
                // Per-day total (use today key)
                const key = todayKey();
                if (!store.days[key]) store.days[key] = 0;
                store.days[key] += elapsed;
            }
        }

        _lastScreen = screen;
        _lastTick   = now;
    }

    /* ── Record a completed focus session ───────────────────── */
    window.__onFocusSessionSaved = function (durationSecs) {
        const store = getStore();
        store.sessionCount = (store.sessionCount || 0) + 1;
        store.sessions.push({ date: todayKey(), secs: durationSecs, ts: Date.now() });
        if (store.sessions.length > 200) store.sessions = store.sessions.slice(-200);
        saveTrackerStore(store);
    };

    /* ── Compute streak (consecutive days with focus time) ─── */
    function computeStreak(store) {
        const activeDays = new Set(Object.keys(store.days).filter(k => store.days[k] > 60));
        const today      = getPhTime(); // ← was new Date()
        let streak       = 0;
        let cursor       = new Date(today.getTime());
        const todayStr   = todayKey();

        if (!activeDays.has(todayStr)) {
            cursor.setDate(cursor.getDate() - 1);
        }
        for (let i = 0; i < 365; i++) {
            const key = `${cursor.getFullYear()}-${pad(cursor.getMonth()+1)}-${pad(cursor.getDate())}`;
            if (activeDays.has(key)) {
                streak++;
                cursor.setDate(cursor.getDate() - 1);
            } else {
                break;
            }
        }
        return streak;
    }

    /* ── Build last-7-days data for bar chart ──────────────── */
    function last7Days(store) {
        const result   = [];
        const today    = getPhTime(); // ← was new Date()
        const todayStr = todayKey();

        for (let i = 6; i >= 0; i--) {
            const d = new Date(today.getTime()); // clone PH time
            d.setDate(d.getDate() - i);
            const key = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            result.push({
                label:   DAYS[d.getDay()],
                key,
                secs:    store.days[key] || 0,
                isToday: key === todayStr,
            });
        }
        return result;
    }

    function streakDots(store) {
        const result   = [];
        const todayStr = todayKey();
        const today    = getPhTime(); // ← was new Date()
        const activeToday = (store.days[todayStr] || 0) > 60;

        for (let i = 6; i >= 0; i--) {
            const d = new Date(today.getTime()); // clone PH time
            d.setDate(d.getDate() - i);
            const key    = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            const label  = DAYS[d.getDay()];
            const hasSecs = (store.days[key] || 0) > 60;
            const isToday = key === todayStr;

            let cls;
            if (isToday) {
                cls = activeToday ? "today-active" : "today-inactive";
            } else {
                cls = hasSecs ? "done" : "empty";
            }
            result.push({ label, cls });
        }
        return result;
    }

    /* ── Today's pomodoro stats (from FocusMode.state) ─────── */
    function pomoStats() {
        const fm = window.FocusMode;
        if (!fm || !fm.state) return null;
        const s = fm.state;
        const cycles      = s.pomoCycle || 0;
        const focusSecs   = s.totalFocusSecs || 0;
        const shortBreaks = Math.max(0, cycles - Math.floor(cycles / 4));
        const longBreaks  = Math.floor(cycles / 4);

        return {
            rounds:      cycles,
            focusSecs,
            shortBreaks,
            longBreaks,
            avgMins:     cycles > 0 ? Math.round(focusSecs / 60 / cycles) : 0,
        };
    }

    /* ── Live focus time (in-progress session) ──────────────── */
    function liveFocusSecs() {
        const fm = window.FocusMode;
        if (!fm || !fm.state || !fm.state.focusOn) return 0;
        return fm.state.totalFocusSecs || 0;
    }

    /* ── Total focus seconds (all-time from store + live) ───── */
    function totalSecsToday(store) {
        const key   = todayKey();
        const saved = store.days[key] || 0;
        const live  = liveFocusSecs();
        return saved + live;
    }

    /* ── Render ─────────────────────────────────────────────── */
    function render() {
        const el = document.getElementById("sessionTracker");
        if (!el) return;

        const store    = getStore();
        accumulateLiveTime(store);
        saveTrackerStore(store);

        const todaySecs   = totalSecsToday(store);
        const weekData    = last7Days(store);
        const maxSecs     = Math.max(...weekData.map(d => d.secs), 1);
        const streak      = computeStreak(store);
        const dots        = streakDots(store);
        const modeData    = store.modeSecs;
        const totalModeSecs = Object.values(modeData).reduce((a, b) => a + b, 0) || 1;
        const pomo        = pomoStats();
        const sessions    = store.sessionCount || 0;
        const server      = window.__trackerData || {};

        // Cards
        const allTimeSecs = (server.total_seconds || 0);
        const allTimeMins = Math.floor(allTimeSecs / 60);
        const totalDecks  = (window.__focusDecks  || []).length;
        const totalCards  = (window.__focusDecks  || []).reduce((a, d) => a + (d.flashcards || []).length, 0);
        const totalQuizzes = (window.__focusQuizzes || []).length;

        // ── Weekly bar max (use today's live secs too) ──
        weekData[6].secs = Math.max(weekData[6].secs, liveFocusSecs());
        const maxSecsAdj = Math.max(...weekData.map(d => d.secs), 1);

        // Mode labels
        const modeLabels = {
            screenReview:    { label: "Review",     color: "#1a5f7a" },
            screenFlashcard: { label: "Flashcards", color: "#7c4dca" },
            screenQuiz:      { label: "Quiz",       color: "#2a9d8f" },
        };

        el.innerHTML = `
        <div class="tracker-section-label">Session Tracker</div>

        <!-- ── STAT CARDS ── -->
        <div class="tracker-stats-grid">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon">⏱</div>
                <div class="tracker-stat-label">Focus today</div>
                <div class="tracker-stat-value" id="trackerLiveFocus">${fmtMins(todaySecs)}</div>
                <div class="tracker-stat-sub ${todaySecs > 0 ? "positive" : "neutral"}">
                    ${todaySecs > 0 ? "Active session" : "No session yet"}
                </div>
            </div>
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon">🔁</div>
                <div class="tracker-stat-label">Sessions done</div>
                <div class="tracker-stat-value">${sessions}</div>
                <div class="tracker-stat-sub neutral">all time</div>
            </div>
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon">🃏</div>
                <div class="tracker-stat-label">Flashcard decks</div>
                <div class="tracker-stat-value">${totalDecks}</div>
                <div class="tracker-stat-sub neutral">${totalCards} card${totalCards !== 1 ? "s" : ""} total</div>
            </div>
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon">📝</div>
                <div class="tracker-stat-label">Quiz questions</div>
                <div class="tracker-stat-value">${totalQuizzes}</div>
                <div class="tracker-stat-sub neutral">created</div>
            </div>
        </div>

        <!-- ── TWO-COL ── -->
        <div class="tracker-two-col">

            <!-- Weekly Activity -->
            <div class="tracker-card">
                <div class="tracker-card-title">
                    <span class="tracker-card-title-icon">📅</span> Weekly activity
                </div>
                <div class="tracker-bars">
                    ${weekData.map(d => `
                    <div class="tracker-bar-row">
                        <span class="tracker-bar-day ${d.isToday ? "today" : ""}">${d.label}</span>
                        <div class="tracker-bar-track">
                            <div class="tracker-bar-fill ${d.isToday ? "today-bar" : ""}"
                                 style="width:${d.secs > 0 ? Math.round((d.secs / maxSecsAdj) * 100) : 0}%">
                            </div>
                        </div>
                        <span class="tracker-bar-mins">${d.secs > 0 ? fmtMinShort(d.secs) : "—"}</span>
                    </div>`).join("")}
                </div>
            </div>

            <!-- Right column stack -->
            <div style="display:flex;flex-direction:column;gap:14px;">

                <!-- Mode breakdown -->
                <div class="tracker-card">
                    <div class="tracker-card-title">
                        <span class="tracker-card-title-icon">🎯</span> Time by mode
                    </div>
                    <div class="tracker-modes">
                        ${Object.entries(modeLabels).map(([key, meta]) => {
                            const secs = modeData[key] || 0;
                            const pct  = totalModeSecs > 0 ? Math.round((secs / totalModeSecs) * 100) : 0;
                            return `
                            <div class="tracker-mode-row">
                                <div class="tracker-mode-dot" style="background:${meta.color}"></div>
                                <span class="tracker-mode-name">${meta.label}</span>
                                <div class="tracker-mode-bar-track">
                                    <div class="tracker-mode-bar-fill"
                                         style="width:${pct}%;background:${meta.color}"></div>
                                </div>
                                <span class="tracker-mode-time">${secs > 0 ? fmtMinShort(secs) : "—"}</span>
                            </div>`;
                        }).join("")}
                        ${Object.values(modeData).every(v => v === 0)
                            ? `<div class="tracker-mode-empty">No mode time recorded yet</div>`
                            : ""}
                    </div>
                </div>

                <!-- Streak -->
                <div class="tracker-card">
                    <div class="tracker-card-title">
                        <span class="tracker-card-title-icon">🔥</span> Focus streak
                    </div>
                    <div class="tracker-streak-row">
                        ${dots.map(d => `
                        <div class="tracker-streak-day ${d.cls}" title="${d.label}">
                            ${d.cls === "done" ? "✓" : d.label.slice(0,1)}
                        </div>`).join("")}
                    </div>
                    <div class="tracker-streak-meta">
                        ${streak > 0
                            ? `<strong style="color:#111827">${streak}-day streak</strong>`
                            : `<span>No streak yet — start a session!</span>`}
                        ${streak >= 7 ? `<span class="tracker-streak-badge">🏆 7-day best</span>` : ""}
                        ${streak >= 3 && streak < 7 ? `<span class="tracker-streak-badge">⚡ On a roll</span>` : ""}
                    </div>
                </div>

            </div>
        </div>

        <!-- ── POMODORO BREAKDOWN ── -->
        ${pomo ? `
        <div class="tracker-card">
            <div class="tracker-card-title">
                <span class="tracker-card-title-icon">🍅</span> Pomodoro — today's session
            </div>
            <div class="tracker-pomo-rows">
                <div class="tracker-pomo-row">
                    <span class="tracker-pomo-label">Rounds completed</span>
                    <span class="tracker-pomo-val">${pomo.rounds} round${pomo.rounds !== 1 ? "s" : ""}</span>
                </div>
                <div class="tracker-pomo-row">
                    <span class="tracker-pomo-label">Total focus time (pomodoro)</span>
                    <span class="tracker-pomo-val">${fmtMins(pomo.focusSecs)}</span>
                </div>
                <div class="tracker-pomo-row">
                    <span class="tracker-pomo-label">Breaks taken</span>
                    <span class="tracker-pomo-val">${pomo.shortBreaks} short${pomo.longBreaks > 0 ? ", " + pomo.longBreaks + " long" : ""}</span>
                </div>
                <div class="tracker-pomo-row">
                    <span class="tracker-pomo-label">Avg. focus per round</span>
                    <span class="tracker-pomo-val">${pomo.avgMins > 0 ? pomo.avgMins + " min" : "—"}</span>
                </div>
            </div>
        </div>` : ""}
        `;
    }

    /* ── Live ticker: update "Focus today" card every second ── */
    let _tickerInterval = null;

    function startTicker() {
        if (_tickerInterval) return;
        _tickerInterval = setInterval(() => {
            // Only re-render the live card, not the full DOM
            const liveEl = document.getElementById("trackerLiveFocus");
            if (!liveEl) { render(); return; }

            const store   = getStore();
            const secs    = totalSecsToday(store);
            liveEl.textContent = fmtMins(secs);

            // Full re-render every 30s to keep bars/streak fresh
            if (!_tickerInterval._lastFull || Date.now() - _tickerInterval._lastFull > 30000) {
                render();
                _tickerInterval._lastFull = Date.now();
            }
        }, REFRESH_MS);
    }

    function stopTicker() {
        if (_tickerInterval) {
            clearInterval(_tickerInterval);
            _tickerInterval = null;
        }
    }

    /* ── Hook into FocusMode toggle ─────────────────────────── */
    function hookFocusToggle() {
        const fab = document.getElementById("focusToggleBtn");
        if (!fab) return;
        fab.addEventListener("click", () => {
            // After the toggle fires, re-render to reflect new state
            setTimeout(() => {
                const fm = window.FocusMode;
                if (fm && fm.state && fm.state.focusOn) {
                    startTicker();
                } else {
                    stopTicker();
                    render();
                }
            }, 50);
        });
    }

    /* ── Hook into session save ─────────────────────────────── */
    function hookSessionSave() {
        // Patch the FocusMode saveFocusSession call to also update local store
        const origBeforeUnload = window.onbeforeunload;
        window.addEventListener("beforeunload", () => {
            const fm = window.FocusMode;
            if (fm && fm.state && fm.state.totalFocusSecs > 0) {
                window.__onFocusSessionSaved(fm.state.totalFocusSecs);
            }
        });
    }

    /* ── Init ───────────────────────────────────────────────── */
    function init() {
        // Wait for FocusMode to be ready
        if (!window.FocusMode) {
            setTimeout(init, 80);
            return;
        }

        hookFocusToggle();
        hookSessionSave();
        render();
        startTicker(); // always tick; render handles focus-off state gracefully
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
