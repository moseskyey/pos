{{-- "Email" button + modal that sends a sale's invoice / quotation PDF to the customer. --}}
@props(['sale'])
@if (feature('email_documents') && ! in_array($sale->status->value, ['held', 'converted'], true))
    @php
        $modalId = 'emailDoc'.$sale->id;
        $quote = $sale->status->value === 'quotation';
    @endphp
    <button type="button" {{ $attributes->merge(['class' => 'btn btn-outline-primary']) }} data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
        <i class="bi bi-envelope"></i> {{ __('Email') }}
    </button>
    @push('modals')
        <x-modal :id="$modalId" :title="$quote ? __('Email quotation') : __('Email invoice')">
            <form method="POST" action="{{ route('receipts.email', $sale) }}" id="{{ $modalId }}Form">@csrf
                <x-input name="email" type="email" :label="__('Customer email')" :value="old('email', $sale->customer?->email)" required />
                <x-textarea name="message" :label="__('Message (optional)')" rows="3" class="mb-0" />
                <p class="small text-body-secondary mt-2 mb-0"><i class="bi bi-paperclip"></i> {{ __(':doc :n is attached as a PDF.', ['doc' => $quote ? __('Quotation') : __('Invoice'), 'n' => $sale->number]) }}</p>
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" form="{{ $modalId }}Form" class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Send email') }}</button>
            </x-slot:footer>
        </x-modal>
    @endpush
@endif
