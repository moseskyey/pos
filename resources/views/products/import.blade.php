<x-layouts.app :title="__('Import products')" :breadcrumbs="[__('Products') => route('products.index'), __('Import')]">
    <x-page-header :title="__('Import products')" :subtitle="__('Create or update products in bulk from an Excel file. Existing SKUs are updated.')">
        <a href="{{ route('products.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-down"></i> {{ __('Download template') }}</a>
    </x-page-header>

    @if (! $preview)
        <div class="row g-4">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('products.import.upload') }}" enctype="multipart/form-data" x-data="{ loading: false }" @submit="loading = true">
                    @csrf
                    <x-card :title="__('1. Upload file')" icon="bi-upload">
                        <x-file-upload name="file" accept=".xlsx,.xls,.csv" :help="__('XLSX, XLS or CSV up to 10MB')" />
                        <button class="btn btn-primary" :disabled="loading"><span class="spinner-border" x-show="loading" x-cloak></span> {{ __('Upload & preview') }}</button>
                    </x-card>
                </form>
            </div>
            <div class="col-lg-5">
                <x-card :title="__('Template columns')" icon="bi-table">
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach (\App\Services\ProductImportService::COLUMNS as $label)
                            <span class="badge text-bg-secondary-soft">{{ $label }}</span>
                        @endforeach
                    </div>
                    <ul class="small text-body-secondary ps-3 mb-0">
                        <li>{{ __('Name, Unit and Retail price are required.') }}</li>
                        <li>{{ __('Unit must match a unit symbol or name (pc, kg, ctn…).') }}</li>
                        <li>{{ __('Tax type: standard, zero or exempt.') }}</li>
                        <li>{{ __('Missing categories and brands are created automatically.') }}</li>
                        <li>{{ __('Yes/No columns accept yes, no, 1 or 0.') }}</li>
                    </ul>
                </x-card>
            </div>
        </div>
    @else
        @php
            $valid = collect($preview)->where('errors', [])->count();
            $invalid = count($preview) - $valid;
        @endphp
        <div class="row g-3 mb-4">
            <div class="col-md-4"><x-stat-card :label="__('Rows in file')" :value="count($preview)" icon="bi-list-ol" /></div>
            <div class="col-md-4"><x-stat-card :label="__('Ready to import')" :value="$valid" icon="bi-check-circle" color="success" /></div>
            <div class="col-md-4"><x-stat-card :label="__('Rows with errors')" :value="$invalid" icon="bi-exclamation-triangle" color="danger" :hint="$invalid ? __('These rows will be skipped.') : null" /></div>
        </div>
        <x-card :title="__('2. Review')" icon="bi-eye" :flush="true">
            <div class="table-responsive" style="max-height: 60vh">
                <table class="table table-sm table-sticky table-stack align-middle">
                    <thead><tr><th>{{ __('Row') }}</th><th>{{ __('Action') }}</th><th>{{ __('Name') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Category') }}</th><th>{{ __('Unit') }}</th><th class="text-end">{{ __('Price') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($preview as $row)
                        <tr class="{{ $row['errors'] ? 'table-danger' : '' }}">
                            <td data-label="{{ __('Row') }}">{{ $row['row'] }}</td>
                            <td data-label="{{ __('Action') }}"><span class="badge text-bg-{{ $row['action'] === 'create' ? 'success' : 'info' }}-soft">{{ $row['action'] === 'create' ? __('New') : __('Update') }}</span></td>
                            <td data-label="{{ __('Name') }}" class="fw-medium">{{ $row['data']['name'] }}</td>
                            <td data-label="{{ __('SKU') }}" class="font-monospace small">{{ $row['data']['sku'] ?: __('auto') }}</td>
                            <td data-label="{{ __('Category') }}">{{ $row['data']['category'] }}{{ $row['data']['subcategory'] ? ' › '.$row['data']['subcategory'] : '' }}</td>
                            <td data-label="{{ __('Unit') }}">{{ $row['data']['unit'] }}</td>
                            <td data-label="{{ __('Price') }}" class="text-end">{{ is_numeric($row['data']['retail_price']) ? money($row['data']['retail_price']) : $row['data']['retail_price'] }}</td>
                            <td data-label="{{ __('Status') }}">
                                @if ($row['errors'])
                                    <span class="text-danger small">{{ implode(' ', $row['errors']) }}</span>
                                @else
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    @if ($invalid)
                        <a href="{{ route('products.import.errors') }}" class="btn btn-outline-danger me-auto"><i class="bi bi-file-earmark-excel"></i> {{ __('Download :n rows with errors', ['n' => $invalid]) }}</a>
                    @endif
                    <form method="POST" action="{{ route('products.import.cancel') }}">@csrf<button class="btn btn-light">{{ __('Start over') }}</button></form>
                    <form method="POST" action="{{ route('products.import.commit') }}" x-data="{ loading: false }" @submit="loading = true">@csrf
                        <button class="btn btn-primary" @disabled($valid === 0) :disabled="loading"><span class="spinner-border" x-show="loading" x-cloak></span> {{ __('Import :n products', ['n' => $valid]) }}</button>
                    </form>
                </div>
            </x-slot:footer>
        </x-card>
    @endif
</x-layouts.app>
