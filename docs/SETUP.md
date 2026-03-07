# Bansari Homeopathy Clinic – Setup Guide

Complete setup instructions for the patient website (Next.js) and admin dashboard (PHP).

---

## Prerequisites

| Software | Version | Purpose |
|----------|---------|---------|
| PHP | 8.0+ | Admin Dashboard & API |
| MySQL | 5.7+ / MariaDB 10.3+ | Database |
| Node.js | 18+ | Next.js Frontend |
| npm | 9+ | Package Manager |
| Composer | 2.x | PHP Dependencies (already installed) |

---

## 1. Database Setup

1. Create the database and import the schema:

```bash
mysql -u root -p < database/schema.sql
```

This creates the `bansari_clinic` database with 12 tables and seeds default data including:
- Default admin account
- Default website settings (about, contact, home, general)

**Default Admin Credentials:**
- Email: `admin@bansari.com`
- Password: `Admin@123`

> ⚠️ Change the default admin password immediately after first login.

---

## 2. Backend (PHP API + Admin)

### Configuration

Edit `backend/config/clinic_config.php` if your MySQL credentials differ from the defaults:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'bansari_clinic');
define('DB_USER', 'root');
define('DB_PASS', '');
```

You can also set these via environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

### Start the PHP Server

```bash
# From the project root directory
php -S localhost:8080 -t .
```

The server must run on port **8080** (the frontend expects this).

### Verify

- Admin Dashboard: [http://localhost:8080/clinic-admin/login.php](http://localhost:8080/clinic-admin/login.php)
- API Test: [http://localhost:8080/backend/api/clinic/testimonials.php](http://localhost:8080/backend/api/clinic/testimonials.php)

---

## 3. Frontend (Next.js)

### Install Dependencies

```bash
cd frontend
npm install
```

### Development Server

```bash
npm run dev
```

Opens at [http://localhost:3000](http://localhost:3000).

### Production Build

```bash
npm run build
npm start
```

### Environment Variables (Optional)

Create `frontend/.env.local` to override the API URL:

```
NEXT_PUBLIC_API_URL=http://localhost:8080/backend/api/clinic
```

---

## 4. Upload Directories

These directories must be writable by the PHP process:

```
uploads/
├── testimonials/    # Before/after images
├── about/           # Doctor + clinic images
├── home/            # Hero images
├── general/         # Logo
└── temp/            # Temporary files
```

Create them if not present:

```bash
mkdir -p uploads/testimonials uploads/about uploads/home uploads/general
```

On Linux/Mac:
```bash
chmod -R 775 uploads/
```

---

## 5. Directory Structure

```
mediconnect/
├── frontend/                  # Next.js 14 Patient Website
│   ├── src/
│   │   ├── app/               # App Router pages
│   │   │   ├── page.tsx       # Home
│   │   │   ├── about/         # About Us
│   │   │   ├── contact/       # Contact Us
│   │   │   ├── testimonials/  # Testimonials
│   │   │   └── book-appointment/ # Appointment Booking
│   │   ├── components/        # Navbar, Footer
│   │   └── lib/               # API client, i18n
│   └── package.json
│
├── clinic-admin/              # PHP Admin Dashboard
│   ├── login.php              # Admin Login
│   ├── dashboard.php          # Dashboard (stats overview)
│   ├── appointments.php       # Appointment List
│   ├── appointment_view.php   # Appointment Detail
│   ├── testimonials.php       # Testimonial List
│   ├── testimonial_form.php   # Add/Edit Testimonial
│   ├── about.php              # About Page CMS
│   ├── contact_settings.php   # Contact Settings
│   ├── messages.php           # Contact Messages
│   ├── settings.php           # General Settings
│   ├── includes/              # Auth, DB, Header, Sidebar, etc.
│   └── css/style.css          # Admin Styles
│
├── backend/
│   ├── api/clinic/            # REST API Endpoints
│   │   ├── appointments.php   # POST – Book appointment
│   │   ├── testimonials.php   # GET – Public testimonials
│   │   ├── settings.php       # GET – Website settings
│   │   └── contact.php        # POST – Contact form
│   └── config/
│       ├── clinic_config.php  # Master configuration
│       └── clinic_db.php      # PDO connection + helpers
│
├── database/
│   └── schema.sql             # Full database schema
│
└── uploads/                   # User-uploaded files
```

---

## 6. Admin Dashboard Pages

| Page | URL | Description |
|------|-----|-------------|
| Login | `/clinic-admin/login.php` | Secure admin login |
| Dashboard | `/clinic-admin/dashboard.php` | Overview stats, recent appointments |
| Appointments | `/clinic-admin/appointments.php` | List, search, filter, status update |
| Appointment Detail | `/clinic-admin/appointment_view.php?id=X` | Full patient + medical view |
| Testimonials | `/clinic-admin/testimonials.php` | List, toggle status, delete |
| Add/Edit Testimonial | `/clinic-admin/testimonial_form.php` | Before/after images, rating |
| About Page | `/clinic-admin/about.php` | Doctor profile, clinic info |
| Contact Settings | `/clinic-admin/contact_settings.php` | Address, phone, email, map |
| Messages | `/clinic-admin/messages.php` | Contact form submissions |
| Settings | `/clinic-admin/settings.php` | Clinic name, logo, home hero |

---

## 7. API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/backend/api/clinic/appointments.php` | Book an appointment (offline/online) |
| `GET` | `/backend/api/clinic/testimonials.php` | Get active testimonials |
| `GET` | `/backend/api/clinic/settings.php?group=about` | Get settings by group |
| `POST` | `/backend/api/clinic/contact.php` | Submit contact form |

---

## 8. Quick Start (TL;DR)

```bash
# 1. Import database
mysql -u root -p < database/schema.sql

# 2. Start PHP backend (keep running)
php -S localhost:8080 -t .

# 3. In another terminal – start frontend
cd frontend
npm install
npm run dev

# 4. Open in browser
# Patient site:  http://localhost:3000
# Admin panel:   http://localhost:8080/clinic-admin/login.php
# Admin login:   admin@bansari.com / Admin@123
```

---

## 9. Tech Stack

- **Frontend:** Next.js 14, React 18, TypeScript, Tailwind CSS
- **Admin:** Core PHP, Bootstrap 5, PDO
- **Database:** MySQL (utf8mb4)
- **i18n:** English + Gujarati (custom implementation)
- **Security:** bcrypt, CSRF tokens, prepared statements, session timeout
