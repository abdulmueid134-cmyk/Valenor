-- Phase 3 Migration: Roles, Users, Saved Addresses & Password Resets
USE valenor_watches;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(140) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  phone VARCHAR(32) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  newsletter_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  preferred_currency VARCHAR(10) NOT NULL DEFAULT 'PKR',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE,
  INDEX idx_users_role (role_id, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(140) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  city VARCHAR(100) NOT NULL,
  area VARCHAR(120) NOT NULL,
  street_address VARCHAR(255) NOT NULL,
  postal_code VARCHAR(24) NULL,
  delivery_instructions TEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_addresses_user_default (user_id, is_default)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_resets_token (token_hash, expires_at)
) ENGINE=InnoDB;

INSERT INTO roles (name, description) VALUES
  ('customer', 'Standard B2C storefront shopper'),
  ('super_admin', 'Full store and permission administrator'),
  ('manager', 'Store operations manager'),
  ('order_manager', 'Order fulfillment and returns manager'),
  ('inventory_manager', 'Catalog and stock manager')
ON DUPLICATE KEY UPDATE description = VALUES(description);

