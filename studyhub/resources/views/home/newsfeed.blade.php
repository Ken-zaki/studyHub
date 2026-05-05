 <!DOCTYPE html>
 <html lang="en">

 <head>
     @include('layouts.theme-init')
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Newsfeed - StudyHub</title>
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link
         href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
         rel="stylesheet">
     <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
     <link rel="stylesheet" href="{{ asset('css/newsfeed.css') }}">
 </head>

 <body>

     @php $activeNav = 'newsfeed'; @endphp
     @include('layouts.sidebar')

     <main class="main-content">

         <!-- ══ CENTER COLUMN ══ -->
         <div class="feed-column" id="feedColumn">
             <header class="page-header">
                 <h1 class="page-title">Newsfeed</h1>
                 <p class="page-subtitle">Stay updated with your community</p>
             </header>

             <!-- ── CREATE POST BOX ── -->
             <div class="create-post" id="createPostBox">
                 <div class="cp-top">
                     <div class="cp-avatar" id="cpAvatar"></div>
                     <div class="cp-input-wrap" onclick="expandComposer()">
                         <span class="cp-placeholder" id="cpPlaceholder">What's on your mind?</span>
                     </div>
                 </div>

                 <!-- Expanded composer (hidden by default) -->
                 <div class="cp-expanded" id="cpExpanded" style="display:none;">
                     <textarea id="postContent" class="cp-textarea" placeholder="What's on your mind? (caption is optional)"
                         oninput="autoResizeCp(this)"></textarea>

                     <!-- Media preview grid -->
                     <div class="cp-media-preview" id="cpMediaPreview"></div>

                     <!-- Link preview -->
                     <div class="cp-link-preview" id="cpLinkPreview" style="display:none;"></div>

                     <div class="cp-footer">
                         <!-- Attach buttons -->
                         <div class="cp-attach-row">
                             <button class="cp-attach-btn" title="Add photos/videos"
                                 onclick="document.getElementById('cpMediaInput').click()">
                                 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <rect x="3" y="3" width="18" height="18" rx="3" />
                                     <circle cx="8.5" cy="8.5" r="1.5" />
                                     <polyline points="21 15 16 10 5 21" />
                                 </svg>
                                 Photo/Video
                             </button>
                             <button class="cp-attach-btn" title="Attach file"
                                 onclick="document.getElementById('cpFileInput').click()">
                                 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path
                                         d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
                                 </svg>
                                 File
                             </button>
                             <button class="cp-attach-btn" title="Add a link" onclick="toggleLinkInput()">
                                 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                     <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
                                     <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
                                 </svg>
                                 Link
                             </button>
                         </div>

                         <!-- Link input (toggleable) -->
                         <div class="cp-link-row" id="cpLinkRow" style="display:none;">
                             <input type="url" id="cpLinkInput" class="cp-link-input"
                                 placeholder="Paste a URL and press Enter…"
                                 onkeydown="if(event.key==='Enter'){fetchLinkPreview(this.value);this.closest('.cp-link-row').style.display='none';}">
                         </div>

                         <!-- Visibility + Post -->
                         <div class="cp-actions-row">
                             <div class="cp-vis-wrap">
                                 <button class="cp-vis-btn" id="cpVisBtn" onclick="toggleVisMenu()">
                                     <span id="cpVisIcon">🌐</span>
                                     <span id="cpVisLabel">Public</span>
                                     <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                         style="width:12px;height:12px;">
                                         <polyline points="6 9 12 15 18 9" />
                                     </svg>
                                 </button>
                                 <div class="cp-vis-menu" id="cpVisMenu" style="display:none;">
                                     <button class="cp-vis-option" onclick="setVisibility('public','🌐','Public')">
                                         <span>🌐</span>
                                         <div><strong>Public</strong><small>Everyone on StudyHub</small></div>
                                     </button>
                                     <button class="cp-vis-option" onclick="setVisibility('friends','👥','Friends')">
                                         <span>👥</span>
                                         <div><strong>Friends</strong><small>Your friends only</small></div>
                                     </button>
                                     <button class="cp-vis-option" onclick="setVisibility('only_me','🔒','Only Me')">
                                         <span>🔒</span>
                                         <div><strong>Only Me</strong><small>Just you</small></div>
                                     </button>
                                 </div>
                             </div>
                             <div style="display:flex;gap:8px;">
                                 <button class="btn-secondary" onclick="cancelComposer()">Cancel</button>
                                 <button class="btn-primary" onclick="createPost()" id="postButton">Post</button>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Hidden file inputs -->
                 <input type="file" id="cpMediaInput" multiple accept="image/*,video/*" style="display:none;"
                     onchange="handleMediaFiles(this.files)">
                 <input type="file" id="cpFileInput" multiple style="display:none;"
                     onchange="handleAttachFiles(this.files)">
             </div>

             <!-- ── FEED TABS (For You / Following / Friends) ── -->
             <div class="feed-tabs">
                 <button class="feed-tab active" id="tabForYou" onclick="switchTab('for_you')">✨ For You</button>
                 <button class="feed-tab" id="tabFollowing" onclick="switchTab('following')">👁 Following</button>
                 <button class="feed-tab" id="tabFriends" onclick="switchTab('friends')">👥 Friends</button>
             </div>

             <!-- ── POST FEED ── -->
             <div id="feed" class="feed">
                 <div class="loading-state">Loading posts…</div>
             </div>
         </div>

         <!-- ══ RIGHT SIDEBAR ══ -->
         <aside class="right-sidebar">

             <!-- Who to follow -->
             <div class="widget-card">
                 <div class="widget-title">👤 Who to Follow</div>
                 <div id="whoToFollow">
                     <div class="res-empty-small">Loading…</div>
                 </div>
             </div>

             <!-- Trending -->
             <div class="widget-card">
                 <div class="widget-title">🔥 Trending Topics</div>
                 <div id="trendingList"></div>
             </div>

             <!-- Quick links -->
             <div class="widget-card">
                 <div class="widget-title">⚡ Quick Links</div>
                 <a href="{{ route('calendar') }}" class="quick-link"><span class="quick-link-icon">📅</span>
                     Calendar</a>
                 <a href="{{ route('study-groups') }}" class="quick-link"><span class="quick-link-icon">👥</span>
                     Study Groups</a>
                 <a href="{{ route('resources') }}" class="quick-link"><span class="quick-link-icon">📚</span>
                     Resources</a>
                 <a href="{{ route('notifications') }}" class="quick-link"><span class="quick-link-icon">🔔</span>
                     Notifications</a>
                 <a href="{{ route('messages') }}" class="quick-link"><span class="quick-link-icon">💬</span>
                     Messages</a>
                 <a href="{{ route('settings') }}" class="quick-link"><span class="quick-link-icon">⚙️</span>
                     Settings</a>
             </div>

             <!-- Study tip -->
             <div class="widget-card">
                 <div class="widget-title">💡 Study Tip</div>
                 <p id="studyTip" style="font-size:14px;color:var(--text-secondary);line-height:1.6;"></p>
             </div>
         </aside>
     </main>

     <!-- ══════════════════════════════════════════
     EDIT POST MODAL
