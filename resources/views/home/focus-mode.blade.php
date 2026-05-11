<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus Mode - StudyHub</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/focus-mode.css') }}?v={{ filemtime(public_path('css/focus-mode.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/flashcards.css') }}?v={{ filemtime(public_path('css/flashcards.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/quiz.css') }}?v={{ filemtime(public_path('css/quiz.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/study-materials.css') }}?v={{ filemtime(public_path('css/study-materials.css')) }}">
</head>
<body>

@php $activeNav = 'focus-mode'; @endphp
@include('layouts.sidebar')

<div class="fm-wrapper" id="focusModeApp">

    <main class="fm-main" id="mainContent">

        {{-- ══ SCREEN: MAIN MENU ══ --}}
        <div class="screen screen-menu" id="screenMenu">
            <div class="menu-intro">
                <h1 class="menu-heading">How do we study today?</h1>
                <p class="menu-sub">Choose a study mode to begin your session</p>
            </div>
            <div class="menu-buttons">
                <button class="menu-btn" data-target="screenReview">
                    <span class="menu-btn-icon">📖</span>
                    <span class="menu-btn-label">Review Material</span>
                    <span class="menu-btn-desc">Read and annotate your uploaded files</span>
                </button>
                <button class="menu-btn" data-target="screenFlashcard">
                    <span class="menu-btn-icon">🃏</span>
                    <span class="menu-btn-label">Flashcards</span>
                    <span class="menu-btn-desc">Create and study with flashcards</span>
                </button>
                <button class="menu-btn" data-target="screenQuiz">
                    <span class="menu-btn-icon">📝</span>
                    <span class="menu-btn-label">Quiz</span>
                    <span class="menu-btn-desc">Test your knowledge with a quiz</span>
                </button>
            </div>
        </div>

        {{-- ══ SCREEN: REVIEW ══ --}}
        <div class="screen screen-content hidden" id="screenReview">
            {{-- populated by study-materials.js → buildLibraryScreen() --}}
        </div>

        {{-- ══ SCREEN: FLASHCARD ══ --}}
        <div class="screen screen-content hidden" id="screenFlashcard">

            {{-- ── DECK BROWSER ── --}}
            <div id="deckBrowser">
                <h2 class="flashcard-screen-heading">Flashcards</h2>
                <p class="deck-section-label">My Decks</p>
                <div class="deck-grid" id="deckGrid">
                    <p class="deck-empty-state" id="deckEmptyState">No decks created yet.</p>
                </div>
                <button class="deck-add-btn" id="deckCreateBtn" type="button">
                    <span class="deck-add-circle" aria-hidden="true">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke="#1a1a1a" stroke-width="2.5" stroke-linecap="round"
                                  fill="none" d="M12 5v14M5 12h14"/>
                        </svg>
                    </span>
                    Add Decks
                </button>
            </div>

            {{-- Create deck modal --}}
            <div class="deck-create-backdrop hidden" id="deckModalOverlay">
                <div class="deck-create-form" id="deckCreateForm" role="dialog"
                     aria-modal="true" aria-labelledby="deckModalTitle">
                    <p class="deck-modal-title" id="deckModalTitle">Deck Name</p>
                    <input class="deck-input" id="deckNameInput" type="text"
                           maxlength="120" placeholder="Name of deck" />
                    <div class="deck-form-actions">
                        <button class="deck-save-btn"   id="deckSaveBtn"   type="button">Confirm</button>
                        <button class="deck-cancel-btn" id="deckCancelBtn" type="button">Cancel</button>
                    </div>
                    <div class="deck-status" id="deckStatus"></div>
                </div>
            </div>

            {{-- ── DECK CONTENT ── --}}
            <div class="hidden" id="deckContent">
                <div class="deck-content-header">
                    <button class="back-btn deck-back-btn" id="deckBackBtn" type="button">← Back to Decks</button>
                    <div>
                        <h2 class="deck-content-title" id="deckContentTitle">Deck Name</h2>
                        <p class="deck-content-desc" id="deckContentDesc"></p>
                    </div>
                </div>

                <div class="study-action-row flashcard-action-row">
                    <button class="menu-btn study-action-btn study-action-flashcard flashcard-action-btn" id="flashcardUploadPromptBtn" type="button">
                        <span class="menu-btn-icon">📎</span>
                        <span class="menu-btn-label">Upload Materials</span>
                        <span class="menu-btn-desc">Add the source material for this study set</span>
                    </button>
                    <button class="menu-btn study-action-btn study-action-flashcard flashcard-action-btn" id="flashcardCreatePromptBtn" type="button">
                        <span class="menu-btn-icon">🃏</span>
                        <span class="menu-btn-label">Create Flashcards</span>
                        <span class="menu-btn-desc">Build cards manually for active recall</span>
                    </button>
                </div>

                <section class="flashcard-stage" id="flashcardStage">
                    <button class="flashcard-nav flashcard-nav-left" id="flashcardPrevBtn" type="button" aria-label="Previous flashcard">‹</button>
                    <div class="flashcard-stage-viewport">
                        <div class="flashcard-stage-track" id="flashcardStageTrack"></div>
                        <div class="flashcard-stage-counter" id="flashcardStageCounter"></div>
                    </div>
                    <button class="flashcard-nav flashcard-nav-right" id="flashcardNextBtn" type="button" aria-label="Next flashcard">›</button>
                </section>
            </div>

            <button class="back-btn" data-target="screenMenu" id="flashcardScreenBackBtn">← Back to Menu</button>
        </div>

        {{-- Flashcard Studio Modal --}}
        <div class="flashcard-modal hidden" id="flashcardModal" aria-hidden="true">
            <div class="flashcard-modal-backdrop" id="flashcardModalBackdrop"></div>
            <div class="flashcard-modal-card" role="dialog" aria-modal="true" aria-labelledby="flashcardModalTitle">
                <div class="flashcard-modal-header">
                    <div>
                        <h3 class="flashcard-modal-title" id="flashcardModalTitle">Flashcard Studio</h3>
                        <p class="flashcard-modal-subtitle" id="flashcardModalSubtitle">Choose how you want to continue.</p>
                    </div>
                    <button class="flashcard-modal-close" id="flashcardModalCloseBtn" type="button" aria-label="Close flashcard studio">×</button>
                </div>

                <div class="flashcard-modal-pane" id="flashcardUploadPane">
                    <div class="flashcard-modal-section-title">Upload Material</div>
                    <div class="flashcard-modal-help">Select a file to attach it to this flashcard session.</div>
                    <button class="materials-upload-btn" id="flashcardMaterialsUploadBtn" type="button">Upload Material</button>
                    <input type="file" id="flashcardMaterialsInput" class="hidden"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,application/pdf,application/msword,
                        application/vnd.openxmlformats-officedocument.wordprocessingml.document,
                        application/vnd.ms-powerpoint,
                        application/vnd.openxmlformats-officedocument.presentationml.presentation">
                    <div class="materials-status" id="flashcardMaterialsStatus"></div>
                    <div class="materials-list" id="flashcardMaterialsList"></div>
                </div>

                <div class="flashcard-modal-pane hidden" id="flashcardCreatePane">
                    <div class="flashcard-modal-section-title">Create Flashcards</div>
                    <form class="flashcard-form" id="flashcardForm">
                        <label class="flashcard-label" for="flashcardQuestion">Question / Front</label>
                        <input class="flashcard-input" id="flashcardQuestion" type="text" maxlength="400" placeholder="Type the question here" required>
                        <label class="flashcard-label" for="flashcardAnswer">Answer / Back</label>
                        <textarea class="flashcard-textarea" id="flashcardAnswer" maxlength="1000" placeholder="Type the answer here" required></textarea>
                        <div class="flashcard-form-actions">
                            <button class="flashcard-cancel-btn" id="flashcardCancelBtn" type="button">Cancel</button>
                            <button class="flashcard-save-btn" id="flashcardSaveBtn" type="submit">Save Flashcard</button>
                        </div>
                    </form>
                    <div class="flashcard-status" id="flashcardStatus"></div>
                </div>
            </div>
        </div>

        {{-- ══ SCREEN: QUIZ ══ --}}
        <div class="screen screen-content hidden" id="screenQuiz">

            {{-- quiz-sets.js injects #quizSetsBrowser and #quizSetContent here --}}

            <div class="study-action-row quiz-action-row hidden" id="quizSetActionRow">
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

            <section class="flashcard-stage hidden" id="quizStage">
                <button class="flashcard-nav flashcard-nav-left" id="quizPrevBtn" type="button" aria-label="Previous question">‹</button>
                <div class="flashcard-stage-viewport">
                    <div class="flashcard-stage-track" id="quizStageTrack"></div>
                </div>
                <button class="flashcard-nav flashcard-nav-right" id="quizNextBtn" type="button" aria-label="Next question">›</button>
            </section>
            <div class="flashcard-stage-counter hidden" id="quizStageCounter"></div>

            {{-- Quiz question modal --}}
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
                    <div class="materials-list" id="quizMaterialsList"></div>
                </div>

                <div class="quiz-modal-pane hidden" id="quizCreatePane">
                    <div class="quiz-modal-section-title">Create Quiz Question</div>
                    <form class="quiz-form" id="quizForm">
                        <label class="quiz-label" for="quizQuestion">Question</label>
                        <textarea class="quiz-textarea quiz-question" id="quizQuestion" maxlength="500" placeholder="Type the quiz question here" required></textarea>
                        <div class="quiz-options-grid">
                            <div>
                                <label class="quiz-label" for="quizOptionA">Option A</label>
                                <input class="quiz-input" id="quizOptionA" type="text" maxlength="400" placeholder="Answer choice A" required>
                            </div>
                            <div>
                                <label class="quiz-label" for="quizOptionB">Option B</label>
                                <input class="quiz-input" id="quizOptionB" type="text" maxlength="400" placeholder="Answer choice B" required>
                            </div>
                            <div>
                                <label class="quiz-label" for="quizOptionC">Option C</label>
                                <input class="quiz-input" id="quizOptionC" type="text" maxlength="400" placeholder="Answer choice C" required>
                            </div>
                            <div>
                                <label class="quiz-label" for="quizOptionD">Option D</label>
                                <input class="quiz-input" id="quizOptionD" type="text" maxlength="400" placeholder="Answer choice D" required>
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
                                <textarea class="quiz-textarea quiz-explanation" id="quizExplanation" maxlength="1000" placeholder="Optional explanation for the correct answer"></textarea>
                            </div>
                        </div>
                        <div class="quiz-form-actions">
                            <button class="quiz-cancel-btn" id="quizCancelBtn" type="button">Cancel</button>
                            <button class="quiz-save-btn"   id="quizSaveBtn"   type="submit">Save Question</button>
                        </div>
                    </form>
                    <div class="quiz-status" id="quizStatus"></div>
                </div>
            </div>

           <button class="back-btn" id="quizScreenMenuBackBtn" data-target="screenMenu">← Back to Menu</button>

        </div>

    </main>

    {{-- ══ MUSIC FAB ══ --}}
    <button class="music-toggle-fab hidden" id="musicToggleBtn"
        title="Toggle Focus Music" aria-label="Toggle Focus Music">
    <svg viewBox="0 0 24 24" id="musicNoteIcon">
        <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
    </svg>
    <span class="music-note music-note-1" aria-hidden="true">♪</span>
    <span class="music-note music-note-2" aria-hidden="true">♫</span>
    <span class="music-note music-note-3" aria-hidden="true">♩</span>
</button>

    {{-- ══ MUSIC PLAYER WIDGET (on/off only) ══ --}}
    <div class="music-player-widget hidden" id="musicWidget">

        {{-- Close button --}}
        <button class="music-hide-btn" id="musicHideBtn"
                title="Hide Music" aria-label="Hide Music">×</button>

        {{-- Album art placeholder --}}
        <div class="music-album-art">
            <div class="music-album-placeholder" aria-hidden="true">🎵</div>
        </div>

        {{-- Track info --}}
        <div class="music-status">Focus Music</div>
        <div class="music-sub-status">Playing in background</div>

        {{-- Progress bar (driven by JS timeupdate, no CSS animation) --}}
        <div class="music-progress">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>

        {{-- Controls: play/pause only --}}
        <div class="music-controls">
            <button class="ctrl-btn ctrl-play" id="playPauseBtn" title="Play / Pause" aria-label="Play or pause focus music">
                {{-- Play icon (shown when paused) --}}
                <svg viewBox="0 0 24 24" id="playIcon" class="hidden" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
                {{-- Pause icon (shown when playing) --}}
                <svg viewBox="0 0 24 24" id="pauseIcon" aria-hidden="true">
                    <rect x="6"  y="4" width="4" height="16"/>
                    <rect x="14" y="4" width="4" height="16"/>
                </svg>
            </button>
        </div>

    </div>

    {{-- ══ FOCUS FOOTER ══ --}}
    <footer class="sh-footer" id="focusFooter">
        <span class="focus-label" id="focusModeLabel">Focus Mode : ON</span>
    </footer>

    {{-- ══ FOCUS FAB ══ --}}
    <button class="focus-fab" id="focusToggleBtn"
            title="Toggle Focus Mode" aria-pressed="false">
        <svg class="lock-icon lock-open" viewBox="0 0 24 24" id="lockOpen">
            <path d="M12 1C9.24 1 7 3.24 7 6v1H5c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2h-2V6c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v1H9V6c0-1.66 1.34-3 3-3zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
        </svg>
        <svg class="lock-icon lock-closed hidden" viewBox="0 0 24 24" id="lockClosed">
            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
        </svg>
    </button>

</div>

{{-- Hidden file input for materials panel --}}
<input type="file" id="materialsInput" class="hidden"
    accept=".pdf,.doc,.docx,.ppt,.pptx,application/pdf,application/msword,
    application/vnd.openxmlformats-officedocument.wordprocessingml.document,
    application/vnd.ms-powerpoint,
    application/vnd.openxmlformats-officedocument.presentationml.presentation">

<script>
    window.__focusMaterials = @json($materials ?? []);
    window.__focusDecks     = @json($decks    ?? []);
    window.__focusQuizzes   = @json($quizzes  ?? []);
    window.__focusQuizSets  = @json($quizSets ?? []);
</script>
<script src="{{ asset('js/focus-mode.js') }}"></script>
<script src="{{ asset('js/flashcards-decks.js') }}"></script>
<script src="{{ asset('js/quiz.js') }}"></script>
<script src="{{ asset('js/study-materials.js') }}"></script>

</body>
</html>