-- =============================================================================
-- VALENOR B2C E-COMMERCE PLATFORM — RELATIONAL DATABASE SCHEMA (22 TABLES)
-- Compatible with MySQL 8.0+ and MariaDB 10.5+ / 11.4+
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `valenor_ecommerce` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `valenor_ecommerce`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. ADMINS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'manager', 'editor') NOT NULL DEFAULT 'manager',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_admin_email_status` (`email`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. CUSTOMERS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(60) NOT NULL,
    `last_name` VARCHAR(60) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `reset_token` VARCHAR(64) DEFAULT NULL,
    `reset_token_expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cust_email` (`email`),
    INDEX `idx_cust_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. CUSTOMER ADDRESSES TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL,
    `address_title` VARCHAR(50) NOT NULL DEFAULT 'Home', -- e.g. Home, Office
    `recipient_name` VARCHAR(120) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `street_address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(80) NOT NULL,
    `postal_code` VARCHAR(20) NOT NULL,
    `delivery_instructions` TEXT DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_addr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    INDEX `idx_addr_cust_default` (`customer_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. CATEGORIES TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cat_slug_status` (`slug`, `status`),
    INDEX `idx_cat_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. PRODUCTS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `sku` VARCHAR(60) NOT NULL UNIQUE,
    `category_id` INT UNSIGNED NOT NULL,
    `brand` VARCHAR(100) NOT NULL DEFAULT 'VALENOR',
    `short_description` VARCHAR(500) NOT NULL,
    `full_description` LONGTEXT NOT NULL,
    `regular_price` DECIMAL(12,2) NOT NULL,
    `discount_price` DECIMAL(12,2) DEFAULT NULL,
    `discount_percentage` INT UNSIGNED GENERATED ALWAYS AS (
        CASE
            WHEN `discount_price` IS NOT NULL AND `discount_price` > 0 AND `regular_price` > `discount_price`
            THEN ROUND(((`regular_price` - `discount_price`) / `regular_price`) * 100)
            ELSE 0
        END
    ) STORED,
    `specifications` LONGTEXT DEFAULT NULL, -- JSON array of key-value pairs
    `colors` VARCHAR(255) DEFAULT NULL,     -- Comma-separated or tag list
    `sizes` VARCHAR(255) DEFAULT NULL,      -- Comma-separated dial/case sizes
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'draft', 'inactive') NOT NULL DEFAULT 'active',
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_new_arrival` TINYINT(1) NOT NULL DEFAULT 0,
    `is_best_seller` TINYINT(1) NOT NULL DEFAULT 0,
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
    INDEX `idx_prod_cat_status` (`category_id`, `status`),
    INDEX `idx_prod_flags` (`status`, `is_featured`, `is_new_arrival`, `is_best_seller`),
    INDEX `idx_prod_prices` (`regular_price`, `discount_price`),
    FULLTEXT KEY `ft_product_search` (`name`, `brand`, `short_description`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. PRODUCT IMAGES TABLE (1–10 images per product, Section 4)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_img_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    INDEX `idx_img_prod_sort` (`product_id`, `sort_order`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. PRODUCT TAGS TABLE (Tags & Hashtags, Section 5 & 8)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_tags`;
CREATE TABLE `product_tags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `tag_name` VARCHAR(80) NOT NULL,
    `tag_type` ENUM('tag', 'hashtag') NOT NULL DEFAULT 'tag',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tag_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    INDEX `idx_tag_lookup` (`tag_name`, `tag_type`),
    INDEX `idx_tag_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. PRODUCT VARIANTS TABLE (Colors & Dial Sizes)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `color` VARCHAR(50) DEFAULT NULL,
    `size` VARCHAR(50) DEFAULT NULL,
    `sku` VARCHAR(80) DEFAULT NULL,
    `additional_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_var_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    INDEX `idx_var_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. PRODUCT REVIEWS TABLE (Section 12)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL, -- 1 to 5
    `title` VARCHAR(150) NOT NULL,
    `review_text` TEXT NOT NULL,
    `review_image` VARCHAR(255) DEFAULT NULL,
    `review_images` TEXT DEFAULT NULL,
    `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rev_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rev_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    INDEX `idx_rev_prod_status` (`product_id`, `status`),
    INDEX `idx_rev_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. WISHLISTS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `wishlists`;
