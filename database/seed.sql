-- =============================================================================
-- VALENOR B2C E-COMMERCE PLATFORM — SAMPLE SEED DATA
-- 5 Main Categories, 10 Realistic Products (2 per category), 1 Super Admin,
-- 1 Sample Customer, Images, Variants, Tags, Coupons, Banners & Settings
-- =============================================================================

USE `valenor_ecommerce`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. ADMIN USER (Password: Admin@12345)
TRUNCATE TABLE `admins`;
INSERT INTO `admins` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `created_at`) VALUES
(1, 'VALENOR Executive', 'admin@valenor.com', '$2y$10$ba27vFMKiSOoCCDq3VBn.ebul0zZjKMMuC2GeD0fosAEHiYMu/HDu', 'super_admin', 'active', NOW());

-- 2. SAMPLE CUSTOMER (Password: Admin@12345)
TRUNCATE TABLE `customers`;
INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `status`, `created_at`) VALUES
(1, 'Hamza', 'Tariq', 'customer@valenor.com', '+923001234567', '$2y$10$ba27vFMKiSOoCCDq3VBn.ebul0zZjKMMuC2GeD0fosAEHiYMu/HDu', 'active', NOW());

-- 3. CUSTOMER ADDRESS
TRUNCATE TABLE `customer_addresses`;
INSERT INTO `customer_addresses` (`id`, `customer_id`, `address_title`, `recipient_name`, `phone`, `street_address`, `city`, `postal_code`, `delivery_instructions`, `is_default`, `created_at`) VALUES
(1, 1, 'Home', 'Hamza Tariq', '+923001234567', 'House 14-B, Street 9, Sector F-7/2', 'Islamabad', '44000', 'Please call before delivery. Ring bell twice.', 1, NOW());

