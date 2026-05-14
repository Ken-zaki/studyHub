// quiz-sets.js — registers Quiz Set browser UI/behavior with FocusMode
// Mirrors flashcards-decks.js exactly, adapted for quiz sets.
//
// HOW TO CONNECT:
//   In focus-mode.blade.php, add AFTER flashcards-decks.js:
//   <script src="{{ asset('js/quiz-sets.js') }}"></script>
//
// WHAT THIS FILE DOES:
//   1. Adds a "quiz set browser" layer in front of the quiz question
//      slider — same pattern as the deck browser in front of flashcards.
//   2. Manages state.quizSets (array) and state.activeQuizSetId.
//   3. POSTs to /focus-mode/quiz-sets  and  DELETE /focus-mode/quiz-sets/{id}
//   4. Patches the existing quiz question save so it sends quiz_set_id.
//   5. Renders the quiz question slider only for the active set's questions.
//   6. Uses FocusMode.register() so focus-mode.js loads first, then this runs.

(function () {
    "use strict";

    function init(state, el) {
        const api           = window.FocusMode || {};
        const getCsrfToken  = api.getCsrfToken  || (() => "");
        const escHtml       = api.escHtml        || ((v) => String(v));

        /* ── Seed quiz set state on the shared state object ── */
        // window.__focusQuizSets is injected by the blade template
        state.quizSets       = Array.isArray(window.__focusQuizSets) ? window.__focusQuizSets : [];
        state.activeQuizSetId = null;
        state.quizIndex       = state.quizIndex ?? 0;

        /* ── DOM refs (new elements unique to quiz sets) ── */
        const $ = (id) => document.getElementById(id);
        const quizSetBrowser       = $("quizSetBrowser");
        const quizSetGrid          = $("quizSetGrid");
        const quizSetCreateBtn     = $("quizSetCreateBtn");
        const quizSetModalOverlay  = $("quizSetModalOverlay");
        const quizSetTitleInput    = $("quizSetTitleInput");
        const quizSetSaveBtn       = $("quizSetSaveBtn");
        const quizSetCancelBtn     = $("quizSetCancelBtn");
        const quizSetStatus        = $("quizSetStatus");
        const quizSetContent       = $("quizSetContent");
        const quizSetBackBtn       = $("quizSetBackBtn");
        const quizSetContentTitle  = $("quizSetContentTitle");
        const quizSetContentDesc   = $("quizSetContentDesc");
        const quizScreenBackBtn    = $("quizScreenBackBtn");

        /* ══════════════════════════════════════════════════
           QUIZ SET BROWSER  (shown when entering quiz screen)
        ══════════════════════════════════════════════════ */
        function showQuizSetBrowser() {
            state.activeQuizSetId = null;
            quizSetBrowser?.classList.remove("hidden");
            quizSetContent?.classList.add("hidden");

            // Show the "← Back to Menu" button
            if (quizScreenBackBtn) {
                quizScreenBackBtn.classList.remove("hidden");
                quizScreenBackBtn.dataset.target = "screenMenu";
            }
            renderQuizSetGrid();
        }

        function showQuizSetContent(setId) {
            const set = state.quizSets.find((s) => s.id === setId);
            if (!set) return;

            state.activeQuizSetId = setId;

            quizSetBrowser?.classList.add("hidden");
            quizSetContent?.classList.remove("hidden");

            if (quizSetContentTitle) quizSetContentTitle.textContent = set.title;
            if (quizSetContentDesc)  quizSetContentDesc.textContent  = set.description || "";

            // Hide the "← Back to Menu" while inside a set
            if (quizScreenBackBtn) quizScreenBackBtn.classList.add("hidden");

            // Render the quiz question slider for this set's questions
            renderQuizSliderForSet(set.questions || []);
        }

        /* ── Back to quiz set browser from inside a set ── */
        quizSetBackBtn?.addEventListener("click", () => {
            quizScreenBackBtn?.classList.remove("hidden");
            showQuizSetBrowser();
        });

        /* ══════════════════════════════════════════════════
           RENDER QUIZ SET GRID
        ══════════════════════════════════════════════════ */
        function renderQuizSetGrid() {
            if (!quizSetGrid) return;

            if (!state.quizSets || !state.quizSets.length) {
                quizSetGrid.innerHTML = `<div class="quiz-set-empty-state">No quizzes created yet.</div>`;
                return;
            }

            quizSetGrid.innerHTML = state.quizSets.map((s) => `
                <div class="quiz-set-card"
                     data-set-id="${escHtml(String(s.id))}"
                     role="button"
                     tabindex="0"
                     aria-label="Open quiz set ${escHtml(s.title)}">
                    <div>
                        <div class="quiz-set-card-name">${escHtml(s.title)}</div>
                        <div class="quiz-set-card-count">
                            ${(s.questions || []).length}
                            question${(s.questions || []).length !== 1 ? "s" : ""}
                        </div>
                    </div>
                    <button class="quiz-set-delete-btn"
                            data-set-id="${escHtml(String(s.id))}"
                            aria-label="Delete quiz set"
                            title="Delete quiz set">🗑</button>
                </div>`).join("");

            // Open set on card click
            quizSetGrid.querySelectorAll(".quiz-set-card").forEach((card) => {
                card.addEventListener("click", (e) => {
                    if (e.target.closest(".quiz-set-delete-btn")) return;
                    showQuizSetContent(String(card.dataset.setId));
                });
                card.addEventListener("keydown", (e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        showQuizSetContent(String(card.dataset.setId));
                    }
                });
            });

            // Delete buttons
            quizSetGrid.querySelectorAll(".quiz-set-delete-btn").forEach((btn) => {
                btn.addEventListener("click", async (e) => {
                    e.stopPropagation();
                    if (!confirm("Delete this quiz set and all its questions?")) return;
                    await handleQuizSetDelete(String(btn.dataset.setId));
                });
            });
        }

        /* ══════════════════════════════════════════════════
           MODAL — open / close
        ══════════════════════════════════════════════════ */
        function openModal() {
            if (!quizSetModalOverlay) return;
            quizSetModalOverlay.classList.remove("hidden");
            requestAnimationFrame(() => quizSetModalOverlay.classList.add("open"));
            if (quizSetTitleInput) quizSetTitleInput.value = "";
            setStatus("");
            setTimeout(() => quizSetTitleInput?.focus(), 60);
        }

        function closeModal() {
            if (!quizSetModalOverlay) return;
            quizSetModalOverlay.classList.remove("open");
            setTimeout(() => quizSetModalOverlay.classList.add("hidden"), 200);
            if (quizSetTitleInput) quizSetTitleInput.value = "";
            setStatus("");
        }

        function setStatus(msg, isErr = false) {
            if (!quizSetStatus) return;
            quizSetStatus.textContent = msg;
            quizSetStatus.classList.toggle("error", isErr);
        }

        quizSetCreateBtn?.addEventListener("click", openModal);
        quizSetCancelBtn?.addEventListener("click", closeModal);

        quizSetModalOverlay?.addEventListener("click", (e) => {
            if (e.target === quizSetModalOverlay) closeModal();
        });

        document.addEventListener("keydown", (e) => {
            if (
                e.key === "Escape" &&
                quizSetModalOverlay &&
                !quizSetModalOverlay.classList.contains("hidden") &&
                quizSetModalOverlay.classList.contains("open")
            ) {
                closeModal();
            }
        });

        quizSetSaveBtn?.addEventListener("click", handleCreate);
        quizSetTitleInput?.addEventListener("keydown", (e) => {
            if (e.key === "Enter") { e.preventDefault(); handleCreate(); }
        });

        /* ══════════════════════════════════════════════════
           CREATE QUIZ SET  (Confirm button)
           Local-first: set is created immediately in state so
           the user can start adding questions right away.
           Server sync happens silently in the background.
        ══════════════════════════════════════════════════ */
        async function handleCreate() {
            const title = (quizSetTitleInput?.value || "").trim();
            if (!title) { setStatus("Please enter a quiz set title.", true); return; }

            // 1. Create locally right away — never blocks the user
            const localId  = `local-${Date.now()}`;
            const localSet = { id: localId, title, description: "", questions: [] };
            state.quizSets        = [localSet, ...(state.quizSets || [])];
            state.activeQuizSetId = localId;
            setStatus("Quiz set created!");
            renderQuizSetGrid();
            setTimeout(closeModal, 600);

            // 2. Sync to server silently in the background
            try {
                if (quizSetSaveBtn) quizSetSaveBtn.disabled = true;
                const res = await fetch("/focus-mode/quiz-sets", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": getCsrfToken(),
                        "Accept": "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ title }),
                });
                const payload = await res.json().catch(() => null);
                if (res.ok && payload?.quiz_set) {
                    // Swap local placeholder for the real server set, keeping any
                    // questions the user may have already added in the meantime
                    const serverSet = { ...payload.quiz_set, questions: localSet.questions || [] };
                    state.quizSets = state.quizSets.map((s) =>
                        String(s.id) === localId ? serverSet : s
                    );
                    // Update active ID if user is still on this set
                    if (String(state.activeQuizSetId) === localId) {
                        state.activeQuizSetId = String(serverSet.id);
                    }
                    renderQuizSetGrid();
                }
                // If server fails, local set stays — usable for this session
            } catch (_) {
                // Silent — set is already visible and usable locally
            } finally {
                if (quizSetSaveBtn) quizSetSaveBtn.disabled = false;
            }
        }

        /* ══════════════════════════════════════════════════
           DELETE QUIZ SET
           Local-first: remove from state immediately so the
           UI feels instant. Best-effort server delete after.
        ══════════════════════════════════════════════════ */
        async function handleQuizSetDelete(setId) {
            // Remove locally right away
            state.quizSets = state.quizSets.filter((s) => String(s.id) !== String(setId));
            renderQuizSetGrid();

            // Local-only sets don\'t exist on the server — nothing to delete
            if (String(setId).startsWith("local-")) return;

            // Best-effort server delete — failure is silent, local state is already clean
            try {
                await fetch(`/focus-mode/quiz-sets/${setId}`, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": getCsrfToken(),
                        "Accept": "application/json",
                    },
                });
            } catch (_) {
                // Silent
            }
        }

        /* ══════════════════════════════════════════════════
           QUIZ QUESTION SLIDER  (scoped to active set)
           Reuses the existing el.quizStageTrack DOM elements
           that focus-mode.js already knows about.
        ══════════════════════════════════════════════════ */
        function renderQuizSliderForSet(questions) {
            if (!el.quizStageTrack) return;
            state.quizIndex = 0;

            if (!questions.length) {
                el.quizStageTrack.innerHTML = `
                    <div class="flashcard-empty-slide">
                        No quiz questions yet.<br>
                        Click "Create Quiz" to add one.
                    </div>`;
                if (el.quizStageCounter) el.quizStageCounter.textContent = "";
                updateQuizNav(0, 0);
                return;
            }

            el.quizStageTrack.innerHTML = questions.map((q, i) => {
                const opts = q.options || {
                    A: q.option_a, B: q.option_b,
                    C: q.option_c, D: q.option_d,
                };
                return `
                <div class="flashcard-slide" data-index="${i}">
                    <div class="flashcard-card quiz-card" tabindex="0">
                        <div class="flashcard-card-inner">
                            <div class="flashcard-card-front">
                                <div class="flashcard-card-label">Question ${i + 1}</div>
                                <div class="flashcard-card-text">${escHtml(q.question)}</div>
                                <div class="quiz-options-display">
                                    ${Object.entries(opts).map(([k, v]) =>
                                        `<div class="quiz-opt-row">
                                            <span class="quiz-opt-key">${escHtml(k)}</span>
                                            <span class="quiz-opt-val">${escHtml(v)}</span>
                                        </div>`
                                    ).join("")}
                                </div>
                                <div class="quiz-flip-hint">Tap to see answer</div>
                            </div>
                            <div class="flashcard-card-back">
                                <div class="flashcard-card-label">Correct Answer</div>
                                <div class="flashcard-card-text quiz-correct-answer">
                                    ${escHtml(q.correct_option)} — ${escHtml(opts[q.correct_option] || "")}
                                </div>
                                ${q.explanation
                                    ? `<div class="quiz-explanation-text">${escHtml(q.explanation)}</div>`
                                    : ""}
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join("");

            el.quizStageTrack.querySelectorAll(".quiz-card").forEach((card) => {
                card.addEventListener("click",   () => card.classList.toggle("flipped"));
                card.addEventListener("keydown", (e) => {
                    if (e.key === "Enter" || e.key === " ") card.classList.toggle("flipped");
                });
            });

            goToQuizSlide(0, questions.length);
        }

        function goToQuizSlide(index, total) {
            total = total ?? (state.quizSets.find((s) => s.id === state.activeQuizSetId)?.questions || []).length;
            index = Math.max(0, Math.min(index, total - 1));
            state.quizIndex = index;
            el.quizStageTrack?.querySelectorAll(".flashcard-card").forEach((c) => c.classList.remove("flipped"));
            if (el.quizStageTrack) el.quizStageTrack.style.transform = `translateX(calc(-${index} * (100% + 24px)))`;
            if (el.quizStageCounter) el.quizStageCounter.textContent = total ? `${index + 1} / ${total}` : "";
            updateQuizNav(index, total);
        }

        function updateQuizNav(i, t) {
            if (el.quizPrevBtn) el.quizPrevBtn.disabled = i <= 0 || t === 0;
            if (el.quizNextBtn) el.quizNextBtn.disabled = i >= t - 1 || t === 0;
        }

        // Re-wire prev/next buttons so they use our local goToQuizSlide
        // (focus-mode.js used its own quizIndex var; we take over here)
        el.quizPrevBtn?.addEventListener("click", () => goToQuizSlide(state.quizIndex - 1));
        el.quizNextBtn?.addEventListener("click", () => goToQuizSlide(state.quizIndex + 1));

        /* ══════════════════════════════════════════════════
           PATCH quiz question save to include quiz_set_id
           focus-mode.js posts to /focus-mode/quizzes without a set ID.
           We intercept the quizForm submit and inject quiz_set_id.
        ══════════════════════════════════════════════════ */
        el.quizForm?.addEventListener("submit", handleQuizQuestionSubmit, true); // capture phase — runs before focus-mode.js

        async function handleQuizQuestionSubmit(e) {
            // Only intercept if a set is active; otherwise let focus-mode.js handle it
            if (!state.activeQuizSetId) return;

            e.preventDefault();
            e.stopImmediatePropagation(); // prevent focus-mode.js handler from running

            const question      = (el.quizQuestion?.value      || "").trim();
            const optionA       = (el.quizOptionA?.value        || "").trim();
            const optionB       = (el.quizOptionB?.value        || "").trim();
            const optionC       = (el.quizOptionC?.value        || "").trim();
            const optionD       = (el.quizOptionD?.value        || "").trim();
            const correctOption = (el.quizCorrectOption?.value  || "").trim();
            const explanation   = (el.quizExplanation?.value    || "").trim();

            if (!question || !optionA || !optionB || !optionC || !optionD || !correctOption) {
                setQuizStatus("Please fill in the question, all options, and the correct answer.", true);
                return;
            }

            // 1. Save locally right away — always works, never blocked by server state
            const newQuestion = {
                id:             `local-q-${Date.now()}`,
                quiz_set_id:    state.activeQuizSetId,
                question,
                options:        { A: optionA, B: optionB, C: optionC, D: optionD },
                correct_option: correctOption,
                explanation,
            };
            const activeSet = state.quizSets.find((s) => String(s.id) === String(state.activeQuizSetId));
            if (!activeSet) { setQuizStatus("Quiz set not found.", true); return; }
            activeSet.questions = [...(activeSet.questions || []), newQuestion];
            renderQuizSliderForSet(activeSet.questions);
            setQuizStatus("Quiz question saved!");
            el.quizForm?.reset();
            setTimeout(() => {
                if (el.quizModal) {
                    el.quizModal.classList.add("hidden");
                    el.quizModal.setAttribute("aria-hidden", "true");
                }
                setQuizStatus("");
            }, 800);

            // 2. If the set has a real server ID, persist silently in background
            if (String(state.activeQuizSetId).startsWith("local-")) return;

            try {
                if (el.quizSaveBtn) el.quizSaveBtn.disabled = true;
                const res = await fetch("/focus-mode/quizzes", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": getCsrfToken(),
                        "Accept": "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        quiz_set_id:    state.activeQuizSetId,
                        question,
                        option_a:       optionA,
                        option_b:       optionB,
                        option_c:       optionC,
                        option_d:       optionD,
                        correct_option: correctOption,
                        explanation,
                    }),
                });
                const payload = await res.json().catch(() => null);
                if (res.ok && payload?.questions) {
                    // Replace local question list with server-confirmed list
                    activeSet.questions = payload.questions;
                    renderQuizSliderForSet(activeSet.questions);
                }
                // If server fails, the locally-added question stays visible
            } catch (_) {
                // Silent — question is already visible locally
            } finally {
                if (el.quizSaveBtn) el.quizSaveBtn.disabled = false;
            }
        }

        function setQuizStatus(msg, isErr = false) {
            if (!el.quizStatus) return;
            el.quizStatus.textContent = msg;
            el.quizStatus.classList.toggle("error", isErr);
        }

        /* ══════════════════════════════════════════════════
           HOOK INTO screenQuiz NAVIGATION
           Poll state.currentScreen for changes (same pattern as
           flashcards-decks.js). When it becomes "screenQuiz",
           show the quiz set browser.
        ══════════════════════════════════════════════════ */
        let lastScreen = state.currentScreen;
        setInterval(() => {
            if (state.currentScreen !== lastScreen) {
                lastScreen = state.currentScreen;
                if (lastScreen === "screenQuiz") showQuizSetBrowser();
            }
        }, 300);

        // Initial render if already on quiz screen
        if (state.currentScreen === "screenQuiz") showQuizSetBrowser();
    }

    // Register with FocusMode, or retry once if not ready yet
    if (window.FocusMode && typeof window.FocusMode.register === "function") {
        window.FocusMode.register("quizSets", init);
    } else {
        setTimeout(() => {
            window.FocusMode?.register?.("quizSets", init);
        }, 300);
    }
})();