══════════════════════════════════════════ -->
     <div class="modal-overlay" id="editModal">
         <div class="modal" style="max-width:560px;">
             <div class="modal-header">
                 <span class="modal-title">Edit Post</span>
                 <button class="modal-close" onclick="closeEditModal()">✕</button>
             </div>
             <div class="edit-vis-row">
                 <label style="font-size:13px;font-weight:600;color:var(--text-secondary);">Visibility</label>
                 <select id="editVisSelect" class="edit-vis-select">
                     <option value="public">🌐 Public</option>
                     <option value="friends">👥 Friends</option>
                     <option value="only_me">🔒 Only Me</option>
                 </select>
             </div>
             <textarea class="modal-textarea" id="editContent" placeholder="Edit your post…"></textarea>
             <div class="modal-actions">
                 <button class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                 <button class="btn-primary" onclick="saveEdit()">Save Changes</button>
             </div>
         </div>
     </div>

     <!-- ══════════════════════════════════════════
     REPORT POST MODAL
══════════════════════════════════════════ -->
     <div class="modal-overlay" id="reportModal">
         <div class="modal" style="max-width:440px;">
             <div class="modal-header">
                 <span class="modal-title">Report Post</span>
                 <button class="modal-close" onclick="closeReportModal()">✕</button>
             </div>
             <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
                 Help us keep StudyHub safe. Tell us what's wrong with this post.
             </p>
             <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
                 <label class="res-report-option"><input type="radio" name="postReportReason"
                         value="Inappropriate content"> Inappropriate content</label>
                 <label class="res-report-option"><input type="radio" name="postReportReason"
                         value="Spam or misleading"> Spam or misleading</label>
                 <label class="res-report-option"><input type="radio" name="postReportReason"
                         value="Harassment or bullying"> Harassment or bullying</label>
                 <label class="res-report-option"><input type="radio" name="postReportReason" value="Hate speech">
                     Hate speech</label>
                 <label class="res-report-option"><input type="radio" name="postReportReason"
                         value="Copyright violation"> Copyright violation</label>
                 <label class="res-report-option"><input type="radio" name="postReportReason" value="Other">
                     Other
                     (describe below)</label>
             </div>
             <textarea id="reportDetails" class="res-input" rows="3" style="resize:vertical;"
                 placeholder="Additional details (optional)…"></textarea>
             <div class="modal-actions" style="margin-top:16px;">
                 <button class="btn-secondary" onclick="closeReportModal()">Cancel</button>
                 <button class="btn-primary" style="background:var(--accent);border-color:var(--accent);"
                     onclick="submitPostReport()">Submit Report</button>
             </div>
         </div>
     </div>

     <!-- ══════════════════════════════════════════
     COMMENTS DRAWER (slides up)
