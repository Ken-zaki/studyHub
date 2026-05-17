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
    <style>
        /* ── Layout ── */
        .messages-layout {
            display: flex;
            height: calc(100vh - 0px);
            overflow: hidden;
            background: #f5f4f0;
        }

        /* ── Friends Sidebar ── */
        .friends-panel {
            width: 390px;
            min-width: 390px;
            background: #ffffff;
            border-right: 1.5px solid #e8e4de;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .friends-panel-header {
            padding: 24px 20px 16px;
            border-bottom: 1.5px solid #e8e4de;
        }

        .friends-panel-header h2 {
            font-family: 'Crimson Pro', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 14px 0;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 36px;
            border: 1.5px solid #ddd9d2;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            background: #faf9f7;
            color: #1a1a1a;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .search-box input:focus {
            border-color: #8b7355;
        }

        .search-box svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
        }

        .friends-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }

        .friends-list::-webkit-scrollbar {
            width: 4px;
        }

        .friends-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .friends-list::-webkit-scrollbar-thumb {
            background: #ddd9d2;
            border-radius: 4px;
        }

        .friend-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 22px;
            min-height: 74px;
            cursor: pointer;
            transition: background 0.15s;
            position: relative;
        }

        .friend-item:hover {
            background: #faf9f7;
        }

        .friend-item.active {
            background: #f0ece4;
        }

        .friend-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            background: #e8e4de;
            flex-shrink: 0;
            font-family: 'Crimson Pro', serif;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b7355;
            border: 2px solid #e8e4de;
        }

        .friend-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .friend-info {
            flex: 1;
            min-width: 0;
        }

        .friend-name {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #1a1a1a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .friend-last-msg {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            color: #888;
            margin-top: 4px;
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        .friend-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            flex-shrink: 0;
        }

        .friend-time {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.72rem;
            color: #aaa;
        }

        .unread-badge {
            background: #8b7355;
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.78rem;
            font-weight: 700;
            border-radius: 50px;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .no-friends {
            padding: 40px 20px;
            text-align: center;
            color: #aaa;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
        }

        .no-friends span {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
        }

        /* ── Friend menu button and dropdown ── */
        .friend-menu-btn {
            display: none;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #8b7355;
            font-size: 1rem;
            padding: 4px;
            border-radius: 6px;
        }

        .friend-item:hover .friend-menu-btn {
            display: block;
        }

        .friend-menu {
            display: none;
            position: absolute;
            right: 14px;
            top: 42px;
            background: #fff;
            border: 1px solid #e8e4de;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
            z-index: 20;
            min-width: 120px;
            overflow: hidden;
        }

        .friend-menu.show {
            display: block;
        }

        .friend-menu button {
            width: 100%;
            padding: 9px 12px;
            border: none;
            background: white;
            text-align: left;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.82rem;
            color: #1a1a1a;
        }

        .friend-menu button:hover {
            background: #faf9f7;
        }

        /* ── Friends actions and header menu ── */
        .friends-actions-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .friends-actions-row .search-box {
            flex: 1;
        }

        .header-menu-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .header-menu-btn {
            width: 36px;
            height: 36px;
            border: 1.5px solid #ddd9d2;
            background: #faf9f7;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.3rem;
            line-height: 1;
            color: #8b7355;
            transition: background 0.15s, transform 0.1s;
        }

        .header-menu-btn:hover {
            background: #f0ece4;
        }

        .header-menu-btn:active {
            transform: scale(0.94);
        }

        .header-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 44px;
            min-width: 145px;
            background: #ffffff;
            border: 1.5px solid #e8e4de;
            border-radius: 12px;
            box-shadow: 0 10px 28px rgba(0,0,0,0.13);
            overflow: hidden;
            z-index: 50;
            animation: menuDrop 0.16s ease;
        }

        .header-menu.show {
            display: block;
        }

        .header-menu button {
            width: 100%;
            border: none;
            background: #ffffff;
            padding: 11px 14px;
            text-align: left;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.86rem;
            color: #1a1a1a;
        }

        .header-menu button:hover {
            background: #faf9f7;
        }

        @keyframes menuDrop {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Archived panel ── */
        .friends-panel {
            position: relative;
        }

        .archived-panel {
            display: none;
            flex-direction: column;
            position: absolute;
            top: 0;
            left: 0;
            width: 390px;
            height: 100%;
            background: #ffffff;
            z-index: 40;
        }

        .archived-panel.show {
            display: flex;
        }

        .archived-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 22px 20px 16px;
            border-bottom: 1.5px solid #e8e4de;
        }

        .archived-header button {
            border: none;
            background: #faf9f7;
            border-radius: 9px;
            width: 34px;
            height: 34px;
            cursor: pointer;
            color: #8b7355;
            font-size: 1rem;
        }

        .archived-header h3 {
            font-family: 'Crimson Pro', serif;
            font-size: 1.45rem;
            margin: 0;
            color: #1a1a1a;
        }

        .archived-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }

        .unarchive-btn {
            border: none;
            background: #f0ece4;
            color: #8b7355;
            border-radius: 8px;
            padding: 6px 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
        }

        .unarchive-btn:hover {
            background: #e8e4de;
        }

        /* ── Chat Area ── */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #faf9f7;
        }

        /* Empty state */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #bbb;
            font-family: 'DM Sans', sans-serif;
        }

        .chat-empty .empty-icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
        }

        .chat-empty h3 {
            font-family: 'Crimson Pro', serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: #aaa;
            margin: 0 0 6px 0;
        }

        .chat-empty p {
            font-size: 0.875rem;
            color: #ccc;
            margin: 0;
        }

        /* Chat header */
        .chat-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 30px;
            background: #ffffff;
            border-bottom: 1.5px solid #e8e4de;
        }

        .chat-header-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #e8e4de;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Crimson Pro', serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: #8b7355;
            overflow: hidden;
            border: 2px solid #e8e4de;
        }

        .chat-header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-header-info h3 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            font-size: 1.08rem;
            color: #1a1a1a;
            margin: 0;
        }

        .chat-header-info span {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.78rem;
            color: #aaa;
        }

        /* Messages */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 28px 32px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .messages-area::-webkit-scrollbar {
            width: 4px;
        }

        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-area::-webkit-scrollbar-thumb {
            background: #ddd9d2;
            border-radius: 4px;
        }

        .msg-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            color: #bbb;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            gap: 8px;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #e8e4de;
            border-top-color: #8b7355;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Date divider */
        .date-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0 10px;
        }

        .date-divider::before,
        .date-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e8e4de;
        }

        .date-divider span {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.72rem;
            color: #bbb;
            white-space: nowrap;
        }

        /* Bubble */
        .msg-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 2px;
        }

        .msg-row.sent {
            flex-direction: row-reverse;
        }

        .msg-bubble-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e8e4de;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Crimson Pro', serif;
            font-size: 0.75rem;
            font-weight: 600;
            color: #8b7355;
            flex-shrink: 0;
            overflow: hidden;
        }

        .msg-bubble-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .msg-bubble-avatar.hidden {
            visibility: hidden;
        }

        .msg-bubble {
            max-width: 74%;
            padding: 13px 17px;
            border-radius: 22px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.96rem;
            line-height: 1.6;
            word-break: break-word;
            position: relative;
        }

        .msg-row.received .msg-bubble {
            background: #ffffff;
            color: #1a1a1a;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }

        .msg-row.sent .msg-bubble {
            background: #8b7355;
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }

        .msg-time {
            font-size: 0.68rem;
            margin-top: 3px;
            text-align: right;
            opacity: 0.6;
        }

        .msg-row.received .msg-time {
            text-align: left;
        }

        /* Grouping: hide avatar for consecutive messages */
        .msg-row.group-end .msg-bubble {
            border-radius: 18px;
        }

        .msg-row.received.group-end .msg-bubble {
            border-bottom-left-radius: 4px;
        }

        .msg-row.sent.group-end .msg-bubble {
            border-bottom-right-radius: 4px;
        }

        /* ── Seen indicator ── */
        .seen-indicator {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.7rem;
            color: #8b7355;
            text-align: right;
            margin-top: 3px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 3px;
            opacity: 0.85;
            animation: fadeInSeen 0.3s ease;
        }

        .seen-indicator svg {
            flex-shrink: 0;
        }

        @keyframes fadeInSeen {
            from { opacity: 0; transform: translateY(3px); }
            to   { opacity: 0.85; transform: translateY(0); }
        }

        /* Input */
        .chat-input-area {
            padding: 20px 26px;
            background: #ffffff;
            border-top: 1.5px solid #e8e4de;
            display: flex;
            align-items: flex-end;
            gap: 12px;
        }

        .chat-input-wrapper {
            flex: 1;
            background: #faf9f7;
            border: 1.5px solid #ddd9d2;
            border-radius: 14px;
            display: flex;
            align-items: flex-end;
            padding: 8px 14px;
            transition: border-color 0.2s;
        }

        .chat-input-wrapper:focus-within {
            border-color: #8b7355;
        }

        #messageInput {
            flex: 1;
            border: none;
            background: transparent;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            line-height: 1.6;
            color: #1a1a1a;
            resize: none;
            outline: none;
            max-height: 120px;
            min-height: 22px;
        }

        #messageInput::placeholder {
            color: #bbb;
        }

        .send-btn {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: #8b7355;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, transform 0.1s;
            flex-shrink: 0;
            color: #fff;
        }

        .send-btn:hover {
            background: #7a6347;
        }

        .send-btn:active {
            transform: scale(0.94);
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Responsive */
        @media (max-width: 500px) {
            .friends-panel {
                width: 72px;
                min-width: 72px;
            }

            .friends-panel-header h2,
            .search-box,
            .friend-info,
            .friend-meta {
                display: none;
            }

            .friend-item {
                padding: 10px;
                justify-content: center;
            }

            .friend-avatar {
                width: 42px;
                height: 42px;
            }

            .chat-header,
            .chat-input-area {
                padding: 14px 16px;
            }

            .messages-area {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

    @include('layouts.sidebar')

    <main class="messages-fullscreen" style="margin-left:70px;">
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
                                @if (!($friend->is_muted ?? false) && $friend->unread_count > 0)
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
                                @if ($friend->is_muted ?? false)
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
                    style="display:none; flex-direction:column; flex:1; overflow:hidden;">
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

            try {

                const response = await fetch(`/messages/${friendId}/archive`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {

                    // FULL PAGE REFRESH
                    location.reload();

                }

            } catch (err) {

                console.error(err);

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
