# Hostinger Deployment & Setup Guide
## MOMAI PLYWOOD - Core PHP & Vanilla Web Application

This project has been completely refactored from Laravel into **clean, native Core PHP (PHP 8+, PDO, HTML, CSS, JavaScript)**. It runs seamlessly on standard shared hosting (specifically Hostinger cPanel / hPanel) with **zero Composer dependencies**, **zero Node/Vite build steps**, and **zero Artisan CLI requirements**.

---

### 1. Requirements on Hostinger
- **PHP Version**: PHP 8.1, 8.2, 8.3, or 8.4
- **PHP Extensions**: `pdo_mysql`, `mbstring`, `openssl`, `json` (enabled by default on Hostinger)
- **Database**: MySQL 5.7+ or MariaDB 10.3+

---

### 2. Step-by-Step Deployment Instructions

#### Step 1: Create Database on Hostinger hPanel
1. Log in to your **Hostinger hPanel**.
2. Navigate to **Databases** > **MySQL Databases**.
3. Create a new database:
   - **Database Name**: e.g., `u123456789_momai`
   - **Username**: e.g., `u123456789_admin`
   - **Password**: e.g., `YourStrongPassword#2026`
4. Click **Create**.
g#I;^l1O
#### Step 2: Import Database Schema via phpMyAdmin
1. On the same Databases page, click **Enter phpMyAdmin** next to your newly created database.
2. Click the **Import** tab in the top navigation bar.
3. Click **Choose File** and select `schema.sql` from your project root.
4. Click **Go** at the bottom of the page.
5. All tables (`users`, `products`, `customers`, `bills`, `bill_items`, `bill_payments`) will be created automatically with the default administrator account.

#### Step 3: Configure Database Connection
Open `config/db.php` on your computer or directly inside Hostinger File Manager, and update your credentials:

```php
define('DB_HOST', 'localhost'); // Usually 'localhost' on Hostinger
define('DB_PORT', '3306');
define('DB_NAME', 'u123456789_momai'); // Your Hostinger database name
define('DB_USER', 'u123456789_admin'); // Your Hostinger database user
define('DB_PASS', 'YourStrongPassword#2026'); // Your Hostinger database password
```

#### Step 4: Upload Project Files to `public_html`
1. In Hostinger hPanel, go to **Files** > **File Manager**.
2. Open the `public_html` directory of your domain or subdomain.
3. Upload the following files and folders:
   - `assets/` (contains CSS, JS, and image assets)
   - `auth/` (login, logout, session guards)
   - `config/` (contains `db.php`)
   - `includes/` (header, footer, sidebar, topbar, helpers)
   - `index.php`
   - `dashboard.php`
   - `products.php`
   - `product-adjust-stock.php`
   - `product-search.php`
   - `bills.php`
   - `bill-create.php`
   - `bill-slip.php`
   - `bill-due-slip.php`
   - `bill-payment.php`
   - `reports.php`
   - `profile.php`
   - `.htaccess`
4. *(Note: You do NOT need to upload the old `vendor/`, `node_modules/`, `app/`, `tests/`, or `.env` directories!)*

---

### 3. Default Login Credentials
- **URL**: `https://yourdomain.com/` (or `https://yourdomain.com/auth/login.php`)
- **Email**: `admin@momai.com`
- **Password**: `password123`

> [!TIP]
> After your first login, visit **Settings** in the sidebar to change your password and name to your preferred credentials.

---

### 4. Key Architectural Highlights
- **100% Secure PDO Prepared Statements**: All SQL queries utilize parameter binding, completely eliminating SQL injection risks.
- **Atomic Transactions**: Generating bills and collecting due payments are wrapped inside `beginTransaction()` / `commit()` blocks to safeguard stock quantities and customer balances against race conditions.
- **Identical UI & Print Accuracy**: The printable physical invoice (`bill-slip.php`) and thermal due slip (`bill-due-slip.php`) preserve the exact red border layout, typography, and numbers-to-words format.
