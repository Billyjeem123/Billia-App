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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' =>  $this->title,
             'message' =>  $this->message,
        ];
    }


    public function toFcm00($notifiable)
    {
        return FcmMessage::create()
            ->setData([
                'custom_key' => 'custom_value',
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->title)
                ->setBody($this->message));
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
