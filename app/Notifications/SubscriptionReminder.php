<?php

namespace App\Notifications;

use App\Models\Platform\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emailed to a business owner before (and when) the subscription runs out. */
class SubscriptionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $tenantId, public string $title, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = Tenant::find($this->tenantId);

        return (new MailMessage)
            ->subject($this->title.' · '.PlatformSettings::get('name', 'DukaPOS'))
            ->greeting(__('Hello :name,', ['name' => $tenant?->owner_name ?? '']))
            ->line($this->message)
            ->action(__('Renew subscription'), route('billing.index'))
            ->line(__('Your data is safe and is never deleted when a subscription ends.'));
    }
}
