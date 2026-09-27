<x-layouts.app :title="__('Notifications')">
    <x-page-header :title="__('Notifications')" :subtitle="__('Stock alerts, approvals and reminders.')">
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                <button class="btn btn-outline-secondary"><i class="bi bi-check2-all"></i> {{ __('Mark all as read') }}</button>
            </form>
        @endif
    </x-page-header>
    <div class="card">
        @if ($notifications->isEmpty())
            <x-empty-state icon="bi-bell" :title="__('You are all caught up')" :message="__('New alerts will appear here.')" />
        @else
            <div class="list-group list-group-flush">
                @foreach ($notifications as $n)
                    @php $color = $n->data['color'] ?? 'primary'; @endphp
                    <a href="{{ route('notifications.open', $n->id) }}" class="list-group-item list-group-item-action d-flex gap-3 py-3 notification-item {{ $n->read_at ? '' : 'unread' }}">
                        <div class="rounded-circle bg-{{ $color }}-soft text-{{ $color }} d-grid flex-shrink-0" style="width:40px;height:40px;place-items:center"><i class="bi {{ $n->data['icon'] ?? 'bi-bell' }}"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold">{{ $n->data['title'] ?? __('Notification') }}</span>
                                <span class="small text-body-secondary text-nowrap">{{ $n->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="small text-body-secondary">{{ $n->data['message'] ?? '' }}</div>
                        </div>
                        @unless ($n->read_at)<span class="badge bg-primary rounded-pill align-self-center">&nbsp;</span>@endunless
                    </a>
                @endforeach
            </div>
            <div class="card-footer">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
