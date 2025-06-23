@component('mail::message')
# 🔐 Email Verification Code

Hello,

You're almost there! To verify your email on **{{ $appName }}**, please use the one-time verification code below:

@component('mail::panel')
## {{ $otp }}
@endcomponent

This code is valid for **10 minutes**. Please do not share it with anyone.

If you didn't initiate this request, you can safely ignore this email.

---

Thanks for using {{ $appName }}!
If you have any issues, contact us at [{{ $supportEmail }}](mailto:{{ $supportEmail }})

@component('mail::subcopy')
Need help? Our support team is here for you.
@endcomponent

Thanks,<br>
The {{ $appName }} Team
@endcomponent
