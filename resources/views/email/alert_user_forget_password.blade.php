@component('mail::message')
# 🔐 Your Password Has Been Reset

Hello,

This is to notify you that your password for the account associated with **{{ $user->email }}** has been successfully reset.

A temporary password has been generated for your account. Kindly use the credentials below to log in:

---

**Email:** {{ $user->email }}
**Temporary Password:** `{{ $token }}`

---

## 🚨 Please take the following actions immediately:
- 🔐 Log in to your account using the credentials above
- 🔁 Change your password to something secure and memorable
- 🛡️ Do not share your login information with anyone

---

If you did not request this password reset, please contact our support team immediately.
We’re here to help you keep your account safe and secure.


Need assistance? Contact our support team at any time.
Thank you for using **BILLIA**.

@endcomponent
