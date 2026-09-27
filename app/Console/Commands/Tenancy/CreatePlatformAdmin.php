<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'dukapos:create-admin
        {--name= : Full name}
        {--email= : Email used to sign in at /admin}
        {--password= : Password (prompted when omitted)}';

    protected $description = 'Create a platform administrator (manages all businesses and subscriptions)';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name'),
            'email' => strtolower((string) ($this->option('email') ?: $this->ask('Email'))),
            'password' => $this->option('password') ?: $this->secret('Password (min. 10 characters)'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:central.platform_admins,email'],
            'password' => ['required', 'string', 'min:10'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        PlatformAdmin::create($data + ['is_super' => true, 'is_active' => true]);
        $this->info("Platform admin {$data['email']} created. Sign in at ".route('admin.login'));

        return self::SUCCESS;
    }
}