CREATE TABLE `wishlists` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL UNIQUE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wish_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. WISHLIST ITEMS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `wishlist_items`;
CREATE TABLE `wishlist_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `wishlist_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_witem_wishlist` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_witem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_wish_product` (`wishlist_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. CARTS TABLE (Section 15 & 20)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED DEFAULT NULL UNIQUE,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    INDEX `idx_cart_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. CART ITEMS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cart_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_citem_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_citem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_citem_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
    INDEX `idx_citem_cart` (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. ORDERS TABLE (14 Order Statuses, Section 10)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(40) NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_email` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `shipping_street` VARCHAR(255) NOT NULL,
    `shipping_city` VARCHAR(80) NOT NULL,
    `shipping_postal_code` VARCHAR(20) NOT NULL,
    `delivery_instructions` TEXT DEFAULT NULL,
    `payment_method` VARCHAR(40) NOT NULL DEFAULT 'COD',
    `payment_status` ENUM('Unpaid', 'Pending', 'Paid', 'Refunded', 'Failed') NOT NULL DEFAULT 'Unpaid',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `coupon_code` VARCHAR(50) DEFAULT NULL,
    `order_status` ENUM(
        'Pending',
        'Confirmed',
        'Processing',
        'Packed',
        'Dispatched',
        'Shipped / In Transit',
        'Out for Delivery',
        'Delivered',
        'Cancelled',
        'Return Requested',
        'Return Approved',
        'Return Rejected',
        'Return in Transit',
        'Returned',
        'Refund Processing',
        'Refunded'
    ) NOT NULL DEFAULT 'Pending',
    `notes` TEXT DEFAULT NULL,
    `tracking_number` VARCHAR(100) DEFAULT NULL,
    `courier_name` VARCHAR(80) DEFAULT 'BlueEx Express',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_order_status` (`order_status`),
    INDEX `idx_order_customer` (`customer_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 15. ORDER ITEMS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `variant_id` INT UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `product_sku` VARCHAR(60) NOT NULL,
    `variant_details` VARCHAR(150) DEFAULT NULL, -- e.g. "Color: Gold, Dial: 42mm"
    `price_at_purchase` DECIMAL(12,2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `total_price` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_oitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    INDEX `idx_oitem_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 16. ORDER STATUS HISTORY TABLE (Timestamps & Audit Trail, Section 10)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_status_history`;
CREATE TABLE `order_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(60) NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `updated_by` ENUM('customer', 'admin', 'system') NOT NULL DEFAULT 'system',
    `admin_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_osh_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
    INDEX `idx_osh_order_time` (`order_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 17. RETURN REQUESTS TABLE (Section 11)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `return_requests`;
CREATE TABLE `return_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_number` VARCHAR(40) NOT NULL UNIQUE,
    `order_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `proof_image` VARCHAR(255) DEFAULT NULL,
    `proof_images` TEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Return Requested',
    `admin_notes` TEXT DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `refund_status` ENUM('Pending', 'Processing', 'Refunded', 'Rejected') NOT NULL DEFAULT 'Pending',
    `refund_method` VARCHAR(50) DEFAULT 'Bank Transfer / Original Payment',
    `refund_notes` TEXT DEFAULT NULL,
    `refunded_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ret_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ret_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    INDEX `idx_ret_status` (`status`),
    INDEX `idx_ret_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 18. RETURN ITEMS TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_request_id` INT UNSIGNED NOT NULL,
    `order_item_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ritem_request` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ritem_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
    INDEX `idx_ritem_req` (`return_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 19. COUPONS TABLE (Section 13)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NOT NULL,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_order_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `max_discount_value` DECIMAL(12,2) DEFAULT NULL,
    `usage_limit` INT UNSIGNED DEFAULT NULL,
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `start_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_coupon_code_status` (`code`, `status`, `expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 20. COUPON USAGE TABLE
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `coupon_usage`;
CREATE TABLE `coupon_usage` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `coupon_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `discount_amount` DECIMAL(12,2) NOT NULL,
    `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cusage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cusage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    INDEX `idx_cusage_coupon_cust` (`coupon_id`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 21. BANNERS TABLE (Section 13)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `subtitle` VARCHAR(255) DEFAULT NULL,
    `badge_text` VARCHAR(80) DEFAULT NULL,
    `button_text` VARCHAR(50) NOT NULL DEFAULT 'Explore Collection',
    `button_link` VARCHAR(255) NOT NULL DEFAULT 'products.php',
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_banner_sort_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 22. WEBSITE SETTINGS TABLE (Section 13)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `website_settings`;
CREATE TABLE `website_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(80) NOT NULL UNIQUE,
    `setting_value` LONGTEXT DEFAULT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settings_key` (`setting_key`),
    INDEX `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
