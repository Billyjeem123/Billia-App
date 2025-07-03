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
        $transactionObj = json_decode(json_encode($this->transaction)); // for use in Blade
        $shortMessage = $this->getShortBillMessage($this->transaction, $this->transaction['purchased_code'] ?? null);

        return (new MailMessage)
            ->subject('Your Bill Payment Was Successful')
            ->view('email.vtpass_tranx_successful', [
                'data' => $transactionObj,
                'short_message' => $shortMessage,
            ]);
    }


    public function toMail001(object $notifiable): MailMessage
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



    private function getShortBillMessage($transaction, $code = null): string
    {
        $type = strtolower($transaction['content']['transactions']['type'] ?? '');
        $product = strtolower($transaction['content']['transactions']['product_name'] ?? '');
        $amount = number_format($transaction['amount'] ?? 0, 2);
        $phone = $transaction['content']['transactions']['unique_element'];

        if (str_contains($product, 'electric')) {
            $context = $transaction['content']['transactions']['product_name'] ?? 'our service';
            return "Your electricity token from  $context is below.\n\nToken: $code\n\n";
        }

        if (str_contains($product, 'airtime')) {
            return "{$transaction['content']['transactions']['product_name']}  of ₦$amount is successful for $phone.";
        }

        if (str_contains($product, 'data')) {
            return "{$transaction['content']['transactions']['product_name']}  purchase of ₦$amount for $phone was  successful.";
        }

        if (str_contains($product, 'jamb') || str_contains($product, 'waec') || str_contains($product, 'neco')) {
            return "Your {$transaction['content']['transactions']['product_name']} PIN is:\n\n$code\n\n";
        }

//        return "{$transaction['content']['transactions']['product_name']} purchase of ₦$amount for $phone successful. Ref: $ref";
        return "Your {$transaction['content']['transactions']['product_name']} purchase for {$transaction['content']['transactions']['unique_element']} is successful";
    }

}
