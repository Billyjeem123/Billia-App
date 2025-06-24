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
                return [
                    'first_name'       => 'required|string|max:255',
                    'last_name'        => 'required|string|max:255',
                    'phone_number'     => 'required|unique:users,phone',
                    'email'            => 'required|email|unique:users,email',
                    'password'         => 'required|string|min:6',
                    'username'         => 'required|unique:users,username',
                    'transaction_pin'  => 'required|digits:4',
                ];

            case "verifyBvn":
                return [
                    'bvn'          => 'required|digits:11',
                    'selfie_image' => 'required|string', // Base64
                    'address'      => 'required|string',
                    'zipcode'      => 'required|string',
                ];

            case "verifyNin":
                return [
                    'nin'          => 'required|digits:11',
                    'selfie_image' => 'required|string',
                    'first_name'   => 'nullable|string|max:255',
                    'last_name'    => 'nullable|string|max:255',
                ];


            case "myTransactionHistory":
                return [
                    'start_date' => 'nullable|date',
                    'end_date' => 'nullable|date',
                    'service_type' => 'nullable|string',
                    'amount' => 'nullable|numeric',
                    'status' => 'nullable|string'
                ];

            case "resendEmailOTP":
                return [
                    'email' => 'required|email|exists:users,email',
                ];

            case "Login":
                return [
                    'email_or_username' => 'required|string', // ✅ supports both email and username
                    'password'          => 'required|string',
                ];

            case "confirmEmailOtp":
                return [
                    'email' => 'required|string', // ✅ supports both email and username
                    'otp'          => 'required',
                ];

            case "checkCredential":
                return [
                    'email' => 'nullable|email',
                    'username' => 'nullable|string|max:255',
                    'phone_number' => 'nullable|string|max:15',
                ];

            case "buyAirtime":
            return [
                'product_code' => 'required|string|max:20',
                'amount' => 'required|numeric|min:50',
                'phone_number' => 'required|digits_between:10,15',
            ];

            case "buyData":
                return [
                    'product_code' => "required",
                    'amount' => "required",
                    'phone_number' => "required",
                    'variation_code' => "required"
                ];


            case "createBeneficiary":
                return [
                    'phone' => 'required|string',
                    'service_type' => 'required|string',
                    'name' => 'nullable|string',
                ];



            default:
                return $this->handleUnwantedParams($rules);
        }
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
