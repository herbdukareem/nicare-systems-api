<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ExpiredAdmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Collection $expiredAdmissions,
        private readonly int $flaggedCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'         => 'Expired Admission UTNs Detected',
            'message'       => "{$this->flaggedCount} active admission(s) have expired referral UTNs and require immediate review.",
            'flagged_count' => $this->flaggedCount,
            'admission_ids' => $this->expiredAdmissions->pluck('id')->toArray(),
            'action_url'    => '/admissions?filter=utn_expired',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('NiCare Alert: Expired Admission UTNs')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("{$this->flaggedCount} active admission(s) have expired referral UTNs.")
            ->line('These admissions require immediate review — new referrals may be needed before claims can be submitted.')
            ->action('View Expired Admissions', url('/admissions?filter=utn_expired'))
            ->line('This alert was generated automatically by the NiCare system.');
    }
}
