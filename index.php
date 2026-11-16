<?php
// =============================================================================
// VALENOR HOROLOGY — B2C E-COMMERCE HOMEPAGE (PHASE 02)
// Fully dynamic, connected to MySQL, White & Caramel luxury aesthetic
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$pageTitle = 'Fine Swiss-Caliber Timepieces & Luxury Watches';
$pageDesc  = 'VALENOR Horology — Discover luxury chronographs, automatic skeleton calibers, classic dress watches, and professional divers with insured nationwide Cash on Delivery.';

$db = getDB();

// -----------------------------------------------------------------------------
// 1. DATA QUERIES FROM MYSQL
// -----------------------------------------------------------------------------

// 1.1 Five Main Categories
$catStmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC LIMIT 5");
$categories = $catStmt->fetchAll();

// 1.2 Helper for fetching products with primary image & category info
function getHomepageProducts($whereClause, $limit = 4) {
    global $db;
    $sql = "
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
               (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' AND {$whereClause}
        ORDER BY p.id ASC
        LIMIT {$limit}
    ";
    return $db->query($sql)->fetchAll();
}

// 1.3 Featured Products
$featuredProducts = getHomepageProducts("p.is_featured = 1", 4);

// 1.4 New Arrivals
$newArrivals = getHomepageProducts("p.is_new_arrival = 1", 4);

// 1.5 Best Sellers
$bestSellers = getHomepageProducts("p.is_best_seller = 1", 4);

// 1.6 Special Discount Watches
$saleProducts = getHomepageProducts("p.discount_price IS NOT NULL AND p.discount_price > 0", 4);

// 1.7 Customer Reviews from MySQL
$revStmt = $db->query("
    SELECT pr.*, p.name AS product_name, p.slug AS product_slug,
           CONCAT(c.first_name, ' ', SUBSTRING(c.last_name, 1, 1), '.') AS customer_display_name
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    JOIN customers c ON pr.customer_id = c.id
    WHERE pr.status = 'approved'
    ORDER BY pr.rating DESC, pr.id DESC
    LIMIT 3
");
$reviews = $revStmt->fetchAll();

// Include Reusable Header & Navbar
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- ===========================================================================
     SECTION 6: HERO BANNER WITH PROMOTIONAL COUNTDOWN & CTA
     =========================================================================== -->
<section class="hero-wrapper" aria-label="Featured Campaign">
    <div class="hero-slide">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-content">
                        <span class="hero-pill">
                            <i class="bi bi-clock-history"></i> Haute Horlogerie &bull; Edition 2026
                        </span>
                        <h1 class="hero-title">
                            The Precision of <span>Swiss Caliber</span> Engineering.
                        </h1>
                        <p class="hero-subtitle">
                            Impeccably hand-calibrated mechanical movements, anti-glare sapphire crystal, and marine-grade surgical steel crafted for the discerning collector.
                        </p>

                        <!-- Dynamic Announcement Countdown -->
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <span class="text-white-50 text-uppercase fw-semibold" style="font-size:0.75rem; letter-spacing:0.15em;">
                                Special 20% Privilege Offer Ends In:
                            </span>
                        </div>
                        <div class="countdown-container" aria-label="Campaign countdown timer">
                            <div class="countdown-box">
                                <span class="countdown-number" id="cd-days">02</span>
                                <span class="countdown-label">Days</span>
                            </div>
                            <div class="countdown-box">
                                <span class="countdown-number" id="cd-hours">14</span>
                                <span class="countdown-label">Hours</span>
                            </div>
                            <div class="countdown-box">
                                <span class="countdown-number" id="cd-mins">35</span>
                                <span class="countdown-label">Mins</span>
                            </div>
                            <div class="countdown-box">
                                <span class="countdown-number" id="cd-secs">00</span>
                                <span class="countdown-label">Secs</span>
                            </div>
                        </div>

                        <!-- Hero CTA Buttons -->
                        <div class="d-flex flex-wrap gap-3">
                            <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary">
                                <i class="bi bi-collection"></i> Discover Collection
                            </a>
                            <a href="#categories" class="btn-valenor-outline">
                                View 5 Categories &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===========================================================================
     TRUST / VALUE PROPOSITION STRIP
     =========================================================================== -->
<div class="trust-strip">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <h5 class="trust-title">2-Year Warranty</h5>
                        <p class="trust-desc">International movement protection</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-truck"></i></div>
                    <div>
                        <h5 class="trust-title">Free Delivery</h5>
                        <p class="trust-desc">On orders over Rs. 15,000</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
                    <div>
                        <h5 class="trust-title">14-Day Inspection</h5>
                        <p class="trust-desc">Guaranteed hassle-free returns</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="trust-item">
                    <div class="trust-icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <h5 class="trust-title">Cash on Delivery</h5>
                        <p class="trust-desc">Pay upon doorstep receipt</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===========================================================================
     SECTION 7: FIVE MAIN PRODUCT CATEGORIES (Section 3.B)
     =========================================================================== -->
<section id="categories" class="py-5" style="background-color: var(--bg-white); border-bottom: 1px solid var(--border-beige);">
    <div class="container py-4">
        <div class="section-header">
            <span class="section-eyebrow">Distinctive Horological Styles</span>
            <h2 class="section-title">Five Curated Categories</h2>
            <p class="section-subtitle">
                Each category is engineered to strict mechanical and aesthetic standards. Explore master timepieces tailored for every occasion.
            </p>
            <div class="section-divider"></div>
        </div>

        <div class="row g-4">
            <?php foreach ($categories as $cat): ?>
                <div class="col-lg col-md-4 col-sm-6">
                    <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($cat['slug']) ?>" class="category-card" title="View <?= e($cat['name']) ?> Collection">
                        <div class="category-img-box">
                            <img src="<?= e(BASE_URL . '/' . ltrim($cat['image_path'], '/')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                        </div>
                        <div class="category-body">
                            <h3 class="category-name"><?= e($cat['name']) ?></h3>
                            <p class="category-desc"><?= e($cat['description']) ?></p>
                            <span class="category-link">
                                Explore <i class="bi bi-arrow-right"></i>
                            </span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===========================================================================
     SECTION 8: NEW ARRIVALS
     =========================================================================== -->
<section id="new-arrivals" class="py-5">
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <span class="section-eyebrow" style="text-align:left;">Fresh From Geneva</span>
                <h2 class="section-title" style="text-align:left; margin-bottom:0;">New Arrivals</h2>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= BASE_URL ?>/products.php?filter=new_arrivals" class="btn btn-outline-dark rounded-pill px-4" style="border-color:var(--border-beige); font-size:0.85rem;">
                    View All New Timepieces &rarr;
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($newArrivals as $prod): ?>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <?= render_product_card($prod) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===========================================================================
     SECTION 9: FEATURED PRODUCTS
     =========================================================================== -->
<section id="featured-products" class="py-5" style="background-color: var(--bg-white); border-top: 1px solid var(--border-beige); border-bottom: 1px solid var(--border-beige);">
    <div class="container py-4">
        <div class="section-header">
            <span class="section-eyebrow">Collector Highlights</span>
            <h2 class="section-title">Featured Timepieces</h2>
            <p class="section-subtitle">
                Masterfully proportioned horological icons recognized for mechanical ingenuity and aesthetic balance.
            </p>
            <div class="section-divider"></div>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredProducts as $prod): ?>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <?= render_product_card($prod) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===========================================================================
     SECTION 11: SPECIAL DISCOUNT & PRIVILEGE COUPON BANNER
     =========================================================================== -->
<section class="special-offer-banner" aria-label="Privilege Promotion">
    <div class="container">
        <div class="special-offer-box">
            <span class="valenor-badge valenor-badge-sale mb-3 d-inline-block">Limited Collector Privilege</span>
            <h2 style="font-family:var(--font-serif); font-size:3.2rem; font-weight:700; margin-bottom:14px;">
                20% OFF Exceptional Timepieces
            </h2>
            <p style="font-size:1.05rem; color:#E8DED2; max-width:540px; margin:0 auto 20px auto; line-height:1.6;">
                Apply your exclusive privilege code at checkout to enjoy 20% savings on qualifying orders over Rs. 25,000, accompanied by complimentary insured courier delivery.
            </p>

            <!-- Click to Copy Coupon Code -->
            <div>
                <button type="button" class="coupon-pill coupon-copy-btn" data-code="VALENOR20" title="Click to copy coupon code">
                    <span>Code:</span>
                    <span class="coupon-code">VALENOR20</span>
                    <i class="bi bi-clipboard ms-1"></i>
                </button>
            </div>

            <div class="mt-3">
                <a href="<?= BASE_URL ?>/products.php?filter=sale" class="btn-valenor-primary" style="background:#FFFFFF; color:var(--text-charcoal); border-color:#FFFFFF;">
                    Shop Special Offers &rarr;
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===========================================================================
     SECTION 10: BEST-SELLING PRODUCTS
     =========================================================================== -->
<section id="best-sellers" class="py-5">
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <span class="section-eyebrow" style="text-align:left;">Most Coveted</span>
                <h2 class="section-title" style="text-align:left; margin-bottom:0;">Best-Selling Watches</h2>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= BASE_URL ?>/products.php?filter=best_sellers" class="btn btn-outline-dark rounded-pill px-4" style="border-color:var(--border-beige); font-size:0.85rem;">
                    Explore Best Sellers &rarr;
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($bestSellers as $prod): ?>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <?= render_product_card($prod) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===========================================================================
     SECTION 12: CUSTOMER REVIEWS PREVIEW
     =========================================================================== -->
<section id="reviews" class="py-5" style="background-color: var(--bg-white); border-top: 1px solid var(--border-beige); border-bottom: 1px solid var(--border-beige);">
    <div class="container py-4">
        <div class="section-header">
            <span class="section-eyebrow">Verified Collector Feedback</span>
            <h2 class="section-title">Client Testimonials</h2>
            <p class="section-subtitle">
                Over 18,000 connoisseurs nationwide trust VALENOR for authentic horological craftsmanship and attentive concierge service.
            </p>
            <div class="section-divider"></div>
        </div>

        <div class="row g-4">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="review-card">
                            <div class="review-quote-icon">
                                <i class="bi bi-quote"></i>
                            </div>
                            <div class="mb-2">
                                <?= star_rating_html($rev['rating']) ?>
                            </div>
                            <h3 class="review-title"><?= e($rev['title']) ?></h3>
                            <p class="review-text">
                                "<?= e($rev['review_text']) ?>"
                            </p>
                            <div class="reviewer-meta">
                                <div class="reviewer-avatar">
                                    <?= strtoupper(substr($rev['customer_display_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h4 class="reviewer-name"><?= e($rev['customer_display_name']) ?></h4>
                                    <div class="verified-badge">
                                        <i class="bi bi-patch-check-fill"></i> Verified Collector &bull; <?= e($rev['product_name']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===========================================================================
     SECTION 13: NEWSLETTER SUBSCRIPTION
     =========================================================================== -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-box">
            <span class="section-eyebrow">Private Correspondence</span>
            <h2 style="font-family:var(--font-serif); font-size:2.4rem; font-weight:700; margin-bottom:10px;">
                Join The VALENOR Horology Gazette
            </h2>
            <p class="text-muted" style="font-size:0.92rem; line-height:1.6;">
                Subscribe to receive private invitations to limited-edition watch releases, horological insights, and an immediate 10% welcome privilege code.
            </p>

            <form id="newsletter-form" class="newsletter-form">
                <input type="email" id="newsletter-email" name="email" class="newsletter-input" placeholder="Enter your email address..." required>
                <button type="submit" class="btn-valenor-primary" style="white-space:nowrap; padding:13px 24px;">
                    <i class="bi bi-envelope-paper me-1"></i> Subscribe
                </button>
            </form>
            <div class="mt-3 text-muted small">
                <i class="bi bi-lock me-1"></i> We respect your privacy. Unsubscribe at any time.
            </div>
        </div>
    </div>
</section>

<?php
// Include Reusable Footer
require_once INCLUDES_PATH . '/footer.php';
?>
