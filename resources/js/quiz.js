/**
 * quiz-sets.js — Quiz Sets feature for StudyHub Focus Mode
 *
 * Registers with window.FocusMode.register() so it runs after
 * focus-mode.js has initialized state and the DOM is ready.
 *
 * Flow:
 *  1. User clicks Quiz from the main menu → screenQuiz is shown
 *  2. screenQuiz renders the quiz-sets browser (list of quiz sets)
 *  3. "+ Add quizzes" opens the floating title panel
 *  4. Confirm → POST /focus-mode/quiz-sets → card appears in grid
 *  5. Cancel → panel closes, back to browser
 *  6. Clicking a quiz-set card → enter that set's content view
 *     (existing per-question quiz UI is preserved inside each set)
 */
(function () {
    "use strict";

    /* ── Wait for FocusMode plugin API ──────────────────────── */
    function init() {
        if (!window.FocusMode?.register) {
            setTimeout(init, 50);
            return;
        }
        window.FocusMode.register("quiz-sets", setupQuizSets);
    }

    function setupQuizSets(state, el) {
        /* ── Inject quiz-sets HTML into screenQuiz ───────────── */
        const screenQuiz = document.getElementById("screenQuiz");
        if (!screenQuiz) return;

        /* Keep whatever old content exists (e.g. the question-level
           modal) but inject the sets browser before it.           */
        screenQuiz.insertAdjacentHTML("afterbegin", buildBrowserHTML());

        /* ── Local state ─────────────────────────────────────── */
        // quizSets lives on the shared FocusMode state so other
        // modules can read it if needed.
        if (!Array.isArray(state.quizSets)) {
            state.quizSets      = Array.isArray(window.__focusQuizSets)
                                  ? window.__focusQuizSets
                                  : [];
        }
        state.activeQuizSetId = state.activeQuizSetId || null;

        /* ── DOM refs ─────────────────────────────────────────── */
        const $ = (id) => document.getElementById(id);
        const qel = {
            browser:     $("quizSetsBrowser"),
            grid:        $("quizSetGrid"),
            emptyState:  $("quizSetEmptyState"),
            addBtn:      $("quizSetAddBtn"),
            backdrop:    $("quizSetBackdrop"),
            input:       $("quizSetTitleInput"),
            confirmBtn:  $("quizSetConfirmBtn"),
            cancelBtn:   $("quizSetCancelBtn"),
            status:      $("quizSetStatus"),
            content:     $("quizSetContent"),
            backBtn:     $("quizSetBackBtn"),
            contentTitle: $("quizSetContentTitle"),
        };

        /* ── Helpers ─────────────────────────────────────────── */
        const esc = window.FocusMode.escHtml;
        const csrf = window.FocusMode.getCsrfToken;

        function setStatus(msg, isError = false) {
            if (!qel.status) return;
            qel.status.textContent = msg;
            qel.status.classList.toggle("error", isError);
        }

        /* ── Render grid ─────────────────────────────────────── */
        function renderGrid() {
            if (!qel.grid) return;
            const sets = state.quizSets;

            // Toggle empty state
            qel.emptyState?.classList.toggle("hidden", sets.length > 0);

            // Remove existing cards (keep the empty-state node)
            qel.grid.querySelectorAll(".quiz-set-card").forEach((c) => c.remove());

            sets.forEach((qs) => {
                const card = document.createElement("button");
                card.type = "button";
                card.className = "quiz-set-card";
                card.dataset.id = qs.id;
                card.innerHTML = `
                    <span class="quiz-set-card-name">${esc(qs.title)}</span>
                    <button class="quiz-set-delete-btn" data-id="${esc(String(qs.id))}" type="button" aria-label="Delete ${esc(qs.title)}">✕</button>
                `;

                // Click card → open set content
                card.addEventListener("click", (e) => {
                    // Don't open if delete btn was clicked
                    if (e.target.closest(".quiz-set-delete-btn")) return;
                    openSetContent(qs);
                });

                // Delete button
                card.querySelector(".quiz-set-delete-btn")
                    .addEventListener("click", (e) => {
                        e.stopPropagation();
                        deleteSet(qs.id);
                    });

                qel.grid.appendChild(card);
            });
        }

        /* ── Open / close floating panel ─────────────────────── */
        function openPanel() {
            if (!qel.backdrop) return;
            qel.input && (qel.input.value = "");
            setStatus("");
            qel.backdrop.classList.add("open");
            qel.input?.focus();
        }

        function closePanel() {
            if (!qel.backdrop) return;
            qel.backdrop.classList.remove("open");
            setStatus("");
            if (qel.input) qel.input.value = "";
        }

        /* ── Create quiz set ─────────────────────────────────── */
        async function createSet() {
            const title = (qel.input?.value || "").trim();
            if (!title) {
                setStatus("Please enter a quiz title.", true);
                qel.input?.focus();
                return;
            }

            try {
                setStatus("Creating…");
                if (qel.confirmBtn) qel.confirmBtn.disabled = true;

                const res = await fetch("/focus-mode/quiz-sets", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrf(),
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ title }),
                });

                const payload = await res.json();
                if (!res.ok) throw new Error(payload?.message || "Failed to create quiz set.");

                // Server should return { quiz_set: {...}, quiz_sets: [...] }
                state.quizSets = payload.quiz_sets
                    || [...state.quizSets, payload.quiz_set];

                renderGrid();
                closePanel();
            } catch (err) {
                setStatus(err.message || "Something went wrong.", true);
            } finally {
                if (qel.confirmBtn) qel.confirmBtn.disabled = false;
            }
        }

        /* ── Delete quiz set ─────────────────────────────────── */
        async function deleteSet(id) {
            if (!confirm("Delete this quiz set? This cannot be undone.")) return;
            try {
                const res = await fetch(`/focus-mode/quiz-sets/${id}`, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": csrf(),
                        Accept: "application/json",
                    },
                });
                if (!res.ok) {
                    const p = await res.json().catch(() => ({}));
                    throw new Error(p?.message || "Delete failed.");
                }
                state.quizSets = state.quizSets.filter((qs) => qs.id !== id);
                renderGrid();
            } catch (err) {
                alert(err.message || "Could not delete quiz set.");
            }
        }

        /* ── Open set content view ───────────────────────────── */
        function openSetContent(qs) {
            state.activeQuizSetId = qs.id;
            if (qel.browser)      qel.browser.classList.add("hidden");
            if (qel.content)      qel.content.classList.remove("hidden");
            if (qel.contentTitle) qel.contentTitle.textContent = qs.title;

            // Show the action buttons row and quiz slider for this set
            if (typeof window.FocusMode.showQuizSetUI === "function") {
                window.FocusMode.showQuizSetUI();
            }

            // Load questions for this set into the quiz slider
            const setQuizzes = qs.quizzes || qs.questions || [];
            state.quizzes = setQuizzes;

            if (typeof window.FocusMode.renderQuizSlider === "function") {
                window.FocusMode.renderQuizSlider(setQuizzes);
            }
        }

        function closeSetContent() {
            state.activeQuizSetId = null;
            if (qel.content)  qel.content.classList.add("hidden");
            if (qel.browser)  qel.browser.classList.remove("hidden");

            // Hide the action buttons row and quiz slider when back at browser
            if (typeof window.FocusMode.hideQuizSetUI === "function") {
                window.FocusMode.hideQuizSetUI();
            }
        }

        /* ── Event listeners ─────────────────────────────────── */
        qel.addBtn?.addEventListener("click", openPanel);
        qel.cancelBtn?.addEventListener("click", closePanel);
        qel.confirmBtn?.addEventListener("click", createSet);

        // Confirm on Enter inside input
        qel.input?.addEventListener("keydown", (e) => {
            if (e.key === "Enter") { e.preventDefault(); createSet(); }
            if (e.key === "Escape") closePanel();
        });

        // Click outside form closes panel
        qel.backdrop?.addEventListener("click", (e) => {
            if (e.target === qel.backdrop) closePanel();
        });

        // Back button from content view
        qel.backBtn?.addEventListener("click", closeSetContent);

        /* ── Initial render ──────────────────────────────────── */
        renderGrid();
    }

    /* ── HTML template ───────────────────────────────────────── */
    function buildBrowserHTML() {
        return `
        <!-- ── Quiz Sets Browser ── -->
        <div id="quizSetsBrowser">

            <h2 class="quiz-screen-heading">Quiz</h2>
            <p class="quiz-section-label">My Quizzes</p>

            <!-- Grid populated by JS -->
            <div class="quiz-set-grid" id="quizSetGrid">
                <p class="quiz-set-empty-state" id="quizSetEmptyState">No quizzes created yet.</p>
            </div>

            <!-- "+ Add quizzes" button -->
            <button class="quiz-set-add-btn" id="quizSetAddBtn" type="button">
                <span class="quiz-set-add-circle" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke="#fff" stroke-width="2.5" stroke-linecap="round"
                              fill="none" d="M12 5v14M5 12h14"/>
                    </svg>
                </span>
                Add quizzes
            </button>

        </div>

        <!-- ── Quiz Set Content view (shown after selecting a set) ── -->
        <div id="quizSetContent" class="hidden">
            <div class="quiz-set-content-header">
                <button class="quiz-set-back-btn" id="quizSetBackBtn" type="button">← Back to Quizzes</button>
                <h2 class="quiz-set-content-title" id="quizSetContentTitle"></h2>
            </div>

            <!-- Action buttons for this quiz set -->
            <div class="study-action-row quiz-action-row" id="quizSetActionRow">
                <button class="menu-btn study-action-btn" id="quizUploadPromptBtn" type="button">
                    <span class="menu-btn-icon">📎</span>
                    <span class="menu-btn-label">Upload Materials</span>
                    <span class="menu-btn-desc">Attach a file to this quiz set</span>
                </button>
                <button class="menu-btn study-action-btn" id="quizCreatePromptBtn" type="button">
                    <span class="menu-btn-icon">📝</span>
                    <span class="menu-btn-label">Create Quiz Questions</span>
                    <span class="menu-btn-desc">Add questions to this quiz set</span>
                </button>
            </div>

            <!-- Quiz question slider -->
            <section class="flashcard-stage" id="quizStage">
                <button class="flashcard-nav flashcard-nav-left" id="quizPrevBtn" type="button" aria-label="Previous question">‹</button>
                <div class="flashcard-stage-viewport">
                    <div class="flashcard-stage-track" id="quizStageTrack"></div>
                </div>
                <button class="flashcard-nav flashcard-nav-right" id="quizNextBtn" type="button" aria-label="Next question">›</button>
            </section>
            <div class="flashcard-stage-counter" id="quizStageCounter"></div>
        </div>

        <!-- ── Floating panel (title input) ── -->
        <div class="quiz-set-backdrop" id="quizSetBackdrop" role="dialog"
             aria-modal="true" aria-labelledby="quizSetTitleInput">
            <div class="quiz-set-form">
                <input  class="quiz-set-input"
                        id="quizSetTitleInput"
                        type="text"
                        maxlength="120"
                        placeholder="Quiz title" />
                <div class="quiz-set-form-actions">
                    <button class="quiz-set-confirm-btn" id="quizSetConfirmBtn" type="button">Confirm</button>
                    <button class="quiz-set-cancel-btn"  id="quizSetCancelBtn"  type="button">Cancel</button>
                </div>
                <div class="quiz-set-status" id="quizSetStatus"></div>
            </div>
        </div>
        `;
    }

    /* ── Bootstrap ───────────────────────────────────────────── */
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();