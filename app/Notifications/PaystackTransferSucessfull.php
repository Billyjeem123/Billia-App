<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaystackTransferSucessfull extends Notification
{
    use Queueable;

    public  $transaction;
    public  $data;

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
            ->view('email.paystack_transfer_successful', [
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
        return [
            'title' => 'Transfer Successful',
            'message' => "Your transfer of ₦" . number_format($this->data['data']['amount'] / 100, 2) . " was successful.",
             'data' =>  [
                 'reference' => $this->transaction->transaction_reference,
                 'amount' => number_format($this->data['data']['amount'] / 100, 2),
                 'type' => 'transfer',
                 'status' => 'successful',
                 'time' => now()->toDateTimeString(),
             ],

        ];
    }
}
