{{-- Shared camera dialog used by every [data-camera-scan] button (resources/js/camera-scan.js). --}}
@if (feature('camera_scan'))
    <div class="modal fade" id="cameraScanModal" tabindex="-1" aria-labelledby="cameraScanTitle" aria-hidden="true"
         data-text-starting="{{ __('Starting camera…') }}" data-text-aim="{{ __('Point the camera at a barcode.') }}"
         data-text-denied="{{ __('Camera access was blocked. Allow the camera for this site in your browser settings.') }}"
         data-text-unsupported="{{ __('This browser cannot use the camera for scanning.') }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content overflow-hidden">
                <div class="modal-header">
                    <h5 class="modal-title" id="cameraScanTitle"><i class="bi bi-camera"></i> {{ __('Scan with camera') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="position-relative bg-black">
                    <video class="w-100 d-block" style="max-height:60vh;object-fit:cover" playsinline muted></video>
                    <div class="position-absolute top-50 start-50 translate-middle border border-2 border-light rounded-3 opacity-75" style="width:70%;height:35%" aria-hidden="true"></div>
                </div>
                <div class="modal-body py-2"><p class="small text-body-secondary mb-0" data-camera-status aria-live="polite"></p></div>
            </div>
        </div>
    </div>
@endif
