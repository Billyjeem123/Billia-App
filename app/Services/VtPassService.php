<?php

namespace App\Services;

use App\Helpers\Utility;
use App\Models\TransactionLog;
use App\Models\Wallet;
use GuzzleHttp\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VtPassService {
    private array $dataAirtimeList = [];
    private array $cableList = [];
    private array $electricityList = [];
    private array $waecServices = [];
    private array $jambServices = [];
    private array $broadBandServices = [];
    private string $base_url;

    public function __construct()
    {
        $this->base_url = env('VTPASS_BASE_URL');

        $this->dataAirtimeList = [
            ['name' => 'Mtn', 'img' => asset('assets/images/vtu/mtn.jpg'), 'airtime_code' => "mtn", 'data_code' => "mtn-data"],
            ['name' => '9mobile', 'img' => asset('assets/images/vtu/etisalat.jpg'), 'airtime_code' => "etisalat", 'data_code' => "etisalat-data"],
            ['name' => 'Airtel', 'img' => asset('assets/images/vtu/airtel.jpg'), 'airtime_code' => "airtel", 'data_code' => "airtel-data"],
            ['name' => 'Glo', 'img' => asset('assets/images/vtu/glo.jpg'), 'airtime' => "glo_code", 'data_code' => "glo-data"],
        ];

        $this->cableList = [
            ['name' => 'DSTV', 'img' => asset('assets/images/cable/dstv.jpg'), 'serviceID' => "dstv", 'convenience_fee' => 0],
            ['name' => 'GOTV Payment', 'img' => asset('assets/images/cable/gotv.jpg'), 'serviceID' => "gotv", 'convenience_fee' => 0],
            ['name' => 'StarTimes Subscription', 'img' => asset('assets/images/cable/startimes.jpg'), 'serviceID' => "startimes", 'convenience_fee' => 0],
            ['name' => 'ShowMax', 'img' => asset('assets/images/cable/showmax.jpg'), 'serviceID' => "showmax", 'convenience_fee' => 0]
        ];

        $this->electricityList = [
            ['name' => 'Ikeja Electric - IKEDC', 'img' => asset('assets/images/electricity/ikeja.png'), 'serviceID' => "ikeja-electric", 'convenience_fee' => 0],
            ['name' => 'Eko Electric - EKEDC', 'img' => asset('assets/images/electricity/ekedc.jpg'), 'serviceID' => "eko-electric", 'convenience_fee' => 0],
            ['name' => 'Kano Electric - KEDCO', 'img' => asset('assets/images/electricity/kano.png'), 'serviceID' => "kano-electric", 'convenience_fee' => 0],
            ['name' => 'Port Harcourt Electric - PHED', 'img' => asset('assets/images/electricity/ph.jpeg'), 'serviceID' => "portharcourt-electric", 'convenience_fee' => 0],
            ['name' => 'Jos Electric - JED', 'img' => asset('assets/images/electricity/jos.jpeg'), 'serviceID' => "jos-electric", 'convenience_fee' => 0],
            ['name' => 'Ibadan Electric - IBEDC', 'img' => asset('assets/images/electricity/ibd.png'), 'serviceID' => "ibadan-electric", 'convenience_fee' => 0],
            ['name' => 'Kaduna Electric - KAEDCO', 'img' => asset('assets/images/electricity/kaduna.jpeg'), 'serviceID' => "kaduna-electric", 'convenience_fee' => 0],
            ['name' => 'Abuja Electric - AEDC', 'img' => asset('assets/images/electricity/abuja.jpeg'), 'serviceID' => "abuja-electric", 'convenience_fee' => 0],
            ['name' => 'Enugu Electric - EEDC', 'img' => asset('assets/images/electricity/enugu.png'), 'serviceID' => "enugu-electric", 'convenience_fee' => 0],
            ['name' => 'Benin Electric - BEDC', 'img' => asset('assets/images/electricity/benin.jpeg'), 'serviceID' => "benin-electric", 'convenience_fee' => 0],
        ];

        $this->waecServices = [
            ['name' => 'WAEC Result Checker', 'serviceID' => "waec", 'convenience_fee' => 0],
            ['name' => 'WAEC Registration', 'serviceID' => "waec-registration", 'convenience_fee' => 0],
        ];
        $this->broadBandServices = [
            ['name' => 'Simile Data', 'serviceID' => "smile-direct  ", 'convenience_fee' => 0],
            ['name' => 'Spectranet', 'serviceID' => "spectranet", 'convenience_fee' => 0],
        ];
        $this->jambServices = [
            ['name' => 'Jamb Pin', 'serviceID' => "jamb", 'convenience_fee' => 0],
        ];
    }

    private static function process_response(string $response, $data): JsonResponse
    {
        $response_array = json_decode($response, true);
        if (is_null($response_array)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or malformed provider response. Please try again later.'
            ], 500);
        }
        if ($response_array['code'] == '000') {
            TransactionLog::update_info($data['transaction_id'],['status' =>  'successful', 'provider_response' => $response]);
            if ($data['service_type'] ==='electricity'){
                $message = $response_array['response_description']. " Please Find Attach your ". $response_array['purchased_code'];
            }elseif ($data['service_type'] ==='jamb'){
                $message = $response_array['response_description']. " Please Find Attach your ". $response_array['purchased_code'];
            }
            else{
                $message = $response_array['response_description'];
            }
            if ($data['service_type'] =='waec'){
                $pins = $response_array['cards'];
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'pins' => $pins
                ]);
            }
            return response()->json([
                'status' => true,
                'message' => $message
            ]);
        }else{
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $response, 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => $response_array['response_description']
            ]);
        }
    }

    public function getAirtimeList(): JsonResponse
    {
        $airtimeList = array_map(function($service) {
            unset($service['data_code']);
            return $service;
        }, $this->dataAirtimeList);
        return response()->json([
            'status' => true,
            'message' => "Airtime list retrieved successfully",
            'airtime_list' => array_values($airtimeList) // Renaming the key
        ], 200);
    }
    public function getCableList(): JsonResponse
    {
        $cableList = $this->cableList;
        return response()->json([
            'status' => true,
            'message' => "Cable list retrieved successfully",
            'cable_list' =>$cableList
        ], 200);
    }

    public function getWaecList(): JsonResponse
    {
        $waec = $this->waecServices;
        return response()->json([
            'status' => true,
            'message' => "Waec list retrieved successfully",
            'cable_list' =>$waec
        ], 200);
    }
    public function getBroadbandLists(): JsonResponse
    {
        $broadnand = $this->broadBandServices;
        return response()->json([
            'status' => true,
            'message' => "Broadband list retrieved successfully",
            'broadband_lists' =>$broadnand
        ], 200);
    }
    public function getInternationalCountries(): JsonResponse
    {
        $host = $this->base_url."get-international-airtime-countries";
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Countries list retrieved successfully",
                'data' => $response_array['content']['countries']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function jambServices(): JsonResponse
    {
        $jamb = $this->jambServices;
        return response()->json([
            'status' => true,
            'message' => "Jamb list retrieved successfully",
            'jamb_list' =>$jamb
        ], 200);
    }
    public function getElectricityList(): JsonResponse
    {
        $electricityList = $this->electricityList;
        return response()->json([
            'status' => true,
            'message' => "Electricity list retrieved successfully",
            'cable_list' =>$electricityList
        ], 200);
    }

    public function getDataList(): JsonResponse
    {
        $airtimeList = array_map(function($service) {
            unset($service['airtime_code']);
            return $service;
        }, $this->dataAirtimeList);
        return response()->json([
            'status' => true,
            'message' => "Data list retrieved successfully",
            'airtime_list' => array_values($airtimeList)
        ], 200);
    }



    public function getDataSubOption():JsonResponse
    {
        $sub_code = request('data_code');
        $host = $this->base_url."service-variations?serviceID=".$sub_code;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Data list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getBroadbandListsOption($type):JsonResponse
    {
        $host = $this->base_url."service-variations?serviceID=".$type;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Broadband list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getCableSubOption($type):JsonResponse
    {
        $host = $this->base_url."service-variations?serviceID=".$type;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Data list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getInternationalAirtimeProductTypes($type):JsonResponse
    {
        $host = $this->base_url."get-international-airtime-product-types?code=".$type;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Data list retrieved successfully",
                'data' => $response_array['content']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getInternationalAirtimeOperators($data):JsonResponse
    {
        $host = $this->base_url."get-international-airtime-operators?code=".$data['code']."&product_type_id=".$data['product_type'];
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Operator list retrieved successfully",
                'data' => $response_array['content']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getInternationalAirtimeVariation($data):JsonResponse
    {
        $host = $this->base_url."service-variations?serviceID=foreign-airtime&operator_id=".$data['operator_id']."&product_type_id=".$data['product_type_id'];
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Variation list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getWaecSubOption($type):JsonResponse
    {
        $host = $this->base_url."service-variations?serviceID=".$type;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Data list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getJambSubOption($type):JsonResponse
    {
        $host = $this->base_url."service-variations?serviceID=".$type;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Jamb list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function getElectricitySubOption($type):JsonResponse
    {
        $host = $this->base_url."service-variations?serviceID=".$type;
        $response = self::sendApiRequest($host, [],'GET');
        $response_array = json_decode($response, true);
        if ($response_array['response_description'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Electricity list retrieved successfully",
                'data' => $response_array['content']['variations']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to retrieve data",
            ]);
        }

    }
    public function verifyCable($data):JsonResponse
    {
        $host = $this->base_url."merchant-verify";
        $data_to_send  = [
            'billersCode' =>  $data['smart_card'],
            'serviceID' => $data['type']
        ];
        $response = self::sendApiRequest($host, $data_to_send,'POST');
        $response_array = json_decode($response, true);
        if ($response_array['code'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Verification successfully",
                'data' => $response_array['content']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to verify data",
            ]);
        }

    }
    public function verifyJamb($data):JsonResponse
    {
        $host = $this->base_url."merchant-verify";
        $data_to_send  = [
            'billersCode' =>  $data['jamb_id'],
            'serviceID' => $data['type'],
            'type' => $data['variation_code']
        ];
        $response = self::sendApiRequest($host, $data_to_send,'POST');
        $response_array = json_decode($response, true);
        if ($response_array['code'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Verification successfully",
                'data' => $response_array['content']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to verify data",
            ]);
        }

    }
    public function verifyElectricity($data):JsonResponse
    {
        $host = $this->base_url."merchant-verify";
        $data_to_send  = [
            'billersCode' =>  $data['meter_number'],
            'serviceID' => $data['type'],
            'type' => $data['payment_type']
        ];
        $response = self::sendApiRequest($host, $data_to_send,'POST');
        $response_array = json_decode($response, true);
        if ($response_array['code'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Verification successfully",
                'data' => $response_array['content']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to verify data",
            ]);
        }

    }
    public function verifyBroadbandSmile($data):JsonResponse
    {
        $host = $this->base_url."merchant-verify";
        $data_to_send  = [
            'billersCode' =>  $data,
            'serviceID' => 'smile-direct',
        ];
        $response = self::sendApiRequest($host, $data_to_send,'POST');
        $response_array = json_decode($response, true);
        if ($response_array['code'] == '000') {
            return response()->json([
                'status' => true,
                'message' => "Verification successfully",
                'data' => $response_array['content']
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to verify data",
            ]);
        }

    }

    public function buyAirtime($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => $data['product_code'],
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed",
                'data' =>Utility::getExceptionDetails($e)
            ]);

        }
    }
    public function buyData($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => $data['product_code'],
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'billersCode'  => $data['phone_number'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyCable($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => $data['cable_type'],
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'billersCode'  => $data['smartcard'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyJamb($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => $data['jamb_type'],
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'billersCode'  => $data['jamb_id'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyWaecDirect($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => $data['waec_type'],
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'quantity'  => $data['quantity'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyElectricity($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => $data['electricity_type'],
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'billersCode'  => $data['meter_number'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyBroadbandSpectranent($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => 'spectranet',
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'quantity'  => $data['quantity'],
                'billersCode'  => $data['phone_number'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyBroadbandSmile($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => 'smile-direct',
                'amount'  => $data['amount'],
                'phone'  => $data['phone_number'],
                'billersCode'  => $data['account_id'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }
    public function buyInternationalAirtime($data):JsonResponse
    {
        try{
            $data_to_send = [
                'request_id' => self::generateRequestId(),
                'serviceID'  => "foreign-airtime",
                'amount'  => $data['amount'],
                'billersCode'  => $data['phone_number'],
                'operator_id'  => $data['operator_id'],
                'country_code'  => $data['country_code'],
                'product_type_id'  => $data['product_type_id'],
                'phone'  => $data['phone_number'],
                'variation_code'  => $data['variation_code'],
            ];
            $url =  $this->base_url.'pay';
            $response = self::sendApiRequest($url, $data_to_send,  'POST');
            return self::process_response($response, $data);
        }catch (\Throwable $e){
            TransactionLog::update_info($data['transaction_id'],['status' =>  'failed', 'provider_response' => $e->getMessage(), 'amount_after' => $data['amount_after'] + $data['amount']]);
            Wallet::add_to_wallet($data['amount']);
            return response()->json([
                'status' => false,
                'message' => "Transaction Failed"
            ]);

        }
    }



    private static function sendApiRequest(string $url, array $data = [], string $method = 'GET'): string
    {
        try {
            $client = new Client([
                'timeout' => 90,
            ]);
            $headers = [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(env('VTPASS_USERNAME') . ':' . env('VTPASS_PASSWORD')),
            ];
            $options = ['headers' => $headers];
            if ($method === 'GET' && !empty($data)) {
                $url .= '?' . http_build_query($data);
            } elseif ($method === 'POST' && !empty($data)) {
                $options['json'] = $data;
            }
            $response = $client->request($method, $url, $options);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return "Error: " . $errorMessage;
        }
    }

    public static function generateRequestId(): string
    {
        $timestamp = Carbon::now('Africa/Lagos')->format('YmdHisv');
        $randomString = Str::upper(Str::random(6));
        return $timestamp . $randomString;
    }



}