-- 4. EXACTLY FIVE MAIN CATEGORIES (Section 3.B)
TRUNCATE TABLE `categories`;
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_path`, `sort_order`, `status`, `created_at`) VALUES
(1, 'Chronographs', 'chronographs', 'Precision multi-dial chronographs with tachymeter scales, split-second stopwatch functions, and anti-reflective sapphire crystals.', 'uploads/products/watch-mens-chronograph.jpg', 1, 'active', NOW()),
(2, 'Automatic Luxury', 'automatic-luxury', 'Masterful self-winding mechanical calibers showcasing open-heart balances, skeleton tourbillon architecture, and exhibition glass casebacks.', 'uploads/products/watch-skeleton-tourbillon.jpg', 2, 'active', NOW()),
(3, 'Classic Dress', 'classic-dress', 'Distinguished slimline luxury timepieces crafted with fluted bezels, sunburst champagne dials, and genuine hand-stitched Italian leather.', 'uploads/products/watch-leather-dress.jpg', 3, 'active', NOW()),
(4, 'Divers & Sports', 'divers-sports', 'High-performance marine instruments with 300M water resistance, unidirectional ceramic diving bezels, and luminous Super-LumiNova indices.', 'uploads/products/watch-diver-steel.jpg', 4, 'active', NOW()),
(5, 'Minimalist Elegance', 'minimalist-elegance', 'Modern Scandinavian and Swiss-inspired minimalist timepieces with sunray dials, Milanese stainless mesh bracelets, and sapphire crystal.', 'uploads/products/watch-mens-black.jpg', 5, 'active', NOW());

-- 5. EXACTLY TEN SAMPLE PRODUCTS (2 PER CATEGORY, Section 3.B)
TRUNCATE TABLE `products`;
INSERT INTO `products` (`id`, `name`, `slug`, `sku`, `category_id`, `brand`, `short_description`, `full_description`, `regular_price`, `discount_price`, `specifications`, `colors`, `sizes`, `stock_quantity`, `status`, `is_featured`, `is_new_arrival`, `is_best_seller`, `views_count`, `created_at`) VALUES
-- Category 1: Chronographs (Products 1 & 2)
(1, 'VALENOR Carrera Tachymeter Chronograph', 'valenor-carrera-tachymeter-chronograph', 'VAL-CHR-001', 1, 'VALENOR',
 'High-precision Swiss quartz chronograph with triple sub-dials, tachymeter ceramic bezel, and brushed surgical steel bracelet.',
 'The Carrera Tachymeter Chronograph represents the pinnacle of sporting elegance. Engineered from solid 316L medical-grade stainless steel with a high-contrast panda dial, this timepiece delivers split-second accuracy via its dedicated stopwatch sub-dials. Fitted with a double-domed anti-reflective sapphire crystal, screw-down crown, and water resistance up to 100 meters.',
 48500.00, 38800.00,
 '[{"key":"Movement","value":"Swiss Quartz Chronograph Caliber"},{"key":"Case Diameter","value":"42 mm"},{"key":"Case Material","value":"316L Surgical Stainless Steel"},{"key":"Crystal","value":"Double-Domed Sapphire Crystal"},{"key":"Water Resistance","value":"100M / 10 ATM"},{"key":"Strap","value":"Solid Stainless Steel Oyster Bracelet"},{"key":"Warranty","value":"2 Years International Warranty"}]',
 'Silver,Black,Midnight Blue', '42mm', 24, 'active', 1, 1, 1, 412, NOW()),

(2, 'VALENOR Monaco Split-Second Chronometer', 'valenor-monaco-split-second-chronometer', 'VAL-CHR-002', 1, 'VALENOR',
 'Square-cased vintage racing chronograph with dual sub-registers, sunburst sunray dial, and racing rally calfskin strap.',
 'Inspired by golden-era motorsport circuits, the Monaco Split-Second Chronometer pairs a distinctive architectural case with modern horological precision. Featuring dual registers measuring 60 seconds and 30 minutes, luminescent faceted baton indices, and a quick-release perforated rally leather strap handcrafted in Tuscany.',
 56000.00, 47600.00,
 '[{"key":"Movement","value":"Precision Multi-Function Chronograph"},{"key":"Case Dimensions","value":"39 mm x 39 mm"},{"key":"Case Material","value":"Hand-Polished Stainless Steel"},{"key":"Crystal","value":"Anti-Reflective Sapphire"},{"key":"Water Resistance","value":"50M / 5 ATM"},{"key":"Strap","value":"Genuine Perforated Calfskin Leather"},{"key":"Warranty","value":"2 Years International Warranty"}]',
 'Champagne,Matte Black', '39mm', 18, 'active', 1, 0, 1, 289, NOW()),

-- Category 2: Automatic Luxury (Products 3 & 4)
(3, 'VALENOR Royal Skeleton Caliber 88 Automatic', 'valenor-royal-skeleton-caliber-88', 'VAL-AUT-001', 2, 'VALENOR',
 'Mesmerizing skeletonized self-winding timepiece showcasing an open-work movement, 42-hour power reserve, and exhibition back.',
 'Crafted for connoisseurs of pure mechanical art, the Royal Skeleton Caliber 88 leaves nothing hidden. Its hand-finished skeletonized bridges and gears oscillate at 28,800 vibrations per hour, powered naturally by the motion of your wrist. Both dial and caseback are protected by scratch-resistant synthetic sapphire, framed in warm caramel-tinted rose gold PVD.',
 125000.00, 99000.00,
 '[{"key":"Movement","value":"In-House Caliber 88 Skeleton Automatic"},{"key":"Power Reserve","value":"42 Hours"},{"key":"Case Diameter","value":"43 mm"},{"key":"Case Material","value":"Rose Gold PVD over 316L Steel"},{"key":"Caseback","value":"Exhibition Sapphire Display Back"},{"key":"Water Resistance","value":"50M / 5 ATM"},{"key":"Strap","value":"Alligator-Embossed Full-Grain Leather"},{"key":"Warranty","value":"5 Years Extended Mechanical Warranty"}]',
 'Rose Gold,Caramel Gold,Obsidian Black', '43mm', 12, 'active', 1, 1, 1, 856, NOW()),

(4, 'VALENOR Imperial Open-Heart Heritage Automatic', 'valenor-imperial-open-heart-heritage', 'VAL-AUT-002', 2, 'VALENOR',
 'Luxury automatic dress watch featuring an open balance aperture at 9 o''clock, Guilloché textured dial, and blued hands.',
 'A celebration of classical Genevan watchmaking, the Imperial Open-Heart Heritage features an intricate hobnail Guilloché dial punctuated by an open aperture that reveals the rhythmic heartbeat of its mechanical balance wheel. Flame-blued feuille hands sweep smoothly across Roman numerals, matched with a luxurious deployant clasp.',
 89000.00, 75000.00,
 '[{"key":"Movement","value":"Japanese 24-Jewel Self-Winding Movement"},{"key":"Power Reserve","value":"40 Hours"},{"key":"Case Diameter","value":"41 mm"},{"key":"Case Material","value":"Mirror-Polished Stainless Steel"},{"key":"Crystal","value":"Scratch-Proof Sapphire"},{"key":"Water Resistance","value":"50M / 5 ATM"},{"key":"Strap","value":"Hand-Stitched Italian Leather"},{"key":"Warranty","value":"3 Years Mechanical Warranty"}]',
 'Silver,Warm Cream,Midnight Black', '41mm', 15, 'active', 1, 0, 0, 340, NOW()),

-- Category 3: Classic Dress (Products 5 & 6)
(5, 'VALENOR Geneve Ultra-Slim Rose Gold', 'valenor-geneve-ultra-slim-rose-gold', 'VAL-DRS-001', 3, 'VALENOR',
 'Impossibly slim 6.8mm profile formal timepiece in 18K rose gold electroplate with warm cream enamel dial and croc leather strap.',
 'Tailored to slide effortlessly beneath a tailored shirt cuff, the Geneve Ultra-Slim measures a discreet 6.8 millimeters in total thickness. The minimalist warm cream dial boasts subtle applied indices and razor-thin leaf hands, paired with an ultra-soft honey-caramel genuine leather band that patinas beautifully over time.',
 34500.00, 29500.00,
 '[{"key":"Movement","value":"Ultra-Thin Swiss Precision Caliber"},{"key":"Thickness","value":"6.8 mm Ultra-Slim"},{"key":"Case Diameter","value":"40 mm"},{"key":"Case Material","value":"18K Rose Gold Ion-Plated Steel"},{"key":"Crystal","value":"Flat Sapphire with Anti-Glare"},{"key":"Water Resistance","value":"30M / 3 ATM"},{"key":"Strap","value":"Supple Italian Calfskin with Caramel Stitching"}]',
 'Rose Gold,Yellow Gold,Silver', '40mm', 30, 'active', 1, 0, 1, 510, NOW()),

(6, 'VALENOR Vendome Classic Roman Dial', 'valenor-vendome-classic-roman-dial', 'VAL-DRS-002', 3, 'VALENOR',
 'Heritage formal watch with fluted coin-edge bezel, deep lacquer Roman numerals, and quick-set date window at 3 o''clock.',
 'The Vendome Classic honors time-honored horological tradition. Highlighted by its coin-edge fluted bezel and rich sunray dial, this dress timepiece features an intuitive date complication, high-torque movement, and an interchangeable quick-release top-grain leather strap.',
 31000.00, 26000.00,
 '[{"key":"Movement","value":"Precision Date Quartz"},{"key":"Case Diameter","value":"38 mm"},{"key":"Case Material","value":"Hypoallergenic Stainless Steel"},{"key":"Crystal","value":"Sapphire-Coated Mineral Glass"},{"key":"Water Resistance","value":"30M / 3 ATM"},{"key":"Strap","value":"Top-Grain Black Leather Strap"}]',
 'White,Cream,Black', '38mm', 22, 'active', 0, 1, 0, 230, NOW()),

-- Category 4: Divers & Sports (Products 7 & 8)
(7, 'VALENOR Submariner Pro Diver 300M', 'valenor-submariner-pro-diver-300m', 'VAL-DIV-001', 4, 'VALENOR',
 'Professional-grade 300-meter saturation dive watch with 120-click ceramic bezel, helium release valve, and diver extension clasp.',
 'Engineered to withstand the abyssal pressure of the deep sea, the Submariner Pro Diver 300M is a true professional tool watch. Featuring a laser-etched unidirectional ceramic bezel, oversized Swiss Super-LumiNova markers that glow vivid ice-blue in dark waters, and a solid link bracelet with built-in micro-adjust wetsuit extension.',
 64000.00, 52000.00,
 '[{"key":"Movement","value":"Robust NH35 Automatic Mechanical Movement"},{"key":"Water Resistance","value":"300M / 30 ATM Certified"},{"key":"Bezel","value":"120-Click Unidirectional High-Tech Ceramic"},{"key":"Case Diameter","value":"42.5 mm"},{"key":"Crystal","value":"3.5mm Thick Double-Domed Sapphire"},{"key":"Lume","value":"Grade A Swiss Super-LumiNova BGW9"}]',
 'Deep Blue,Ocean Black,Emerald Green', '42.5mm', 20, 'active', 1, 1, 1, 678, NOW()),

(8, 'VALENOR Seafarer Ocean Titanium Edition', 'valenor-seafarer-ocean-titanium-edition', 'VAL-DIV-002', 4, 'VALENOR',
 'Featherlight aerospace-grade titanium dive chronometer with matte wave-engraved dial and reinforced FKM rubber diver strap.',
 'Combining supreme marine resilience with lightweight comfort, the Seafarer Ocean is forged from Grade 2 Aerospace Titanium—offering 40% less weight than steel with complete salt-water corrosion immunity. Equipped with a custom textured FKM rubber strap and 200m depth rating.',
 78000.00, 66000.00,
 '[{"key":"Movement","value":"High-Beat Automatic Caliber"},{"key":"Case Material","value":"Grade 2 Aerospace Titanium"},{"key":"Weight","value":"Only 78 grams"},{"key":"Case Diameter","value":"41 mm"},{"key":"Water Resistance","value":"200M / 20 ATM"},{"key":"Strap","value":"High-Grade Fluorocarbon FKM Rubber"}]',
 'Titanium Grey,Oceanic Blue', '41mm', 14, 'active', 1, 0, 0, 312, NOW()),

-- Category 5: Minimalist Elegance (Products 9 & 10)
(9, 'VALENOR Bauhaus Obsidian Mesh', 'valenor-bauhaus-obsidian-mesh', 'VAL-MIN-001', 5, 'VALENOR',
 'Architectural German-inspired minimalist watch with matte jet-black dial, warm caramel accents, and Milanese magnetic mesh band.',
 'Form follows function in this stunning Bauhaus tribute. Stripped of superfluous decoration, the Obsidian Mesh features a deep matte dial with precision-printed stick indices, whisper-quiet sweep movement, and a flexible Milanese magnetic mesh band that adjusts seamlessly to any wrist contour.',
 28000.00, 22400.00,
 '[{"key":"Movement","value":"Whisper-Quiet Slim Quartz Movement"},{"key":"Case Diameter","value":"40 mm"},{"key":"Case Material","value":"DLC Matte Black Coated Steel"},{"key":"Crystal","value":"Hardened Sapphire Crystal"},{"key":"Water Resistance","value":"30M / 3 ATM"},{"key":"Bracelet","value":"Milanese Fine Stainless Steel Mesh"}]',
 'Obsidian Black,Silver Mesh,Rose Gold Mesh', '40mm', 35, 'active', 1, 1, 1, 720, NOW()),

(10, 'VALENOR Zurich Champagne Ceramic', 'valenor-zurich-champagne-ceramic', 'VAL-MIN-002', 5, 'VALENOR',
 'Contemporary luxury watch featuring a high-gloss champagne dial, diamond-cut hands, and scratch-proof ceramic link bracelet.',
 'Clean, luminous, and timeless. The Zurich Champagne combines polished 316L steel with ultra-hard ceramic central links that never scratch or fade. An understated luxury timepiece that transitions effortlessly from boardroom presentations to evening galas.',
 32000.00, 27200.00,
 '[{"key":"Movement","value":"Precision Swiss-Parts Caliber"},{"key":"Case Diameter","value":"38 mm"},{"key":"Case Material","value":"Mirror Steel & White Ceramic Links"},{"key":"Crystal","value":"Curved Anti-Reflective Sapphire"},{"key":"Water Resistance","value":"50M / 5 ATM"},{"key":"Clasp","value":"Hidden Butterfly Deployant Clasp"}]',
 'Champagne Gold,Pure White,Silver', '38mm', 28, 'active', 0, 1, 0, 445, NOW());

-- 6. PRODUCT IMAGES (1–10 images per product, primary image + gallery, Section 4)
TRUNCATE TABLE `product_images`;
INSERT INTO `product_images` (`product_id`, `image_path`, `sort_order`, `is_primary`, `created_at`) VALUES
-- Product 1 (Carrera Chrono)
(1, 'uploads/products/watch-mens-chronograph.jpg', 0, 1, NOW()),
(1, 'uploads/products/watch-diver-steel.jpg', 1, 0, NOW()),
(1, 'uploads/products/watch-mens-black.jpg', 2, 0, NOW()),

-- Product 2 (Monaco Split-Second)
(2, 'uploads/products/watch-mens-chronograph.jpg', 0, 1, NOW()),
(2, 'uploads/products/watch-leather-dress.jpg', 1, 0, NOW()),

-- Product 3 (Royal Skeleton)
(3, 'uploads/products/watch-skeleton-tourbillon.jpg', 0, 1, NOW()),
(3, 'uploads/products/watch-mens-black.jpg', 1, 0, NOW()),
(3, 'uploads/products/watch-couple-set.jpg', 2, 0, NOW()),

-- Product 4 (Imperial Open-Heart)
(4, 'uploads/products/watch-skeleton-tourbillon.jpg', 0, 1, NOW()),
(4, 'uploads/products/watch-leather-dress.jpg', 1, 0, NOW()),

-- Product 5 (Geneve Ultra-Slim)
(5, 'uploads/products/watch-leather-dress.jpg', 0, 1, NOW()),
(5, 'uploads/products/watch-womens-gold.jpg', 1, 0, NOW()),

-- Product 6 (Vendome Roman)
(6, 'uploads/products/watch-leather-dress.jpg', 0, 1, NOW()),
(6, 'uploads/products/watch-mens-chronograph.jpg', 1, 0, NOW()),

-- Product 7 (Submariner 300M)
(7, 'uploads/products/watch-diver-steel.jpg', 0, 1, NOW()),
(7, 'uploads/products/watch-mens-black.jpg', 1, 0, NOW()),
(7, 'uploads/products/watch-smart-obsidian.jpg', 2, 0, NOW()),

-- Product 8 (Seafarer Titanium)
(8, 'uploads/products/watch-diver-steel.jpg', 0, 1, NOW()),
(8, 'uploads/products/watch-mens-chronograph.jpg', 1, 0, NOW()),

-- Product 9 (Bauhaus Obsidian)
(9, 'uploads/products/watch-mens-black.jpg', 0, 1, NOW()),
(9, 'uploads/products/watch-smart-obsidian.jpg', 1, 0, NOW()),

-- Product 10 (Zurich Champagne)
(10, 'uploads/products/watch-womens-gold.jpg', 0, 1, NOW()),
(10, 'uploads/products/watch-couple-set.jpg', 1, 0, NOW()),
(10, 'uploads/products/watch-smart-womens-gold.jpg', 2, 0, NOW());

-- 7. PRODUCT TAGS & HASHTAGS (Section 5 & 8)
TRUNCATE TABLE `product_tags`;
INSERT INTO `product_tags` (`product_id`, `tag_name`, `tag_type`, `created_at`) VALUES
(1, 'chronograph', 'tag', NOW()), (1, 'tachymeter', 'tag', NOW()), (1, '#luxurychrono', 'hashtag', NOW()), (1, '#valenorheritage', 'hashtag', NOW()),
(2, 'vintage', 'tag', NOW()), (2, 'racing', 'tag', NOW()), (2, '#monacowatch', 'hashtag', NOW()),
(3, 'automatic', 'tag', NOW()), (3, 'skeleton', 'tag', NOW()), (3, 'tourbillon', 'tag', NOW()), (3, '#mechanicalart', 'hashtag', NOW()),
(4, 'openheart', 'tag', NOW()), (4, 'automatic', 'tag', NOW()), (4, '#luxuryhorology', 'hashtag', NOW()),
(5, 'ultraslim', 'tag', NOW()), (5, 'dresswatch', 'tag', NOW()), (5, 'rosegold', 'tag', NOW()), (5, '#formalluxury', 'hashtag', NOW()),
(6, 'roman', 'tag', NOW()), (6, 'classic', 'tag', NOW()), (6, '#timelessdesign', 'hashtag', NOW()),
(7, 'diver', 'tag', NOW()), (7, '300m', 'tag', NOW()), (7, 'ceramicbezel', 'tag', NOW()), (7, '#submariner', 'hashtag', NOW()),
(8, 'titanium', 'tag', NOW()), (8, 'sports', 'tag', NOW()), (8, '#aerospacehorology', 'hashtag', NOW()),
(9, 'minimalist', 'tag', NOW()), (9, 'bauhaus', 'tag', NOW()), (9, 'meshband', 'tag', NOW()), (9, '#modernluxury', 'hashtag', NOW()),
(10, 'ceramic', 'tag', NOW()), (10, 'champagne', 'tag', NOW()), (10, '#elegance', 'hashtag', NOW());

-- 8. PRODUCT VARIANTS (Colors and Sizes)
TRUNCATE TABLE `product_variants`;
INSERT INTO `product_variants` (`product_id`, `color`, `size`, `sku`, `additional_price`, `stock_quantity`, `created_at`) VALUES
(1, 'Silver Sunray', '42mm', 'VAL-CHR-001-SLV-42', 0.00, 10, NOW()),
(1, 'Midnight Blue', '42mm', 'VAL-CHR-001-BLU-42', 1500.00, 8, NOW()),
(1, 'Matte Black', '42mm', 'VAL-CHR-001-BLK-42', 0.00, 6, NOW()),
(3, 'Rose Gold Skeleton', '43mm', 'VAL-AUT-001-RG-43', 0.00, 7, NOW()),
(3, 'Caramel Gold', '43mm', 'VAL-AUT-001-CG-43', 3000.00, 5, NOW()),
(5, 'Rose Gold / Honey Leather', '40mm', 'VAL-DRS-001-RG-40', 0.00, 18, NOW()),
(5, 'Silver / Black Leather', '40mm', 'VAL-DRS-001-SLV-40', 0.00, 12, NOW()),
(7, 'Deep Blue Ceramic', '42.5mm', 'VAL-DIV-001-BLU-42', 0.00, 12, NOW()),
(7, 'Ocean Black', '42.5mm', 'VAL-DIV-001-BLK-42', 0.00, 8, NOW()),
(9, 'Obsidian Black Mesh', '40mm', 'VAL-MIN-001-BLK-40', 0.00, 20, NOW()),
(9, 'Silver Mesh', '40mm', 'VAL-MIN-001-SLV-40', 0.00, 15, NOW());

-- 9. PRODUCT REVIEWS (Verified purchases, Section 12)
TRUNCATE TABLE `product_reviews`;
INSERT INTO `product_reviews` (`product_id`, `customer_id`, `order_id`, `rating`, `title`, `review_text`, `is_verified_purchase`, `status`, `created_at`) VALUES
(1, 1, 1, 5, 'Outstanding craftsmanship and presence!', 'The Carrera Chronograph exceeded all my expectations. The weight, the sapphire dome, and the tactile click of the chronograph pushers feel like a five-figure luxury watch. Delivery was fast and securely packaged.', 1, 'approved', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 1, 1, 5, 'Breathtaking mechanical artwork', 'The skeleton movement Caliber 88 is absolutely mesmerizing in person. You can see every beat and gear interaction. I receive compliments every single time I wear it to formal dinners.', 1, 'approved', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(7, 1, 1, 5, 'Unbeatable dive watch quality', '300 meters depth rating with a genuine ceramic bezel at this price point is unmatched in Pakistan. The blue lume lasts the entire night. Highly recommended!', 1, 'approved', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9, 1, 1, 4, 'Sleek, lightweight, and modern', 'Very clean minimalist aesthetic. The Milanese mesh bracelet is smooth and doesn''t catch on arm hair. Perfect daily timepiece.', 1, 'approved', DATE_SUB(NOW(), INTERVAL 8 DAY));

-- 10. COUPONS (Section 13.H)
TRUNCATE TABLE `coupons`;
INSERT INTO `coupons` (`code`, `description`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_value`, `usage_limit`, `usage_count`, `start_date`, `expiry_date`, `status`, `created_at`) VALUES
('WELCOME10', 'Welcome 10% Discount on Your First Order', 'percentage', 10.00, 5000.00, 10000.00, 500, 1, '2026-01-01', '2027-12-31', 'active', NOW()),
('VALENOR20', 'Special 20% Privilege Discount for Valued Collectors', 'percentage', 20.00, 25000.00, 25000.00, 200, 0, '2026-01-01', '2027-12-31', 'active', NOW()),
('FREESHIP', 'Free Insured Nationwide Delivery on Any Watch', 'fixed', 250.00, 3000.00, 250.00, 1000, 0, '2026-01-01', '2027-12-31', 'active', NOW());

