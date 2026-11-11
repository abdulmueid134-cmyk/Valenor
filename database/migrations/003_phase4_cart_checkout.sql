-- Phase 4 Migration: Carts, Coupons, Orders, Order Items, Status History, Payments & Inventory Movements
USE valenor_watches;

CREATE TABLE IF NOT EXISTS carts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  session_token VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variant_name VARCHAR(120) NOT NULL DEFAULT '',
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL,
  discount_type ENUM('percent', 'fixed', 'free_shipping') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  max_discount_amount DECIMAL(12, 2) NULL,
  min_purchase_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  usage_limit INT UNSIGNED NULL,
  per_user_limit INT UNSIGNED NOT NULL DEFAULT 1,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_coupons_active (code, is_active, expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  idempotency_key VARCHAR(80) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  customer_name VARCHAR(140) NOT NULL,
  customer_email VARCHAR(180) NOT NULL,
  customer_phone VARCHAR(32) NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_area VARCHAR(120) NOT NULL,
  shipping_street VARCHAR(255) NOT NULL,
  shipping_postal_code VARCHAR(24) NULL,
  delivery_instructions TEXT NULL,
  delivery_method ENUM('standard', 'express') NOT NULL DEFAULT 'standard',
  payment_method ENUM('cod', 'bank_transfer', 'online_gateway') NOT NULL DEFAULT 'cod',
  payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
  order_status ENUM(
    'Pending', 'Confirmed', 'Processing', 'Packed', 'Shipped',
    'Out for Delivery', 'Delivered', 'Cancelled', 'Rejected',
    'Return Requested', 'Returned', 'Refunded'
  ) NOT NULL DEFAULT 'Pending',
  subtotal DECIMAL(12, 2) NOT NULL,
  product_discount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  coupon_code VARCHAR(50) NULL,
  coupon_discount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  delivery_charge DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12, 2) NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_user (user_id, created_at),
  INDEX idx_orders_status (order_status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(64) NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  variant_name VARCHAR(120) NOT NULL DEFAULT '',
  unit_original_price DECIMAL(12, 2) NOT NULL,
  unit_price DECIMAL(12, 2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(12, 2) NOT NULL,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  previous_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  changed_by_user_id INT UNSIGNED NULL,
  reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  method ENUM('cod', 'bank_transfer', 'online_gateway') NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'PKR',
  status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
  reference_code VARCHAR(120) NULL,
  gateway_status VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coupon_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  customer_email VARCHAR(180) NULL,
  discount_applied DECIMAL(12, 2) NOT NULL,
  used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_cu_coupon_user (coupon_id, user_id, customer_email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NULL,
  change_qty INT NOT NULL,
  movement_type ENUM('sale_reserve', 'cancel_release', 'return_restock', 'admin_adjust') NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_im_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_im_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  INDEX idx_im_product_time (product_id, created_at)
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('tax_rate_percent', '0'),
  ('standard_shipping_fee', '250'),
  ('express_shipping_fee', '550'),
  ('payment_cod_enabled', '1'),
  ('payment_bank_enabled', '1'),
  ('payment_online_enabled', '0'),
  ('bank_transfer_details', 'Meezan Bank · VALENOR Horology · IBAN: PK12MEZN0002140105551111')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO coupons (
  code, description, discount_type, discount_value, max_discount_amount,
  min_purchase_amount, usage_limit, per_user_limit, starts_at, expires_at, is_active
) VALUES
  ('WELCOME10', '10% off your welcome order (min PKR 5,000)', 'percent', 10.00, 10000.00, 5000.00, 1000, 2, '2026-01-01 00:00:00', '2028-12-31 23:59:59', 1),
  ('SALE20', '20% off orders PKR 15,000+', 'percent', 20.00, 25000.00, 15000.00, 500, 5, '2026-01-01 00:00:00', '2028-12-31 23:59:59', 1),
  ('FREESHIP', 'Free Standard Delivery on orders PKR 3,000+', 'free_shipping', 0.00, NULL, 3000.00, 1000, 5, '2026-01-01 00:00:00', '2028-12-31 23:59:59', 1),
  ('EXPIRED50', 'Expired promotional test code', 'percent', 50.00, 10000.00, 1000.00, 100, 1, '2024-01-01 00:00:00', '2025-01-01 00:00:00', 1)
ON DUPLICATE KEY UPDATE description = VALUES(description), expires_at = VALUES(expires_at), is_active = VALUES(is_active);

