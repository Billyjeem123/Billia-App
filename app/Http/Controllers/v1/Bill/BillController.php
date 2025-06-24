<?php

namespace App\Http\Controllers\v1\Bill;

use App\Http\Controllers\Controller;
use App\Http\Requests\AirtimeRequest;
use App\Http\Requests\CableRequest;
use App\Http\Requests\ElectricityRequest;
use App\Http\Requests\GiftCardRequest;
use App\Http\Requests\GlobalRequest;
use App\Http\Requests\InternationalRequest;
use App\Http\Requests\JambRequest;
use App\Http\Requests\SmileRequest;
use App\Http\Requests\SpectranentRequest;
use App\Http\Requests\WaecDirectRequest;
use App\Services\VendingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    protected VendingService $vendingService;
    public function __construct()
    {
        $this->vendingService = new VendingService;
    }
    public function get_airtime_list()
    {
        return $this->vendingService->getAirtimeList();
    }
    public function get_international_countries(): JsonResponse
    {
        return $this->vendingService->get_international_countries();
    }

    public function get_waec_list(): JsonResponse
    {
        return $this->vendingService->getWaecList();
    }
    public function get_broadband_list(): JsonResponse
    {
        return $this->vendingService->getBroadbandLists();
    }
    public function get_jamb_list(): JsonResponse
    {
        return $this->vendingService->jambServices();
    }
    public function get_giftcard_list(): JsonResponse
    {
        return $this->vendingService->getGiftcardList();
    }
    public function get_cable_lists_option(Request $request): JsonResponse
    {
        $type = $request->input('type');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Cable type is required.'
            ], 400);
        }
        return $this->vendingService->getCableSubOption($type);
    }

    public function get_international_airtime_product_types(Request $request): JsonResponse
    {
        $type = $request->input('code');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Code is required.'
            ], 400);
        }
        return $this->vendingService->getInternationalAirtimeProductTypes($type);
    }
    public function get_broadband_lists_option(Request $request): JsonResponse
    {
        $type = $request->input('type');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'type is required.'
            ], 400);
        }
        return $this->vendingService->getBroadbandListsOption($type);
    }

    public function get_international_airtime_operators(Request $request): JsonResponse
    {
        $type = $request->input('code');
        $product_type = $request->input('product_type_id');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Code is required.'
            ], 400);
        }
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Product Type ID is required.'
            ], 400);
        }
        $data_to_send= [
            'code' => $type,
            'product_type' => $product_type
        ];
        return $this->vendingService->getInternationalAirtimeOperators($data_to_send);
    }
    public function get_international_airtime_variation(Request $request): JsonResponse
    {
        $type = $request->input('operator_id');
        $product_type = $request->input('product_type_id');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'OperatorID is required.'
            ], 400);
        }
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Product Type ID is required.'
            ], 400);
        }
        $data_to_send= [
            'operator_id' => $type,
            'product_type_id' => $product_type
        ];
        return $this->vendingService->getInternationalAirtimeVariation($data_to_send);
    }
    public function get_jamb_lists_option(Request $request): JsonResponse
    {
        $type = $request->input('type');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Jamb type is required.'
            ], 400);
        }
        return $this->vendingService->getJambSubOption($type);
    }
    public function get_waec_lists_option(Request $request): JsonResponse
    {
        $type = $request->input('type');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Waec type is required.'
            ], 400);
        }
        return $this->vendingService->getWaecSubOption($type);
    }
    public function get_electricity_lists_option(Request $request): JsonResponse
    {
        $type = $request->input('type');
        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Electricity type is required.'
            ], 400);
        }
        return $this->vendingService->getElectricitySubOption($type);
    }
    public function verify_cable(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $smart_card = $request->input('smartcard');

        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Cable type is required.'
            ], 400);
        }

        if (empty($smart_card)) {
            return response()->json([
                'status' => false,
                'message' => 'Smartcard number is required.'
            ], 400);
        }

        $verify_data = [
            'type' => $type,
            'smart_card' => $smart_card
        ];

        return $this->vendingService->verifyCable($verify_data);
    }
    public function verify_broadband_smile(Request $request): JsonResponse
    {
        $account = $request->input('account');

        if (empty($account)) {
            return response()->json([
                'status' => false,
                'message' => 'Account  is required.'
            ], 400);
        }

        return $this->vendingService->verifyBroadbandSmile($account);
    }
    public function verify_electricity(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $meter_number = $request->input('meter_number');
        $payment_type = $request->input('payment_type');

        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Electricity type is required.'
            ], 400);
        }

        if (empty($meter_number)) {
            return response()->json([
                'status' => false,
                'message' => 'Meter number is required.'
            ], 400);
        }
        if (empty($payment_type)) {
            return response()->json([
                'status' => false,
                'message' => 'Payment Type is required.'
            ], 400);
        }
        $verify_data = [
            'type' => $type,
            'meter_number' => $meter_number,
            'payment_type' => $payment_type
        ];
        return $this->vendingService->verifyElectricity($verify_data);
    }

    public function get_data_list(): JsonResponse
    {
        return $this->vendingService->getDataList();
    }
    public function get_cable_lists(): JsonResponse
    {
        return $this->vendingService->getCableList();
    }
    public function get_electricity_lists(): JsonResponse
    {
        return $this->vendingService->getElectricityList();
    }
    public function get_data_sub_option(): JsonResponse
    {
        return $this->vendingService->getDataSubOption();
    }
    public function buyAirtime(GlobalRequest $request): JsonResponse
    {
        $data_to_send = [
            'product_code' => $request->product_code,
            'amount' => $request->amount,
            'phone_number' => $request->phone_number
        ];
        return $this->vendingService->buyAirtime($data_to_send);
    }
    public function buy_cable(CableRequest $request): JsonResponse
    {
        $data_to_send = [
            'cable_type' => $request->input('cable_type'),
            'smartcard' => $request->input('smartcard'),
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'phone_number' => $request->input('phone_number')
        ];

        return $this->vendingService->buyCable($data_to_send);
    }
    public function buy_electricity(ElectricityRequest $request): JsonResponse
    {
        $data_to_send = [
            'electricity_type' => $request->input('electricity_type'),
            'meter_number' => $request->input('meter_number'),
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'phone_number' => $request->input('phone_number')
        ];

        return $this->vendingService->buyElectricity($data_to_send);
    }

    public function buyData(GlobalRequest $request): JsonResponse
    {
        $data_to_send = [
            'product_code' => $request->product_code,
            'amount' => $request->amount,
            'phone_number' => $request->phone_number,
            'variation_code' => $request->variation_code
        ];
        return $this->vendingService->buyData($data_to_send);
    }
    public function verify_jamb(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $jamb_id = $request->input('jamb_id');
        $variation_code = $request->input('variation_code');

        if (empty($type)) {
            return response()->json([
                'status' => false,
                'message' => 'Jamb type is required.'
            ], 400);
        }

        if (empty($jamb_id)) {
            return response()->json([
                'status' => false,
                'message' => 'Jamb ID  is required.'
            ], 400);
        }

        if (empty($variation_code)) {
            return response()->json([
                'status' => false,
                'message' => 'Variation Code  is required.'
            ], 400);
        }

        $verify_data = [
            'type' => $type,
            'jamb_id' => $jamb_id,
            'variation_code' => $variation_code
        ];

        return $this->vendingService->verifyJamb($verify_data);
    }

    public function buy_jamb(JambRequest $request): JsonResponse
    {
        $data_to_send = [
            'jamb_type' => $request->input('jamb_type'),
            'jamb_id' => $request->input('jamb_id'),
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'phone_number' => $request->input('phone_number')
        ];

        return $this->vendingService->buyJamb($data_to_send);
    }
    public function buy_waec_direct(WaecDirectRequest $request): JsonResponse
    {
        $data_to_send = [
            'waec_type' => $request->input('waec_type'),
            'quantity' => $request->input('quantity'),
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'phone_number' => $request->input('phone_number')
        ];

        return $this->vendingService->buyWaecDirect($data_to_send);
    }
    public function buy_international_airtime(InternationalRequest $request): JsonResponse
    {
        $data_to_send = [
            'country_code' => $request->input('country_code'),
            'operator_id' => $request->input('operator_id'),
            'product_type_id' => $request->input('product_type_id'),
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'phone_number' => $request->input('phone_number')
        ];
        return $this->vendingService->buyInternationalAirtime($data_to_send);
    }
    public function buy_broadband_spectranent(SpectranentRequest $request): JsonResponse
    {
        $data_to_send = [
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'quantity' => $request->input('quantity'),
            'phone_number' => $request->input('phone_number')
        ];
        return $this->vendingService->buyBroadbandSpectranent($data_to_send);
    }
    public function buy_broadband_smile(SmileRequest $request): JsonResponse
    {
        $data_to_send = [
            'variation_code' => $request->input('variation_code'),
            'amount' => $request->input('amount'),
            'account_id' => $request->input('account_id'),
            'phone_number' => $request->input('phone_number')
        ];
        return $this->vendingService->buyBroadbandSmile($data_to_send);
    }
    public function buy_giftcard(GiftCardRequest $request): JsonResponse
    {
        $data_to_send = [
            'product_id' => $request->input('product_id'),
            'amount' => $request->input('amount'),
            'recipient_email' => $request->input('recipient_email'),
            'recipient_country_code' => $request->input('recipient_country_code'),
            'quantity' => $request->input('quantity'),
            'recipient_phone' => $request->input('recipient_phone')
        ];
        return $this->vendingService->buyGiftcard($data_to_send);
    }
}
