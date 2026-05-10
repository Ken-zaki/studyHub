/**
 * quiz.js — Quiz Sets feature for StudyHub Focus Mode
 *
 * Changes from original:
 *  1. Removed 4-question limit — slider now uses percentage-based transform
 *     so any number of questions works correctly.
 *  2. Set content view now shows a REVIEW panel first (Q+A cards, no choices)
 *     with a "Start Quiz" button to enter the interactive quiz.
 *  3. On correct answer → green "Correct!" toast notification (no reveal panel).
 *     On wrong answer → answer + explanation reveal panel (unchanged).
 *  4. Quiz mode is a separate view toggled by "Start Quiz" / "← Back to Review".
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
        const screenQuiz = document.getElementById("screenQuiz");
        if (!screenQuiz) return;

        screenQuiz.insertAdjacentHTML("afterbegin", buildBrowserHTML());

        if (!Array.isArray(state.quizSets)) {
            state.quizSets = Array.isArray(window.__focusQuizSets)
                ? window.__focusQuizSets
                : [];
        }
        state.activeQuizSetId = state.activeQuizSetId || null;

        /* ── DOM refs ─────────────────────────────────────────── */
        const $ = (id) => document.getElementById(id);
        const qel = {
            browser:      $("quizSetsBrowser"),
            grid:         $("quizSetGrid"),
            emptyState:   $("quizSetEmptyState"),
            addBtn:       $("quizSetAddBtn"),
            backdrop:     $("quizSetBackdrop"),
            input:        $("quizSetTitleInput"),
            confirmBtn:   $("quizSetConfirmBtn"),
            cancelBtn:    $("quizSetCancelBtn"),
            status:       $("quizSetStatus"),
            // Content view
            content:      $("quizSetContent"),
            backBtn:      $("quizSetBackBtn"),
            contentTitle: $("quizSetContentTitle"),
            // Review panel
            reviewPanel:  $("quizReviewPanel"),
            reviewList:   $("quizReviewList"),
            startQuizBtn: $("quizStartBtn"),
            // Quiz panel
            quizPanel:    $("quizInteractivePanel"),
            quizBackBtn:  $("quizInteractiveBackBtn"),
            track:        $("quizInteractiveTrack"),
            counter:      $("quizInteractiveCounter"),
            prevBtn:      $("quizInteractivePrevBtn"),
            nextBtn:      $("quizInteractiveNextBtn"),
        };

        const esc  = window.FocusMode.escHtml;
        const csrf = window.FocusMode.getCsrfToken;

        let quizSlideIndex = 0;

        /* ── Status helper ───────────────────────────────────── */
        function setStatus(msg, isError = false) {
            if (!qel.status) return;
            qel.status.textContent = msg;
            qel.status.classList.toggle("error", isError);
        }

        /* ── "Correct!" toast ────────────────────────────────── */
        function showCorrectToast() {
            const existing = document.getElementById("quizCorrectToast");
            if (existing) existing.remove();

            const toast = document.createElement("div");
            toast.id = "quizCorrectToast";
            toast.className = "quiz-correct-toast";
            toast.textContent = "Correct!";
            document.body.appendChild(toast);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => toast.classList.add("visible"));
            });

            setTimeout(() => {
                toast.classList.remove("visible");
                setTimeout(() => toast.remove(), 400);
            }, 1800);
        }

        /* ── Render quiz set grid ────────────────────────────── */
        function renderGrid() {
            if (!qel.grid) return;
            const sets = state.quizSets;
            qel.emptyState?.classList.toggle("hidden", sets.length > 0);
            qel.grid.querySelectorAll(".quiz-set-card").forEach((c) => c.remove());

            sets.forEach((qs) => {
                const card = document.createElement("button");
                card.type = "button";
                card.className = "quiz-set-card";
                card.dataset.id = qs.id;
                const count = (qs.quizzes || qs.questions || []).length;
                card.innerHTML = `
                    <span class="quiz-set-card-name">${esc(qs.title)}</span>
                    <span class="quiz-set-card-count">${count} question${count !== 1 ? "s" : ""}</span>
                    <button class="quiz-set-delete-btn" data-id="${esc(String(qs.id))}" type="button" aria-label="Delete ${esc(qs.title)}">✕</button>
                `;

                card.addEventListener("click", (e) => {
                    if (e.target.closest(".quiz-set-delete-btn")) return;
                    openSetContent(qs);
                });

                card.querySelector(".quiz-set-delete-btn").addEventListener("click", (e) => {
                    e.stopPropagation();
                    deleteSet(qs.id);
                });

                qel.grid.appendChild(card);
            });
        }

        /* ── Floating panel ──────────────────────────────────── */
        function openPanel() {
            if (!qel.backdrop) return;
            if (qel.input) qel.input.value = "";
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
                state.quizSets = payload.quiz_sets || [...state.quizSets, payload.quiz_set];
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
                    headers: { "X-CSRF-TOKEN": csrf(), Accept: "application/json" },
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

        /* ══════════════════════════════════════════════════════
           REVIEW PANEL — shows Q+A cards for studying
        ══════════════════════════════════════════════════════ */
        function renderReviewPanel(questions) {
            if (!qel.reviewList) return;

            if (!questions.length) {
                qel.reviewList.innerHTML = `<p class="quiz-review-empty">No questions yet.<br>Click "Create Quiz Questions" to add some.</p>`;
                if (qel.startQuizBtn) qel.startQuizBtn.classList.add("hidden");
                return;
            }

            if (qel.startQuizBtn) qel.startQuizBtn.classList.remove("hidden");

            qel.reviewList.innerHTML = questions.map((q, i) => {
                const opts = q.options || {
                    A: q.option_a,
                    B: q.option_b,
                    C: q.option_c,
                    D: q.option_d,
                };
                const optPairs = Object.entries(opts).filter(([, v]) => v);
                return `
                <div class="quiz-review-card">
                    <div class="quiz-review-card-number">Question ${i + 1}</div>
                    <div class="quiz-review-card-question">${esc(q.question)}</div>
                    <div class="quiz-review-card-answer-label">Answer</div>
                    <div class="quiz-review-card-answer">${esc(opts[q.correct_option] || q.correct_option)}</div>
                    ${optPairs.length ? `
                    <div class="quiz-review-card-options">
                        ${optPairs.map(([k, v]) => `
                            <div class="quiz-review-card-option ${k === q.correct_option ? "is-correct" : ""}">
                                <span class="quiz-review-option-key">${esc(k)}</span>
                                <span class="quiz-review-option-val">${esc(v)}</span>
                            </div>`).join("")}
                    </div>` : ""}
                    ${q.explanation ? `<div class="quiz-review-card-explanation">${esc(q.explanation)}</div>` : ""}
                </div>`;
            }).join("");
        }

        /* ══════════════════════════════════════════════════════
           QUIZ INTERACTIVE PANEL — answerable questions
        ══════════════════════════════════════════════════════ */
        function renderQuizInteractive(questions) {
            if (!qel.track) return;
            quizSlideIndex = 0;

            if (!questions.length) {
                qel.track.innerHTML = `<div class="quiz-interactive-empty">No questions to quiz on yet.</div>`;
                updateInteractiveNav(0, 0);
                return;
            }

            qel.track.innerHTML = questions.map((q, i) => {
                const opts = q.options || {
                    A: q.option_a,
                    B: q.option_b,
                    C: q.option_c,
                    D: q.option_d,
                };
                const optPairs = Object.entries(opts).filter(([, v]) => v);
                return `
                <div class="quiz-interactive-slide" data-index="${i}">
                    <div class="quiz-question-card">
                        <div class="quiz-question-label">Question ${i + 1}</div>
                        <div class="quiz-question-text">${esc(q.question)}</div>
                        <div class="quiz-choices-grid">
                            ${optPairs.map(([k, v]) => `
                            <button class="quiz-choice-btn" data-key="${esc(k)}" type="button">
                                <span class="quiz-choice-key">${esc(k)}.</span>
                                <span class="quiz-choice-val">${esc(v)}</span>
                            </button>`).join("")}
                        </div>
                        <div class="quiz-answer-reveal hidden" data-correct="${esc(q.correct_option)}">
                            <div class="quiz-answer-label">CORRECT ANSWER: <span class="quiz-answer-letter">${esc(q.correct_option)}</span></div>
                            ${q.explanation ? `<div class="quiz-answer-explanation">${esc(q.explanation)}</div>` : ""}
                        </div>
                    </div>
                </div>`;
            }).join("");

            /* Bind answer logic for each slide */
            qel.track.querySelectorAll(".quiz-interactive-slide").forEach((slide) => {
                const btns    = slide.querySelectorAll(".quiz-choice-btn");
                const reveal  = slide.querySelector(".quiz-answer-reveal");
                const correct = reveal?.dataset.correct;

                btns.forEach((btn) => {
                    btn.addEventListener("click", () => {
                        if (slide.dataset.answered) return;
                        slide.dataset.answered = "1";

                        const chosen    = btn.dataset.key;
                        const isCorrect = chosen === correct;

                        /* Disable all buttons */
                        btns.forEach((b) => {
                            b.disabled = true;
                            if (b.dataset.key === correct) b.classList.add("quiz-choice-correct");
                        });

                        if (isCorrect) {
                            /* ✅ Correct → green toast, no reveal */
                            btn.classList.add("quiz-choice-correct");
                            showCorrectToast();
                        } else {
                            /* ❌ Wrong → mark choice red, show answer + explanation */
                            btn.classList.add("quiz-choice-wrong");
                            reveal?.classList.remove("hidden");
                        }
                    });
                });
            });

            goToInteractiveSlide(0, questions.length);
        }

        /* ── Slide navigation (percentage-based — works for any count) ── */
        function goToInteractiveSlide(index, total) {
            total = total ?? qel.track?.querySelectorAll(".quiz-interactive-slide").length ?? 0;
            index = Math.max(0, Math.min(index, total - 1));
            quizSlideIndex = index;

            if (qel.track) {
                /* translateX by -N * 100% moves exactly one slide-width per step,
                   regardless of how many slides exist. No pixel math needed. */
                qel.track.style.transform = `translateX(calc(-${index} * 100%))`;
            }
            if (qel.counter) qel.counter.textContent = total ? `${index + 1} / ${total}` : "";
            updateInteractiveNav(index, total);
        }

        function updateInteractiveNav(i, t) {
            if (qel.prevBtn) qel.prevBtn.disabled = i <= 0 || t === 0;
            if (qel.nextBtn) qel.nextBtn.disabled = i >= t - 1 || t === 0;
        }

        /* ── View switching ──────────────────────────────────── */
        function showReviewPanel() {
            qel.reviewPanel?.classList.remove("hidden");
            qel.quizPanel?.classList.add("hidden");
        }
        function showQuizPanel() {
            qel.reviewPanel?.classList.add("hidden");
            qel.quizPanel?.classList.remove("hidden");
        }

        /* ── Open / close set content ────────────────────────── */
        function openSetContent(qs) {
            state.activeQuizSetId = qs.id;
            if (qel.browser)      qel.browser.classList.add("hidden");
            if (qel.content)      qel.content.classList.remove("hidden");
            if (qel.contentTitle) qel.contentTitle.textContent = qs.title;

            const questions = qs.quizzes || qs.questions || [];
            state.quizzes = questions;

            /* Move the Create button into the actions row beside Start Quiz */
            const actionsRow = document.querySelector(".quiz-review-actions");
            const createBtn  = document.getElementById("quizCreatePromptBtn");
            if (actionsRow && createBtn && !actionsRow.contains(createBtn)) {
                actionsRow.insertBefore(createBtn, actionsRow.firstChild);
            }
            if (createBtn) createBtn.classList.remove("hidden");
            
            /* Render both panels; start on review */
            renderReviewPanel(questions);
            renderQuizInteractive(questions);
            showReviewPanel();
        }

        function closeSetContent() {
            state.activeQuizSetId = null;
            if (qel.content)  qel.content.classList.add("hidden");
            if (qel.browser)  qel.browser.classList.remove("hidden");

          const createBtn = document.getElementById("quizCreatePromptBtn");
            if (createBtn) createBtn.classList.add("hidden");
        }

        /* ── Expose renderQuizSlider so focus-mode.js can refresh after save ── */
        window.FocusMode.renderQuizSlider = function (questions) {
            renderReviewPanel(questions);
            renderQuizInteractive(questions);
            showReviewPanel();
        };

        /* ── Event listeners ─────────────────────────────────── */
        qel.addBtn?.addEventListener("click", openPanel);
        qel.cancelBtn?.addEventListener("click", closePanel);
        qel.confirmBtn?.addEventListener("click", createSet);

        // Wire the green "Create Quiz Questions" button to the existing quiz modal
        document.querySelector("#quizReviewPanel .quiz-action-btn:not(#quizStartBtn)")
            ?.addEventListener("click", () => {
                if (typeof window.FocusMode?.el?.quizCreatePromptBtn?.click === "function") {
                    window.FocusMode.el.quizCreatePromptBtn.click();
                } else {
                    // Fallback: dispatch click on the original hidden pink button
                    document.getElementById("quizCreatePromptBtn")?.click();
                }
            });

        qel.input?.addEventListener("keydown", (e) => {
            if (e.key === "Enter")  { e.preventDefault(); createSet(); }
            if (e.key === "Escape") closePanel();
        });
        qel.backdrop?.addEventListener("click", (e) => {
            if (e.target === qel.backdrop) closePanel();
        });

        qel.backBtn?.addEventListener("click", closeSetContent);

        /* Start Quiz button */
        qel.startQuizBtn?.addEventListener("click", () => {
            /* Re-render quiz to reset answered state */
            const qs = state.quizSets.find((s) => s.id === state.activeQuizSetId);
            const questions = qs ? (qs.quizzes || qs.questions || []) : state.quizzes || [];
            renderQuizInteractive(questions);
            showQuizPanel();
        });

        /* Back to Review from Quiz */
        qel.quizBackBtn?.addEventListener("click", showReviewPanel);

        /* Interactive slider nav */
        qel.prevBtn?.addEventListener("click", () => {
            const total = qel.track?.querySelectorAll(".quiz-interactive-slide").length ?? 0;
            goToInteractiveSlide(quizSlideIndex - 1, total);
        });
        qel.nextBtn?.addEventListener("click", () => {
            const total = qel.track?.querySelectorAll(".quiz-interactive-slide").length ?? 0;
            goToInteractiveSlide(quizSlideIndex + 1, total);
        });

        /* ── Initial render ──────────────────────────────────── */
        renderGrid();
    }

    /* ══════════════════════════════════════════════════════════
       HTML TEMPLATE
    ══════════════════════════════════════════════════════════ */
    function buildBrowserHTML() {
        return `
        <!-- ── Quiz Sets Browser ── -->
        <div id="quizSetsBrowser">
            <h2 class="quiz-screen-heading">Quiz</h2>
            <p class="quiz-section-label">My Quizzes</p>

            <div class="quiz-set-grid" id="quizSetGrid">
                <p class="quiz-set-empty-state" id="quizSetEmptyState">No quizzes created yet.</p>
            </div>

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

        <!-- ── Quiz Set Content view ── -->
        <div id="quizSetContent" class="hidden">
            <div class="quiz-set-content-header">
                <button class="quiz-set-back-btn" id="quizSetBackBtn" type="button">← Back to Quizzes</button>
                <h2 class="quiz-set-content-title" id="quizSetContentTitle"></h2>
            </div>

              <!-- REVIEW PANEL: Q+A cards for studying -->
                <div id="quizReviewPanel">

                    <div class="quiz-review-actions">

                        <button class="quiz-action-btn"
                                type="button">
                            Create Quiz Questions
                        </button>

                        <button class="quiz-action-btn"
                                id="quizStartBtn"
                                type="button">
                            Start Quiz
                        </button>

                    </div>

                    <div class="quiz-review-list" id="quizReviewList"></div>

                </div>

            <!-- INTERACTIVE PANEL: answerable quiz -->
            <div id="quizInteractivePanel" class="hidden">
                <div class="quiz-interactive-header">
                    <button class="quiz-set-back-btn" id="quizInteractiveBackBtn" type="button">← Back to Review</button>
                </div>

                <!-- Slider -->
                <div class="quiz-interactive-stage">
                    <button class="quiz-nav quiz-nav-left"  id="quizInteractivePrevBtn" type="button" aria-label="Previous question">‹</button>
                    <div class="quiz-interactive-viewport">
                        <div class="quiz-interactive-track" id="quizInteractiveTrack"></div>
                    </div>
                    <button class="quiz-nav quiz-nav-right" id="quizInteractiveNextBtn" type="button" aria-label="Next question">›</button>
                </div>
                <div class="quiz-interactive-counter" id="quizInteractiveCounter"></div>
            </div>
        </div>

        <!-- ── Floating title-input panel ── -->
        <div class="quiz-set-backdrop" id="quizSetBackdrop" role="dialog"
             aria-modal="true" aria-labelledby="quizSetTitleInput">
            <div class="quiz-set-form">
                <input class="quiz-set-input"
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