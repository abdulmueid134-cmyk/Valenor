-- Migration 008: Soft-delete support so deleted watches with order history are hidden from both Admin Catalog and Customer Storefront while preserving past order records
USE valenor_watches;

ALTER TABLE products ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
CREATE INDEX IF NOT EXISTS idx_products_not_deleted ON products (is_deleted, is_active);

