@extends('email.main')

@section('title', 'Bills Payment')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header Message -->
                <h1 class="greeting">Bills Payment</h1>

                <p class="message">
                    Hello {{ $data->content->transactions->name ?? 'there' }},<br><br>

                    {{ $short_message }}
                </p>

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
