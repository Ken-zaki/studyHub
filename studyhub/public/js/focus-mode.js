/**
 * focus-mode.js – StudyHub Focus Mode
 * Fixed: menu button navigation, deck segregation for flashcards,
 *        persistent DB-backed decks & flashcards.
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
        materials: Array.isArray(window.__focusMaterials) ? window.__focusMaterials : [],
        decks: Array.isArray(window.__focusDecks) ? window.__focusDecks : [],
        quizzes: Array.isArray(window.__focusQuizzes) ? window.__focusQuizzes : [],
        activeDeckId: null,   // currently selected deck
        flashcardIndex: 0,    // current card in slider
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
        focusToggleBtn:         $("focusToggleBtn"),
        lockOpen:               $("lockOpen"),
        lockClosed:             $("lockClosed"),
        focusFooter:            $("focusFooter"),
        // Music
        musicToggleBtn:         $("musicToggleBtn"),
        musicHideBtn:           $("musicHideBtn"),
        musicWidget:            $("musicWidget"),
        playPauseBtn:           $("playPauseBtn"),
        playIcon:               $("playIcon"),
        pauseIcon:              $("pauseIcon"),
        progressFill:           $("progressFill"),
        shuffleBtn:             $("shuffleBtn"),
        // Materials panel (top sidebar)
        materialsPanel:         $("materialsPanel"),
        materialsInput:         $("materialsInput"),
        materialsList:          $("materialsList"),
        materialsStatus:        $("materialsStatus"),
        // Deck browser
        deckBrowser:            $("deckBrowser"),
        deckGrid:               $("deckGrid"),
        deckCreateBtn:          $("deckCreateBtn"),
        deckModalOverlay:       $("deckModalOverlay") || $("deckCreateBackdrop"),
        deckNameInput:          $("deckNameInput"),
        deckCancelBtn:          $("deckCancelBtn"),
        deckSaveBtn:            $("deckSaveBtn"),
        deckStatus:             $("deckStatus"),
        // Deck content (after selecting a deck)
        deckContent:            $("deckContent"),
        deckBackBtn:            $("deckBackBtn"),
        deckContentTitle:       $("deckContentTitle"),
        deckContentDesc:        $("deckContentDesc"),
        flashcardScreenBackBtn: $("flashcardScreenBackBtn"),
        // Flashcard action buttons (inside deck content)
        flashcardUploadPromptBtn: $("flashcardUploadPromptBtn"),
        flashcardCreatePromptBtn: $("flashcardCreatePromptBtn"),
        // Flashcard slider
        flashcardStageTrack:    $("flashcardStageTrack"),
        flashcardStageCounter:  $("flashcardStageCounter"),
        flashcardPrevBtn:       $("flashcardPrevBtn"),
        flashcardNextBtn:       $("flashcardNextBtn"),
        // Flashcard modal
        flashcardModal:         $("flashcardModal"),
        flashcardModalBackdrop: $("flashcardModalBackdrop"),
        flashcardModalCloseBtn: $("flashcardModalCloseBtn"),
        flashcardUploadPane:    $("flashcardUploadPane"),
        flashcardCreatePane:    $("flashcardCreatePane"),
        flashcardMaterialsUploadBtn: $("flashcardMaterialsUploadBtn"),
        flashcardMaterialsInput:     $("flashcardMaterialsInput"),
        flashcardMaterialsStatus:    $("flashcardMaterialsStatus"),
        flashcardMaterialsList:      $("flashcardMaterialsList"),
        flashcardForm:          $("flashcardForm"),
        flashcardQuestion:      $("flashcardQuestion"),
        flashcardAnswer:        $("flashcardAnswer"),
        flashcardCancelBtn:     $("flashcardCancelBtn"),
        flashcardSaveBtn:       $("flashcardSaveBtn"),
        flashcardStatus:        $("flashcardStatus"),
        // Review upload
        reviewUploadBtn:        $("reviewUploadBtn"),
        // Quiz modal
        quizUploadPromptBtn:    $("quizUploadPromptBtn"),
        quizCreatePromptBtn:    $("quizCreatePromptBtn"),
        quizModal:              $("quizModal"),
        quizModalBackdrop:      $("quizModalBackdrop"),
        quizModalCloseBtn:      $("quizModalCloseBtn"),
        quizUploadPane:         $("quizUploadPane"),
        quizCreatePane:         $("quizCreatePane"),
        quizMaterialsUploadBtn: $("quizMaterialsUploadBtn"),
        quizMaterialsInput:     $("quizMaterialsInput"),
        quizMaterialsStatus:    $("quizMaterialsStatus"),
        quizMaterialsList:      $("quizMaterialsList"),
        quizForm:               $("quizForm"),
        quizQuestion:           $("quizQuestion"),
        quizOptionA:            $("quizOptionA"),
        quizOptionB:            $("quizOptionB"),
        quizOptionC:            $("quizOptionC"),
        quizOptionD:            $("quizOptionD"),
        quizCorrectOption:      $("quizCorrectOption"),
        quizExplanation:        $("quizExplanation"),
        quizCancelBtn:          $("quizCancelBtn"),
        quizSaveBtn:            $("quizSaveBtn"),
        quizStageTrack:         $("quizStageTrack"),
        quizStageCounter:       $("quizStageCounter"),
        quizPrevBtn:            $("quizPrevBtn"),
        quizNextBtn:            $("quizNextBtn"),
        quizStatus:             $("quizStatus"),
    };

    // Expose a lightweight plugin API so deck/flashcard modules can register
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
    window.FocusMode.renderFlashcardSlider = function (cards) { if (typeof renderFlashcardSlider === 'function') return renderFlashcardSlider(cards); };

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
       SCREEN NAVIGATION  (FIX: was broken by bad el references)
    ═══════════════════════════════════════════════════════════ */
    function showScreen(id) {
        document.querySelectorAll(".screen").forEach((s) => s.classList.add("hidden"));
        const t = document.getElementById(id);
        if (t) t.classList.remove("hidden");
        state.currentScreen = id;
        updateMaterialsPanelVisibility();
        updateMusicFabVisibility();
        // reflect visible screen in the URL hash for deep-linking/navigation
        try { history.replaceState(null, "", `#${id}`); } catch (err) { /* ignore */ }
    }

    // Menu buttons → navigate to target screen
    document.querySelectorAll(".menu-btn[data-target]").forEach((btn) =>
        btn.addEventListener("click", () => showScreen(btn.dataset.target))
    );
    // Back buttons → navigate to target screen
    document.querySelectorAll(".back-btn[data-target]").forEach((btn) =>
        btn.addEventListener("click", () => showScreen(btn.dataset.target))
    );

    /* ═══════════════════════════════════════════════════════════
       MATERIALS PANEL
    ═══════════════════════════════════════════════════════════ */
    // Review screen upload button triggers the shared materials input
    el.reviewUploadBtn?.addEventListener("click", () => el.materialsInput?.click());
    el.materialsInput?.addEventListener("change", handleMaterialUpload);

    function updateMaterialsPanelVisibility() {
        if (!el.materialsPanel) return;
        const inStudy = state.currentScreen !== "screenMenu";
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

    /* Deck UI & functionality moved to resources/js/flashcards-decks.js via plugin registration. */

    /* ═══════════════════════════════════════════════════════════
       FLASHCARD MODAL  (Upload / Create — opened from deck content)
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

    el.flashcardUploadPromptBtn?.addEventListener("click", () => openFlashcardModal("upload"));
    el.flashcardCreatePromptBtn?.addEventListener("click", () => openFlashcardModal("create"));
    el.flashcardModalCloseBtn?.addEventListener("click",  closeFlashcardModal);
    el.flashcardModalBackdrop?.addEventListener("click",  closeFlashcardModal);

    // Upload inside flashcard modal
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

    // Flashcard create form inside modal
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
            // If the active deck is a local-only deck (created offline), try to sync it first
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
                    // Replace local deck with server deck
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

            // Update the active deck's flashcards in state
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

        // Flip on click / Enter
        el.flashcardStageTrack.querySelectorAll(".flashcard-card").forEach((card) => {
            card.addEventListener("click",   () => card.classList.toggle("flipped"));
            card.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") card.classList.toggle("flipped"); });
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
        // Unflip current cards
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
        const question       = (el.quizQuestion?.value      || "").trim();
        const optionA        = (el.quizOptionA?.value        || "").trim();
        const optionB        = (el.quizOptionB?.value        || "").trim();
        const optionC        = (el.quizOptionC?.value        || "").trim();
        const optionD        = (el.quizOptionD?.value        || "").trim();
        const correctOption  = (el.quizCorrectOption?.value  || "").trim();
        const explanation    = (el.quizExplanation?.value    || "").trim();
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
                body: JSON.stringify({ question, option_a: optionA, option_b: optionB, option_c: optionC, option_d: optionD, correct_option: correctOption, explanation }),
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload?.message || "Save failed.");
            state.quizzes = payload.quizzes || [...state.quizzes, payload.quiz];
            renderQuizSlider();
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

    function renderQuizSlider() {
        if (!el.quizStageTrack) return;
        quizIndex = 0;
        if (!state.quizzes.length) {
            el.quizStageTrack.innerHTML = `<div class="flashcard-empty-slide">No quiz questions yet.<br>Click "Create Quiz" to add one.</div>`;
            if (el.quizStageCounter) el.quizStageCounter.textContent = "";
            updateQuizNav(0, 0);
            return;
        }
        el.quizStageTrack.innerHTML = state.quizzes.map((q, i) => {
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

        goToQuizCard(0);
    }

    function goToQuizCard(index) {
        const total = state.quizzes.length;
        index = Math.max(0, Math.min(index, total - 1));
        quizIndex = index;
        el.quizStageTrack?.querySelectorAll(".flashcard-card").forEach((c) => c.classList.remove("flipped"));
        if (el.quizStageTrack) el.quizStageTrack.style.transform = `translateX(calc(-${index} * (100% + 24px)))`;
        if (el.quizStageCounter) el.quizStageCounter.textContent = total ? `${index + 1} / ${total}` : "";
        updateQuizNav(index, total);
    }
    function updateQuizNav(i, t) { if (el.quizPrevBtn) el.quizPrevBtn.disabled = i <= 0 || t === 0; if (el.quizNextBtn) el.quizNextBtn.disabled = i >= t - 1 || t === 0; }
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
        el.focusToggleBtn.animate([{ transform: "scale(1)" }, { transform: "scale(1.18)" }, { transform: "scale(1)" }], { duration: 300, easing: "ease-out" });
    }

    /* ── Pomodoro ───────────────────────────────────────────── */
    let pomoWidget = null;

    function buildPomodoroWidget() {
        const w = document.createElement("div");
        w.id = "pomoWidget";
        w.className = "pomo-widget hidden";
        w.dataset.phase = "focus";
        w.innerHTML = `...`;
        document.body.appendChild(w);
        return w;
    }

    function showPomodoroWidget() { if (!pomoWidget) { pomoWidget = buildPomodoroWidget(); bindPomodoroEvents(); } pomoWidget.classList.remove("hidden"); requestAnimationFrame(() => pomoWidget.classList.add("visible")); renderPomodoro(); }
    function hidePomodoroWidget() { pausePomodoro(); if (!pomoWidget) return; pomoWidget.classList.remove("visible"); setTimeout(() => pomoWidget.classList.add("hidden"), 350); }
    function bindPomodoroEvents() { pomoWidget.querySelectorAll(".pomo-tab").forEach((tab) => tab.addEventListener("click", () => switchPhase(tab.dataset.phase))); $("pomoPlayPauseBtn").addEventListener("click", () => state.pomoRunning ? pausePomodoro() : startPomodoro()); $("pomoResetBtn").addEventListener("click", resetPomodoro); $("pomoSkipBtn").addEventListener("click", advancePhase); }

    // ... (rest of file unchanged)

    // respond to manual hash navigation
    window.addEventListener("hashchange", () => {
        const id = (location.hash || "").replace(/^#/, "") || "screenMenu";
        if (id && id !== state.currentScreen) showScreen(id);
    });
    // initialize based on URL hash if present
    const initial = (location.hash || "").replace(/^#/, "") || "screenMenu";
    showScreen(initial);
})();
