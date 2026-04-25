@extends('layouts.app')
 
@section('title', 'StudyHub - Focus Mode')
 
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/focus-mode.css') }}">
@endpush
 
@section('content')
<div class="studyhub-wrapper" id="focusModeApp">
 
    {{-- Header --}}
    <header class="sh-header">
        <button class="hamburger-btn" id="sidebarToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <div class="sh-logo">
            <span class="logo-study">Study</span><span class="logo-hub">Hub</span>
        </div>
    </header>
 
    {{-- Sidebar --}}
    <aside class="sh-sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">S</div>
                <span class="logo-text">StudyHub</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'sidebar-link active' : 'sidebar-link' }}">Newsfeed</a>
            <a href="{{ route('calendar') }}" class="{{ request()->routeIs('calendar') ? 'sidebar-link active' : 'sidebar-link' }}">Calendar</a>
            <a href="{{ route('study-groups') }}" class="{{ request()->routeIs('study-groups') ? 'sidebar-link active' : 'sidebar-link' }}">Study Groups</a>
            <a href="{{ route('resources') }}" class="{{ request()->routeIs('resources') ? 'sidebar-link active' : 'sidebar-link' }}">Resources</a>
            <a href="{{ route('notifications') }}" class="{{ request()->routeIs('notifications') ? 'sidebar-link active' : 'sidebar-link' }}">Notifications</a>
            <a href="{{ route('messages') }}" class="{{ request()->routeIs('messages') ? 'sidebar-link active' : 'sidebar-link' }}">Messages</a>
            <a href="{{ route('focus-mode') }}" class="{{ request()->routeIs('focus-mode') ? 'sidebar-link active' : 'sidebar-link' }}">Focus Mode</a>
            <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'sidebar-link active' : 'sidebar-link' }}">Profile</a>
            <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? 'sidebar-link active' : 'sidebar-link' }}">Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="{{ route('profile') }}" class="user-profile" id="focusSidebarProfile">
                <div class="user-avatar" id="sidebarAvatar">{{ strtoupper(substr(session('user_first_name', 'U'), 0, 1) . substr(session('user_last_name', 'U'), 0, 1)) }}</div>
                <div class="user-info">
                    <div class="user-name" id="sidebarUserName">{{ trim((session('user_first_name') ?? '') . ' ' . (session('user_last_name') ?? '')) ?: (session('user_username') ?? 'You') }}</div>
                    <div class="user-status">Online</div>
                </div>
            </a>
        </div>
    </aside>
 
    {{-- Main content area --}}
    <main class="sh-main" id="mainContent">

        <section class="materials-panel hidden" id="materialsPanel">
            <div class="materials-panel-header">
                <div>
                    <div class="materials-eyebrow">Study Materials</div>
                    <h2 class="materials-title">Upload PDF, Word, or PowerPoint files</h2>
                </div>
                <button class="materials-upload-btn" id="materialsUploadBtn" type="button">Upload Material</button>
                <input type="file" id="materialsInput" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation">
            </div>
            <p class="materials-help" id="materialsHelp">Attach materials to the study mode you are currently using.</p>
            <div class="materials-status" id="materialsStatus"></div>
            <div class="materials-list" id="materialsList"></div>
        </section>
 
        {{-- === SCREEN: MAIN MENU === --}}
        <div class="screen screen-menu" id="screenMenu">
            <div class="menu-buttons">
                <button class="menu-btn" data-target="screenReview">
                    Review material
                </button>
                <button class="menu-btn" data-target="screenFlashcard">
                    FLASHCARD
                </button>
                <button class="menu-btn" data-target="screenQuiz">
                    Quiz
                </button>
            </div>
        </div>
 
        {{-- === SCREEN: REVIEW MATERIAL === --}}
        <div class="screen screen-content hidden" id="screenReview">
            <div class="content-area" id="reviewContent">
                <p class="placeholder-text">Review material / Content</p>
            </div>
            <button class="back-btn" data-target="screenMenu">← Back</button>
        </div>
 
        {{-- === SCREEN: FLASHCARD === --}}
        <div class="screen screen-content hidden" id="screenFlashcard">
            <div class="content-area flashcard-area" id="flashcardContent">
                <div class="flashcard-builder" id="flashcardBuilder">
                    <div class="flashcard-builder-header">
                        <h3 class="flashcard-builder-title">Manual Flashcard Creator</h3>
                        <button class="flashcard-create-btn" id="flashcardCreateBtn" type="button">Create Flashcard</button>
                    </div>

                    <form class="flashcard-form hidden" id="flashcardForm">
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
                    <div class="flashcard-list" id="flashcardList"></div>
                </div>
            </div>
            <button class="back-btn" data-target="screenMenu">← Back</button>
        </div>
 
        {{-- === SCREEN: QUIZ === --}}
        <div class="screen screen-content hidden" id="screenQuiz">
            <div class="content-area" id="quizContent">
                <p class="placeholder-text">Quiz / Content</p>
            </div>
            <button class="back-btn" data-target="screenMenu">← Back</button>
        </div>
 
    </main>

    {{-- Music Launcher + Player (available in every study mode) --}}
    <button class="music-toggle-fab hidden" id="musicToggleBtn" title="Open Music" aria-label="Open Music">
        <span class="music-note music-note-1" aria-hidden="true">♪</span>
        <span class="music-note music-note-2" aria-hidden="true">♫</span>
        <span class="music-note music-note-3" aria-hidden="true">♬</span>
        <svg viewBox="0 0 24 24" id="musicNoteIcon">
            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
        </svg>
    </button>

    <div class="music-player-widget hidden" id="musicWidget">
        <button class="music-hide-btn" id="musicHideBtn" title="Hide Music" aria-label="Hide Music">×</button>
        <div class="music-album-art">
           <img src="{{ asset('images/album-placeholder.png') }}" alt="Album Art" id="albumArt">
        </div>
        <div class="music-status">MUSIC PLAYING...</div>
        <div class="music-progress">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
        <div class="music-controls">
            <button class="ctrl-btn" id="shuffleBtn" title="Shuffle">
                <svg viewBox="0 0 24 24"><path d="M16 3h5v5l-1.5-1.5-4.5 4.5-1.5-1.5 4.5-4.5L16 3zm-7 1L3 10l2 2 6-6-2-2zm2 10l-4 4 1.5 1.5L12 16l3.5 3.5L17 18l-4-4zm6.5-1.5L16 10l-2 2 4.5 4.5L17 18h5v-5l-1.5 1.5z"/></svg>
            </button>
            <button class="ctrl-btn" id="prevBtn" title="Previous">
                <svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>
            </button>
            <button class="ctrl-btn ctrl-play" id="playPauseBtn" title="Pause">
                <svg viewBox="0 0 24 24" id="playIcon" class="hidden"><path d="M8 5v14l11-7z"/></svg>
                <svg viewBox="0 0 24 24" id="pauseIcon"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            </button>
            <button class="ctrl-btn" id="nextBtn" title="Next">
                <svg viewBox="0 0 24 24"><path d="m6 18 8.5-6L6 6v12zm2.5-6L6 6v12l8.5-6zM16 6v12h2V6z"/></svg>
            </button>
            <button class="ctrl-btn" id="likeBtn" title="Like">
                <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </button>
        </div>
    </div>
 
    {{-- Footer bar (visible when Focus Mode is ON) --}}
    <footer class="sh-footer" id="focusFooter">
        <span class="focus-label" id="focusModeLabel">Focus Mode : ON</span>
    </footer>
 
    {{-- Focus Mode FAB Lock Button --}}
    <button class="focus-fab" id="focusToggleBtn" title="Toggle Focus Mode" aria-pressed="false">
        <svg class="lock-icon lock-open" viewBox="0 0 24 24" id="lockOpen">
            <path d="M12 1C9.24 1 7 3.24 7 6v1H5c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2h-2V6c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v1H9V6c0-1.66 1.34-3 3-3zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
        </svg>
        <svg class="lock-icon lock-closed hidden" viewBox="0 0 24 24" id="lockClosed">
            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
        </svg>
    </button>
 
</div>
@endsection
 
@push('scripts')
    <script>
        window.__focusMaterials = @json($materials ?? []);
        window.__focusFlashcards = @json($flashcards ?? []);
    </script>
    <script src="{{ asset('js/focus-mode.js') }}"></script>
@endpush
