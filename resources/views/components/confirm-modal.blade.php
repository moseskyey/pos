<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true" data-default-title="{{ __('Are you sure?') }}" data-default-button="{{ __('Yes, continue') }}">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="empty-icon mx-auto mb-3 bg-danger-soft text-danger rounded-circle d-grid" style="width:64px;height:64px;place-items:center;font-size:1.75rem">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h5 class="fw-semibold mb-2" data-confirm-title>{{ __('Are you sure?') }}</h5>
                <p class="text-body-secondary small mb-4" data-confirm-message></p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-danger flex-fill" data-confirm-accept>{{ __('Yes, continue') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
