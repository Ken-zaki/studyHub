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
 *  5. FIX: Green "Create Quiz Questions" button now opens the modal directly
 *     without any dependency on the pink button. Pink button can be safely removed.
 *  6. Review cards show only question, answer, and explanation (no choices).
 *     Each card has a trashcan delete button in the upper-right corner.
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
            screenMenuBackBtn: $("quizScreenMenuBackBtn"),
            track:        $("quizInteractiveTrack"),
            counter:      $("quizInteractiveCounter"),
            prevBtn:      $("quizInteractivePrevBtn"),
            nextBtn:      $("quizInteractiveNextBtn"),
        };

        const esc  = window.FocusMode.escHtml;
        const csrf = window.FocusMode.getCsrfToken;

        let quizSlideIndex = 0;

        /* ── Trashcan SVG ────────────────────────────────────── */
        const TRASH_ICON = [
            '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"',
            ' viewBox="0 0 24 24" fill="none" stroke="currentColor"',
            ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round">',
            '<polyline points="3 6 5 6 21 6"/>',
            '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
            '<path d="M10 11v6"/>',
            '<path d="M14 11v6"/>',
            '<path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
            '</svg>',
        ].join("");

        /* ── Status helper ───────────────────────────────────── */
        function setStatus(msg, isError = false) {
            if (!qel.status) return;
            qel.status.textContent = msg;
            qel.status.classList.toggle("error", isError);
        }

        /* ── Open quiz modal directly ────────────────────────── */
        function openQuizCreateModal() {
            const modal = document.getElementById("quizModal");
            if (!modal) return;
            modal.classList.remove("hidden");
            modal.setAttribute("aria-hidden", "false");
            document.getElementById("quizUploadPane")?.classList.add("hidden");
            document.getElementById("quizCreatePane")?.classList.remove("hidden");
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
                card.innerHTML =
                    '<span class="quiz-set-card-name">' + esc(qs.name || qs.title) + "</span>" +
                    '<span class="quiz-set-card-count">' + count + " question" + (count !== 1 ? "s" : "") + "</span>" +
                    '<button class="quiz-set-delete-btn" data-id="' + esc(String(qs.id)) + '" type="button" aria-label="Delete ' + esc(qs.title) + '">\u2715</button>';

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
            // Restore both back buttons on the browser screen
            qel.screenMenuBackBtn?.classList.remove("hidden");
            qel.backBtn?.classList.remove("hidden");

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
        // AFTER
        async function createSet() {
            const inputEl = qel.input || document.getElementById("quizSetTitleInput");
            const title = (inputEl?.value || "").trim();
            if (!title) {
                setStatus("Please enter a quiz title.", true);
                inputEl?.focus();
                return;
            }
            try {
                setStatus("Creating\u2026");
                if (qel.confirmBtn) qel.confirmBtn.disabled = true;
                const res = await fetch("/focus-mode/quiz-sets", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrf(),
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ title: title }),
                });
                const payload = await res.json();
                if (!res.ok) throw new Error(payload?.message || "Failed to create quiz set.");
                state.quizSets = payload.quizSets || [...state.quizSets, payload.quiz_set];
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
                const res = await fetch("/focus-mode/quiz-sets/" + id, {
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
           (no choices; trashcan delete button top-right of each card)
        ══════════════════════════════════════════════════════ */
        function renderReviewPanel(questions) {
            if (!qel.reviewList) return;

            if (!questions.length) {
                qel.reviewList.innerHTML = '<p class="quiz-review-empty">No questions yet.<br>Click "Create Quiz Questions" to add some.</p>';
                if (qel.startQuizBtn) qel.startQuizBtn.classList.add("hidden");
                return;
            }

            if (qel.startQuizBtn) qel.startQuizBtn.classList.remove("hidden");

            qel.reviewList.innerHTML = questions.map(function (q, i) {
                const opts = q.options || {
                    A: q.option_a,
                    B: q.option_b,
                    C: q.option_c,
                    D: q.option_d,
                };
                const explanation = q.explanation
                    ? '<div class="quiz-review-card-explanation">' + esc(q.explanation) + "</div>"
                    : "";
                return (
                    '<div class="quiz-review-card" data-question-id="' + esc(String(q.id)) + '">' +
                        '<div class="quiz-review-card-top">' +
                            '<div class="quiz-review-card-number">Question ' + (i + 1) + "</div>" +
                            '<button class="quiz-review-card-delete-btn" type="button" aria-label="Delete question ' + (i + 1) + '">' +
                                TRASH_ICON +
                            "</button>" +
                        "</div>" +
                        '<div class="quiz-review-card-question">' + esc(q.question) + "</div>" +
                        '<div class="quiz-review-card-answer-label">Answer</div>' +
                        '<div class="quiz-review-card-answer">' + esc(opts[q.correct_option] || q.correct_option) + "</div>" +
                        explanation +
                    "</div>"
                );
            }).join("");

            /* Bind delete buttons */
            qel.reviewList.querySelectorAll(".quiz-review-card").forEach(function (card) {
                card.querySelector(".quiz-review-card-delete-btn")
                    ?.addEventListener("click", function () {
                        deleteQuestion(card.dataset.questionId);
                    });
            });
        }

        /* ── Delete a single question ────────────────────────── */
        async function deleteQuestion(id) {
            if (!confirm("Delete this question? This cannot be undone.")) return;
            try {
                const res = await fetch("/focus-mode/quizzes/" + id, {
                    method: "DELETE",
                    headers: { "X-CSRF-TOKEN": csrf(), Accept: "application/json" },
                });
                if (!res.ok) {
                    const p = await res.json().catch(() => ({}));
                    throw new Error(p?.message || "Delete failed.");
                }
                /* Remove from local state and re-render */
                const qs = state.quizSets.find((s) => s.id === state.activeQuizSetId);
                if (qs) {
                    const key = qs.quizzes ? "quizzes" : "questions";
                    qs[key] = qs[key].filter((q) => String(q.id) !== String(id));
                    state.quizzes = qs[key];
                    renderReviewPanel(qs[key]);
                    renderQuizInteractive(qs[key]);
                }
            } catch (err) {
                alert(err.message || "Could not delete question.");
            }
        }

        /* ══════════════════════════════════════════════════════
           QUIZ INTERACTIVE PANEL — answerable questions
        ══════════════════════════════════════════════════════ */
        function renderQuizInteractive(questions) {
            if (!qel.track) return;
            quizSlideIndex = 0;

            if (!questions.length) {
                qel.track.innerHTML = '<div class="quiz-interactive-empty">No questions to quiz on yet.</div>';
                updateInteractiveNav(0, 0);
                return;
            }

            qel.track.innerHTML = questions.map(function (q, i) {
                const opts = q.options || {
                    A: q.option_a,
                    B: q.option_b,
                    C: q.option_c,
                    D: q.option_d,
                };
                const optPairs = Object.entries(opts).filter(([, v]) => v);
                const choiceBtns = optPairs.map(([k, v]) =>
                    '<button class="quiz-choice-btn" data-key="' + esc(k) + '" type="button">' +
                        '<span class="quiz-choice-key">' + esc(k) + ".</span>" +
                        '<span class="quiz-choice-val">' + esc(v) + "</span>" +
                    "</button>"
                ).join("");
                const explanation = q.explanation
                    ? '<div class="quiz-answer-explanation">' + esc(q.explanation) + "</div>"
                    : "";
                return (
                    '<div class="quiz-interactive-slide" data-index="' + i + '">' +
                        '<div class="quiz-question-card">' +
                            '<div class="quiz-question-label">Question ' + (i + 1) + "</div>" +
                            '<div class="quiz-question-text">' + esc(q.question) + "</div>" +
                            '<div class="quiz-choices-grid">' + choiceBtns + "</div>" +
                            '<div class="quiz-answer-reveal hidden" data-correct="' + esc(q.correct_option) + '">' +
                                '<div class="quiz-answer-label">CORRECT ANSWER: <span class="quiz-answer-letter">' + esc(q.correct_option) + "</span></div>" +
                                explanation +
                            "</div>" +
                        "</div>" +
                    "</div>"
                );
            }).join("");

            /* Bind answer logic for each slide */
            qel.track.querySelectorAll(".quiz-interactive-slide").forEach(function (slide) {
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
                qel.track.style.transform = "translateX(calc(-" + index + " * 100%))";
            }
            if (qel.counter) qel.counter.textContent = total ? (index + 1) + " / " + total : "";
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
            // Hide back to menu, keep back to quizzes visible
            qel.screenMenuBackBtn?.classList.add("hidden");
            qel.backBtn?.classList.remove("hidden");
        }

        function showQuizPanel() {
            qel.reviewPanel?.classList.add("hidden");
            qel.quizPanel?.classList.remove("hidden");
            // Hide both back buttons on quiz panel
            qel.screenMenuBackBtn?.classList.add("hidden");
            qel.backBtn?.classList.add("hidden");
        }

        function closeSetContent() {
            state.activeQuizSetId = null;
            if (qel.content)  qel.content.classList.add("hidden");
            if (qel.browser)  qel.browser.classList.remove("hidden");
            // Restore both back buttons when back on browser
            qel.screenMenuBackBtn?.classList.remove("hidden");
            qel.backBtn?.classList.remove("hidden");
        }

        /* ── Open / close set content ────────────────────────── */
        function openSetContent(qs) {
            state.activeQuizSetId = qs.id;
            if (qel.browser)      qel.browser.classList.add("hidden");
            if (qel.content)      qel.content.classList.remove("hidden");
            if (qel.contentTitle) qel.contentTitle.textContent = qs.name || qs.title;

            const questions = qs.quizzes || qs.questions || [];
            state.quizzes = questions;

            /* Wire the green "Create Quiz Questions" button directly to the modal.
               Uses a data attribute to avoid attaching duplicate listeners. */
            const greenCreateBtn = document.getElementById("quizReviewCreateBtn");
            if (greenCreateBtn && !greenCreateBtn.dataset.wired) {
                greenCreateBtn.dataset.wired = "1";
                greenCreateBtn.addEventListener("click", openQuizCreateModal);
            }

            /* Render both panels; start on review */
            renderReviewPanel(questions);
            renderQuizInteractive(questions);
            showReviewPanel();
        }

        /* ── Expose renderQuizSlider so focus-mode.js can refresh after save ── */
        window.FocusMode.renderQuizSlider = function (questions) {
            const qs = state.quizSets.find((s) => s.id === state.activeQuizSetId);
            const list = questions
                ?? (qs ? (qs.quizzes || qs.questions || []) : state.quizzes || []);

            // Sync local state so the set always has the latest questions
            if (qs && list) {
                const key = qs.quizzes ? "quizzes" : "questions";
                qs[key] = list;
            }
            state.quizzes = list;

            renderReviewPanel(list);
            renderQuizInteractive(list);
            showReviewPanel();
        };

        /* ── Event listeners ─────────────────────────────────── */
        qel.addBtn?.addEventListener("click", openPanel);
        qel.cancelBtn?.addEventListener("click", closePanel);
        qel.confirmBtn?.addEventListener("click", createSet);

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
        return [
            "<!-- Quiz Sets Browser -->",
            '<div id="quizSetsBrowser">',
            '    <h2 class="quiz-screen-heading">Quiz</h2>',
            '    <p class="quiz-section-label">My Quizzes</p>',
            '    <div class="quiz-set-grid" id="quizSetGrid">',
            '        <p class="quiz-set-empty-state" id="quizSetEmptyState">No quizzes created yet.</p>',
            "    </div>",
            '    <button class="quiz-set-add-btn" id="quizSetAddBtn" type="button">',
            '        <span class="quiz-set-add-circle" aria-hidden="true">',
            '            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">',
            '                <path stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none" d="M12 5v14M5 12h14"/>',
            "            </svg>",
            "        </span>",
            "        Add quizzes",
            "    </button>",
            "</div>",

            "<!-- Quiz Set Content view -->",
            '<div id="quizSetContent" class="hidden">',
            '    <div class="quiz-set-content-header">',
            '        <button class="quiz-set-back-btn" id="quizSetBackBtn" type="button">\u2190 Back to Quizzes</button>',
            '        <h2 class="quiz-set-content-title" id="quizSetContentTitle"></h2>',
            "    </div>",

            "    <!-- REVIEW PANEL -->",
            '    <div id="quizReviewPanel">',
            '        <div class="rm-library-header">',
            '            <span class="rm-library-label">My Quiz Questions</span>',
            '            <div style="display:flex; gap: 10px;">',
            '                <button class="rm-upload-btn" id="quizReviewCreateBtn" type="button">Create Quiz Questions</button>',
            '                <button class="rm-upload-btn" id="quizStartBtn" type="button">Start Quiz</button>',
            '            </div>',
            '        </div>',
            '        <div class="quiz-review-list" id="quizReviewList"></div>',
            "    </div>",

            "    <!-- INTERACTIVE PANEL -->",
            '    <div id="quizInteractivePanel" class="hidden">',
            '        <div class="quiz-interactive-header">',
            '            <button class="quiz-set-back-btn" id="quizInteractiveBackBtn" type="button">\u2190 Back to Review</button>',
            "        </div>",
            '        <div class="quiz-interactive-stage">',
            '            <button class="quiz-nav quiz-nav-left" id="quizInteractivePrevBtn" type="button" aria-label="Previous question">\u2039</button>',
            '            <div class="quiz-interactive-viewport">',
            '                <div class="quiz-interactive-track" id="quizInteractiveTrack"></div>',
            "            </div>",
            '            <button class="quiz-nav quiz-nav-right" id="quizInteractiveNextBtn" type="button" aria-label="Next question">\u203a</button>',
            "        </div>",
            '        <div class="quiz-interactive-counter" id="quizInteractiveCounter"></div>',
            "    </div>",
            "</div>",

            "<!-- Floating title-input panel -->",
            '<div class="quiz-set-backdrop" id="quizSetBackdrop" role="dialog" aria-modal="true" aria-labelledby="quizSetTitleInput">',
            '    <div class="quiz-set-form">',
            '        <input class="quiz-set-input" id="quizSetTitleInput" type="text" maxlength="120" placeholder="Quiz title" />',
            '        <div class="quiz-set-form-actions">',
            '            <button class="quiz-set-confirm-btn" id="quizSetConfirmBtn" type="button">Confirm</button>',
            '            <button class="quiz-set-cancel-btn" id="quizSetCancelBtn" type="button">Cancel</button>',
            "        </div>",
            '        <div class="quiz-set-status" id="quizSetStatus"></div>',
            "    </div>",
            "</div>",
        ].join("\n");
    }

    /* ── Bootstrap ───────────────────────────────────────────── */
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
