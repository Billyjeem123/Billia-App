# 📱 Billa App — VTU Airtime & Data Recharge Platform

**Billa App** is a Laravel-based Virtual Top-Up (VTU) application built for seamless **airtime top-up**, **data bundle purchases**, and **recharge card PIN** services. The application uses a **controller-service architecture** and integrates **Paystack** as the payment provider.

---

## 🚀 Features

- Airtime recharge (MTN, GLO, Airtel, 9mobile)
- Data bundle purchases (VTU & gifting)
- Recharge card generation
- Paystack payment integration
- Webhook processing and transaction status updates
- Wallet debit and credit system
- Transaction reversal handling
- Notification system (email/SMS support)

---

## 🧱 Architecture

The app follows a **Controller-Service Pattern**:


Each service contains the business logic for its respective domain.

All **wallet-related operations** such as **funding**, **withdrawal**, and **internal transfers** are handled within a dedicated controller (`v1/payment`). This is because these operations are exclusively powered by **Paystack**  excluding (In-app-transfer). They  serve a distinct transactional purpose.

> 🧾 Therefore, instead of distributing the logic across multiple services, it is encapsulated inside the controller to maintain cohesion and simplify payment-related processes.

---

## 📂 Key Folders

| Folder                | Purpose                                                                  |
|----------------------|--------------------------------------------------------------------------|
| `app/Http/v1/Controllers` | Contains `Controller classes` to handle   route cals and (webhook logic) |
| `app/Services`        | Encapsulates business logic for VTU operations                           |
| `app/Models`          | Eloquent models like `Transaction`, `Wallet`, `User`, etc.               |



## 💳 Payment Provider

This application integrates **[Paystack](https://paystack.com/)** for payment processing.

- **Webhook Support**: Paystack sends transaction updates to `/api/v1/vtu/paystack-webhook`.
- **Reversal Handling**: If a transaction is reversed, the wallet is credited back automatically and a notification is sent.

---

## 🔐 Authentication

- API Token authentication is used.
- Role-based access control can be extended using Laravel’s policies and gates.


