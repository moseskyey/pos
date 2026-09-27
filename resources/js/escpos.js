/**
 * Direct thermal printing (ESC/POS) from the browser.
 *
 * Chrome / Edge only: WebUSB for USB printers, WebSerial for serial/COM and
 * many Bluetooth-serial printers. The server renders the bytes
 * (/receipts/{sale}/escpos); this just delivers them. The chosen printer is
 * remembered by the browser after the first "Connect printer".
 */
const KEY = 'dp-printer-kind';

class ThermalPrinter {
    constructor() {
        this.port = null;
        this.device = null;
    }

    supported() {
        return 'usb' in navigator || 'serial' in navigator;
    }

    kind() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    async connect(kind) {
        if (kind === 'serial') {
            this.port = await navigator.serial.requestPort();
            this.device = null;
        } else {
            this.device = await navigator.usb.requestDevice({ filters: [] });
            this.port = null;
        }
        try { localStorage.setItem(KEY, kind); } catch (e) { /* ignore */ }
    }

    async resolve() {
        if (this.port || this.device) return true;
        const kind = this.kind();
        if (kind === 'serial' && navigator.serial) this.port = (await navigator.serial.getPorts())[0] || null;
        if (kind === 'usb' && navigator.usb) this.device = (await navigator.usb.getDevices())[0] || null;
        return !!(this.port || this.device);
    }

    async connected() {
        try { return await this.resolve(); } catch (e) { return false; }
    }

    async write(bytes) {
        if (!bytes.length) return;
        if (!(await this.resolve())) throw new Error('No printer connected');

        if (this.port) {
            if (!this.port.writable) await this.port.open({ baudRate: 9600 });
            const writer = this.port.writable.getWriter();
            try { await writer.write(bytes); } finally { writer.releaseLock(); }
            return;
        }

        const d = this.device;
        if (!d.opened) await d.open();
        if (d.configuration === null) await d.selectConfiguration(1);
        const iface = d.configuration.interfaces.find(i => i.alternate.endpoints.some(e => e.direction === 'out' && e.type === 'bulk'));
        if (!iface) throw new Error('Printer has no bulk OUT endpoint');
        if (!iface.claimed) await d.claimInterface(iface.interfaceNumber);
        const endpoint = iface.alternate.endpoints.find(e => e.direction === 'out' && e.type === 'bulk');
        for (let i = 0; i < bytes.length; i += 4096) {
            await d.transferOut(endpoint.endpointNumber, bytes.slice(i, i + 4096));
        }
    }

    async fetchBytes(url, options = {}) {
        const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/octet-stream', ...(options.headers || {}) }, ...options });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return new Uint8Array(await res.arrayBuffer());
    }

    async print(url) {
        await this.write(await this.fetchBytes(url));
    }

    async openDrawer(url) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        await this.write(await this.fetchBytes(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }));
    }
}

window.dpPrinter = new ThermalPrinter();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('printerStatus', ({ drawerUrl }) => ({
        ready: false,
        supported: window.dpPrinter.supported(),
        async init() { this.ready = await window.dpPrinter.connected(); },
        async connect(kind) {
            try {
                await window.dpPrinter.connect(kind);
                this.ready = true;
                window.dpToast?.('Printer connected', 'success');
            } catch (e) {
                if (e.name !== 'NotFoundError') window.dpToast?.(e.message, 'error');
            }
        },
        async drawer() {
            try { await window.dpPrinter.openDrawer(drawerUrl); } catch (e) { window.dpToast?.(e.message, 'error'); }
        },
    }));
});
