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
});