══════════════════════════════════════════ -->
     <div class="comments-overlay" id="commentsOverlay" onclick="closeComments(event)">
         <div class="comments-drawer" id="commentsDrawer">
             <div class="comments-drawer-header">
                 <span class="comments-drawer-title">Comments</span>
                 <button class="modal-close" onclick="closeComments(null,true)">✕</button>
             </div>
             <div class="comments-list" id="commentsList">
                 <div class="res-loading-sm">Loading comments…</div>
             </div>
             <div class="comments-input-bar">
                 <div class="comments-input-avatar" id="commentsAvatar"></div>
                 <div class="comments-input-wrap">
                     <textarea id="commentInput" class="comments-textarea" placeholder="Write a comment…" oninput="autoResizeCp(this)"
                         onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();submitComment();}"></textarea>
                     <button class="comments-send-btn" onclick="submitComment()">
                         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                             <line x1="22" y1="2" x2="11" y2="13" />
                             <polygon points="22 2 15 22 11 13 2 9 22 2" />
                         </svg>
                     </button>
                 </div>
             </div>
         </div>
     </div>

     <!-- ══════════════════════════════════════════
     SHARE MODAL
══════════════════════════════════════════ -->
     <div class="modal-overlay" id="shareModal">
         <div class="modal" style="max-width:420px;">
             <div class="modal-header">
                 <span class="modal-title">Share Post</span>
                 <button class="modal-close" onclick="closeShareModal()">✕</button>
             </div>
             <div class="share-options">
                 <button class="share-opt-btn" onclick="shareToFeed()">
                     <span>📢</span><span>Share to your feed</span>
                 </button>
                 <button class="share-opt-btn" onclick="copyPostLink()">
                     <span>🔗</span><span>Copy link</span>
                 </button>
             </div>
             <p id="shareConfirmMsg"
                 style="font-size:13px;color:#15803d;text-align:center;min-height:20px;margin-top:8px;"></p>
         </div>
     </div>

     <!-- ══ CONFIG ══ -->
     <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
     <script>
         const SUPABASE_URL = '{{ env('SUPABASE_URL') }}';
         const SUPABASE_ANON_KEY = '{{ env('SUPABASE_ANON_KEY') }}';
         const SUPABASE_SERVICE_KEY = '{{ env('SUPABASE_SERVICE_KEY') }}';
         const currentUser = {
             id: '{{ session('user_id') }}',
             first_name: '{{ session('user_first_name') }}',
             last_name: '{{ session('user_last_name') }}',
             username: '{{ session('user_username') }}',
             profile_photo_url: '{{ session('user_profile_photo') }}'
         };
     </script>
     <script src="{{ asset('js/newsfeed.js') }}"></script>
 </body>

 </html>
