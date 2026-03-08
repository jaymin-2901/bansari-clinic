# Clinic Admin PHP Backend

A comprehensive clinic management system with PHP backend and admin dashboard for Bansari Homeopathy Clinic.

## Project Overview

This is the PHP backend and admin dashboard portion of the Bansari Homeopathy Clinic management system. It provides:

- Admin authentication and dashboard
- Patient management
- Appointment scheduling and tracking
- Follow-up management
- Testimonials management
- Legal pages (Privacy Policy, Terms & Conditions)
- Contact messages management
- File uploads for patient reports and clinic images
- RESTful API endpoints for frontend integration

## Features

- **Patient Registration**: Register and manage patient records
- **Appointment Booking**: Schedule and track appointments (offline/online)
- **Follow-up Management**: Track and manage patient follow-ups
- **Admin Dashboard**: Comprehensive dashboard with analytics
- **File Uploads**: Upload patient reports, prescriptions, and clinic images
- **MySQL Database**: Robust database with complete schema
- **REST API**: JSON-based API for frontend integration

## Project Structure

```
clinic-admin-php/
├── config/
│   └── db.php              # Database configuration
├── api/                    # API endpoints
│   ├── clinic/            # Clinic-specific APIs
│   ├── export_appointments.php
│   ├── legal_pages.php
│   ├── manage_backups.php
│   ├── notifications.php
│   ├── restore_backup.php
│   └── search_appointments.php
├── admin/                  # Admin pages (dashboard, patients, etc.)
├── includes/               # Shared includes
│   ├── auth.php           # Authentication functions
│   ├── db.php             # Database connection
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   └── sidebar.php
├── uploads/                # Uploaded files
├── assets/
│   ├── css/
│   └── js/
├── index.php               # Entry point (redirects to login)
├── login.php               # Admin login
└── logout.php              # Admin logout
```

## Prerequisites

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx) or XAMPP/WAMP

## Installation Steps

### Option 1: Using XAMPP (Recommended for Local Development)

1. **Install XAMPP**
   - Download XAMPP from https://www.apachefriends.org/
   - Install and start Apache and MySQL services

2. **Set Up Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `bansari_clinic`
   - Import the SQL schema:
     - Go to Import tab
     - Select `database/schema.sql` file
     - Click "Go"

3. **Configure Database Connection**
   - Edit `config/db.php`
   - Update the database credentials:
     ```php
     $db_host = 'localhost';
     $db_port = '3306';  // Default MySQL port (or 3307 if using XAMPP)
     $db_name = 'bansari_clinic';
     $db_user = 'root';
     $db_pass = '';      // Default XAMPP has no password
     ```

4. **Deploy Project**
   - Copy the `clinic-admin-php` folder to XAMPP's `htdocs` directory:
     ```
     C:\xampp\htdocs\clinic-admin-php\
     ```

5. **Run the Project**
   - Open browser and navigate to: http://localhost/clinic-admin-php

6. **Login**
   - Default credentials:
     - Email: `admin@bansari.com`
     - Password: `Admin@123`

### Option 2: Using Web Hosting (InfinityFree, etc.)

1. **Upload Files**
   - Upload all files to your hosting account
   - Place in the root directory or a subfolder

2. **Create Database**
   - Create a MySQL database through your hosting control panel
   - Note the database credentials (host, name, user, password)

3. **Import Database**
   - Import the SQL schema via phpMyAdmin in your hosting panel

4. **Update Configuration**
   - Edit `config/db.php` with your hosting database credentials:
     ```php
     $db_host = 'localhost';  // Or your hosting's MySQL host
     $db_port = '3306';
     $db_name = 'your_database_name';
     $db_user = 'your_database_user';
     $db_pass = 'your_database_password';
     ```

5. **Access Your Site**
   - Visit your domain to access the admin panel

## API Endpoints

The backend provides RESTful JSON APIs:

### Authentication
- `POST /api/auth/login.php` - Admin login

### Appointments
- `GET /api/appointments.php` - List all appointments
- `POST /api/appointments.php` - Create appointment
- `GET /api/search_appointments.php` - Search appointments
- `GET /api/export_appointments.php` - Export appointments

### Patients
- `GET /api/patients.php` - List patients (via main admin)

### Clinic Settings
- `GET /api/clinic/settings.php` - Get clinic settings
- `POST /api/clinic/settings.php` - Update settings

### Testimonials
- `GET /api/clinic/testimonials.php` - Get testimonials
- `POST /api/clinic/testimonials.php` - Add testimonial

### Images
- `GET /api/clinic/clinic_images.php` - List clinic images
- `POST /api/clinic/clinic_images.php` - Upload image
- `DELETE /api/clinic/clinic_images.php?id=X` - Delete image

## Configuration

### Database Settings (config/db.php)

```php
// Database credentials
$db_host = 'localhost';      // Database host
$db_port = '3307';           // Database port (3306 or 3307)
$db_name = 'bansari_clinic'; // Database name
$db_user = 'root';           // Database username
$db_pass = '';               // Database password
```

### Upload Settings

Maximum file size: 5MB
Allowed file types: JPG, JPEG, PNG, WebP, GIF

## Security Features

- Secure session configuration
- CSRF protection
- Password hashing (bcrypt)
- SQL injection prevention (PDO prepared statements)
- XSS protection (output sanitization)
- Secure file upload validation

## Default Admin Account

| Field | Value |
|-------|-------|
| Name | Dr. Bansari Patel |
| Email | admin@bansari.com |
| Password | Admin@123 |

**Important**: Change the default password after first login!

## Troubleshooting

### Database Connection Error
- Check MySQL service is running
- Verify database credentials in `config/db.php`
- Ensure database exists and user has permissions

### 403 Forbidden Error
- Check file permissions (folders: 755, files: 644)
- Verify .htaccess is present

### Upload Issues
- Check uploads folder permissions
- Verify PHP upload limits in php.ini

### Session Issues
- Check PHP session configuration
- Ensure cookies are enabled

## File Upload Security

The `uploads/` directory is protected with:
- `.htaccess` to prevent PHP execution
- `index.php` to prevent directory listing
- File type validation
- Maximum file size limit

## Deployment Checklist

- [ ] Database created and schema imported
- [ ] Database credentials configured in `config/db.php`
- [ ] Default admin password changed
- [ ] Upload folder permissions set ( writable)
- [ ] SSL certificate installed (for production)
- [ ] Error logging configured
- [ ] Backup system in place

## Support

For issues or questions:
- Email: info@bansarihomeopathy.com

## License

Copyright © 2024 Bansari Homeopathy Clinic. All rights reserved.

