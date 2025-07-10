@extends('email.main')

@section('title', 'Referral Bonus Earned')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="content">

                <!-- Header -->
                <h1 class="greeting" style="font-size: 24px; color: #333;">🎉 Referral Bonus Earned!</h1>

                <!-- Intro Message -->
                <p class="message" style="font-size: 16px; color: #28a745;">
                    Hello {{ $referrer->first_name }},<br><br>
                    Great news! You've just earned a referral bonus of
                    <strong>₦{{ number_format($amount, 2) }}</strong> for inviting a new user to {{ config('app.name') }}.
                    The bonus has been credited to your wallet and is now available for use.
                </p>

                <!-- Highlight Box -->
                <div class="highlight-box" style="background-color: #28a745; padding: 15px 20px; border-left: 4px solid #28a745; border-radius: 8px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 15px; color: #28a745;">
                       Thank you for sharing {{ config('app.name') }} with others — we appreciate your continued support and trust.
                    </p>
                </div>

                <!-- Footer Message -->
                <p class="message" style="font-size: 15px; color: #28a745;">
                    Thank you for being a valued user of {{ config('app.name') }}.<br><br>

                    Best regards,<br>
                    <strong>The  {{ config('app.name') }} Team</strong>
                </p>

            </td>
        </tr>
    </table>
@endsection





