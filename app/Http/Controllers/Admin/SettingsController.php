<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlatformSettingsRequest;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\Plan;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'settings' => app(PlatformSettings::class)->all(),
            'plans' => Plan::orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    public function update(PlatformSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Secrets are write-only: a blank field keeps the stored value.
        foreach (PlatformSettings::ENCRYPTED as $secret) {
            if (($data[$secret] ?? null) === null || $data[$secret] === '') {
                unset($data[$secret]);
            }
        }
        foreach (['trial_days', 'grace_days'] as $int) {
            $data[$int] = (int) $data[$int];
        }
        PlatformSettings::set($data);
        AdminActivity::record('settings.updated', 'Updated platform settings', null, ['keys' => array_keys($data)]);

        return back()->with('success', __('Settings saved.'));
    }
}
