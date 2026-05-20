    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Study Groups - StudyHub</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
        <style>
            /* ══════════════════════════════════════════
            BASE LAYOUT
            ══════════════════════════════════════════ */
            .sg-layout {
                display: flex;
                height: 100vh;
                overflow: hidden;
                background: #0f1117;
                color: #e8e6e1;
                font-family: 'DM Sans', sans-serif;
            }

            /* ══════════════════════════════════════════
            SIDEBAR
            ══════════════════════════════════════════ */
            .sg-sidebar {
                width: 280px;
                min-width: 280px;
                background: #161820;
                border-right: 1px solid #252830;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .sg-sidebar-header {
                padding: 20px 18px 14px;
                border-bottom: 1px solid #252830;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .sg-sidebar-header h2 {
                font-family: 'Crimson Pro', serif;
                font-size: 1.35rem;
                font-weight: 700;
                color: #e8e6e1;
                margin: 0;
            }

            .btn-new-group {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: #6c63ff;
                border: none;
                color: #fff;
                font-size: 1.3rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background .2s, transform .15s;
            }

            .btn-new-group:hover {
                background: #5a52e0;
                transform: scale(1.08);
            }

            .sg-group-list {
                flex: 1;
                overflow-y: auto;
                padding: 10px 8px;
            }

            .sg-group-list::-webkit-scrollbar {
                width: 3px;
            }

            .sg-group-list::-webkit-scrollbar-thumb {
                background: #2a2d3e;
                border-radius: 4px;
            }

            .sg-group-item {
                display: flex;
                align-items: center;
                gap: 11px;
                padding: 10px 10px;
                border-radius: 10px;
                cursor: pointer;
                transition: background .15s;
                border-left: 3px solid transparent;
            }

            .sg-group-item:hover {
                background: #1e2030;
            }

            .sg-group-item.active {
                background: #1e2030;
                border-left-color: #6c63ff;
                padding-left: 7px;
            }

            .sg-group-avatar {
                width: 42px;
                height: 42px;
                border-radius: 12px;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                font-weight: 700;
                color: #fff;
                flex-shrink: 0;
                overflow: hidden;
            }

            .sg-group-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 12px;
            }

            /* privacy badge */
            .sg-group-item-wrap {
                position: relative;
                flex: 1;
                min-width: 0;
            }

            .sg-group-name {
                font-size: 0.88rem;
                font-weight: 600;
                color: #e8e6e1;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sg-group-meta {
                display: flex;
                align-items: center;
                gap: 5px;
                margin-top: 2px;
            }

            .sg-group-subject {
                font-size: 0.74rem;
                color: #6b7280;
            }

            .privacy-badge {
                font-size: 0.62rem;
                padding: 1px 5px;
                border-radius: 4px;
                font-weight: 600;
                letter-spacing: .03em;
                flex-shrink: 0;
            }

            .privacy-badge.public {
                background: rgba(34, 197, 94, .12);
                color: #4ade80;
                border: 1px solid rgba(34, 197, 94, .2);
            }

            .privacy-badge.private {
                background: rgba(251, 191, 36, .1);
                color: #fbbf24;
                border: 1px solid rgba(251, 191, 36, .18);
            }

            /* ══════════════════════════════════════════
            CHAT PANEL
            ══════════════════════════════════════════ */
            .sg-chat {
                flex: 1;
                display: flex;
                flex-direction: column;
                background: #0f1117;
                overflow: hidden;
            }

            .sg-empty {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                color: #4b5563;
                gap: 12px;
            }

            .sg-empty-icon {
                font-size: 3rem;
            }

            .sg-empty p {
                font-size: 0.9rem;
            }

            /* ── CHAT HEADER ── */
            .sg-chat-header {
                padding: 12px 18px;
                border-bottom: 1px solid #252830;
                display: flex;
                align-items: center;
                gap: 14px;
                background: #161820;
                position: relative;
            }

            .sg-chat-header-avatar {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                font-weight: 700;
                color: #fff;
                flex-shrink: 0;
                overflow: hidden;
            }

            .sg-chat-header-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 10px;
            }

            .sg-chat-header-info {
                flex: 1;
            }

            .sg-chat-header-name {
                font-weight: 600;
                font-size: 0.95rem;
                color: #e8e6e1;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .sg-chat-header-members {
                font-size: 0.74rem;
                color: #6b7280;
            }

            .header-privacy-badge {
                font-size: 0.62rem;
                padding: 2px 7px;
                border-radius: 5px;
                font-weight: 600;
            }

            .header-privacy-badge.public {
                background: rgba(34, 197, 94, .12);
                color: #4ade80;
                border: 1px solid rgba(34, 197, 94, .2);
            }

            .header-privacy-badge.private {
                background: rgba(251, 191, 36, .1);
                color: #fbbf24;
                border: 1px solid rgba(251, 191, 36, .18);
            }

            /* ── SETTINGS BUTTON & DROPDOWN ── */
            .sg-settings-wrap {
                position: relative;
            }

            .btn-settings {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: #1e2030;
                border: 1px solid #2a2d3e;
                color: #a78bfa;
                font-size: 1.05rem;
                font-weight: 700;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background .2s, border-color .2s, transform .15s;
                flex-shrink: 0;
            }

            .btn-settings:hover {
                background: #252840;
                border-color: #6c63ff;
                transform: scale(1.05);
            }

            .btn-settings.active {
                background: #252840;
                border-color: #6c63ff;
                color: #6c63ff;
            }

            /* dropdown panel */
            .settings-dropdown {
                position: absolute;
                top: calc(100% + 10px);
                right: 0;
                width: 290px;
                background: #161820;
                border: 1px solid #252830;
                border-radius: 16px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, .55);
                z-index: 50;
                overflow: hidden;

                /* drop animation */
                opacity: 0;
                transform: translateY(-14px) scale(.97);
                pointer-events: none;
                transition: opacity .22s ease, transform .22s cubic-bezier(.34, 1.56, .64, 1);
            }

            .settings-dropdown.open {
                opacity: 1;
                transform: translateY(0) scale(1);
                pointer-events: all;
            }

            .sd-group-banner {
                padding: 18px 18px 14px;
                border-bottom: 1px solid #252830;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            /* Clickable photo upload area */
            .sd-avatar-wrap {
                position: relative;
                width: 52px;
                height: 52px;
                flex-shrink: 0;
                cursor: pointer;
            }

            .sd-avatar-wrap input[type="file"] {
                display: none;
            }

            .sd-avatar {
                width: 52px;
                height: 52px;
                border-radius: 14px;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.2rem;
                font-weight: 700;
                color: #fff;
                overflow: hidden;
                transition: filter .2s;
            }

            .sd-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 14px;
            }

            .sd-avatar-overlay {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, .55);
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                opacity: 0;
                transition: opacity .2s;
            }

            .sd-avatar-wrap:hover .sd-avatar-overlay {
                opacity: 1;
            }

            .sd-avatar-wrap:hover .sd-avatar {
                filter: brightness(.7);
            }

            .sd-avatar-hint {
                font-size: 0.68rem;
                color: #6b7280;
                margin-top: 3px;
            }

            .sd-group-title {
                flex: 1;
                min-width: 0;
            }

            .sd-group-title-name {
                font-weight: 700;
                font-size: 0.92rem;
                color: #e8e6e1;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sd-group-title-sub {
                font-size: 0.73rem;
                color: #6b7280;
                margin-top: 2px;
            }

            /* ── dropdown sections ── */
            .sd-section {
                padding: 10px 14px;
                border-bottom: 1px solid #1c1e2b;
            }

            .sd-section:last-child {
                border-bottom: none;
            }

            .sd-section-label {
                font-size: 0.67rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: #4b5563;
                margin-bottom: 6px;
            }

            /* rename inline */
            .sd-rename-row {
                display: flex;
                gap: 6px;
                align-items: center;
            }

            .sd-rename-input {
                flex: 1;
                background: #0f1117;
                border: 1px solid #2a2d3e;
                border-radius: 8px;
                padding: 7px 10px;
                color: #e8e6e1;
                font-family: 'DM Sans', sans-serif;
                font-size: 0.83rem;
                outline: none;
                transition: border-color .2s;
            }

            .sd-rename-input:focus {
                border-color: #6c63ff;
            }

            .btn-sd-rename {
                padding: 7px 12px;
                background: #6c63ff;
                border: none;
                border-radius: 8px;
                color: #fff;
                font-size: 0.78rem;
                font-weight: 600;
                cursor: pointer;
                white-space: nowrap;
                transition: background .2s;
            }

            .btn-sd-rename:hover {
                background: #5a52e0;
            }

            /* members list inside dropdown */
            .sd-members-list {
                display: flex;
                flex-direction: column;
                gap: 4px;
                max-height: 160px;
                overflow-y: auto;
            }

            .sd-members-list::-webkit-scrollbar {
                width: 3px;
            }

            .sd-members-list::-webkit-scrollbar-thumb {
                background: #2a2d3e;
                border-radius: 3px;
            }

            .sd-member-row {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 5px 6px;
                border-radius: 8px;
            }

            .sd-member-row:hover {
                background: #1e2030;
            }

            .sd-member-avatar {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.68rem;
                font-weight: 700;
                color: #fff;
                overflow: hidden;
                flex-shrink: 0;
            }

            .sd-member-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
            }

            .sd-member-name {
                font-size: 0.8rem;
                color: #d1cfe8;
                flex: 1;
            }

            .sd-member-you {
                font-size: 0.68rem;
                color: #6b7280;
            }

            /* delete group inside dropdown */
            .btn-sd-delete {
                width: 100%;
                padding: 9px;
                background: rgba(239, 68, 68, .08);
                border: 1px solid rgba(239, 68, 68, .2);
                border-radius: 9px;
                color: #f87171;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                transition: background .2s, border-color .2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            .btn-sd-delete:hover {
                background: rgba(239, 68, 68, .18);
                border-color: rgba(239, 68, 68, .4);
            }

            /* ══════════════════════════════════════════
            MESSAGES
            ══════════════════════════════════════════ */
            .sg-messages {
                flex: 1;
                overflow-y: auto;
                padding: 18px 22px;
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            .sg-messages::-webkit-scrollbar {
                width: 4px;
            }

            .sg-messages::-webkit-scrollbar-thumb {
                background: #2a2d3e;
                border-radius: 4px;
            }

            .date-sep {
                text-align: center;
                font-size: 0.72rem;
                color: #4b5563;
                position: relative;
                margin: 4px 0;
            }

            .date-sep::before,
            .date-sep::after {
                content: '';
                position: absolute;
                top: 50%;
                width: 40%;
                height: 1px;
                background: #252830;
            }

            .date-sep::before {
                left: 0;
            }

            .date-sep::after {
                right: 0;
            }

            .msg-row {
                display: flex;
                gap: 10px;
                align-items: flex-end;
            }

            .msg-row.own {
                flex-direction: row-reverse;
            }

            .msg-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.7rem;
                font-weight: 700;
                color: #fff;
                flex-shrink: 0;
                overflow: hidden;
            }

            .msg-avatar img {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                object-fit: cover;
            }

            .msg-body {
                max-width: 68%;
            }

            .msg-sender {
                font-size: 0.72rem;
                color: #6b7280;
                margin-bottom: 3px;
                padding-left: 2px;
            }

            .msg-row.own .msg-sender {
                text-align: right;
                padding-right: 2px;
            }

            .msg-bubble {
                background: #1e2030;
                border-radius: 14px 14px 14px 4px;
                padding: 9px 14px;
                font-size: 0.875rem;
                line-height: 1.5;
                color: #e8e6e1;
                word-break: break-word;
            }

            .msg-row.own .msg-bubble {
                background: #6c63ff;
                border-radius: 14px 14px 4px 14px;
                color: #fff;
            }

            .msg-time {
                font-size: 0.68rem;
                color: #4b5563;
                margin-top: 4px;
                padding-left: 2px;
            }

            .msg-row.own .msg-time {
                text-align: right;
                padding-right: 2px;
            }

            .msg-image {
                max-width: 220px;
                border-radius: 10px;
                margin-top: 4px;
                cursor: pointer;
                border: 1px solid #2a2d3e;
                display: block;
            }

            .msg-file {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #252830;
                border-radius: 8px;
                padding: 8px 12px;
                margin-top: 4px;
                font-size: 0.8rem;
                text-decoration: none;
                color: #c4c0d8;
                border: 1px solid #2e3040;
                transition: background .15s;
            }

            .msg-file:hover {
                background: #2e3040;
            }

            .msg-file-icon {
                font-size: 1.2rem;
            }

            .msg-file-name {
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .msg-file-size {
                color: #4b5563;
                font-size: 0.72rem;
            }

            /* ══════════════════════════════════════════
            INPUT AREA
            ══════════════════════════════════════════ */
            .sg-input-area {
                padding: 14px 18px;
                border-top: 1px solid #252830;
                background: #161820;
            }

            .sg-input-toolbar {
                display: flex;
                align-items: center;
                gap: 6px;
                background: #0f1117;
                border: 1px solid #2a2d3e;
                border-radius: 14px;
                padding: 8px 12px;
            }

            .sg-input-toolbar textarea {
                flex: 1;
                background: transparent;
                border: none;
                outline: none;
                resize: none;
                color: #e8e6e1;
                font-family: 'DM Sans', sans-serif;
                font-size: 0.875rem;
                line-height: 1.4;
                max-height: 120px;
                min-height: 22px;
                overflow-y: auto;
            }

            .sg-input-toolbar textarea::placeholder {
                color: #4b5563;
            }

            .sg-attach-btn {
                width: 32px;
                height: 32px;
                border: none;
                background: none;
                color: #6b7280;
                cursor: pointer;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.1rem;
                transition: color .15s, background .15s;
            }

            .sg-attach-btn:hover {
                color: #a78bfa;
                background: #1e2030;
            }

            .sg-send-btn {
                width: 34px;
                height: 34px;
                background: #6c63ff;
                border: none;
                border-radius: 9px;
                color: #fff;
                font-size: 1rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background .2s, transform .15s;
                flex-shrink: 0;
            }

            .sg-send-btn:hover {
                background: #5a52e0;
                transform: scale(1.05);
            }

            .sg-send-btn:disabled {
                background: #2a2d3e;
                cursor: not-allowed;
                transform: none;
            }

            .sg-upload-preview {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 8px 0 2px;
            }

            .sg-preview-item {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #1e2030;
                border-radius: 8px;
                padding: 5px 10px 5px 7px;
                font-size: 0.76rem;
                color: #c4c0d8;
                max-width: 180px;
            }

            .sg-preview-item img {
                width: 30px;
                height: 30px;
                border-radius: 5px;
                object-fit: cover;
            }

            .sg-preview-name {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .sg-preview-remove {
                background: none;
                border: none;
                color: #6b7280;
                cursor: pointer;
                font-size: 0.9rem;
                padding: 0;
                margin-left: 2px;
            }

            .sg-preview-remove:hover {
                color: #ef4444;
            }

            /* ══════════════════════════════════════════
            CREATE GROUP MODAL
            ══════════════════════════════════════════ */
            .sg-modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, .65);
                backdrop-filter: blur(4px);
                z-index: 100;
                display: none;
                align-items: center;
                justify-content: center;
            }

            .sg-modal-backdrop.open {
                display: flex;
            }

            .sg-modal {
                background: #161820;
                border: 1px solid #252830;
                border-radius: 18px;
                padding: 28px 26px;
                width: 460px;
                max-width: 95vw;
                box-shadow: 0 24px 60px rgba(0, 0, 0, .5);
            }

            .sg-modal h3 {
                font-family: 'Crimson Pro', serif;
                font-size: 1.4rem;
                font-weight: 700;
                margin: 0 0 20px;
                color: #e8e6e1;
            }

            .sg-modal label {
                display: block;
                font-size: 0.78rem;
                font-weight: 600;
                color: #9ca3af;
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: .04em;
            }

            .sg-modal input[type="text"] {
                width: 100%;
                background: #0f1117;
                border: 1px solid #2a2d3e;
                border-radius: 10px;
                padding: 10px 13px;
                color: #e8e6e1;
                font-size: 0.9rem;
                font-family: 'DM Sans', sans-serif;
                outline: none;
                box-sizing: border-box;
                transition: border-color .2s;
            }

            .sg-modal input[type="text"]:focus {
                border-color: #6c63ff;
            }

            .sg-field {
                margin-bottom: 18px;
            }

            /* privacy toggle */
            .privacy-toggle-wrap {
                display: flex;
                gap: 8px;
            }

            .privacy-opt {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 9px 12px;
                border-radius: 10px;
                border: 1.5px solid #2a2d3e;
                cursor: pointer;
                font-size: 0.82rem;
                font-weight: 500;
                color: #6b7280;
                transition: all .18s;
                user-select: none;
            }

            .privacy-opt:hover {
                border-color: #4b5563;
                color: #9ca3af;
                background: #1a1c25;
            }

            .privacy-opt.selected-public {
                border-color: rgba(34, 197, 94, .4);
                background: rgba(34, 197, 94, .06);
                color: #4ade80;
            }

            .privacy-opt.selected-private {
                border-color: rgba(251, 191, 36, .35);
                background: rgba(251, 191, 36, .06);
                color: #fbbf24;
            }

            .privacy-opt input[type="radio"] {
                display: none;
            }

            .privacy-opt-icon {
                font-size: 1rem;
            }

            /* friend list in modal */
            .friend-list {
                display: flex;
                flex-direction: column;
                gap: 6px;
                max-height: 180px;
                overflow-y: auto;
            }

            .friend-list::-webkit-scrollbar {
                width: 3px;
            }

            .friend-list::-webkit-scrollbar-thumb {
                background: #2a2d3e;
                border-radius: 3px;
            }

            .friend-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 10px;
                border-radius: 9px;
                cursor: pointer;
                transition: background .15s;
                user-select: none;
            }

            .friend-item:hover {
                background: #1e2030;
            }

            .friend-item input[type="checkbox"] {
                accent-color: #6c63ff;
                width: 15px;
                height: 15px;
            }

            .friend-avatar {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.8rem;
                font-weight: 700;
                color: #fff;
                overflow: hidden;
                flex-shrink: 0;
            }

            .friend-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .friend-name {
                font-size: 0.87rem;
                color: #e8e6e1;
            }

            .friend-username {
                font-size: 0.74rem;
                color: #6b7280;
            }

            .sg-modal-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 22px;
            }

            .btn-cancel {
                padding: 9px 18px;
                background: none;
                border: 1px solid #2a2d3e;
                border-radius: 9px;
                color: #9ca3af;
                font-size: 0.86rem;
                cursor: pointer;
                transition: background .15s;
            }

            .btn-cancel:hover {
                background: #1e2030;
            }

            .btn-create {
                padding: 9px 22px;
                background: #6c63ff;
                border: none;
                border-radius: 9px;
                color: #fff;
                font-size: 0.86rem;
                font-weight: 600;
                cursor: pointer;
                transition: background .2s;
            }

            .btn-create:hover {
                background: #5a52e0;
            }

            /* ══════════════════════════════════════════
            LIGHTBOX
            ══════════════════════════════════════════ */
            .lightbox {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, .88);
                z-index: 200;
                display: none;
                align-items: center;
                justify-content: center;
            }

            .lightbox.open {
                display: flex;
            }

            .lightbox img {
                max-width: 90vw;
                max-height: 90vh;
                border-radius: 10px;
            }

            .lightbox-close {
                position: absolute;
                top: 20px;
                right: 28px;
                color: #fff;
                font-size: 2rem;
                cursor: pointer;
                background: none;
                border: none;
            }

            .no-msg {
                color: #4b5563;
                font-size: 0.83rem;
                text-align: center;
                padding: 12px;
            }

            /* ══════════════════════════════════════════
            MEMBERS MODAL
            ══════════════════════════════════════════ */
            .members-modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, .65);
                backdrop-filter: blur(4px);
                z-index: 150;
                display: none;
                align-items: center;
                justify-content: center;
            }

            .members-modal-backdrop.open {
                display: flex;
            }

            .members-modal {
                background: #161820;
                border: 1px solid #252830;
                border-radius: 18px;
                padding: 24px 22px;
                width: 380px;
                max-width: 95vw;
                box-shadow: 0 24px 60px rgba(0, 0, 0, .5);
            }

            .members-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 16px;
            }

            .members-modal-header h3 {
                font-family: 'Crimson Pro', serif;
                font-size: 1.25rem;
                font-weight: 700;
                color: #e8e6e1;
                margin: 0;
            }

            .btn-close-modal {
                background: none;
                border: none;
                color: #6b7280;
                font-size: 1.2rem;
                cursor: pointer;
                transition: color .15s;
            }

            .btn-close-modal:hover {
                color: #e8e6e1;
            }

            .members-full-list {
                display: flex;
                flex-direction: column;
                gap: 6px;
                max-height: 340px;
                overflow-y: auto;
            }

            .members-full-list::-webkit-scrollbar {
                width: 3px;
            }

            .members-full-list::-webkit-scrollbar-thumb {
                background: #2a2d3e;
                border-radius: 3px;
            }

            .member-full-row {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 10px;
                border-radius: 10px;
                transition: background .15s;
            }

            .member-full-row:hover {
                background: #1e2030;
            }

            .member-full-avatar {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: linear-gradient(135deg, #6c63ff, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.82rem;
                font-weight: 700;
                color: #fff;
                overflow: hidden;
                flex-shrink: 0;
            }

            .member-full-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
            }

            .member-full-name {
                font-size: 0.87rem;
                color: #e8e6e1;
                font-weight: 500;
                flex: 1;
            }

            .member-full-username {
                font-size: 0.73rem;
                color: #6b7280;
            }

            .member-tag {
                font-size: 0.65rem;
                padding: 2px 7px;
                border-radius: 5px;
                font-weight: 600;
            }

            .member-tag.owner {
                background: rgba(108, 99, 255, .15);
                color: #a78bfa;
                border: 1px solid rgba(108, 99, 255, .25);
            }

            .member-tag.you {
                background: rgba(34, 197, 94, .1);
                color: #4ade80;
                border: 1px solid rgba(34, 197, 94, .2);
            }

            /* STUDY GROUP FULL WIDTH FIX - ACCOUNT FOR LEFT SIDEBAR */
            .sg-fullscreen {
                margin-left: var(--sidebar-width, 260px) !important;
                width: calc(100vw - var(--sidebar-width, 260px)) !important;
                max-width: calc(100vw - var(--sidebar-width, 260px)) !important;
                height: 100vh !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            .sg-fullscreen .sg-layout {
                width: 100% !important;
                max-width: 100% !important;
                height: 100vh !important;
                margin: 0 !important;
                padding: 0 !important;
                display: flex !important;
            }

            .sg-fullscreen .sg-sidebar {
                width: 360px !important;
                min-width: 360px !important;
                max-width: 360px !important;
                flex-shrink: 0 !important;
            }

            .sg-fullscreen .sg-chat {
                flex: 1 !important;
                width: 100% !important;
                min-width: 0 !important;
            }

            .sg-fullscreen,
            .sg-fullscreen .sg-layout,
            .sg-fullscreen .sg-chat,
            .sg-fullscreen .sg-messages,
            .sg-fullscreen .sg-chat-header,
            .sg-fullscreen .sg-input-area {
                box-sizing: border-box !important;
            }

            /* Remove shared layout/container choking */
            .main-content-simple,
            .main-content,
            .content-wrapper,
            .page-container,
            .container {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
        </style>
    </head>

    <body>

        @include('layouts.sidebar')

        <main class="main-content-simple sg-fullscreen">
            <div class="sg-layout">

                {{-- ══ GROUP LIST SIDEBAR ══ --}}
                <aside class="sg-sidebar">
                    <div class="sg-sidebar-header">
                        <h2>Study Groups</h2>
                        <button class="btn-new-group" id="btnOpenModal" title="Create new group">+</button>
                    </div>
                    <div class="sg-group-list" id="groupList">
                        @forelse($groups as $group)
                            <div class="sg-group-item" data-group-id="{{ $group->id }}"
                                data-privacy="{{ $group->is_private ? 'private' : 'public' }}"
                                onclick="openGroup('{{ $group->id }}', this)">
                                <div class="sg-group-avatar">
                                    @if ($group->photo)
                                        <img src="{{ $group->photo }}" alt="">
                                    @else
                                        {{ strtoupper(substr($group->name, 0, 2)) }}
                                    @endif
                                </div>
                                <div class="sg-group-item-wrap">
                                    <div class="sg-group-name">{{ $group->name }}</div>
                                    <div class="sg-group-meta">
                                        <span class="sg-group-subject">{{ $group->subject ?? 'General' }} ·
                                            {{ $group->members_count }} members</span>
                                        <span class="privacy-badge {{ $group->is_private ? 'private' : 'public' }}">
                                            {{ $group->is_private ? '🔒 Private' : '🌐 Public' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="no-msg" style="margin-top:24px;">No groups yet.<br>Hit <strong>+</strong> to
                                create one!</div>
                        @endforelse
                    </div>
                </aside>

                {{-- ══ CHAT PANEL ══ --}}
                <section class="sg-chat" id="chatPanel">
                    <div class="sg-empty" id="chatEmpty">
                        <div class="sg-empty-icon">👥</div>
                        <p>Select a group to start chatting</p>
                    </div>

                    {{-- CHAT HEADER --}}
                    <div class="sg-chat-header" id="chatHeader" style="display:none;">
                        <div class="sg-chat-header-avatar" id="chatAvatar"></div>
                        <div class="sg-chat-header-info">
                            <div class="sg-chat-header-name" id="chatGroupName">
                                {{-- name text injected by JS --}}
                            </div>
                            <div class="sg-chat-header-members" id="chatGroupMembers"></div>
                        </div>

                        {{-- !! SETTINGS BUTTON + DROPDOWN !! --}}
                        <div class="sg-settings-wrap" id="settingsWrap">
                            <button class="btn-settings" id="btnSettings" onclick="toggleSettings(event)"
                                title="Group settings">!</button>

                            <div class="settings-dropdown" id="settingsDropdown">

                                {{-- Group banner: avatar + name display --}}
                                <div class="sd-group-banner">
                                    <div class="sd-avatar-wrap"
                                        onclick="document.getElementById('groupPhotoInput').click()"
                                        title="Change group photo">
                                        <div class="sd-avatar" id="sdAvatar"></div>
                                        <div class="sd-avatar-overlay">📷</div>
                                        <input type="file" id="groupPhotoInput" accept="image/*">
                                    </div>
                                    <div class="sd-group-title">
                                        <div class="sd-group-title-name" id="sdGroupName">—</div>
                                        <div class="sd-group-title-sub" id="sdGroupSub">—</div>
                                    </div>
                                </div>

                                {{-- Rename group --}}
                                <div class="sd-section">
                                    <div class="sd-section-label">Rename Group</div>
                                    <div class="sd-rename-row">
                                        <input type="text" class="sd-rename-input" id="sdRenameInput"
                                            placeholder="New group name…">
                                        <button class="btn-sd-rename" onclick="renameGroup()">Save</button>
                                    </div>
                                </div>

                                {{-- Members --}}
                                <div class="sd-section">
                                    <div class="sd-section-label"
                                        style="display:flex;align-items:center;justify-content:space-between;">
                                        <span>Members</span>
                                        <button onclick="openMembersModal()"
                                            style="background:none;border:none;color:#6c63ff;font-size:0.72rem;cursor:pointer;font-weight:600;">See
                                            all →</button>
                                    </div>
                                    <div class="sd-members-list" id="sdMembersList">
                                        <div style="color:#4b5563;font-size:0.78rem;padding:4px 6px;">Loading…</div>
                                    </div>
                                </div>

                                {{-- Delete group --}}
                                <div class="sd-section">
                                    <button class="btn-sd-delete" onclick="deleteGroup()">
                                        🗑️ Delete Group
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- VIEW TABS --}}
                    <div
                        style="display: flex; gap: 0; border-bottom: 1px solid #252830; background: #0f1117; padding: 0;">
                        <button onclick="switchGroupView('messages')" id="tabMessages"
                            style="flex: 1; padding: 12px; background: #161820; color: #6c63ff; border: none; border-bottom: 2px solid #6c63ff; cursor: pointer; font-weight: 600; transition: all 0.2s; font-family: 'DM Sans';">💬
                            Messages</button>
                        <button onclick="switchGroupView('calendars')" id="tabCalendars"
                            style="flex: 1; padding: 12px; background: #0f1117; color: #6b7280; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-weight: 600; transition: all 0.2s; font-family: 'DM Sans';">📅
                            Shared Calendars</button>
                    </div>

                    <div class="sg-messages" id="messagesBox" style="display:none;"></div>

                    <div id="calendarsBox"
                        style="display: none; flex: 1; overflow-y: auto; padding: 20px; background: #0f1117;">
                        <div style="text-align: center; color: #6b7280; padding: 40px 20px;">
                            <div style="font-size: 2rem; margin-bottom: 10px;">📅</div>
                            <p>Loading shared calendars…</p>
                        </div>
                    </div>

                    <div class="sg-input-area" id="inputArea" style="display:none;">
                        <div id="uploadPreview" class="sg-upload-preview"></div>
                        <div class="sg-input-toolbar">
                            <input type="file" id="imageInput" accept="image/*" multiple style="display:none">
                            <button class="sg-attach-btn" onclick="document.getElementById('imageInput').click()"
                                title="Send image">🖼️</button>
                            <input type="file" id="fileInput" multiple style="display:none">
                            <button class="sg-attach-btn" onclick="document.getElementById('fileInput').click()"
                                title="Attach file">📎</button>
                            <textarea id="msgInput" rows="1" placeholder="Message…" onkeydown="handleEnter(event)"></textarea>
                            <button class="sg-send-btn" id="sendBtn" onclick="sendMessage()">➤</button>
                        </div>
                    </div>
                </section>

            </div>
        </main>

        {{-- ══ CREATE GROUP MODAL ══ --}}
        <div class="sg-modal-backdrop" id="modalBackdrop">
            <div class="sg-modal">
                <h3>Create Study Group</h3>

                <div class="sg-field">
                    <label for="groupNameInput">Group Name</label>
                    <input type="text" id="groupNameInput" placeholder="e.g. Calculus Squad">
                </div>

                <div class="sg-field">
                    <label for="groupSubjectInput">Subject (optional)</label>
                    <input type="text" id="groupSubjectInput" placeholder="e.g. Mathematics">
                </div>

                {{-- Privacy toggle --}}
                <div class="sg-field">
                    <label>Privacy</label>
                    <div class="privacy-toggle-wrap">
                        <label class="privacy-opt selected-public" id="optPublic" for="radioPublic">
                            <input type="radio" id="radioPublic" name="groupPrivacy" value="0" checked>
                            <span class="privacy-opt-icon">🌐</span> Public
                        </label>
                        <label class="privacy-opt" id="optPrivate" for="radioPrivate">
                            <input type="radio" id="radioPrivate" name="groupPrivacy" value="1">
                            <span class="privacy-opt-icon">🔒</span> Private
                        </label>
                    </div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 6px;">
                        <div id="publicDesc" style="display: block;">🌐 Anyone can discover and request to join this
                            group</div>
                        <div id="privateDesc" style="display: none;">🔒 Only invited members can join this group</div>
                    </div>
                </div>

                <div class="sg-field" id="friendsField" style="display: none;">
                    <label>Add Friends</label>
                    <div class="friend-list" id="friendList">
                        @forelse($friends as $friend)
                            <label class="friend-item" for="friend_{{ $friend['id'] }}">
                                <input type="checkbox" id="friend_{{ $friend['id'] }}" value="{{ $friend['id'] }}">
                                <div class="friend-avatar">
                                    @if (!empty($friend['photo']))
                                        <img src="{{ $friend['photo'] }}" alt="">
                                    @else
                                        {{ $friend['initials'] }}
                                    @endif
                                </div>
                                <div>
                                    <div class="friend-name">{{ $friend['name'] }}</div>
                                    <div class="friend-username">@{{ $friend['username'] }}</div>
                                </div>
                            </label>
                        @empty
                            <div class="no-msg">No friends to add yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="sg-modal-actions">
                    <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button class="btn-create" onclick="createGroup()">Create Group</button>
                </div>
            </div>
        </div>

        {{-- ══ MEMBERS MODAL ══ --}}
        <div class="members-modal-backdrop" id="membersModalBackdrop">
            <div class="members-modal">
                <div class="members-modal-header">
                    <h3 id="membersModalTitle">Members</h3>
                    <button class="btn-close-modal" onclick="closeMembersModal()">✕</button>
                </div>
                <div class="members-full-list" id="membersFullList">
                    <div style="color:#4b5563;font-size:0.83rem;text-align:center;padding:16px;">Loading…</div>
                </div>
            </div>
        </div>

        {{-- ══ LIGHTBOX ══ --}}
        <div class="lightbox" id="lightbox" onclick="closeLightbox()">
            <button class="lightbox-close">✕</button>
            <img src="" id="lightboxImg" alt="">
        </div>

        <script>
            const CSRF = document.querySelector('meta[name="csrf-token"]').content;
            const ME = @json(session('user_id'));

            let activeGroupId = null;
            let activeGroupData = {}; // { name, subject, privacy, photo }
            let pollInterval = null;
            let pendingFiles = [];
            let lastMsgCount = 0;
            let settingsOpen = false;

            // ══════════════════════════════════════════
            // OPEN GROUP
            // ══════════════════════════════════════════
            function openGroup(groupId, el) {
                document.querySelectorAll('.sg-group-item').forEach(i => i.classList.remove('active'));
                el.classList.add('active');
                activeGroupId = groupId;
                lastMsgCount = 0;
                settingsOpen = false;
                document.getElementById('settingsDropdown').classList.remove('open');
                document.getElementById('btnSettings').classList.remove('active');

                const name = el.querySelector('.sg-group-name').textContent.trim();
                const subject = el.querySelector('.sg-group-subject').textContent.trim();
                const privacy = el.dataset.privacy || 'public';
                const avatarEl = el.querySelector('.sg-group-avatar img');
                const photo = avatarEl ? avatarEl.src : null;

                activeGroupData = {
                    name,
                    subject,
                    privacy,
                    photo
                };

                // show panels
                document.getElementById('chatEmpty').style.display = 'none';
                document.getElementById('chatHeader').style.display = 'flex';
                document.getElementById('messagesBox').style.display = 'flex';
                document.getElementById('inputArea').style.display = 'block';

                // header avatar
                const hAvatar = document.getElementById('chatAvatar');
                if (photo) {
                    hAvatar.innerHTML =
                        `<img src="${escHtml(photo)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
                } else {
                    hAvatar.textContent = name.substring(0, 2).toUpperCase();
                    hAvatar.style.background = 'linear-gradient(135deg,#6c63ff,#a78bfa)';
                }

                // header name + badge
                const privBadge = privacy === 'private' ?
                    `<span class="header-privacy-badge private">🔒 Private</span>` :
                    `<span class="header-privacy-badge public">🌐 Public</span>`;
                document.getElementById('chatGroupName').innerHTML = escHtml(name) + ' ' + privBadge;
                document.getElementById('chatGroupMembers').textContent = subject;

                // sync settings dropdown banner
                syncSettingsDropdown();

                loadMessages(true);
                clearInterval(pollInterval);
                pollInterval = setInterval(() => loadMessages(false), 3000);
            }

            // Sync dropdown state to active group
            function syncSettingsDropdown() {
                const {
                    name,
                    subject,
                    photo
                } = activeGroupData;
                document.getElementById('sdGroupName').textContent = name || '—';
                document.getElementById('sdGroupSub').textContent = subject || '—';
                document.getElementById('sdRenameInput').value = name || '';

                const sdAvatar = document.getElementById('sdAvatar');
                if (photo) {
                    sdAvatar.innerHTML = `<img src="${escHtml(photo)}" alt="">`;
                } else {
                    sdAvatar.textContent = (name || '??').substring(0, 2).toUpperCase();
                    sdAvatar.style.background = 'linear-gradient(135deg,#6c63ff,#a78bfa)';
                }

                loadGroupMembers();
            }

            // ══════════════════════════════════════════
            // SETTINGS DROPDOWN TOGGLE
            // ══════════════════════════════════════════
            function toggleSettings(e) {
                e.stopPropagation();
                settingsOpen = !settingsOpen;
                document.getElementById('settingsDropdown').classList.toggle('open', settingsOpen);
                document.getElementById('btnSettings').classList.toggle('active', settingsOpen);
                if (settingsOpen) syncSettingsDropdown();
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (settingsOpen && !document.getElementById('settingsWrap').contains(e.target)) {
                    settingsOpen = false;
                    document.getElementById('settingsDropdown').classList.remove('open');
                    document.getElementById('btnSettings').classList.remove('active');
                }
            });

            // ══════════════════════════════════════════
            // LOAD GROUP MEMBERS (for dropdown preview)
            // ══════════════════════════════════════════
            function loadGroupMembers() {
                if (!activeGroupId) return;
                const list = document.getElementById('sdMembersList');
                list.innerHTML = '<div style="color:#4b5563;font-size:0.78rem;padding:4px 6px;">Loading…</div>';

                fetch(`/study-groups/${activeGroupId}/members`)
                    .then(r => {
                        if (!r.ok) {
                            throw new Error(`HTTP Error: ${r.status} ${r.statusText}`);
                        }
                        return r.json();
                    })
                    .then(data => {
                        console.log('Members data received:', data);
                        const members = data.members || [];
                        if (!members.length) {
                            list.innerHTML =
                                '<div style="color:#4b5563;font-size:0.78rem;padding:8px 6px;">No members found.</div>';
                            return;
                        }
                        // show all members in table format
                        let html =
                            '<div style="display: flex; flex-direction: column; gap: 0; max-height: 300px; overflow-y: auto;">';
                        members.forEach(m => {
                            html += memberRowHtml(m, 'table');
                        });
                        html += '</div>';
                        list.innerHTML = html;
                    })
                    .catch(err => {
                        console.error('Failed to load members:', err);
                        list.innerHTML =
                            `<div style="color:#f87171;font-size:0.78rem;padding:8px 6px;">Error: ${err.message}</div>`;
                    });
            }

            function memberRowHtml(m, type) {
                const isMe = ME && String(m.id) === String(ME);
                const initials = (m.first_name || m.name || '?').charAt(0).toUpperCase();
                const avatar = m.photo ?
                    `<img src="${escHtml(m.photo)}" alt="">` :
                    initials;
                const name = `${m.first_name || ''} ${m.last_name || ''}`.trim() || m.name || 'Member';
                const tag = isMe ? `<span class="member-tag you">You</span>` :
                    m.is_owner ? `<span class="member-tag owner">Owner</span>` : '';

                if (type === 'table') {
                    return `<div style="display: flex; align-items: center; gap: 10px; padding: 8px 6px; border-bottom: 1px solid #252830; font-size: 0.78rem;">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #6c63ff, #a78bfa); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 600; color: #fff; flex-shrink: 0; overflow: hidden;">
                            ${avatar}
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="color: #d1cfe8; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escHtml(name)}</div>
                            ${m.username ? `<div style="color: #6b7280; font-size: 0.7rem;">@${escHtml(m.username)}</div>` : ''}
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            ${tag}
                            <span style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; background: #252830; color: #6b7280; text-transform: capitalize;">${m.role || 'member'}</span>
                        </div>
                    </div>`;
                }
                if (type === 'sd') {
                    return `<div class="sd-member-row">
                        <div class="sd-member-avatar">${avatar}</div>
                        <span class="sd-member-name">${escHtml(name)}</span>
                        ${tag}
                    </div>`;
                }
                return `<div class="member-full-row">
                    <div class="member-full-avatar">${avatar}</div>
                    <div style="flex:1;min-width:0;">
                        <div class="member-full-name">${escHtml(name)}</div>
                        ${m.username ? `<div class="member-full-username">@${escHtml(m.username)}</div>` : ''}
                    </div>
                    ${tag}
                </div>`;
            }

            // ══════════════════════════════════════════
            // MEMBERS FULL MODAL
            // ══════════════════════════════════════════
            function openMembersModal() {
                closeSettings();
                const title = `Members of "${activeGroupData.name}"`;
                document.getElementById('membersModalTitle').textContent = title;
                document.getElementById('membersModalBackdrop').classList.add('open');

                const full = document.getElementById('membersFullList');
                full.innerHTML = '<div style="color:#4b5563;font-size:0.83rem;text-align:center;padding:16px;">Loading…</div>';

                fetch(`/study-groups/${activeGroupId}/members`)
                    .then(r => {
                        if (!r.ok) {
                            throw new Error(`HTTP Error: ${r.status} ${r.statusText}`);
                        }
                        return r.json();
                    })
                    .then(data => {
                        console.log('Full members data received:', data);
                        const members = data.members || [];
                        if (!members.length) {
                            full.innerHTML =
                                '<div style="color:#4b5563;font-size:0.83rem;text-align:center;padding:16px;">No members.</div>';
                            return;
                        }
                        full.innerHTML = members.map(m => memberRowHtml(m, 'full')).join('');
                    })
                    .catch(err => {
                        console.error('Failed to load members modal:', err);
                        full.innerHTML =
                            `<div style="color:#f87171;font-size:0.83rem;text-align:center;padding:16px;">Failed to load members: ${err.message}</div>`;
                    });
            }

            function closeMembersModal() {
                document.getElementById('membersModalBackdrop').classList.remove('open');
            }

            document.getElementById('membersModalBackdrop').addEventListener('click', function(e) {
                if (e.target === this) closeMembersModal();
            });

            // ══════════════════════════════════════════
            // RENAME GROUP
            // ══════════════════════════════════════════
            function renameGroup() {
                if (!activeGroupId) return;
                const newName = document.getElementById('sdRenameInput').value.trim();
                if (!newName) {
                    alert('Please enter a group name.');
                    return;
                }

                fetch(`/study-groups/${activeGroupId}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF
                        },
                        body: JSON.stringify({
                            name: newName
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success || data.group) {
                            activeGroupData.name = newName;

                            // update sidebar item
                            const el = document.querySelector(`[data-group-id="${activeGroupId}"] .sg-group-name`);
                            if (el) el.textContent = newName;

                            // update header
                            const privacy = activeGroupData.privacy === 'private' ?
                                `<span class="header-privacy-badge private">🔒 Private</span>` :
                                `<span class="header-privacy-badge public">🌐 Public</span>`;
                            document.getElementById('chatGroupName').innerHTML = escHtml(newName) + ' ' + privacy;

                            // update dropdown
                            document.getElementById('sdGroupName').textContent = newName;

                            // update sidebar avatar initials (if no photo)
                            const sideEl = document.querySelector(`[data-group-id="${activeGroupId}"] .sg-group-avatar`);
                            if (sideEl && !sideEl.querySelector('img')) sideEl.textContent = newName.substring(0, 2)
                                .toUpperCase();

                            alert('Group renamed successfully!');
                        } else {
                            alert(data.error || 'Failed to rename group.');
                        }
                    })
                    .catch(() => alert('Failed to rename group.'));
            }

            // ══════════════════════════════════════════
            // CHANGE GROUP PHOTO
            // ══════════════════════════════════════════
            document.getElementById('groupPhotoInput').addEventListener('change', function() {
                if (!this.files.length || !activeGroupId) return;
                const file = this.files[0];
                const form = new FormData();
                form.append('_token', CSRF);
                form.append('photo', file);

                fetch(`/study-groups/${activeGroupId}/photo`, {
                        method: 'POST',
                        body: form
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.photo_url) {
                            const url = data.photo_url;
                            activeGroupData.photo = url;

                            // update dropdown avatar
                            const sdAvatar = document.getElementById('sdAvatar');
                            sdAvatar.innerHTML = `<img src="${escHtml(url)}" alt="">`;

                            // update chat header avatar
                            const hAvatar = document.getElementById('chatAvatar');
                            hAvatar.innerHTML =
                                `<img src="${escHtml(url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;

                            // update sidebar avatar
                            const sideAvatar = document.querySelector(
                                `[data-group-id="${activeGroupId}"] .sg-group-avatar`);
                            if (sideAvatar) sideAvatar.innerHTML = `<img src="${escHtml(url)}" alt="">`;
                        } else {
                            alert(data.error || 'Failed to update photo.');
                        }
                    })
                    .catch(() => alert('Failed to upload photo.'));

                this.value = '';
            });

            // ══════════════════════════════════════════
            // LOAD MESSAGES
            // ══════════════════════════════════════════
            function loadMessages(scrollToBottom) {
                if (!activeGroupId) return;
                fetch(`/study-groups/${activeGroupId}/messages`)
                    .then(r => {
                        if (!r.ok) {
                            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                        }
                        return r.json();
                    })
                    .then(data => {
                        if (!scrollToBottom && data.messages.length === lastMsgCount) return;
                        lastMsgCount = data.messages.length;
                        renderMessages(data.messages, scrollToBottom);
                    })
                    .catch(err => {
                        console.error('Failed to load messages:', err);
                    });
            }

            function renderMessages(messages, scroll) {
                const box = document.getElementById('messagesBox');
                let html = '';
                let lastDate = '';

                messages.forEach(m => {
                    const d = new Date(m.created_at);
                    const date = d.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric'
                    });
                    if (date !== lastDate) {
                        html += `<div class="date-sep">${date}</div>`;
                        lastDate = date;
                    }

                    const isOwn = ME && String(m.user_id) === String(ME);
                    const initials = (m.sender_first || '?').charAt(0).toUpperCase();
                    const name = isOwn ? 'You' : `${m.sender_first || ''} ${m.sender_last || ''}`.trim();
                    const time = d.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    const avatar = m.sender_photo ?
                        `<img src="${escHtml(m.sender_photo)}" alt="">` :
                        initials;

                    let content = '';

                    // Check if this is a calendar share message
                    let isCalendarShare = false;
                    let calendarShareData = null;
                    try {
                        if (m.message && m.message.startsWith('{')) {
                            const parsed = JSON.parse(m.message);
                            if (parsed.type === 'calendar_share') {
                                isCalendarShare = true;
                                calendarShareData = parsed;
                            }
                        }
                    } catch (e) {
                        // Not JSON, treat as regular message
                    }

                    if (isCalendarShare && calendarShareData) {
                        // Render calendar share notification
                        content += `
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 12px; border-radius: 8px; color: white; margin: 4px 0;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                    <span style="font-size: 1.2rem;">📅</span>
                                    <span style="font-weight: 600;">Shared their calendar</span>
                                </div>
                                ${calendarShareData.message ? `<div style="font-size: 0.9rem; opacity: 0.9; margin-left: 28px;">"${escHtml(calendarShareData.message)}"</div>` : ''}
                            </div>
                        `;
                    } else if (m.message && m.message.trim()) {
                        content += `<div class="msg-bubble">${escHtml(m.message)}</div>`;
                    }

                    (m.attachments || []).forEach(a => {
                        if (a.type === 'image') {
                            content +=
                                `<img class="msg-image" src="${escHtml(a.url)}" alt="${escHtml(a.name)}" onclick="openLightbox('${escHtml(a.url)}')">`;
                        } else {
                            const ext = a.name.split('.').pop().toUpperCase();
                            const sz = a.size ? formatBytes(a.size) : '';
                            content += `<a class="msg-file" href="${escHtml(a.url)}" target="_blank" download>
                                <span class="msg-file-icon">${fileIcon(ext)}</span>
                                <span class="msg-file-name">${escHtml(a.name)}</span>
                                <span class="msg-file-size">${sz}</span>
                            </a>`;
                        }
                    });

                    html += `
                    <div class="msg-row ${isOwn ? 'own' : ''}">
                        <div class="msg-avatar">${avatar}</div>
                        <div class="msg-body">
                            <div class="msg-sender">${escHtml(name)}</div>
                            ${content}
                            <div class="msg-time">${time}</div>
                        </div>
                    </div>`;
                });

                box.innerHTML = html;
                if (scroll) box.scrollTop = box.scrollHeight;
            }

            // ══════════════════════════════════════════
            // SEND MESSAGE
            // ══════════════════════════════════════════
            function sendMessage() {
                if (!activeGroupId) return;
                const text = document.getElementById('msgInput').value.trim();
                if (!text && pendingFiles.length === 0) return;

                const btn = document.getElementById('sendBtn');
                btn.disabled = true;

                const formData = new FormData();
                formData.append('_token', CSRF);
                formData.append('message', text);
                pendingFiles.forEach(pf => formData.append('attachments[]', pf.file));

                console.log('Sending message to:', `/study-groups/${activeGroupId}/messages`);
                console.log('Message text:', text);
                console.log('Files count:', pendingFiles.length);

                fetch(`/study-groups/${activeGroupId}/messages`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => {
                        console.log('Response status:', r.status, r.statusText);
                        if (!r.ok) {
                            return r.json().then(data => {
                                throw new Error(`HTTP ${r.status}: ${data.error || data.message || r.statusText}`);
                            }).catch(() => {
                                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                            });
                        }
                        return r.json();
                    })
                    .then(data => {
                        console.log('Message sent successfully:', data);
                        document.getElementById('msgInput').value = '';
                        pendingFiles = [];
                        document.getElementById('uploadPreview').innerHTML = '';
                        loadMessages(true);
                    })
                    .catch(err => {
                        console.error('Failed to send message:', err);
                        alert(`Failed to send message:\n${err.message}`);
                    })
                    .finally(() => {
                        btn.disabled = false;
                    });
            }

            function handleEnter(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            }

            // ══════════════════════════════════════════
            // FILE PICKERS
            // ══════════════════════════════════════════
            document.getElementById('imageInput').addEventListener('change', function() {
                addFiles(this.files, 'image');
                this.value = '';
            });
            document.getElementById('fileInput').addEventListener('change', function() {
                addFiles(this.files, 'file');
                this.value = '';
            });

            function addFiles(fileList, type) {
                Array.from(fileList).forEach(f => {
                    const id = Math.random().toString(36).slice(2);
                    const pf = {
                        file: f,
                        type,
                        id
                    };
                    if (type === 'image') pf.previewUrl = URL.createObjectURL(f);
                    pendingFiles.push(pf);
                    const div = document.createElement('div');
                    div.className = 'sg-preview-item';
                    div.id = 'prev_' + id;
                    if (type === 'image') {
                        div.innerHTML =
                            `<img src="${pf.previewUrl}" alt=""><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
                    } else {
                        const ext = f.name.split('.').pop().toUpperCase();
                        div.innerHTML =
                            `<span>${fileIcon(ext)}</span><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
                    }
                    document.getElementById('uploadPreview').appendChild(div);
                });
            }

            function removeFile(id) {
                pendingFiles = pendingFiles.filter(f => f.id !== id);
                const el = document.getElementById('prev_' + id);
                if (el) el.remove();
            }

            // ══════════════════════════════════════════
            // CREATE GROUP MODAL
            // ══════════════════════════════════════════
            document.getElementById('btnOpenModal').onclick = () => {
                document.getElementById('groupNameInput').value = '';
                document.getElementById('groupSubjectInput').value = '';
                document.querySelectorAll('#friendList input[type="checkbox"]').forEach(c => c.checked = false);
                // reset privacy to public
                document.getElementById('radioPublic').checked = true;
                document.getElementById('optPublic').classList.add('selected-public');
                document.getElementById('optPublic').classList.remove('selected-private');
                document.getElementById('optPrivate').classList.remove('selected-public', 'selected-private');
                // Hide friends field for public (default)
                document.getElementById('friendsField').style.display = 'none';
                document.getElementById('publicDesc').style.display = 'block';
                document.getElementById('privateDesc').style.display = 'none';

                loadFriendsForModal();
                document.getElementById('modalBackdrop').classList.add('open');
            };

            // Privacy toggle styling and friends field visibility
            document.querySelectorAll('input[name="groupPrivacy"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('optPublic').classList.remove('selected-public',
                        'selected-private');
                    document.getElementById('optPrivate').classList.remove('selected-public',
                        'selected-private');

                    const isPrivate = this.value === '1';

                    // Show/hide friends field based on privacy
                    if (isPrivate) {
                        document.getElementById('optPrivate').classList.add('selected-private');
                        document.getElementById('friendsField').style.display = 'block';
                        document.getElementById('publicDesc').style.display = 'none';
                        document.getElementById('privateDesc').style.display = 'block';
                    } else {
                        document.getElementById('optPublic').classList.add('selected-public');
                        document.getElementById('friendsField').style.display = 'none';
                        document.getElementById('publicDesc').style.display = 'block';
                        document.getElementById('privateDesc').style.display = 'none';
                    }
                });
            });

            function loadFriendsForModal() {
                const friendListDiv = document.getElementById('friendList');
                friendListDiv.innerHTML = '<div style="padding:10px;color:#9ca3af;">Loading friends…</div>';

                fetch('/study-groups/api/friends')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.friends || !data.friends.length) {
                            friendListDiv.innerHTML = '<div class="no-msg">No friends to add yet.</div>';
                            return;
                        }
                        friendListDiv.innerHTML = data.friends.map(f => {
                            const photoHtml = f.photo ?
                                `<img src="${escHtml(f.photo)}" alt="">` :
                                f.initials;
                            return `<label class="friend-item" for="friend_${f.id}">
                                <input type="checkbox" id="friend_${f.id}" value="${f.id}">
                                <div class="friend-avatar">${photoHtml}</div>
                                <div>
                                    <div class="friend-name">${escHtml(f.name)}</div>
                                    <div class="friend-username">@${escHtml(f.username || 'friend')}</div>
                                </div>
                            </label>`;
                        }).join('');
                    })
                    .catch(() => {
                        friendListDiv.innerHTML = '<div class="no-msg">Failed to load friends.</div>';
                    });
            }

            function closeModal() {
                document.getElementById('modalBackdrop').classList.remove('open');
            }

            document.getElementById('modalBackdrop').addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });

            function createGroup() {
                const name = document.getElementById('groupNameInput').value.trim();
                const subject = document.getElementById('groupSubjectInput').value.trim();
                const isPrivate = document.querySelector('input[name="groupPrivacy"]:checked').value;
                if (!name) {
                    alert('Please enter a group name.');
                    return;
                }

                // Only collect members for private groups
                const members = isPrivate === '1' ? Array.from(
                    document.querySelectorAll('#friendList input[type="checkbox"]:checked')
                ).map(c => c.value) : [];

                fetch('/study-groups', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF
                        },
                        body: JSON.stringify({
                            name,
                            subject,
                            members,
                            is_private: isPrivate
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.group) {
                            closeModal();
                            const noMsg = document.querySelector('#groupList .no-msg');
                            if (noMsg) noMsg.remove();

                            const privacy = isPrivate === '1' ? 'private' : 'public';
                            const badgeHtml = privacy === 'private' ?
                                `<span class="privacy-badge private">🔒 Private</span>` :
                                `<span class="privacy-badge public">🌐 Public</span>`;

                            const div = document.createElement('div');
                            div.className = 'sg-group-item';
                            div.dataset.groupId = data.group.id;
                            div.dataset.privacy = privacy;
                            div.setAttribute('onclick', `openGroup('${data.group.id}', this)`);
                            div.innerHTML = `
                            <div class="sg-group-avatar">${data.group.name.substring(0,2).toUpperCase()}</div>
                            <div class="sg-group-item-wrap">
                                <div class="sg-group-name">${escHtml(data.group.name)}</div>
                                <div class="sg-group-meta">
                                    <span class="sg-group-subject">${escHtml(data.group.subject || 'General')} · 1 member</span>
                                    ${badgeHtml}
                                </div>
                            </div>`;
                            document.getElementById('groupList').prepend(div);
                            openGroup(data.group.id, div);
                        } else {
                            alert(data.error || 'Failed to create group.');
                        }
                    })
                    .catch(() => alert('Failed to create group.'));
            }

            // ══════════════════════════════════════════
            // DELETE GROUP
            // ══════════════════════════════════════════
            function deleteGroup() {
                if (!activeGroupId) return;
                if (!confirm('Are you sure you want to delete this group? This cannot be undone.')) return;

                fetch(`/study-groups/${activeGroupId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': CSRF
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const groupEl = document.querySelector(`[data-group-id="${activeGroupId}"]`);
                            if (groupEl) groupEl.remove();

                            activeGroupId = null;
                            clearInterval(pollInterval);
                            closeSettings();

                            document.getElementById('chatEmpty').style.display = 'flex';
                            document.getElementById('chatHeader').style.display = 'none';
                            document.getElementById('messagesBox').style.display = 'none';
                            document.getElementById('inputArea').style.display = 'none';

                            if (!document.querySelectorAll('.sg-group-item').length) {
                                const noMsg = document.createElement('div');
                                noMsg.className = 'no-msg';
                                noMsg.style.marginTop = '24px';
                                noMsg.innerHTML = 'No groups yet. Hit <strong>+</strong> to create one!';
                                document.getElementById('groupList').appendChild(noMsg);
                            }
                        } else {
                            alert(data.error || 'Failed to delete group.');
                        }
                    })
                    .catch(() => alert('Failed to delete group.'));
            }

            function closeSettings() {
                settingsOpen = false;
                document.getElementById('settingsDropdown').classList.remove('open');
                document.getElementById('btnSettings').classList.remove('active');
            }

            // ══════════════════════════════════════════
            // LIGHTBOX
            // ══════════════════════════════════════════
            function openLightbox(url) {
                document.getElementById('lightboxImg').src = url;
                document.getElementById('lightbox').classList.add('open');
            }

            function closeLightbox() {
                document.getElementById('lightbox').classList.remove('open');
            }

            // ══════════════════════════════════════════
            // HELPERS
            // ══════════════════════════════════════════
            function escHtml(str) {
                return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                    '&quot;');
            }

            function formatBytes(b) {
                if (b < 1024) return b + ' B';
                if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
                return (b / 1048576).toFixed(1) + ' MB';
            }

            function fileIcon(ext) {
                const m = {
                    PDF: '📄',
                    DOC: '📝',
                    DOCX: '📝',
                    XLS: '📊',
                    XLSX: '📊',
                    PPT: '📑',
                    PPTX: '📑',
                    ZIP: '🗜️',
                    RAR: '🗜️',
                    MP4: '🎥',
                    MP3: '🎵'
                };
                return m[ext] || '📁';
            }

            function switchGroupView(view) {
                const messagesBox = document.getElementById('messagesBox');
                const calendarsBox = document.getElementById('calendarsBox');
                const tabMessages = document.getElementById('tabMessages');
                const tabCalendars = document.getElementById('tabCalendars');
                const inputArea = document.getElementById('inputArea');

                if (view === 'messages') {
                    messagesBox.style.display = 'flex';
                    calendarsBox.style.display = 'none';
                    inputArea.style.display = 'flex';
                    tabMessages.style.background = '#161820';
                    tabMessages.style.color = '#6c63ff';
                    tabMessages.style.borderBottomColor = '#6c63ff';
                    tabCalendars.style.background = '#0f1117';
                    tabCalendars.style.color = '#6b7280';
                    tabCalendars.style.borderBottomColor = 'transparent';
                } else {
                    messagesBox.style.display = 'none';
                    calendarsBox.style.display = 'flex';
                    inputArea.style.display = 'none';
                    tabMessages.style.background = '#0f1117';
                    tabMessages.style.color = '#6b7280';
                    tabMessages.style.borderBottomColor = 'transparent';
                    tabCalendars.style.background = '#161820';
                    tabCalendars.style.color = '#6c63ff';
                    tabCalendars.style.borderBottomColor = '#6c63ff';
                    loadGroupSharedCalendars();
                }
            }

            function loadGroupSharedCalendars() {
                if (!activeGroupId) return;

                fetch(`/study-groups/${activeGroupId}/shared-calendars`)
                    .then(r => r.json())
                    .then(data => {
                        const calendarsBox = document.getElementById('calendarsBox');
                        if (!data.calendars || data.calendars.length === 0) {
                            calendarsBox.innerHTML = `<div style="text-align: center; color: #6b7280; padding: 60px 20px;">
                                <div style="font-size: 2rem; margin-bottom: 10px;">📅</div>
                                <p>No shared calendars yet</p>
                            </div>`;
                            return;
                        }

                        let html = '<div style="display: grid; gap: 16px;">';
                        data.calendars.forEach(cal => {
                            const eventCount = (cal.events || []).length;
                            html += `
                                <div style="padding: 16px; background: #161820; border: 1px solid #252830; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                        ${cal.owner_photo ? `<img src="${cal.owner_photo}" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">` : `<div style="width: 48px; height: 48px; border-radius: 50%; background: #6c63ff; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">${cal.owner_name.charAt(0).toUpperCase()}</div>`}
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #e8e6e1;">${cal.owner_name}'s Calendar</div>
                                            <div style="font-size: 0.85rem; color: #6b7280;">${eventCount} event${eventCount !== 1 ? 's' : ''}</div>
                                        </div>
                                    </div>
                                    <div style="max-height: 300px; overflow-y: auto; background: #0f1117; border-radius: 6px; padding: 8px;">
                                        ${eventCount === 0 ?
                                            `<div style="text-align: center; color: #4b5563; padding: 20px;">No events shared</div>` :
                                            `<div style="display: flex; flex-direction: column; gap: 6px;">
                                                                        ${(cal.events || []).map(e => `
                                                    <div style="padding: 8px; background: #161820; border-left: 3px solid #6c63ff; border-radius: 4px;">
                                                        <div style="font-size: 0.9rem; color: #e8e6e1; font-weight: 500;">${e.title}</div>
                                                        <div style="font-size: 0.8rem; color: #6b7280; margin-top: 2px;">${e.event_date} ${e.event_time || ''}</div>
                                                    </div>
                                                `).join('')}
                                                                    </div>`
                                        }
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        calendarsBox.innerHTML = html;
                    })
                    .catch(err => {
                        console.error('Error loading group shared calendars:', err);
                        document.getElementById('calendarsBox').innerHTML = `<div style="text-align: center; color: #ef4444; padding: 40px 20px;">
                            <p>Failed to load shared calendars</p>
                        </div>`;
                    });
            }

            // Auto-open first group
            @if ($groups->isNotEmpty())
                window.addEventListener('DOMContentLoaded', () => {
                    const first = document.querySelector('.sg-group-item');
                    if (first) openGroup('{{ $groups->first()->id }}', first);
                });
            @endif
        </script>

        {{-- ── NOTIFICATIONS ── --}}
        <script>
            const SB_URL = '{{ config('services.supabase.url') }}';
            const SB_ANON = '{{ config('services.supabase.anon_key') }}';
            const SB_SVC = '{{ config('services.supabase.service_key') }}';
            const UID = '{{ session('user_id') }}';
        </script>
        <script src="{{ asset('js/notifications.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => initNotifications());
        </script>
        @include('layouts.admin_bar')
    </body>

    </html>
