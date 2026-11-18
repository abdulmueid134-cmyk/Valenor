<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN CATEGORIES MANAGEMENT (Section 3.B)
// List, monitor product counts, and manage master horological collections
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

// Handle POST Actions (e.g. Quick Toggle Status Active/Inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Security verification token invalid. Please try again.');
        header('Location: ' . ADMIN_URL . '/categories.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'toggle_status') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $stmt = $db->prepare("SELECT id, name, status FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cat) {
            $newStatus = ($cat['status'] === 'active') ? 'inactive' : 'active';
            $update = $db->prepare("UPDATE categories SET status = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$newStatus, $categoryId]);

            $statusLabel = ($newStatus === 'active') ? 'ACTIVE' : 'INACTIVE';
            set_flash('success', "Collection '{$cat['name']}' status updated to {$statusLabel}.");
        } else {
            set_flash('danger', 'Specified category record not found.');
        }

        header('Location: ' . ADMIN_URL . '/categories.php');
        exit;
    }
}

$adminTitle      = 'Manage Categories';
$adminPageHeader = 'Master Horology Collections';
$adminPageSub    = 'Configure master categories, assign sort priority, upload banner artwork, and track assigned timepieces.';

$categories = $db->query("
    SELECT c.*, 
           COUNT(p.id) AS products_count,
           SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) AS active_products_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">Master Categories (<?= count($categories) ?>)</h4>
        <div class="small text-muted">All categories appear automatically on the customer website and navigation bar.</div>
    </div>
    <div>
        <a href="<?= ADMIN_URL ?>/category-add.php" class="btn btn-caramel">
            <i class="bi bi-folder-plus me-1"></i> Add New Category
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width:70px;">Artwork</th>
                    <th>Category Name</th>
                    <th>Slug URL</th>
                    <th>Description</th>
                    <th class="text-center" style="width:110px;">Timepieces</th>
                    <th class="text-center" style="width:90px;">Sort Order</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:130px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <img src="<?= e(BASE_URL . '/' . ltrim($cat['image_path'] ?? 'assets/images/watch-mens-chronograph.jpg', '/')) ?>" alt="" style="width:52px; height:52px; object-fit:cover; border-radius:6px; border:1px solid var(--border-beige);">
                            </td>
                            <td>
                                <div class="fw-bold" style="color:var(--text-charcoal);"><?= e($cat['name']) ?></div>
                                <small class="text-muted">ID: #<?= (int)$cat['id'] ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace"><?= e($cat['slug']) ?></span>
                            </td>
                            <td>
                                <div class="small text-muted" style="max-width:320px;"><?= e($cat['description']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge px-2 py-1" style="background:var(--caramel-dark);">
                                    <?= (int)$cat['products_count'] ?> watches
                                </span>
                            </td>
                            <td class="text-center fw-bold">
                                <?= (int)$cat['sort_order'] ?>
                            </td>
                            <!-- Status (1-Click Toggle) -->
                            <td>
                                <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="d-inline" onsubmit="event.stopPropagation();">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                    <?php if ($cat['status'] === 'active'): ?>
                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2 rounded-pill fw-semibold shadow-none border-0" 
                                                title="Status: Active. Click to switch to Inactive" style="font-size:0.75rem; min-width:82px;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Active
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-pill fw-semibold shadow-none" 
                                                title="Status: Inactive. Click to switch to Active" style="font-size:0.75rem; min-width:82px; background:#fff;">
                                            <i class="bi bi-slash-circle me-1"></i> Inactive
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <!-- 1-Click Status Toggle -->
                                <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="d-inline" onsubmit="event.stopPropagation();">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $cat['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?> p-1 px-2" 
                                            title="<?= $cat['status'] === 'active' ? 'Click to make Inactive' : 'Click to make Active' ?>">
                                        <i class="bi <?= $cat['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                    </button>
                                </form>
                                <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($cat['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-2 ms-1" title="View Collection on Storefront">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= ADMIN_URL ?>/category-edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-dark p-1 px-2 ms-1" title="Edit Category">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#delCatModal<?= $cat['id'] ?>" title="Delete Category">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="delCatModal<?= $cat['id'] ?>" tabindex="-1" aria-labelledby="delCatLabel<?= $cat['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content" style="border-color:var(--border-beige);">
                                            <div class="modal-header">
                                                <h5 class="modal-title font-serif fw-bold text-danger" id="delCatLabel<?= $cat['id'] ?>">Confirm Category Deletion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete category <strong><?= e($cat['name']) ?></strong>?</p>
                                                <?php if ($cat['products_count'] > 0): ?>
                                                    <div class="alert alert-warning small mb-0">
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                        This category currently contains <strong><?= (int)$cat['products_count'] ?> timepieces</strong>. You must reassign or remove these timepieces before this category can be removed.
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted small mb-0">This category has no assigned timepieces and can be safely deleted.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <?php if ($cat['products_count'] == 0): ?>
                                                    <form action="<?= ADMIN_URL ?>/category-delete.php" method="POST">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">Confirm Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-danger" disabled>Deletion Blocked</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No categories created yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

