<?php

namespace App\Listeners;

use App\Events\AccountRegistered;
use App\Models\PaystackCustomer;
use App\Services\NombaService;
use App\Services\PaystackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AccountRegisteredListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
       #
    }

    /**
     * Handle the event.
     */
    public function handle(AccountRegistered $event): void
    {
        Log::info('AssignDedicatedAccount event triggered for user: ' . $event->user->email);

        try {
           #  Check if user already has a Paystack customer
           #  Prepare data for Paystack
            $customerData = [
                'email' => $event->user->email,
                'first_name' => $event->user->first_name ?? '',
                'last_name' => $event->user->last_name ?? '',
                'phone' => $event->user->phone ?? null,
                'id' => $event->user->id,
            ];

           #  Create customer with dedicated account
            $result = (new PaystackService())->createCustomer($customerData, $event->user->id);
            //  $result = (new NombaService())->createVirtualAccount($customerData, $event->user->id);

            if (is_array($result) && !$result['success']) {
                Log::error('Failed to create wallet for  customer : ' . $event->user->email, [
                    'error' => $result['message'],
                    'user_id' => $event->user->id
                ]);
                return;
            }

            Log::info('Dedicated account assigned successfully for user: ' . $event->user->email);

        } catch (\Exception $e) {
            Log::error('Exception in AssignDedicatedAccount listener: ' . $e->getMessage(), [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
