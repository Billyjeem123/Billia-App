<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\JsonResponse;

class ReloadlyService
{
    private $authUrl;
    private $giftcardBaseUrl;
    private $clientId;
    private $clientSecret;
    private $accessToken;

    public function __construct()
    {
        $this->clientId = env('RELOADLY_CLIENT_ID');
        $this->clientSecret = env('RELOADLY_CLIENT_SECRET');
        $this->authUrl = 'https://auth.reloadly.com/oauth/token';
        $this->giftcardBaseUrl = env('APP_ENV') === 'local'
            ? 'https://giftcards-sandbox.reloadly.com'
            : 'https://giftcards.reloadly.com';
        $this->accessToken = null;
    }

    private function makeCurlRequest($url, $method = 'GET', $headers = [], $body = null)
    {
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json'
        ];
        $finalHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL Error: $error");
        }

        curl_close($ch);

        if ($httpcode >= 400) {
            throw new \Exception("HTTP Error ($httpcode): $response");
        }

        return json_decode($response, true);
    }

    private function getAuthToken(): string
    {
        try {
            $body = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
                'audience' => $this->giftcardBaseUrl
            ];


            $response = $this->makeCurlRequest($this->authUrl, 'POST', [], $body);
            $this->accessToken = $response['access_token'] ?? '';
            return $this->accessToken;
        } catch (\Exception $e) {
            return '';
        }
    }

    public function buyGiftcard($data):JsonResponse
    {
        try {
            if (!$this->accessToken) $this->getAuthToken();

            $url = "{$this->giftcardBaseUrl}/orders";
            $headers = [
                "Authorization: Bearer {$this->accessToken}"
            ];
            $body = [
                'unitPrice' => $data['amount'],
                'quantity' => $data['quantity'],
                'useLocalAmount' => true,
                'productId' => $data['product_id'],
                'countryCode' => $data['recipient_country_code'],
                'recipientPhoneDetails' => [
                    'countryCode' => $data['recipient_country_code'],
                    'phoneNumber' => $data['recipient_phone']
                ]
            ];

            $response = $this->makeCurlRequest($url, 'POST', $headers, $body);
            return self::process_response($response, $data);

        } catch (\Exception $e) {
            TransactionController::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount_giftcard']]);
            WalletController::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }

    private static function process_response($response, $data):JsonResponse
    {
        $response_array = is_array($response) ? $response :  json_decode($response, true);
        if ($response_array['status'] == 'SUCCESSFUL') {
            TransactionController::update_info($data['transaction_id'],['status' =>  'successful', 'provider_response' => $response]);

            return response()->json([
                'status' => true,
                'message' => "You have successfully purchased giftcard"
            ]);
        }else{
            TransactionController::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $response, 'amount_after' => $data['amount_after'] + $data['amount_giftcard']]);
            WalletController::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => $response_array['response_description']
            ]);
        }
    }


    public function giftcardFxRate($data)
    {
        try {
            if (!$this->accessToken) $this->getAuthToken();

            $url = "{$this->giftcardBaseUrl}/fx-rate?currencyCode={$data['currencyCode']}&amount={$data['amount']}";
            $headers = ["Authorization: Bearer {$this->accessToken}"];

            $res = $this->makeCurlRequest($url, 'GET', $headers);

            return [
                'sender_currency' => $res['senderCurrency'],
                'sender_amount' => $res['senderAmount'],
                'recipient_currency' => $res['recipientCurrency'],
                'recipient_amount' => $res['recipientAmount']
            ];

        } catch (\Exception $e) {
        }
    }

    public function getGiftcardList(): JsonResponse
    {
        try {
            if (!$this->accessToken) $this->getAuthToken();

            $url = "{$this->giftcardBaseUrl}/products";
            $headers = ["Authorization: Bearer {$this->accessToken}"];

            $data = $this->makeCurlRequest($url, 'GET', $headers);

            return response()->json([
                'status' => true,
                'message' => "Gift cards retrieved successfully",
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => "Gift cards could not be retrieved",
            ]);
        }
    }

}
