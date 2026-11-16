# VALENOR Horology — Professional B2C Luxury E-Commerce Platform

A complete, modern, fully functional B2C E-commerce platform with a separate Admin Panel built using **Core PHP (Structured Architecture)**, **MySQL / MariaDB**, **HTML5**, **CSS3**, **JavaScript**, and **Bootstrap 5**.

The website features a **White & Caramel** luxury color aesthetic, mandatory customer login for shopping cart management, product comparison, dynamic price range filtering, server-side product image management (1–10 images), 14 order statuses, customer return management, verified reviews, and Cash on Delivery (COD) fulfillment.

---

## 1. Design & Color Palette

- **Primary Background:** Pure White (`#FFFFFF`)
- **Secondary Background:** Warm Cream (`#F8F3EB`)
- **Primary Caramel:** Luxury Caramel (`#B8895B`)
- **Dark Caramel:** Deep Roast Caramel (`#8B6342`)
- **Text:** Dark Charcoal (`#252525`)
- **Borders & Dividers:** Light Beige (`#E8DED2`)
- **Typography:** Cormorant Garamond (Display / Headings) & Inter (Body / UI)

---

## 2. Technology Stack

- **Backend:** Core PHP 8.1+ (OOP/Structured PDO Architecture, Sessions, CSRF, Prepared Statements)
- **Database:** MySQL 8.0+ or MariaDB 10.5+ / 11.4+ (InnoDB, UTF-8 mb4, Full Foreign Keys & Constraints)
- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Bootstrap Icons
- **Server:** Apache / XAMPP / WampServer / PHP Built-in Server (`php -S localhost:8000`)

---

## 3. Project Structure

```text
valenor-ecommerce/
├── admin/                     # Protected Administrator Operations Console
│   ├── includes/              # Admin Header, Sidebar, Footer, Breadcrumbs
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   └── footer.php
│   ├── index.php              # Executive Dashboard with Live Analytics
│   ├── products.php           # Product Management (List, Filter, Stock)
│   ├── product-add.php        # Add Product with 1–10 Image Uploader
│   ├── product-edit.php       # Edit Product, Reorder & Replace Images
│   ├── categories.php         # Category CRUD & Image Management
│   ├── orders.php             # Order Management & 14-Status Lifecycle Updates
│   ├── order-detail.php       # Detailed Order Invoice & Tracking
│   ├── returns.php            # Return Requests Review, Approval & Refunds
│   ├── customers.php          # Customer Accounts & Purchase History
│   ├── reviews.php            # Customer Reviews Moderation
│   ├── coupons.php            # Promotional Discount Coupons CRUD
│   ├── content.php            # Hero Banners, Announcements & Promotional Text
│   ├── settings.php           # Store Name, Currency, Shipping & Return Policies
│   ├── login.php              # Secure Admin Sign-In
│   └── logout.php             # Admin Session Logout
├── ajax/                      # Asynchronous Endpoints (Cart, Wishlist, Compare, Filter)
│   ├── cart.php
│   ├── wishlist.php
│   ├── compare.php
│   ├── filter-products.php
│   └── coupon.php
├── assets/                    # Compiled Presentation Assets
│   ├── css/
│   │   ├── style.css          # Customer Theme (White & Caramel)
│   │   └── admin.css          # Admin Portal Theme
│   ├── js/
│   │   ├── main.js            # Storefront Interactions, Cart, Compare, Sliders
│   │   └── admin.js           # Admin Dashboard Controls & Image Manager
│   └── images/
│       ├── logo.svg           # Luxury VALENOR Vector Identity
│       ├── hero-banner.jpg
│       └── promo-sale-banner.jpg
├── config/                    # Core Infrastructure & Configuration
│   ├── config.php             # Central System Settings, Paths & Constants
│   ├── database.php           # Singleton PDO Connection (UTF-8 mb4, ErrorMode Exception)
│   └── helpers.php            # Security (CSRF, XSS `e()`), Flash, Auth, Cart Merging, Uploads
├── database/                  # Relational Database Definitions
│   ├── schema.sql             # Full 22 Relational Tables (InnoDB, FK Constraints)
│   ├── seed.sql               # 5 Categories, 10 Realistic Watches, Admin, Customer, Reviews
│   └── init.php               # One-Click CLI / Web Database Setup Script
├── includes/                  # Reusable Storefront Components
│   ├── header.php             # Document Head, Fonts, Meta Tags, Flash Messages
│   ├── navbar.php             # Announcement Bar, Brand Logo, Navigation, Search & Badges
│   └── footer.php             # Luxury Footer, Policies, Newsletter & Scripts
├── uploads/                   # Media Upload Storage
│   ├── products/              # 1–10 Product Images per Timepiece
│   ├── reviews/               # Verified Customer Review Photos
│   └── returns/               # Defect / Return Proof Images
├── .htaccess                  # Apache Security Directives & Security Headers
├── .gitignore                 # Version Control Ignore Rules
├── cart.php                   # Shopping Cart (Protected — Mandatory Customer Login)
├── categories.php             # Full Category Showcase
├── checkout.php               # Cash on Delivery (COD) Checkout & Summary
├── compare.php                # Side-by-Side Product Comparison Table
├── forgot-password.php        # Password Recovery Workflow
├── index.php                  # Homepage (Hero, Featured, Arrivals, Best Sellers, Reviews)
├── login.php                  # Customer Authentication & Session Login
├── logout.php                 # Customer Logout
├── my-account.php             # Customer Profile, Saved Addresses & Security
├── my-orders.php              # Customer Order History & Tracking
├── order-details.php          # Detailed Order Receipt & Invoice
├── order-success.php          # Order Confirmation & Order Number
├── product-detail.php         # Product Detail Page (1–10 Gallery, Specs, Reviews)
├── products.php               # Product Catalog with Search, Dynamic Price Range & Filters
├── register.php               # Customer Account Registration
├── request-return.php         # 14-Day Hassle-Free Product Return Submission
├── track-order.php            # Real-Time Order Status Tracker
├── wishlist.php               # Customer Saved Timepieces
└── README.md                  # System Documentation & Run Guide
```

