-- Phase 9 Migration: Promotions, Homepage Banners, Newsletter, Extended Settings & Category Featured Flag
USE valenor_watches;

ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;

CREATE TABLE IF NOT EXISTS banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  subtitle VARCHAR(255) NULL,
  cta_text VARCHAR(80) NOT NULL DEFAULT 'Explore Collection',
  cta_link VARCHAR(255) NOT NULL DEFAULT 'pages/catalog.html',
  image_url VARCHAR(255) NOT NULL,
  placement ENUM('hero', 'mid_promo', 'top_announcement') NOT NULL DEFAULT 'hero',
  sort_order INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_banners_active (placement, is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS promotional_campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  badge_text VARCHAR(80) NOT NULL DEFAULT 'LIMITED SALE',
  headline VARCHAR(255) NOT NULL,
  discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  coupon_code VARCHAR(50) NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_campaigns_active (is_active, starts_at, ends_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(180) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO banners (id, title, subtitle, cta_text, cta_link, image_url, placement, sort_order, is_active) VALUES
  (1, 'Geneva Precision, Crafted for Every Moment', 'Swiss & Japanese movements with sapphire crystal and nationwide insured Cash on Delivery.', 'Shop 50% Sale', 'pages/catalog.html?minDiscount=25', 'assets/images/hero-banner.jpg', 'hero', 1, 1),
  (2, 'Couple Sets & Anniversary Box Editions', 'Curated dual-timepiece presentation sets with engraved wooden collector boxes.', 'Explore Couple Sets', 'pages/catalog.html?category=couple-watches', 'assets/images/watch-couple-set.jpg', 'mid_promo', 2, 1),
  (3, 'Connected Titanium Horology', 'AMOLED health telemetry inside classic 42mm horology cases.', 'Discover Smart Watches', 'pages/catalog.html?category=smart-watches', 'assets/images/promo-sale-banner.jpg', 'mid_promo', 3, 1)
ON DUPLICATE KEY UPDATE title = VALUES(title), image_url = VALUES(image_url);

INSERT INTO promotional_campaigns (id, name, badge_text, headline, discount_percent, coupon_code, starts_at, ends_at, is_active) VALUES
  (1, '11.11 Horology Sale', '11.11 HOROLOGY SALE', 'Up to 50% OFF Selected Watches | Extra 20% with SALE20', 50, 'SALE20', '2026-01-01 00:00:00', '2028-12-31 23:59:59', 1)
ON DUPLICATE KEY UPDATE headline = VALUES(headline);

INSERT INTO settings (setting_key, setting_value) VALUES
  ('website_name', 'VALENOR Horology'),
  ('brand_logo_url', 'assets/icons/favicon.svg'),
  ('contact_info', 'Lahore | Karachi | Islamabad - Mon-Sat: 10:00 AM - 8:00 PM PKT'),
  ('contact_email', 'concierge@valenor.pk'),
  ('contact_phone', '+92 300 048 1111'),
  ('social_instagram', 'https://instagram.com/valenorhorology'),
  ('social_facebook', 'https://facebook.com/valenorhorology'),
  ('social_youtube', 'https://youtube.com/@valenorhorology'),
  ('return_policy', '7-day unused return guarantee on all unworn watches with original box, warranty card, and protective seals intact. Approved returns are inspected within 48 hours and refunded via Bank Transfer or Store Credit.'),
  ('privacy_policy', 'VALENOR Horology protects collector personal details with encrypted storage, salted password hashing, and never stores raw credit card numbers or CVVs.'),
  ('terms_conditions', 'All timepieces carry an authentic 2-year to 3-year movement warranty. Cash on Delivery orders above PKR 15,000 qualify for complimentary insured nationwide delivery.')
ON DUPLICATE KEY UPDATE setting_value = COALESCE(NULLIF(setting_value, ''), VALUES(setting_value));
