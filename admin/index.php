<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN DASHBOARD (Section 1 & 2)
// Displays the 9 required business metrics, recent orders, and low-stock alerts
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Dashboard Overview';
$adminPageHeader = 'Horological Operations Dashboard';
$adminPageSub    = 'Real-time sales, order lifecycle statuses, and inventory oversight.';

// 1. Total Products
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$activeProducts = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

// 2. Total Categories
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM categories WHERE status = 'active'")->fetchColumn();

// 3. Total Customers
$totalCustomers = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();

// 4. Total Orders
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// 5. Pending Orders
$pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();

// 6. Dispatched Orders (Dispatched, Shipped, Out for Delivery)
$dispatchedOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Dispatched', 'Shipped / In Transit', 'Out for Delivery')")->fetchColumn();

// 7. Delivered Orders
$deliveredOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Delivered'")->fetchColumn();

// 8. Return Requests
$returnRequests = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Return Requested', 'Return Approved')")->fetchColumn();

// 9. Total Sales
$totalSales = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status NOT IN ('Cancelled')")->fetchColumn();

// Fetch Recent Orders (Up to 6)
$recentOrders = $db->query("
    SELECT o.*
    FROM orders o
    ORDER BY o.id DESC
    LIMIT 6
")->fetchAll();

// Fetch Low Stock Products (< 5 units)
$lowStockProducts = $db->query("
    SELECT p.*, c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity <= 5 AND p.status = 'active'
    ORDER BY p.stock_quantity ASC
    LIMIT 5
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- ======================================================================
     THE 9 REQUIRED METRICS CARDS (Section 1 & 2)
     ====================================================================== -->
<div class="row g-3 mb-4">
    
    <!-- 1. Total Products -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Products</div>
                    <h3 class="stat-value"><?= $totalProducts ?></h3>
                    <div class="small text-muted mt-1"><?= $activeProducts ?> active in catalogue</div>
                </div>
                <div class="stat-icon"><i class="bi bi-watch"></i></div>
            </div>
            <a href="<?= ADMIN_URL ?>/products.php" class="stretched-link"></a>
        </div>
    </div>

    <!-- 2. Total Categories -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Categories</div>
                    <h3 class="stat-value"><?= $totalCategories ?></h3>
                    <div class="small text-muted mt-1">Master collections</div>
                </div>
                <div class="stat-icon"><i class="bi bi-grid"></i></div>
            </div>
            <a href="<?= ADMIN_URL ?>/categories.php" class="stretched-link"></a>
        </div>
    </div>

    <!-- 3. Total Customers -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Customers</div>
                    <h3 class="stat-value"><?= $totalCustomers ?></h3>
                    <div class="small text-muted mt-1">Registered accounts</div>
                </div>
                <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>

    <!-- 4. Total Orders -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Orders</div>
                    <h3 class="stat-value"><?= $totalOrders ?></h3>
                    <div class="small text-muted mt-1">All time bookings</div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
        </div>
    </div>

    <!-- 5. Pending Orders -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-warning fw-bold">Pending Orders</div>
                    <h3 class="stat-value text-warning"><?= $pendingOrders ?></h3>
                    <div class="small text-muted mt-1">Awaiting dispatch</div>
                </div>
                <div class="stat-icon text-warning bg-warning-subtle"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
    </div>

    <!-- 6. Dispatched Orders -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-info fw-bold">Dispatched Orders</div>
                    <h3 class="stat-value text-info"><?= $dispatchedOrders ?></h3>
                    <div class="small text-muted mt-1">With courier / transit</div>
                </div>
                <div class="stat-icon text-info bg-info-subtle"><i class="bi bi-truck"></i></div>
            </div>
        </div>
    </div>

    <!-- 7. Delivered Orders -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-success fw-bold">Delivered Orders</div>
                    <h3 class="stat-value text-success"><?= $deliveredOrders ?></h3>
                    <div class="small text-muted mt-1">Successfully fulfilled</div>
                </div>
                <div class="stat-icon text-success bg-success-subtle"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- 8. Return Requests -->
    <div class="col-sm-6 col-xl-4 col-xxl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-danger fw-bold">Return Requests</div>
                    <h3 class="stat-value text-danger"><?= $returnRequests ?></h3>
                    <div class="small text-muted mt-1">Inspection requests</div>
                </div>
                <div class="stat-icon text-danger bg-danger-subtle"><i class="bi bi-arrow-counterclockwise"></i></div>
            </div>
        </div>
    </div>

    <!-- 9. Total Sales Revenue -->
    <div class="col-12 col-xl-6 col-xxl-6">
        <div class="stat-card" style="background:linear-gradient(135deg, #231f1c 0%, #171513 100%); color:#fff; border-color:#38322c;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-uppercase" style="color:var(--caramel-primary);">Gross Sales Revenue</div>
                    <h2 class="stat-value text-white display-6 my-1"><?= format_price($totalSales) ?></h2>
                    <div class="small text-white-50">Across all successful and in-transit Cash on Delivery bookings</div>
                </div>
                <div class="stat-icon text-white" style="background:rgba(184,137,91,0.25);"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts Card -->
    <div class="col-12 col-xl-6 col-xxl-6">
        <div class="stat-card d-flex flex-column justify-content-between">
            <div class="stat-title mb-2">Concierge Quick Actions</div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= ADMIN_URL ?>/product-add.php" class="btn btn-sm btn-caramel">
                    <i class="bi bi-plus-circle me-1"></i> Add New Timepiece
                </a>
                <a href="<?= ADMIN_URL ?>/category-add.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                    <i class="bi bi-folder-plus me-1"></i> Add Category
                </a>
                <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                    <i class="bi bi-list-check me-1"></i> View All Products
                </a>
                <a href="<?= BASE_URL ?>/index.php" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-globe me-1"></i> Preview Storefront
                </a>
            </div>
        </div>
    </div>

</div>

<!-- ======================================================================
     RECENT ORDERS & LOW STOCK ALERTS
     ====================================================================== -->
<div class="row g-4">
    
    <!-- Recent Orders Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm admin-table">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center" style="border-color:var(--border-beige) !important;">
                <h6 class="mb-0 font-serif fw-bold" style="color:var(--text-charcoal);"><i class="bi bi-receipt me-1"></i> Recent Client Orders</h6>
                <span class="badge bg-light text-dark border"><?= count($recentOrders) ?> recent</span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $ord): ?>
                                <tr>
                                    <td><strong class="font-monospace" style="color:var(--caramel-dark);"><?= e($ord['order_number']) ?></strong></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($ord['customer_name']) ?></div>
                                        <small class="text-muted"><?= e($ord['customer_email']) ?></small>
                                    </td>
                                    <td class="fw-bold"><?= format_price($ord['total_amount']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= strtoupper($ord['payment_method']) ?></span></td>
                                    <td><?= get_order_status_badge($ord['order_status']) ?></td>
                                    <td class="small text-muted"><?= format_date($ord['created_at'], 'M d, Y') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No client orders placed yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Inventory Alerts (< 5 units) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm admin-table">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center" style="border-color:var(--border-beige) !important;">
                <h6 class="mb-0 font-serif fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Low Stock Alerts</h6>
                <span class="badge bg-danger"><?= count($lowStockProducts) ?> alerts</span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($lowStockProducts)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($lowStockProducts as $lp): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="<?= e(BASE_URL . '/' . ltrim($lp['primary_image'] ?? 'assets/images/watch-mens-chronograph.jpg', '/')) ?>" alt="" style="width:42px; height:42px; object-fit:cover; border-radius:6px; border:1px solid var(--border-beige);">
                                    <div class="text-truncate">
                                        <div class="small fw-bold text-truncate"><?= e($lp['name']) ?></div>
                                        <div class="small text-muted">SKU: <?= e($lp['sku']) ?></div>
                                    </div>
                                </div>
                                <div class="text-end ps-2">
                                    <span class="badge bg-danger-subtle text-danger fw-bold"><?= (int)$lp['stock_quantity'] ?> left</span>
                                    <div class="mt-1">
                                        <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $lp['id'] ?>" class="btn btn-sm btn-outline-dark py-0 px-2" style="font-size:0.75rem;">Restock</a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-check-circle text-success fs-3 d-block mb-1"></i>
                        All active timepieces have sufficient stock levels (&gt; 5 units).
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
