<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user, $title, $message;

    public function __construct(User $user, string $title, string $message)
    {
        $this->user = $user;
        $this->title = $title;
        $this->message = $message;
    }

    public function handle()
    {
        $this->user->notify(new PushNotification($this->title, $this->message));
    }

}
