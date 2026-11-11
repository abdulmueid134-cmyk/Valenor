const pool = require("../config/db");
const { AppError } = require("./checkoutModel");

const ADMIN_ROLES = ["super_admin", "manager", "order_manager", "inventory_manager"];

function pad2(n) {
  return String(n).padStart(2, "0");
}

function toSqlDateTime(d) {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
}

function startOfDay(d) {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0);
}

function endOfDay(d) {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59);
}

function resolveDateRange(rangeKey = "30d", startDateStr = "", endDateStr = "") {
  const now = new Date();
  const today0 = startOfDay(now);
  const todayEnd = endOfDay(now);

  let start;
  let end = todayEnd;
  let label = "Last 30 Days";
  let key = String(rangeKey || "30d").trim().toLowerCase();

  if (key === "today") {
    start = today0;
    label = "Today";
  } else if (key === "yesterday") {
    const y = new Date(today0);
    y.setDate(y.getDate() - 1);
    start = y;
    end = endOfDay(y);
    label = "Yesterday";
  } else if (key === "7d") {
    start = new Date(today0);
    start.setDate(start.getDate() - 6);
    label = "Last 7 Days";
  } else if (key === "30d") {
    start = new Date(today0);
    start.setDate(start.getDate() - 29);
    label = "Last 30 Days";
  } else if (key === "this_month") {
    start = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0);
    label = "This Month";
  } else if (key === "prev_month") {
    start = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0, 0);
    end = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);
    label = "Previous Month";
  } else if (key === "this_year") {
    start = new Date(now.getFullYear(), 0, 1, 0, 0, 0);
    label = "This Year";
  } else if (key === "custom") {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startDateStr) || !/^\d{4}-\d{2}-\d{2}$/.test(endDateStr)) {
      throw new AppError(400, "Please provide both valid From and To dates (YYYY-MM-DD).");
    }
    const [sy, sm, sd] = startDateStr.split("-").map(Number);
    const [ey, em, ed] = endDateStr.split("-").map(Number);
    start = new Date(sy, sm - 1, sd, 0, 0, 0);
    end = new Date(ey, em - 1, ed, 23, 59, 59);
    if (start > end) throw new AppError(400, "Start date cannot be after end date.");
    label = `${startDateStr} to ${endDateStr}`;
  } else {
    key = "30d";
    start = new Date(today0);
    start.setDate(start.getDate() - 29);
    label = "Last 30 Days";
  }

  return {
    rangeKey: key,
    label,
    startSql: toSqlDateTime(start),
    endSql: toSqlDateTime(end),
    startDate: `${start.getFullYear()}-${pad2(start.getMonth() + 1)}-${pad2(start.getDate())}`,
    endDate: `${end.getFullYear()}-${pad2(end.getMonth() + 1)}-${pad2(end.getDate())}`
  };
}

