@extends('email.main')

@section('title', 'Wallet Funded Successfully')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">Wallet Funded Successfully</h1>

                <p class="message">
                    Hello {{ $transaction->user->first_name ?? 'there' }},<br><br>

                    Great news! Your wallet has been funded with ₦{{ number_format($data['data']['amount'] / 100, 2) }} successfully. Your transaction has been processed and the funds are now available in your account.
                </p>

                <!-- Transaction Details -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #28a745; font-size: 18px;">Transaction Details</h3>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #28a745; width: 40%;">Transaction Type:</td>
                            <td style="padding: 8px 0; color: #333;">Wallet Funding</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Amount Funded:</td>
                            <td style="padding: 8px 0; color: #333; font-weight: 600;">₦{{ number_format($data['data']['amount'] / 100, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Payment Method:</td>
                            <td style="padding: 8px 0; color: #333;">{{ ucfirst($data['data']['channel']) }} (****{{ $data['data']['authorization']['last4'] }})</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Card Type:</td>
                            <td style="padding: 8px 0; color: #333;">{{ ucfirst($data['data']['authorization']['brand']) }} {{ ucfirst($data['data']['authorization']['card_type']) }}</td>
                        </tr>

                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Date & Time:</td>
                            <td style="padding: 8px 0; color: #333;">{{ \Carbon\Carbon::parse($data['data']['paid_at'])->format('M d, Y - h:i A') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Status:</td>
                            <td style="padding: 8px 0;">
                                <span style="background-color: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    {{ ucfirst($data['data']['status']) }}
                                </span>
                            </td>
                        </tr>

                    </table>
                </div>

                <!-- Success Message -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <p style="margin: 0; color: #555; font-weight: 600;">
                         Your wallet has been successfully funded with ₦{{ number_format($data['data']['amount'] / 100, 2) }}. The funds are now available for use in your account.
                    </p>
                </div>


                <!-- Next Steps -->
                <div style="background-color: #e8f5e8; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <p style="margin: 0; color: #333; font-size: 14px;">
                        <strong> What's Next:</strong> Your funds are now available in your wallet. You can start using them for transactions, transfers, or any other services on our platform.
                    </p>
                </div>

                <!-- Additional Info -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #6c757d; font-size: 14px; text-align: center;">
                        Need help? Contact our support team or check your wallet balance and transaction history in your account dashboard.
                    </p>
                </div>

            </td>
        </tr>
    </table>
@endsection
