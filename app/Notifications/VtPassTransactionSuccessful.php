<?php

namespace App\Notifications;

use App\Models\TransactionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class VtPassTransactionSuccessful extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $transaction;
    protected $status;

    public function __construct( $transaction, $status)
    {
        $this->transaction = $transaction;
        $this->status = $status;
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
            ->greeting("Hello {$notifiable->first_name},")
            ->view('email.vtpass_tranx_successful', [
                'data' => json_decode(json_encode($this->transaction)), // Converts nested arrays to objects
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
            'title' => 'Transaction Successful',
            'message' => "Your purchase of {$this->transaction['content']['transactions']['product_name']} was successful. Amount charged: ₦{$this->transaction['amount']}.",
        ];
    }
}
