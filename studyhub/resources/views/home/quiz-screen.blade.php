{{--
    ══════════════════════════════════════════════════════════════
    PARTIAL: quiz-screen.blade.php
    ══════════════════════════════════════════════════════════════
    Drop-in replacement for the  #screenQuiz block  and the
    Quiz Modal inside focus-mode.blade.php.

    WHAT CHANGED vs the original blade:
    ─────────────────────────────────────────────────────────────
    1.  The quiz screen now has a QUIZ SET BROWSER layer wrapping
        the existing quiz question slider — exactly mirroring how
        the flashcard screen has a deck browser layer.

    2.  New IDs introduced (all unique, no clashes with existing):
          #quizSetBrowser         — the set list view
          #quizSetGrid            — where JS renders set cards
          #quizSetCreateBtn       — "+ Add quizzes" button
          #quizSetModalOverlay    — the floating name panel
          #quizSetTitleInput      — title input inside the modal
          #quizSetSaveBtn         — Confirm pill button
          #quizSetCancelBtn       — Cancel pill button
          #quizSetStatus          — status/error message
          #quizSetContent         — the content area after opening a set
          #quizSetBackBtn         — "← Back to Quiz Sets" button
          #quizSetContentTitle    — set title heading
          #quizSetContentDesc     — set subtitle / description
          #quizScreenBackBtn      — "← Back to Menu" (renamed from the
                                    old un-id'd back-btn for JS targeting)

    3.  The existing quiz action buttons (Upload / Create Quiz) and
        the quiz question slider are preserved unchanged inside
        #quizSetContent, shown only after a set is opened.

    4.  The Quiz Modal (quizModal, quizForm, etc.) is unchanged.

    HOW TO USE:
    ─────────────────────────────────────────────────────────────
    In focus-mode.blade.php:

    A) Replace everything between these two markers with this file:

         {{-- ══ SCREEN: QUIZ ══ --}}
         <div class="screen screen-content hidden" id="screenQuiz">
             …
         </div>
         {{-- Quiz Modal --}}
         <div class="quiz-modal hidden" id="quizModal" …>
             …
         </div>

    B) In <head>, add after flashcards.css:
         <link rel="stylesheet" href="{{ asset('css/quiz-sets.css') }}">

    C) In the data-bootstrap <script> block, add:
         window.__focusQuizSets = @json($quizSets ?? []);

    D) After <script src="flashcards-decks.js"></script>, add:
         <script src="{{ asset('js/quiz-sets.js') }}"></script>
    ══════════════════════════════════════════════════════════════
--}}

