@extends('email.main')

@section('title', 'Transfer Reversed')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">
                <!-- Header Message -->
                <h1 class="greeting">Transfer Reversed</h1>

                <p class="message">
                    Hello {{ $transaction->user->first_name ?? 'there' }},<br><br>

                    Your transfer of ₦{{ number_format($data['data']['amount'] / 100, 2) }} to {{ $data['data']['recipient']['details']['account_name'] ?? "_" }} has been successfully reversed.<br><br>

                    The full amount of ₦{{ number_format($data['data']['amount'] / 100, 2) }} has been credited back to your wallet.
                </p>

                <!-- Refund Message -->
                <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #555;">
                    <p style="margin: 0; color: #555; font-weight: 600;">
                        💰 Your wallet has been credited with ₦{{ number_format($data['data']['amount'] / 100, 2) }} from the reversed transfer
                    </p>
                </div>

                <!-- Additional Info -->
                <div style="background-color: #28a745; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #555; font-size: 14px; text-align: center;">
                        Need help? Contact our support team or check your transaction history in your account dashboard.
                    </p>
                </div>
            </td>
        </tr>
    </table>
@endsection
