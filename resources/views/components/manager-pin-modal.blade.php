{{--
  Livewire-driven manager override modal. The host component uses the
  App\Livewire\Concerns\RequiresApproval trait and toggles $approval.
--}}
@props(['approval' => null])
@if ($approval)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(15,23,42,.55)"
         x-data="{ pin: '' }" x-init="$nextTick(() => $refs.pin.focus())" @keydown.escape.window="$wire.cancelApproval()">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <form wire:submit="submitApproval">
                    <div class="modal-body p-4 text-center">
                        <div class="rounded-circle bg-warning-soft text-warning d-grid mx-auto mb-3" style="width:60px;height:60px;place-items:center;font-size:1.6rem">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h5 class="fw-semibold mb-1">{{ __('Manager approval') }}</h5>
                        <p class="small text-body-secondary mb-3">{{ $approval['message'] ?? __('This action needs a manager PIN.') }}</p>
                        <input type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="6" x-ref="pin"
                               wire:model="approvalPin" x-model="pin"
                               class="form-control form-control-lg text-center fw-bold @error('pin') is-invalid @enderror" style="letter-spacing:.5em"
                               placeholder="••••" aria-label="{{ __('Manager PIN') }}">
                        @error('pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if (! empty($approval['needs_reason']))
                            <input type="text" wire:model="approvalReason" class="form-control mt-2 @error('reason') is-invalid @enderror" placeholder="{{ __('Reason') }}">
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                        <div class="pin-pad mt-3">
                            @foreach ([1,2,3,4,5,6,7,8,9] as $d)
                                <button type="button" class="btn btn-light" @click="pin = (pin + '{{ $d }}').slice(0,6); $wire.set('approvalPin', pin, false)">{{ $d }}</button>
                            @endforeach
                            <button type="button" class="btn btn-light" @click="pin = pin.slice(0,-1); $wire.set('approvalPin', pin, false)"><i class="bi bi-backspace"></i></button>
                            <button type="button" class="btn btn-light" @click="pin = (pin + '0').slice(0,6); $wire.set('approvalPin', pin, false)">0</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i></button>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center border-0 pt-0">
                        <button type="button" class="btn btn-link text-body-secondary" wire:click="cancelApproval">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
