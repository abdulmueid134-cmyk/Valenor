-- Phase 7 Migration: Watch Types Table, Flexible watch_type Column & Inventory Movement User Tracking
USE valenor_watches;

ALTER TABLE products MODIFY COLUMN watch_type VARCHAR(80) NOT NULL DEFAULT 'Analog';

CREATE TABLE IF NOT EXISTS watch_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_watch_types_active (is_active, sort_order)
) ENGINE=InnoDB;

INSERT INTO watch_types (name, slug, description, sort_order) VALUES
  ('Analog', 'analog', 'Classic three-hand and dress dial horology', 1),
  ('Digital', 'digital', 'LED/LCD multi-function and kid-friendly digital timepieces', 2),
  ('Automatic', 'automatic', 'Self-winding mechanical calibres and exhibition casebacks', 3),
  ('Chronograph', 'chronograph', 'Precision stopwatch subdials and tachymeter bezels', 4),
  ('Quartz', 'quartz', 'High-accuracy Swiss & Japanese quartz calibres', 5),
  ('Smartwatch', 'smartwatch', 'AMOLED health telemetry and connected hybrid watches', 6)
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO watch_types (name, slug, description, sort_order)
SELECT DISTINCT watch_type, LOWER(REPLACE(TRIM(watch_type), ' ', '-')), 'Catalog watch type', 20
FROM products WHERE watch_type IS NOT NULL AND TRIM(watch_type) <> '';

ALTER TABLE inventory_movements ADD COLUMN IF NOT EXISTS updated_by_user_id INT UNSIGNED NULL AFTER note;

