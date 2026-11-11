# VALENOR Watch E-Commerce — Architecture & Phase Plan

## Phase 1 Foundation (Current)
- **Project Root**: `C:\Users\Admin\.gemini\antigravity\scratch\watch-ecommerce`
- **Brand Identity**: VALENOR Horology — Obsidian black header (`#0B0B0D`), gallery white (`#FFFFFF`) & alabaster (`#F7F7F5`) sections, champagne gold accent (`#B8934A`), crimson sale badges (`#C42B2B`).
- **Default Currency**: `PKR` formatted via a single currency helper (`formatCurrency`) ready to switch dynamically from Admin Website Settings (`settings` table) in Phase 9.

## Planned Full-Stack Contract (Phases 2–10)
The Phase 1 frontend data models in `frontend/assets/js/home-data.js` mirror the future MySQL schema and REST API payloads so Phase 2+ integration requires swapping the data loader rather than rewriting UI templates:

- **Storefront API Endpoints (Planned)**:
  - `GET /api/campaigns/active` → Announcement bar, countdown end timestamp, and hero campaign (`promotional_campaigns`, `banners`)
  - `GET /api/categories` → Shop by Category & navbar collections (`categories`)
  - `GET /api/products` → Sections & catalog filters (`products`, `product_images`, `brands`, `inventory`)
  - `GET /api/reviews/featured` → Verified customer testimonials (`reviews`)
  - `POST /api/newsletter` → Newsletter subscription (`newsletter_subscribers`)
- **Admin Dashboard**: Separate protected `/admin/` portal with RBAC (`roles`, `permissions`, `role_permissions`), live MySQL KPI aggregations, Chart.js analytics, inventory movements, order lifecycle, and CSV/PDF reporting.

## Local Environment Status (Inspected Phase 1)
- Browsers verified available: Microsoft Edge, Google Chrome.
- Runtime prerequisite for Phase 2+ backend: Node.js (`v18+`) and MySQL (`8.0+`) are not currently installed on PATH and will be needed before starting Phase 2 backend API integration.

