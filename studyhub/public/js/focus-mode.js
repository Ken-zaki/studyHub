/**
 * focus-mode.js – StudyHub Focus Mode
 * Sidebar is now the shared @include('layouts.sidebar') — no sidebar logic here.
 * Handles: screen navigation, focus mode toggle, music player, Pomodoro timer
 */
(function () {
    "use strict";

    /* ── Pomodoro Config ─────────────────────────────────────── */
    const POMO = {
        focus: 25 * 60,
        shortBreak: 5 * 60,
        longBreak: 15 * 60,
        cyclesBeforeLong: 4,
    };

    /* ── State ──────────────────────────────────────────────── */
    const state = {
        focusOn: false,
        musicOn: false,
        isPlaying: true,
        currentScreen: "screenMenu",
        pomoPhase: "focus",
        pomoSecondsLeft: POMO.focus,
        pomoInterval: null,
        pomoRunning: false,
        pomoCycle: 0,
        totalFocusSecs: 0,
        materials: Array.isArray(window.__focusMaterials)
            ? window.__focusMaterials
            : [],
        flashcards: Array.isArray(window.__focusFlashcards)
            ? window.__focusFlashcards
            : [],
        uploadBusy: false,
        flashcardBusy: false,
    };

    /* ── DOM refs ───────────────────────────────────────────── */
    const $ = (id) => document.getElementById(id);
    const el = {
        body: document.body,
        focusToggleBtn: $("focusToggleBtn"),
        lockOpen: $("lockOpen"),
        lockClosed: $("lockClosed"),
        focusFooter: $("focusFooter"),
        musicToggleBtn: $("musicToggleBtn"),
        musicHideBtn: $("musicHideBtn"),
        musicWidget: $("musicWidget"),
        materialsPanel: $("materialsPanel"),
        materialsUploadBtn: $("materialsUploadBtn"),
        materialsInput: $("materialsInput"),
        materialsList: $("materialsList"),
        materialsStatus: $("materialsStatus"),
        flashcardCreateBtn: $("flashcardCreateBtn"),
        flashcardForm: $("flashcardForm"),
        flashcardQuestion: $("flashcardQuestion"),
        flashcardAnswer: $("flashcardAnswer"),
        flashcardCancelBtn: $("flashcardCancelBtn"),
        flashcardSaveBtn: $("flashcardSaveBtn"),
        flashcardList: $("flashcardList"),
        flashcardStatus: $("flashcardStatus"),
        playPauseBtn: $("playPauseBtn"),
        playIcon: $("playIcon"),
        pauseIcon: $("pauseIcon"),
        progressFill: $("progressFill"),
        shuffleBtn: $("shuffleBtn"),
        flashcardContent: $("flashcardContent"),
    };

    /* ── CSRF helper (reads from meta tag set in blade) ─────── */
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || "";
    }

    /* ── Build Pomodoro Widget ──────────────────────────────── */
    function buildPomodoroWidget() {
        const w = document.createElement("div");
        w.id = "pomodoroWidget";
        w.className = "pomodoro-widget hidden";
        w.dataset.phase = "focus";
        w.innerHTML = `
            <div class="pomo-phase-tabs">
                <button class="pomo-tab active" data-phase="focus">Focus</button>
                <button class="pomo-tab" data-phase="shortBreak">Short Break</button>
                <button class="pomo-tab" data-phase="longBreak">Long Break</button>
            </div>
            <div class="pomo-ring-wrap">
                <svg class="pomo-ring" viewBox="0 0 120 120">
                    <circle class="pomo-ring-bg"   cx="60" cy="60" r="52"/>
                    <circle class="pomo-ring-fill" cx="60" cy="60" r="52" id="pomoRingFill"/>
                </svg>
                <div class="pomo-ring-inner">
                    <div class="pomo-time" id="pomoTimeDisplay">25:00</div>
                    <div class="pomo-phase-label" id="pomoPhaseLabel">Focus Time</div>
                </div>
            </div>
            <div class="pomo-controls">
                <button class="pomo-btn pomo-reset" id="pomoResetBtn" title="Reset">
                    <svg viewBox="0 0 24 24"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>
                </button>
                <button class="pomo-btn pomo-main" id="pomoPlayPauseBtn" title="Start / Pause">
                    <svg viewBox="0 0 24 24" id="pomoPlayIcon"><path d="M8 5v14l11-7z"/></svg>
                    <svg viewBox="0 0 24 24" id="pomoPauseIcon" class="hidden"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                </button>
                <button class="pomo-btn pomo-skip" id="pomoSkipBtn" title="Skip phase">
                    <svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zm8.5-6L6 6v12l8.5-6zM16 6v12h2V6z"/></svg>
                </button>
            </div>
            <div class="pomo-cycle-wrap">
                <div class="pomo-cycle-dots" id="pomoCycleDots">
                    <span class="pomo-dot"></span><span class="pomo-dot"></span>
                    <span class="pomo-dot"></span><span class="pomo-dot"></span>
                </div>
                <div class="pomo-session-label">Round <span id="pomoRoundNum">1</span> / ${POMO.cyclesBeforeLong}</div>
            </div>`;
        document.body.appendChild(w);
        return w;
    }

    let pomoWidget = null;

    /* ── Screen navigation ──────────────────────────────────── */
    function showScreen(id) {
        document
            .querySelectorAll(".screen")
            .forEach((s) => s.classList.add("hidden"));
        const t = document.getElementById(id);
        if (t) t.classList.remove("hidden");
        state.currentScreen = id;
        updateMaterialsPanelVisibility();
        updateMusicFabVisibility();
    }

    document
        .querySelectorAll(".menu-btn[data-target]")
        .forEach((btn) =>
            btn.addEventListener("click", () => showScreen(btn.dataset.target)),
        );
    document
        .querySelectorAll(".back-btn[data-target]")
        .forEach((btn) =>
            btn.addEventListener("click", () => showScreen(btn.dataset.target)),
        );

    /* ── Materials ──────────────────────────────────────────── */
    el.materialsUploadBtn?.addEventListener("click", () =>
        el.materialsInput?.click(),
    );
    el.materialsInput?.addEventListener("change", handleMaterialUpload);

    function updateMaterialsPanelVisibility() {
        if (!el.materialsPanel) return;
        const inStudy = state.currentScreen !== "screenMenu";
        el.materialsPanel.classList.toggle("hidden", !inStudy);
        if (inStudy) renderMaterialsPanel();
        else setMaterialsStatus("");
    }

    function getScreenLabel(id) {
        return (
            {
                screenReview: "Review",
                screenFlashcard: "Flashcard",
                screenQuiz: "Quiz",
            }[id] || "Study"
        );
    }

    function getMaterialIcon(ext) {
        if (ext === "pdf") return "PDF";
        if (["doc", "docx"].includes(ext)) return "DOC";
        if (["ppt", "pptx"].includes(ext)) return "PPT";
        return "FILE";
    }

    function renderMaterialsPanel() {
        if (!el.materialsList) return;
        const list = state.materials.filter(
            (m) => m.screen === state.currentScreen,
        );
        if (!list.length) {
            el.materialsList.innerHTML = `<div class="materials-empty">No materials uploaded for ${getScreenLabel(state.currentScreen)} yet.</div>`;
            return;
        }
        el.materialsList.innerHTML = list
            .map(
                (m) => `
            <a class="material-item" href="${m.url}" target="_blank" rel="noopener noreferrer">
                <div class="material-icon">${getMaterialIcon(m.type)}</div>
                <div class="material-info">
                    <div class="material-name">${escHtml(m.name)}</div>
                    <div class="material-meta">${getScreenLabel(m.screen)} · ${m.type.toUpperCase()}</div>
                </div>
            </a>`,
            )
            .join("");
    }

    function setMaterialsStatus(msg, isError = false) {
        if (!el.materialsStatus) return;
        el.materialsStatus.textContent = msg;
        el.materialsStatus.classList.toggle("error", isError);
    }

    async function handleMaterialUpload(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        if (state.currentScreen === "screenMenu") {
            setMaterialsStatus("Choose a study mode first.", true);
            e.target.value = "";
            return;
        }
        const fd = new FormData();
        fd.append("material", file);
        fd.append("screen", state.currentScreen);
        try {
            state.uploadBusy = true;
            el.materialsUploadBtn.disabled = true;
            setMaterialsStatus(`Uploading ${file.name}…`);
            const res = await fetch("/focus-mode/materials", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                },
                body: fd,
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Upload failed.");
            state.materials = payload.materials || [
                ...state.materials,
                payload.material,
            ];
            setMaterialsStatus(`${file.name} uploaded.`);
            renderMaterialsPanel();
        } catch (err) {
            setMaterialsStatus(err.message || "Upload failed.", true);
        } finally {
            state.uploadBusy = false;
            el.materialsUploadBtn.disabled = false;
            e.target.value = "";
        }
    }

    /* ── Flashcards ─────────────────────────────────────────── */
    el.flashcardCreateBtn?.addEventListener("click", () => {
        el.flashcardForm.classList.remove("hidden");
        el.flashcardCreateBtn.classList.add("hidden");
        el.flashcardQuestion?.focus();
    });
    el.flashcardCancelBtn?.addEventListener("click", () => {
        resetFlashcardForm();
        setFlashcardStatus("");
    });
    el.flashcardForm?.addEventListener("submit", handleFlashcardSubmit);

    function setFlashcardStatus(msg, isError = false) {
        if (!el.flashcardStatus) return;
        el.flashcardStatus.textContent = msg;
        el.flashcardStatus.classList.toggle("error", isError);
    }
    function resetFlashcardForm() {
        el.flashcardForm?.reset();
        el.flashcardForm?.classList.add("hidden");
        el.flashcardCreateBtn?.classList.remove("hidden");
    }
    function renderFlashcards() {
        if (!el.flashcardList) return;
        if (!state.flashcards.length) {
            el.flashcardList.innerHTML =
                '<div class="flashcard-empty">No flashcards yet. Click "Create Flashcard" to add one.</div>';
            return;
        }
        el.flashcardList.innerHTML = state.flashcards
            .map(
                (c, i) => `
            <article class="typed-flashcard-item">
                <div class="typed-flashcard-index">Card ${i + 1}</div>
                <div class="typed-flashcard-question">${escHtml(c.question)}</div>
                <div class="typed-flashcard-answer">${escHtml(c.answer)}</div>
            </article>`,
            )
            .join("");
    }
    async function handleFlashcardSubmit(e) {
        e.preventDefault();
        const question = (el.flashcardQuestion?.value || "").trim();
        const answer = (el.flashcardAnswer?.value || "").trim();
        if (!question || !answer) {
            setFlashcardStatus("Please fill in both fields.", true);
            return;
        }
        try {
            state.flashcardBusy = true;
            if (el.flashcardSaveBtn) el.flashcardSaveBtn.disabled = true;
            setFlashcardStatus("Saving…");
            const res = await fetch("/focus-mode/flashcards", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ question, answer }),
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Save failed.");
            state.flashcards = payload.flashcards || [
                ...state.flashcards,
                payload.flashcard,
            ];
            renderFlashcards();
            setFlashcardStatus("Flashcard saved!");
            resetFlashcardForm();
        } catch (err) {
            setFlashcardStatus(err.message || "Save failed.", true);
        } finally {
            state.flashcardBusy = false;
            if (el.flashcardSaveBtn) el.flashcardSaveBtn.disabled = false;
        }
    }

    /* ── Focus Mode Toggle ──────────────────────────────────── */
    el.focusToggleBtn.addEventListener("click", toggleFocusMode);
    function toggleFocusMode() {
        state.focusOn = !state.focusOn;
        el.focusToggleBtn.classList.toggle("focus-on", state.focusOn);
        el.focusToggleBtn.setAttribute("aria-pressed", state.focusOn);
        el.lockOpen.classList.toggle("hidden", state.focusOn);
        el.lockClosed.classList.toggle("hidden", !state.focusOn);
        el.focusFooter.classList.toggle("visible", state.focusOn);
        el.body.classList.toggle("focus-mode-on", state.focusOn);
        state.focusOn ? showPomodoroWidget() : hidePomodoroWidget();
        updateMusicFabVisibility();
        el.focusToggleBtn.animate(
            [
                { transform: "scale(1)" },
                { transform: "scale(1.18)" },
                { transform: "scale(1)" },
            ],
            { duration: 300, easing: "ease-out" },
        );
    }

    /* ── Pomodoro ───────────────────────────────────────────── */
    function showPomodoroWidget() {
        if (!pomoWidget) {
            pomoWidget = buildPomodoroWidget();
            bindPomodoroEvents();
        }
        pomoWidget.classList.remove("hidden");
        requestAnimationFrame(() => pomoWidget.classList.add("visible"));
        renderPomodoro();
    }
    function hidePomodoroWidget() {
        pausePomodoro();
        if (!pomoWidget) return;
        pomoWidget.classList.remove("visible");
        setTimeout(() => pomoWidget.classList.add("hidden"), 350);
    }
    function bindPomodoroEvents() {
        pomoWidget
            .querySelectorAll(".pomo-tab")
            .forEach((tab) =>
                tab.addEventListener("click", () =>
                    switchPhase(tab.dataset.phase),
                ),
            );
        $("pomoPlayPauseBtn").addEventListener("click", () =>
            state.pomoRunning ? pausePomodoro() : startPomodoro(),
        );
        $("pomoResetBtn").addEventListener("click", resetPomodoro);
        $("pomoSkipBtn").addEventListener("click", advancePhase);
    }
    function switchPhase(phase) {
        pausePomodoro();
        state.pomoPhase = phase;
        state.pomoSecondsLeft = POMO[phase];
        pomoWidget
            .querySelectorAll(".pomo-tab")
            .forEach((t) =>
                t.classList.toggle("active", t.dataset.phase === phase),
            );
        pomoWidget.dataset.phase = phase;
        renderPomodoro();
    }
    function startPomodoro() {
        state.pomoRunning = true;
        setPlayPauseUI(true);
        state.pomoInterval = setInterval(() => {
            state.pomoSecondsLeft--;
            if (state.pomoPhase === "focus") state.totalFocusSecs++;
            if (state.pomoSecondsLeft <= 0) onPhaseComplete();
            else renderPomodoro();
        }, 1000);
    }
    function pausePomodoro() {
        state.pomoRunning = false;
        clearInterval(state.pomoInterval);
        state.pomoInterval = null;
        setPlayPauseUI(false);
    }
    function resetPomodoro() {
        pausePomodoro();
        state.pomoSecondsLeft = POMO[state.pomoPhase];
        renderPomodoro();
    }
    function advancePhase() {
        pausePomodoro();
        if (state.pomoPhase === "focus") {
            state.pomoCycle++;
            updateDots();
            switchPhase(
                state.pomoCycle % POMO.cyclesBeforeLong === 0
                    ? "longBreak"
                    : "shortBreak",
            );
        } else {
            switchPhase("focus");
        }
        updateRoundLabel();
    }
    function onPhaseComplete() {
        pausePomodoro();
        playChime();
        showNotif(
            state.pomoPhase === "focus"
                ? "🎉 Focus session complete! Time for a break."
                : "⚡ Break over! Ready to focus again?",
        );
        if (state.pomoPhase === "focus") {
            state.pomoCycle++;
            updateDots();
        }
        setTimeout(() => {
            const next =
                state.pomoPhase === "focus"
                    ? state.pomoCycle % POMO.cyclesBeforeLong === 0
                        ? "longBreak"
                        : "shortBreak"
                    : "focus";
            switchPhase(next);
            updateRoundLabel();
            startPomodoro();
        }, 2200);
    }

    const PHASE_META = {
        focus: { label: "Focus Time", color: "#7c4dca" },
        shortBreak: { label: "Short Break", color: "#1eaabb" },
        longBreak: { label: "Long Break", color: "#2a9d8f" },
    };
    const CIRC = 2 * Math.PI * 52;

    function renderPomodoro() {
        const tEl = $("pomoTimeDisplay");
        const lEl = $("pomoPhaseLabel");
        const ring = $("pomoRingFill");
        if (!tEl) return;
        const left = state.pomoSecondsLeft;
        const total = POMO[state.pomoPhase];
        const meta = PHASE_META[state.pomoPhase];
        tEl.textContent = `${pad(Math.floor(left / 60))}:${pad(left % 60)}`;
        lEl.textContent = meta.label;
        ring.style.strokeDasharray = CIRC;
        ring.style.strokeDashoffset = CIRC * (1 - Math.max(0, left / total));
        ring.style.stroke = meta.color;
    }
    function setPlayPauseUI(running) {
        const play = $("pomoPlayIcon");
        const pause = $("pomoPauseIcon");
        if (!play) return;
        play.classList.toggle("hidden", running);
        pause.classList.toggle("hidden", !running);
    }
    function updateDots() {
        const dots = pomoWidget.querySelectorAll(".pomo-dot");
        const filled = state.pomoCycle % POMO.cyclesBeforeLong;
        dots.forEach((d, i) => d.classList.toggle("filled", i < filled));
    }
    function updateRoundLabel() {
        const el = $("pomoRoundNum");
        if (el)
            el.textContent = Math.min(
                (state.pomoCycle % POMO.cyclesBeforeLong) + 1,
                POMO.cyclesBeforeLong,
            );
    }

    /* ── Notifications + Sound ──────────────────────────────── */
    function showNotif(msg) {
        const n = document.createElement("div");
        n.className = "pomo-notif";
        n.textContent = msg;
        document.body.appendChild(n);
        requestAnimationFrame(() => n.classList.add("show"));
        setTimeout(() => {
            n.classList.remove("show");
            setTimeout(() => n.remove(), 400);
        }, 3000);
    }
    function playChime() {
        try {
            const ctx = new (
                window.AudioContext || window.webkitAudioContext
            )();
            [523.25, 659.25, 783.99].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const g = ctx.createGain();
                osc.connect(g);
                g.connect(ctx.destination);
                osc.frequency.value = freq;
                osc.type = "sine";
                const t = ctx.currentTime + i * 0.18;
                g.gain.setValueAtTime(0.28, t);
                g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
                osc.start(t);
                osc.stop(t + 0.5);
            });
        } catch (e) {}
    }

    /* ── Music ──────────────────────────────────────────────── */
    el.musicToggleBtn.addEventListener("click", () => {
        state.musicOn = !state.musicOn;
        updateMusicFabVisibility();
    });
    el.musicHideBtn?.addEventListener("click", () => {
        state.musicOn = false;
        updateMusicFabVisibility();
    });
    function updateMusicFabVisibility() {
        const inStudy = state.currentScreen !== "screenMenu";
        el.musicToggleBtn.classList.toggle("hidden", !inStudy);
        el.musicToggleBtn.classList.toggle(
            "is-playing",
            state.musicOn && state.isPlaying && inStudy,
        );

        if (!el.musicWidget) return;
        const show = state.musicOn && inStudy;
        el.body.classList.toggle("music-panel-visible", show);
        if (show) {
            el.musicWidget.classList.remove("hidden", "hiding");
        } else {
            el.musicWidget.classList.add("hiding");
            setTimeout(() => {
                el.musicWidget.classList.add("hidden");
                el.musicWidget.classList.remove("hiding");
            }, 280);
        }
    }

    el.playPauseBtn?.addEventListener("click", () => {
        state.isPlaying = !state.isPlaying;
        el.playIcon?.classList.toggle("hidden", state.isPlaying);
        el.pauseIcon?.classList.toggle("hidden", !state.isPlaying);
        if (el.progressFill)
            el.progressFill.style.animationPlayState = state.isPlaying
                ? "running"
                : "paused";
        el.musicToggleBtn.classList.toggle(
            "is-playing",
            state.musicOn &&
                state.isPlaying &&
                state.currentScreen !== "screenMenu",
        );
    });
    el.shuffleBtn?.addEventListener("click", () => {
        el.shuffleBtn.animate(
            [
                { transform: "rotate(0deg) scale(1)" },
                { transform: "rotate(180deg) scale(1.2)" },
                { transform: "rotate(360deg) scale(1)" },
            ],
            { duration: 400, easing: "ease-in-out" },
        );
    });

    /* ── Session save ───────────────────────────────────────── */
    function saveFocusSession(secs) {
        if (secs < 1) return;
        fetch("/focus-mode/session", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": getCsrfToken(),
            },
            body: JSON.stringify({ duration: secs }),
        }).catch(() => {});
    }
    window.addEventListener("beforeunload", () => {
        if (state.focusOn && state.totalFocusSecs > 0)
            saveFocusSession(state.totalFocusSecs);
    });

    /* ── Utils ──────────────────────────────────────────────── */
    function pad(n) {
        return String(n).padStart(2, "0");
    }
    function escHtml(v) {
        return String(v)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    /* ── Init ───────────────────────────────────────────────── */
    showScreen("screenMenu");
    renderFlashcards();
})();
