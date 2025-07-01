@extends('email.main')

@section('title', 'Transfer Successful')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">Transfer Successful</h1>

                <p class="message">
                    Hello {{ $notifiable->first_name ?? 'there' }},<br><br>

                    Your transfer of ₦{{ number_format($data['data']['amount'] / 100, 2) }} has been completed successfully! Here are the details of your transaction:
                </p>

                <!-- Transfer Details -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #28a745; font-size: 18px;">Transfer Details</h3>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #28a745; width: 40%;">Transaction Type:</td>
                            <td style="padding: 8px 0; color: #333;">Money Transfer</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Amount:</td>
                            <td style="padding: 8px 0; color: #333; font-weight: 600;">₦{{ number_format($data['data']['amount'] / 100, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Recipient Name:</td>
                            <td style="padding: 8px 0; color: #333;">{{ $data['data']['recipient']['details']['account_name'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Account Number:</td>
                            <td style="padding: 8px 0; color: #333;">{{ $data['data']['recipient']['details']['account_number'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Bank Name:</td>
                            <td style="padding: 8px 0; color: #333;">{{ $data['data']['recipient']['details']['bank_name'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Transfer Fee:</td>
                            <td style="padding: 8px 0; color: #333;">₦{{ number_format($data['data']['fee_charged'] / 100, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Date & Time:</td>
                            <td style="padding: 8px 0; color: #333;">{{ \Carbon\Carbon::parse($data['data']['createdAt'])->format('M d, Y - h:i A') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Status:</td>
                            <td style="padding: 8px 0;">
                                <span style="background-color: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    {{ ucfirst($data['data']['status']) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Transaction Reference:</td>
                            <td style="padding: 8px 0; color: #333;">{{ $data['reference'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Transfer Code:</td>
                            <td style="padding: 8px 0; color: #333;">{{ $data['data']['transfer_code'] }}</td>
                        </tr>
                        @if($data['data']['reason'])
                            <tr>
                                <td style="padding: 8px 0; font-weight: 600; color: #555;">Description:</td>
                                <td style="padding: 8px 0; color: #333;">{{ $data['data']['reason'] }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                <!-- Success Message -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <p style="margin: 0; color: #555; font-weight: 600;">
                        🎉 Your transfer of ₦{{ number_format($data['data']['amount'] / 100, 2) }} to {{ $data['data']['recipient']['details']['account_name'] }} has been processed successfully
                    </p>
                </div>

                <!-- Transaction Summary -->
                <div style="background-color: #e3f2fd; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #2196f3;">
                    <h4 style="margin-top: 0; color: #1976d2; font-size: 16px;">💰 Transaction Summary</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 5px 0; color: #555;">Transfer Amount:</td>
                            <td style="padding: 5px 0; color: #333; text-align: right; font-weight: 600;">₦{{ number_format($data['data']['amount'] / 100, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; color: #555;">Transfer Fee:</td>
                            <td style="padding: 5px 0; color: #333; text-align: right;">₦{{ number_format($data['data']['fee_charged'] / 100, 2) }}</td>
                        </tr>
                        <tr style="border-top: 1px solid #ddd;">
                            <td style="padding: 8px 0; color: #333; font-weight: 600;">Total Debited:</td>
                            <td style="padding: 8px 0; color: #333; text-align: right; font-weight: 600;">₦{{ number_format(($data['data']['amount'] + $data['data']['fee_charged']) / 100, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Security Notice -->
                <div style="background-color: #fff3cd; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <p style="margin: 0; color: #333; font-size: 14px;">
                        <strong>🔒 Security Reminder:</strong> Keep your transaction reference safe for your records. If you didn't authorize this transfer, contact our support team immediately.
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
