# Big Cash LMS — Installation Guide

## Prerequisites

- PHP 8.1 or higher (with extensions: pdo, pdo_pgsql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, curl, gd)
- Composer 2.x
- Git

---

## Quick Start (Local Development)

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy and configure environment
cp .env.example .env
# Edit .env — set DB_HOST, DB_PASSWORD, APP_URL, etc.

# 3. Generate application key
php artisan key:generate

# 4. Generate VAPID keys for push notifications
php artisan webpush:vapid

# 5. Run migrations and seed demo data
php artisan migrate --force
php artisan db:seed --force

# 6. Link storage for file uploads
php artisan storage:link

# 7. Start development server
php artisan serve
```

Visit `http://localhost:8000` → Login: `admin@bigcash.com` / `Password@123`

---

## Vercel Deployment

See **[VERCEL_DEPLOY.md](VERCEL_DEPLOY.md)** for the full step-by-step guide.

**Summary:**
1. Push to GitHub
2. Create Supabase project → run migrations locally with direct connection (port 5432)
3. Create Cloudflare R2 bucket
4. Set up Resend email
5. Import repo in Vercel → set env vars → deploy
6. Configure custom domain + Paystack webhook

---

## cPanel Hosting

1. Upload files to `/home/user/bigcash/` (NOT inside `public_html`)
2. Point domain document root to `/home/user/bigcash/public`
3. Create MySQL database and user in cPanel
4. Update `.env` with DB credentials (`DB_CONNECTION=mysql`)
5. Via Terminal/SSH:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   ```
6. Add single cron entry:
   ```
   * * * * * /usr/local/bin/php /home/user/bigcash/artisan schedule:run >> /dev/null 2>&1
   ```

---

## Default Login Credentials

| Role | Email | Password |
|---|---|---|
| Super Admin | admin@bigcash.com | Password@123 |
| Admin | admin2@bigcash.com | Password@123 |
| Branch Manager | manager.kumasi@bigcash.com | Password@123 |
| Loan Officer | officer1@bigcash.com | Password@123 |
| Accountant | accountant@bigcash.com | Password@123 |
| Collector | collector1@bigcash.com | Password@123 |
| Borrower Portal | kwabena@example.com | Borrower@123 |

**Change all passwords immediately after first login.**

---

## Environment Variables Reference

See [.env.example](.env.example) for the full list.

Key variables:

| Variable | Description | Required |
|---|---|---|
| `APP_KEY` | Laravel encryption key | Yes — `php artisan key:generate` |
| `APP_URL` | Your app's URL | Yes |
| `DB_CONNECTION` | `pgsql` (Supabase) or `mysql` (cPanel) | Yes |
| `DB_HOST` | Database host | Yes |
| `DB_PASSWORD` | Database password | Yes |
| `PAYSTACK_SECRET_KEY` | Paystack live secret | For payments |
| `OPENAI_API_KEY` | GPT-4o-mini key | For AI features |
| `VAPID_PUBLIC_KEY` | Push notification key | For PWA push |
| `VAPID_PRIVATE_KEY` | Push notification key | For PWA push |
| `SMS_PROVIDER` | `arkesel`/`hubtel`/`mnotify`/`log` | For SMS |
| `CLOUDFLARE_R2_*` | R2 credentials | For Vercel file uploads |
| `CRON_SECRET` | Random string for cron auth | For Vercel crons |

---

## Running Tests

```bash
php artisan test
# or
./vendor/bin/phpunit
```

---

## Artisan Commands

```bash
# Mark overdue loans and accrue penalties
php artisan loans:mark-overdue

# Send due payment reminders (SMS + push)
php artisan loans:send-reminders

# Send overdue warnings
php artisan loans:send-overdue-warnings

# Send push notifications
php artisan push:send-reminders --type=all

# Generate VAPID keys
php artisan webpush:vapid

# Clean expired payment links
php artisan paystack:clean-expired-links

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
