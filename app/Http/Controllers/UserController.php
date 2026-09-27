<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('users.index');
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.form', $this->formData(new User(['is_active' => true, 'locale' => 'en'])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->guardOwnerRole($request);

        $user = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $user = User::create(Arr::only($data, ['name', 'email', 'phone', 'password', 'is_active', 'default_branch_id', 'commission_rate']));
            $user->syncRoles([$data['role']]);
            $user->branches()->sync($data['branches']);
            if (! empty($data['pin'])) {
                $user->setPin($data['pin']);
            }
            if ($request->hasFile('avatar')) {
                $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'local')]);
            }

            return $user;
        });

        if ($request->boolean('save_new')) {
            return redirect()->route('users.create')->with('success', __('User created.'));
        }

        return redirect()->route('users.show', $user)->with('success', __('User created.'));
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);
        $user->load(['roles', 'branches', 'defaultBranch']);
        $activities = Activity::query()
            ->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id)
            ->latest()->limit(15)->get();

        return view('users.show', compact('user', 'activities'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.form', $this->formData($user->load('roles', 'branches')));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->guardOwnerRole($request, $user);

        DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $attributes = Arr::only($data, ['name', 'email', 'phone', 'is_active', 'default_branch_id']);
            if (array_key_exists('commission_rate', $data)) {
                $attributes['commission_rate'] = $data['commission_rate'];
            }
            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }
            if ($user->is($request->user())) {
                $attributes['is_active'] = true; // cannot deactivate yourself
            }
            $user->update($attributes);
            $user->syncRoles([$data['role']]);
            $user->branches()->sync($data['branches']);
            if (! empty($data['pin'])) {
                $user->setPin($data['pin']);
            }
            if ($request->hasFile('avatar')) {
                $user->deleteAvatar();
                $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'local')]);
            }
        });

        return redirect()->route('users.show', $user)->with('success', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        if ($user->is($request->user())) {
            return back()->with('error', __('You cannot delete your own account.'));
        }
        if ($user->hasRole('owner') && User::role('owner')->count() <= 1) {
            return back()->with('error', __('At least one owner account is required.'));
        }

        $user->update(['is_active' => false]);
        $user->delete();

        return redirect()->route('users.index')->with('success', __('User deleted.'));
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('impersonate', $user);

        activity('auth')->causedBy($request->user())->performedOn($user)->log('Started impersonation');
        $impersonator = $request->user()->id;
        Auth::login($user);
        $request->session()->put('impersonator_id', $impersonator);
        $request->session()->forget(BranchContext::SESSION_KEY);

        return redirect()->route('dashboard')->with('info', __('You are now signed in as :name.', ['name' => $user->name]));
    }

    public function leaveImpersonation(Request $request): RedirectResponse
    {
        $id = $request->session()->pull('impersonator_id');
        abort_unless($id, 403);
        $original = User::findOrFail($id);
        activity('auth')->causedBy($original)->performedOn($request->user())->log('Stopped impersonation');
        Auth::login($original);
        $request->session()->forget(BranchContext::SESSION_KEY);

        return redirect()->route('users.index')->with('success', __('Welcome back, :name.', ['name' => $original->name]));
    }

    protected function formData(User $user): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => config("dukapos.roles.$name.label", ucfirst(str_replace('_', ' ', $name)))]);
        if (! auth()->user()->hasRole('owner')) {
            $roles = $roles->except('owner');
        }

        return [
            'user' => $user,
            'roles' => $roles,
            'branches' => Branch::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }

    /** Only owners may grant or edit the owner role. */
    protected function guardOwnerRole(Request $request, ?User $target = null): void
    {
        $isOwner = $request->user()->hasRole('owner');
        abort_if(! $isOwner && ($request->input('role') === 'owner' || $target?->hasRole('owner')), 403);
    }
}
