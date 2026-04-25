<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsfeed - StudyHub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">

    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>


@php $activeNav = 'newsfeed'; @endphp
@include('layouts.sidebar')


<main class="main-content">


    <div class="feed-column" id="feedColumn">
        <header class="page-header">
            <h1 class="page-title">Newsfeed</h1>
            <p class="page-subtitle">Stay updated with the latest news, updates &amp; events</p>
        </header>


        <div class="create-post">
            <div class="create-post-header">
                <div class="create-post-avatar" id="createPostAvatar"><!-- filled by JS --></div>
                <div style="flex:1;">
                    <textarea id="postContent" placeholder="What's on your mind?"></textarea>
                    <div id="attachmentPreview" class="attachment-preview"></div>
                </div>
            </div>
            <div class="post-actions">
                <div class="attachment-buttons">
                    <button class="attach-btn" onclick="attachImage()">📷 Image</button>
                    <button class="attach-btn" onclick="attachFile()">📎 File</button>
                    <button class="attach-btn" onclick="attachLink()">🔗 Link</button>
                    <button class="attach-btn" onclick="attachVideo()">🎥 Video</button>
                </div>
                <button class="btn-primary" onclick="createPost()" id="postButton">Post</button>
            </div>
            <input type="file" id="imageInput" accept="image/*">
            <input type="file" id="fileInput">
            <input type="file" id="videoInput" accept="video/*">
        </div>


        <div id="feed" class="feed">
            <div class="loading-state">Loading posts…</div>
        </div>
    </div>


    <aside class="right-sidebar">
        <div class="widget-card">
            <div class="widget-title">🔥 Trending Topics</div>
            <div id="trendingList"></div>
        </div>

        <div class="widget-card">
            <div class="widget-title">⚡ Quick Links</div>
            <a href="{{ route('calendar') }}"      class="quick-link"><span class="quick-link-icon">📅</span> Calendar</a>
            <a href="{{ route('study-groups') }}"  class="quick-link"><span class="quick-link-icon">👥</span> Study Groups</a>
            <a href="{{ route('resources') }}"     class="quick-link"><span class="quick-link-icon">📚</span> Resources</a>
            <a href="{{ route('notifications') }}" class="quick-link"><span class="quick-link-icon">🔔</span> Notifications</a>
            <a href="{{ route('messages') }}"      class="quick-link"><span class="quick-link-icon">💬</span> Messages</a>
            <a href="{{ route('settings') }}"      class="quick-link"><span class="quick-link-icon">⚙️</span> Settings</a>
        </div>

        <div class="widget-card">
            <div class="widget-title">💡 Study Tip</div>
            <p id="studyTip" style="font-size:14px; color:var(--text-secondary); line-height:1.6;"></p>
        </div>
    </aside>

</main>


<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Post</span>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <textarea class="modal-textarea" id="editContent" placeholder="Edit your post…"></textarea>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeEditModal()">Cancel</button>
            <button class="btn-primary"   onclick="saveEdit()">Save Changes</button>
        </div>
    </div>
</div>


<script>
    const SUPABASE_URL         = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY    = '{{ env("SUPABASE_ANON_KEY") }}';
    const SUPABASE_SERVICE_KEY = '{{ env("SUPABASE_SERVICE_KEY") }}';

    const currentUser = {
        id:                '{{ session("user_id") }}',
        first_name:        '{{ session("user_first_name") }}',
        last_name:         '{{ session("user_last_name") }}',
        username:          '{{ session("user_username") }}',
        profile_photo_url: '{{ session("user_profile_photo") }}'
    };
</script>

<script src="{{ asset('js/dashboard.js') }}"></script>

</body>
</html>
