<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN REVIEWS & RATINGS MANAGEMENT (Phase 09)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Customer Reviews';
$adminPageHeader = 'Customer Reviews & Ratings Management';
$adminPageSub    = 'Moderate client testimonials, verified purchase badges, rating distributions, and photo submissions.';

// Handle moderation actions (Approve, Reject, Delete, Edit Rating/Review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf()) {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $action   = sanitize($_POST['action'] ?? '');

        if ($reviewId > 0) {
            if ($action === 'approve') {
                $db->prepare("UPDATE product_reviews SET status = 'approved', updated_at = NOW() WHERE id = ?")->execute([$reviewId]);
                set_flash('success', 'Review approved and published to timepiece page.');
            } elseif ($action === 'reject') {
                $db->prepare("UPDATE product_reviews SET status = 'rejected', updated_at = NOW() WHERE id = ?")->execute([$reviewId]);
                set_flash('info', 'Review marked as rejected.');
            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$reviewId]);
                set_flash('success', 'Review permanently removed from vault.');
            } elseif ($action === 'edit_rating') {
                $newRating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
                $newTitle  = sanitize($_POST['title'] ?? '');
                $newText   = sanitize($_POST['review_text'] ?? '');
                $newStatus = in_array($_POST['status'] ?? '', ['approved', 'pending', 'rejected']) ? $_POST['status'] : 'approved';

                $db->prepare("
                    UPDATE product_reviews SET 
                        rating = ?, title = ?, review_text = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$newRating, $newTitle, $newText, $newStatus, $reviewId]);

                set_flash('success', 'Review rating and content updated successfully.');
            }
            header('Location: ' . ADMIN_URL . '/reviews.php');
            exit;
        }
    }
}

// Filters
$filterStatus = sanitize($_GET['status'] ?? '');
$filterRating = (int)($_GET['rating'] ?? 0);

$whereClauses = [];
$params = [];