{{-- ══ SCREEN: QUIZ ══ --}}
<div class="screen screen-content hidden" id="screenQuiz">

    {{-- ── QUIZ SET BROWSER (shown when no set is selected) ── --}}
    <div id="quizSetBrowser">

        {{-- "QUIZ" page heading ─ matches mockup screenshot --}}
        <h2 class="quiz-screen-heading">Quiz</h2>

        {{-- "My Quizzes" section label --}}
        <p class="quiz-set-section-label">My Quizzes</p>

        {{--
            Quiz set grid — quiz-sets.js populates .quiz-set-card elements here.
            The empty-state div is shown when no sets exist.
        --}}
        <div class="quiz-set-grid" id="quizSetGrid">
            <div class="quiz-set-empty-state">No quizzes created yet.</div>
        </div>

        {{-- "+ Add quizzes" button — teal circle + label --}}
        <button class="quiz-set-add-btn" id="quizSetCreateBtn" type="button">
            <span class="quiz-set-add-circle" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke="#1a1a1a" stroke-width="2.5" stroke-linecap="round"
                          fill="none" d="M12 5v14M5 12h14"/>
                </svg>
            </span>
            Add quizzes
        </button>

    </div>

    {{--
        ── Quiz Set Name Modal ──
        ID: quizSetModalOverlay
        Starts hidden; quiz-sets.js removes .hidden and adds .open.
        position:fixed — floats above the whole page.
    --}}
    <div id="quizSetModalOverlay" class="hidden" aria-hidden="true">
        <div class="quiz-set-create-form"
             role="dialog"
             aria-modal="true"
             aria-labelledby="quizSetModalTitle">

            {{-- "QUIZZES" muted-pink label — matches floating panel mockup --}}
            <p class="quiz-set-modal-title" id="quizSetModalTitle">Quizzes</p>

            {{-- Title input ─ placeholder "Quiz title" --}}
            <input class="quiz-set-input"
                   id="quizSetTitleInput"
                   type="text"
                   maxlength="120"
                   placeholder="Quiz title"
                   autocomplete="off" />

            {{-- Confirm (orange) + Cancel (teal) pill buttons --}}
            <div class="quiz-set-form-actions">
                <button class="quiz-set-save-btn"   id="quizSetSaveBtn"   type="button">Confirm</button>
                <button class="quiz-set-cancel-btn" id="quizSetCancelBtn" type="button">Cancel</button>
            </div>

            {{-- Status / error message --}}
            <div class="quiz-set-status" id="quizSetStatus" aria-live="polite"></div>

        </div>
    </div>

    {{-- ── QUIZ SET CONTENT (shown after a quiz set card is clicked) ── --}}
    <div class="hidden" id="quizSetContent">

        <div class="quiz-set-content-header">
            {{-- "← Back to Quiz Sets" button --}}
            <button class="back-btn quiz-set-back-btn" id="quizSetBackBtn" type="button">
                ← Back to Quiz Sets
            </button>
            <div>
                <h2 class="quiz-set-content-title" id="quizSetContentTitle">Quiz Set</h2>
                <p class="quiz-set-content-desc"   id="quizSetContentDesc"></p>
            </div>
        </div>

        {{-- Action buttons — Upload Materials + Create Quiz (preserved exactly) --}}
        <div class="study-action-row quiz-action-row">
            <button class="menu-btn study-action-btn study-action-quiz quiz-action-btn"
                    id="quizUploadPromptBtn" type="button">
                <span class="menu-btn-icon">📎</span>
                <span class="menu-btn-label">Upload Materials</span>
                <span class="menu-btn-desc">Add the source material for this quiz</span>
            </button>
            <button class="menu-btn study-action-btn study-action-quiz quiz-action-btn"
                    id="quizCreatePromptBtn" type="button">
                <span class="menu-btn-icon">📝</span>
                <span class="menu-btn-label">Create Quiz</span>
                <span class="menu-btn-desc">Build questions manually</span>
            </button>
        </div>

        {{-- Quiz question slider (preserved exactly) --}}
        <section class="quiz-stage" id="quizStage">
            <button class="quiz-nav quiz-nav-left"
                    id="quizPrevBtn" type="button"
                    aria-label="Previous question">‹</button>
            <div class="quiz-stage-viewport">
                <div class="quiz-stage-track"   id="quizStageTrack"></div>
                <div class="quiz-stage-counter" id="quizStageCounter"></div>
            </div>
            <button class="quiz-nav quiz-nav-right"
                    id="quizNextBtn" type="button"
                    aria-label="Next question">›</button>
        </section>

    </div>

    {{-- "← Back to Menu" button — visible on set browser, hidden inside a set --}}
    <button class="back-btn" data-target="screenMenu" id="quizScreenBackBtn">
        ← Back to Menu
    </button>

</div>{{-- end #screenQuiz --}}


{{-- ══ QUIZ STUDIO MODAL (unchanged from original) ══ --}}
{{--
    Opened when the user clicks "Upload Materials" or "Create Quiz"
    inside a quiz set. focus-mode.js handles it via el.quizModal, etc.
--}}
<div class="quiz-modal hidden" id="quizModal" aria-hidden="true">
    <div class="quiz-modal-backdrop" id="quizModalBackdrop"></div>
    <div class="quiz-modal-card"
         role="dialog"
         aria-modal="true"
         aria-labelledby="quizModalTitle">

        <div class="quiz-modal-header">
            <div>
                <h3 class="quiz-modal-title"    id="quizModalTitle">Quiz Studio</h3>
                <p  class="quiz-modal-subtitle" id="quizModalSubtitle">Choose how you want to continue.</p>
            </div>
            <button class="quiz-modal-close" id="quizModalCloseBtn"
                    type="button" aria-label="Close quiz studio">×</button>
        </div>

        {{-- Upload pane (preserved) --}}
        <div class="quiz-modal-pane" id="quizUploadPane">
            <div class="quiz-modal-section-title">Upload Material</div>
            <div class="quiz-modal-help">Select a file to attach it to this quiz session.</div>
            <button class="materials-upload-btn" id="quizMaterialsUploadBtn" type="button">
                Upload Material
            </button>
            <input type="file" id="quizMaterialsInput" class="hidden"
                accept=".pdf,.doc,.docx,.ppt,.pptx,
                        application/pdf,
                        application/msword,
                        application/vnd.openxmlformats-officedocument.wordprocessingml.document,
                        application/vnd.ms-powerpoint,
                        application/vnd.openxmlformats-officedocument.presentationml.presentation">
            <div class="materials-status" id="quizMaterialsStatus"></div>
            <div class="materials-list"   id="quizMaterialsList"></div>
        </div>

        {{-- Create pane (preserved) --}}
        <div class="quiz-modal-pane hidden" id="quizCreatePane">
            <div class="quiz-modal-section-title">Create Quiz Questions</div>
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
                    <button class="quiz-save-btn"   id="quizSaveBtn"   type="submit">Save Quiz Question</button>
                </div>

            </form>
            <div class="quiz-status" id="quizStatus" aria-live="polite"></div>
        </div>

    </div>
</div>{{-- end #quizModal --}}
