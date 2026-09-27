<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\Plan;
use App\Models\Platform\TenantLogin;
use App\Tenancy\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class TenantsCreate extends Command
{
    protected $signature = 'tenants:create
        {--business= : Business name}
        {--owner= : Owner full name}
        {--email= : Owner email (used to sign in)}
        {--phone= : Owner phone}
        {--password= : Owner password (prompted when omitted)}
        {--plan= : Plan slug}
        {--trial-days= : Free trial length (default from platform settings)}';

    protected $description = 'Create a new business with its own database and owner account';

    public function handle(TenantProvisioner $provisioner): int
    {
        $data = [
            'business_name' => $this->option('business') ?: $this->ask('Business name'),
            'owner_name' => $this->option('owner') ?: $this->ask('Owner full name'),
            'email' => strtolower((string) ($this->option('email') ?: $this->ask('Owner email'))),
            'phone' => $this->option('phone'),
            'password' => $this->option('password') ?: $this->secret('Password (min. 8 characters)'),
            'trial_days' => $this->option('trial-days'),
        ];
        if ($slug = $this->option('plan')) {
            $data['plan_id'] = Plan::where('slug', $slug)->value('id');
        }

        $validator = Validator::make($data, [
            'business_name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $validator->after(function ($v) use ($data) {
            if (TenantLogin::where('email', $data['email'])->exists()) {
                $v->errors()->add('email', 'This email is already used by another account.');
            }
        });
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $tenant = $provisioner->provision(array_filter($data, fn ($v) => $v !== null));
        $this->info("Business #{$tenant->id} {$tenant->name} created (database {$tenant->database}). The owner can sign in at ".route('login'));

        return self::SUCCESS;
    }
}
