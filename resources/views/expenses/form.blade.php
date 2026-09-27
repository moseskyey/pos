@php $editing = $expense->exists; @endphp
<x-layouts.app :title="$editing ? __('Edit expense') : __('Record expense')" :breadcrumbs="[__('Expenses') => route('expenses.index'), $editing ? $expense->number : __('New')]">
    <x-page-header :title="$editing ? $expense->number : __('Record expense')" />
    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Expense')" icon="bi-credit-card-2-back">
                    <div class="row">
                        <div class="col-md-6"><x-select name="expense_category_id" :label="__('Category')" :options="$categories" :value="$expense->expense_category_id" :placeholder="__('Select')" required /></div>
                        <div class="col-md-6"><x-input name="expense_date" type="date" :label="__('Date')" :value="$expense->expense_date?->toDateString()" required /></div>
                        <div class="col-md-6"><x-input name="amount" type="number" min="0" step="0.01" :label="__('Amount')" :value="$expense->amount" prefix="TSh" required :disabled="$expense->paid_from_drawer" /></div>
                        <div class="col-md-6"><x-select name="payment_method" :label="__('Paid with')" :options="$methods" :value="$expense->payment_method" /></div>
                        <div class="col-md-6"><x-input name="payee" :label="__('Paid to')" :value="$expense->payee" :placeholder="__('e.g. TANESCO, landlord')" /></div>
                        <div class="col-md-6"><x-input name="reference" :label="__('Reference')" :value="$expense->reference" /></div>
                        <div class="col-12"><x-textarea name="description" :label="__('Description')" :value="$expense->description" rows="2" /></div>
                        @unless ($editing)
                            <div class="col-12"><x-toggle name="paid_from_drawer" :label="__('Paid in cash from my shift drawer')" :help="__('Deducts from expected cash when you close your shift.')" class="mb-0" /></div>
                        @endunless
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Receipt / attachment')">
                    <x-file-upload name="attachment" accept="image/*,application/pdf" :current="$expense->attachment_path && ! str_ends_with($expense->attachment_path, '.pdf') ? $expense->attachmentUrl() : null" :help="__('Photo or PDF, up to 4MB')" class="mb-0" />
                    @if ($expense->attachment_path)<a href="{{ $expense->attachmentUrl() }}" target="_blank" class="small"><i class="bi bi-paperclip"></i> {{ __('Current file') }}</a>@endif
                </x-card>
                @if ($editing)
                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="mt-3" data-confirm="{{ __('Delete this expense?') }}" id="deleteExpense">@csrf @method('DELETE')</form>
                    <button class="btn btn-soft-danger w-100 mt-3" form="deleteExpense"><i class="bi bi-trash"></i> {{ __('Delete expense') }}</button>
                @endif
            </div>
        </div>
        <x-form-actions :cancel="route('expenses.index')" :save-new="! $editing" />
    </form>
</x-layouts.app>
