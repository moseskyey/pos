{{-- Platform notices: admin sign-in, subscription status and announcements. --}}
@php
    $tenant = tenant();
    $status = $tenant?->status();
    $announcements = $tenant ? \App\Models\Platform\Announcement::visibleTo($tenant->id)->latest()->limit(3)->get() : collect();
    $canBill = $user?->can('settings.manage');
@endphp
@if (session('admin_impersonator_id'))
    <div class="alert alert-info rounded-0 border-0 mb-0 py-2 small d-flex flex-wrap align-items-center gap-2 no-print" role="status">
        <i class="bi bi-person-gear"></i>
        {{ __('Platform admin view: :business, signed in as :name.', ['business' => $tenant?->name, 'name' => $user?->name]) }}
        <form method="POST" action="{{ route('impersonate.admin.leave') }}" class="ms-auto">@csrf
            <button class="btn btn-sm btn-info py-0"><i class="bi bi-arrow-left"></i> {{ __('Back to admin') }}</button>
        </form>
    </div>
@endif
@if ($tenant && in_array($status, ['trial', 'grace'], true) && ($status === 'grace' || $tenant->daysLeft() <= 7))
    <div class="alert alert-{{ $status === 'grace' ? 'danger' : 'warning' }} rounded-0 border-0 mb-0 py-2 small d-flex flex-wrap align-items-center gap-2 no-print" role="status">
        <i class="bi bi-hourglass-split"></i>
        @if ($status === 'grace')
            {{ __('Your subscription has ended. You can keep working until :date; renew now to avoid interruption.', ['date' => $tenant->graceEndsAt()->format('d/m/Y')]) }}
        @else
            {{ trans_choice('{0} Your free trial ends today.|{1} Your free trial ends tomorrow.|[2,*] Your free trial ends in :count days.', $tenant->daysLeft()) }}
        @endif
        @if ($canBill)
            <a href="{{ route('billing.index') }}" class="btn btn-sm btn-{{ $status === 'grace' ? 'danger' : 'warning' }} py-0 ms-auto">{{ __('Choose a plan') }}</a>
        @else
            <span class="ms-auto">{{ __('Ask the owner to renew.') }}</span>
        @endif
    </div>
@endif
@foreach ($announcements as $announcement)
    <div class="alert alert-{{ $announcement->level }} rounded-0 border-0 mb-0 py-2 small no-print" role="status">
        <i class="bi bi-megaphone"></i> <strong>{{ $announcement->title }}</strong>
        @if ($announcement->body) <span>{{ $announcement->body }}</span> @endif
    </div>
@endforeach
