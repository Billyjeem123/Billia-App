<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaystackTransferFailed extends Notification
{
    use Queueable;

    public  $transaction;
    public  $data;

    /**

    /**
     * Create a new notification instance.
     */
    public function __construct($transaction, $data)
    {
        $this->transaction = $transaction;
        $this->data = $data;

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
            ->subject('Transaction Notification')
            ->greeting("Hello {$notifiable->first_name},")
            ->view('email.paystack_transfer_failed', [
                'data' => ($this->data),
                'transaction' => $this->transaction,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return
            [
         'title' => 'Transfer Failed',
        'message' => 'Your transfer of ₦' . number_format($this->data['data']['amount'] / 100, 2) . ' to ' . $this->data['data']['recipient']['details']['account_name'] . ' failed. Amount refunded to your wallet.',
         "data" => [
             'type' => 'transfer_failed',
             'amount' => $this->data['data']['amount'] / 100,
             'recipient_name' => $this->data['data']['recipient']['details']['account_name'] ?? "",
            // 'reference' => $this->data['reference'],
             'status' => 'failed',
             'timestamp' => now()->toISOString(),
         ]
        ];
    }
}
