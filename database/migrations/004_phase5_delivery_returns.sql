-- Phase 5 Migration: Shipments, Returns, Return Items, Refunds & Returned Inventory Tracking
USE valenor_watches;

ALTER TABLE inventory ADD COLUMN IF NOT EXISTS returned_quantity INT NOT NULL DEFAULT 0 AFTER sold_quantity;

CREATE TABLE IF NOT EXISTS shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  courier_name VARCHAR(120) NOT NULL DEFAULT 'VALENOR Insured Courier',
  tracking_number VARCHAR(100) NULL,
  shipped_at DATETIME NULL,
  expected_delivery_date DATE NULL,
  delivered_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_shipments_tracking (tracking_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS returns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_number VARCHAR(40) NOT NULL UNIQUE,
  order_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  reason VARCHAR(160) NOT NULL,
  details TEXT NULL,
  supporting_photos TEXT NULL,
  status ENUM('Pending', 'Approved', 'Rejected', 'Returned', 'Refunded') NOT NULL DEFAULT 'Pending',
  admin_note TEXT NULL,
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_returns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_returns_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_returns_order (order_id, status),
  INDEX idx_returns_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS return_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  condition_note VARCHAR(255) NULL,
  CONSTRAINT fk_ri_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refunds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  refund_number VARCHAR(40) NOT NULL UNIQUE,
  return_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  method ENUM('bank_transfer', 'store_credit', 'original_payment') NOT NULL DEFAULT 'bank_transfer',
  status ENUM('Pending', 'Processed', 'Rejected') NOT NULL DEFAULT 'Pending',
  reference_code VARCHAR(120) NULL,
  processed_by_user_id INT UNSIGNED NULL,
  processed_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_refunds_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE SET NULL,
  CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_refunds_admin FOREIGN KEY (processed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_refunds_order (order_id, status)
) ENGINE=InnoDB;

