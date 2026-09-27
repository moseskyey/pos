<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Platform administrators (super admins only). */
class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.admins.index');
    }

    public function create(): View
    {
        return view('admin.admins.form', ['admin' => new PlatformAdmin(['is_active' => true])]);
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        $admin = PlatformAdmin::create($request->validated());
        AdminActivity::record('admin.created', "Added admin {$admin->email}", null, ['super' => $admin->is_super]);

        return redirect()->route('admin.admins.index')->with('success', __('Admin added.'));
    }

    public function edit(PlatformAdmin $admin): View
    {
        return view('admin.admins.form', ['admin' => $admin]);
    }

    public function update(AdminUserRequest $request, PlatformAdmin $admin): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($admin->is(auth('admin')->user())) {
            // Nobody can lock themselves out.
            $data['is_active'] = true;
            $data['is_super'] = true;
        } elseif ($admin->is_super && (! $data['is_super'] || ! $data['is_active']) && $this->lastSuper($admin)) {
            return back()->withInput()->with('error', __('Keep at least one active super admin.'));
        }
        $admin->update($data);
        AdminActivity::record('admin.updated', "Updated admin {$admin->email}", null, ['super' => $admin->is_super, 'active' => $admin->is_active]);

        return redirect()->route('admin.admins.index')->with('success', __('Admin updated.'));
    }

    public function destroy(PlatformAdmin $admin): RedirectResponse
    {
        if ($admin->is(auth('admin')->user()) || ($admin->is_super && $this->lastSuper($admin))) {
            return back()->with('error', __('You cannot remove yourself or the last super admin.'));
        }
        $admin->delete();
        AdminActivity::record('admin.deleted', "Removed admin {$admin->email}");

        return redirect()->route('admin.admins.index')->with('success', __('Admin removed.'));
    }

    protected function lastSuper(PlatformAdmin $admin): bool
    {
        return ! PlatformAdmin::where('is_super', true)->where('is_active', true)->whereKeyNot($admin->id)->exists();
    }
}
