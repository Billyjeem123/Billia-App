<?php

namespace App\Services;

use App\Models\TransactionLog;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VendingService {
    protected VtPassService $vtPassService;
    protected reloadlyService $reloadlyService;
    public function __construct()
    {
        $this->vtPassService = new VtPassService;
        $this->reloadlyService = new ReloadlyService;
    }

    public function getAirtimeList(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_AIRTIME_VENDING',
            'airtime',
            'getAirtimeList'
        );
    }

    public function getDataList(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_DATA_VENDING',
            'data',
            'getDataList'
        );
    }
    public function getBroadbandLists(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_BROADBAND_VENDING',
            'broadband',
            'getBroadbandLists'
        );
    }
    public function getCableList(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_CABLE_VENDING',
            'cable',
            'getCableList'
        );
    }
    public function getWaecList(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_WAEC_VENDING',
            'waec',
            'getWaecList'
        );
    }
    public function getGiftcardList(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_GIFTCARD_VENDING',
            'giftcard',
            'getGiftcardList'
        );
    }
    public function get_international_countries(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalCountries'
        );
    }
    public function jambServices(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_JAMB_VENDING',
            'jamb',
            'jambServices'
        );
    }
    public function getElectricityList(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_ELECTRICITY_VENDING',
            'electricity',
            'getElectricityList'
        );
    }


    private function processVendingList(string $envKey, string $type, string $method, $param = null): JsonResponse
    {
        $active_vending = env($envKey, 'vtpass');
        $service = $this->resolveVendingService($active_vending);

        if (!$service || !method_exists($service, $method)) {
            return response()->json([
                'status' => false,
                'message' => "Invalid {$type} vending provider or method"
            ], 400);
        }

        $reflection = new \ReflectionMethod($service, $method);
        if ($reflection->getNumberOfParameters() > 0 && $param !== null) {
            return $service->$method($param);
        }

        return $service->$method();
    }


    public function getDataSubOption(): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_DATA_VENDING',
            'data',
            'getDataSubOption'
        );
    }

    public function getCableSubOption($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_CABLE_VENDING',
            'cable',
            'getCableSubOption',
            $type
        );
    }
    public function getBroadbandListsOption($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_BROADBAND_VENDING',
            'broadband',
            'getBroadbandListsOption',
            $type
        );
    }

    public function getInternationalAirtimeOperators($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalAirtimeOperators',
            $type
        );
    }
    public function getInternationalAirtimeVariation($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalAirtimeVariation',
            $type
        );
    }
    public function getInternationalAirtimeProductTypes($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalAirtimeProductTypes',
            $type
        );
    }
    public function getJambSubOption($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_JAMB_VENDING',
            'jamb',
            'getJambSubOption',
            $type
        );
    }
    public function getWaecSubOption($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_WAEC_VENDING',
            'waec',
            'getWaecSubOption',
            $type
        );
    }
    public function getElectricitySubOption($type = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_ELECTRICITY_VENDING',
            'cable',
            'getElectricitySubOption',
            $type
        );
    }

    public function verifyCable($param = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_CABLE_VENDING',
            'cable',
            'verifyCable',
            $param
        );
    }
    public function verifyJamb($param = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_JAMB_VENDING',
            'jamb',
            'verifyJamb',
            $param
        );
    }
    public function verifyElectricity($param = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_ELECTRICITY_VENDING',
            'electricity',
            'verifyElectricity',
            $param
        );
    }
    public function verifyBroadbandSmile($param = null): JsonResponse
    {
        return $this->processVendingList(
            'ACTIVE_BROADBAND_VENDING',
            'braodband',
            'verifyBroadbandSmile',
            $param
        );
    }


    private function resolveVendingService(string $providerKey): ?object
    {
        $services = [
            'vtpass' => $this->vtPassService,
            'reloadly' => $this->reloadlyService
        ];
        return $services[$providerKey] ?? null;
    }

    private function processVendingRequest(array $data, string $type, string $envKey, string $method): JsonResponse
    {
        $result = self::validateAndProcessVending($data, $type);
        if (!$result['status']) {
            return response()->json($result);
        }

        $validated_payload = $result['data'];
        $active_vending = env($envKey, 'vtpass');
        $service = $this->resolveVendingService($active_vending);
        if ($service && method_exists($service, $method)) {
            return $service->$method($validated_payload);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid ' . ucfirst($type) . ' vending provider or method'
        ], 400);
    }


    public function buyAirtime($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "airtime",
            "ACTIVE_AIRTIME_VENDING",
            "buyAirtime"
        );
    }


    public function buyWaecDirect($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "waec",
            "ACTIVE_WAEC_VENDING",
            'buyWaecDirect'
        );
    }
    public function buyCable($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "cable",
            "ACTIVE_CABLE_VENDING",
            'buyCable'
        );
    }
    public function buyElectricity($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "electricity",
            "ACTIVE_ELECTRICITY_VENDING",
            'buyElectricity'
        );
    }

    public function buyData($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "data",
            "ACTIVE_DATA_VENDING",
            'buyData'
        );
    }
    public function buyInternationalAirtime($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "international_airtime",
            "ACTIVE_INTERNATIONAL_AIRTIME",
            'buyInternationalAirtime'
        );
    }
    public function buyJamb($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "jamb",
            "ACTIVE_JAMB_VENDING",
            'buyJamb'
        );
    }
    public function buyBroadbandSpectranent($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "broadband",
            "ACTIVE_BROADBAND_VENDING",
            'buyBroadbandSpectranent'
        );
    }
    public function buyBroadbandSmile($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "broadband",
            "ACTIVE_BROADBAND_VENDING",
            'buyBroadbandSmile'
        );
    }
    public function buyGiftcard($data): JsonResponse
    {
        return $this->processVendingRequest(
            $data,
            "giftcard",
            "ACTIVE_GIFTCARD_VENDING",
            'buyGiftcard'
        );
    }


    private static function process_vending($data):array{
        $check_balance = Wallet::check_balance();
        $amount =  abs($data['amount']);

        if ($data['vending_type'] === 'giftcard'){
            $dollar_rate = 1600;
            $amount = $amount * $dollar_rate;
            $data['amount_giftcard'] = $amount;
        }


        if ($amount > $check_balance) {
            return [
                'status' => false,
                'message' => 'Insufficient balance'
            ];
        }

        $data['service_type'] = $data['vending_type'] ?? '';
        $data['amount_after'] = $check_balance - $amount;
        $data['provider']  =  env('ACTIVE_AIRTIME_VENDING');
        $data['channel']  =  'Internal';
        $data['type']  =  'debit';
        $data['wallet_id'] = Auth::user()->wallet->id;
        $data['description'] = self::getDescription($data);


        Wallet::remove_From_wallet($amount);
        $transaction_data = TransactionLog::create_transaction($data);
        $data['transaction_id'] = $transaction_data['transaction_id'];
        return [
            'status' => true,
            'data' => $data
        ];
    }



    private static function getDescription(array $data): string
    {
        switch ($data['vending_type']) {
            case 'airtime':
                return "Payment for airtime to {$data['phone_number']}";

            case 'data':
                return "Payment for data bundle to {$data['phone_number']}";

            case 'waec':
            case 'jamb':
            case 'neco':
                $qty = $data['quantity'] ?? 1;
                $type = $data['waec_type'] ?? $data['vending_type'];
                return "Payment for {$type} PIN - {$qty} unit(s)";

            case 'giftcard':
                return "Payment for gift card worth \${$data['amount']}";

            default:
                return "Payment for {$data['vending_type']}";
        }
    }


    private static function validateAndProcessVending(array $data, string $type): array
    {
        $data['vending_type'] = $type;
        $validate_order = self::process_vending($data);
        if (!$validate_order['status']) {
            return [
                'status' => false,
                'message' => $validate_order['message']
            ];
        }
        return [
            'status' => true,
            'data' => $validate_order['data']
        ];
    }



}
