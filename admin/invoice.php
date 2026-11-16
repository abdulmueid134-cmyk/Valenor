<?php
// =============================================================================
// VALENOR HOROLOGY — OFFICIAL LUXURY ORDER INVOICE (Phase 08)
// Printable luxury invoice with print media stylesheet & authorized access
// =============================================================================

require_once __DIR__ . '/../config/config.php';

$orderId = (int)($_GET['id'] ?? 0);
$orderNumber = sanitize($_GET['order'] ?? '');

$db = getDB();

if ($orderId > 0) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
} elseif (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
} else {
    die('Order reference missing.');
}

if (!$order) {
    die('Order not found.');
}

// Access authorization: Must be Admin OR the Customer who placed the order
$isAdmin = is_admin_logged_in();
$isOwner = (is_logged_in() && current_user_id() === (int)$order['customer_id']);

if (!$isAdmin && !$isOwner) {
    die('Unauthorized access.');
}

// Fetch order items
$itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([(int)$order['id']]);
$items = $itemsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice — <?= e($order['order_number']) ?> — VALENOR Horology</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --caramel-primary: #B8895B;
            --caramel-dark: #8B6342;
            --bg-cream: #F8F3EB;
            --border-beige: #E8DED2;
        }
        body {
            font-family: 'Inter', sans-serif;
            color: #252525;
            background: #fdfdfd;
            padding: 30px 0;
        }
        .font-serif {
            font-family: 'Cormorant Garamond', Georgia, serif;
        }
        .invoice-card {
            background: #fff;
            border: 1px solid var(--border-beige);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            padding: 45px;
            max-width: 820px;
            margin: 0 auto;
        }
        .invoice-header {
            border-bottom: 2px solid var(--caramel-primary);
            padding-bottom: 25px;
            margin-bottom: 30px;
        }
        .invoice-table th {
            background-color: var(--bg-cream);
            color: #252525;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-color: var(--border-beige);
        }
        .invoice-table td {
            border-color: var(--border-beige);
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .invoice-card {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Top Action Toolbar (Hidden in Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print mx-auto" style="max-width:820px;">
        <button onclick="window.close();" class="btn btn-sm btn-outline-secondary">
            &larr; Close Window
        </button>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-sm btn-dark">
                <i class="bi bi-printer me-1"></i> Print Invoice
            </button>
        </div>
    </div>

    <!-- Printable Invoice Card -->
    <div class="invoice-card">
        
        <!-- Header -->
        <div class="invoice-header d-flex justify-content-between align-items-start">
            <div>
                <h1 class="font-serif fw-bold mb-0" style="letter-spacing:0.18em; font-size:2rem; color:var(--caramel-dark);">VALENOR</h1>
                <div class="text-uppercase small text-muted" style="letter-spacing:0.12em; font-size:0.75rem;">Swiss Horology Atelier</div>
                <div class="small text-muted mt-2" style="font-size:0.8rem; line-height:1.4;">
                    Vault &amp; Atelier Concierge<br>
                    Khyaban-e-Iqbal, DHA Phase 5, Karachi<br>
                    support@valenor.com &bull; +92 21 3588 4020
                </div>
            </div>
            <div class="text-end">
                <h3 class="font-serif fw-bold mb-1" style="color:var(--text-dark);">OFFICIAL INVOICE</h3>
                <div class="font-monospace fw-bold fs-6" style="color:var(--caramel-primary);"><?= e($order['order_number']) ?></div>
                <div class="small text-muted mt-1">Date: <?= date('F d, Y', strtotime($order['created_at'])) ?></div>
                <div class="mt-2">
                    <span class="badge" style="background:var(--caramel-dark); font-size:0.75rem;"><?= e($order['order_status']) ?></span>
                    <span class="badge <?= $order['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size:0.75rem;">
                        <?= e($order['payment_status']) ?> (<?= e($order['payment_method']) ?>)
                    </span>
                </div>
            </div>
        </div>

        <!-- Billed To & Shipping Destination -->
        <div class="row g-4 mb-4 pb-3 border-bottom" style="border-color:var(--border-beige) !important;">
            <div class="col-sm-6">
                <span class="small fw-bold text-uppercase text-muted d-block mb-1" style="letter-spacing:0.06em; font-size:0.72rem;">Customer Information</span>
                <div class="fw-bold fs-6"><?= e($order['customer_name']) ?></div>
                <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= e($order['customer_email']) ?></div>
                <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></div>
            </div>
            <div class="col-sm-6 text-sm-end">
                <span class="small fw-bold text-uppercase text-muted d-block mb-1" style="letter-spacing:0.06em; font-size:0.72rem;">Insured Delivery Destination</span>
                <div class="fw-bold fs-6"><?= e($order['customer_name']) ?></div>
                <div class="small text-muted"><?= e($order['shipping_street']) ?></div>
                <div class="small text-muted"><?= e($order['shipping_city']) ?> <?= e($order['shipping_postal_code']) ?></div>
                <?php if (!empty($order['tracking_number'])): ?>
                    <div class="small mt-1 text-primary">
                        <i class="bi bi-truck me-1"></i> <?= e($order['courier_name'] ?: 'BlueEx Express') ?>: <code><?= e($order['tracking_number']) ?></code>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Items Table -->
        <table class="table invoice-table align-middle mb-4">
            <thead>
                <tr>
                    <th style="width:50%;">Description</th>
                    <th class="text-center" style="width:15%;">SKU</th>
                    <th class="text-center" style="width:10%;">Qty</th>
                    <th class="text-end" style="width:25%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td>
                            <div class="fw-bold font-serif fs-6"><?= e($it['product_name']) ?></div>
                            <?php if (!empty($it['variant_details'])): ?>
                                <small class="text-muted"><?= e($it['variant_details']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center font-monospace small"><?= e($it['product_sku']) ?></td>
                        <td class="text-center fw-bold"><?= (int)$it['quantity'] ?></td>
                        <td class="text-end fw-bold" style="color:var(--caramel-dark);">
                            <?= format_price($it['total_price']) ?>
                            <div class="text-muted small fw-normal"><?= format_price($it['price_at_purchase']) ?> each</div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Summary Breakdown -->
        <div class="row justify-content-end mb-4">
            <div class="col-sm-6 col-md-5">
                <div class="d-flex justify-content-between py-1 small text-muted">
                    <span>Subtotal:</span>
                    <span class="fw-semibold text-dark"><?= format_price($order['subtotal']) ?></span>
                </div>
                <?php if ($order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between py-1 small text-success">
                        <span>Privilege Discount (<?= e($order['coupon_code'] ?? '') ?>):</span>
                        <span class="fw-semibold">-<?= format_price($order['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-1 small text-muted">
                    <span>Insured Delivery:</span>
                    <span class="fw-semibold text-dark"><?= $order['shipping_fee'] == 0 ? 'Complimentary' : format_price($order['shipping_fee']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-top fs-5 fw-bold" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                    <span>Grand Total (PKR):</span>
                    <span><?= format_price($order['total_amount']) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer / Terms -->
        <div class="pt-4 border-top text-center text-muted small" style="border-color:var(--border-beige) !important; font-size:0.75rem;">
            <p class="mb-1"><strong>Payment Terms:</strong> Cash on Delivery (COD) upon physical inspection &amp; white-glove handover.</p>
            <p class="mb-0">All VALENOR horological pieces include a 2-Year International Manufacture Warranty and 14-day return privilege.</p>
        </div>

    </div>
</div>

</body>
</html>
