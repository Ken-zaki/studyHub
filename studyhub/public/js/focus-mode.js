/**
 * focus-mode.js – StudyHub Focus Mode
 * Fixed: menu button navigation, deck segregation for flashcards,
 *        persistent DB-backed decks & flashcards.
 * Updated: simplified music player (on/off only, DB-backed default track)
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
        isPlaying: false,
        currentScreen: "screenMenu",
        screenHistory: [],
        pomoPhase: "focus",
        pomoSecondsLeft: POMO.focus,
        pomoInterval: null,
        pomoRunning: false,
        pomoCycle: 0,
        pomoPosition: null,
        totalFocusSecs: 0,
        materials: Array.isArray(window.__focusMaterials) ? window.__focusMaterials : [],
        decks: Array.isArray(window.__focusDecks) ? window.__focusDecks : [],
        quizzes: Array.isArray(window.__focusQuizzes) ? window.__focusQuizzes : [],
        activeDeckId: null,
        flashcardIndex: 0,
        uploadBusy: false,
        deckBusy: false,
        flashcardBusy: false,
        quizBusy: false,
    };

    /* ── DOM refs ───────────────────────────────────────────── */
    const $ = (id) => document.getElementById(id);
    const el = {
        body: document.body,
        // Focus / lock
        focusToggleBtn:              $("focusToggleBtn"),
        lockOpen:                    $("lockOpen"),
        lockClosed:                  $("lockClosed"),
        focusFooter:                 $("focusFooter"),
        // Music
        musicToggleBtn:              $("musicToggleBtn"),
        musicHideBtn:                $("musicHideBtn"),
        musicWidget:                 $("musicWidget"),
        playPauseBtn:                $("playPauseBtn"),
        playIcon:                    $("playIcon"),
        pauseIcon:                   $("pauseIcon"),
        progressFill:                $("progressFill"),
        // Materials panel
        materialsPanel:              $("materialsPanel"),
        materialsInput:              $("materialsInput"),
        materialsList:               $("materialsList"),
        materialsStatus:             $("materialsStatus"),
        // Deck browser
        deckBrowser:                 $("deckBrowser"),
        deckGrid:                    $("deckGrid"),
        deckCreateBtn:               $("deckCreateBtn"),
        deckModalOverlay:            $("deckModalOverlay") || $("deckCreateBackdrop"),
        deckNameInput:               $("deckNameInput"),
        deckCancelBtn:               $("deckCancelBtn"),
        deckSaveBtn:                 $("deckSaveBtn"),
        deckStatus:                  $("deckStatus"),
        // Deck content
        deckContent:                 $("deckContent"),
        deckBackBtn:                 $("deckBackBtn"),
        deckContentTitle:            $("deckContentTitle"),
        deckContentDesc:             $("deckContentDesc"),
        flashcardScreenBackBtn:      $("flashcardScreenBackBtn"),
        // Flashcard action buttons
        flashcardCreatePromptBtn:    $("flashcardCreatePromptBtn"),
        // Flashcard slider
        flashcardStageTrack:         $("flashcardStageTrack"),
        flashcardStageCounter:       $("flashcardStageCounter"),
        flashcardPrevBtn:            $("flashcardPrevBtn"),
        flashcardNextBtn:            $("flashcardNextBtn"),
        // Flashcard modal
        flashcardModal:              $("flashcardModal"),
        flashcardModalBackdrop:      $("flashcardModalBackdrop"),
        flashcardModalCloseBtn:      $("flashcardModalCloseBtn"),
        flashcardUploadPane:         $("flashcardUploadPane"),
        flashcardCreatePane:         $("flashcardCreatePane"),
        flashcardMaterialsUploadBtn: $("flashcardMaterialsUploadBtn"),
        flashcardMaterialsInput:     $("flashcardMaterialsInput"),
        flashcardMaterialsStatus:    $("flashcardMaterialsStatus"),
        flashcardMaterialsList:      $("flashcardMaterialsList"),
        flashcardForm:               $("flashcardForm"),
        flashcardQuestion:           $("flashcardQuestion"),
        flashcardAnswer:             $("flashcardAnswer"),
        flashcardCancelBtn:          $("flashcardCancelBtn"),
        flashcardSaveBtn:            $("flashcardSaveBtn"),
        flashcardStatus:             $("flashcardStatus"),
        // Review upload
        reviewUploadBtn:             $("reviewUploadBtn"),
        // Quiz modal
        quizUploadPromptBtn:         $("quizUploadPromptBtn"),
        quizCreatePromptBtn:         $("quizCreatePromptBtn"),
        quizModal:                   $("quizModal"),
        quizModalBackdrop:           $("quizModalBackdrop"),
        quizModalCloseBtn:           $("quizModalCloseBtn"),
        quizUploadPane:              $("quizUploadPane"),
        quizCreatePane:              $("quizCreatePane"),
        quizMaterialsUploadBtn:      $("quizMaterialsUploadBtn"),
        quizMaterialsInput:          $("quizMaterialsInput"),
        quizMaterialsStatus:         $("quizMaterialsStatus"),
        quizMaterialsList:           $("quizMaterialsList"),
        quizForm:                    $("quizForm"),
        quizQuestion:                $("quizQuestion"),
        quizOptionA:                 $("quizOptionA"),
        quizOptionB:                 $("quizOptionB"),
        quizOptionC:                 $("quizOptionC"),
        quizOptionD:                 $("quizOptionD"),
        quizCorrectOption:           $("quizCorrectOption"),
        quizExplanation:             $("quizExplanation"),
        quizCancelBtn:               $("quizCancelBtn"),
        quizSaveBtn:                 $("quizSaveBtn"),
        quizStageTrack:              $("quizStageTrack"),
        quizStageCounter:            $("quizStageCounter"),
        quizPrevBtn:                 $("quizPrevBtn"),
        quizNextBtn:                 $("quizNextBtn"),
        quizStatus:                  $("quizStatus"),
        topBarFocusBackBtn:          $("focusTopBackBtn"),
    };

    // Plugin API
    window.FocusMode = window.FocusMode || {};
    window.FocusMode.state = state;
    window.FocusMode.el = el;
    window.FocusMode.register = function (name, initFn) {
        try {
            if (typeof initFn === "function") initFn(window.FocusMode.state, window.FocusMode.el);
        } catch (err) {
            console.error("FocusMode plugin error", name, err);
        }
    };
    window.FocusMode.getCsrfToken = getCsrfToken;
    window.FocusMode.escHtml = escHtml;
    window.FocusMode.renderFlashcardSlider = function (cards) {
        if (typeof renderFlashcardSlider === "function") return renderFlashcardSlider(cards);
    };
    window.FocusMode.renderQuizSlider = function (questions) {
        // quiz.js overrides this — intentional no-op stub
    };
    window.FocusMode.showQuizSetUI = function () { /* handled by quiz.js */ };
    window.FocusMode.hideQuizSetUI = function () { /* handled by quiz.js */ };

    /* ── CSRF helper ────────────────────────────────────────── */
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || "";
    }

    /* ── Utils ──────────────────────────────────────────────── */
    function pad(n) { return String(n).padStart(2, "0"); }
    function escHtml(v) {
        return String(v)
            .replaceAll("&", "&amp;").replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;").replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    /* ═══════════════════════════════════════════════════════════
       SCREEN NAVIGATION
    ═══════════════════════════════════════════════════════════ */
    function showScreen(id, options = {}) {
        const fromHistory = options.fromHistory === true;

        // Restrict navigation while focus mode is on; derive allowed targets from menu.
        // screenMaterialViewer is always allowed so the file viewer works in focus mode.
        if (state.focusOn) {
            const menu = document.getElementById('screenMenu');
            let allowed = [
                "screenMenu",
                "screenReview",
                "screenFlashcard",
                "screenQuiz",
                "screenMaterialViewer", // ← FIXED: viewer must always be reachable
            ];
            if (menu) {
                const btns = Array.from(menu.querySelectorAll('.menu-btn[data-target]'));
                const targets = btns.map(b => b.getAttribute('data-target')).filter(Boolean);
                if (targets.length) {
                    allowed = Array.from(new Set([
                        "screenMenu",
                        "screenMaterialViewer", // always keep viewer allowed
                        ...targets,
                    ]));
                }
            }
            if (!allowed.includes(id)) return;
        }

        if (!fromHistory && state.currentScreen && state.currentScreen !== id) {
            state.screenHistory.push(state.currentScreen);
            if (state.screenHistory.length > 30) state.screenHistory.shift();
        }

        document.querySelectorAll(".screen").forEach((s) => s.classList.add("hidden"));
        const t = document.getElementById(id);
        if (t) t.classList.remove("hidden");
        state.currentScreen = id;
        updateMaterialsPanelVisibility();
        updateMusicFabVisibility();
        updateFocusTopBackButtonVisibility();
    }

    function updateFocusTopBackButtonVisibility() {
        if (!el.topBarFocusBackBtn) return;
        const showBackButton = state.currentScreen && state.currentScreen !== 'screenMenu';
        el.topBarFocusBackBtn.classList.toggle('hidden', !showBackButton);
    }

    function goToPreviousFocusScreen() {
        if (state.screenHistory.length) {
            const previous = state.screenHistory.pop();
            if (previous) showScreen(previous, { fromHistory: true });
            return;
        }

        showScreen('screenMenu', { fromHistory: true });
    }

    el.topBarFocusBackBtn?.addEventListener("click", goToPreviousFocusScreen);
    updateFocusTopBackButtonVisibility();

    document.querySelectorAll(".menu-btn[data-target]").forEach((btn) =>
        btn.addEventListener("click", () => showScreen(btn.dataset.target))
    );
    document.querySelectorAll(".focus-back-btn[data-target], .back-btn[data-target]").forEach((btn) =>
        btn.addEventListener("click", () => showScreen(btn.dataset.target))
    );

    /* ═══════════════════════════════════════════════════════════
       MATERIALS PANEL
    ═══════════════════════════════════════════════════════════ */
    el.reviewUploadBtn?.addEventListener("click", () => el.materialsInput?.click());
    el.materialsInput?.addEventListener("change", handleMaterialUpload);

    function updateMaterialsPanelVisibility() {
        if (!el.materialsPanel) return;
        // Hide the materials panel when on the menu or when the full-screen viewer is open
        const inStudy = state.currentScreen !== "screenMenu"
                     && state.currentScreen !== "screenMaterialViewer"; // ← FIXED
        el.materialsPanel.classList.toggle("hidden", !inStudy);
        if (inStudy) renderMaterialsPanel();
        else setMaterialsStatus("");
    }

    function getScreenLabel(id) {
        return ({ screenReview: "Review", screenFlashcard: "Flashcard", screenQuiz: "Quiz" }[id] || "Study");
    }

    function getMaterialIcon(ext) {
        if (ext === "pdf") return "PDF";
        if (["doc", "docx"].includes(ext)) return "DOC";
        if (["ppt", "pptx"].includes(ext)) return "PPT";
        return "FILE";
    }

    function renderMaterialsPanel() {
        if (!el.materialsList) return;
        const list = state.materials.filter((m) => m.screen === state.currentScreen);
        if (!list.length) {
            el.materialsList.innerHTML = `<div class="materials-empty">No materials uploaded for ${getScreenLabel(state.currentScreen)} yet.</div>`;
            return;
        }
        el.materialsList.innerHTML = list.map((m) => `
            <a class="material-item" href="${m.url}" target="_blank" rel="noopener noreferrer">
                <div class="material-icon">${getMaterialIcon(m.type)}</div>
                <div class="material-info">
                    <div class="material-name">${escHtml(m.name)}</div>
                    <div class="material-meta">${getScreenLabel(m.screen)} · ${m.type.toUpperCase()}</div>
                </div>
            </a>`).join("");
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
            setMaterialsStatus(`Uploading ${file.name}…`);
            const res = await fetch("/focus-mode/materials", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json" },
                body: fd,
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Upload failed.");
            state.materials = payload.materials || [...state.materials, payload.material];
            setMaterialsStatus(`${file.name} uploaded.`);
            renderMaterialsPanel();
        } catch (err) {
            setMaterialsStatus(err.message || "Upload failed.", true);
        } finally {
            state.uploadBusy = false;
            e.target.value = "";
        }
    }

    /* ═══════════════════════════════════════════════════════════
       FLASHCARD MODAL
    ═══════════════════════════════════════════════════════════ */
    function openFlashcardModal(pane) {
        if (!el.flashcardModal) return;
        el.flashcardModal.classList.remove("hidden");
        el.flashcardModal.setAttribute("aria-hidden", "false");
        el.flashcardUploadPane?.classList.toggle("hidden", pane !== "upload");
        el.flashcardCreatePane?.classList.toggle("hidden", pane !== "create");
    }
    function closeFlashcardModal() {
        if (!el.flashcardModal) return;
        el.flashcardModal.classList.add("hidden");
        el.flashcardModal.setAttribute("aria-hidden", "true");
    }

    el.flashcardCreatePromptBtn?.addEventListener("click", () => openFlashcardModal("create"));
    el.flashcardModalCloseBtn?.addEventListener("click",   closeFlashcardModal);
    el.flashcardModalBackdrop?.addEventListener("click",   closeFlashcardModal);

    el.flashcardMaterialsUploadBtn?.addEventListener("click", () => el.flashcardMaterialsInput?.click());
    el.flashcardMaterialsInput?.addEventListener("change", async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const fd = new FormData();
        fd.append("material", file);
        fd.append("screen", "screenFlashcard");
        try {
            if (el.flashcardMaterialsStatus) el.flashcardMaterialsStatus.textContent = `Uploading ${file.name}…`;
            const res = await fetch("/focus-mode/materials", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json" },
                body: fd,
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Upload failed.");
            state.materials = payload.materials || [...state.materials, payload.material];
            if (el.flashcardMaterialsStatus) el.flashcardMaterialsStatus.textContent = `${file.name} uploaded.`;
        } catch (err) {
            if (el.flashcardMaterialsStatus) el.flashcardMaterialsStatus.textContent = err.message || "Upload failed.";
        } finally {
            e.target.value = "";
        }
    });

    el.flashcardCancelBtn?.addEventListener("click", () => {
        el.flashcardForm?.reset();
        setFlashcardStatus("");
        closeFlashcardModal();
    });
    el.flashcardForm?.addEventListener("submit", handleFlashcardSubmit);

    function setFlashcardStatus(msg, isError = false) {
        if (!el.flashcardStatus) return;
        el.flashcardStatus.textContent = msg;
        el.flashcardStatus.classList.toggle("error", isError);
    }

    async function handleFlashcardSubmit(e) {
        e.preventDefault();
        if (!state.activeDeckId) {
            setFlashcardStatus("No deck selected.", true);
            return;
        }
        const question = (el.flashcardQuestion?.value || "").trim();
        const answer   = (el.flashcardAnswer?.value   || "").trim();
        if (!question || !answer) {
            setFlashcardStatus("Please fill in both fields.", true);
            return;
        }
        try {
            if (String(state.activeDeckId).startsWith("local-")) {
                const localDeck = state.decks.find((d) => d.id === state.activeDeckId);
                if (!localDeck) { setFlashcardStatus("Deck not found.", true); return; }
                try {
                    setFlashcardStatus("Syncing deck to server…");
                    const r = await fetch("/focus-mode/decks", {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": getCsrfToken(),
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ name: localDeck.name, description: localDeck.description || "" }),
                    });
                    const p = await r.json().catch(() => null);
                    if (!r.ok || !p || !p.deck) throw new Error(p?.message || "Sync failed");
                    state.decks = state.decks.map((d) => d.id === localDeck.id ? p.deck : d);
                    state.activeDeckId = p.deck.id;
                    setFlashcardStatus("Deck synced.");
                } catch (syncErr) {
                    setFlashcardStatus("Could not sync deck to server. Try again later.", true);
                    return;
                }
            }

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
                body: JSON.stringify({ deck_id: state.activeDeckId, question, answer }),
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Save failed.");

            const deck = state.decks.find((d) => d.id === state.activeDeckId);
            if (deck) deck.flashcards = payload.flashcards || [...(deck.flashcards || []), payload.flashcard];

            renderFlashcardSlider(deck?.flashcards || []);
            setFlashcardStatus("Flashcard saved!");
            el.flashcardForm?.reset();
            setTimeout(closeFlashcardModal, 800);
        } catch (err) {
            setFlashcardStatus(err.message || "Save failed.", true);
        } finally {
            state.flashcardBusy = false;
            if (el.flashcardSaveBtn) el.flashcardSaveBtn.disabled = false;
        }
    }

    /* ── Flashcard Slider ───────────────────────────────────── */
    function renderFlashcardSlider(cards) {
        if (!el.flashcardStageTrack) return;
        state.flashcardIndex = 0;

        if (!cards.length) {
            el.flashcardStageTrack.innerHTML = `<div class="flashcard-empty-slide">No flashcards yet.<br>Click "Create Flashcards" to add one.</div>`;
            if (el.flashcardStageCounter) el.flashcardStageCounter.textContent = "";
            updateSliderNav(0, 0);
            return;
        }

        el.flashcardStageTrack.innerHTML = cards.map((c, i) => `
            <div class="flashcard-slide" data-index="${i}">
                <div class="flashcard-card" tabindex="0" aria-label="Card ${i + 1}: click to flip">
                    <button class="flashcard-card-delete-btn" type="button" data-flashcard-id="${escHtml(c.id || ('local-' + i))}" aria-label="Delete flashcard ${i + 1}">🗑</button>
                    <div class="flashcard-card-inner">
                        <div class="flashcard-card-front">
                            <div class="flashcard-card-label">Question</div>
                            <div class="flashcard-card-text">${escHtml(c.question)}</div>
                        </div>
                        <div class="flashcard-card-back">
                            <div class="flashcard-card-label">Answer</div>
                            <div class="flashcard-card-text">${escHtml(c.answer)}</div>
                        </div>
                    </div>
                </div>
            </div>`).join("");

        el.flashcardStageTrack.querySelectorAll(".flashcard-card").forEach((card) => {
            card.addEventListener("click",   () => card.classList.toggle("flipped"));
            card.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") card.classList.toggle("flipped"); });
        });

        el.flashcardStageTrack.querySelectorAll(".flashcard-card-delete-btn").forEach((btn) => {
            btn.addEventListener("click", async (e) => {
                e.stopPropagation();
                const fid = btn.dataset.flashcardId;
                if (!fid) return;
                if (!confirm("Delete this flashcard? This cannot be undone.")) return;
                try {
                    if (String(fid).startsWith('local-')) {
                        const deck = state.decks.find((d) => d.id === state.activeDeckId);
                        if (deck) {
                            deck.flashcards = (deck.flashcards || []).filter((f, idx) => {
                                const idOrLocal = f.id || ('local-' + idx);
                                return String(idOrLocal) !== String(fid);
                            });
                            renderFlashcardSlider(deck.flashcards || []);
                        }
                        return;
                    }
                    const res = await fetch(`/focus-mode/flashcards/${fid}`, {
                        method: "DELETE",
                        headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json" },
                    });
                    if (!res.ok) {
                        const p = await res.json().catch(() => ({}));
                        throw new Error(p?.message || "Delete failed.");
                    }
                    const deck = state.decks.find((d) => d.id === state.activeDeckId);
                    if (deck) {
                        deck.flashcards = (deck.flashcards || []).filter((f) => String(f.id) !== String(fid));
                        renderFlashcardSlider(deck.flashcards || []);
                    }
                } catch (err) {
                    alert(err.message || "Could not delete flashcard.");
                }
            });
        });

        goToCard(0, cards.length);
    }

    function goToCard(index, total) {
        if (!el.flashcardStageTrack) return;
        const slides = el.flashcardStageTrack.querySelectorAll(".flashcard-slide");
        if (!slides.length) return;
        total = total ?? slides.length;
        index = Math.max(0, Math.min(index, total - 1));
        state.flashcardIndex = index;
        slides.forEach((s) => s.querySelector(".flashcard-card")?.classList.remove("flipped"));
        el.flashcardStageTrack.style.transform = `translateX(calc(-${index} * (100% + 24px)))`;
        if (el.flashcardStageCounter) el.flashcardStageCounter.textContent = `${index + 1} / ${total}`;
        updateSliderNav(index, total);
    }

    function updateSliderNav(index, total) {
        if (el.flashcardPrevBtn) el.flashcardPrevBtn.disabled = index <= 0 || total === 0;
        if (el.flashcardNextBtn) el.flashcardNextBtn.disabled = index >= total - 1 || total === 0;
    }

    el.flashcardPrevBtn?.addEventListener("click", () => {
        const deck = state.decks.find((d) => d.id === state.activeDeckId);
        goToCard(state.flashcardIndex - 1, (deck?.flashcards || []).length);
    });
    el.flashcardNextBtn?.addEventListener("click", () => {
        const deck = state.decks.find((d) => d.id === state.activeDeckId);
        goToCard(state.flashcardIndex + 1, (deck?.flashcards || []).length);
    });

    /* ═══════════════════════════════════════════════════════════
       QUIZ MODAL
    ═══════════════════════════════════════════════════════════ */
    let quizIndex = 0;

    function openQuizModal(pane) {
        if (!el.quizModal) return;
        el.quizModal.classList.remove("hidden");
        el.quizModal.setAttribute("aria-hidden", "false");
        el.quizUploadPane?.classList.toggle("hidden", pane !== "upload");
        el.quizCreatePane?.classList.toggle("hidden", pane !== "create");
    }
    function closeQuizModal() {
        if (!el.quizModal) return;
        el.quizModal.classList.add("hidden");
        el.quizModal.setAttribute("aria-hidden", "true");
    }

    el.quizUploadPromptBtn?.addEventListener("click",  () => openQuizModal("upload"));
    el.quizCreatePromptBtn?.addEventListener("click",  () => openQuizModal("create"));
    el.quizModalCloseBtn?.addEventListener("click",    closeQuizModal);
    el.quizModalBackdrop?.addEventListener("click",    closeQuizModal);

    el.quizMaterialsUploadBtn?.addEventListener("click", () => el.quizMaterialsInput?.click());
    el.quizMaterialsInput?.addEventListener("change", async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const fd = new FormData();
        fd.append("material", file);
        fd.append("screen", "screenQuiz");
        try {
            if (el.quizMaterialsStatus) el.quizMaterialsStatus.textContent = `Uploading ${file.name}…`;
            const res = await fetch("/focus-mode/materials", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json" },
                body: fd,
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Upload failed.");
            state.materials = payload.materials || [...state.materials, payload.material];
            if (el.quizMaterialsStatus) el.quizMaterialsStatus.textContent = `${file.name} uploaded.`;
        } catch (err) {
            if (el.quizMaterialsStatus) el.quizMaterialsStatus.textContent = err.message || "Upload failed.";
        } finally { e.target.value = ""; }
    });

    el.quizCancelBtn?.addEventListener("click", () => { el.quizForm?.reset(); setQuizStatus(""); closeQuizModal(); });
    el.quizForm?.addEventListener("submit", handleQuizSubmit);

    function setQuizStatus(msg, isError = false) {
        if (!el.quizStatus) return;
        el.quizStatus.textContent = msg;
        el.quizStatus.classList.toggle("error", isError);
    }

    async function handleQuizSubmit(e) {
        e.preventDefault();
        const question      = (el.quizQuestion?.value     || "").trim();
        const optionA       = (el.quizOptionA?.value       || "").trim();
        const optionB       = (el.quizOptionB?.value       || "").trim();
        const optionC       = (el.quizOptionC?.value       || "").trim();
        const optionD       = (el.quizOptionD?.value       || "").trim();
        const correctOption = (el.quizCorrectOption?.value || "").trim();
        const explanation   = (el.quizExplanation?.value  || "").trim();
        if (!question || !optionA || !optionB || !optionC || !optionD || !correctOption) {
            setQuizStatus("Please fill in the question, all options, and the correct answer.", true);
            return;
        }
        try {
            state.quizBusy = true;
            if (el.quizSaveBtn) el.quizSaveBtn.disabled = true;
            setQuizStatus("Saving…");
            const res = await fetch("/focus-mode/quizzes", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ quiz_set_id: state.activeQuizSetId || null, question, option_a: optionA, option_b: optionB, option_c: optionC, option_d: optionD, correct_option: correctOption, explanation }),
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Save failed.");

            if (state.activeQuizSetId && payload.quizzes) {
                const focusState = window.FocusMode.state;
                const activeSet = (focusState.quizSets || []).find((s) => s.id === state.activeQuizSetId);
                if (activeSet) {
                    const key = activeSet.quizzes ? "quizzes" : "questions";
                    activeSet[key] = payload.quizzes.filter((q) => q.quiz_set_id === state.activeQuizSetId);
                    window.FocusMode.renderQuizSlider(activeSet[key]);
                } else {
                    window.FocusMode.renderQuizSlider(payload.quizzes);
                }
            } else {
                state.quizzes = payload.quizzes || [...state.quizzes, payload.quiz];
                window.FocusMode.renderQuizSlider();
            }
            setQuizStatus("Quiz question saved!");
            el.quizForm?.reset();
            setTimeout(closeQuizModal, 800);
        } catch (err) {
            setQuizStatus(err.message || "Save failed.", true);
        } finally {
            state.quizBusy = false;
            if (el.quizSaveBtn) el.quizSaveBtn.disabled = false;
        }
    }

    function renderQuizSlider(questionsOverride) {
        if (!el.quizStageTrack) return;
        quizIndex = 0;
        const questions = Array.isArray(questionsOverride) ? questionsOverride : state.quizzes;

        const quizStage        = document.getElementById("quizStage");
        const quizStageCounter = el.quizStageCounter;

        if (!questions.length) {
            el.quizStageTrack.innerHTML = `<div class="flashcard-empty-slide">No quiz questions yet.<br>Click "Create Quiz Questions" to add one.</div>`;
            if (quizStageCounter) { quizStageCounter.textContent = ""; quizStageCounter.classList.add("hidden"); }
            if (quizStage) quizStage.classList.remove("hidden");
            updateQuizNav(0, 0);
            return;
        }
        if (quizStage) quizStage.classList.remove("hidden");
        if (quizStageCounter) quizStageCounter.classList.remove("hidden");

        el.quizStageTrack.innerHTML = questions.map((q, i) => {
            const opts = q.options || { A: q.option_a, B: q.option_b, C: q.option_c, D: q.option_d };
            return `
            <div class="flashcard-slide" data-index="${i}">
                <div class="flashcard-card quiz-card" tabindex="0">
                    <div class="flashcard-card-inner">
                        <div class="flashcard-card-front">
                            <div class="flashcard-card-label">Question ${i + 1}</div>
                            <div class="flashcard-card-text">${escHtml(q.question)}</div>
                            <div class="quiz-options-display">
                                ${Object.entries(opts).map(([k, v]) =>
                                    `<div class="quiz-opt-row"><span class="quiz-opt-key">${escHtml(k)}</span><span class="quiz-opt-val">${escHtml(v)}</span></div>`
                                ).join("")}
                            </div>
                            <div class="quiz-flip-hint">Tap to see answer</div>
                        </div>
                        <div class="flashcard-card-back">
                            <div class="flashcard-card-label">Correct Answer</div>
                            <div class="flashcard-card-text quiz-correct-answer">${escHtml(q.correct_option)} — ${escHtml(opts[q.correct_option] || "")}</div>
                            ${q.explanation ? `<div class="quiz-explanation-text">${escHtml(q.explanation)}</div>` : ""}
                        </div>
                    </div>
                </div>
            </div>`;
        }).join("");

        el.quizStageTrack.querySelectorAll(".quiz-card").forEach((card) => {
            card.addEventListener("click",   () => card.classList.toggle("flipped"));
            card.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") card.classList.toggle("flipped"); });
        });

        goToQuizCard(0, questions.length);
    }

    function goToQuizCard(index, total) {
        total = total ?? state.quizzes.length;
        index = Math.max(0, Math.min(index, total - 1));
        quizIndex = index;
        el.quizStageTrack?.querySelectorAll(".flashcard-card").forEach((c) => c.classList.remove("flipped"));
        if (el.quizStageTrack) el.quizStageTrack.style.transform = `translateX(calc(-${index} * (100% + 24px)))`;
        if (el.quizStageCounter) el.quizStageCounter.textContent = total ? `${index + 1} / ${total}` : "";
        updateQuizNav(index, total);
    }
    function updateQuizNav(i, t) {
        if (el.quizPrevBtn) el.quizPrevBtn.disabled = i <= 0 || t === 0;
        if (el.quizNextBtn) el.quizNextBtn.disabled = i >= t - 1 || t === 0;
    }
    el.quizPrevBtn?.addEventListener("click", () => goToQuizCard(quizIndex - 1));
    el.quizNextBtn?.addEventListener("click", () => goToQuizCard(quizIndex + 1));

    /* ═══════════════════════════════════════════════════════════
       FOCUS MODE TOGGLE
    ═══════════════════════════════════════════════════════════ */
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
    if (state.focusOn) {
        showScreen('screenMenu');
    }

    // ── Lock / unlock sidebar & top bar keyboard navigation ──
    const lockTargets = document.querySelectorAll(
        '.sidebar a, .sidebar button, .top-bar a, .top-bar button'
    );
    lockTargets.forEach((node) => {
        if (state.focusOn) {
            node.dataset.prevTabindex = node.getAttribute('tabindex') ?? '';
            node.setAttribute('tabindex', '-1');
            node.setAttribute('aria-disabled', 'true');
        } else {
            const prev = node.dataset.prevTabindex;
            if (prev === '') node.removeAttribute('tabindex');
            else if (prev != null) node.setAttribute('tabindex', prev);
            node.removeAttribute('aria-disabled');
            delete node.dataset.prevTabindex;
        }
    });

    el.focusToggleBtn.animate(
        [{ transform: "scale(1)" }, { transform: "scale(1.18)" }, { transform: "scale(1)" }],
        { duration: 300, easing: "ease-out" }
    );
}

    /* ── Pomodoro ───────────────────────────────────────────── */
    let pomoWidget = null;

    function buildPomodoroWidget() {
        const w = document.createElement("div");
        w.id = "pomodoroWidget";
        w.className = "pomodoro-widget hidden";
        w.dataset.phase = "focus";
        w.innerHTML = `
            <div class="pomo-drag-handle" id="pomoDragHandle" role="button" tabindex="0" aria-label="Drag pomodoro timer">
                <span class="pomo-drag-label">Pomodoro Timer</span>
                <span class="pomo-drag-grip" aria-hidden="true">⋮⋮</span>
            </div>
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

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function applyPomodoroPosition() {
        if (!pomoWidget || !state.pomoPosition) return;
        const { left, top } = state.pomoPosition;
        pomoWidget.style.left = `${left}px`;
        pomoWidget.style.top = `${top}px`;
        pomoWidget.style.right = "auto";
        pomoWidget.style.bottom = "auto";
    }

    function bindPomodoroDrag() {
        const handle = $("pomoDragHandle");
        if (!handle || !pomoWidget) return;

        let pointerId = null;
        let offsetX = 0;
        let offsetY = 0;
        let widgetWidth = 0;
        let widgetHeight = 0;

        const onMove = (event) => {
            if (pointerId !== event.pointerId) return;
            const maxLeft = Math.max(8, window.innerWidth - widgetWidth - 8);
            const maxTop = Math.max(8, window.innerHeight - widgetHeight - 8);
            const left = clamp(event.clientX - offsetX, 8, maxLeft);
            const top = clamp(event.clientY - offsetY, 8, maxTop);
            state.pomoPosition = { left, top };
            pomoWidget.style.left = `${left}px`;
            pomoWidget.style.top = `${top}px`;
            pomoWidget.style.right = "auto";
            pomoWidget.style.bottom = "auto";
        };

        const endDrag = (event) => {
            if (pointerId !== event.pointerId) return;
            pointerId = null;
            pomoWidget.classList.remove("is-dragging");
            window.removeEventListener("pointermove", onMove);
            window.removeEventListener("pointerup", endDrag);
            window.removeEventListener("pointercancel", endDrag);
        };

        handle.addEventListener("pointerdown", (event) => {
            if (event.button !== 0) return;
            if (!pomoWidget || pomoWidget.classList.contains("hidden")) return;
            const rect = pomoWidget.getBoundingClientRect();
            pointerId = event.pointerId;
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            widgetWidth = rect.width;
            widgetHeight = rect.height;
            pomoWidget.classList.add("is-dragging");
            handle.setPointerCapture?.(event.pointerId);
            window.addEventListener("pointermove", onMove);
            window.addEventListener("pointerup", endDrag);
            window.addEventListener("pointercancel", endDrag);
            event.preventDefault();
        });
    }

    function showPomodoroWidget() {
        if (!pomoWidget) { pomoWidget = buildPomodoroWidget(); bindPomodoroEvents(); }
        applyPomodoroPosition();
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
        bindPomodoroDrag();
        pomoWidget.querySelectorAll(".pomo-tab").forEach((tab) =>
            tab.addEventListener("click", () => switchPhase(tab.dataset.phase))
        );
        $("pomoPlayPauseBtn").addEventListener("click", () => state.pomoRunning ? pausePomodoro() : startPomodoro());
        $("pomoResetBtn").addEventListener("click", resetPomodoro);
        $("pomoSkipBtn").addEventListener("click",  advancePhase);
    }
    function switchPhase(phase) {
        pausePomodoro();
        state.pomoPhase = phase;
        state.pomoSecondsLeft = POMO[phase];
        pomoWidget.querySelectorAll(".pomo-tab").forEach((t) => t.classList.toggle("active", t.dataset.phase === phase));
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
            switchPhase(state.pomoCycle % POMO.cyclesBeforeLong === 0 ? "longBreak" : "shortBreak");
        } else {
            switchPhase("focus");
        }
        updateRoundLabel();
    }
    function onPhaseComplete() {
        pausePomodoro();
        playChime();
        showNotif(state.pomoPhase === "focus" ? "🎉 Focus session complete! Time for a break." : "⚡ Break over! Ready to focus again?");
        if (state.pomoPhase === "focus") { state.pomoCycle++; updateDots(); }
        setTimeout(() => {
            const next = state.pomoPhase === "focus"
                ? (state.pomoCycle % POMO.cyclesBeforeLong === 0 ? "longBreak" : "shortBreak")
                : "focus";
            switchPhase(next);
            updateRoundLabel();
            startPomodoro();
        }, 2200);
    }

    const PHASE_META = {
        focus:      { label: "Focus Time",  color: "#7c4dca" },
        shortBreak: { label: "Short Break", color: "#1eaabb" },
        longBreak:  { label: "Long Break",  color: "#2a9d8f" },
    };
    const CIRC = 2 * Math.PI * 52;

    function renderPomodoro() {
        const tEl = $("pomoTimeDisplay"), lEl = $("pomoPhaseLabel"), ring = $("pomoRingFill");
        if (!tEl) return;
        const left = state.pomoSecondsLeft, total = POMO[state.pomoPhase], meta = PHASE_META[state.pomoPhase];
        tEl.textContent = `${pad(Math.floor(left / 60))}:${pad(left % 60)}`;
        lEl.textContent = meta.label;
        ring.style.strokeDasharray  = CIRC;
        ring.style.strokeDashoffset = CIRC * (1 - Math.max(0, left / total));
        ring.style.stroke           = meta.color;
    }
    function setPlayPauseUI(running) {
        const play = $("pomoPlayIcon"), pause = $("pomoPauseIcon");
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
        const rEl = $("pomoRoundNum");
        if (rEl) rEl.textContent = Math.min((state.pomoCycle % POMO.cyclesBeforeLong) + 1, POMO.cyclesBeforeLong);
    }

    /* ── Notifications + Sound ──────────────────────────────── */
    function showNotif(msg) {
        const n = document.createElement("div");
        n.className = "pomo-notif";
        n.textContent = msg;
        document.body.appendChild(n);
        requestAnimationFrame(() => n.classList.add("show"));
        setTimeout(() => { n.classList.remove("show"); setTimeout(() => n.remove(), 400); }, 3000);
    }
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [523.25, 659.25, 783.99].forEach((freq, i) => {
                const osc = ctx.createOscillator(), g = ctx.createGain();
                osc.connect(g); g.connect(ctx.destination);
                osc.frequency.value = freq; osc.type = "sine";
                const t = ctx.currentTime + i * 0.18;
                g.gain.setValueAtTime(0.28, t);
                g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
                osc.start(t); osc.stop(t + 0.5);
            });
        } catch (e) {}
    }

    /* ═══════════════════════════════════════════════════════════
       MUSIC PLAYER  (on/off only — single DB-backed default track)
    ═══════════════════════════════════════════════════════════ */
    const bgAudio = new Audio();
    bgAudio.src     = "/focus-mode/music/stream";
    bgAudio.loop    = true;
    bgAudio.preload = "none";

    bgAudio.addEventListener("timeupdate", () => {
        if (!bgAudio.duration || !el.progressFill) return;
        const pct = (bgAudio.currentTime / bgAudio.duration) * 100;
        el.progressFill.style.width      = `${pct}%`;
        el.progressFill.style.animation  = "none";
        el.progressFill.style.transition = "width 1s linear";
    });

    bgAudio.addEventListener("loadstart", () => {
        if (el.progressFill) el.progressFill.style.width = "0%";
    });

    bgAudio.addEventListener("play",  () => { el.musicToggleBtn?.classList.add("is-playing"); });
    bgAudio.addEventListener("pause", () => { el.musicToggleBtn?.classList.remove("is-playing"); });
    bgAudio.addEventListener("ended", () => { el.musicToggleBtn?.classList.remove("is-playing"); });

    function startMusic() {
        bgAudio.play().catch((err) => {
            console.warn("Music play blocked:", err);
        });
        state.isPlaying = true;
        syncPlayPauseIcons();
    }

    function stopMusic() {
        bgAudio.pause();
        state.isPlaying = false;
        syncPlayPauseIcons();
    }

    function syncPlayPauseIcons() {
        el.playIcon?.classList.toggle("hidden",  state.isPlaying);
        el.pauseIcon?.classList.toggle("hidden", !state.isPlaying);
    }

    // FAB toggle — show/hide the music widget panel
    el.musicToggleBtn.addEventListener("click", () => {
        state.musicOn = !state.musicOn;
        updateMusicFabVisibility();
    });

    // × button inside the widget — hide panel but keep music playing
    el.musicHideBtn?.addEventListener("click", () => {
        state.musicOn = false;
        updateMusicFabVisibility();
    });

    // Play / Pause button inside the widget
    el.playPauseBtn?.addEventListener("click", () => {
        if (bgAudio.paused) {
            startMusic();
        } else {
            stopMusic();
        }
    });

    function updateMusicFabVisibility() {
        // Show FAB on ALL screens (including menu and viewer)
        el.musicToggleBtn.classList.remove("hidden");

        if (!el.musicWidget) return;

        const show = state.musicOn;
        el.body.classList.toggle("music-panel-visible", show);

        if (show) {
            el.musicWidget.classList.remove("hidden", "hiding");
            if (bgAudio.paused) startMusic();
        } else {
            el.musicWidget.classList.add("hiding");
            setTimeout(() => {
                el.musicWidget.classList.add("hidden");
                el.musicWidget.classList.remove("hiding");
            }, 280);
        }
    }

    /* ── Session save ───────────────────────────────────────── */
    function saveFocusSession(secs) {
        if (secs < 1) return;
        fetch("/focus-mode/session", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": getCsrfToken() },
            body: JSON.stringify({ duration: secs }),
        }).catch(() => {});
    }
    window.addEventListener("beforeunload", () => {
        if (state.focusOn && state.totalFocusSecs > 0) saveFocusSession(state.totalFocusSecs);
    });

    /* ── Init ───────────────────────────────────────────────── */
    showScreen("screenMenu");
    window.FocusMode.renderQuizSlider();
})();
