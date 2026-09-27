/**
 * Offline selling.
 *
 * - The product catalogue and a queue of offline sales live in localStorage,
 *   per user. Nothing in the queue is ever dropped until the server confirms it.
 * - Sales are sent to /pos/offline/sync with their client UUID as the
 *   idempotency key, so a retry never records a sale twice.
 * - A service worker (public/sw.js) keeps the offline till page and the built
 *   assets available without a connection.
 */
const qKey = (userId) => `dp-offline-queue-${userId}`;
const cKey = (userId) => `dp-offline-catalog-${userId}`;

const store = {
    get(key, fallback) {
        try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch (e) { return fallback; }
    },
    set(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch (e) { return false; }
    },
};

export const offlineQueue = {
    all: (userId) => store.get(qKey(userId), []),
    save: (userId, items) => store.set(qKey(userId), items),
    push(userId, sale) {
        const items = this.all(userId);
        items.push(sale);
        if (!this.save(userId, items)) throw new Error('This device has no space left to store the sale.');
    },
};

const cents = (v) => Math.round(Number(v || 0) * 100);

/** Send queued sales. Returns {synced, errors, pending}. */
export async function syncOffline(userId, { pingUrl, syncUrl }) {
    const items = offlineQueue.all(userId);
    if (!items.length || !navigator.onLine) return { synced: 0, errors: [], pending: items.length };

    const ping = await fetch(pingUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!ping.ok) throw new Error(ping.status === 401 ? 'Sign in again to sync offline sales.' : 'Server not reachable.');
    const { csrf, user_id: serverUser } = await ping.json();
    if (String(serverUser) !== String(userId)) return { synced: 0, errors: [], pending: items.length };

    let synced = 0;
    const errors = [];
    for (let i = 0; i < items.length; i += 20) {
        const batch = items.slice(i, i + 20);
        const res = await fetch(syncUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ sales: batch.map(({ ref, lines, ...s }) => ({ ...s, lines: lines.map(({ name, total, ...l }) => l) })) }),
        });
        if (!res.ok) throw new Error('Sync failed (HTTP ' + res.status + ').');
        const { results } = await res.json();
        const byId = Object.fromEntries(results.map((r) => [r.client_id, r]));
        const remaining = offlineQueue.all(userId).filter((s) => {
            const r = byId[s.client_id];
            if (r?.status === 'synced') { synced++; return false; }
            if (r?.status === 'error') { s.error = r.message; errors.push({ client_id: s.client_id, ref: s.ref, message: r.message }); }
            return true;
        });
        offlineQueue.save(userId, remaining);
    }

    return { synced, errors, pending: offlineQueue.all(userId).length };
}

function registerServiceWorker() {
    const sw = document.querySelector('meta[name="dp-sw"]')?.content;
    if (sw && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register(sw).catch(() => { /* offline mode unavailable */ });
    }
}

/** Background sync from any signed-in page. */
function autoSync() {
    const userId = document.querySelector('meta[name="dp-user"]')?.content;
    const pingUrl = document.querySelector('meta[name="dp-offline-ping"]')?.content;
    const syncUrl = document.querySelector('meta[name="dp-offline-sync"]')?.content;
    if (!userId || !pingUrl || !syncUrl) return;
    const run = () => {
        if (!offlineQueue.all(userId).length) return;
        syncOffline(userId, { pingUrl, syncUrl })
            .then(({ synced, errors }) => {
                if (synced) window.dpToast?.(`${synced} offline sale(s) synced.`, 'success');
                if (errors.length) window.dpToast?.(`${errors.length} offline sale(s) need attention. Open the offline till.`, 'error', 8000);
            })
            .catch(() => { /* retried on the next reconnect */ });
    };
    window.addEventListener('online', run);
    run();
}

