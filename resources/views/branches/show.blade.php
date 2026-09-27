<x-layouts.app :title="$branch->name" :breadcrumbs="[__('Branches') => route('branches.index'), $branch->name]">
    <x-page-header :title="$branch->name">
        <x-slot:meta>
            <div class="d-flex gap-2 align-items-center mt-2">
                <span class="badge text-bg-secondary-soft font-monospace">{{ $branch->code }}</span>
                <x-status-badge :status="$branch->is_active ? 'active' : 'inactive'" />
            </div>
        </x-slot:meta>
        @can('update', $branch)
            <a href="{{ route('branches.edit', $branch) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal"><i class="bi bi-plus-lg"></i> {{ __('Add till') }}</button>
        @endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Tills / registers')" :value="$branch->registers->count()" icon="bi-pc-display" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Staff')" :value="$branch->users->count()" icon="bi-people" color="info" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Phone')" :value="\App\Support\PhoneNumber::display($branch->phone) ?: '—'" icon="bi-telephone" color="success" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Created')" :value="format_date($branch->created_at)" icon="bi-calendar3" color="warning" /></div>
    </div>

    <ul class="nav nav-tabs-modern mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-registers" type="button">{{ __('Tills') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-users" type="button">{{ __('Staff') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-details" type="button">{{ __('Details') }}</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-registers">
            <div class="card">
                @if ($branch->registers->isEmpty())
                    <x-empty-state icon="bi-pc-display" :title="__('No tills yet')" :message="__('Add a till so cashiers can open shifts.')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-stack">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($branch->registers as $register)
                                <tr>
                                    <td data-label="{{ __('Name') }}" class="fw-semibold">{{ $register->name }}</td>
                                    <td data-label="{{ __('Code') }}">{{ $register->code ?: '—' }}</td>
                                    <td data-label="{{ __('Status') }}"><x-status-badge :status="$register->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="text-end" data-label="">
                                        @can('update', $branch)
                                            <form method="POST" action="{{ route('registers.update', [$branch, $register]) }}" class="d-inline">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="name" value="{{ $register->name }}">
                                                <input type="hidden" name="code" value="{{ $register->code }}">
                                                <input type="hidden" name="is_active" value="{{ $register->is_active ? 0 : 1 }}">
                                                <button class="btn btn-sm btn-light">{{ $register->is_active ? __('Deactivate') : __('Activate') }}</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="tab-pane fade" id="tab-users">
            <div class="card">
                @if ($branch->users->isEmpty())
                    <x-empty-state icon="bi-people" :title="__('No staff assigned')" />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($branch->users as $u)
                            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                                <x-avatar :user="$u" />
                                <div class="flex-grow-1 min-w-0">
                                    <a href="{{ route('users.show', $u) }}" class="fw-semibold text-decoration-none">{{ $u->name }}</a>
                                    <div class="small text-body-secondary">{{ $u->email }}</div>
                                </div>
                                <span class="badge rounded-pill text-bg-primary-soft">{{ $u->roleLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="tab-pane fade" id="tab-details">
            <x-card>
                <dl class="row info-list mb-0">
                    <div class="col-md-6"><dt>{{ __('Address') }}</dt><dd>{{ $branch->address ?: '—' }}</dd></div>
                    <div class="col-md-6"><dt>{{ __('Email') }}</dt><dd>{{ $branch->email ?: '—' }}</dd></div>
                    <div class="col-md-6"><dt>{{ __('Receipt header') }}</dt><dd>{{ $branch->receipt_header ?: __('Business default') }}</dd></div>
                    <div class="col-md-6"><dt>{{ __('Receipt footer') }}</dt><dd>{{ $branch->receipt_footer ?: __('Business default') }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>

    @push('modals')
        <x-modal id="registerModal" :title="__('Add till / register')">
            <form method="POST" action="{{ route('registers.store', $branch) }}" id="registerForm">
                @csrf
                <x-input name="name" :label="__('Name')" required placeholder="{{ __('Till 2') }}" />
                <x-input name="code" :label="__('Code')" placeholder="T2" class="mb-0" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" form="registerForm" class="btn btn-primary">{{ __('Add till') }}</button>
            </x-slot:footer>
        </x-modal>
    @endpush
</x-layouts.app>
