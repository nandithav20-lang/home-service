# 🛠️ Home Service App (HomeServe Pro)

> A modern, full-stack home service management web application developed using **HTML5, CSS3, JavaScript, PHP (PDO), and MySQL**.

---

## 📌 Project Abstract
The **Home Service App** connects homeowners with certified, reliable professionals across household maintenance trades like plumbing, electrical work, carpentry, deep cleaning, painting, and appliance repairs.

The application features three distinct roles and operational modules:
1. **User / Customer Module**: Browse services, search & filter by trade, schedule appointments, track real-time booking status (*Pending*, *Accepted*, *In Progress*, *Completed*, *Cancelled*), and submit ratings & reviews.
2. **Service Provider Module**: Register trade qualifications, set hourly visiting rates, toggle availability status (*Available*, *Busy*, *Offline*), view dispatched customer jobs, and update work progress.
3. **Admin Module**: Central control center to oversee customers, verify providers, manage service pricing, review pending requests, and dispatch professionals.

---

## 🏗️ Architecture & Tech Stack
* **Frontend**: Responsive HTML5, CSS3 Custom Properties (Design System), Vanilla JavaScript (Form validation, live search, dynamic filters).
* **Backend**: PHP 8+ using secure PDO prepared statements, session management, and role-based access control.
* **Database**: MySQL with relational integrity, foreign keys, and indexes.

---

## 📁 Project Directory Structure
```
home-service-app/
├── admin/                     # Administrator Module
│   ├── bookings.php           # Dispatch & booking management
│   ├── categories.php         # Service category management
│   ├── dashboard.php          # Admin KPIs & metrics
│   ├── edit-booking.php       # Edit any booking schedule/price/status
│   ├── edit-category.php      # Edit category name & details
│   ├── edit-provider.php      # Edit technician details & rates
│   ├── edit-service.php       # Edit service name, rate & duration
│   ├── edit-user.php          # Edit customer information
│   ├── providers.php          # Provider verification & approval
│   ├── services.php           # Service catalog & pricing CRUD
│   └── users.php              # Customer directory (Excel records)
├── assets/
│   ├── css/
│   │   └── style.css          # Modern, responsive design system
│   └── js/
│       └── main.js            # Client-side validation & live search
├── auth/
│   ├── login.php              # Unified authentication portal
│   ├── logout.php             # Session destruction
│   ├── provider-register.php  # Service provider registration
│   └── register.php           # Customer registration
├── config/
│   └── db.php                 # Database connection & auth helpers
├── customer/                  # Customer Module
│   ├── add-review.php         # 5-star rating & review submission
│   ├── book.php               # Service booking wizard
│   ├── dashboard.php          # Customer overview & stats
│   ├── edit-booking.php       # Reschedule/edit pending bookings
│   ├── my-bookings.php        # Status tracking & cancellation
│   ├── profile.php            # Edit customer personal profile & password
│   └── services.php           # Interactive service catalog
├── database/
│   └── schema.sql             # SQL schema + initial seed data
├── includes/
│   ├── footer.php             # Global footer
│   └── header.php             # Dynamic role-based navigation bar
├── index.php                  # Landing page
├── .gitignore
└── README.md
```

---

## 🚀 Quick Setup Instructions (XAMPP / WAMP / LAMP)

### 1. Copy to Web Server
Place the `home-service-app` directory inside your web server root:
* **XAMPP (Windows)**: `C:\xampp\htdocs\home-service-app`
* **WAMP (Windows)**: `C:\wamp64\www\home-service-app`
* **Linux (Apache)**: `/var/www/html/home-service-app`

### 2. Import Database
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a database named `home_service_db` (or run `schema.sql` directly).
3. Import the `database/schema.sql` file.

### 3. Database Credentials Configuration
If your MySQL password is not blank, edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // Update if you have a MySQL password
define('DB_NAME', 'home_service_db');
```

### 4. Launch Application
Open your browser and navigate to:
```
http://localhost/home-service-app/
```

---

## 🔑 Demo Accounts & Credentials

| Role | Email | Password |
|---|---|---|
| **System Admin** | `admin@homeservice.com` | `admin123` or `password123` |
| **Customer (Arjun Kumar)** | `arjun.kumar@email.com` | `password123` |
| **Customer (Priya Sharma)** | `priya.sharma@email.com` | `password123` |
| **Provider (Plumber - Ramesh Babu)** | `ramesh.plumber@email.com` | `password123` |
| **Provider (Electrician - Suresh)** | `suresh.electric@email.com` | `password123` |

---

## 🐙 Push to GitHub Guide

If you are initializing a new GitHub repository:

```bash
# 1. Open Terminal / PowerShell inside the project directory:
cd C:\xampp\htdocs\home-service-app

# 2. Initialize Git
git init

# 3. Add all files to staging
git add .

# 4. Commit changes
git commit -m "Initial commit: Home Service App complete system"

# 5. Rename branch to main
git branch -M main

# 6. Add your GitHub repository remote URL:
git remote add origin https://github.com/YOUR_USERNAME/home-service-app.git

# 7. Push to GitHub:
git push -u origin main
```
