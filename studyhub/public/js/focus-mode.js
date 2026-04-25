/**
 * focus-mode.js  –  StudyHub Focus Mode
 * Handles: screen navigation, focus mode toggle, music player, sidebar, Pomodoro timer
 */
(function () {
    'use strict';
 
    /* ── Pomodoro Config ───────────────────────────────────────── */
    const POMO = {
        focus:            25 * 60,
        shortBreak:        5 * 60,
        longBreak:        15 * 60,
        cyclesBeforeLong:  4,
    };
 
    /* ── State ─────────────────────────────────────────────────── */
    const state = {
        focusOn:         false,
        musicOn:         false,
        isPlaying:       true,
        currentScreen:   'screenMenu',
        sidebarOpen:     false,
        pomoPhase:       'focus',
        pomoSecondsLeft: POMO.focus,
        pomoInterval:    null,
        pomoRunning:     false,
        pomoCycle:       0,
        totalFocusSecs:  0,
        materials:       Array.isArray(window.__focusMaterials) ? window.__focusMaterials : [],
        flashcards:      Array.isArray(window.__focusFlashcards) ? window.__focusFlashcards : [],
        uploadBusy:      false,
        flashcardBusy:   false,
    };
 
    /* ── DOM refs ──────────────────────────────────────────────── */
    const el = {
        body:             document.body,
        focusToggleBtn:   document.getElementById('focusToggleBtn'),
        lockOpen:         document.getElementById('lockOpen'),
        lockClosed:       document.getElementById('lockClosed'),
        focusFooter:      document.getElementById('focusFooter'),
        musicToggleBtn:   document.getElementById('musicToggleBtn'),
        musicHideBtn:     document.getElementById('musicHideBtn'),
        musicWidget:      document.getElementById('musicWidget'),
        materialsPanel:   document.getElementById('materialsPanel'),
        materialsUploadBtn: document.getElementById('materialsUploadBtn'),
        materialsInput:   document.getElementById('materialsInput'),
        materialsList:    document.getElementById('materialsList'),
        materialsStatus:  document.getElementById('materialsStatus'),
        materialsHelp:    document.getElementById('materialsHelp'),
        flashcardCreateBtn: document.getElementById('flashcardCreateBtn'),
        flashcardForm:    document.getElementById('flashcardForm'),
        flashcardQuestion: document.getElementById('flashcardQuestion'),
        flashcardAnswer:  document.getElementById('flashcardAnswer'),
        flashcardCancelBtn: document.getElementById('flashcardCancelBtn'),
        flashcardSaveBtn: document.getElementById('flashcardSaveBtn'),
        flashcardList:    document.getElementById('flashcardList'),
        flashcardStatus:  document.getElementById('flashcardStatus'),
        playPauseBtn:     document.getElementById('playPauseBtn'),
        playIcon:         document.getElementById('playIcon'),
        pauseIcon:        document.getElementById('pauseIcon'),
        progressFill:     document.getElementById('progressFill'),
        shuffleBtn:       document.getElementById('shuffleBtn'),
        sidebar:          document.getElementById('sidebar'),
        sidebarToggle:    document.getElementById('sidebarToggle'),
        mainContent:      document.getElementById('mainContent'),
        flashcardContent: document.getElementById('flashcardContent'),
    };
 
    /* ── Build Pomodoro Widget ──────────────────────────────────── */
    function buildPomodoroWidget() {
        const w = document.createElement('div');
        w.id = 'pomodoroWidget';
        w.className = 'pomodoro-widget hidden';
        w.dataset.phase = 'focus';
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
                    <span class="pomo-dot"></span>
                    <span class="pomo-dot"></span>
                    <span class="pomo-dot"></span>
                    <span class="pomo-dot"></span>
                </div>
                <div class="pomo-session-label">
                    Round <span id="pomoRoundNum">1</span> / ${POMO.cyclesBeforeLong}
                </div>
            </div>
        `;
        document.body.appendChild(w);
        return w;
    }
 
    let pomoWidget = null;
 
    /* ── Screen navigation ─────────────────────────────────────── */
    function showScreen(id) {
        document.querySelectorAll('.screen').forEach(s => s.classList.add('hidden'));
        const t = document.getElementById(id);
        if (t) t.classList.remove('hidden');
        state.currentScreen = id;
        updateMaterialsPanelVisibility();
        updateMusicWidgetVisibility();
        updateMusicFabVisibility();
    }
 
    document.querySelectorAll('.menu-btn[data-target]').forEach(btn =>
        btn.addEventListener('click', () => showScreen(btn.dataset.target))
    );
    document.querySelectorAll('.back-btn[data-target]').forEach(btn =>
        btn.addEventListener('click', () => showScreen(btn.dataset.target))
    );

    if (el.materialsUploadBtn) {
        el.materialsUploadBtn.addEventListener('click', () => el.materialsInput?.click());
    }

    if (el.materialsInput) {
        el.materialsInput.addEventListener('change', handleMaterialUpload);
    }

    if (el.flashcardCreateBtn) {
        el.flashcardCreateBtn.addEventListener('click', () => {
            el.flashcardForm.classList.remove('hidden');
            el.flashcardCreateBtn.classList.add('hidden');
            el.flashcardQuestion?.focus();
        });
    }

    if (el.flashcardCancelBtn) {
        el.flashcardCancelBtn.addEventListener('click', () => {
            resetFlashcardForm();
            setFlashcardStatus('');
        });
    }

    if (el.flashcardForm) {
        el.flashcardForm.addEventListener('submit', handleFlashcardSubmit);
    }
 
    /* ── Focus Mode Toggle ─────────────────────────────────────── */
    el.focusToggleBtn.addEventListener('click', toggleFocusMode);
 
    function toggleFocusMode() {
        state.focusOn = !state.focusOn;
 
        el.focusToggleBtn.classList.toggle('focus-on', state.focusOn);
        el.focusToggleBtn.setAttribute('aria-pressed', state.focusOn);
        el.lockOpen.classList.toggle('hidden', state.focusOn);
        el.lockClosed.classList.toggle('hidden', !state.focusOn);
        el.focusFooter.classList.toggle('visible', state.focusOn);
        el.body.classList.toggle('focus-mode-on', state.focusOn);
 
        if (state.focusOn) showPomodoroWidget();
        else hidePomodoroWidget();
 
        updateMusicWidgetVisibility();
        updateMusicFabVisibility();
 
        el.focusToggleBtn.animate([
            { transform: 'scale(1)' },
            { transform: 'scale(1.18)' },
            { transform: 'scale(1)' },
        ], { duration: 300, easing: 'ease-out' });
    }
 
    /* ── Show / Hide Pomodoro ───────────────────────────────────── */
    function showPomodoroWidget() {
        if (!pomoWidget) {
            pomoWidget = buildPomodoroWidget();
            bindPomodoroEvents();
        }
        pomoWidget.classList.remove('hidden');
        requestAnimationFrame(() => pomoWidget.classList.add('visible'));
        renderPomodoro();
    }
 
    function hidePomodoroWidget() {
        pausePomodoro();
        if (!pomoWidget) return;
        pomoWidget.classList.remove('visible');
        setTimeout(() => pomoWidget.classList.add('hidden'), 350);
    }
 
    /* ── Bind Events ────────────────────────────────────────────── */
    function bindPomodoroEvents() {
        pomoWidget.querySelectorAll('.pomo-tab').forEach(tab =>
            tab.addEventListener('click', () => switchPhase(tab.dataset.phase))
        );
        document.getElementById('pomoPlayPauseBtn').addEventListener('click', () => {
            state.pomoRunning ? pausePomodoro() : startPomodoro();
        });
        document.getElementById('pomoResetBtn').addEventListener('click', resetPomodoro);
        document.getElementById('pomoSkipBtn').addEventListener('click', advancePhase);
    }
 
    /* ── Pomodoro Engine ────────────────────────────────────────── */
    function switchPhase(phase) {
        pausePomodoro();
        state.pomoPhase       = phase;
        state.pomoSecondsLeft = POMO[phase];
        pomoWidget.querySelectorAll('.pomo-tab').forEach(t =>
            t.classList.toggle('active', t.dataset.phase === phase)
        );
        pomoWidget.dataset.phase = phase;
        renderPomodoro();
    }
 
    function startPomodoro() {
        state.pomoRunning = true;
        setPlayPauseUI(true);
        state.pomoInterval = setInterval(() => {
            state.pomoSecondsLeft--;
            if (state.pomoPhase === 'focus') state.totalFocusSecs++;
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
        if (state.pomoPhase === 'focus') {
            state.pomoCycle++;
            updateDots();
            switchPhase(state.pomoCycle % POMO.cyclesBeforeLong === 0 ? 'longBreak' : 'shortBreak');
        } else {
            switchPhase('focus');
        }
        updateRoundLabel();
    }
 
    function onPhaseComplete() {
        pausePomodoro();
        playChime();
        showNotif(
            state.pomoPhase === 'focus'
                ? '🎉 Focus session complete! Time for a break.'
                : '⚡ Break over! Ready to focus again?'
        );
        if (state.pomoPhase === 'focus') {
            state.pomoCycle++;
            updateDots();
        }
        setTimeout(() => {
            const next = state.pomoPhase === 'focus'
                ? (state.pomoCycle % POMO.cyclesBeforeLong === 0 ? 'longBreak' : 'shortBreak')
                : 'focus';
            switchPhase(next);
            updateRoundLabel();
            startPomodoro();
        }, 2200);
    }
 
    /* ── Render ─────────────────────────────────────────────────── */
    const PHASE_META = {
        focus:      { label: 'Focus Time',    color: '#7c4dca' },
        shortBreak: { label: 'Short Break',   color: '#1eaabb' },
        longBreak:  { label: 'Long Break',    color: '#2a9d8f' },
    };
    const CIRC = 2 * Math.PI * 52; // r = 52
 
    function renderPomodoro() {
        const tEl    = document.getElementById('pomoTimeDisplay');
        const lEl    = document.getElementById('pomoPhaseLabel');
        const ring   = document.getElementById('pomoRingFill');
        if (!tEl) return;
 
        const left    = state.pomoSecondsLeft;
        const total   = POMO[state.pomoPhase];
        const meta    = PHASE_META[state.pomoPhase];
 
        tEl.textContent = `${pad(Math.floor(left / 60))}:${pad(left % 60)}`;
        lEl.textContent = meta.label;
 
        const progress = Math.max(0, left / total);
        ring.style.strokeDasharray  = CIRC;
        ring.style.strokeDashoffset = CIRC * (1 - progress);
        ring.style.stroke           = meta.color;
    }
 
    function setPlayPauseUI(running) {
        const play  = document.getElementById('pomoPlayIcon');
        const pause = document.getElementById('pomoPauseIcon');
        if (!play) return;
        play.classList.toggle('hidden', running);
        pause.classList.toggle('hidden', !running);
    }
 
    function updateDots() {
        const dots = pomoWidget.querySelectorAll('.pomo-dot');
        const filled = state.pomoCycle % POMO.cyclesBeforeLong;
        dots.forEach((d, i) => d.classList.toggle('filled', i < filled));
    }
 
    function updateRoundLabel() {
        const el = document.getElementById('pomoRoundNum');
        if (el) {
            const n = (state.pomoCycle % POMO.cyclesBeforeLong) + 1;
            el.textContent = Math.min(n, POMO.cyclesBeforeLong);
        }
    }
 
    /* ── Notification + Sound ───────────────────────────────────── */
    function showNotif(msg) {
        const n = document.createElement('div');
        n.className = 'pomo-notif';
        n.textContent = msg;
        document.body.appendChild(n);
        requestAnimationFrame(() => n.classList.add('show'));
        setTimeout(() => {
            n.classList.remove('show');
            setTimeout(() => n.remove(), 400);
        }, 3000);
    }
 
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [523.25, 659.25, 783.99].forEach((freq, i) => {
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = freq;
                osc.type = 'sine';
                const t = ctx.currentTime + i * 0.18;
                gain.gain.setValueAtTime(0.28, t);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
                osc.start(t);
                osc.stop(t + 0.5);
            });
        } catch (e) { /* no AudioContext */ }
    }
 
    /* ── Music Toggle ──────────────────────────────────────────── */
    el.musicToggleBtn.addEventListener('click', toggleMusic);
    el.musicHideBtn && el.musicHideBtn.addEventListener('click', () => {
        state.musicOn = false;
        updateMusicWidgetVisibility();
    });
 
    function toggleMusic() {
        state.musicOn = !state.musicOn;
        updateMusicWidgetVisibility();
    }

    function updateMusicNoteAnimation() {
        const shouldAnimate = state.musicOn && state.isPlaying && state.currentScreen !== 'screenMenu';
        el.musicToggleBtn.classList.toggle('is-playing', shouldAnimate);
    }

    function updateMusicWidgetVisibility() {
        if (!el.musicWidget) return;

        const inStudyScreen = state.currentScreen !== 'screenMenu';
        const showPanel = state.musicOn && inStudyScreen;

        el.body.classList.toggle('music-panel-visible', showPanel);
        el.musicToggleBtn.classList.toggle('hidden', !inStudyScreen);
        updateMusicNoteAnimation();

        if (showPanel) {
            el.musicWidget.classList.remove('hidden');
            el.musicWidget.classList.remove('hiding');
        } else {
            el.musicWidget.classList.add('hiding');
            setTimeout(() => {
                el.musicWidget.classList.add('hidden');
                el.musicWidget.classList.remove('hiding');
            }, 280);
        }

        if (el.flashcardContent) {
            el.flashcardContent.classList.remove('expanded');
        }
    }
 
    function updateMusicFabVisibility() {
        updateMusicWidgetVisibility();
    }

    function updateMaterialsPanelVisibility() {
        if (!el.materialsPanel) return;

        const inStudyScreen = state.currentScreen !== 'screenMenu';
        el.materialsPanel.classList.toggle('hidden', !inStudyScreen);

        if (inStudyScreen) {
            renderMaterialsPanel();
        } else {
            setMaterialsStatus('');
        }
    }

    function getScreenLabel(screenId) {
        const labels = {
            screenReview: 'Review',
            screenFlashcard: 'Flashcard',
            screenQuiz: 'Quiz',
        };

        return labels[screenId] || 'Study';
    }

    function getMaterialIcon(type) {
        if (['pdf'].includes(type)) return 'PDF';
        if (['doc', 'docx'].includes(type)) return 'DOC';
        if (['ppt', 'pptx'].includes(type)) return 'PPT';
        return 'FILE';
    }

    function renderMaterialsPanel() {
        if (!el.materialsList) return;

        const currentMaterials = state.materials.filter(item => item.screen === state.currentScreen);

        if (!currentMaterials.length) {
            el.materialsList.innerHTML = `
                <div class="materials-empty">
                    No materials uploaded for ${getScreenLabel(state.currentScreen)} yet.
                </div>
            `;
            return;
        }

        el.materialsList.innerHTML = currentMaterials.map(item => `
            <a class="material-item" href="${item.url}" target="_blank" rel="noopener noreferrer">
                <div class="material-icon">${getMaterialIcon(item.type)}</div>
                <div class="material-info">
                    <div class="material-name">${escapeHtml(item.name)}</div>
                    <div class="material-meta">${getScreenLabel(item.screen)} • ${item.type.toUpperCase()}</div>
                </div>
            </a>
        `).join('');
    }

    function setMaterialsStatus(message, isError = false) {
        if (!el.materialsStatus) return;
        el.materialsStatus.textContent = message;
        el.materialsStatus.classList.toggle('error', isError);
    }

    function setFlashcardStatus(message, isError = false) {
        if (!el.flashcardStatus) return;
        el.flashcardStatus.textContent = message;
        el.flashcardStatus.classList.toggle('error', isError);
    }

    function resetFlashcardForm() {
        if (!el.flashcardForm) return;
        el.flashcardForm.reset();
        el.flashcardForm.classList.add('hidden');
        el.flashcardCreateBtn?.classList.remove('hidden');
    }

    function renderFlashcards() {
        if (!el.flashcardList) return;

        if (!state.flashcards.length) {
            el.flashcardList.innerHTML = '<div class="flashcard-empty">No flashcards yet. Use Create Flashcard to type and upload one.</div>';
            return;
        }

        el.flashcardList.innerHTML = state.flashcards.map((card, index) => `
            <article class="typed-flashcard-item">
                <div class="typed-flashcard-index">Card ${index + 1}</div>
                <div class="typed-flashcard-question">${escapeHtml(card.question)}</div>
                <div class="typed-flashcard-answer">${escapeHtml(card.answer)}</div>
            </article>
        `).join('');
    }

    async function handleFlashcardSubmit(event) {
        event.preventDefault();

        const question = (el.flashcardQuestion?.value || '').trim();
        const answer = (el.flashcardAnswer?.value || '').trim();

        if (!question || !answer) {
            setFlashcardStatus('Please complete both question and answer.', true);
            return;
        }

        try {
            state.flashcardBusy = true;
            if (el.flashcardSaveBtn) el.flashcardSaveBtn.disabled = true;
            setFlashcardStatus('Saving flashcard...');

            const response = await fetch('/focus-mode/flashcards', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ question, answer }),
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload?.message || 'Unable to save flashcard.');
            }

            state.flashcards = payload.flashcards || state.flashcards.concat(payload.flashcard);
            renderFlashcards();
            setFlashcardStatus('Flashcard saved.');
            resetFlashcardForm();
        } catch (error) {
            setFlashcardStatus(error.message || 'Unable to save flashcard.', true);
        } finally {
            state.flashcardBusy = false;
            if (el.flashcardSaveBtn) el.flashcardSaveBtn.disabled = false;
        }
    }

    async function handleMaterialUpload(event) {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        if (state.currentScreen === 'screenMenu') {
            setMaterialsStatus('Choose a study mode first.', true);
            event.target.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('material', file);
        formData.append('screen', state.currentScreen);

        try {
            state.uploadBusy = true;
            el.materialsUploadBtn.disabled = true;
            setMaterialsStatus(`Uploading ${file.name}...`);

            const response = await fetch('/focus-mode/materials', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload?.message || 'Upload failed.');
            }

            state.materials = payload.materials || state.materials.concat(payload.material);
            setMaterialsStatus(`${file.name} uploaded to ${getScreenLabel(state.currentScreen)}.`);
            renderMaterialsPanel();
        } catch (error) {
            setMaterialsStatus(error.message || 'Upload failed.', true);
        } finally {
            state.uploadBusy = false;
            el.materialsUploadBtn.disabled = false;
            event.target.value = '';
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
 
    el.playPauseBtn.addEventListener('click', () => {
        state.isPlaying = !state.isPlaying;
        el.playIcon.classList.toggle('hidden', state.isPlaying);
        el.pauseIcon.classList.toggle('hidden', !state.isPlaying);
        if (el.progressFill) el.progressFill.style.animationPlayState = state.isPlaying ? 'running' : 'paused';
        updateMusicNoteAnimation();
    });
 
    el.shuffleBtn && el.shuffleBtn.addEventListener('click', () => {
        el.shuffleBtn.animate([
            { transform: 'rotate(0deg) scale(1)' },
            { transform: 'rotate(180deg) scale(1.2)' },
            { transform: 'rotate(360deg) scale(1)' },
        ], { duration: 400, easing: 'ease-in-out' });
    });
 
    /* ── Sidebar ───────────────────────────────────────────────── */
    el.sidebarToggle.addEventListener('click', () => {
        state.sidebarOpen = !state.sidebarOpen;
        el.sidebar.classList.toggle('expanded', state.sidebarOpen);
        el.mainContent.classList.toggle('sidebar-open', state.sidebarOpen);
    });
 
    /* ── Helpers ───────────────────────────────────────────────── */
    function pad(n) { return String(n).padStart(2, '0'); }
 
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
 
    function saveFocusSession(secs) {
        if (secs < 1) return;
        fetch('/focus-mode/session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            body: JSON.stringify({ duration: secs }),
        }).catch(() => {});
    }
 
    window.addEventListener('beforeunload', () => {
        if (state.focusOn && state.totalFocusSecs > 0) saveFocusSession(state.totalFocusSecs);
    });
 
    /* ── Init ──────────────────────────────────────────────────── */
    showScreen('screenMenu');
    updateMaterialsPanelVisibility();
    renderFlashcards();
 
})();