<?php
// =============================================================================
// VALENOR HOROLOGY — ABOUT THE MAISON & HERITAGE
// =============================================================================

require_once __DIR__ . '/config/config.php';

$pageTitle = 'The Maison & Swiss Horological Heritage';
$pageDesc  = 'Learn about VALENOR Horology — our commitment to mechanical excellence, architectural design, and hand-finished precision.';

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-5" style="background: linear-gradient(135deg, rgba(248, 243, 235, 0.96), rgba(255, 255, 255, 0.9)), url('assets/images/hero-banner.jpg') center/cover no-repeat; border-bottom:1px solid var(--border-beige);">
    <div class="container text-center py-4">
        <span class="section-eyebrow">Haute Horlogerie Tradition</span>
        <h1 class="display-4 font-serif fw-bold mb-3">The Art of Enduring Precision</h1>
        <p class="lead text-muted mx-auto" style="max-width:680px;">
            Founded with an unyielding reverence for classical Genevan watchmaking, VALENOR marries traditional architectural symmetry with surgical-grade modern metallurgy.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
            <img src="assets/images/watch-skeleton-tourbillon.jpg" alt="VALENOR Mechanical Caliber" class="rounded shadow-sm border w-100" style="border-color:var(--border-beige) !important;">
        </div>
        <div class="col-lg-6">
            <span class="section-eyebrow" style="text-align:left;">Our Mechanical Philosophy</span>
            <h2 class="font-serif fw-bold h2 mb-3">Calibrated to Exacting Standards</h2>
            <p style="line-height:1.8; color:#444;">
                Every VALENOR timepiece begins its journey with meticulous technical drafting. From 316L solid surgical stainless steel cases to double-domed sapphire crystals with anti-reflective treatment, no shortcut is taken.
            </p>
            <p style="line-height:1.8; color:#444;">
                Our skeleton and automatic movements oscillate at up to 28,800 vibrations per hour, offering collectors tactile crown winding, smooth sweeping hands, and exhibition glass display casebacks.
            </p>
            <div class="row g-3 mt-2">
                <div class="col-6">
                    <div class="p-3 bg-white border rounded text-center" style="border-color:var(--border-beige) !important;">
                        <h3 class="font-serif fw-bold mb-1" style="color:var(--caramel-dark);">316L</h3>
                        <div class="small text-muted">Surgical Stainless Steel</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-white border rounded text-center" style="border-color:var(--border-beige) !important;">
                        <h3 class="font-serif fw-bold mb-1" style="color:var(--caramel-dark);">9 Mohs</h3>
                        <div class="small text-muted">Sapphire Crystal Hardness</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

