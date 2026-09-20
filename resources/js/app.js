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

    // ── Notification dropdown ─────────────────────────────
    const notifDropdown = document.getElementById('notification-dropdown');
    const notifTrigger = document.getElementById('notification-trigger');
    const notifMenu = document.getElementById('notification-menu');

    if (notifTrigger && notifMenu) {
        notifTrigger.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = notifMenu.classList.toggle('open');
            notifTrigger.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', e => {
            if (notifDropdown && !notifDropdown.contains(e.target)) {
                notifMenu.classList.remove('open');
                notifTrigger.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && notifMenu.classList.contains('open')) {
                notifMenu.classList.remove('open');
                notifTrigger.setAttribute('aria-expanded', 'false');
                notifTrigger.focus();
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

    // ── Admin write actions: modals + AJAX ─────────────────
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    window.Orca = window.Orca || {};

    window.Orca.toast = (message, type = 'success') => {
        let wrap = document.querySelector('.toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'toast-wrap';
            document.body.appendChild(wrap);
        }
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        wrap.appendChild(toast);
        setTimeout(() => toast.remove(), 4200);
    };

    window.Orca.openModal = (id, context = {}) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        if (context.actionUrl) {
            modal.querySelector('form[data-ajax-form]')?.setAttribute('action', context.actionUrl);
        }
        if (context.prefill) {
            let data;
            try { data = JSON.parse(context.prefill); } catch { data = {}; }
            Object.entries(data).forEach(([name, value]) => {
                const el = modal.querySelector(`[name="${name}"]`);
                if (el && value !== null && value !== undefined) {
                    el.value = value;
                    if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
        if (context.label) {
            let labels;
            try { labels = JSON.parse(context.label); } catch { labels = {}; }
            Object.entries(labels).forEach(([selector, value]) => {
                const el = modal.querySelector(selector);
                if (el) el.textContent = value;
            });
        }
        modal.removeAttribute('hidden');
        modal.querySelector('input, select, textarea')?.focus?.();
    };

    window.Orca.closeModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.setAttribute('hidden', '');
        modal.querySelectorAll('.om-error, .form-error-box').forEach(el => el.remove());
        modal.querySelectorAll('.field-error').forEach(el => el.remove());
        const form = modal.querySelector('form[data-ajax-form]');
        if (form) { form.dataset.sessionUrl = ''; form.dataset.resetOnSuccess = ''; }
    };

    document.addEventListener('click', e => {
        const opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            window.Orca.openModal(opener.dataset.modalOpen, {
                actionUrl: opener.dataset.actionUrl,
                prefill: opener.dataset.prefill,
                label: opener.dataset.orcaLabel || opener.dataset.label,
            });
        }
        const closer = e.target.closest('[data-modal-close]');
        if (closer) {
            window.Orca.closeModal(closer.dataset.modalClose || closer.closest('.modal-backdrop')?.id);
        }
        if (e.target.classList?.contains('modal-backdrop')) {
            window.Orca.closeModal(e.target.id);
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            const open = document.querySelector('.modal-backdrop:not([hidden])');
            if (open) window.Orca.closeModal(open.id);
        }
    });

    const fieldError = (form, name, message) => {
        form.querySelectorAll(`[name="${name}"]`).forEach(el => {
            const holder = el.closest('.om-field');
            if (holder) {
                holder.querySelectorAll('.om-error').forEach(x => x.remove());
                const err = document.createElement('span');
                err.className = 'om-error';
                err.textContent = message;
                holder.appendChild(err);
                el.addEventListener('input', () => err.remove(), { once: true });
            }
        });
    };

    document.addEventListener('submit', e => {
        const form = e.target.closest('form[data-ajax-form]');
        if (!form) return;

        e.preventDefault();
        if (form.dataset.busy) return;
        form.dataset.busy = '1';
        const submitBtn = form.querySelector('[data-submit]');
        if (submitBtn) submitBtn.setAttribute('data-submitting', '');

        form.querySelectorAll('.om-error').forEach(x => x.remove());
        const errBox = form.querySelector('.form-error-box');
        if (errBox) errBox.setAttribute('hidden', '');

        const url = form.action;
        const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() };
        const body = new FormData(form);

        fetch(url, { method: 'POST', headers, body })
            .then(async res => {
                const contentType = res.headers.get('content-type') || '';
                const payload = contentType.includes('application/json') ? await res.json() : null;
                if (!res.ok) {
                    if (res.status === 422 && payload?.errors) {
                        Object.entries(payload.errors).forEach(([field, messages]) => {
                            fieldError(form, field, Array.isArray(messages) ? messages[0] : String(messages));
                        });
                        throw new Error(payload.message || 'تحقق من الحقول المظللة.');
                    }
                    throw new Error(payload?.message || `حدث خطأ (${res.status}).`);
                }
                Orca.toast(payload?.message || 'تم الحفظ بنجاح.');
                const open = form.closest('.modal-backdrop');
                if (open) window.Orca.closeModal(open.id);
                setTimeout(() => window.location.reload(), 600);
            })
            .catch(err => {
                Orca.toast(err.message || 'تعذر تنفيذ العملية.', 'error');
            })
            .finally(() => {
                delete form.dataset.busy;
                if (submitBtn) submitBtn.removeAttribute('data-submitting');
            });
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-post]');
        if (!btn) return;
        e.preventDefault();
        const message = btn.dataset.confirm;
        if (message && !window.confirm(message)) return;

        const submit = () => {
            if (btn.dataset.busy) return;
            btn.dataset.busy = '1';
            btn.setAttribute('data-submitting', '');
            fetch(btn.dataset.url, {
                method: btn.dataset.method || 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            })
                .then(res => res.json().catch(() => null))
                .then(payload => {
                    if (!payload || payload.success === false) {
                        throw new Error(payload?.message || 'تعذر تنفيذ العملية.');
                    }
                    Orca.toast(payload.message || 'تمت العملية بنجاح.');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch(err => Orca.toast(err.message || 'تعذر تنفيذ العملية.', 'error'))
                .finally(() => {
                    delete btn.dataset.busy;
                    btn.removeAttribute('data-submitting');
                });
        };

        submit();
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-fill-modal]');
        if (!btn) return;
        const target = document.getElementById(btn.dataset.fillModal);
        if (!target) return;
        if (btn.dataset.actionUrl) {
            target.querySelector('form[data-ajax-form]')?.setAttribute('action', btn.dataset.actionUrl);
        }
        let data;
        try { data = JSON.parse(btn.dataset.edit || '{}'); } catch { data = {}; }
        Object.entries(data).forEach(([name, value]) => {
            const el = target.querySelector(`[name="${name}"]`);
            if (el) {
                if (el.type === 'checkbox') el.checked = Boolean(value);
                else el.value = value ?? '';
                if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        if (btn.dataset.capitalValues) {
            let capitals;
            try { capitals = JSON.parse(btn.dataset.capitalValues || '[]'); } catch { capitals = []; }
            const map = {};
            capitals.forEach(c => { map[c.participant_id] = c.capital; });
            target.querySelectorAll('[data-capital-for]').forEach(el => {
                el.value = map[el.dataset.capitalFor] !== undefined ? map[el.dataset.capitalFor] : '';
            });
            const first = target.querySelector('[data-capital-for]');
            if (first) first.dispatchEvent(new Event('input', { bubbles: true }));
        }
        target.removeAttribute('hidden');
        target.querySelector('input, select, textarea')?.focus?.();
    });

    document.addEventListener('input', e => {
        if (!e.target.classList?.contains('js-capital-input')) return;
        const form = e.target.closest('form[data-ajax-form]');
        const total = form?.querySelector('[data-capital-total]');
        if (!total) return;
        let sum = 0;
        form.querySelectorAll('.js-capital-input').forEach(el => {
            const n = parseFloat(el.value);
            if (!Number.isNaN(n)) sum += n;
        });
        total.textContent = sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });

    // ── Password reveal toggle ───────────────────────────
    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-password-toggle');
        if (!btn) return;
        const input = btn.closest('.password-field')?.querySelector('input[type="password"], input[type="text"]');
        if (!input) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        btn.classList.toggle('active', reveal);
        btn.setAttribute('aria-pressed', String(reveal));
        btn.setAttribute('aria-label', reveal ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
    });

    // ── Searchable select (filter-by-name) ───────────────
    const bindSearchableSelects = (root = document) => {
        root.querySelectorAll('.searchable-select[data-searchable]').forEach(wrap => {
            if (wrap.dataset.searchableReady) return;
            wrap.dataset.searchableReady = '1';

            const input = wrap.querySelector('.searchable-input');
            const select = wrap.querySelector('.js-searchable-select');
            const list = wrap.querySelector('.searchable-list');
            if (!input || !select || !list) return;

            const refreshLabel = () => {
                const opt = select.options[select.selectedIndex];
                if (opt && opt.value !== '') input.value = opt.textContent.trim();
                else input.value = '';
            };

            const render = (filter) => {
                const q = filter.trim().toLowerCase();
                list.innerHTML = '';
                let shown = 0;
                Array.from(select.options).forEach(opt => {
                    const label = opt.textContent.trim();
                    if (q && !label.toLowerCase().includes(q)) return;
                    const li = document.createElement('li');
                    li.dataset.value = opt.value;
                    li.dataset.label = label;
                    li.textContent = label;
                    if (String(opt.value) === String(select.value)) li.classList.add('selected');
                    li.addEventListener('mousedown', ev => ev.preventDefault());
                    li.addEventListener('click', () => {
                        select.value = li.dataset.value;
                        input.value = li.dataset.value !== '' ? li.dataset.label : '';
                        input.classList.remove('open');
                        render('');
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        if (input.value !== '') input.blur();
                    });
                    list.appendChild(li);
                    shown++;
                });
                if (!shown) {
                    const li = document.createElement('li');
                    li.className = 'searchable-empty';
                    li.textContent = 'لا توجد نتائج';
                    list.appendChild(li);
                }
            };

            input.addEventListener('focus', () => { render(input.value); input.classList.add('open'); });
            input.addEventListener('input', () => render(input.value));
            input.addEventListener('keydown', e => {
                if (e.key === 'Escape') { input.classList.remove('open'); refreshLabel(); }
            });
            document.addEventListener('click', e => {
                if (!wrap.contains(e.target)) {
                    input.classList.remove('open');
                    const cur = select.selectedOptions[0] ? select.selectedOptions[0].textContent.trim() : '';
                    if (select.value === '' || input.value.trim() !== cur) refreshLabel();
                }
            });

            select.addEventListener('change', refreshLabel);
            refreshLabel();
        });
    };

    bindSearchableSelects();
    window.Orca.rebindSearchable = (root) => bindSearchableSelects(root || document);

});
