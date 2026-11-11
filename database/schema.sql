-- VALENOR Horology — MySQL Relational Schema (Phase 2 Catalog Foundation)
-- Charset: utf8mb4_unicode_ci | Engine: InnoDB

CREATE DATABASE IF NOT EXISTS valenor_watches
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE valenor_watches;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  image_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_categories_active_sort (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS brands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(140) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  brand_id INT UNSIGNED NOT NULL,
  primary_category_id INT UNSIGNED NOT NULL,
  gender ENUM('Men', 'Women', 'Kids', 'Couple', 'Unisex') NOT NULL DEFAULT 'Unisex',
  watch_type ENUM('Analog', 'Digital', 'Automatic', 'Chronograph', 'Quartz', 'Smartwatch') NOT NULL DEFAULT 'Analog',
  strap_material VARCHAR(100) NOT NULL,
  case_material VARCHAR(100) NOT NULL,
  movement VARCHAR(120) NOT NULL,
  water_resistance VARCHAR(80) NOT NULL,
  warranty VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  original_price DECIMAL(12, 2) NOT NULL,
  sale_price DECIMAL(12, 2) NOT NULL,
  cost_price DECIMAL(12, 2) NULL,
  discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  color VARCHAR(60) NOT NULL,
  rating DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  sales_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON UPDATE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (primary_category_id) REFERENCES categories(id) ON UPDATE CASCADE,
  INDEX idx_products_catalog (is_active, sale_price, rating, discount_percent),
  INDEX idx_products_filters (gender, watch_type, color, strap_material),
  INDEX idx_products_flags (is_featured, is_best_seller, is_new_arrival)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_categories (
  product_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_pi_product (product_id, is_primary, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  color_name VARCHAR(60) NOT NULL,
  color_hex VARCHAR(16) NOT NULL,
  variant_sku VARCHAR(80) NOT NULL UNIQUE,
  price_delta DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  stock_quantity INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory (
  product_id INT UNSIGNED PRIMARY KEY,
  available_stock INT NOT NULL DEFAULT 0,
  reserved_stock INT NOT NULL DEFAULT 0,
  sold_quantity INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  author_name VARCHAR(120) NOT NULL,
  city VARCHAR(80) NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 1,
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_reviews_product (product_id, is_approved)
) ENGINE=InnoDB;


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