-- 11. BANNERS (Section 13.I - White & Caramel Theme)
TRUNCATE TABLE `banners`;
INSERT INTO `banners` (`title`, `subtitle`, `badge_text`, `button_text`, `button_link`, `image_path`, `sort_order`, `status`, `created_at`) VALUES
('THE ART OF SWISS CALIBER HOROLOGY', 'Masterpieces engineered with sapphire crystal, surgical steel, and automatic movements.', '2026 LUXURY COLLECTION', 'Explore All Watches', 'products.php', 'assets/images/hero-banner.jpg', 1, 'active', NOW()),
('AUTUMN CARAMEL & GOLD TIMEPIECES', 'Elevate your signature style with hand-stitched Italian leather and warm champagne dials.', 'CURATED ELEGANCE', 'Shop Dress Watches', 'products.php?category=classic-dress', 'assets/images/promo-sale-banner.jpg', 2, 'active', NOW());

-- 12. WEBSITE SETTINGS (Section 13.J)
TRUNCATE TABLE `website_settings`;
INSERT INTO `website_settings` (`setting_key`, `setting_value`, `setting_group`, `created_at`) VALUES
('website_name', 'VALENOR Horology', 'general', NOW()),
('website_tagline', 'Precision in Every Second — Luxury B2C Horology', 'general', NOW()),
('primary_email', 'concierge@valenor.com', 'contact', NOW()),
('primary_phone', '+92 300 000 8253', 'contact', NOW()),
('store_address', 'Suite 402, Executive Horology Tower, Blue Area, Islamabad, Pakistan', 'contact', NOW()),
('currency_code', 'PKR', 'commerce', NOW()),
('currency_symbol', 'Rs. ', 'commerce', NOW()),
('standard_shipping_fee', '250.00', 'commerce', NOW()),
('free_shipping_threshold', '15000.00', 'commerce', NOW()),
('cod_enabled', '1', 'commerce', NOW()),
('return_window_days', '14', 'policy', NOW()),
('return_policy_summary', '14-Day Hassle-Free Inspection Guarantee. Returned items must be unworn, in original luxury presentation boxes with all warranty tags and protective stickers intact.', 'policy', NOW()),
('theme_primary_white', '#FFFFFF', 'design', NOW()),
('theme_warm_cream', '#F8F3EB', 'design', NOW()),
('theme_primary_caramel', '#B8895B', 'design', NOW()),
('theme_dark_caramel', '#8B6342', 'design', NOW()),
('theme_text_charcoal', '#252525', 'design', NOW()),
('theme_border_beige', '#E8DED2', 'design', NOW());

