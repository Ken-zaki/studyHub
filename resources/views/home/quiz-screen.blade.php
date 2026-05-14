{{--
    ══════════════════════════════════════════════════════════════
    PARTIAL: quiz-screen.blade.php  (updated)
    ══════════════════════════════════════════════════════════════
    Replace the  #screenQuiz block  and the Quiz Modal block
    inside focus-mode.blade.php with this file.

    WHAT CHANGED:
    ─────────────────────────────────────────────────────────────
    • The old #quizSetActionRow, #quizStage, #quizStageTrack,
      #quizStageCounter, and their nav buttons have been REMOVED
      from the blade. quiz.js now injects its own complete HTML
      (browser, review panel, interactive slider) via
      insertAdjacentHTML("afterbegin", ...).

    • The Quiz Studio Modal (quizModal, quizForm, etc.) is
      preserved unchanged — focus-mode.js still manages it.

    HOW TO USE:
    ─────────────────────────────────────────────────────────────
    In focus-mode.blade.php:

    A) Replace the entire #screenQuiz block AND the Quiz Modal
       block with this file.

    B) In <head>, make sure quiz.css is linked:
         <link rel="stylesheet" href="{{ asset('css/quiz.css') }}">

    C) The data-bootstrap script block should include:
         window.__focusQuizSets = @json($quizSets ?? []);

    D) Load quiz.js after focus-mode.js:
         <script src="{{ asset('js/quiz.js') }}"></script>
    ══════════════════════════════════════════════════════════════
--}}

{{-- ══ SCREEN: QUIZ ══ --}}
<div class="screen screen-content hidden" id="screenQuiz">

    {{--
        quiz.js will inject the following as the FIRST children here:
          · #quizSetsBrowser     — set list / grid view
          · #quizSetContent      — per-set content (review + interactive)
          · .quiz-set-backdrop   — floating title-input panel

        Nothing else is needed in this div except the modal and back button.
    --}}

    {{-- ── Quiz Studio Modal trigger buttons are rendered by quiz.js ── --}}
    {{-- ── Quiz Studio Modal (Upload / Create panes) ── --}}
    <div class="flashcard-modal-backdrop hidden" id="quizModalBackdrop"></div>
    <div class="flashcard-modal hidden" id="quizModal" role="dialog"
         aria-modal="true" aria-label="Quiz Question" aria-hidden="true">
        <button class="flashcard-modal-close" id="quizModalCloseBtn" type="button" aria-label="Close">×</button>

        <div class="quiz-modal-pane" id="quizUploadPane">
            <div class="quiz-modal-section-title">Upload Material</div>
            <div class="quiz-modal-help">Select a file to attach it to this quiz session.</div>
            <button class="materials-upload-btn" id="quizMaterialsUploadBtn" type="button">Upload Material</button>
            <input type="file" id="quizMaterialsInput" class="hidden"
                accept=".pdf,.doc,.docx,.ppt,.pptx,application/pdf,application/msword,
                application/vnd.openxmlformats-officedocument.wordprocessingml.document,
                application/vnd.ms-powerpoint,
                application/vnd.openxmlformats-officedocument.presentationml.presentation">
            <div class="materials-status" id="quizMaterialsStatus"></div>
            <div class="materials-list"   id="quizMaterialsList"></div>
        </div>

        <div class="quiz-modal-pane hidden" id="quizCreatePane">
            <div class="quiz-modal-section-title">Create Quiz Question</div>
            <form class="quiz-form" id="quizForm">

                <label class="quiz-label" for="quizQuestion">Question</label>
                <textarea class="quiz-textarea quiz-question"
                          id="quizQuestion"
                          maxlength="500"
                          placeholder="Type the quiz question here"
                          required></textarea>

                <div class="quiz-options-grid">
                    <div>
                        <label class="quiz-label" for="quizOptionA">Option A</label>
                        <input class="quiz-input" id="quizOptionA" type="text"
                               maxlength="400" placeholder="Answer choice A" required>
                    </div>
                    <div>
                        <label class="quiz-label" for="quizOptionB">Option B</label>
                        <input class="quiz-input" id="quizOptionB" type="text"
                               maxlength="400" placeholder="Answer choice B" required>
                    </div>
                    <div>
                        <label class="quiz-label" for="quizOptionC">Option C</label>
                        <input class="quiz-input" id="quizOptionC" type="text"
                               maxlength="400" placeholder="Answer choice C" required>
                    </div>
                    <div>
                        <label class="quiz-label" for="quizOptionD">Option D</label>
                        <input class="quiz-input" id="quizOptionD" type="text"
                               maxlength="400" placeholder="Answer choice D" required>
                    </div>
                </div>

                <div class="quiz-form-row">
                    <div>
                        <label class="quiz-label" for="quizCorrectOption">Correct Answer</label>
                        <select class="quiz-input" id="quizCorrectOption" required>
                            <option value="" selected disabled>Select correct option</option>
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>
                    <div>
                        <label class="quiz-label" for="quizExplanation">Explanation</label>
                        <textarea class="quiz-textarea quiz-explanation"
                                  id="quizExplanation"
                                  maxlength="1000"
                                  placeholder="Optional explanation for the correct answer"></textarea>
                    </div>
                </div>

                <div class="quiz-form-actions">
                    <button class="quiz-cancel-btn" id="quizCancelBtn" type="button">Cancel</button>
                    <button class="quiz-save-btn"   id="quizSaveBtn"   type="submit">Save Question</button>
                </div>

            </form>
            <div class="quiz-status" id="quizStatus" aria-live="polite"></div>
        </div>
    </div>

    <button class="back-btn" data-target="screenMenu">← Back to Menu</button>

</div>{{-- end #screenQuiz --}}