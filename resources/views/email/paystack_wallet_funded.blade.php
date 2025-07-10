@extends('email.main')

@section('title', 'Transaction Successful')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">Transaction Successful</h1>

                <p class="message">
                    Hello {{ $transaction->user->first_name ?? 'there' }},<br><br>

                    Great news! Your wallet has been funded with ₦{{ number_format($data['data']['amount'] / 100, 2) }} successfully. Your transaction has been processed and the funds are now available in your account.
                </p>



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
