<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\Customer;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const GROUPS = [
        'business' => ['Business profile', 'bi-shop'],
        'currency' => ['Currency & tax', 'bi-currency-exchange'],
        'receipt' => ['Receipts', 'bi-receipt'],
        'pos' => ['POS & inventory', 'bi-upc-scan'],
        'payments' => ['Payment methods', 'bi-credit-card'],
        'notifications' => ['SMS, alerts & fiscal', 'bi-bell'],
        'localisation' => ['Language & region', 'bi-translate'],
        'prefixes' => ['Document numbers', 'bi-hash'],
    ];

    /** Setting key prefixes stored by each group. */
    protected const GROUP_KEYS = [
        'business' => ['business.'],
        'currency' => ['currency.', 'tax.'],
        'receipt' => ['receipt.'],
        'pos' => ['pos.', 'loyalty.', 'inventory.'],
        'payments' => ['payments.'],
        'notifications' => ['sms.', 'notify.', 'fiscal.'],
        'localisation' => ['locale.'],
        'prefixes' => ['prefix.'],
    ];

    public function edit(Request $request, SettingsService $settings, string $group = 'business'): View
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        $customers = $group === 'pos' && class_exists(Customer::class)
            ? Customer::query()->orderBy('name')->limit(500)->pluck('name', 'id')
            : collect();

        return view('settings.edit', [
            'group' => $group,
            'groups' => self::GROUPS,
            's' => $settings->all(),
            'customers' => $customers,
        ]);
    }

    public function update(SettingsRequest $request, SettingsService $settings, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        $data = $request->validated();
        $values = [];
        foreach (array_keys(config('dukapos.settings')) as $key) {
            if (! collect(self::GROUP_KEYS[$group])->contains(fn ($p) => str_starts_with($key, $p))) {
                continue;
            }
            $field = str_replace('.', '_', $key);
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            // Keep existing secrets when the field is left blank.
            if ($settings->isEncrypted($key) && ($value === null || $value === '')) {
                continue;
            }
            $default = config("dukapos.settings.$key");
            if (is_bool($default)) {
                $value = (bool) $value;
            } elseif (is_int($default) && is_numeric($value)) {
                $value = (int) $value;
            } elseif ($key === 'pos.default_customer_id') {
                $value = $value ? (int) $value : null;
            }
            $values[$key] = $value;
        }

        if ($group === 'business' && $request->hasFile('logo')) {
            $values['business.logo'] = $request->file('logo')->store('branding', 'local');
        }

        $settings->set($values);

        activity('settings')
            ->withProperties(['group' => $group, 'keys' => array_keys($values)])
            ->log("Settings updated: $group");

        return redirect()->route('settings.edit', $group)->with('success', __('Settings saved.'));
    }
}
