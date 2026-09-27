<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app (and optional email) alert: low stock, expiring batches,
 * transfers awaiting approval, shift over/short, overdue debts, exports ready.
 */
class SystemAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $icon = 'bi-bell',
        public string $color = 'primary',
        public bool $mail = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->mail && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url, 'icon' => $this->icon, 'color' => $this->color];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title.' · '.setting('business.name'))->line($this->message);

        return $this->url ? $mail->action(__('Open DukaPOS'), $this->url) : $mail;
    }
}
