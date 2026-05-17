const SB_URL = window.SB_URL || '';
const SB_ANON = window.SB_ANON || '';
const SB_SVC = window.SB_SVC || '';
const UID = window.UID || '';

document.addEventListener('DOMContentLoaded', () => {
    if (typeof initNotifications === 'function') {
        initNotifications();
    }
});