document.addEventListener('DOMContentLoaded', () => {
    registerServiceWorker();
    autoSync();
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('offlineStatus', ({ userId, offlineUrl }) => ({
        online: navigator.onLine,
        pending: offlineQueue.all(userId).length,
        offlineUrl,
        init() {
            window.addEventListener('online', () => { this.online = true; setTimeout(() => { this.pending = offlineQueue.all(userId).length; }, 3000); });
            window.addEventListener('offline', () => { this.online = false; });
        },
    }));

    window.Alpine.data('offlineTill', (config) => ({
        ...config,
        online: navigator.onLine,
        catalog: store.get(cKey(config.userId), null),
        queue: offlineQueue.all(config.userId),
        errors: [],
        term: '',
        cart: [],
        method: 'cash',
        tendered: null,
        usd: null,
        reference: '',
        error: '',
        done: null,
        syncing: false,

        async init() {
            window.addEventListener('online', () => { this.online = true; this.refreshCatalog(); this.sync(); });
            window.addEventListener('offline', () => { this.online = false; });
            this.errors = this.queue.filter((s) => s.error).map((s) => ({ client_id: s.client_id, ref: s.ref, message: s.error }));
            if (this.online) {
                await this.refreshCatalog();
                this.sync();
            }
            this.$nextTick(() => this.$refs.search?.focus());
        },

        async refreshCatalog() {
            try {
                const res = await fetch(this.catalogUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.catalog = data;
                store.set(cKey(this.userId), data);
            } catch (e) { /* keep the cached catalogue */ }
        },

        async sync() {
            if (this.syncing || !this.online) return;
            this.syncing = true;
            try {
                const { synced, errors } = await syncOffline(this.userId, { pingUrl: this.pingUrl, syncUrl: this.syncUrl });
                this.errors = errors;
                if (synced) window.dpToast?.(this.i18n.synced.replace(':count', synced), 'success');
            } catch (e) {
                window.dpToast?.(e.message, 'error');
            } finally {
                this.queue = offlineQueue.all(this.userId);
                this.syncing = false;
            }
        },

        get results() {
            if (!this.catalog) return [];
            const t = this.term.trim().toLowerCase();
            const list = this.catalog.products;
            if (!t) return list.slice(0, 30);
            return list.filter((p) => p.name.toLowerCase().includes(t) || (p.sku || '').toLowerCase() === t || p.barcodes.includes(this.term.trim())).slice(0, 30);
        },

        scan() {
            const code = this.term.trim();
            if (!code || !this.catalog) return;
            const exact = this.catalog.products.find((p) => p.barcodes.includes(code) || (p.sku || '').toLowerCase() === code.toLowerCase());
            const match = exact || (this.results.length === 1 ? this.results[0] : null);
            if (match) { this.add(match); this.term = ''; window.dpBeep?.(true); } else { window.dpBeep?.(false); }
        },

        add(p) {
            const line = this.cart.find((l) => l.id === p.id);
            if (line) line.qty = Number(line.qty) + 1; else this.cart.push({ ...p, qty: 1 });
            this.error = '';
            this.$nextTick(() => this.$refs.search?.focus());
        },

        changeQty(i, delta) {
            const l = this.cart[i];
            l.qty = Math.max(0, Number(l.qty) + delta);
            if (l.qty === 0) this.cart.splice(i, 1);
        },

        /** Same rule as the server's PriceResolver (no customer offline). */
        unitPrice(l) {
            if (l.wholesale_price && Number(l.wholesale_price) > 0 && l.wholesale_min_qty && Number(l.wholesale_min_qty) > 0 && Number(l.qty) >= Number(l.wholesale_min_qty)) {
                return l.wholesale_price;
            }
            return l.price;
        },

        /** Integer cents, half-up, like App\Support\Money. */
        lineTotal(l) { return Math.round(Number(l.qty) * cents(this.unitPrice(l))); },

        total() {
            const s = this.catalog?.settings || {};
            let total = 0;
            for (const l of this.cart) {
                const line = this.lineTotal(l);
                total += line;
                if (!s.prices_include_vat) total += Math.round(line * Number(l.tax_rate || 0) / 100);
            }
            const step = (s.rounding || 0) * 100;
            return step > 0 ? Math.round(total / step) * step : total;
        },

        currentMethod() { return (this.catalog?.methods || []).find((m) => m.value === this.method); },

        paidCents() {
            const m = this.currentMethod();
            if (m?.foreign) return Math.round(Number(this.usd || 0) * cents(this.catalog.settings.usd_rate));
            if (this.tendered === null || this.tendered === '') return this.total();
            return cents(this.tendered);
        },

        change() {
            const m = this.currentMethod();
            return m && (m.value === 'cash' || m.foreign) ? Math.max(this.paidCents() - this.total(), 0) : 0;
        },

        fmt(value, symbol = false) {
            const n = Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
            return symbol ? `${this.catalog?.settings.symbol || 'TSh'} ${n}` : n;
        },

        complete() {
            this.error = '';
            const lines = this.cart.filter((l) => Number(l.qty) > 0);
            if (!lines.length) return;
            const m = this.currentMethod();
            if (!m) return;
            const total = this.total();
            const paid = this.paidCents();
            if (paid < total) { this.error = this.i18n.short; return; }
            if (m.reference && !this.reference.trim()) { this.error = this.i18n.reference; return; }

            const clientId = crypto.randomUUID ? crypto.randomUUID() : ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16));
            const payment = { method: m.value, amount: (m.value === 'cash' ? paid : Math.min(paid, total)) / 100, reference: this.reference.trim() || null };
            if (m.foreign) payment.foreign_amount = Number(this.usd || 0);
            const sale = {
                client_id: clientId,
                ref: 'OFF-' + clientId.slice(0, 8).toUpperCase(),
                sold_at: new Date().toISOString(),
                shift_id: this.catalog.shift_id,
                lines: lines.map((l) => ({ product_id: l.id, qty: Number(l.qty), unit_price: this.unitPrice(l), name: l.name, total: this.lineTotal(l) })),
                payments: [payment],
            };
            try {
                offlineQueue.push(this.userId, sale);
            } catch (e) {
                this.error = e.message;
                return;
            }
            // Keep the local stock figures roughly right until the next catalogue download.
            for (const l of lines) {
                const p = this.catalog.products.find((x) => x.id === l.id);
                if (p) p.stock = String(Number(p.stock) - Number(l.qty));
            }
            store.set(cKey(this.userId), this.catalog);
            this.queue = offlineQueue.all(this.userId);
            this.done = { ...sale, total, change: this.change() };
            if (this.online) this.sync();
        },

        newSale() {
            this.cart = [];
            this.tendered = null;
            this.usd = null;
            this.reference = '';
            this.method = 'cash';
            this.done = null;
            this.$nextTick(() => this.$refs.search?.focus());
        },
    }));
});
