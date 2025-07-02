@extends('email.main')

@section('title', 'Email Verification Code')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">Transaction Successful</h1>

                <p class="message">
                    Hello {{ $data->content->transactions->name ?? 'there' }},<br><br>

                    Your {{ $data->content->transactions->product_name }} purchase has been completed successfully! Here are the details of your transaction:
                </p>

                <!-- Transaction Details -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #28a745; font-size: 18px;">Transaction Details</h3>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #28a745; width: 40%;">Service Type:</td>
                            <td style="padding: 8px 0; color: #28a745;">{{ \Illuminate\Support\Str::ucfirst($data->content->transactions->type ?? 'Service') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Product:</td>
                            <td style="padding: 8px 0; color: #28a745;">{{ $data->content->transactions->product_name }}</td>
                        </tr>
                        @if($data->content->transactions->quantity > 1)
                            <tr>
                                <td style="padding: 8px 0; font-weight: 600; color: #555;">Quantity:</td>
                                <td style="padding: 8px 0; color: #28a745; font-weight: 600;">{{ $data->content->transactions->quantity }} PIN(s)</td>
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
                            <td style="padding: 8px 0; color: #28a745;">{{ $data->content->transactions->phone ?? $data->content->transactions->unique_element }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Amount:</td>
                            <td style="padding: 8px 0; color: #28a745; font-weight: 600;">₦{{ number_format($data->amount, 2) }}</td>
                        </tr>


                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Date & Time:</td>
                            <td style="padding: 8px 0; color: #28a745;">{{ \Carbon\Carbon::parse($data->transaction_date)->format('M d, Y - h:i A') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Status:</td>
                            <td style="padding: 8px 0;">
                        <span style="background-color: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                            {{ $data->content->transactions->status }}
                        </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Transaction ID:</td>
                            <td style="padding: 8px 0; color: #28a745;">{{ $data->content->transactions->transactionId }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Request ID:</td>
                            <td style="padding: 8px 0; color: #28a745;">{{ $data->requestId }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Purchase Code (for electricity and education PINs) -->
                @if($data->purchased_code && !empty($data->purchased_code))
                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                        <h3 style="margin-top: 0; color: #555; font-size: 18px;">
                            @if(str_contains(strtolower($data->content->transactions->product_name), 'electric'))
                                ⚡ Electricity Token
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'jamb'))
                                🎓 JAMB PIN
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'waec'))
                                📚 WAEC PIN
                            @elseif(str_contains(strtolower($data->content->transactions->product_name), 'neco'))
                                📖 NECO PIN
                            @else
                                🔑 PIN/Token
                            @endif
                        </h3>
                        <p style="margin: 10px 0; font-size: 16px; color: #555;">
                            <strong>
                                @if(str_contains(strtolower($data->content->transactions->product_name), 'electric'))
                                    Your electricity token:
                                @else
                                    Your PIN code{{ $data->content->transactions->quantity > 1 ? 's' : '' }}:
                                @endif
                            </strong>
                        </p>
                        <div style="background-color: #fff; padding: 15px; border-radius: 6px; border: 2px dashed #ffc107; text-align: center;">
                            <code style="font-size: 18px; font-weight: 700; color: #28a745; letter-spacing: 2px;">{{ $data->purchased_code }}</code>
                        </div>
                        <p style="margin: 10px 0 0 0; font-size: 14px; color: #555;">
                            <em>
                                @if(str_contains(strtolower($data->content->transactions->product_name), 'electric'))
                                    Please keep this token safe for your records
                                @else
                                    Please keep this PIN safe - you'll need it to access your results/registration
                                @endif
                            </em>
                        </p>
                    </div>
                @elseif(str_contains(strtolower($data->content->transactions->type ?? ''), 'education'))
                    <!-- Note for Education services when no PIN is provided -->
                    <div style="background-color: #e2e3e5; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
                        <p style="margin: 0; color: #495057; font-weight: 600;">
                            📋 Your {{ $data->content->transactions->product_name }} has been processed. If a PIN is required, it will be sent separately.
                        </p>
                    </div>
                @endif

                <!-- Success Message -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <p style="margin: 0; color: #555; font-weight: 600;">
                        @if(str_contains(strtolower($data->content->transactions->product_name), 'airtime'))
                            🎉 Your airtime has been successfully credited to {{ $data->content->transactions->phone ?? $data->content->transactions->unique_element }}
                        @elseif(str_contains(strtolower($data->content->transactions->product_name), 'data'))
                            🎉 Your data bundle has been successfully activated on {{ $data->content->transactions->phone ?? $data->content->transactions->unique_element }}
                        @elseif(str_contains(strtolower($data->content->transactions->product_name), 'electric'))
                            🎉 Your electricity payment has been processed successfully
                        @elseif(str_contains(strtolower($data->content->transactions->product_name), 'jamb'))
                            🎓 Your JAMB PIN has been generated successfully
                        @elseif(str_contains(strtolower($data->content->transactions->product_name), 'waec'))
                            📚 Your WAEC PIN{{ $data->content->transactions->quantity > 1 ? 's have' : ' has' }} been generated successfully
                        @elseif(str_contains(strtolower($data->content->transactions->product_name), 'neco'))
                            📖 Your NECO PIN{{ $data->content->transactions->quantity > 1 ? 's have' : ' has' }} been generated successfully
                        @elseif(str_contains(strtolower($data->content->transactions->type ?? ''), 'education'))
                            🎓 Your {{ $data->content->transactions->product_name }} purchase has been completed successfully
                        @else
                            ✅ Your {{ strtolower($data->content->transactions->product_name) }} purchase has been completed successfully
                        @endif
                    </p>
                </div>

                <!-- Additional Info -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #6c757d; font-size: 14px; text-align: center;">
                        Need help? Contact our support team or check your transaction history in your account dashboard.
                    </p>
                </div>

            </td>
        </tr>
    </table>
@endsection
