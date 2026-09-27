/**
 * POS terminal client behaviour: keyboard shortcuts, focus management,
 * localStorage cart backup (offline resilience) and receipt printing.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('posTerminal', ({ userId }) => ({
        storageKey: `dp-pos-cart-${userId}`,
        restorable: false,

        init() {
            this.restorable = this.saved().length > 0 && Object.keys(this.$wire.cart || {}).length === 0;
            this.$wire.$watch('cart', (cart) => this.persist(cart));
            this.focusSearch();
        },

        saved() {
            try { return JSON.parse(localStorage.getItem(this.storageKey) || '[]'); } catch (e) { return []; }
        },

        persist(cart) {
            try {
                const lines = Object.values(cart || {}).map(l => ({ product_id: l.product_id, product_unit_id: l.product_unit_id, qty: l.qty }));
                if (lines.length) {
                    localStorage.setItem(this.storageKey, JSON.stringify(lines));
                    this.restorable = false;
                } else {
                    localStorage.removeItem(this.storageKey);
                }
            } catch (e) { /* storage unavailable */ }
        },

        restore() {
            const lines = this.saved();
            this.restorable = false;
            if (lines.length) this.$wire.restoreCart(lines);
        },

        focusSearch() {
            this.$nextTick(() => this.$refs.search?.focus());
        },

        modalOpen() {
            return !!document.querySelector('.modal.show');
        },

        onKey(e) {
            if (document.body.classList.contains('pos-locked')) return;
            const tag = (e.target.tagName || '').toLowerCase();
            const typing = ['input', 'textarea', 'select'].includes(tag);
            const key = e.key;

            if (key === 'F2') { e.preventDefault(); this.focusSearch(); return; }
            if (key === 'F4') { e.preventDefault(); this.$wire.set('modal', 'customer'); return; }
            if (key === 'F6') { e.preventDefault(); this.$wire.openDiscount(); return; }
            if (key === 'F8') { e.preventDefault(); if (Object.keys(this.$wire.cart).length) this.$wire.set('modal', 'hold'); return; }
            if (key === 'F9') { e.preventDefault(); this.$wire.set('modal', 'held'); return; }
            if (key === 'F10' || (key === 'Enter' && (e.ctrlKey || e.metaKey))) {
                e.preventDefault();
                if (this.$wire.modal === 'payment') this.$wire.checkout(); else this.$wire.openPayment();
                return;
            }
            if (key === 'Escape') {
                if (this.$wire.modal) { e.preventDefault(); this.$wire.set('modal', null); this.focusSearch(); }
                return;
            }
            if (key === 'Enter' && this.$wire.completed && !this.modalOpen()) {
                e.preventDefault(); this.$wire.newSale(); return;
            }
            if (typing || this.modalOpen()) return;

            const selected = this.$wire.selectedLine;
            if (key === 'Delete' && selected) { e.preventDefault(); this.$wire.removeSelected(); return; }
            if ((key === '+' || key === '=') && selected) { e.preventDefault(); this.$wire.increment(selected, 1); return; }
            if ((key === '-' || key === '_') && selected) { e.preventDefault(); this.$wire.increment(selected, -1); return; }
            if (key === '?') { e.preventDefault(); this.$wire.set('modal', 'help'); return; }

            // Any printable key while nothing is focused goes to the search box (scanner friendly).
            if (key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) this.focusSearch();
        },

        afterSale(detail) {
            try { localStorage.removeItem(this.storageKey); } catch (e) { /* ignore */ }
            const data = Array.isArray(detail) ? detail[0] : detail;
            if (data && data.autoPrint) this.printReceipt(data.receipt);
            this.$nextTick(() => this.$refs.newSale?.focus());
        },

        printReceipt(url) {
            const frame = this.$refs.printFrame;
            if (!frame) { window.open(url, '_blank'); return; }
            frame.onload = () => { try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { window.open(url, '_blank'); } };
            frame.src = url + (url.includes('?') ? '&' : '?') + 'embed=1';
        },
    }));

    /**
     * Idle lock: after N minutes without input the terminal is covered and the
     * cashier must re-enter their PIN. The lock is also flagged in the server
     * session so a page reload does not bypass it.
     */
    window.Alpine.data('idleLock', ({ minutes, locked, lockUrl, verifyUrl }) => ({
        locked,
        pin: '',
        error: '',
        busy: false,
        timer: null,

        init() {
            if (!minutes || minutes <= 0) return;
            ['mousemove', 'mousedown', 'keydown', 'touchstart', 'wheel'].forEach(ev =>
                window.addEventListener(ev, () => this.reset(), { passive: true }));
            this.locked ? this.show() : this.reset();
        },

        reset() {
            if (this.locked) return;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.lock(), minutes * 60000);
        },

        lock() {
            this.locked = true;
            this.show();
            this.post(lockUrl, {}).catch(() => {});
        },

        show() {
            document.body.classList.add('pos-locked');
            this.$nextTick(() => this.$refs.pin?.focus());
        },

        post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify(body),
            });
        },

        async unlock() {
            if (this.busy || this.pin.length < 4) return;
            this.busy = true; this.error = '';
            try {
                const res = await this.post(verifyUrl, { pin: this.pin });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    this.locked = false; this.pin = '';
                    document.body.classList.remove('pos-locked');
                    this.reset();
                    window.dispatchEvent(new CustomEvent('focus-search'));
                } else {
                    this.error = data.message || 'Incorrect PIN.';
                    this.pin = '';
                    if (res.status === 423 || res.status === 419) setTimeout(() => window.location.reload(), 1500);
                }
            } finally {
                this.busy = false;
                this.$nextTick(() => this.$refs.pin?.focus());
            }
        },
    }));
});