---

## 4. Database Setup & Installation

### Option A: Quick Automated Setup (Recommended)
From the project root directory, run:
```bash
php database/init.php
```
*Or open `http://localhost:8000/database/init.php` in your browser.*

### Option B: Manual phpMyAdmin Import
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a database named `valenor_ecommerce` with collation `utf8mb4_unicode_ci`.
3. Import `database/schema.sql`.
4. Import `database/seed.sql`.

---

## 5. Starting the Local PHP Server

Run PHP's built-in web server from the project directory:
```bash
php -S localhost:8000
```
Then visit:
- **Customer Storefront:** `http://localhost:8000`
- **Admin Portal:** `http://localhost:8000/admin/login.php`

---

## 6. Default Credentials

### Administrator
- **URL:** `http://localhost:8000/admin/login.php`
- **Email:** `admin@valenor.com`
- **Password:** `Admin@12345`

### Demo Customer
- **URL:** `http://localhost:8000/login.php`
- **Email:** `customer@valenor.com`
- **Password:** `Admin@12345`

---

## 7. Development Phases

| Phase | Milestone | Status |
| :---: | :--- | :---: |
| **01** | Project structure, 22-table database schema, and configuration | **COMPLETED & TESTED** |
| **02** | Customer website design, White & Caramel styling, and homepage | Up Next |
| **03** | Product categories and product detail pages (1–10 image gallery) | Scheduled |
| **04** | Admin dashboard, executive analytics, and product management | Scheduled |
| **05** | Image upload management (1–10 limits) and product comparison | Scheduled |
| **06** | Search, multi-facet filters, and dynamic price range slider | Scheduled |
| **07** | Mandatory login cart, cart merging, and Cash on Delivery checkout | Scheduled |
| **08** | Order management, 14-status lifecycle, and customer tracking | Scheduled |
| **09** | Returns & refunds workflow and customer reviews/ratings | Scheduled |
| **10** | Security hardening, CSRF/XSS verification, and final quality audit | Scheduled |
