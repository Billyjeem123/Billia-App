<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FCMService
{

//Perfect! Let me break down this FCM Service class in beginner-friendly terms:
//What This Class Does
//This is like a "Notification Sending Helper" for your Laravel app.
//Instead of writing complex Firebase code everywhere, you use this simple helper.
    protected $messaging;

//What this does:
//
//Connects to Firebase when the class starts
//Reads your Firebase credentials from config/firebase.json file
//Sets up the messaging service so you can send notifications
//
//Think of it like: Logging into your Firebase account so you can send messages
    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(base_path('config/firebase.json'));
        $this->messaging = $factory->createMessaging();
    }


//What this does:
//
//Sends a notification to ONE specific phone/device
//Like sending a personal text message to one person
//
//Parameters explained:
//
//$deviceToken = The phone's unique ID (like a phone number for notifications)
//$title = The big bold text (like "New Message!")
//$body = The details (like "You have 5 unread messages")
//$data = Extra hidden info your app can use
//
//Example usage:
    public function sendToDevice($deviceToken, $title, $body, $data = [])
    {
        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $this->messaging->send($message);
            return ['success' => true, 'message' => 'Notification sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

//What this does:
//
//Sends the same notification to many phones at once
//Like sending a group text to multiple people
//
//Example usage:
    public function sendToMultipleDevices($deviceTokens, $title, $body, $data = [])
    {
        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $this->messaging->sendMulticast($message, $deviceTokens);
            return ['success' => true, 'message' => 'Notifications sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


//4. Send to Topic (Broadcast)
//phppublic function sendToTopic($topic, $title, $body, $data = [])
//What this does:
//
//Sends to everyone subscribed to a topic
//Like broadcasting on radio - everyone tuned in hears it
//
//Example usage:

//$fcm->sendToTopic(
//"breaking_news",         // Topic name
//"BREAKING NEWS!",        // Title
//"Major event happened"   // Body
//);

    public function sendToTopic($topic, $title, $body, $data = [])
    {
        $message = CloudMessage::withTarget('topic', $topic)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $this->messaging->send($message);
            return ['success' => true, 'message' => 'Topic notification sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    // Subscribe device to topic
    public function subscribeToTopic($deviceToken, $topic)
    {
        try {
            $this->messaging->subscribeToTopic([$deviceToken], $topic);
            return ['success' => true, 'message' => "Device subscribed to {$topic}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Unsubscribe device from topic
    public function unsubscribeFromTopic($deviceToken, $topic): array
    {
        try {
            $this->messaging->unsubscribeFromTopic([$deviceToken], $topic);
            return ['success' => true, 'message' => "Device unsubscribed from {$topic}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Subscribe multiple devices to topic
    public function subscribeMultipleToTopic($deviceTokens, $topic): array
    {
        try {
            $this->messaging->subscribeToTopic($deviceTokens, $topic);
            return ['success' => true, 'message' => count($deviceTokens) . " devices subscribed to {$topic}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

    }

}
