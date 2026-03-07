# 🔔 Reminder & Confirmation System — Setup Guide

## Overview

This system provides automatic 24-hour appointment reminders via WhatsApp and Email, with patient confirmation/cancellation handling.

---

## 🗄️ Step 1: Run Database Migration

Execute the SQL migration on your `bansari_clinic` database:

```bash
mysql -u root -p bansari_clinic < backend/sql/011_reminder_confirmation_system.sql
```

This adds:
- Reminder/confirmation fields to `appointments` table
- `reminder_logs` table for audit trail
- `confirmation_tokens` table for email confirm/cancel links
- `reminder_rate_limits` table for rate limiting

---

## 📦 Step 2: Install Dependencies

```bash
cd frontend
npm install
```

New dependencies added:
- `@prisma/client` — Database ORM
- `prisma` — Schema management (dev)
- `nodemailer` — Email sending
- `@types/nodemailer` — TypeScript types

---

## 🔧 Step 3: Generate Prisma Client

```bash
cd frontend
npx prisma generate
```

To validate the schema:
```bash
npx prisma validate
```

To introspect existing database:
```bash
npx prisma db pull
```

---

## 🔑 Step 4: Configure Environment Variables

Copy the example env file and fill in your values:

```bash
cp frontend/.env.example frontend/.env.local
```

### Required Variables:

| Variable | Description |
|----------|-------------|
| `DATABASE_URL` | MySQL connection string: `mysql://root:@localhost:3307/bansari_clinic` |
| `NEXT_PUBLIC_APP_URL` | Your Next.js app URL (e.g., `http://localhost:3000`) |
| `CRON_SECRET` | Secret key for cron job authentication |
| `ADMIN_API_KEY` | Secret key for admin API authentication |

### WhatsApp Cloud API (Optional — can be enabled later):

| Variable | Description |
|----------|-------------|
| `WHATSAPP_PHONE_NUMBER_ID` | From Meta Business dashboard |
| `WHATSAPP_ACCESS_TOKEN` | Permanent access token from Meta |
| `WHATSAPP_VERIFY_TOKEN` | For webhook verification |
| `WHATSAPP_TEMPLATE_NAME` | Approved message template name |

### Email SMTP:

| Variable | Description |
|----------|-------------|
| `SMTP_HOST` | SMTP server (e.g., `smtp.gmail.com`) |
| `SMTP_PORT` | SMTP port (e.g., `587`) |
| `SMTP_USER` | Email address |
| `SMTP_PASS` | App password (NOT your regular password) |
| `EMAIL_FROM` | From address with name |

---

## 📲 Step 5: WhatsApp Cloud API Setup

### 5a. Create WhatsApp Business App

