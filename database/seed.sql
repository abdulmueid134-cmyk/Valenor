-- VALENOR Horology — Phase 2 Catalog Seed Data (14 Categories, Brands, Watches, Stock, Reviews)
USE valenor_watches;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('site_name', 'VALENOR Horology'),
  ('currency', 'PKR'),
  ('free_shipping_threshold', '15000')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO categories (name, slug, description, image_url, sort_order) VALUES
  ('Men''s Watches', 'mens-watches', 'Chronographs, Phillipes, Stiveson, and diver watches for men.', 'assets/images/watch-mens-chronograph.jpg', 1),
  ('Women''s Watches', 'womens-watches', 'Champagne gold Milanese, mother-of-pearl, and dress watches.', 'assets/images/watch-womens-gold.jpg', 2),
  ('Kids'' Watches', 'kids-watches', 'Swim-safe, easy-read analog and digital watches for kids.', 'assets/images/watch-kids-play.jpg', 3),
  ('Couple Watches', 'couple-watches', 'Matched his-and-hers collector gift sets.', 'assets/images/watch-couple-set.jpg', 4),
  ('Smart Watches', 'smart-watches', 'AMOLED health, GPS, and Bluetooth calling timepieces.', 'assets/images/watch-smart-obsidian.jpg', 5),
  ('Luxury Watches', 'luxury-watches', 'Signature open-worked and precious-finished calibers.', 'assets/images/watch-skeleton-tourbillon.jpg', 6),
  ('Casual Watches', 'casual-watches', 'Versatile everyday watches built for relaxed style.', 'assets/images/watch-mens-black.jpg', 7),
  ('Formal Watches', 'formal-watches', 'Ultra-slim dress watches for evening and business attire.', 'assets/images/watch-leather-dress.jpg', 8),
  ('Sports Watches', 'sports-watches', 'High-ATM water-resistant divers and active chronographs.', 'assets/images/watch-diver-steel.jpg', 9),
  ('Digital Watches', 'digital-watches', 'Crisp AMOLED and hybrid digital timekeeping.', 'assets/images/watch-smart-obsidian.jpg', 10),
  ('Analog Watches', 'analog-watches', 'Traditional multi-hand dials with precision indices.', 'assets/images/watch-mens-chronograph.jpg', 11),
  ('Automatic Watches', 'automatic-watches', 'Self-winding mechanical movements with exhibition backs.', 'assets/images/watch-skeleton-tourbillon.jpg', 12),
  ('Leather Strap Watches', 'leather-strap-watches', 'Hand-stitched calfskin and alligator-grain straps.', 'assets/images/watch-leather-dress.jpg', 13),
  ('Stainless Steel Watches', 'stainless-steel-watches', 'Brushed 316L surgical-grade steel bracelets.', 'assets/images/watch-diver-steel.jpg', 14)
ON DUPLICATE KEY UPDATE name = VALUES(name), image_url = VALUES(image_url), sort_order = VALUES(sort_order);

INSERT INTO brands (name, slug) VALUES
  ('VALENOR Genève', 'valenor-geneve'),
  ('Phillipes Genève', 'phillipes-geneve'),
  ('Stiveson Atelier', 'stiveson-atelier'),
  ('VALENOR Marine', 'valenor-marine'),
  ('VALENOR Connect', 'valenor-connect'),
  ('VALENOR Junior', 'valenor-junior')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (
  sku, name, slug, brand_id, primary_category_id, gender, watch_type,
  strap_material, case_material, movement, water_resistance, warranty, description,
  original_price, sale_price, cost_price, discount_percent, color, rating, review_count, sales_count,
  is_featured, is_best_seller, is_new_arrival
)
SELECT 'VAL-CHR-101', 'Chronomaster Reverse Panda 41mm', 'chronomaster-reverse-panda-41mm',
  (SELECT id FROM brands WHERE slug = 'valenor-geneve'),
  (SELECT id FROM categories WHERE slug = 'mens-watches'),
  'Men', 'Chronograph', 'Stainless Steel', 'Brushed Steel · 41mm', 'Swiss Quartz Chronograph', '10 ATM (100m)', '2-Year International Warranty',
  'Three-register reverse panda chronograph engineered with anti-reflective sapphire crystal and solid brushed steel bracelet.',
  68000.00, 44200.00, 26000.00, 35, 'Black', 4.90, 142, 310, 1, 1, 0
