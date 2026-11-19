# SlotKita

A booking platform for small shops (barbers, salons and similar). One platform, many shops, monthly plans.
Each shop gets its own booking page; customers pick a service and a slot, and the owner manages it all from a dashboard.

## Highlights

- **Payments you can trust:** bills are paid through ToyyibPay (FPX). The payment status is always re-checked with
  the gateway and the callback hash is verified, so a forged callback cannot mark a bill as paid.
- **Plans and billing:** free trial, monthly plans and a manual bank-transfer fallback.
- **Referrals and notifications:** referral rewards and Telegram alerts for new payments.

## Run it locally

1. Create a MySQL 8 database and import `database/install.sql`.
2. Copy `config.sample.php` to `config.php` and fill it in (set `payment_driver` to `mock` for local testing).
3. Start PHP's built-in server from this folder:

   ```
   php -S localhost:8120 router.php
   ```

The full setup and hosting guide (in Bahasa Malaysia) is in [PANDUAN.md](PANDUAN.md).

Stack: PHP 8.1+, MySQL / MariaDB, ToyyibPay.
