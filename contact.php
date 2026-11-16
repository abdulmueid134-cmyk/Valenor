<?php
// =============================================================================
// VALENOR HOROLOGY — CONCIERGE & CLIENT CONTACT
// =============================================================================

require_once __DIR__ . '/config/config.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please resubmit.';
    } else {
        $name    = sanitize($_POST['name'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $phone   = sanitize($_POST['phone'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($message)) {
            $error = 'Please fill in your name, email, and inquiry message.';
        } else {
            $sent = true;
            set_flash('success', 'Thank you, ' . $name . '. Your inquiry has been routed to our Senior Concierge Desk.');
        }
    }
}

$pageTitle = 'Concierge & Boutique Support';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold">Client Concierge Support</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-7">
            <div class="card p-4 p-md-5 bg-white border shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h3 class="font-serif fw-bold mb-3">Send a Message</h3>
                <p class="text-muted small mb-4">Our horology specialists are available 7 days a week to assist with timepiece specifications, custom straps, or order inquiries.</p>

                <?php if ($sent): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div>Your message has been received. Our concierge will reply within 4 business hours.</div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/contact.php">
                    <?= csrf_field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="name" class="form-label small fw-bold text-uppercase">Your Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="email" class="form-label small fw-bold text-uppercase">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="phone" class="form-label small fw-bold text-uppercase">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="+92 ...">
                        </div>
                        <div class="col-sm-6">
                            <label for="subject" class="form-label small fw-bold text-uppercase">Inquiry Topic</label>
                            <select id="subject" name="subject" class="form-select">
                                <option value="Watch Consultation">Timepiece Recommendation</option>
                                <option value="Order Tracking">Order &amp; Delivery Inquiry</option>
                                <option value="Warranty & Service">Warranty &amp; Caliber Service</option>
                                <option value="Corporate / Gifting">Corporate &amp; Milestone Gifting</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="message" class="form-label small fw-bold text-uppercase">Inquiry Details</label>
                        <textarea id="message" name="message" class="form-control" rows="5" placeholder="Please describe how our horological specialists can assist you..." required></textarea>
                    </div>

                    <button type="submit" class="btn-valenor-primary w-100 justify-content-center py-2">
                        <i class="bi bi-envelope-paper me-1"></i> Transmit Inquiry &rarr;
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card p-4 bg-white border h-100 shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h4 class="font-serif fw-bold mb-3" style="color:var(--caramel-dark);">Boutique Headquarters</h4>
                <div class="mb-4">
                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-geo-alt-fill fs-4" style="color:var(--caramel-primary);"></i>
                        <div>
                            <strong>VALENOR Executive Maison</strong>
                            <div class="text-muted small mt-1">Suite 402, Executive Horology Tower, Blue Area, Islamabad, Pakistan</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-telephone-fill fs-4" style="color:var(--caramel-primary);"></i>
                        <div>
                            <strong>Concierge Phone Line</strong>
                            <div class="text-muted small mt-1">+92 300 000 8253 &bull; Mon - Sat: 9:00 AM - 9:00 PM PKT</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-envelope-fill fs-4" style="color:var(--caramel-primary);"></i>
                        <div>
                            <strong>Direct Correspondence</strong>
                            <div class="text-muted small mt-1">concierge@valenor.com &bull; corporate@valenor.com</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded border" style="background-color:var(--bg-cream); border-color:var(--border-beige) !important;">
                    <div class="fw-bold small mb-1" style="color:var(--caramel-dark);"><i class="bi bi-truck me-1"></i> Nationwide Courier Fulfillment</div>
                    <div class="text-muted small">All deliveries are insured through BlueEx Logistics with dedicated door-to-door tracking and tamper-proof wax seal presentation boxes.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
