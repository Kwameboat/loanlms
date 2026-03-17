# Big Cash LMS — Loan Management System

Production-ready loan management for Ghanaian microfinance. Laravel 10 · MySQL · Bootstrap 5 · Paystack · PWA · Vercel-ready.

## Quick Start

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force && php artisan db:seed --force
php artisan storage:link
php artisan webpush:vapid
php artisan serve
```

Login: `admin@bigcash.com` / `Password@123`

## Deployment

| Method | Guide |
|---|---|
| Vercel (serverless) | [VERCEL_DEPLOY.md](VERCEL_DEPLOY.md) |
| cPanel shared hosting | [INSTALL.md](INSTALL.md) |

## Default Accounts

| Role | Email | Password |
|---|---|---|
| Super Admin | admin@bigcash.com | Password@123 |
| Loan Officer | officer1@bigcash.com | Password@123 |
| Borrower | kwabena@example.com | Borrower@123 |

## Features

- Multi-branch loan operations · Full Ghana KYC · 7 user roles
- Salary, personal, business, emergency, micro, group loans
- Paystack payments (card + mobile money) · Webhook integration
- AI credit assessment (GPT-4o-mini) · BigCashAI chat for officers
- PDF receipts · Excel reports · PAR30 · Arrears aging
- Full PWA: installable · offline · push notifications
- Vercel + PlanetScale + Cloudflare R2 ready

## PWA Setup

See [PWA_SETUP.md](PWA_SETUP.md)

## License

Proprietary — Big Cash Finance Ltd.
