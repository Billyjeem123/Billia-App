<?php

namespace App\Services;

use App\Models\TransactionLog;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VendingService {
    protected VtPassService $vtPassService;
    protected reloadlyService $reloadlyService;

    protected  $tracker;
    public function __construct()
    {
        $this->vtPassService = new VtPassService;
        $this->reloadlyService = new ReloadlyService;
        $this->tracker = new ActivityTracker();
    }

    public function getAirtimeList(): JsonResponse
    {

        $this->tracker->track('airtime_plan', "viewed available airtime plan", [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_AIRTIME_VENDING',
            'airtime',
            'getAirtimeList'
        );

    }

    public function getDataList(): JsonResponse
    {

        $this->tracker->track('data_plan', "viewed available data plan", [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_DATA_VENDING',
            'data',
            'getDataList'
        );
    }
    public function getBroadbandLists(): JsonResponse
    {

        $this->tracker->track('broad_band_list', "viewed available broad band list plan", [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_BROADBAND_VENDING',
            'broadband',
            'getBroadbandLists'
        );
    }
    public function getCableList(): JsonResponse
    {

        $this->tracker->track('cable_list', "viewed available cable plan", [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_CABLE_VENDING',
            'cable',
            'getCableList'
        );
    }
    public function getWaecList(): JsonResponse
    {
        $this->tracker->track('cable_list', "viewed waec services", [
            "effective" => true,
        ]);

        return $this->processVendingList(
            'ACTIVE_WAEC_VENDING',
            'waec',
            'getWaecList'
        );
    }
    public function getGiftcardList(): JsonResponse
    {

        $this->tracker->track('cable_list', "viewed gift card lis", [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_GIFTCARD_VENDING',
            'giftcard',
            'getGiftcardList'
        );
    }
    public function get_international_countries(): JsonResponse
    {
        $this->tracker->track('international_countries_airtime', "viewed list of international countries airtime", [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalCountries'
        );
    }
    public function jambServices(): JsonResponse
    {
        $this->tracker->track('jamb_list', "viewed list of jamb services", [
            "effective" => true,
        ]);

        return $this->processVendingList(
            'ACTIVE_JAMB_VENDING',
            'jamb',
            'jambServices'
        );
    }
    public function getElectricityList(): JsonResponse
    {
        $this->tracker->track('electricity_options', "viewed list of electricity provider", [
            "effective" => true,
        ]);
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
        $dataCode = request()->input('data_code');
        $this->tracker->track('data_subscription_options', "viewed data subscription options for provider: " . $dataCode, [
            "effective" => true,
            "data_code" => $dataCode,
            "provider" => $dataCode // You might want to extract just the provider name
        ]);
        return $this->processVendingList(
            'ACTIVE_DATA_VENDING',
            'data',
            'getDataSubOption'
        );
    }

    public function getCableSubOption($type = null): JsonResponse
    {

        $dataCode = request()->input('type');
        $this->tracker->track('cable_sub_options', "viewed cable subscription options for provider: " . $dataCode, [
            "effective" => true,
            "data_code" => $dataCode,
            "provider" => $dataCode // You might want to extract just the provider name
        ]);
        return $this->processVendingList(
            'ACTIVE_CABLE_VENDING',
            'cable',
            'getCableSubOption',
            $type
        );
    }
    public function getBroadbandListsOption($type = null): JsonResponse
    {

        $dataCode = request()->input('type');
        $this->tracker->track('broadband_list_option', "viewed broadband options for provider: " . $dataCode, [
            "effective" => true,
            "data_code" => $dataCode,
            "provider" => $dataCode // You might want to extract just the provider name
        ]);
        return $this->processVendingList(
            'ACTIVE_BROADBAND_VENDING',
            'broadband',
            'getBroadbandListsOption',
            $type
        );
    }

    public function getInternationalAirtimeOperators($type = null): JsonResponse
    {
        $operatorType = $type ?? request()->input('type');
        $this->tracker->track('international_airtime_operators', "viewed international airtime operators for type: " . ($operatorType ?? 'all'), [
            "effective" => true,
            "type" => $operatorType,
            "operator_type" => $operatorType,
            "action" => "view_operators"
        ]);
        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalAirtimeOperators',
            $type
        );
    }
    public function getInternationalAirtimeVariation($type = null): JsonResponse
    {
        $variationType = $type ?? request()->input('type');
        $this->tracker->track('international_airtime_variation', "viewed international airtime variations for type: " . ($variationType ?? 'all'), [
            "effective" => true,
            "type" => $variationType,
            "variation_type" => $variationType,
            "action" => "view_variations"
        ]);

        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalAirtimeVariation',
            $type
        );
    }
    public function getInternationalAirtimeProductTypes($type = null): JsonResponse
    {

        $productType = $type ?? request()->input('type');

        $this->tracker->track('international_airtime_product_types', "viewed international airtime product types for type: " . ($productType ?? 'all'), [
            "effective" => true,
            "type" => $productType,
            "product_type" => $productType,
            "action" => "view_product_types"
        ]);

        return $this->processVendingList(
            'ACTIVE_INTERNATIONAL_AIRTIME',
            'international_airtime',
            'getInternationalAirtimeProductTypes',
            $type
        );
    }
    public function getJambSubOption($type = null): JsonResponse
    {
        $jambType = $type ?? request()->input('type');
        $this->tracker->track('jamb_subscription_options', "viewed JAMB subscription options for type: " . ($jambType ?? 'all'), [
            "effective" => true,
            "type" => $jambType,
            "jamb_type" => $jambType,
            "action" => "view_jamb_options"
        ]);

        return $this->processVendingList(
            'ACTIVE_JAMB_VENDING',
            'jamb',
            'getJambSubOption',
            $type
        );
    }
    public function getWaecSubOption($type = null): JsonResponse
    {

        $waec = $type ?? request()->input('type');
        $this->tracker->track('waec_subscription_options', "viewed WAEC subscription options for type: " . ($waec ?? 'all'), [
            "effective" => true,
            "type" => $waec,
            "jamb_type" => $waec,
            "action" => "view_jamb_options"
        ]);

        return $this->processVendingList(
            'ACTIVE_WAEC_VENDING',
            'waec',
            'getWaecSubOption',
            $type
        );
    }
    public function getElectricitySubOption($type = null): JsonResponse
    {

        $waec = $type ?? request()->input('type');
        $this->tracker->track('electricity_options_list', "viewed Electricity subscription options for type: " . ($waec ?? 'all'), [
            "effective" => true,
            "action" => "view_electricity_options"
        ]);

        return $this->processVendingList(
            'ACTIVE_ELECTRICITY_VENDING',
            'cable',
            'getElectricitySubOption',
            $type
        );
    }

    public function verifyCable($param = null): JsonResponse
    {

        $this->tracker->track(
            'verify_cable_data',
            "Verify cable details for: " . (is_array($param) ? json_encode($param) : ($param ?? 'all')),
            [
                "effective" => true,
                "action" => "view_electricity_options"
            ]
        );


        return $this->processVendingList(
            'ACTIVE_CABLE_VENDING',
            'cable',
            'verifyCable',
            $param
        );
    }
    public function verifyJamb($param = null): JsonResponse
    {
        $this->tracker->track(
            'verify_jamb_data',
            "Verify JAMB details for: " . (is_array($param) ? json_encode($param) : ($param ?? 'all')),
            [
                "effective" => true,
                "action" => "view_jamb_options"
            ]
        );

        return $this->processVendingList(
            'ACTIVE_JAMB_VENDING',
            'jamb',
            'verifyJamb',
            $param
        );
    }

    public function verifyElectricity($param = null): JsonResponse
    {

        $this->tracker->track('verify_electricity_request', "Verify electricity details  for : " . ($param ?? 'all'), [
            "effective" => true,
        ]);
        return $this->processVendingList(
            'ACTIVE_ELECTRICITY_VENDING',
            'electricity',
            'verifyElectricity',
            $param
        );
    }
    public function verifyBroadbandSmile($param = null): JsonResponse
    {

        $this->tracker->track('verify_broadband_smile', "Verify broad band details  for : " . ($param ?? 'all'), [
            "effective" => true,
        ]);

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
        $this->tracker->track(
            'purchase_airtime',
            "processing to purchase ₦" . number_format($data['amount']) . " airtime for: " . ($data['phone_number'] ?? 'all'),
            [
                "effective" => true,
            ]
        );

        return $this->processVendingRequest(
            $data,
            "airtime",
            "ACTIVE_AIRTIME_VENDING",
            "buyAirtime"
        );
    }


    public function buyWaecDirect($data): JsonResponse
    {
        $this->tracker->track(
            'purchase_waec_direct',
            "processioning to purchase  WAEC result pin(s) worth ₦" . number_format($data['amount'] ?? 0),
            [
                "quantity" => $data['quantity'] ?? 1,
                "effective" => true,
            ]
        );

        return $this->processVendingRequest(
            $data,
            "waec",
            "ACTIVE_WAEC_VENDING",
            'buyWaecDirect'
        );
    }
    public function buyCable($data): JsonResponse
    {
        $this->tracker->track(
            'purchase_cable',
            "processing to purchase ₦" . number_format($data['amount']) . " " . strtoupper($data['cable_type']) . " subscription ("
            . ($data['variation_code'] ?? 'N/A') . ") for smartcard: " . ($data['smartcard'] ?? 'N/A'),
            [
                "phone" => $data['phone_number'] ?? null,
                "smartcard" => $data['smartcard'] ?? null,
                "variation_code" => $data['variation_code'] ?? null,
                "cable_type" => $data['cable_type'] ?? null,
                "effective" => true,
            ]
        );

        return $this->processVendingRequest(
            $data,
            "cable",
            "ACTIVE_CABLE_VENDING",
            'buyCable'
        );
    }

    public function buyElectricity($data): JsonResponse
    {

        $this->tracker->track(
            'purchase_electricity',
            "processing to purchase ₦" . number_format($data['amount']) . " electricity for meter: " . ($data['meter_number'] ?? 'N/A')
            . " (" . strtoupper(str_replace('-', ' ', $data['electricity_type'] ?? '')) . ", " . ($data['variation_code'] ?? 'N/A') . ")",
            [
                "phone" => $data['phone_number'] ?? null,
                "meter_number" => $data['meter_number'] ?? null,
                "variation_code" => $data['variation_code'] ?? null,
                "electricity_type" => $data['electricity_type'] ?? null,
                "effective" => true,
            ]
        );

        return $this->processVendingRequest(
            $data,
            "electricity",
            "ACTIVE_ELECTRICITY_VENDING",
            'buyElectricity'
        );
    }

    public function buyData($data): JsonResponse
    {

        $this->tracker->track(
            'purchase_data',
            "processing to purchase ₦" . number_format($data['amount']) . " data (" . ($data['variation_code'] ?? 'N/A') . ") for: " . ($data['phone_number'] ?? 'N/A'),
            [
                "phone" => $data['phone_number'] ?? null,
                "product_code" => $data['product_code'] ?? null,
                "variation_code" => $data['variation_code'] ?? null,
                "effective" => true,
            ]
        );

        return $this->processVendingRequest(
            $data,
            "data",
            "ACTIVE_DATA_VENDING",
            'buyData'
        );
    }
    public function buyInternationalAirtime($data): JsonResponse
    {
        $this->tracker->track(
            'purchase_international_airtime',
            "processing to purchase ₦" . number_format($data['amount']) . " international airtime for: " . ($data['phone_number'] ?? 'N/A')
            . " (Country: " . strtoupper($data['country_code'] ?? 'N/A') . ", Variation: " . ($data['variation_code'] ?? 'N/A') . ")",
            [
                "phone" => $data['phone_number'] ?? null,
                "country_code" => $data['country_code'] ?? null,
                "operator_id" => $data['operator_id'] ?? null,
                "product_type_id" => $data['product_type_id'] ?? null,
                "variation_code" => $data['variation_code'] ?? null,
                "effective" => true,
            ]
        );
        return $this->processVendingRequest(
            $data,
            "international_airtime",
            "ACTIVE_INTERNATIONAL_AIRTIME",
            'buyInternationalAirtime'
        );
    }
    public function buyJamb($data): JsonResponse
    {

        $this->tracker->track(
            'purchase_jamb',
            "processing to purchase ₦" . number_format($data['amount']) . " JAMB PIN (" . strtoupper($data['variation_code'] ?? 'N/A') . ") for: " . ($data['jamb_id'] ?? 'N/A'),
            [
                "phone" => $data['phone_number'] ?? null,
                "jamb_id" => $data['jamb_id'] ?? null,
                "variation_code" => $data['variation_code'] ?? null,
                "jamb_type" => $data['jamb_type'] ?? null,
                "effective" => true,
            ]
        );

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

        $this->tracker->track(
            'purchase_broadband_smile',
            "processing to purchase ₦" . number_format($data['amount']) . " Smile Broadband plan (" . ($data['variation_code'] ?? 'N/A') . ") for account: " . ($data['account_id'] ?? 'N/A'),
            [
                "phone" => $data['phone_number'] ?? null,
                "account_id" => $data['account_id'] ?? null,
                "variation_code" => $data['variation_code'] ?? null,
                "effective" => true,
            ]
        );

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

        app('ActivityTracker')->track(
            'purchased_vending_successful',
            "Successfully processed ₦" . number_format($amount) . " vending for: " . ucfirst($data['vending_type']),
            [
                "vending_type" => $data['vending_type'] ?? null,
                "amount" => $amount,
                "wallet_id" => $data['wallet_id'],
                "transaction_id" => $transaction_data['transaction_id'],
                "effective" => true,
            ]
        );
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
