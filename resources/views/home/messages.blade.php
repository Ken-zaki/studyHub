<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Messages - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- External CSS may override message styles -->
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/focus-mode.css') }}">
   
</head>

<body>

    @include('layouts.sidebar')

    <main class="main-content-simple">
        <div class="messages-layout">

            {{-- ── Friends Panel ── --}}
            <aside class="friends-panel">
                <div class="friends-panel-header">
                    <h2>Messages</h2>
                    <div class="friends-actions-row">
                        <div class="search-box">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.35-4.35" />
                            </svg>
                            <input type="text" id="friendSearch" placeholder="Search friends…">
                        </div>

                        <div class="header-menu-wrap">
                            <button class="header-menu-btn" onclick="toggleHeaderMenu(event)">⋯</button>

                            <div class="header-menu" id="headerMenu">
                                <button onclick="openArchivedPanel(event)">Archived</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="friends-list" id="friendsList">
                    @forelse($friends as $friend)
                        @php
                            $initials = strtoupper(
                                substr($friend->first_name, 0, 1) . substr($friend->last_name, 0, 1),
                            );
                            $lastMsg = $friend->last_message;
                            $timeStr = '';
                            if ($lastMsg) {
                                $ts = \Carbon\Carbon::parse($lastMsg->created_at);
                                $timeStr = $ts->isToday()
                                    ? $ts->format('g:i A')
                                    : ($ts->isYesterday()
                                        ? 'Yesterday'
                                        : $ts->format('M j'));
                            }
                        @endphp
                        <div class="friend-item" data-friend-id="{{ $friend->id }}"
                            data-friend-name="{{ $friend->first_name }} {{ $friend->last_name }}"
                            data-friend-photo="{{ $friend->profile_photo_url }}"
                            data-friend-initials="{{ $initials }}" onclick="openConversation(this)">
                            <div class="friend-avatar">
                                @if ($friend->profile_photo_url)
                                    <img src="{{ $friend->profile_photo_url }}" alt="{{ $friend->first_name }}"
                                        onerror="this.style.display='none'; this.parentElement.textContent='{{ $initials }}'">
                                @else
                                    {{ $initials }}
                                @endif
                            </div>
                            <div class="friend-info">
                                <div class="friend-name">{{ $friend->first_name }} {{ $friend->last_name }}</div>
                                <div class="friend-last-msg" id="lastMsg-{{ $friend->id }}">
                                    {{ $lastMsg ? \Illuminate\Support\Str::limit($lastMsg->message, 35) : 'No messages yet' }}
                                </div>
                            </div>
                            <div class="friend-meta">
                                <span class="friend-time" id="lastTime-{{ $friend->id }}">{{ $timeStr }}</span>
                                @if (!$friend->is_muted && $friend->unread_count > 0)
                                    <span class="unread-badge"
                                        id="badge-{{ $friend->id }}">{{ $friend->unread_count }}</span>
                                @else
                                    <span class="unread-badge" id="badge-{{ $friend->id }}"
                                        style="display:none;">0</span>
                                @endif
                            </div>
                            <button class="friend-menu-btn" onclick="event.stopPropagation(); toggleFriendMenu(this)">
                                ˅
                            </button>
                            <div class="friend-menu">
                                <button onclick="event.stopPropagation(); archiveConversation('{{ $friend->id }}')">Archive</button>
                                @if ($friend->is_muted)
                                    <button onclick="event.stopPropagation(); unmuteConversation('{{ $friend->id }}')">Unmute</button>
                                @else
                                    <button onclick="event.stopPropagation(); muteConversation('{{ $friend->id }}')">Mute</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="no-friends">
                            <span>👥</span>
                            You haven't added any friends yet.<br>Connect with classmates to start chatting!
                        </div>
                    @endforelse
                </div>

                <div class="archived-panel" id="archivedPanel">
                    <div class="archived-header">
                        <button onclick="closeArchivedPanel()">←</button>
                        <h3>Archived</h3>
                    </div>

                    <div class="archived-list" id="archivedList">
                        @forelse($archivedFriends ?? [] as $friend)
                            @php
                                $initials = strtoupper(substr($friend->first_name, 0, 1) . substr($friend->last_name, 0, 1));
                            @endphp

                            <div class="friend-item archived-friend-item"
                                data-friend-id="{{ $friend->id }}"
                                data-friend-name="{{ $friend->first_name }} {{ $friend->last_name }}"
                                data-friend-photo="{{ $friend->profile_photo_url }}"
                                data-friend-initials="{{ $initials }}"
                                onclick="openConversation(this)">

                                <div class="friend-avatar">
                                    @if ($friend->profile_photo_url)
                                        <img src="{{ $friend->profile_photo_url }}" alt="{{ $friend->first_name }}"
                                            onerror="this.style.display='none'; this.parentElement.textContent='{{ $initials }}'">
                                    @else
                                        {{ $initials }}
                                    @endif
                                </div>

                                <div class="friend-info">
                                    <div class="friend-name">{{ $friend->first_name }} {{ $friend->last_name }}</div>
                                    <div class="friend-last-msg">Archived conversation</div>
                                </div>

                                <button class="unarchive-btn"
                                    onclick="event.stopPropagation(); unarchiveConversation('{{ $friend->id }}')">Unarchive</button>
                            </div>
                        @empty
                            <div class="no-friends">
                                <span>📦</span>
                                No archived conversations.
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>

            {{-- ── Chat Panel ── --}}
            <section class="chat-panel" id="chatPanel">
                {{-- Empty state --}}
                <div class="chat-empty" id="chatEmpty">
                    <div class="empty-icon">💬</div>
                    <h3>Select a conversation</h3>
                    <p>Choose a friend from the left to start chatting</p>
                </div>

                {{-- Active conversation (hidden until a friend is selected) --}}
                <div id="chatActive"
                    style="display:none; flex-direction:column; flex:1; overflow:hidden; display:none;">
                    <div class="chat-header" id="chatHeader">
                        <div class="chat-header-avatar" id="chatHeaderAvatar"></div>
                        <div class="chat-header-info">
                            <h3 id="chatHeaderName"></h3>
                            <span id="chatHeaderSub">StudyHub friend</span>
                        </div>
                    </div>

                    <div class="messages-area" id="messagesArea">
                        <div class="msg-loading" id="msgLoading">
                            <div class="spinner"></div> Loading messages…
                        </div>
                    </div>

                    <div class="chat-input-area">
                        <div class="chat-input-wrapper">
                            <textarea id="messageInput" rows="1" placeholder="Type a message…" onkeydown="handleInputKey(event)"
                                oninput="autoResize(this)"></textarea>
                        </div>
                        <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.2">
                                <path d="M22 2 11 13" />
                                <path d="M22 2 15 22 11 13 2 9l20-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // ── State ──────────────────────────────────────────────
        let AUTH_ID = '{{ Auth::id() }}';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let activeFriendId = null;
        let lastMessageId = null;
        let pollInterval = null;

        // Track the message ID currently showing the "Seen" indicator
        // so we only update the DOM when it actually changes
        let currentSeenMessageId = null;

        // ── Helpers ────────────────────────────────────────────
        function formatTime(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }

        function formatDate(dateStr) {
            const d = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - d) / 86400000);
            if (diff === 0) return 'Today';
            if (diff === 1) return 'Yesterday';
            return d.toLocaleDateString([], {
                month: 'short',
                day: 'numeric',
                year: diff > 300 ? 'numeric' : undefined
            });
        }

        function avatarHtml(photoUrl, initials, size = 28) {
            if (photoUrl) {
                return `<img src="${photoUrl}" alt="" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;"
                        onerror="this.style.display='none'; this.insertAdjacentText('afterend','${initials}')">`;
            }
            return initials;
        }

        function initials(first, last) {
            return (first[0] || '').toUpperCase() + (last[0] || '').toUpperCase();
        }

        function autoResize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        }

        // ── Seen indicator ─────────────────────────────────────
        // Places a "Seen" label beneath the sent bubble whose data-msg-id
        // matches lastSeenMessageId.  Removes any old indicator first.
        function updateSeenIndicator(lastSeenMessageId) {
            // Nothing changed — skip DOM work
            if (lastSeenMessageId === currentSeenMessageId) return;

            const area = document.getElementById('messagesArea');

            // Remove existing indicator
            area.querySelectorAll('.seen-indicator').forEach(el => el.remove());

            if (!lastSeenMessageId) {
                currentSeenMessageId = null;
                return;
            }

            // Find the sent bubble wrapper that owns this message ID
            const targetRow = area.querySelector(`.msg-row.sent[data-msg-id="${lastSeenMessageId}"]`);
            if (!targetRow) {
                // Message may not be rendered yet (e.g. very first poll after send).
                // We'll try again next poll — don't update currentSeenMessageId.
                return;
            }

            const bubbleWrap = targetRow.querySelector('div'); // the flex-column wrapper
            if (!bubbleWrap) return;

            const seen = document.createElement('div');
            seen.className = 'seen-indicator';
            seen.innerHTML = `
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="#8b7355" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Seen
            `;
            bubbleWrap.appendChild(seen);

            currentSeenMessageId = lastSeenMessageId;
        }

        // ── Friend search ──────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            const friendSearch = document.getElementById('friendSearch');

            if (!friendSearch) return;

            friendSearch.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();

                document.querySelectorAll('#friendsList > .friend-item').forEach(item => {
                    const name = (item.dataset.friendName || '').toLowerCase();
                    const lastMsg = item.querySelector('.friend-last-msg')?.textContent.toLowerCase() || '';

                    item.style.display = name.includes(q) || lastMsg.includes(q) ? 'flex' : 'none';
                });
            });
        });

        // ── Open Conversation ──────────────────────────────────
        function openConversation(el) {
            const friendId       = el.dataset.friendId;
            const friendName     = el.dataset.friendName;
            const friendPhoto    = el.dataset.friendPhoto;
            const friendInitials = el.dataset.friendInitials;

            // Mark active
            document.querySelectorAll('.friend-item').forEach(i => i.classList.remove('active'));
            el.classList.add('active');

            // Clear unread badge
            const badge = document.getElementById('badge-' + friendId);
            if (badge) badge.style.display = 'none';

            // Stop previous poll
            if (pollInterval) clearInterval(pollInterval);

            activeFriendId       = friendId;
            lastMessageId        = null;
            currentSeenMessageId = null; // reset seen state for new conversation

            // Update header
            const headerAvatar = document.getElementById('chatHeaderAvatar');
            headerAvatar.innerHTML = friendPhoto
                ? `<img src="${friendPhoto}" alt="" style="width:100%;height:100%;object-fit:cover;"
                       onerror="this.style.display='none'; this.parentElement.textContent='${friendInitials}'">`
                : friendInitials;
            document.getElementById('chatHeaderName').textContent = friendName;

            // Show chat panel
            document.getElementById('chatEmpty').style.display = 'none';
            const chatActive = document.getElementById('chatActive');
            chatActive.style.display       = 'flex';
            chatActive.style.flexDirection = 'column';
            chatActive.style.flex          = '1';
            chatActive.style.overflow      = 'hidden';

            // Load messages
            loadConversation(friendId);

            // Start polling for new messages every 3s
            pollInterval = setInterval(() => pollMessages(friendId), 3000);

            // Focus input
            setTimeout(() => document.getElementById('messageInput').focus(), 100);
        }

        // ── Load full conversation ─────────────────────────────
        async function loadConversation(friendId) {
            const area = document.getElementById('messagesArea');
            area.innerHTML = '<div class="msg-loading"><div class="spinner"></div> Loading messages…</div>';

            try {
                const res  = await fetch(`/messages/conversation/${friendId}`, {
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                const data = await res.json();

                // Update AUTH_ID from response (fixes logout/login issue)
                if (data.auth_id) AUTH_ID = data.auth_id;

                renderMessages(data.messages, true);

                if (data.messages.length > 0) {
                    lastMessageId = data.messages[data.messages.length - 1].id;
                }

                // Show seen indicator from the initial load
                if (data.last_seen_message_id) {
                    updateSeenIndicator(data.last_seen_message_id);
                }
            } catch (e) {
                area.innerHTML = '<div class="msg-loading" style="color:#e88;">Failed to load messages.</div>';
            }
        }

        // ── Render messages ────────────────────────────────────
        // Each sent msg-row gets data-msg-id so the seen indicator can target it.
        function renderMessages(messages, replace = false) {
            const area = document.getElementById('messagesArea');

            if (replace) area.innerHTML = '';

            if (replace && messages.length === 0) {
                area.innerHTML = '<div class="msg-loading" style="color:#ccc;">No messages yet. Say hello! 👋</div>';
                return;
            }

            let lastDate   = null;
            let lastSender = null;

            if (!replace) {
                const existingRows = area.querySelectorAll('.msg-row');
                if (existingRows.length > 0) {
                    lastSender = existingRows[existingRows.length - 1].dataset.senderId;
                }
            }

            messages.forEach((msg) => {
                const msgDate = new Date(msg.created_at).toDateString();

                // Date divider
                if (msgDate !== lastDate) {
                    const divider = document.createElement('div');
                    divider.className = 'date-divider';
                    divider.innerHTML = `<span>${formatDate(msg.created_at)}</span>`;
                    area.appendChild(divider);
                    lastDate   = msgDate;
                    lastSender = null;
                }

                const isSent    = String(msg.sender_id) === String(AUTH_ID);
                const sameGroup = msg.sender_id === lastSender;
                const ini       = initials(msg.first_name || '', msg.last_name || '');

                const row = document.createElement('div');
                row.className          = `msg-row ${isSent ? 'sent' : 'received'}`;
                row.dataset.senderId   = msg.sender_id;

                // Store message ID on the row so seen indicator can find it
                if (isSent) {
                    row.dataset.msgId = msg.id;
                }

                if (!isSent) {
                    const avatarDiv = document.createElement('div');
                    avatarDiv.className = `msg-bubble-avatar ${sameGroup ? 'hidden' : ''}`;
                    avatarDiv.innerHTML = avatarHtml(msg.profile_photo_url, ini);
                    row.appendChild(avatarDiv);
                }

                const bubbleWrap = document.createElement('div');
                bubbleWrap.style.display       = 'flex';
                bubbleWrap.style.flexDirection = 'column';
                bubbleWrap.style.alignItems    = isSent ? 'flex-end' : 'flex-start';

                const bubble = document.createElement('div');
                bubble.className  = 'msg-bubble';
                bubble.textContent = msg.message;

                const timeEl = document.createElement('div');
                timeEl.className  = 'msg-time';
                timeEl.textContent = formatTime(msg.created_at);

                bubbleWrap.appendChild(bubble);
                bubbleWrap.appendChild(timeEl);
                row.appendChild(bubbleWrap);
                area.appendChild(row);

                lastSender = msg.sender_id;
            });

            // Scroll to bottom
            area.scrollTop = area.scrollHeight;
        }

        // ── Poll for new messages ──────────────────────────────
        async function pollMessages(friendId) {
            if (friendId !== activeFriendId) return;

            try {
                const params = lastMessageId ? `?after_id=${lastMessageId}` : '';
                const res    = await fetch(`/messages/poll/${friendId}${params}`, {
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                const data = await res.json();

                // Render any new incoming messages
                if (data.messages && data.messages.length > 0) {
                    renderMessages(data.messages, false);
                    lastMessageId = data.messages[data.messages.length - 1].id;

                    // Remove loading placeholder if it exists
                    const placeholder = document.querySelector('.msg-loading');
                    if (placeholder) placeholder.remove();
                }

                // Update the seen indicator regardless of new messages —
                // the friend may have read your messages without sending any back.
                if (data.last_seen_message_id !== undefined) {
                    updateSeenIndicator(data.last_seen_message_id);
                }
            } catch (e) {
                // Silent fail for poll
            }
        }

        // ── Send message ───────────────────────────────────────
        async function sendMessage() {
            const input   = document.getElementById('messageInput');
            const sendBtn = document.getElementById('sendBtn');
            const message = input.value.trim();

            if (!message || !activeFriendId) return;

            input.value        = '';
            input.style.height = 'auto';
            sendBtn.disabled   = true;

            // Optimistic render — give the temporary bubble a temp ID
            const tmpId = 'tmp-' + Date.now();
            const optimistic = {
                id:                tmpId,
                sender_id:         AUTH_ID,
                receiver_id:       activeFriendId,
                message:           message,
                created_at:        new Date().toISOString(),
                first_name:        '',
                last_name:         '',
                profile_photo_url: null,
            };
            renderMessages([optimistic], false);

            // Remove loading placeholder
            const placeholder = document.querySelector('.msg-loading');
            if (placeholder) placeholder.remove();

            try {
                const res  = await fetch('/messages/send', {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'X-CSRF-TOKEN':  CSRF_TOKEN,
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({ receiver_id: activeFriendId, message }),
                });
                const data = await res.json();

                if (data.message) {
                    // Swap the temporary row's data-msg-id to the real server-assigned ID
                    // so the seen indicator can match it once the friend reads it.
                    const tmpRow = document.querySelector(`.msg-row.sent[data-msg-id="${tmpId}"]`);
                    if (tmpRow) tmpRow.dataset.msgId = data.message.id;

                    // Update last message ID for polling
                    lastMessageId = data.message.id;

                    // Update sidebar preview
                    const lastMsgEl  = document.getElementById('lastMsg-'  + activeFriendId);
                    const lastTimeEl = document.getElementById('lastTime-' + activeFriendId);
                    if (lastMsgEl)  lastMsgEl.textContent  = message.length > 35 ? message.substring(0, 35) + '…' : message;
                    if (lastTimeEl) lastTimeEl.textContent = formatTime(data.message.created_at);
                }
            } catch (e) {
                // Keep the optimistic message; user can retry
            }

            sendBtn.disabled = false;
            input.focus();
        }

        // ── Enter key to send ──────────────────────────────────
        function handleInputKey(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        }

        // ── Friend menu handling ───────────────────────────────
        function toggleFriendMenu(btn) {
            document.querySelectorAll('.friend-menu').forEach(menu => {
                if (menu !== btn.nextElementSibling) {
                    menu.classList.remove('show');
                }
            });

            btn.nextElementSibling.classList.toggle('show');
        }

        document.addEventListener('click', () => {
            document.querySelectorAll('.friend-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            document.getElementById('headerMenu')?.classList.remove('show');
        });

        function toggleHeaderMenu(event) {
            event.stopPropagation();
            document.getElementById('headerMenu').classList.toggle('show');
        }

        function openArchivedPanel(event) {
            event.stopPropagation();
            document.getElementById('headerMenu').classList.remove('show');
            document.getElementById('archivedPanel').classList.add('show');
        }

        function closeArchivedPanel() {
            document.getElementById('archivedPanel').classList.remove('show');
        }

        async function unarchiveConversation(friendId) {
            await fetch(`/messages/${friendId}/unarchive`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
            });

            location.reload();
        }

        async function archiveConversation(friendId) {
            await fetch(`/messages/${friendId}/archive`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
            });

            const item = document.querySelector(`.friend-item[data-friend-id="${friendId}"]`);
            if (item) item.remove();

            if (String(activeFriendId) === String(friendId)) {
                activeFriendId = null;
                document.getElementById('chatActive').style.display = 'none';
                document.getElementById('chatEmpty').style.display = 'flex';
            }
        }

        async function muteConversation(friendId) {
            await fetch(`/messages/${friendId}/mute`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
            });

            const badge = document.getElementById('badge-' + friendId);
            if (badge) badge.style.display = 'none';

            location.reload();
        }

        async function unmuteConversation(friendId) {
            await fetch(`/messages/${friendId}/unmute`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
            });

            location.reload();
        }
    </script>

    <script>
        const SB_URL  = '{{ config('services.supabase.url') }}';
        const SB_ANON = '{{ config('services.supabase.anon_key') }}';
        const SB_SVC  = '{{ config('services.supabase.service_key') }}';
        const UID     = '{{ session('user_id') }}';
    </script>

    <script src="{{ asset('js/notifications.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => initNotifications());
    </script>

</body>

</html>