async function logAdminActivity({ userId, action, entityType = null, entityId = null, details = null, ipAddress = null }) {
  try {
    await pool.execute(
      `INSERT INTO admin_activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [userId, action, entityType, entityId ? String(entityId) : null, details, ipAddress]
    );
  } catch (err) {
    console.error("Failed to record admin activity log:", err.message);
  }
}

async function getDashboardAnalytics({ range = "30d", startDate = "", endDate = "" } = {}) {
  const window = resolveDateRange(range, startDate, endDate);
  const { startSql, endSql } = window;

  const [[settingsRow]] = await pool.query(
    "SELECT setting_value FROM settings WHERE setting_key = 'currency' LIMIT 1"
  );
  const currency = settingsRow?.setting_value || "PKR";

  // 1. Order & Revenue Aggregations (date-filtered)
  const [[ordAgg]] = await pool.query(
    `SELECT
       COUNT(*) AS total_orders,
       SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_orders,
       SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_orders,
       SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
       SUM(CASE WHEN order_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_orders,
       SUM(CASE WHEN order_status IN ('Return Requested', 'Returned', 'Refunded') THEN 1 ELSE 0 END) AS returned_orders,
       SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected') THEN 1 ELSE 0 END) AS valid_orders,
       COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected') THEN subtotal + product_discount ELSE 0 END), 0) AS gross_sales,
       COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected') THEN product_discount + coupon_discount ELSE 0 END), 0) AS discounts_given,
       COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected') THEN delivery_charge ELSE 0 END), 0) AS delivery_charges,
       COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected') THEN total_amount ELSE 0 END), 0) AS placed_revenue
     FROM orders
     WHERE created_at BETWEEN ? AND ?`,
    [startSql, endSql]
  );

  // Refunds processed on orders in selected window
  const [[refAgg]] = await pool.query(
    `SELECT COALESCE(SUM(rf.amount), 0) AS refund_amount
     FROM refunds rf
     JOIN orders o ON o.id = rf.order_id
     WHERE rf.status = 'Processed' AND o.created_at BETWEEN ? AND ?`,
    [startSql, endSql]
  );

  // Live Today's Sales using identical placed-minus-refund accounting + Global Counts
  const todayBounds = resolveDateRange("today");
  const [[todayOrd]] = await pool.query(
    `SELECT COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected') THEN total_amount ELSE 0 END), 0) AS placed_today
     FROM orders WHERE created_at BETWEEN ? AND ?`,
    [todayBounds.startSql, todayBounds.endSql]
  );
  const [[todayRef]] = await pool.query(
    `SELECT COALESCE(SUM(rf.amount), 0) AS refund_today
     FROM refunds rf JOIN orders o ON o.id = rf.order_id
     WHERE rf.status = 'Processed' AND o.created_at BETWEEN ? AND ?`,
    [todayBounds.startSql, todayBounds.endSql]
  );
  const todaySales = Math.max(0, Number(todayOrd.placed_today || 0) - Number(todayRef.refund_today || 0));

  const [[custCount]] = await pool.query(
    `SELECT COUNT(*) AS cnt
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.name = 'customer' AND u.is_active = 1`
  );

  const [[prodCount]] = await pool.query(
    `SELECT
       COUNT(p.id) AS total_products,
       SUM(CASE WHEN COALESCE(inv.available_stock, 0) <= COALESCE(inv.low_stock_threshold, 5) THEN 1 ELSE 0 END) AS low_stock_products
     FROM products p
     LEFT JOIN inventory inv ON inv.product_id = p.id
     WHERE p.is_active = 1 AND p.is_deleted = 0`
  );

  // Profit calculation STRICTLY when product cost_price is recorded (revenue != profit)
  const [[profitAgg]] = await pool.query(
    `SELECT
       COALESCE(SUM(oi.quantity), 0) AS total_units,
       COALESCE(SUM(CASE WHEN p.cost_price IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS costed_units,
       COALESCE(SUM(CASE WHEN p.cost_price IS NOT NULL
         THEN (oi.line_total * (1 - COALESCE(o.coupon_discount / NULLIF(o.subtotal, 0), 0)))
         ELSE 0 END), 0) AS costed_net_revenue,
       COALESCE(SUM(CASE WHEN p.cost_price IS NOT NULL THEN (p.cost_price * oi.quantity) ELSE 0 END), 0) AS total_cogs
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.created_at BETWEEN ? AND ?
       AND o.order_status NOT IN ('Cancelled', 'Rejected', 'Refunded')`,
    [startSql, endSql]
  );

  const totalOrders = Number(ordAgg.total_orders || 0);
  const validOrders = Number(ordAgg.valid_orders || 0);
  const grossSales = Number(ordAgg.gross_sales || 0);
  const discountsGiven = Number(ordAgg.discounts_given || 0);
  const deliveryCharges = Number(ordAgg.delivery_charges || 0);
  const refundAmount = Number(refAgg.refund_amount || 0);
  const netRevenue = Math.max(0, Number(ordAgg.placed_revenue || 0) - refundAmount);

  const costedUnits = Number(profitAgg.costed_units || 0);
  const totalUnits = Number(profitAgg.total_units || 0);
  const costedNetRevenue = Number(profitAgg.costed_net_revenue || 0);
  const totalCogs = Number(profitAgg.total_cogs || 0);
  const totalProfit = costedUnits > 0 ? Math.round(costedNetRevenue - totalCogs) : null;

  // 2. Time-Series Sales Graphs (Daily / Weekly / Monthly / Yearly)
  const [dailyRows] = await pool.query(
    `SELECT DATE_FORMAT(DATE(created_at), '%d %b') AS label,
            DATE(created_at) AS sort_key,
            COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected', 'Refunded') THEN total_amount ELSE 0 END), 0) AS revenue,
            COUNT(*) AS orders
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY sort_key ASC`,
    [startSql, endSql]
  );

  const [weeklyRows] = await pool.query(
    `SELECT CONCAT('W', DATE_FORMAT(created_at, '%v %Y')) AS label,
            YEARWEEK(created_at, 1) AS sort_key,
            COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected', 'Refunded') THEN total_amount ELSE 0 END), 0) AS revenue,
            COUNT(*) AS orders
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY YEARWEEK(created_at, 1), CONCAT('W', DATE_FORMAT(created_at, '%v %Y'))
     ORDER BY sort_key ASC`,
    [startSql, endSql]
  );

  const [monthlyRows] = await pool.query(
    `SELECT DATE_FORMAT(created_at, '%b %Y') AS label,
            DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
            COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected', 'Refunded') THEN total_amount ELSE 0 END), 0) AS revenue,
            COUNT(*) AS orders
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
     ORDER BY sort_key ASC`,
    [startSql, endSql]
  );

  const [yearlyRows] = await pool.query(
    `SELECT CAST(YEAR(created_at) AS CHAR) AS label,
            YEAR(created_at) AS sort_key,
            COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled', 'Rejected', 'Refunded') THEN total_amount ELSE 0 END), 0) AS revenue,
            COUNT(*) AS orders
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY YEAR(created_at)
     ORDER BY sort_key ASC`,
    [startSql, endSql]
  );

  // Orders Overview by Status (in selected date window)
  const [statusRows] = await pool.query(
    `SELECT order_status AS status, COUNT(*) AS count
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY order_status
     ORDER BY count DESC`,
    [startSql, endSql]
  );

  // Product Performance (Top 8 watches in date range)
  const [productPerf] = await pool.query(
    `SELECT p.id, p.name, p.sku,
            SUM(oi.quantity) AS units_sold,
            COALESCE(SUM(oi.line_total), 0) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.created_at BETWEEN ? AND ?
       AND o.order_status NOT IN ('Cancelled', 'Rejected', 'Refunded')
     GROUP BY p.id, p.name, p.sku
     ORDER BY revenue DESC, units_sold DESC
     LIMIT 8`,
    [startSql, endSql]
  );

  // Category Performance (Top 8 categories in date range)
  const [categoryPerf] = await pool.query(
    `SELECT c.id, c.name, c.slug,
            SUM(oi.quantity) AS units_sold,
            COALESCE(SUM(oi.line_total), 0) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     JOIN categories c ON c.id = p.primary_category_id
     WHERE o.created_at BETWEEN ? AND ?
       AND o.order_status NOT IN ('Cancelled', 'Rejected', 'Refunded')
     GROUP BY c.id, c.name, c.slug
     ORDER BY revenue DESC, units_sold DESC
     LIMIT 8`,
    [startSql, endSql]
  );

  // 3. Dashboard Tables (Latest 6 rows each)
  const [recentOrders] = await pool.query(
    `SELECT o.order_number, o.customer_name, o.customer_phone, o.payment_method,
            o.payment_status, o.order_status, o.total_amount, o.created_at,
            COUNT(oi.id) AS items_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.created_at BETWEEN ? AND ?
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 6`,
    [startSql, endSql]
  );

  const [latestCustomers] = await pool.query(
    `SELECT u.id, u.full_name, u.email, u.phone, u.created_at,
            COUNT(o.id) AS orders_count,
            COALESCE(SUM(CASE WHEN o.order_status NOT IN ('Cancelled','Rejected','Refunded') THEN o.total_amount ELSE 0 END), 0) AS total_spent
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN orders o ON o.user_id = u.id
     WHERE r.name = 'customer'
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT 6`
  );

  const [bestSellingProducts] = await pool.query(
    `SELECT p.id, p.name, p.sku, b.name AS brand_name, p.sale_price,
            COALESCE(SUM(oi.quantity), 0) AS units_sold,
            COALESCE(SUM(oi.line_total), 0) AS revenue,
            COALESCE(inv.available_stock, 0) AS available_stock
     FROM products p
     JOIN brands b ON b.id = p.brand_id
     LEFT JOIN inventory inv ON inv.product_id = p.id
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON o.id = oi.order_id
       AND o.created_at BETWEEN ? AND ?
       AND o.order_status NOT IN ('Cancelled', 'Rejected', 'Refunded')
     GROUP BY p.id, b.name, inv.available_stock
     ORDER BY units_sold DESC, revenue DESC, p.sales_count DESC
     LIMIT 6`,
    [startSql, endSql]
  );

  const [lowStockProducts] = await pool.query(
    `SELECT p.id, p.name, p.sku, b.name AS brand_name,
            COALESCE(inv.available_stock, 0) AS available_stock,
            COALESCE(inv.reserved_stock, 0) AS reserved_stock,
            COALESCE(inv.low_stock_threshold, 5) AS low_stock_threshold
     FROM products p
     JOIN brands b ON b.id = p.brand_id
     JOIN inventory inv ON inv.product_id = p.id
     WHERE p.is_active = 1 AND p.is_deleted = 0
       AND inv.available_stock <= inv.low_stock_threshold
     ORDER BY inv.available_stock ASC, p.id ASC
     LIMIT 6`
  );

  const [recentReturns] = await pool.query(
    `SELECT r.id, r.return_number, o.order_number, o.customer_name,
            r.reason, r.status, r.created_at
     FROM returns r
     JOIN orders o ON o.id = r.order_id
     ORDER BY r.created_at DESC
     LIMIT 6`
  );

  const [recentCancelledOrders] = await pool.query(
    `SELECT order_number, customer_name, total_amount, order_status, updated_at, created_at
     FROM orders
     WHERE order_status IN ('Cancelled', 'Rejected')
       AND created_at BETWEEN ? AND ?
     ORDER BY updated_at DESC
     LIMIT 6`,
    [startSql, endSql]
  );

  return {
    currency,
    filter: window,
    kpis: {
      totalRevenue: netRevenue,
      todaySales,
      totalOrders,
      pendingOrders: Number(ordAgg.pending_orders || 0),
      deliveredOrders: Number(ordAgg.delivered_orders || 0),
      cancelledOrders: Number(ordAgg.cancelled_orders || 0),
      rejectedOrders: Number(ordAgg.rejected_orders || 0),
      returnedOrders: Number(ordAgg.returned_orders || 0),
      totalCustomers: Number(custCount.cnt || 0),
      totalProducts: Number(prodCount.total_products || 0),
      lowStockProducts: Number(prodCount.low_stock_products || 0),
      totalProfit,
      profitMeta: {
        costedUnits,
        totalUnits,
        costedRevenue: costedNetRevenue,
        totalCogs
      }
    },
    charts: {
      dailySales: dailyRows,
      weeklySales: weeklyRows,
      monthlySales: monthlyRows,
      yearlySales: yearlyRows,
      ordersOverview: statusRows,
      revenueOverview: [
        { label: "Gross Sales", amount: grossSales },
        { label: "Discounts Given", amount: discountsGiven },
        { label: "Delivery Charges", amount: deliveryCharges },
        { label: "Refunds Processed", amount: refundAmount },
        { label: "Net Revenue", amount: netRevenue }
      ],
      productPerformance: productPerf,
      categoryPerformance: categoryPerf
    },
    businessAnalytics: {
      grossSales,
      netRevenue,
      discountsGiven,
      refundAmount,
      deliveryCharges,
      orderCount: totalOrders,
      validOrderCount: validOrders,
      averageOrderValue: validOrders > 0 ? Math.round(netRevenue / validOrders) : 0,
      returnRatePercent: totalOrders > 0 ? Number(((Number(ordAgg.returned_orders || 0) / totalOrders) * 100).toFixed(1)) : 0,
      cancellationRatePercent: totalOrders > 0 ? Number(((Number(ordAgg.cancelled_orders || 0) / totalOrders) * 100).toFixed(1)) : 0,
      totalProfit,
      costedRevenue: costedNetRevenue,
      totalCogs,
      bestSellingCategories: categoryPerf.slice(0, 5),
      bestSellingProducts: productPerf.slice(0, 5)
    },
    tables: {
      recentOrders,
      latestCustomers,
      bestSellingProducts,
      lowStockProducts,
      recentReturns,
      recentCancelledOrders
    }
  };
}

// Parameterized read-only directory queries for all 15 sidebar operational views
async function getSectionRecords(section, search = "") {
  const q = String(search || "").trim();
  const like = `%${q}%`;

  const handlers = {
    products: async () => {
      const [rows] = await pool.query(
        `SELECT p.id, p.sku, p.name, p.watch_type, p.gender, b.name AS brand, c.name AS category,
                p.original_price, p.sale_price, p.cost_price, p.discount_percent,
                COALESCE(inv.available_stock, 0) AS stock,
                p.is_featured, p.is_best_seller, p.is_new_arrival, p.is_active,
                (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS image
         FROM products p
         JOIN brands b ON b.id = p.brand_id
         JOIN categories c ON c.id = p.primary_category_id
         LEFT JOIN inventory inv ON inv.product_id = p.id
         WHERE p.is_deleted = 0
           AND (? = '' OR p.name LIKE ? OR p.sku LIKE ? OR b.name LIKE ? OR p.watch_type LIKE ? OR c.name LIKE ?)
         ORDER BY p.id ASC LIMIT 100`,
        [q, like, like, like, like, like]
      );
      return { title: "Watch Catalog", subtitle: "Add, edit, discount, upload images, and manage all timepieces", rows };
    },
    "watch-types": async () => {
      const [rows] = await pool.query(
        `SELECT wt.id, wt.name, wt.slug, wt.description, COUNT(p.id) AS products, wt.sort_order, wt.is_active
         FROM watch_types wt
         LEFT JOIN products p ON p.watch_type = wt.name AND p.is_deleted = 0
         WHERE (? = '' OR wt.name LIKE ? OR wt.slug LIKE ?)
         GROUP BY wt.id ORDER BY wt.sort_order ASC, wt.id ASC LIMIT 100`,
        [q, like, like]
      );
      return { title: "Product Types", subtitle: "Create, rename, and organize watch types (auto-syncs with catalog filters)", rows };
    },
    categories: async () => {
      const [rows] = await pool.query(
        `SELECT c.id, c.name, c.slug, c.description, COUNT(pc.product_id) AS products, c.sort_order, c.is_active
         FROM categories c
         LEFT JOIN product_categories pc ON pc.category_id = c.id
         WHERE (? = '' OR c.name LIKE ? OR c.slug LIKE ?)
         GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC LIMIT 100`,
        [q, like, like]
      );
      return { title: "Categories", subtitle: "Add, edit, reorder, and remove horology categories", rows };
    },
    brands: async () => {
      const [rows] = await pool.query(
        `SELECT b.id, b.name, b.slug, COUNT(p.id) AS products, b.is_active
         FROM brands b LEFT JOIN products p ON p.brand_id = b.id AND p.is_deleted = 0
         WHERE (? = '' OR b.name LIKE ?)
         GROUP BY b.id ORDER BY b.name ASC LIMIT 100`,
        [q, like]
      );
      return { title: "Brands", subtitle: "Add, rename, and manage watchmaker houses", rows };
    },
    orders: async () => {
      const [rows] = await pool.query(
        `SELECT o.order_number, o.customer_name, o.customer_phone, o.payment_method,
                o.payment_status, o.order_status, o.total_amount, o.created_at
         FROM orders o
         WHERE (? = '' OR o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.order_status LIKE ?)
         ORDER BY o.created_at DESC LIMIT 100`,
        [q, like, like, like, like]
      );
      return { title: "Orders", subtitle: "Live order directory (Full management actions arriving in Phase 8)", rows };
    },
    customers: async () => {
      const [rows] = await pool.query(
        `SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.created_at,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(CASE WHEN o.order_status NOT IN ('Cancelled','Rejected','Refunded') THEN o.total_amount ELSE 0 END), 0) AS lifetime_spent
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN orders o ON o.user_id = u.id
         WHERE r.name = 'customer'
           AND (? = '' OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
         GROUP BY u.id ORDER BY u.created_at DESC LIMIT 100`,
        [q, like, like, like]
      );
      return { title: "Customers", subtitle: "Registered collectors and lifetime spend", rows };
    },
    inventory: async () => {
      const [rows] = await pool.query(
        `SELECT p.id AS product_id, p.sku, p.name, inv.available_stock, inv.reserved_stock,
                inv.sold_quantity, inv.returned_quantity, inv.low_stock_threshold,
                CASE WHEN inv.available_stock <= 0 THEN 'Out of Stock'
                     WHEN inv.available_stock <= inv.low_stock_threshold THEN 'Low Stock'
                     ELSE 'Healthy' END AS stock_health
         FROM inventory inv JOIN products p ON p.id = inv.product_id
         WHERE p.is_deleted = 0 AND (? = '' OR p.name LIKE ? OR p.sku LIKE ?)
         ORDER BY inv.available_stock ASC LIMIT 100`,
        [q, like, like]
      );
      const [movements] = await pool.query(
        `SELECT im.id, p.sku, p.name AS product_name, im.change_qty, im.movement_type,
                o.order_number, im.note, u.full_name AS admin_name, im.created_at
         FROM inventory_movements im
         JOIN products p ON p.id = im.product_id
         LEFT JOIN orders o ON o.id = im.order_id
         LEFT JOIN users u ON u.id = im.updated_by_user_id
         ORDER BY im.id DESC LIMIT 30`
      );
      return { title: "Inventory Control", subtitle: "Live stock levels, configurable low-stock alerts, and movement ledger", rows, movements };
    },
    payments: async () => {
      const [rows] = await pool.query(
        `SELECT pay.id, o.order_number, pay.method, pay.amount, pay.currency,
                pay.status, pay.reference_code, pay.created_at
         FROM payments pay JOIN orders o ON o.id = pay.order_id
         WHERE (? = '' OR o.order_number LIKE ? OR pay.method LIKE ? OR pay.status LIKE ?)
         ORDER BY pay.created_at DESC LIMIT 100`,
        [q, like, like, like]
      );
      return { title: "Payments", subtitle: "Recorded order payment transactions", rows };
    },
    deliveries: async () => {
      const [rows] = await pool.query(
        `SELECT o.order_number, o.customer_name, o.shipping_city, o.delivery_method,
                o.order_status, s.courier_name, s.tracking_number, s.expected_delivery_date
         FROM orders o LEFT JOIN shipments s ON s.order_id = o.id
         WHERE (? = '' OR o.order_number LIKE ? OR s.tracking_number LIKE ? OR o.shipping_city LIKE ?)
         ORDER BY o.created_at DESC LIMIT 100`,
        [q, like, like, like]
      );
      return { title: "Deliveries & Shipments", subtitle: "Courier tracking and dispatch pipeline", rows };
    },
    returns: async () => {
      const [rows] = await pool.query(
        `SELECT r.id, r.return_number, o.order_number, o.customer_name, r.reason, r.status, r.created_at
         FROM returns r JOIN orders o ON o.id = r.order_id
         WHERE (? = '' OR r.return_number LIKE ? OR o.order_number LIKE ? OR r.status LIKE ?)
         ORDER BY r.created_at DESC LIMIT 100`,
        [q, like, like, like]
      );
      return { title: "Return Requests", subtitle: "Customer RMA queue and admin review outcomes", rows };
    },
    refunds: async () => {
      const [rows] = await pool.query(
        `SELECT rf.refund_number, o.order_number, rf.amount, rf.method, rf.status, rf.reference_code, rf.processed_at
         FROM refunds rf JOIN orders o ON o.id = rf.order_id
         WHERE (? = '' OR rf.refund_number LIKE ? OR o.order_number LIKE ?)
         ORDER BY rf.created_at DESC LIMIT 100`,
        [q, like, like]
      );
      return { title: "Refunds", subtitle: "Processed and pending customer refunds", rows };
    },
    coupons: async () => {
      const [rows] = await pool.query(
        `SELECT id, code, description, discount_type, discount_value, max_discount_amount,
                min_purchase_amount, used_count, usage_limit, expires_at, is_active
         FROM coupons
         WHERE (? = '' OR code LIKE ? OR description LIKE ?)
         ORDER BY id ASC LIMIT 100`,
        [q, like, like]
      );
      return { title: "Coupons", subtitle: "Create, edit, and toggle promotional discount codes", rows };
    },
    reviews: async () => {
      const [rows] = await pool.query(
        `SELECT rv.id, p.name AS product, rv.author_name, rv.city, rv.rating, rv.comment, rv.is_approved, rv.created_at
         FROM reviews rv JOIN products p ON p.id = rv.product_id
         WHERE (? = '' OR p.name LIKE ? OR rv.author_name LIKE ?)
         ORDER BY rv.created_at DESC LIMIT 100`,
        [q, like, like]
      );
      return { title: "Customer Reviews", subtitle: "Verified buyer ratings and feedback", rows };
    },
    reports: async () => {
      const [rows] = await pool.query(
        `SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS date,
                COUNT(*) AS orders,
                SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled','Rejected','Refunded') THEN total_amount ELSE 0 END), 0) AS net_sales
         FROM orders
         GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
         ORDER BY date DESC LIMIT 60`
      );
      return { title: "Reports Summary", subtitle: "Daily ledger preview (Downloadable CSV/PDF exports scheduled in Phase 9)", rows };
    },
    settings: async () => {
      const [rows] = await pool.query(
        `SELECT setting_key, setting_value, updated_at FROM settings ORDER BY setting_key ASC`
      );
      return { title: "Website Settings", subtitle: "Configured store settings (Interactive editing scheduled in Phase 9)", rows };
    },
    "admin-users": async () => {
      const [rows] = await pool.query(
        `SELECT u.id, u.full_name, u.email, u.phone, r.name AS role, u.is_active, u.created_at
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.name IN ('super_admin', 'manager', 'order_manager', 'inventory_manager')
         ORDER BY u.id ASC`
      );
      return { title: "Admin Users & Staff", subtitle: "Role-protected administrative accounts", rows };
    }
  };

  const handler = handlers[section];
  if (!handler) throw new AppError(404, `Unknown admin section "${section}".`);
  return handler();
}

module.exports = {
  ADMIN_ROLES,
  resolveDateRange,
  logAdminActivity,
  getDashboardAnalytics,
  getSectionRecords
};
