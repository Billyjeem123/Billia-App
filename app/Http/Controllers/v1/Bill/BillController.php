<?php

namespace App\Http\Controllers\v1\Bill;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
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
        $validated = $request->validated();

        $data_to_send = [
            'product_code'   => $validated['product_code'],
            'amount'         => $validated['amount'],
            'phone_number'   => $validated['phone_number'],
        ];

        return $this->vendingService->buyAirtime($data_to_send);
    }

    public function buy_cable(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'cable_type'      => $validated['cable_type'],
            'smartcard'       => $validated['smartcard'],
            'variation_code'  => $validated['variation_code'],
            'amount'          => $validated['amount'],
            'phone_number'    => $validated['phone_number'],
        ];

        return $this->vendingService->buyCable($data_to_send);
    }

    public function buy_electricity(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'electricity_type' => $validated['electricity_type'],
            'meter_number'     => $validated['meter_number'],
            'variation_code'   => $validated['variation_code'],
            'amount'           => $validated['amount'],
            'phone_number'     => $validated['phone_number'],
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
    public function verify_jamb(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $verify_data = [
            'type'           => $validated['type'],
            'jamb_id'        => $validated['jamb_id'],
            'variation_code' => $validated['variation_code'],
        ];

        return $this->vendingService->verifyJamb($verify_data);
    }


    public function buy_jamb(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'jamb_type'      => $validated['jamb_type'],
            'jamb_id'        => $validated['jamb_id'],
            'variation_code' => $validated['variation_code'],
            'amount'         => $validated['amount'],
            'phone_number'   => $validated['phone_number'],
        ];

        return $this->vendingService->buyJamb($data_to_send);
    }

    public function buy_waec_direct(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'waec_type'      => $validated['waec_type'],
            'quantity'       => $validated['quantity'],
            'variation_code' => $validated['variation_code'],
            'amount'         => $validated['amount'],
            'phone_number'   => $validated['phone_number'],
        ];

        return $this->vendingService->buyWaecDirect($data_to_send);
    }


    public function buy_international_airtime(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'country_code'     => $validated['country_code'],
            'operator_id'      => $validated['operator_id'],
            'product_type_id'  => $validated['product_type_id'],
            'variation_code'   => $validated['variation_code'],
            'amount'           => $validated['amount'],
            'phone_number'     => $validated['phone_number'],
        ];

        return $this->vendingService->buyInternationalAirtime($data_to_send);
    }

    public function buy_broadband_spectranent(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'variation_code' => $validated['variation_code'],
            'amount'         => $validated['amount'],
            'quantity'       => $validated['quantity'],
            'phone_number'   => $validated['phone_number'],
        ];

        return $this->vendingService->buyBroadbandSpectranent($data_to_send);
    }

    public function buy_broadband_smile(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'variation_code' => $validated['variation_code'],
            'amount'         => $validated['amount'],
            'account_id'     => $validated['account_id'],
            'phone_number'   => $validated['phone_number'],
        ];

        return $this->vendingService->buyBroadbandSmile($data_to_send);
    }

    public function buy_giftcard(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data_to_send = [
            'product_id'             => $validated['product_id'],
            'amount'                 => $validated['amount'],
            'recipient_email'        => $validated['recipient_email'],
            'recipient_country_code' => $validated['recipient_country_code'],
            'quantity'               => $validated['quantity'],
            'recipient_phone'        => $validated['recipient_phone'],
        ];

        return $this->vendingService->buyGiftcard($data_to_send);
    }

}
