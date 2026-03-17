# Big Cash LMS — Vercel + Supabase Deployment Guide

## Architecture

```
Browser → Vercel Edge CDN
               │
               ├── Static assets (CDN cache — instant)
               └── PHP requests → api/index.php (Serverless)
                                       │
                                       ├── Supabase PostgreSQL (DB)
                                       ├── Cloudflare R2 (files)
                                       ├── Resend (email)
                                       └── Paystack (payments)
```

---

## Services Required

| Service | Free Tier | Purpose |
|---|---|---|
| [Vercel](https://vercel.com) | 100GB bandwidth | PHP serverless hosting |
| [Supabase](https://supabase.com) | 500MB DB | PostgreSQL database |
| [Cloudflare R2](https://cloudflare.com/r2) | 10GB, free egress | File uploads (KYC, receipts) |
| [Resend](https://resend.com) | 3,000 emails/mo | Transactional email |
| [GitHub](https://github.com) | Free | Source control |

---

## Step 1 — Push to GitHub

```bash
git init && git add .
git commit -m "Big Cash LMS initial"
git remote add origin https://github.com/yourorg/bigcash-lms.git
git push -u origin main
```

---

## Step 2 — Create Supabase Project

1. Go to [supabase.com](https://supabase.com) → **New project**
2. Name: `bigcash` · Choose password · Region: `AWS US East 1` (closest to Ghana)
3. Wait ~2 minutes for provisioning

### Get your connection strings

Go to **Settings → Database → Connection string** tab.

You need **two** strings:

**Session Pooler — port 6543 (for Vercel serverless):**
```
Host:     aws-0-us-east-1.pooler.supabase.com
Port:     6543
User:     postgres.YOUR_PROJECT_REF
Password: your_db_password
Database: postgres
```

**Direct — port 5432 (for running migrations locally):**
```
Host:     db.YOUR_PROJECT_REF.supabase.co
Port:     5432
User:     postgres
Password: your_db_password
Database: postgres
```

> **Why two?** Vercel opens hundreds of short-lived connections. Port 6543 routes through PgBouncer pooling, preventing connection exhaustion. Port 5432 bypasses pooling and is required for schema migrations.

---

## Step 3 — Run Migrations Against Supabase

Set your local `.env` to the **direct connection** (port 5432):

```env
DB_CONNECTION=pgsql
DB_HOST=db.YOUR_PROJECT_REF.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_supabase_password
DB_SSLMODE=require
```

Then:
```bash
php artisan migrate --force
php artisan db:seed --force
```

Verify in Supabase **Table Editor** — all tables should be populated.

---

## Step 4 — Cloudflare R2 (File Storage)

KYC documents, receipts, PDFs need persistent storage — Vercel's `/tmp` is wiped per deploy.

1. Cloudflare Dashboard → **R2** → Create bucket `bigcash-uploads`
2. Enable **Public Access** on the bucket
3. **Manage R2 API Tokens** → Create token (Object Read & Write)
4. Note your **Account ID**, **Access Key ID**, **Secret Access Key**

---

## Step 5 — Resend (Email)

1. [resend.com](https://resend.com) → Add domain → Add DNS records
2. Create API Key → copy it

---

## Step 6 — Generate Keys

```bash
# App key
php artisan key:generate --show
# → base64:xxxxxxxx  (copy this)

# VAPID keys for push notifications
php artisan webpush:vapid
# → Writes VAPID_PUBLIC_KEY + VAPID_PRIVATE_KEY to .env
```

---

## Step 7 — Deploy to Vercel

1. [vercel.com/new](https://vercel.com/new) → Import your GitHub repo under the **kwameboats-projects** account
2. Vercel auto-detects `vercel.json` (scope is already set to `kwameboats-projects`)
3. Set all environment variables below **before** clicking Deploy

### Required Environment Variables

Paste these into Vercel → Settings → Environment Variables:

```
APP_NAME                         Big Cash
APP_ENV                          production
APP_KEY                          base64:YOUR_KEY_HERE
APP_DEBUG                        false
APP_URL                          https://bigcash-lms.vercel.app

LOG_CHANNEL                      stderr
LOG_LEVEL                        error

DB_CONNECTION                    pgsql
DB_HOST                          aws-0-us-east-1.pooler.supabase.com
DB_PORT                          6543
DB_DATABASE                      postgres
DB_USERNAME                      postgres.YOUR_PROJECT_REF
DB_PASSWORD                      YOUR_SUPABASE_PASSWORD
DB_SSLMODE                       require

CACHE_DRIVER                     array
SESSION_DRIVER                   cookie
SESSION_SECURE_COOKIE            true
QUEUE_CONNECTION                 sync
FILESYSTEM_DISK                  r2

CLOUDFLARE_R2_ACCESS_KEY_ID      your_key_id
CLOUDFLARE_R2_SECRET_ACCESS_KEY  your_secret
CLOUDFLARE_R2_BUCKET             bigcash-uploads
CLOUDFLARE_R2_ENDPOINT           https://ACCOUNT_ID.r2.cloudflarestorage.com
CLOUDFLARE_R2_PUBLIC_URL         https://pub-HASH.r2.dev

MAIL_MAILER                      smtp
MAIL_HOST                        smtp.resend.com
MAIL_PORT                        465
MAIL_USERNAME                    resend
MAIL_PASSWORD                    re_YOUR_RESEND_KEY
MAIL_ENCRYPTION                  ssl
MAIL_FROM_ADDRESS                noreply@bigcash.com
MAIL_FROM_NAME                   Big Cash Finance

PAYSTACK_PUBLIC_KEY              pk_live_xxx
PAYSTACK_SECRET_KEY              sk_live_xxx
PAYSTACK_PAYMENT_URL             https://api.paystack.co
PAYSTACK_MERCHANT_EMAIL          merchant@bigcash.com
PAYSTACK_MODE                    live

OPENAI_API_KEY                   sk-xxx
AI_ENABLED                       true
AI_MODEL                         gpt-4o-mini

SMS_PROVIDER                     arkesel
SMS_API_KEY                      your_arkesel_key
SMS_SENDER_ID                    BigCash

VAPID_PUBLIC_KEY                 your_vapid_public
VAPID_PRIVATE_KEY                your_vapid_private
VAPID_SUBJECT                    mailto:noreply@bigcash.com

COMPANY_NAME                     Big Cash Finance
COMPANY_CURRENCY                 GHS
COMPANY_CURRENCY_SYMBOL          ₵
COMPANY_TIMEZONE                 Africa/Accra
COMPANY_PHONE                    +233000000000
COMPANY_EMAIL                    info@bigcash.com

CRON_SECRET                      generate_a_strong_random_string
```

4. Click **Deploy**

---

## Step 8 — Custom Domain

1. Vercel → your project → **Domains** → Add `app.bigcash.com`
2. Add CNAME record at your DNS: `cname.vercel-dns.com`
3. Update `APP_URL` env var to your domain
4. SSL certificate is automatic (Let's Encrypt via Vercel)

---

## Step 9 — Paystack Webhook

Paystack Dashboard → **Settings → Webhooks** → Add:
```
https://your-domain.com/webhook/paystack
```

---

## Cron Jobs

### Vercel Pro Plan
Crons are in `vercel.json` and run automatically:
```json
"crons": [
  { "path": "/api/cron/mark-overdue",   "schedule": "0 1 * * *" },
  { "path": "/api/cron/send-reminders", "schedule": "0 8 * * *" },
  { "path": "/api/cron/cleanup",        "schedule": "0 * * * *" }
]
```

### Free Tier — cron-job.org
1. Sign up at [cron-job.org](https://cron-job.org)
2. Create these jobs (add header `Authorization: Bearer YOUR_CRON_SECRET`):

| URL | Schedule | Purpose |
|---|---|---|
| `.../api/cron/mark-overdue` | Daily 01:00 | Flag overdue, accrue penalties |
| `.../api/cron/send-reminders` | Daily 08:00 | SMS + push reminders |
| `.../api/cron/cleanup` | Hourly | Clean expired payment links |

---

## Health Check

```bash
curl https://your-domain.com/api/health
# {"status":"ok","app":"Big Cash","db":"connected","vercel":true}
```

---

## Default Login

| Role | Email | Password |
|---|---|---|
| Super Admin | admin@bigcash.com | Password@123 |
| Loan Officer | officer1@bigcash.com | Password@123 |
| Borrower Portal | kwabena@example.com | Borrower@123 |

**Change all passwords immediately after first login.**

---

## Troubleshooting

**DB connection refused on Vercel:**
- Use port `6543` not `5432` for Vercel
- `DB_USERNAME` must include project ref: `postgres.YOUR_PROJECT_REF`
- `DB_SSLMODE=require`

**500 after deploy:**
- Check Vercel → Functions → Logs
- Ensure `APP_KEY` starts with `base64:`

**File uploads fail:**
- `FILESYSTEM_DISK=r2` must be set
- R2 bucket must have public access enabled

**Migrations error locally:**
- Use direct connection port `5432` for migrations
- `DB_SSLMODE=require`
