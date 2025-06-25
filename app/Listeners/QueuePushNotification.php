<?php

namespace App\Listeners;

use App\Events\PushNotificationEvent;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class QueuePushNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PushNotificationEvent $event)
    {
        dispatch(new SendPushNotificationJob(
            $event->user,
            $event->title,
            $event->message
        ));
    }
}
