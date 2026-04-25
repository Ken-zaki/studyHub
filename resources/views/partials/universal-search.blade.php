<style>
    .universal-search-shell {
        position: fixed;
        top: 18px;
        left: 50%;
        transform: translateX(-50%);
        width: min(760px, calc(100vw - 160px));
        z-index: 1100;
        pointer-events: none;
    }

    .universal-search-with-topbar .universal-search-shell {
        top: 74px;
    }

    .universal-search-no-topbar .main-content {
        padding-top: 92px !important;
    }

    .universal-search-bar {
        pointer-events: auto;
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        padding: 12px 16px;
        backdrop-filter: blur(12px);
    }

    .universal-search-icon {
        width: 20px;
        height: 20px;
        color: var(--text-light);
        flex-shrink: 0;
    }

    .universal-search-input {
        width: 100%;
        border: 0;
        outline: none;
        background: transparent;
        font-family: 'DM Sans', sans-serif;
        font-size: 15px;
        color: var(--text-primary);
    }

    .universal-search-input::placeholder {
        color: var(--text-light);
    }

    .universal-search-kbd {
        flex-shrink: 0;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg-main);
        color: var(--text-secondary);
    }

    .universal-search-panel {
        pointer-events: auto;
        margin-top: 10px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        display: none;
        max-height: min(62vh, 560px);
    }

    .universal-search-panel.is-open {
        display: block;
    }

    .universal-search-group {
        padding: 14px 14px 8px;
        border-top: 1px solid var(--border);
    }

    .universal-search-group:first-child {
        border-top: 0;
    }

    .universal-search-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-light);
        margin-bottom: 10px;
    }

    .universal-search-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 10px 12px;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: background 0.2s ease;
    }

    .universal-search-item:hover {
        background: var(--bg-main);
    }

    .universal-search-item-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .universal-search-avatar {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .universal-search-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .universal-search-text {
        min-width: 0;
    }

    .universal-search-name {
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .universal-search-meta {
        font-size: 12px;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .universal-search-pill {
        flex-shrink: 0;
        font-size: 11px;
        font-weight: 700;
        color: var(--primary);
        background: rgba(26, 95, 122, 0.08);
        border-radius: 999px;
        padding: 5px 10px;
    }

    .universal-search-empty {
        padding: 18px 14px;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .universal-search-hint {
        padding: 10px 14px 14px;
        font-size: 12px;
        color: var(--text-light);
        border-top: 1px solid var(--border);
    }

    .universal-search-highlight {
        outline: 2px solid rgba(26, 95, 122, 0.18);
        outline-offset: 2px;
        border-radius: 12px;
    }
</style>

<div class="universal-search-shell" data-universal-search-root data-search-endpoint="{{ route('universal-search') }}">
    <div class="universal-search-bar">
        <svg class="universal-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="M20 20l-3.5-3.5"></path>
        </svg>
        <input
            type="search"
            class="universal-search-input"
            placeholder="Search users, resources, and tags..."
            autocomplete="off"
            spellcheck="false"
            data-universal-search-input
        >
        <span class="universal-search-kbd">Ctrl K</span>
    </div>

    <div class="universal-search-panel" data-universal-search-panel aria-live="polite">
        <div class="universal-search-group" data-universal-search-users-wrap>
            <div class="universal-search-title">Users</div>
            <div data-universal-search-users></div>
        </div>

        <div class="universal-search-group" data-universal-search-resources-wrap>
            <div class="universal-search-title">Resources</div>
            <div data-universal-search-resources></div>
        </div>

        <div class="universal-search-hint">
            Universal search will also filter any page content marked with data-universal-search or data-tags in the future.
        </div>
    </div>
</div>

<script>
(() => {
    const root = document.querySelector('[data-universal-search-root]');
    if (!root) return;

    const input = root.querySelector('[data-universal-search-input]');
    const panel = root.querySelector('[data-universal-search-panel]');
    const usersWrap = root.querySelector('[data-universal-search-users-wrap]');
    const usersList = root.querySelector('[data-universal-search-users]');
    const resourcesWrap = root.querySelector('[data-universal-search-resources-wrap]');
    const resourcesList = root.querySelector('[data-universal-search-resources]');
    const endpoint = root.dataset.searchEndpoint;
    const messagesBaseUrl = @json(route('messages'));
    const currentUserId = @json((string) session('user_id', ''));
    const SUPABASE_URL = @json(env('SUPABASE_URL'));
    const SUPABASE_ANON_KEY = @json(env('SUPABASE_ANON_KEY'));
    let timer = null;
    let lastQuery = '';

    if (document.getElementById('topBar')) {
        document.body.classList.add('universal-search-with-topbar');
    } else {
        document.body.classList.add('universal-search-no-topbar');
    }

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const openPanel = () => panel.classList.add('is-open');
    const closePanel = () => panel.classList.remove('is-open');

    const renderEmpty = (container, message) => {
        container.innerHTML = `<div class="universal-search-empty">${escapeHtml(message)}</div>`;
    };

    const applyLocalFiltering = (query) => {
        const normalized = query.trim().toLowerCase();
        const items = document.querySelectorAll('[data-universal-search]');

        items.forEach((item) => {
            const haystack = String(item.dataset.universalSearch || item.textContent || '').toLowerCase();
            const tags = String(item.dataset.tags || '').toLowerCase();
            const matches = normalized === '' || haystack.includes(normalized) || tags.includes(normalized);

            item.style.display = matches ? '' : 'none';

            if (matches && normalized !== '') {
                item.classList.add('universal-search-highlight');
            } else {
                item.classList.remove('universal-search-highlight');
            }
        });
    };

    const renderUsers = (users) => {
        if (!users.length) {
            renderEmpty(usersList, 'No matching users found.');
            return;
        }

        usersList.innerHTML = users.map((user) => {
            const avatar = user.profile_photo_url
                ? `<img src="${escapeHtml(user.profile_photo_url)}" alt="${escapeHtml(user.name)}">`
                : escapeHtml(user.initials || 'U');

            const subtitle = user.username || user.email || 'StudyHub member';
            const href = `${messagesBaseUrl}?user=${encodeURIComponent(user.id)}&name=${encodeURIComponent(user.name || '')}`;

            return `
                <a class="universal-search-item" href="${escapeHtml(href)}">
                    <div class="universal-search-item-info">
                        <div class="universal-search-avatar">${avatar}</div>
                        <div class="universal-search-text">
                            <div class="universal-search-name">${escapeHtml(user.name || 'User')}</div>
                            <div class="universal-search-meta">${escapeHtml(subtitle)}</div>
                        </div>
                    </div>
                    <span class="universal-search-pill">Message</span>
                </a>
            `;
        }).join('');
    };

    const renderResources = (resources) => {
        if (!resources.length) {
            renderEmpty(resourcesList, 'No live resource matches yet. Future resource cards tagged with science, math, or similar tags will appear here.');
            return;
        }

        resourcesList.innerHTML = resources.map((resource) => `
            <a class="universal-search-item" href="${escapeHtml(resource.url || '#')}">
                <div class="universal-search-item-info">
                    <div class="universal-search-avatar">${escapeHtml(resource.icon || 'R')}</div>
                    <div class="universal-search-text">
                        <div class="universal-search-name">${escapeHtml(resource.title || 'Resource')}</div>
                        <div class="universal-search-meta">${escapeHtml(resource.subtitle || resource.tags || 'Resource match')}</div>
                    </div>
                </div>
                <span class="universal-search-pill">Open</span>
            </a>
        `).join('');
    };

    const ensureSupabaseClient = async () => {
        if (!SUPABASE_URL || !SUPABASE_ANON_KEY) {
            return null;
        }

        if (!window.supabase) {
            await new Promise((resolve, reject) => {
                const existing = document.querySelector('script[data-universal-supabase]');
                if (existing) {
                    existing.addEventListener('load', resolve, { once: true });
                    existing.addEventListener('error', reject, { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2';
                script.async = true;
                script.dataset.universalSupabase = '1';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        if (!window.supabase?.createClient) {
            return null;
        }

        if (!window.__universalSearchSupabaseClient) {
            window.__universalSearchSupabaseClient = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
        }

        return window.__universalSearchSupabaseClient;
    };

    const searchUsersViaSupabase = async (normalized) => {
        try {
            const client = await ensureSupabaseClient();
            if (!client) {
                return [];
            }

            const wildcard = `%${normalized}%`;
            const { data, error } = await client
                .from('profiles')
                .select('id,username,first_name,last_name,profile_photo_url,email')
                .or(`username.ilike.${wildcard},first_name.ilike.${wildcard},last_name.ilike.${wildcard},email.ilike.${wildcard}`)
                .limit(8);

            if (error || !Array.isArray(data)) {
                return [];
            }

            return data
                .filter((user) => String(user.id || '') !== String(currentUserId || ''))
                .map((user) => {
                    const firstName = String(user.first_name || '').trim();
                    const lastName = String(user.last_name || '').trim();
                    const name = `${firstName} ${lastName}`.trim() || String(user.username || user.email || 'User');
                    const parts = name.split(/\s+/).filter(Boolean);
                    const initials = `${(parts[0] || 'U').charAt(0)}${(parts[1] || '').charAt(0)}`.toUpperCase();

                    return {
                        id: String(user.id || ''),
                        name,
                        username: String(user.username || ''),
                        email: String(user.email || ''),
                        profile_photo_url: String(user.profile_photo_url || ''),
                        initials,
                    };
                });
        } catch (error) {
            console.error('Universal search Supabase fallback failed:', error);
            return [];
        }
    };

    const doSearch = async (query) => {
        const normalized = query.trim();
        lastQuery = normalized;

        applyLocalFiltering(normalized);

        if (normalized === '') {
            usersList.innerHTML = '';
            resourcesList.innerHTML = '';
            closePanel();
            return;
        }

        openPanel();
        renderEmpty(usersList, 'Searching users...');
        renderEmpty(resourcesList, 'Searching resources...');

        try {
            let users = await searchUsersViaSupabase(normalized);
            let resources = [];

            if (!users.length) {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(normalized)}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();
                users = Array.isArray(data.users) ? data.users : [];
                resources = Array.isArray(data.resources) ? data.resources : [];
            }

            if (lastQuery !== normalized) {
                return;
            }

            renderUsers(users);
            renderResources(resources);

            const hasUsers = usersList.querySelectorAll('.universal-search-item').length > 0;
            const hasResources = resourcesList.querySelectorAll('.universal-search-item').length > 0;
            usersWrap.style.display = hasUsers || normalized !== '' ? '' : 'none';
            resourcesWrap.style.display = hasResources || normalized !== '' ? '' : 'none';
            openPanel();
        } catch (error) {
            renderEmpty(usersList, 'Search temporarily unavailable.');
            renderEmpty(resourcesList, 'Search temporarily unavailable.');
            openPanel();
            console.error('Universal search failed:', error);
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => doSearch(input.value), 180);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim() !== '') {
            openPanel();
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            input.focus();
        }
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closePanel();
        }
    });
})();
</script>