1. Go to [Meta Developers](https://developers.facebook.com/)
2. Create a new App → Business type
3. Add WhatsApp product
4. Get your Phone Number ID and Access Token

### 5b. Create Message Template

In Meta Business Manager → WhatsApp → Message Templates:

- **Template Name**: `appointment_reminder`
- **Category**: Utility
- **Language**: English
- **Body**:
  ```
  Hello {{1}},
  This is a reminder for your appointment at Bansari Homeopathy Clinic on {{2}} at {{3}}.
  Reply YES to confirm or NO to cancel.
  ```

### 5c. Configure Webhook

In your WhatsApp app settings, set webhook URL to:
```
https://your-domain.com/api/webhook/whatsapp
```

Verify token: Use the same value as `WHATSAPP_VERIFY_TOKEN` in `.env.local`

Subscribe to: `messages`

---

## ⏰ Step 6: Set Up Cron Job

The cron endpoint runs every 30 minutes to check for appointments needing reminders.

### Option A: External Cron Service (Recommended)

Use [cron-job.org](https://cron-job.org) or similar:

- **URL**: `https://your-domain.com/api/cron/reminders?secret=YOUR_CRON_SECRET`
- **Method**: GET
- **Schedule**: Every 30 minutes (`*/30 * * * *`)

### Option B: System Crontab

```bash
*/30 * * * * curl -s "http://localhost:3000/api/cron/reminders?secret=YOUR_CRON_SECRET" > /dev/null 2>&1
```

### Option C: Vercel Cron (if deployed on Vercel)

Add to `vercel.json`:
```json
{
  "crons": [
    {
      "path": "/api/cron/reminders?secret=YOUR_CRON_SECRET",
      "schedule": "*/30 * * * *"
    }
  ]
}
```

---

## 🖥️ Step 7: Admin Panel Configuration

Update the admin panel's reminder JS configuration:

In `clinic-admin/js/reminders.js`, update:
- `NEXT_API_URL` — Your Next.js server URL
- `window.ADMIN_API_KEY` — Must match `ADMIN_API_KEY` in `.env.local`

### Inject config in the admin header

Add to `clinic-admin/includes/header.php` (before `</head>`):
```html
<script>
  window.ADMIN_API_KEY = '<?= getenv("ADMIN_API_KEY") ?: "your-admin-api-key-here" ?>';
  window.ADMIN_ID = '<?= getAdminId() ?>';
</script>
```

---

## 🚀 Step 8: Start the Application

```bash
# Terminal 1: Start Next.js
cd frontend
npm run dev

# Terminal 2: Start PHP (XAMPP/Apache should be running on port 8080)
# The clinic-admin panel is served by Apache/XAMPP
```

---

## 📡 API Routes Reference

| Route | Method | Auth | Description |
|-------|--------|------|-------------|
| `/api/cron/reminders` | GET | Cron Secret | Process automatic reminders |
| `/api/admin/reminders` | POST | Admin API Key | Send manual reminder |
| `/api/admin/reminders` | GET | Admin API Key | Get reminder stats |
| `/api/admin/followup` | POST | Admin API Key | Create follow-up appointment |
| `/api/admin/followup` | GET | Admin API Key | Get follow-up suggestions |
| `/api/appointment/confirm` | GET | Token | Patient confirms (email link) |
| `/api/appointment/cancel` | GET | Token | Patient cancels (email link) |
| `/api/webhook/whatsapp` | GET | Verify Token | WhatsApp webhook verification |
| `/api/webhook/whatsapp` | POST | — | Receive WhatsApp replies |

---

## 🔒 Security Features

- **Admin API Key Authentication** — All admin endpoints require valid API key
- **Cron Secret** — Cron jobs authenticated via secret token
- **Rate Limiting** — Max 3 manual reminders per appointment per hour
- **Token Expiry** — Email confirm/cancel links expire after 48 hours
- **Single-use Tokens** — Confirmation tokens can only be used once
- **Appointment Validation** — All appointment IDs validated before processing
- **Audit Logging** — All reminder actions logged in `reminder_logs` table
- **CSRF Protection** — Admin panel forms protected with CSRF tokens

---

## 🧪 Testing

### Test Cron Job:
```bash
curl "http://localhost:3000/api/cron/reminders?secret=YOUR_CRON_SECRET"
```

### Test Manual Reminder:
```bash
curl -X POST "http://localhost:3000/api/admin/reminders" \
  -H "Content-Type: application/json" \
  -H "x-admin-api-key: YOUR_ADMIN_API_KEY" \
  -H "x-admin-id: 1" \
  -d '{"appointmentId": 1}'
```

### Test Reminder Stats:
```bash
curl "http://localhost:3000/api/admin/reminders?action=stats" \
  -H "x-admin-api-key: YOUR_ADMIN_API_KEY"
```

---

## 📁 File Structure

```
frontend/
├── prisma/
│   └── schema.prisma          # Database schema
├── src/
│   ├── lib/
│   │   ├── prisma.ts          # Prisma client singleton
│   │   ├── whatsapp.ts        # WhatsApp Cloud API service
│   │   ├── email.ts           # Email/Nodemailer service
│   │   ├── middleware.ts      # Auth & rate limiting
│   │   └── reminder-service.ts # Core reminder business logic
│   └── app/
│       └── api/
│           ├── cron/
│           │   └── reminders/route.ts    # Automatic reminder cron
│           ├── appointment/
│           │   ├── confirm/route.ts      # Email confirm handler
│           │   └── cancel/route.ts       # Email cancel handler
│           ├── admin/
│           │   ├── reminders/route.ts    # Manual reminder + stats
│           │   └── followup/route.ts     # Follow-up management
│           └── webhook/
│               └── whatsapp/route.ts     # WhatsApp webhook
├── .env.example               # Environment template

backend/sql/
└── 011_reminder_confirmation_system.sql  # Database migration

clinic-admin/
├── appointments.php           # Updated: Reminder & confirmation columns
├── dashboard.php              # Updated: Reminder statistics
├── css/style.css              # Updated: New badge styles
└── js/reminders.js            # New: Manual reminder sending
```

---

## 🔄 Workflow

1. **Patient books appointment** → Status: `pending`, Confirmation: `pending`
2. **Cron runs (every 30 min)** → Checks appointments within 24 hours
3. **Reminders sent** → WhatsApp + Email → Status: `reminder_sent`
4. **Patient replies YES** (WhatsApp) or clicks Confirm (Email) → Status: `confirmed`
5. **Patient replies NO** or clicks Cancel → Status: `cancelled`
6. **If confirmed + is follow-up** → Auto-creates next follow-up appointment
7. **Admin can manually send** reminders via dashboard button
