/**
 * Camera barcode scanning (Settings → Features → Camera scanning).
 *
 * <button data-camera-scan="#input-id" data-camera-enter> opens the shared
 * camera dialog (#cameraScanModal). The first code read is typed into the
 * target input (firing `input` for wire:model) and, with data-camera-enter,
 * an Enter key press, so the page's normal scan handler runs; with
 * data-camera-submit the input's form is submitted instead.
 *
 * Uses the browser's BarcodeDetector (Chrome on Android, ChromeOS, macOS) and
 * falls back to ZXing, loaded only when needed (iPhone Safari, Firefox).
 */
const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code', 'data_matrix'];

let target = null;
let pressEnter = false;
let submitForm = false;
let stream = null;
let stopZxing = null;
let running = false;

function modal() {
    return document.getElementById('cameraScanModal');
}

function setStatus(text, error = false) {
    const el = modal()?.querySelector('[data-camera-status]');
    if (el) {
        el.textContent = text;
        el.classList.toggle('text-danger', error);
    }
}

function resolveTarget(button) {
    const spec = button.dataset.cameraScan;
    if (!spec || spec === 'prev') {
        return button.closest('.input-group')?.querySelector('input') || button.previousElementSibling;
    }

    return document.querySelector(spec);
}

function deliver(code) {
    if (!target || !code) {
        return;
    }
    target.value = code;
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
    if (pressEnter) {
        // Let wire:model send the value first, then trigger the Enter handler.
        setTimeout(() => target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', bubbles: true })), 60);
    }
    if (submitForm && target.form) {
        target.form.requestSubmit();
    }
    window.dpBeep && window.dpBeep(true);
    window.bootstrap.Modal.getOrCreateInstance(modal()).hide();
}

async function startNative(video) {
    const supported = await window.BarcodeDetector.getSupportedFormats();
    const detector = new window.BarcodeDetector({ formats: FORMATS.filter((f) => supported.includes(f)) });
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    video.srcObject = stream;
    await video.play();
    running = true;
    const tick = async () => {
        if (!running) {
            return;
        }
        try {
            const codes = await detector.detect(video);
            if (codes.length) {
                running = false;
                deliver(codes[0].rawValue);

                return;
            }
        } catch (e) { /* frame not ready */ }
        requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

async function startZxing(video) {
    const { BrowserMultiFormatReader } = await import('@zxing/browser');
    const reader = new BrowserMultiFormatReader();
    const controls = await reader.decodeFromConstraints({ video: { facingMode: { ideal: 'environment' } }, audio: false }, video, (result) => {
        if (result && running) {
            running = false;
            deliver(result.getText());
        }
    });
    running = true;
    stopZxing = () => controls.stop();
}

async function start() {
    const video = modal().querySelector('video');
    setStatus(modal().dataset.textStarting);
    if (!navigator.mediaDevices?.getUserMedia) {
        setStatus(modal().dataset.textUnsupported, true);

        return;
    }
    try {
        if ('BarcodeDetector' in window) {
            await startNative(video);
        } else {
            await startZxing(video);
        }
        setStatus(modal().dataset.textAim);
    } catch (e) {
        setStatus(e && e.name === 'NotAllowedError' ? modal().dataset.textDenied : modal().dataset.textUnsupported, true);
    }
}

function stop() {
    running = false;
    if (stopZxing) {
        stopZxing();
        stopZxing = null;
    }
    if (stream) {
        stream.getTracks().forEach((t) => t.stop());
        stream = null;
    }
    const video = modal()?.querySelector('video');
    if (video) {
        video.srcObject = null;
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-camera-scan]');
    if (!button || !modal()) {
        return;
    }
    event.preventDefault();
    target = resolveTarget(button);
    pressEnter = button.hasAttribute('data-camera-enter');
    submitForm = button.hasAttribute('data-camera-submit');
    window.bootstrap.Modal.getOrCreateInstance(modal()).show();
});

document.addEventListener('shown.bs.modal', (event) => {
    if (event.target === modal()) {
        start();
    }
});

document.addEventListener('hidden.bs.modal', (event) => {
    if (event.target === modal()) {
        stop();
        target?.focus();
    }
});
