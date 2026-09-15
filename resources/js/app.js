import './bootstrap';

const THEME_KEY = 'orca-theme';

function applyTheme(theme) {
    const isLight = theme === 'light';
    document.documentElement.classList.toggle('light', isLight);
    document.querySelectorAll('.theme-toggle').forEach(btn => {
        btn.textContent = isLight ? '🌙' : '☀️';
        btn.title = isLight ? 'تفعيل الوضع الداكن' : 'تفعيل الوضع الفاتح';
    });
    const cb = document.getElementById('theme-toggle-settings');
    if (cb) cb.checked = isLight;
}

function getSavedTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch { return 'dark'; }
}

function toggleTheme() {
    const next = getSavedTheme() === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(THEME_KEY, next); } catch { }
    applyTheme(next);
}

applyTheme(getSavedTheme());

document.addEventListener('DOMContentLoaded', () => {

    // ── Theme ──────────────────────────────────────────────
    document.querySelectorAll('.theme-toggle').forEach(btn =>
        btn.addEventListener('click', toggleTheme)
    );
    const settingsCb = document.getElementById('theme-toggle-settings');
    if (settingsCb) settingsCb.addEventListener('change', toggleTheme);

    // ── Profile Dropdown ───────────────────────────────────
    const profileDropdown = document.getElementById('profile-dropdown');
    const profileTrigger = document.getElementById('profile-trigger');
    const profileMenu = document.getElementById('dropdown-menu');

    if (profileTrigger && profileMenu) {
        profileTrigger.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = profileMenu.classList.toggle('open');
            profileTrigger.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', e => {
            if (profileDropdown && !profileDropdown.contains(e.target)) {
                profileMenu.classList.remove('open');
                profileTrigger.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && profileMenu.classList.contains('open')) {
                profileMenu.classList.remove('open');
                profileTrigger.setAttribute('aria-expanded', 'false');
                profileTrigger.focus();
            }
        });
    }

    // ── Sidebar (mobile + desktop collapse) ──────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.querySelector('[data-sidebar]');

    function setSidebar(open) {
        document.body.classList.remove('sidebar-open', 'sidebar-closed');
        document.body.classList.add(open ? 'sidebar-open' : 'sidebar-closed');
        sidebarToggle?.setAttribute('aria-expanded', String(open));
        sidebarToggle?.setAttribute('aria-label', open ? 'إغلاق القائمة' : 'فتح القائمة');
    }

    if (sidebarToggle) {
        const defaultOpen = window.innerWidth > 768;
        document.body.classList.add(defaultOpen ? 'sidebar-open' : 'sidebar-closed');
        sidebarToggle.setAttribute('aria-expanded', String(defaultOpen));
        sidebarToggle.setAttribute('aria-label', defaultOpen ? 'إغلاق القائمة' : 'فتح القائمة');

        sidebarToggle.addEventListener('click', () => {
            const isOpen = document.body.classList.contains('sidebar-open');
            setSidebar(!isOpen);
        });
        sidebarBackdrop?.addEventListener('click', () => setSidebar(false));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') setSidebar(false);
        });
        if (sidebar) {
            new ResizeObserver(() => {
                document.body.classList.remove('sidebar-open', 'sidebar-closed');
            }).observe(document.documentElement);
        }
    }

    // ── Settings tabs ──────────────────────────────────────
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            if (!target) return;
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-pane').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('pane-' + target)?.classList.add('active');
        });
    });

    // ── Chart tooltips ─────────────────────────────────────
    document.querySelectorAll('.bar[title]').forEach(bar => {
        let tip = null;
        bar.addEventListener('mouseenter', () => {
            tip = document.createElement('div');
            tip.className = '__chart-tip';
            tip.textContent = bar.title;
            document.body.appendChild(tip);
        });
        bar.addEventListener('mousemove', e => {
            if (tip) { tip.style.left = `${e.clientX + 12}px`; tip.style.top = `${e.clientY - 35}px`; }
        });
        bar.addEventListener('mouseleave', () => { tip?.remove(); tip = null; });
    });

});
