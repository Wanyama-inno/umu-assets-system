# Uganda Martyrs University — Assets Management System
## Masaka Campus | Version 1.0

---

## 📋 System Overview
A full-featured web-based Assets Management System built with PHP, MySQL, HTML, and CSS for Uganda Martyrs University Masaka Campus.

---

## 🚀 Installation Guide

### Prerequisites
- **PHP** 7.4+ or 8.x
- **MySQL** 5.7+ or MariaDB 10+
- **Apache/Nginx** web server (XAMPP, WAMP, or LAMP)

### Step 1: Set Up Files
1. Copy the entire `umu_assets/` folder to your web server root:
   - XAMPP: `C:/xampp/htdocs/umu_assets/`
   - WAMP: `C:/wamp64/www/umu_assets/`
   - Linux: `/var/www/html/umu_assets/`

### Step 2: Create the Database
1. Open **phpMyAdmin** or MySQL console
2. Run the SQL schema file:
   ```sql
   SOURCE /path/to/umu_assets/database.sql;
   ```
   OR copy-paste the contents of `database.sql` into phpMyAdmin SQL tab

### Step 3: Configure Database Connection
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');      // Your MySQL host
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');               // Your MySQL password
define('DB_NAME', 'umu_assets_db');  // Database name
define('BASE_URL', 'http://localhost/umu_assets/');
```

### Step 4: Access the System
Open your browser and go to: **http://localhost/umu_assets/**

---

## 🔐 Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| **Admin** (Assets Manager) | admin@umu.ac.ug | password |
| **Staff** | staff@umu.ac.ug | password |
| **Student** | student@umu.ac.ug | password |

> ⚠️ **Change passwords immediately after first login!**

---

## 📁 Project Structure

```
umu_assets/
├── index.php              # Login / Register page
├── logout.php             # Session logout
├── database.sql           # MySQL schema + seed data
├── includes/
│   ├── config.php         # DB config + helper functions
│   ├── sidebar.php        # Navigation sidebar
│   └── header.php         # Top header bar
├── pages/
│   ├── dashboard.php      # Main dashboard
│   ├── assets.php         # Asset management (CRUD)
│   ├── users.php          # User management (Admin only)
│   ├── borrow.php         # Borrow assets
│   ├── returns.php        # Process returns & clearance
│   ├── maintenance.php    # Maintenance logs
│   ├── reports.php        # Reports (Admin only)
│   └── profile.php        # User profile
├── css/
│   └── style.css          # Main stylesheet
└── js/
    └── app.js             # JavaScript utilities
```

---

## ✅ Features Summary

### User Management
- Register/login for Admin, Staff, Students
- Admin can add, activate/deactivate, and delete users
- Password reset functionality

### Asset Management
- Full CRUD for all assets
- Asset classification: Borrowable vs Non-Borrowable
- Condition tracking: Good, Damaged, Under Maintenance
- Status tracking: Available, Borrowed, In Use, Under Repair

### Borrowing System
- Only borrowable + available assets can be issued
- Records borrower details, purpose, dates
- Prevents double-borrowing

### Returns & Clearance
- Process returns with condition assessment
- Overdue detection and alerts
- Auto-updates asset status on return

### Maintenance Monitoring
- Log maintenance activities for fixed assets
- Track technician, cost, dates
- Maintenance history and status workflow

### Reports
- All Assets report
- Currently Borrowed report
- Overdue Assets report
- Returned Assets (by date range)
- Maintenance Logs (by date range)
- Damaged Assets report
- Print-friendly output

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `users` | System users (admin, staff, students) |
| `asset_categories` | Asset classification categories |
| `assets` | All university assets |
| `borrow_records` | Borrowing transactions |
| `maintenance_logs` | Maintenance history |

---

## 🔒 Access Control

| Feature | Admin | Staff | Student |
|---------|-------|-------|---------|
| Dashboard | ✅ | ✅ | ✅ |
| View Assets | ✅ | ✅ | ✅ |
| Add/Edit/Delete Assets | ✅ | ❌ | ❌ |
| Issue Assets | ✅ | ✅ | ❌ |
| Process Returns | ✅ | ❌ | ❌ |
| Manage Users | ✅ | ❌ | ❌ |
| Maintenance Logs | ✅ | ❌ | ❌ |
| Reports | ✅ | ❌ | ❌ |

---

## 📞 Support
Uganda Martyrs University — ICT Department, Masaka Campus
