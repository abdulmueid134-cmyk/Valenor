<?php
// =============================================================================
// VALENOR HOROLOGY — REUSABLE DOCUMENT FOOTER COMPONENT
// =============================================================================

require_once __DIR__ . '/../config/config.php';

$footerSettingsStmt = getDB()->query("SELECT setting_key, setting_value FROM website_settings");
$footerSettings = $footerSettingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$footEmail   = $footerSettings['primary_email'] ?? 'concierge@valenor.com';
$footPhone   = $footerSettings['primary_phone'] ?? '+92 300 000 8253';
$footAddress = $footerSettings['store_address'] ?? 'Suite 402, Executive Horology Tower, Blue Area, Islamabad';
?>

<!-- ===========================================================================
     SITE FOOTER
     =========================================================================== -->
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">

            <!-- Col 1: Brand Info -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand-title"><?= APP_NAME ?></div>
                <span class="footer-brand-sub">Genève &bull; Haute Horlogerie</span>
                <p style="color:#A8A096; line-height:1.7; font-size:0.86rem; max-width:320px;">
                    Dedicated to the enduring art of classical and modern mechanical watchmaking. Hand-assembled luxury timepieces engineered with sapphire crystal and surgical-grade steel.
                </p>
                <div class="social-links">
                    <a href="https://instagram.com" target="_blank" rel="noopener" class="social-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="https://facebook.com" target="_blank" rel="noopener" class="social-btn" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://youtube.com" target="_blank" rel="noopener" class="social-btn" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    <a href="https://linkedin.com" target="_blank" rel="noopener" class="social-btn" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>

            <!-- Col 2: Navigation Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h5 class="footer-heading">Collections</h5>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>/products.php?category=chronographs"><i class="bi bi-chevron-right small"></i>Chronographs</a></li>
                    <li><a href="<?= BASE_URL ?>/products.php?category=automatic-luxury"><i class="bi bi-chevron-right small"></i>Automatic Luxury</a></li>
                    <li><a href="<?= BASE_URL ?>/products.php?category=classic-dress"><i class="bi bi-chevron-right small"></i>Classic Dress</a></li>
                    <li><a href="<?= BASE_URL ?>/products.php?category=divers-sports"><i class="bi bi-chevron-right small"></i>Divers &amp; Sports</a></li>
                    <li><a href="<?= BASE_URL ?>/products.php?category=minimalist-elegance"><i class="bi bi-chevron-right small"></i>Minimalist</a></li>
                    <li><a href="<?= BASE_URL ?>/products.php?filter=sale" style="color:var(--caramel-light);"><i class="bi bi-tag-fill small me-1"></i>20% Privilege Sale</a></li>
                </ul>
            </div>

            <!-- Col 3: Customer Care & Services -->
            <div class="col-lg-3 col-md-6 col-6">
                <h5 class="footer-heading">Client Services</h5>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>/track-order.php"><i class="bi bi-geo-alt me-1 text-warning"></i>Live Order Tracking</a></li>
                    <li><a href="<?= BASE_URL ?>/request-return.php"><i class="bi bi-arrow-counterclockwise me-1"></i>14-Day Return Guarantee</a></li>
                    <li><a href="<?= BASE_URL ?>/compare.php"><i class="bi bi-arrow-left-right me-1"></i>Product Comparison</a></li>
                    <li><a href="<?= BASE_URL ?>/wishlist.php"><i class="bi bi-heart me-1"></i>Collector Wishlist</a></li>
                    <li><a href="<?= BASE_URL ?>/about.php"><i class="bi bi-award me-1"></i>Swiss Heritage &amp; Quality</a></li>
                    <li><a href="<?= BASE_URL ?>/contact.php"><i class="bi bi-envelope me-1"></i>Concierge Support</a></li>
                </ul>
            </div>

            <!-- Col 4: Boutique Concierge Contact -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-heading">Boutique Concierge</h5>
                <div class="footer-contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <div><?= e($footAddress) ?></div>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <div><?= e($footPhone) ?></div>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <div><?= e($footEmail) ?></div>
                </div>
                <div class="mt-3">
                    <span class="badge" style="background:var(--caramel-dark); font-size:0.75rem; padding:6px 10px;">
                        <i class="bi bi-cash me-1"></i> Cash on Delivery Available
                    </span>
                </div>
            </div>

        </div>

        <!-- Bottom Copyright and Payment Icons -->
        <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                &copy; <?= date('Y') ?> <strong><?= APP_NAME ?> Horology</strong>. All Rights Reserved. Engineered with Core PHP &amp; MySQL.
            </div>
            <div class="d-flex align-items-center gap-3" style="font-size:0.78rem;">
                <span class="text-white-50"><i class="bi bi-shield-lock me-1 text-success"></i> 256-Bit SSL Secured</span>
                <span class="text-white-50">&bull;</span>
                <span class="text-white-50"><i class="bi bi-truck me-1"></i> Insured BlueEx Courier</span>
                <span class="text-white-50">&bull;</span>
                <span class="text-white-50">Cash on Delivery (COD)</span>
            </div>
        </div>
    </div>
</footer>

<!-- Toast Notifications Container -->
<div class="valenor-toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Storefront JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
