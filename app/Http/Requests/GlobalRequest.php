<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class GlobalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        $rules = [];

        switch ($this->route()->getActionMethod()) {

            case "Register":
                 $rules = [
                    'first_name'       => 'required|string|max:255',
                    'last_name'        => 'required|string|max:255',
                    'phone_number'     => 'required|unique:users,phone',
                    'email'            => 'required|email|unique:users,email',
                    'password'         => 'required|string|min:6',
                    'username'         => 'required|unique:users,username',
                    'transaction_pin'  => 'required|digits:4',
                    'device_token'     => 'nullable',
                    'referral_code' => 'nullable|string|exists:users,referral_code',
                    'device_type'  => 'required|string|in:android,ios,web',
                ];
                break;

            case "verifyBvn":
                 $rules = [
                    'bvn'          => 'required|digits:11',
                    'selfie_image' => 'required|string', // Base64
                    'address'      => 'required|string',
                    'zipcode'      => 'required|string',
                ];
                break;

            case "buy_international_airtime":
                 $rules = [
                    'country_code'      => 'required',
                    'operator_id'       => 'required',
                    'product_type_id'   => 'required',
                    'variation_code'    => 'required',
                    'amount'            => 'required|numeric|min:0',
                    'phone_number'      => 'required',
                ];
                break;


            case "verifyNin":
                 $rules = [
                    'nin'          => 'required|digits:11',
                    'selfie_image' => 'required|string',
                    'first_name'   => 'nullable|string|max:255',
                    'last_name'    => 'nullable|string|max:255',
                    'address'      => 'required|string',
                    'zipcode'      => 'required|string'
                ];
                break;


            case "initiateTransfer":
                 $rules = [
                    'amount' => 'required|numeric|min:100',
                    'bankCode' => 'required',
                    'is_beneficiary' => ['boolean'],
                    'bankName' => 'required',
                    'accountNumber' => 'required|numeric'
                ];
                break;

            case "updateTransactionPin":
                 $rules = [
                    'current_pin' => ['required', 'digits:4'],
                    'new_pin' => ['required', 'digits:4', 'different:current_pin'],
                ];
                break;

            case "verifyBettingID":
                 $rules = [
                    'betting_number' => 'required|string',
                    'betsite_id' => 'required|integer',
                ];
                break;



            case "verifyDriverLicense":
                $rules = [
                    'license_number' => 'required',
                ];
                break;

            case "createVirtualCard":
                $rules = [
                    'title'    => 'required|string|max:255',
                    'color'    => 'required|string|max:50',
                    'amount'   => 'required|numeric|min:0.01',
                    'userId'   => 'required|exists:virtual_cards,eversend_user_id',
                    'currency' => 'required|string|in:USD,EUR,NGN,USG',
                    'brand'    => 'required|string|in:visa,mastercard',
                ];
                break;





            case "fundBettingWallet":
                 $rules = [
                    'amount' => 'required|numeric|min:100',
                    'betting_number' => 'required|string',
                    'betsite_id' => 'required|integer',
                ];
                break;


            case "FundWallet":
            case "Withdrawal":
                $rules = [
                    'amount' => 'required|numeric',
                    'card_id' => 'required',
                    'currency' => 'required',
                ];
                break;


            case "myTransactionHistory":
                 $rules = [
                    'start_date' => ['nullable', 'date'],
                    'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                    'service_type' => ['nullable', 'string'],
                    'amount' => ['nullable', 'numeric'],
                    'status' => ['nullable', 'string'],
                    'page' => ['nullable', 'integer', 'min:1'],
                    'per_page' => ['nullable', 'integer', 'min:1'],
                ];
                break;


            case "buy_broadband_spectranent":
                 $rules = [
                    'variation_code' => 'required|string',
                    'amount'         => 'required|numeric|min:1',
                    'quantity'       => 'required|integer|min:1',
                    'phone_number'   => 'required|string',
                ];
                break;

            case "transferToBank":
                 $rules = [
                    'amount' => 'required|numeric|min:100',
                    'account_number' => 'required|string|digits:10',
                    'bank_code' => 'required|string',
                    'account_name' => 'required|string|max:255',
                    'bank_name' => 'sometimes|string|max:255',
                    'narration' => 'required|string|max:255',
                    'transaction_pin' => 'required|string|digits:4'
                ];
                break;


            case "resolveAccount":
                 $rules = [
                    'account_number' => 'required|string|digits:10',
                    'bank_code' => 'required|string',
                ];
                break;

            case "buy_broadband_smile":
                 $rules = [
                    'variation_code' => 'required|string',
                    'amount'         => 'required|numeric|min:1',
                    'account_id'     => 'required|string',
                    'phone_number'   => 'required|string',
                ];
                break;


            case "buy_giftcard":
                 $rules = [
                    'product_id'              => 'required|string',
                    'amount'                  => 'required|numeric|min:1',
                    'recipient_email'         => 'required|email',
                    'recipient_country_code'  => 'required|string',
                    'quantity'                => 'required|integer|min:1',
                    'recipient_phone'         => 'required|string',
                ];
                break;





            case "resendEmailOTP":
                 $rules = [
                    'email' => 'required|email|exists:users,email',
                ];
                break;

            case "initializeTransaction":
                 $rules = [
                    'amount' => 'required|numeric|min:1',
                ];
                break;


            case 'updatePassword':
                $rules = [
                    'old_password' => [
                        'required',
                        function ($attribute, $value, $fail) {
                            if (!Hash::check($value, auth()->user()->password)) {
                                $fail('The old password is incorrect.');
                            }
                        },
                    ],
                    'new_password' => 'required|confirmed|different:old_password',
                    'new_password_confirmation' => 'required', #  Confirm password must match new_password
                ];
                break;


            case "buy_waec_direct":
                $rules = [
                    'waec_type'      => 'required|string',
                    'quantity'       => 'required|integer|min:1',
                    'variation_code' => 'required|string',
                    'amount'         => 'required|numeric|min:0',
                    'phone_number'   => 'required|string'
                    ];
                break;

            case "Login":
                $rules = [
                    'email_or_username' => 'required|string', // ✅ supports both email and username
                    'password'          => 'required|string',
                ];
                break;

            case "confirmEmailOtp":
                $rules = [
                    'email' => 'required|string', // ✅ supports both email and username
                    'otp'          => 'required',
                ];
                break;

            case "checkCredential":
                $rules = [
                    'email' => 'nullable|email',
                    'username' => 'nullable|string|max:255',
                    'phone_number' => 'nullable|string|max:15',
                ];
                break;




            case "buyAirtime":
                $rules = [
                'product_code' => 'required|string|max:20',
                'amount' => 'required|numeric|min:50',
                'phone_number' => 'required|digits_between:10,15',
            ];
                break;

            case "buyData":
                $rules = [
                    'product_code' => "required",
                    'amount' => "required",
                    'phone_number' => "required",
                    'variation_code' => "required"
                ];
                break;


            case "FreezeACard":
            case "terminateACard":
            case "UnFreezeACard":
                $rules = [
                    'card_id' => "required",
                ];
                break;


            case "createBeneficiary":
                $rules = [
                    'phone' => 'required|string',
                    'service_type' => 'required|string',
                    'name' => 'nullable|string',
                ];
                break;

            case "deleteBeneficiary":
                $rules = [
                    'id' => 'required|exists:beneficiaries,id',

                ];
                break;

            case "buy_cable":
                $rules = [
                    'cable_type'      => 'required|string',
                    'smartcard'       => 'required|string',
                    'variation_code'  => 'required|string',
                    'amount'          => 'required|numeric|min:0',
                    'phone_number'    => 'required|string',
                ];
                break;


            case "buy_electricity":
                 $rules = [
                    'electricity_type' => 'required|string',
                    'meter_number'     => 'required|string',
                    'variation_code'   => 'required|string',
                    'amount'           => 'required|numeric|min:0',
                    'phone_number'     => 'required|string',
                ];
                break;


            case "verify_jamb":
                 $rules = [
                    'type'            => 'required|string',
                    'jamb_id'         => 'required|string',
                    'variation_code'  => 'required|string',
                ];
                break;


            case "buy_jamb":
                 $rules = [
                    'jamb_type'      => 'required|string',
                    'jamb_id'        => 'required|string',
                    'variation_code' => 'required|string',
                    'amount'         => 'required|numeric|min:0',
                    'phone_number'   => 'required|string',
                ];
                break;

            case 'forgetPassword':
                $rules = [
                    'email' => 'required',

                ];
                break;

            case "inAppTransfer":
            case "InAppTransferNow":
                $rules = [
                    'identifier' => 'required|string',
                    'amount' => 'required|numeric'
                ];

                break;

            default:
                break;
        }
        return $this->handleUnwantedParams($rules);
    }



    public function messages()
    {
        $messages = [];

        switch ($this->route()->getActionMethod()) {
            case 'Register':
                $messages = [
                    'first_name.required' => 'First name is required.',
                    'last_name.required' => 'Last name is required.',
                    'phone_number.required' => 'Phone number is required.',
                    'phone_number.unique' => 'This phone number is already taken.',
                    'phone_number.regex' => 'Phone number must be 10 to 15 digits.',
                    'email.required' => 'Email is required.',
                    'email.unique' => 'This email is already taken.',
                    'username.required' => 'Username is required.',
                    'email.email' => 'Provide a valid email address.',
                    'password.required' => 'Password is required.',
                    'password.min' => 'Password must be at least 6 characters.',
                    'transaction_pin.required' => 'Transaction PIN is required.',
                    'transaction_pin.digits' => 'Transaction PIN must be exactly 4 digits.',
                ];
                break;
        }

        return $messages;
    }



    /**
     * Private function to detect and handle extra parameters.
     *
     * @param array $rules
     * @return array
     */
    private function handleUnwantedParams(array $rules): array
    {
        $inputParams = array_keys($this->all());
        $allowedParams = array_keys($rules);
        $allowedExtraParams = ['per_page', 'page', 'search'];
        $extraParams = array_diff($inputParams, $allowedParams, $allowedExtraParams);
        if (!empty($extraParams)) {
            foreach ($extraParams as $extraParam) {
                $rules[$extraParam] = 'prohibited';
            }
        }

        return $rules;
    }
}
