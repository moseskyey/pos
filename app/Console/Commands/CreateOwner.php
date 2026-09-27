<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * First-run setup for a production install: roles, the first branch and the
 * owner account. Safe to re-run; existing roles and branches are kept.
 */
class CreateOwner extends Command
{
    protected $signature = 'dukapos:create-owner
        {--name= : Owner full name}
        {--email= : Owner email}
        {--password= : Owner password (prompted when omitted)}
        {--branch= : First branch name, e.g. Kariakoo}
        {--code= : First branch code, e.g. DSM01}';

    protected $description = 'Create the owner account (and the first branch) on a fresh install';

    public function handle(): int
    {
        if (! Role::where('name', 'owner')->exists()) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Owner full name'),
            'email' => $this->option('email') ?: $this->ask('Owner email'),
            'password' => $this->option('password') ?: $this->secret('Password (min. 8 characters)'),
        ];
        $needsBranch = ! Branch::exists();
        if ($needsBranch) {
            $data['branch'] = $this->option('branch') ?: $this->ask('First branch name', 'Main');
            $data['code'] = strtoupper($this->option('code') ?: $this->ask('Branch code (used in document numbers)', 'MAIN'));
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'branch' => [$needsBranch ? 'required' : 'nullable', 'string', 'max:120'],
            'code' => [$needsBranch ? 'required' : 'nullable', 'alpha_dash', 'max:10', 'unique:branches,code'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data, $needsBranch) {
            $branch = $needsBranch
                ? tap(Branch::create(['name' => $data['branch'], 'code' => $data['code'], 'is_active' => true]), fn ($b) => $b->registers()->create(['name' => __('Till 1'), 'code' => 'T1']))
                : Branch::orderBy('id')->first();

            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
                'is_active' => true, 'default_branch_id' => $branch->id,
            ]);
            $user->assignRole('owner');
            $user->branches()->syncWithoutDetaching([$branch->id]);

            return $user;
        });

        $this->info("Owner {$user->email} created. Sign in at ".config('app.url'));

        return self::SUCCESS;
    }
}
