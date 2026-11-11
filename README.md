# VALENOR Horology — Watch E-Commerce Storefront & Admin Dashboard

A full-stack, responsive B2C luxury watch storefront paired with a role-governed Admin Operations & Analytics Dashboard built on **Node.js**, **Express.js**, **MySQL / MariaDB**, **Chart.js**, and modular vanilla JavaScript.

---

## Architecture Overview

```text
watch-ecommerce/
├── backend/
│   ├── config/          # MySQL2 connection pool (utf8mb4)
│   ├── controllers/     # Request/response controllers (MVC)
│   ├── middleware/      # JWT auth, RBAC guards, CSP/security headers, rate limiters
│   ├── models/          # Parameterized & transactional SQL queries
│   ├── routes/          # REST API endpoints (/api/*, /api/admin/*)
│   ├── uploads/         # Product, return proof, and branding image storage
│   └── server.js        # Express application entry point
├── database/
│   ├── schema.sql       # Base relational catalog tables
│   ├── migrations/      # Incremental SQL migrations (002–007)
│   ├── seed.sql         # Demo catalog showcase seed (optional)
│   ├── init_db.js       # Supports --schema-only, --seed-only, or combined init
│   └── create_admin.js  # Secure CLI utility to create/promote admin users
├── frontend/
│   ├── index.html       # Storefront homepage
│   ├── pages/           # Catalog, Product Detail, Auth, Account, Cart, Checkout, Track Order, Policies
│   ├── admin/           # Separate protected Admin Portal (login + 17-item executive console)
│   ├── components/      # Shared UI cards, XSS-safe escaper, header/footer shells
│   └── assets/          # CSS, JS modules, product imagery, SVG brand assets
├── .env.example
└── package.json
```

---

## Prerequisites & Setup

### 1. Node.js Setup
- Install **Node.js v18+** (tested on **v22.14.0**) with `npm`.
- Install backend dependencies from the project root:
  ```bash
  npm install
  ```

### 2. MySQL / MariaDB Database Setup
- Ensure **MySQL 8.0+** or **MariaDB 11.4+** is running locally on port `3306` (or configure custom host/port in `.env`).

### 3. Environment Configuration
Copy `.env.example` to `.env` and fill in your credentials and cryptographic secrets:
```bash
cp .env.example .env
```
Key variables in `.env`:
- `PORT=5000`
- `NODE_ENV=development` (set to `production` in deployment; `JWT_SECRET` becomes strictly mandatory)
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME=valenor_watches`
- `JWT_SECRET` — generate a long random hex secret (e.g. `node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"`)
- `CORS_ORIGIN` — comma-separated allowed origins in production (e.g. `https://shop.yourdomain.com`)

### 4. Initialize the Database (Demo vs Production Separation)
VALENOR separates **clean production schema initialization** from **optional demo showcase data**:

- **Clean Production Schema Only (no demo watches, reviews, or test orders):**
  ```bash
  npm run db:schema
  ```
- **Load Optional Demo Showcase Catalog (14 categories, 6 brands, 20 watches, reviews):**
  ```bash
  npm run db:seed-demo
  ```
- **Quick Local Full Setup (Schema + Migrations + Demo Catalog):**
  ```bash
  npm run db:init
  ```

### 5. Create the First Super Admin Securely
Never hardcode admin passwords inside frontend files. Provision your first **Super Admin** from the CLI using `bcryptjs` hashing:
```bash
npm run admin:create -- --email=admin@yourdomain.com --password=StrongAdminPassword123 --name="Store Owner" --phone=+923000000001 --role=super_admin
```
Supported staff roles:
- `super_admin` — Full access across Store Settings, Admin Staff, Role Permissions, Reports, Promotions, Orders, Customers, Products, and Inventory.
- `manager` — Operational access across Catalog, Orders, Returns, Customers, Promotions, and Reports.
- `order_manager` — Fulfillment & customer service access across Orders, Deliveries, Returns, Refunds, and Customers.
- `inventory_manager` — Catalog, Product Types, Categories, Brands, Images, and Inventory control.

---

## Starting the Server

- **Production:**
  ```bash
  npm start
  ```
- **Development (auto-reload with nodemon):**
  ```bash
  npm run dev
  ```
Health check endpoint: `http://localhost:5000/api/health`

---

## Accessing Storefront & Admin Portal

### Customer Storefront
- **Homepage:** `http://localhost:5000/index.html`
- **Full Catalog & Filters (Dynamic `0..max` price range):** `http://localhost:5000/pages/catalog.html`
- **Product Detail Page:** `http://localhost:5000/pages/product.html?id=1`
- **Customer Sign In / Register / Password Reset:** `http://localhost:5000/pages/auth.html`
- **Collector Account Dashboard & Addresses:** `http://localhost:5000/pages/account.html`
- **Shopping Cart & Checkout:** `http://localhost:5000/pages/cart.html` / `http://localhost:5000/pages/checkout.html`
- **Live Order Tracking & Return Requests:** `http://localhost:5000/pages/track-order.html`
- **Storefront Policies (Return, Privacy, Terms):** `http://localhost:5000/pages/policies.html`

### Protected Admin Dashboard
- **Admin Sign-In:** `http://localhost:5000/admin/login.html`
- **Admin Executive Console:** `http://localhost:5000/admin/index.html`
  - Unauthenticated visits automatically redirect to `/admin/login.html`.
  - Ordinary `customer` accounts receive HTTP `403 Forbidden` on admin login and every `/api/admin/*` API route.

---

## Security Practices Implemented
- **Password Hashing:** `bcryptjs` with per-user salts (`cost 10–12`), passwords never stored or returned in API payloads.
- **SQL Injection Defense:** 100% parameterized prepared queries via `mysql2/promise`.
- **XSS Protection:** HTML entity escaping (`escapeHtml` / `esc`) across dynamic UI renders; strict `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and `Referrer-Policy` headers.
- **File Upload Validation:** Multer disk storage restricted strictly to `.jpg`, `.jpeg`, `.png`, `.webp` (`<= 5 MB`), rejecting invalid MIME/extensions and SVG script payloads.
- **Rate Limiting:** IP-based rate limiters protecting public API routes (`/api/*`), customer & admin login/registration, and password-reset endpoints (`HTTP 429`).
- **Transactional Integrity:** MySQL InnoDB transactions (`FOR UPDATE` row locks) guard order creation, stock reservation, cancellation stock release, return restocking, and refund accounting.