if (in_array($filterStatus, ['approved', 'pending', 'rejected'])) {
    $whereClauses[] = "pr.status = ?";
    $params[] = $filterStatus;
}
if ($filterRating >= 1 && $filterRating <= 5) {
    $whereClauses[] = "pr.rating = ?";
    $params[] = $filterRating;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Metrics
$totalReviews    = (int)$db->query("SELECT COUNT(*) FROM product_reviews")->fetchColumn();
$approvedReviews = (int)$db->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'approved'")->fetchColumn();
$pendingReviews  = (int)$db->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'")->fetchColumn();
$rejectedReviews = (int)$db->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'rejected'")->fetchColumn();
$avgRatingGlobal = (float)$db->query("SELECT AVG(rating) FROM product_reviews WHERE status = 'approved'")->fetchColumn() ?: 5.0;

// Fetch Reviews with Filters
$stmtReviews = $db->prepare("
    SELECT pr.*, 
           p.name AS product_name, p.sku AS product_sku,
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.email AS customer_email
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    JOIN customers c ON pr.customer_id = c.id
    {$whereSql}
    ORDER BY pr.id DESC
");
$stmtReviews->execute($params);
$reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Statistics Overview -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Total Reviews</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= $totalReviews ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white" style="background:var(--caramel-dark);">
                    <i class="bi bi-chat-square-quote fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Average Rating</span>
                    <h3 class="fw-bold mb-0 mt-1 text-warning"><?= number_format($avgRatingGlobal, 1) ?> ★</h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-warning">
                    <i class="bi bi-star-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Approved &amp; Live</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success"><?= $approvedReviews ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-success">
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Pending Moderation</span>
                    <h3 class="fw-bold mb-0 mt-1 <?= $pendingReviews > 0 ? 'text-danger' : 'text-muted' ?>"><?= $pendingReviews ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-secondary">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4 bg-white p-3">
    <form method="GET" action="<?= ADMIN_URL ?>/reviews.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Publishing Statuses</option>
                <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved Only</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Moderation</option>
                <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected Only</option>
            </select>
        </div>
        <div class="col-md-4">
            <select name="rating" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">All Star Ratings</option>
                <option value="5" <?= $filterRating === 5 ? 'selected' : '' ?>>5 Stars Only</option>
                <option value="4" <?= $filterRating === 4 ? 'selected' : '' ?>>4 Stars Only</option>
                <option value="3" <?= $filterRating === 3 ? 'selected' : '' ?>>3 Stars Only</option>
                <option value="2" <?= $filterRating === 2 ? 'selected' : '' ?>>2 Stars Only</option>
                <option value="1" <?= $filterRating === 1 ? 'selected' : '' ?>>1 Star Only</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-dark px-3">Filter</button>
            <a href="<?= ADMIN_URL ?>/reviews.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Reviews Table -->
<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review Content</th>
                    <th>Photo</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="width:130px;" class="text-end">Manage</th>
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
                                <small class="text-muted d-block"><?= e($rev['customer_email']) ?></small>
                                <?php if ($rev['is_verified_purchase']): ?>
                                    <span class="badge bg-success-subtle text-success small" style="font-size:0.68rem;">
                                        <i class="bi bi-patch-check-fill me-1"></i>Verified Buyer
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= star_rating_html($rev['rating']) ?>
                                <span class="small fw-bold ms-1">(<?= (int)$rev['rating'] ?>)</span>
                            </td>
                            <td>
                                <div class="fw-bold small"><?= e($rev['title']) ?></div>
                                <div class="small text-muted" style="max-width:280px;"><?= e($rev['review_text']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($rev['review_image'])): 
                                    $imgSrc = BASE_URL . '/' . ltrim($rev['review_image'], '/');
                                ?>
                                    <a href="<?= e($imgSrc) ?>" target="_blank" title="View Customer Image">
                                        <img src="<?= e($imgSrc) ?>" alt="Review Image" class="rounded border" style="width:48px; height:48px; object-fit:cover;">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
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
                            <td class="small text-muted text-nowrap"><?= format_date($rev['created_at'], 'M d, Y') ?></td>
                            <td class="text-end text-nowrap">
                                <!-- Approve Button -->
                                <?php if ($rev['status'] !== 'approved'): ?>
                                    <form method="POST" action="<?= ADMIN_URL ?>/reviews.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-outline-success p-1 px-2" title="Approve &amp; Publish">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Reject Button -->
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

                                <!-- Edit Rating & Content Modal Trigger -->
                                <button type="button" class="btn btn-sm btn-outline-dark p-1 px-2 ms-1 edit-review-btn"
                                    data-review='<?= htmlspecialchars(json_encode($rev), ENT_QUOTES, 'UTF-8') ?>'
                                    title="Manage Rating &amp; Review">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <!-- Delete Form -->
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
                        <td colspan="8" class="text-center py-5 text-muted">No client reviews matching criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================================================================
     ADMIN EDIT REVIEW & MANAGE RATING MODAL
     ====================================================================== -->
<div class="modal fade" id="adminEditReviewModal" tabindex="-1" aria-labelledby="adminEditReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--bg-cream); border-color:var(--border-beige);">
                <h5 class="modal-title font-serif fw-bold" id="adminEditReviewModalLabel">Manage Rating &amp; Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= ADMIN_URL ?>/reviews.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_rating">
                <input type="hidden" name="review_id" id="adminEditReviewId" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Star Rating</label>
                        <select name="rating" id="adminEditRating" class="form-select" required>
                            <option value="5">5 Stars — Flawless Masterpiece</option>
                            <option value="4">4 Stars — Exceptional Precision</option>
                            <option value="3">3 Stars — Satisfactory</option>
                            <option value="2">2 Stars — Subpar</option>
                            <option value="1">1 Star — Unacceptable</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Publishing Status</label>
                        <select name="status" id="adminEditStatus" class="form-select" required>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Review Headline</label>
                        <input type="text" name="title" id="adminEditTitle" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Review Description</label>
                        <textarea name="review_text" id="adminEditText" class="form-control" rows="4" required></textarea>
                    </div>
                </div>

                <div class="modal-footer" style="border-color:var(--border-beige);">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4">Save Review Adjustments</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-review-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const rev = JSON.parse(this.dataset.review);
        document.getElementById('adminEditReviewId').value = rev.id;
        document.getElementById('adminEditRating').value = rev.rating;
        document.getElementById('adminEditStatus').value = rev.status;
        document.getElementById('adminEditTitle').value = rev.title;
        document.getElementById('adminEditText').value = rev.review_text;

        const modal = new bootstrap.Modal(document.getElementById('adminEditReviewModal'));
        modal.show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
