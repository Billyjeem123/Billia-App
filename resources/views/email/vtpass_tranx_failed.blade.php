{{-- Transaction Failed/Reversed Email Template --}}
@extends('email.main')

@section('title', 'Transaction Update')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">
                    Transaction Failed - Amount Reversed
                </h1>

                <p class="message">
                    Hello {{ $data->content->transactions->name ?? 'there' }},<br><br>

                        We regret to inform you that your {{ $data->content->transactions->product_name }} purchase could not be completed. However, your payment has been automatically reversed to your account. Here are the details:
                </p>

                <!-- Transaction Details -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid {{ strtolower($data->content->transactions->status ?? '') === 'failed' || strtolower($data->content->transactions->status ?? '') === 'reversed' ? '#555' : '#555' }};">
                    <h3 style="margin-top: 0; color: {{ strtolower($data->content->transactions->status ?? '') === 'failed' || strtolower($data->content->transactions->status ?? '') === 'reversed' ? '#555' : '#555' }}; font-size: 18px;">Transaction Details</h3>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555; width: 40%;">Service Type:</td>
                            <td style="padding: 8px 0; color: #555;">{{ \Illuminate\Support\Str::ucfirst($data->content->transactions->type ?? 'Service') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Product:</td>
                            <td style="padding: 8px 0; color: #555;">{{ $data->content->transactions->product_name }}</td>
                        </tr>
                        @if($data->content->transactions->quantity > 1)
                            <tr>
                                <td style="padding: 8px 0; font-weight: 600; color: #555;">Quantity:</td>
                                <td style="padding: 8px 0; color: #555; font-weight: 600;">{{ $data->content->transactions->quantity }} PIN(s)</td>
                            </tr>
                        @endif
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">
                                @if(str_contains(strtolower($data->content->transactions->product_name), 'electric'))
                                    Meter Number:
                                @elseif(str_contains(strtolower($data->content->transactions->product_name), 'jamb') || str_contains(strtolower($data->content->transactions->product_name), 'waec') || str_contains(strtolower($data->content->transactions->product_name), 'neco'))
                                    Phone Number:
                                @else
                                    Phone Number:
                                @endif
                            </td>
                            <td style="padding: 8px 0; color: #555;">{{ $data->content->transactions->phone ?? $data->content->transactions->unique_element }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Amount:</td>
                            <td style="padding: 8px 0; color: #555; font-weight: 600;">₦{{ number_format($data->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Date & Time:</td>
                            <td style="padding: 8px 0; color: #555;">{{ \Carbon\Carbon::parse($data->transaction_date)->format('M d, Y - h:i A') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Status:</td>
                            <td style="padding: 8px 0;">
                                <span style="background-color: {{ strtolower($data->content->transactions->status ?? '') === 'failed' || strtolower($data->content->transactions->status ?? '') === 'reversed' ? '#555' : '#555' }}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    {{ $data->content->transactions->status }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Transaction ID:</td>
                            <td style="padding: 8px 0; color: #555;">{{ $data->content->transactions->transactionId }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Request ID:</td>
                            <td style="padding: 8px 0; color: #555;">{{ $data->requestId }}</td>
                        </tr>
                    </table>
                </div>

                    <!-- Reversal Notice -->
                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                        <h3 style="margin-top: 0; color: #856404; font-size: 18px;">💰 Payment Reversed</h3>
                        <p style="margin: 10px 0; font-size: 16px; color: #856404;">
                            Your payment of <strong>₦{{ number_format($data->amount, 2) }}</strong> has been automatically reversed to your account.
                        </p>

                    </div>

                    <!-- Failure Message -->
                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #555;">
                        <p style="margin: 0; color: #555; font-weight: 600;">
                            @if(str_contains(strtolower($data->content->transactions->product_name), 'airtime'))
                                ❌ We couldn't process your airtime purchase for {{ $data->content->transactions->phone ?? $data->content->transactions->unique_element }}. Your money has been reversed.
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'data'))
                                ❌ We couldn't activate your data bundle on {{ $data->content->transactions->phone ?? $data->content->transactions->unique_element }}. Your money has been reversed.
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'electric'))
                                ❌ We couldn't process your electricity payment. Your money has been reversed.
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'jamb'))
                                ❌ We couldn't generate your JAMB PIN. Your money has been reversed.
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'waec'))
                                ❌ We couldn't generate your WAEC PIN{{ $data->content->transactions->quantity > 1 ? 's' : '' }}. Your money has been reversed.
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'neco'))
                                ❌ We couldn't generate your NECO PIN{{ $data->content->transactions->quantity > 1 ? 's' : '' }}. Your money has been reversed.
                            @else
                                ❌ We couldn't complete your {{ strtolower($data->content->transactions->product_name) }} purchase. Your money has been reversed.
                            @endif
                        </p>
                    </div>


                <!-- Additional Info -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #6c757d; font-size: 14px; text-align: center;">
                        We apologize for the inconvenience. You can try your transaction again or contact our support team for assistance.
                    </p>
                </div>

            </td>
        </tr>
    </table>
@endsection
