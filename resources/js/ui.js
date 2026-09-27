import * as bootstrap from 'bootstrap';

/**
 * Global UI behaviour: theme, sidebar, toasts, confirm dialogs, form helpers.
 * Alpine is bundled with Livewire; we register stores/components on alpine:init.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

// ---------------------------------------------------------------- Theme ----
window.dpSetTheme = (theme, persist = true) => {
    document.documentElement.setAttribute('data-bs-theme', theme);
    try { localStorage.setItem('dp-theme', theme); } catch (e) { /* ignore */ }
    if (persist && document.body?.dataset.authenticated === '1') {
        fetch('/preferences/theme', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ theme }),
        }).catch(() => {});
    }
    document.dispatchEvent(new CustomEvent('dp:theme', { detail: theme }));
};

window.dpToggleTheme = () => {
    const current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    window.dpSetTheme(current === 'dark' ? 'light' : 'dark');
};

// --------------------------------------------------------------- Toasts ----
window.dpToast = (message, type = 'success', timeout = 4000) => {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toast-stack';
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-octagon-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const el = document.createElement('div');
    el.className = `dp-toast toast-${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><div class="flex-grow-1 small fw-medium"></div><button type="button" class="btn-close btn-sm" aria-label="Close"></button>`;
    el.querySelector('.flex-grow-1').textContent = message;
    el.querySelector('.btn-close').addEventListener('click', () => el.remove());
    stack.appendChild(el);
    if (timeout) setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, timeout);
};

// ------------------------------------------------------------ Beep sound ---
let audioCtx;
window.dpBeep = (ok = true) => {
    if (document.body?.dataset.scanSound === '0') return;
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'square';
        osc.frequency.value = ok ? 1250 : 220;
        gain.gain.value = 0.05;
        osc.connect(gain); gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + (ok ? 0.08 : 0.25));
    } catch (e) { /* ignore */ }
};

// ----------------------------------------------------------- Alpine ------
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('sidebar', {
        collapsed: (() => { try { return localStorage.getItem('dp-sidebar') === 'collapsed'; } catch (e) { return false; } })(),
        open: false,
        toggle() {
            if (window.innerWidth < 992) { this.open = !this.open; return; }
            this.collapsed = !this.collapsed;
            try { localStorage.setItem('dp-sidebar', this.collapsed ? 'collapsed' : 'expanded'); } catch (e) { /* ignore */ }
        },
    });

    // Unsaved-changes guard for forms: <form x-data="dirtyForm">
    Alpine.data('dirtyForm', () => ({
        dirty: false,
        submitting: false,
        init() {
            this.$el.addEventListener('input', () => { this.dirty = true; });
            this.$el.addEventListener('change', () => { this.dirty = true; });
            this.$el.addEventListener('submit', () => {
                this.dirty = false;
                this.submitting = true;
                this.$el.querySelectorAll('button[type=submit]').forEach(b => { b.disabled = true; });
            });
            this._handler = (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } };
            window.addEventListener('beforeunload', this._handler);
        },
        destroy() { window.removeEventListener('beforeunload', this._handler); },
    }));

    // Drag & drop file upload with preview
    Alpine.data('fileUpload', (initial = null) => ({
        preview: initial,
        fileName: null,
        dragover: false,
        pick() { this.$refs.input.click(); },
        drop(e) {
            this.dragover = false;
            if (e.dataTransfer.files.length) {
                this.$refs.input.files = e.dataTransfer.files;
                this.change();
            }
        },
        change() {
            const file = this.$refs.input.files[0];
            if (!file) return;
            this.fileName = file.name;
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (ev) => { this.preview = ev.target.result; };
                reader.readAsDataURL(file);
            } else {
                this.preview = null;
            }
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));

    // Global search (Ctrl+K)
    Alpine.data('globalSearch', () => ({
        q: '', results: [], open: false, loading: false, timer: null,
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.$refs.input.focus(); }
            });
        },
        search() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
            this.timer = setTimeout(async () => {
                this.loading = true;
                try {
                    const res = await fetch(`/search?q=${encodeURIComponent(this.q)}`, { headers: { 'Accept': 'application/json' } });
                    this.results = await res.json();
                    this.open = true;
                } catch (e) { this.results = []; }
                this.loading = false;
            }, 250);
        },
    }));

    // Denomination counter for shift close
    Alpine.data('denominations', (denoms, initial = {}) => ({
        counts: Object.fromEntries(denoms.map(d => [d, initial[d] || 0])),
        coins: initial.coins || 0,
        get total() { return Object.entries(this.counts).reduce((s, [d, c]) => s + Number(d) * Number(c || 0), 0) + Number(this.coins || 0); },
    }));
});

// ------------------------------------------------------ Livewire hooks ----
document.addEventListener('livewire:init', () => {
    window.Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        window.dpToast(data.message, data.type || 'success');
    });
});

// --------------------------------------------------------- DOM helpers ----
function initWidgets(root = document) {
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
    root.querySelectorAll('select[data-tom-select]:not(.tomselected)').forEach(el => {
        const opts = { plugins: el.multiple ? ['remove_button'] : [], allowEmptyOption: true, maxOptions: 500 };
        if (el.dataset.create) opts.create = true;
        new window.TomSelect(el, opts);
    });
}

// Confirm dialogs for forms with data-confirm
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;
    e.preventDefault();
    const modalEl = document.getElementById('confirmModal');
    if (!modalEl) { if (window.confirm(form.dataset.confirm)) { form.dataset.confirmed = '1'; form.requestSubmit(); } return; }
    modalEl.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    modalEl.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || modalEl.dataset.defaultTitle;
    const btn = modalEl.querySelector('[data-confirm-accept]');
    btn.textContent = form.dataset.confirmButton || modalEl.dataset.defaultButton;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const accept = () => { form.dataset.confirmed = '1'; modal.hide(); form.requestSubmit(); };
    btn.onclick = accept;
    modal.show();
}, true);

document.addEventListener('DOMContentLoaded', () => {
    initWidgets();
    document.querySelectorAll('[data-flash]').forEach(el => window.dpToast(el.dataset.flash, el.dataset.type));
});
document.addEventListener('livewire:navigated', () => initWidgets());
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', ({ el }) => { if (el instanceof HTMLElement) initWidgets(el); });
});

window.dpInitWidgets = initWidgets;
