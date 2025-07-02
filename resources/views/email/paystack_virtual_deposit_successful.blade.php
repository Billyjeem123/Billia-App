@extends('email.main')

@section('title', 'Wallet Funded Successfully')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">Transaction Successful</h1>

                <p class="message">
                    Hello {{ $transaction->user->first_name ?? 'there' }},<br><br>

                    Great news! Your wallet has been credited with ₦{{ number_format($data['data']['amount'] / 100, 2) }} via your virtual account deposit. The funds are now available in your wallet.
                </p>

                <!-- Deposit Details -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #555; font-size: 18px;">Funding Details</h3>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555; width: 40%;">Transaction Type:</td>
                            <td style="padding: 8px 0; color: #555;">Deposit</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Amount Credited:</td>
                            <td style="padding: 8px 0; color: #5555; font-weight: 600;">₦{{ number_format($data['data']['amount'] / 100, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Sender Account number:</td>
                            <td style="padding: 8px 0; color: #555;">{{ $data['data']['metadata']['receiver_account_number'] ?? $data['data']['authorization']['receiver_bank_account_number'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Account Name:</td>
                            <td style="padding: 8px 0; color: #555;">{{ $data['data']['metadata']['account_name']  ?? "_" }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600; color: #555;">Payment Method:</td>
                            <td style="padding: 8px 0; color: #555;">{{ ucfirst($data['data']['channel']) }} (****{{ $data['data']['authorization']['last4'] }})</td>
                        </tr>
                    </table>
                </div>

                <!-- Success Message -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #555;">
                    <p style="margin: 0; color: #555; font-weight: 600;">
                        Your wallet has been successfully credited with ₦{{ number_format($data['data']['amount'] / 100, 2) }} to your account. You can now use these funds on our platform.
                    </p>
                </div>

                <!-- Additional Info -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #555; font-size: 14px; text-align: center;">
                        Need help? Contact our support team or check your wallet balance in your account dashboard.
                    </p>
                </div>

            </td>
        </tr>
    </table>
@endsection
