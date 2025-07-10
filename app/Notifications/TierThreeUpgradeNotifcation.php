<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TierThreeUpgradeNotifcation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    private  $user;

    /**
     * Create a new notification instance.
     */
    public function __construct( $user)
    {
        $this->user = $user;
    }
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        return (new MailMessage)
            ->subject('Account Upgrade Notification')
            ->greeting("Hello {$notifiable->first_name},")
            ->view('email.account_upgraded', [
                'user' => ($this->user),
                'tier' => 'Tier-3',
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account Upgraded',
            'message' => 'Congratulations! Your account has been upgraded to Tier 3.',
        ];
    }
}
