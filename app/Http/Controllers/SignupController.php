<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\Platform\Plan;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Public sign-up: a new business gets its own database and a free trial.
 */
class SignupController extends Controller
{
    public function create(): View
    {
        abort_unless(PlatformSettings::get('signups_enabled'), 404);

        return view('auth.register', [
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get(),
            'trialDays' => (int) PlatformSettings::get('trial_days', 14),
        ]);
    }

    public function store(SignupRequest $request, TenantProvisioner $provisioner): RedirectResponse
    {
        try {
            $tenant = $provisioner->provision($request->safe()->only(['business_name', 'owner_name', 'email', 'phone', 'password', 'plan_id']));
        } catch (Throwable $e) {
            Log::error('Sign-up failed', ['email' => $request->input('email'), 'error' => $e->getMessage()]);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', __('We could not create your account right now. Please try again in a few minutes.'));
        }

        // Sign the owner straight in.
        app(TenantManager::class)->initialize($tenant);
        $owner = User::where('email', $request->input('email'))->firstOrFail();
        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $tenant->id);
        activity('auth')->causedBy($owner)->performedOn($owner)->log('Signed up');

        return redirect()->route('dashboard')->with('success', __('Welcome to DukaPOS! Your free trial runs until :date.', ['date' => $tenant->trial_ends_at?->format('d/m/Y') ?? '-']));
    }
}
