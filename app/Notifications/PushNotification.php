<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\AndroidConfig;
use NotificationChannels\Fcm\Resources\AndroidNotification;

class PushNotification extends Notification
{
    use Queueable;

    public  $title;
    public  $message;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $message)
    {
         $this->title = $title;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];

    }


    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->setNotification(
                \NotificationChannels\Fcm\Resources\Notification::create()
                    ->setTitle($this->title)
                    ->setBody($this->message)
                    ->setImage(url('images/banner.png')) // Android banner image
            )
            ->setAndroid(
                AndroidConfig::create()
                    ->setNotification(
                        AndroidNotification::create()
                            ->setIcon('icon.webp') // name of drawable icon in Android app
                            ->setColor('#1E90FF')       // optional accent color
                            ->setImage(url('images/banner.png')) // Optional
                    )
            );
    }



}
