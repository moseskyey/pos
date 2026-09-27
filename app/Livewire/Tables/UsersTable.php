<?php

namespace App\Livewire\Tables;

use App\Models\Branch;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

class UsersTable extends DataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Users');
    }

    protected function query(): Builder
    {
        $user = auth()->user();

        return User::query()->with(['roles', 'branches'])
            ->when(! $user->can('branches.view_all'), fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', branch_context()->accessibleIds())));
    }

    protected function searchable(): array
    {
        return ['name', 'email', 'phone'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('role', __('Roles'), Role::query()->orderBy('name')->pluck('name', 'name')->map(fn ($n) => config("dukapos.roles.$n.label", ucfirst($n)))->all())
                ->query(fn ($q, $v) => $q->role($v)),
            Filter::select('branch', __('Branches'), Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                ->query(fn ($q, $v) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $v))),
            Filter::boolean('is_active', __('Active')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->html(fn ($u) => Blade::render(
                '<div class="d-flex align-items-center gap-2"><x-avatar :user="$u" size="sm" /><div><div class="fw-semibold">{{ $u->name }}</div><div class="small text-body-secondary">{{ $u->email }}</div></div></div>',
                ['u' => $u]
            ))->exportAs(fn ($u) => $u->name),
            Column::make(__('Email'), 'email')->exportOnly(),
            Column::make(__('Phone'), 'phone')->format(fn ($u) => $u->phone ? PhoneNumber::display($u->phone) : '—'),
            Column::make(__('Role'))->html(fn ($u) => '<span class="badge rounded-pill text-bg-primary-soft">'.e($u->roleLabel()).'</span>')->exportAs(fn ($u) => $u->roleLabel()),
            Column::make(__('Branches'))->format(fn ($u) => $u->branches->pluck('code')->join(', ') ?: '—'),
            Column::make(__('Last login'), 'last_login_at')->sortable()->date(true),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('users.show', $row);
    }

    protected function rowActions(mixed $row): ?string
    {
        $html = '';
        if (auth()->user()->can('update', $row)) {
            $html .= '<a href="'.route('users.edit', $row).'" class="btn btn-sm btn-light btn-icon" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></a>';
        }

        return $html;
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-people', 'title' => __('No users found'), 'message' => __('Invite your team and assign them roles.'),
            'action' => route('users.create'), 'actionLabel' => __('Add user')];
    }
}
