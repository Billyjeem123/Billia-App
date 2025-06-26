@component('mail::message')
# 🎉 Congratulations, {{ $referrer->first_name }}!

You’ve successfully earned a referral bonus of **₦{{ number_format($amount, 2) }}**.

Your wallet has been credited, and the funds are now available for use.

Thank you for sharing {{ config('app.name') }} with others — we appreciate your continued support.



Warm regards,
**The {{ config('app.name') }} Team**
@endcomponent
