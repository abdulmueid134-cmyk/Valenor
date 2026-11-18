# VALENOR Horology — Luxury B2C E-Commerce Platform

A production-grade, secure, modern B2C luxury timepiece e-commerce platform with a separate Admin Concierge Operations Panel. Built in accordance with strict enterprise standards using **Core PHP 8.3 (Structured & PDO Architecture)**, **MariaDB / MySQL**, **HTML5**, **CSS3**, **JavaScript (ES6+)**, and **Bootstrap 5.3**.

Live Deployment: [https://valenor-eight.vercel.app/](https://valenor-eight.vercel.app/)  
GitHub Repository: [https://github.com/abdulmueid134-cmyk/Valenor](https://github.com/abdulmueid134-cmyk/Valenor)

---

## 1. Design & Luxury Aesthetic

- **Theme Palette:** Bespoke White & Caramel
  - Primary Background: Pure White (`#FFFFFF`)
  - Secondary Background: Warm Cream (`#F8F3EB`)
  - Primary Caramel: Luxury Gold/Caramel (`#B8895B`)
  - Dark Caramel: Deep Roast Caramel (`#8B6342`)
  - Dark Charcoal Typography: (`#252525`)
  - Accent Dividers: Light Beige (`#E8DED2`)
- **Typography:** Cormorant Garamond (Editorial Headings) & Inter (UI & Numerics)
- **Responsive Breakpoints:** Fully fluid layout adapted for Desktop (1400px+), Laptop (1024px–1200px), Tablet (768px–992px), and Mobile (<768px).

---

## 2. Key System Capabilities

1. **Product Catalog & Dynamic Discovery:**
   - 5 luxury collections, 10 seed timepieces, dynamic database retrieval.
   - 6-dimension discovery search: Timepiece Name, Reference SKU, Brand, Category, Hashtags, and Tags.
   - Dual-handle dynamic price range slider with zero external libraries, recalculating maximum bounds from database prices.
   - Multi-facet filters: Category, Brand, Star Rating, Availability (`in_stock`), Discount Percentage, and 5 Sorting orders.
2. **Product Image Suite (1 to 10 Images per Timepiece):**
   - Minimum 1, maximum 10 images per timepiece.
   - Admin upload, replace, reorder, delete individual images, and primary thumbnail designation.
   - Customer thumbnail switcher and high-resolution fullscreen lightbox modal.
3. **Product Comparison & Private Wishlist:**
   - Side-by-side comparison for 2 to 4 watches with difference-highlighting toggle.
   - Customer wishlist with persistent MySQL storage and Move to Bag functionality.
4. **Mandatory Login Cart & Session Merging:**
   - Guests can freely browse products and add watches to an ephemeral session cart.
   - Attempting to view the shopping bag or proceed to checkout enforces authentication with automatic post-login redirect.
   - Unauthenticated guest carts are seamlessly merged into the customer's database cart upon login without duplication.
5. **Cash on Delivery Checkout:**
   - Real-time stock validation, automated inventory deduction, and initial payment status set to `Unpaid`.
   - Free shipping qualification rules (> 50,000 PKR threshold).
6. **Order Dispatch & Public Live Tracking:**
   - 9-status order progression: `Pending` &rarr; `Confirmed` &rarr; `Processing` &rarr; `Packed` &rarr; `Dispatched` &rarr; `Shipped / In Transit` &rarr; `Out for Delivery` &rarr; `Delivered` &rarr; `Cancelled`.
   - Live public tracking via Order Number or Courier Tracking ID with an 8-milestone visual stepper.
   - Printable luxury invoices with company header, barcode, financial breakdown, and print CSS styles.
   - Customer self-service order cancellation for pending orders with automated inventory restoration.
7. **After-Sales Returns & Refunds:**
   - 7-status return lifecycle: `Return Requested`, `Return Approved`, `Return Rejected`, `Return in Transit`, `Returned`, `Refund Processing`, `Refunded`.
   - Configurable return deadline window (default 14 days) and return policy text.
   - Supporting photo upload for return requests, customer tracking portal (`my-returns.php`), and admin review console (`admin/returns.php`) with mandatory rejection reasons, stock restoration, and financial refund authorization.
8. **Customer Reviews & Verified Buyer Badges:**
   - 1–5 star ratings, review headlines, detailed feedback, and wristshot photo uploads.
   - Strict verified purchase rule: only customers with confirmed delivered orders can submit verified buyer reviews.
   - Self-service review editing and admin review moderation suite (`admin/reviews.php`).
9. **Privilege Coupon Management:**
   - Admin coupon creation with percentage or fixed discount, minimum order value, usage limits, max discount cap, start/expiry dates, and active toggling (`admin/coupons.php`).
   - Server-side rule validation in shopping bag and checkout (`checkout.php`).
10. **Admin Concierge Operations Dashboard:**
    - Real-time display of the 9 required business metrics: Total Products, Total Categories, Total Customers, Total Orders, Pending Orders, Dispatched Orders, Delivered Orders, Return Requests, and Total Sales.

---

## 3. Technology Stack & Security Architecture

- **Backend:** Core PHP 8.1+ / 8.3 (Zero Node.js/React backend, structured PDO architecture).
- **Database:** MariaDB 10.5+ / MySQL 8.0+ (InnoDB, UTF-8 mb4, 22 relational tables, 25 foreign key constraints).
- **Security:**
  - **SQL Injection:** 100% prepared PDO statements with parameter binding.
  - **XSS Protection:** Context-aware output escaping via `e()` (`htmlspecialchars` with `ENT_QUOTES`).
  - **CSRF Protection:** Cryptographic 64-character tokens generated with `random_bytes(32)` validated on all state-altering POST requests.
  - **Password Security:** BCRYPT password hashing (`PASSWORD_BCRYPT`).
  - **Session Security:** `session_regenerate_id(true)` upon authentication, strict cookie settings (`httponly`, `samesite=Lax`).
  - **File Upload Security:** MIME-type validation using `finfo`, strict extension whitelist (`jpg`, `jpeg`, `png`, `webp`), size ceiling (5 MB), randomized non-executable filenames.
  - **Authorization:** `require_admin()` enforced across all admin operational endpoints; `require_login()` enforced on customer account, bag, and order pages.

---

## 4. Local Installation & Setup Guide

### Prerequisites
- PHP 8.1, 8.2, or 8.3 with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `openssl` extensions enabled.
- MySQL 8.0+ or MariaDB 10.5+.
- Apache (via XAMPP / WampServer) or the built-in PHP development server.

---

### Step 1: Clone the Repository
```bash
git clone https://github.com/abdulmueid134-cmyk/Valenor.git
cd Valenor
```

---

### Step 2: XAMPP Setup (Option A)

1. **Move Project Folder:**
   Copy the `Valenor` project folder into your XAMPP web root:
   ```text
   C:\xampp\htdocs\antigravity
   ```
2. **Start Apache & MySQL:**
   Open the **XAMPP Control Panel** and click **Start** next to Apache and MySQL.
3. **PHP Configuration (`php.ini`):**
   Ensure the following extensions are uncommented in `C:\xampp\php\php.ini`:
   ```ini
   extension=pdo_mysql
   extension=mbstring
   extension=fileinfo
   extension=openssl
   extension=gd
   upload_max_filesize = 10M
   post_max_size = 12M
   ```
   Restart Apache after modifying `php.ini`.

---

### Step 3: MySQL Database Setup & Import

1. **Via phpMyAdmin (Web UI):**
   - Navigate to `http://localhost/phpmyadmin`.
   - Click **New** and create a database named:
     ```text
     valenor_ecommerce
     ```
     Collation: `utf8mb4_unicode_ci`.
   - Click the **Import** tab.
   - Choose `database/schema.sql` and click **Import**.
   - Next, import `database/seed.sql` to populate initial categories, timepieces, coupons, and test accounts.
   - Run any migrations in `database/migrations/` if updating an existing database.

2. **Via MySQL Command Line (CLI):**
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS valenor_ecommerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p valenor_ecommerce < database/schema.sql
   mysql -u root -p valenor_ecommerce < database/seed.sql
   ```

3. **Via One-Click PHP Setup Script:**
   ```bash
   php database/init.php
   ```

---

### Step 4: Environment & Database Credentials Configuration

Open `config/config.php` and verify your local database settings:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'valenor_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave blank for default XAMPP, or set your password
```

---

### Step 5: How to Run the Website Locally

#### Method A: Using PHP Built-in Server (Fastest)
From the project root directory, run:
```bash
php -S 127.0.0.1:8000
```
Then access:
- **Storefront:** [http://127.0.0.1:8000](http://127.0.0.1:8000)
- **Admin Concierge Panel:** [http://127.0.0.1:8000/admin/login.php](http://127.0.0.1:8000/admin/login.php)

#### Method B: Using XAMPP Apache
If placed in `C:\xampp\htdocs\antigravity`:
- **Storefront:** [http://localhost/antigravity/](http://localhost/antigravity/)
- **Admin Concierge Panel:** [http://localhost/antigravity/admin/login.php](http://localhost/antigravity/admin/login.php)

---

## 5. Default Credentials

### Administrator Account
- **Login URL:** `/admin/login.php`
- **Email:** `admin@valenor.com`
- **Password:** `Admin@12345`

### Demo Customer Account
- **Login URL:** `/login.php`
- **Email:** `customer@valenor.com`
- **Password:** `Admin@12345`

### Customer Registration
New visitors can register at `/register.php` with:
- First Name & Last Name
- Valid Email Address
- Mobile Phone Number (formatted: `+92 3XX XXXXXXX` or international)
- Secure Password (minimum 8 characters)

---

## 6. Development Roadmap & Milestones

| Phase | Description | Status |
| :---: | :--- | :---: |
| **01** | Database schema (22 tables, 25 FKs), seed data, structured PDO architecture | **COMPLETED & TESTED** |
| **02** | Customer website design, White & Caramel theme, 14 homepage sections | **COMPLETED & TESTED** |
| **03** | Product catalog, dynamic categories, product detail page, 1–10 image gallery | **COMPLETED & TESTED** |
| **04** | Admin panel, 9 required business metrics, product CRUD & stock management | **COMPLETED & TESTED** |
| **05** | Image management suite (upload/reorder/delete/primary), product comparison, wishlist | **COMPLETED & TESTED** |
| **06** | Search across 6 dimensions, dynamic price range slider, multi-facet filters | **COMPLETED & TESTED** |
| **07** | Mandatory cart login, guest cart merging, Cash on Delivery checkout | **COMPLETED & TESTED** |
| **08** | 9-stage order status lifecycle, customer order center, live tracking, printable invoices | **COMPLETED & TESTED** |
| **09** | 7-stage return/refund system, verified customer reviews, coupon management | **COMPLETED & TESTED** |
| **10** | Security penetration audit, performance optimization, automated test suite | **COMPLETED & TESTED** |

---

## 7. Automated Test Suite Execution

The repository includes an automated testing harness in `scratch/full_system_test.php` verifying all 22 functional workflows and 5 security penetration assertions:
```bash
php scratch/full_system_test.php
```
**Results:** `27 PASSED, 0 FAILED (100% PASS RATE)`.