ON DUPLICATE KEY UPDATE sale_price = VALUES(sale_price);

INSERT INTO products (
  sku, name, slug, brand_id, primary_category_id, gender, watch_type,
  strap_material, case_material, movement, water_resistance, warranty, description,
  original_price, sale_price, cost_price, discount_percent, color, rating, review_count, sales_count,
  is_featured, is_best_seller, is_new_arrival
)
SELECT 'VAL-DIV-104', 'Veridian Submersible Automatic', 'veridian-submersible-automatic',
  (SELECT id FROM brands WHERE slug = 'valenor-marine'),
  (SELECT id FROM categories WHERE slug = 'automatic-watches'),
  'Men', 'Automatic', 'Stainless Steel', 'Ceramic Bezel · 42mm', 'Caliber V-28 Automatic', '20 ATM (200m)', '3-Year Movement Warranty',
  'Unidirectional emerald ceramic diver bezel paired with sunburst dial and luminous indices built for daily resilience.',
  89000.00, 62300.00, 38000.00, 30, 'Emerald', 4.90, 98, 220, 1, 1, 1
ON DUPLICATE KEY UPDATE sale_price = VALUES(sale_price);

INSERT INTO products (
  sku, name, slug, brand_id, primary_category_id, gender, watch_type,
  strap_material, case_material, movement, water_resistance, warranty, description,
  original_price, sale_price, cost_price, discount_percent, color, rating, review_count, sales_count,
  is_featured, is_best_seller, is_new_arrival
)
SELECT 'VAL-WOM-201', 'Phillipes Lumiere Milanese 32mm', 'phillipes-lumiere-milanese-32mm',
  (SELECT id FROM brands WHERE slug = 'phillipes-geneve'),
  (SELECT id FROM categories WHERE slug = 'womens-watches'),
  'Women', 'Analog', 'Gold Mesh', 'PVD Gold Steel · 32mm', 'Japanese High-Precision Quartz', '5 ATM (50m)', '2-Year International Warranty',
  'Ultra-slim 6.8mm champagne-gold dress watch featuring natural mother-of-pearl dial and tool-free Milanese mesh strap.',
  54000.00, 27000.00, 15000.00, 50, 'Gold', 5.00, 164, 405, 1, 1, 0
ON DUPLICATE KEY UPDATE sale_price = VALUES(sale_price);

INSERT INTO inventory (product_id, available_stock, sold_quantity)
SELECT id, 28, sales_count FROM products
ON DUPLICATE KEY UPDATE available_stock = VALUES(available_stock);

INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
SELECT id, 'assets/images/watch-mens-chronograph.jpg', 1, 1 FROM products WHERE sku = 'VAL-CHR-101'
UNION ALL
SELECT id, 'assets/images/watch-mens-black.jpg', 0, 2 FROM products WHERE sku = 'VAL-CHR-101'
UNION ALL
SELECT id, 'assets/images/watch-diver-steel.jpg', 1, 1 FROM products WHERE sku = 'VAL-DIV-104'
UNION ALL
SELECT id, 'assets/images/watch-womens-gold.jpg', 1, 1 FROM products WHERE sku = 'VAL-WOM-201';

INSERT INTO roles (name, description) VALUES
  ('customer', 'Standard B2C storefront shopper'),
  ('super_admin', 'Full store and permission administrator'),
  ('manager', 'Store operations manager'),
  ('order_manager', 'Order fulfillment and returns manager'),
  ('inventory_manager', 'Catalog and stock manager')
ON DUPLICATE KEY UPDATE description = VALUES(description);
