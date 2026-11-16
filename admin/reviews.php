<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN REVIEWS MODERATION
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Customer Reviews';
$adminPageHeader = 'Customer Reviews Moderation';
$adminPageSub    = 'Moderate horological ratings, verified purchase testimonials, and publishing statuses.';

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf()) {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $action   = $_POST['action'];

        if ($reviewId > 0 && in_array($action, ['approve', 'reject', 'delete'])) {
            if ($action === 'approve') {
                $db->prepare("UPDATE product_reviews SET status = 'approved' WHERE id = ?")->execute([$reviewId]);
                set_flash('success', 'Review approved and published to storefront.');
            } elseif ($action === 'reject') {
                $db->prepare("UPDATE product_reviews SET status = 'rejected' WHERE id = ?")->execute([$reviewId]);
                set_flash('info', 'Review marked as rejected.');
            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$reviewId]);
                set_flash('success', 'Review removed permanently.');
            }
            header('Location: ' . ADMIN_URL . '/reviews.php');
            exit;
        }
    }
}

$reviews = $db->query("
    SELECT pr.*, 
           p.name AS product_name, p.sku AS product_sku,
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.email AS customer_email
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    JOIN customers c ON pr.customer_id = c.id
    ORDER BY pr.id DESC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">Timepiece Reviews (<?= count($reviews) ?>)</h4>
        <div class="small text-muted">Testimonials displayed on individual product detail pages.</div>
    </div>
</div>

<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review Content</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="width:120px;" class="text-end">Moderate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $rev): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= e($rev['product_name']) ?></div>
                                <small class="text-muted font-monospace"><?= e($rev['product_sku']) ?></small>
                            </td>
                            <td>
                                <div><?= e($rev['customer_name']) ?></div>
                                <small class="text-muted"><?= e($rev['customer_email']) ?></small>
                            </td>
                            <td>
                                <?= star_rating_html($rev['rating']) ?>
                            </td>
                            <td>
                                <div class="fw-bold small"><?= e($rev['title']) ?></div>
                                <div class="small text-muted" style="max-width:320px;"><?= e($rev['review_text']) ?></div>
                            </td>
                            <td>
                                <?php if ($rev['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($rev['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= format_date($rev['created_at'], 'M d, Y') ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($rev['status'] !== 'approved'): ?>
                                    <form method="POST" action="<?= ADMIN_URL ?>/reviews.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-outline-success p-1 px-2" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($rev['status'] !== 'rejected'): ?>
                                    <form method="POST" action="<?= ADMIN_URL ?>/reviews.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-warning p-1 px-2 ms-1" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?= ADMIN_URL ?>/reviews.php" class="d-inline" onsubmit="return confirm('Delete this review permanently?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2 ms-1" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No client reviews submitted yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
