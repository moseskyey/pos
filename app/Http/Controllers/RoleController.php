<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);
        $roles = Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);

        return view('roles.form', ['role' => new Role, 'assigned' => []]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));
        activity('roles')->performedOn($role)->withProperties(['permissions' => $request->validated('permissions', [])])->log('Role created');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', __('Role created.'));
    }

    public function edit(Role $role): View
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);

        return view('roles.form', ['role' => $role, 'assigned' => $role->permissions->pluck('name')->all()]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'owner', 403, __('The owner role always has every permission.'));

        $before = $role->permissions->pluck('name')->all();
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));
        activity('roles')->performedOn($role)->withProperties([
            'added' => array_values(array_diff($request->validated('permissions', []), $before)),
            'removed' => array_values(array_diff($before, $request->validated('permissions', []))),
        ])->log('Role permissions updated');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);
        if (in_array($role->name, array_keys(config('dukapos.roles')), true)) {
            return back()->with('error', __('Built-in roles cannot be deleted.'));
        }
        if ($role->users()->exists()) {
            return back()->with('error', __('Remove users from this role first.'));
        }
        $role->delete();

        return back()->with('success', __('Role deleted.'));
    }
}
