# Bansari Homeopathy Clinic System

A complete clinic management system with a Next.js patient-facing frontend and PHP admin dashboard.

## Project Structure

```
bansari-homeopathy/
├── frontend-nextjs/       # Next.js 14 patient-facing website (TypeScript)
│   ├── src/
│   │   ├── app/           # Next.js App Router pages
│   │   ├── components/    # React components
│   │   ├── i18n/          # Internationalization (English + Gujarati)
│   │   ├── lib/           # Utilities, API client, Prisma, email services
│   │   └── types/         # TypeScript type definitions
│   ├── prisma/            # Prisma schema (MySQL)
│   ├── package.json       # Next.js dependencies
│   ├── .env.local         # Environment variables
│   └── next.config.js     # Next.js configuration
│
├── clinic-admin-php/      # PHP Admin Dashboard (clinic management)
│   ├── *.php              # Admin pages (dashboard, appointments, patients, etc.)
│   ├── includes/          # Auth, DB connection, header/footer/sidebar
│   ├── api/               # Admin API endpoints (export, search, backups)
│   ├── css/               # Admin stylesheets
│   └── js/                # Admin JavaScript (reminders, followup)
│
├── backend-php/           # PHP Backend API & Services
│   ├── api/
│   │   └── clinic/        # Clinic-specific APIs (appointments, slots, etc.)
│   ├── config/            # Database & app configuration
│   ├── cron/              # Scheduled tasks (reminders, backups)
│   ├── email/             # Email service (PHPMailer)
│   ├── sms/               # SMS service (Fast2SMS)
│   ├── security/          # Auth middleware, CORS, rate limiting
│   ├── sql/               # Database migrations
│   └── logs/              # Application logs
│
├── patient-form/          # Standalone patient intake form
├── public/uploads/        # Uploaded files (testimonials, about images)
├── vendor/                # PHP dependencies (Composer)
├── database/              # Database schema
├── backups/               # Database backups
├── composer.json          # PHP dependency manifest
└── docs/                  # Documentation
```

## Getting Started

### Prerequisites
- Node.js 18+
- PHP 8.1+
- MySQL 8.0+
- Composer

### 1. Next.js Frontend

```bash
cd frontend-nextjs
cp .env.example .env.local   # Edit with your database/API credentials
npm install
npx prisma generate
npm run dev                  # Starts on http://localhost:3000
```

### 2. PHP Backend & Admin Dashboard

```bash
# Install PHP dependencies
composer install

# Set up MySQL database
mysql -u root -p < database/schema.sql

# Start PHP built-in server for backend API
cd backend-php
php -S localhost:8000

# In another terminal, serve clinic admin
cd clinic-admin-php
php -S localhost:8080
```

### 3. Environment Variables

- **Next.js**: Configure in `frontend-nextjs/.env.local` (see `.env.example`)
- **PHP**: Configure in `backend-php/config/clinic_config.php` or set environment variables

### Key Environment Variables

| Variable | Purpose |
|----------|---------|
| `DATABASE_URL` | MySQL connection string for Prisma |
| `PHP_BACKEND_URL` | URL where PHP backend is running |
| `SMTP_*` | Email configuration |
| `WHATSAPP_*` | WhatsApp Business API credentials |
| `CRON_SECRET` | Secret key for cron job authentication |

## Database

- **Database name**: `bansari_clinic`
- **Schema**: `database/schema.sql` and `frontend-nextjs/prisma/schema.prisma`
- **Port**: Default MySQL port 3307 (configurable)
