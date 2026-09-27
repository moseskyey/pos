<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionReminder;
use App\Notifications\SystemAlert;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Tells owners (in the app and by email) that the trial or subscription ends
 * in 7, 3 and 1 days (configurable), and once more when it has ended.
 */
class SendSubscriptionReminders extends Command
{
    protected $signature = 'billing:reminders';

    protected $description = 'Remind business owners before their subscription or trial ends';

    public function handle(TenantManager $tenancy): int
    {
        $days = collect(explode(',', (string) PlatformSettings::get('reminder_days', '7,3,1')))
            ->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d > 0)->all();
        $sent = 0;

        foreach (TenantsRun::tenants() as $tenant) {
            if ($tenant->last_reminder_at && $tenant->last_reminder_at->gt(now()->subHours(20))) {
                continue;
            }
            $status = $tenant->status();
            $left = $tenant->daysLeft();

            if (in_array($status, [Tenant::TRIAL, Tenant::ACTIVE], true) && in_array($left, $days, true)) {
                $title = $status === Tenant::TRIAL ? __('Your free trial is ending') : __('Your subscription is ending');
                $message = trans_choice('{1} :business: access ends tomorrow (:date). Renew to keep selling without interruption.|[2,*] :business: access ends in :count days (:date). Renew to keep selling without interruption.',
                    $left, ['business' => $tenant->name, 'date' => $tenant->accessEndsAt()->format('d/m/Y')]);
            } elseif ($status === Tenant::GRACE && $tenant->accessEndsAt()?->isYesterday()) {
                $title = __('Your subscription has ended');
                $message = __(':business: your subscription ended. You can keep working until :date; after that the account is locked until it is renewed.',
                    ['business' => $tenant->name, 'date' => $tenant->graceEndsAt()->format('d/m/Y')]);
            } else {
                continue;
            }

            $tenancy->run($tenant, function () use ($title, $message) {
                User::role('owner')->where('is_active', true)->get()
                    ->each(fn (User $owner) => $owner->notify(new SystemAlert($title, $message, route('billing.index'), 'bi-credit-card-2-front', 'warning')));
            });
            if ($tenant->owner_email) {
                Notification::route('mail', $tenant->owner_email)->notify(new SubscriptionReminder($tenant->id, $title, $message));
            }
            $tenant->forceFill(['last_reminder_at' => now()])->saveQuietly();
            $sent++;
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