-- 13. SAMPLE COMPLETED ORDER (For instant tracking & testing in Phase 01)
TRUNCATE TABLE `orders`;
INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`, `shipping_street`, `shipping_city`, `shipping_postal_code`, `delivery_instructions`, `payment_method`, `payment_status`, `subtotal`, `discount_amount`, `shipping_fee`, `total_amount`, `coupon_code`, `order_status`, `created_at`) VALUES
(1, 'VAL-20261115-001', 1, 'Hamza Tariq', 'customer@valenor.com', '+923001234567', 'House 14-B, Street 9, Sector F-7/2', 'Islamabad', '44000', 'Please call upon arrival.', 'COD', 'Paid', 38800.00, 3880.00, 0.00, 34920.00, 'WELCOME10', 'Delivered', DATE_SUB(NOW(), INTERVAL 6 DAY));

TRUNCATE TABLE `order_items`;
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variant_id`, `product_name`, `product_sku`, `variant_details`, `price_at_purchase`, `quantity`, `total_price`, `created_at`) VALUES
(1, 1, 1, 1, 'VALENOR Carrera Tachymeter Chronograph', 'VAL-CHR-001', 'Color: Silver Sunray, Dial: 42mm', 38800.00, 1, 38800.00, DATE_SUB(NOW(), INTERVAL 6 DAY));

TRUNCATE TABLE `order_status_history`;
INSERT INTO `order_status_history` (`order_id`, `status`, `comment`, `updated_by`, `admin_id`, `created_at`) VALUES
(1, 'Pending', 'Order placed successfully by customer via Cash on Delivery.', 'customer', NULL, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'Confirmed', 'Order verified via customer phone confirmation.', 'admin', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'Processing', 'Watch inspected, calibrated, and sent to cleanroom packaging.', 'admin', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'Packed', 'Sealed in VALENOR presentation wooden box with warranty booklet.', 'admin', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 'Dispatched', 'Handed over to BlueEx Insured Express Courier. Tracking: BEX-982314.', 'admin', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'Out for Delivery', 'Rider out for delivery in Islamabad Sector F-7.', 'system', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 'Delivered', 'Order delivered safely to customer. Cash collected.', 'admin', 1, DATE_SUB(NOW(), INTERVAL 2 DAY));

SET FOREIGN_KEY_CHECKS = 1;
