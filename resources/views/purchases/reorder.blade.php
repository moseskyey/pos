<x-layouts.app :title="__('Reorder suggestions')" :breadcrumbs="[__('Purchases'), __('Reorder')]">
    <x-page-header :title="__('Reorder suggestions')" :subtitle="$branch ? __('Products at or below reorder level at :b, grouped by last supplier.', ['b' => $branch->name]) : __('Select a branch in the navbar.')" />
    @forelse ($groups as $group)
        <form method="POST" action="{{ route('reorder.store') }}" class="mb-4">
            @csrf
            <x-card :title="$group['supplier'] ?? __('No previous supplier')" icon="bi-building" :flush="true">
                <x-slot:actions>
                    <select name="supplier_id" class="form-select form-select-sm w-auto" required aria-label="{{ __('Supplier') }}">
                        <option value="">{{ __('Choose supplier') }}</option>
                        @foreach ($suppliers as $id => $name)<option value="{{ $id }}" @selected($id === $group['supplier_id'])>{{ $name }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-cart-plus"></i> {{ __('Create PO') }}</button>
                </x-slot:actions>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0 table-stack">
                    <thead><tr><th style="width:36px"></th><th>{{ __('Product') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Reorder at') }}</th><th style="width:120px">{{ __('Order qty') }}</th><th style="width:130px">{{ __('Unit cost') }}</th></tr></thead>
                    <tbody>
                    @foreach ($group['items'] as $i => $row)
                        <tr>
                            <td data-label=""><input type="hidden" name="items[{{ $i }}][selected]" value="0"><input type="checkbox" class="form-check-input" name="items[{{ $i }}][selected]" value="1" checked></td>
                            <td data-label="{{ __('Product') }}" class="fw-semibold">{{ $row['product']->name }}<input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $row['product']->id }}"></td>
                            <td data-label="{{ __('On hand') }}" class="text-end {{ $row['on_hand'] <= 0 ? 'text-danger fw-semibold' : 'text-warning' }}">{{ qty($row['on_hand']) }} {{ $row['product']->unit?->short_name }}</td>
                            <td data-label="{{ __('Reorder at') }}" class="text-end">{{ qty($row['product']->reorder_level) }}</td>
                            <td data-label="{{ __('Order qty') }}"><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="items[{{ $i }}][quantity]" value="{{ $row['suggested'] }}"></td>
                            <td data-label="{{ __('Unit cost') }}"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{ $i }}][unit_cost]" value="{{ (float) $row['unit_cost'] }}"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </x-card>
        </form>
    @empty
        <div class="card"><x-empty-state icon="bi-emoji-smile" :title="__('Nothing to reorder')" :message="__('All products are above their reorder level.')" /></div>
    @endforelse
</x-layouts.app>
