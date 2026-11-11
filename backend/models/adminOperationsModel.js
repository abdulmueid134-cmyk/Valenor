const pool = require("../config/db");
const { AppError, getOrderByNumber } = require("./checkoutModel");
const { logAdminActivity } = require("./adminDashboardModel");

const ORDER_SORT_SQL = {
  newest: "o.created_at DESC, o.id DESC",
  oldest: "o.created_at ASC, o.id ASC",
  total_desc: "o.total_amount DESC, o.id DESC",
  total_asc: "o.total_amount ASC, o.id ASC"
};

const CUSTOMER_SORT_SQL = {
  newest: "u.created_at DESC, u.id DESC",
  spent_desc: "total_spent DESC, order_count DESC",
  orders_desc: "order_count DESC, total_spent DESC"
};

async function listOrdersAdmin({
  q = "",
  status = "",
  dateFrom = "",
  dateTo = "",
  sort = "newest",
  page = 1,
  limit = 15
} = {}) {
  const where = ["1 = 1"];
  const params = [];

  const cleanQ = String(q || "").trim();
  if (cleanQ) {
    const like = `%${cleanQ}%`;
    where.push(`(
      o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?
      OR EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND (oi2.product_name LIKE ? OR oi2.sku LIKE ?))
    )`);
    params.push(like, like, like, like, like, like);
  }
  if (status && status !== "all") {
    where.push("o.order_status = ?");
    params.push(status);
  }
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateFrom)) {
    where.push("o.created_at >= ?");
    params.push(`${dateFrom} 00:00:00`);
  }
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateTo)) {
    where.push("o.created_at <= ?");
    params.push(`${dateTo} 23:59:59`);
  }

  const pageNum = Math.max(1, Number(page || 1));
  const lim = Math.min(100, Math.max(1, Number(limit || 15)));
  const offset = (pageNum - 1) * lim;
  const orderSql = ORDER_SORT_SQL[sort] || ORDER_SORT_SQL.newest;

  const [[countRow]] = await pool.query(`SELECT COUNT(*) AS total FROM orders o WHERE ${where.join(" AND ")}`, params);

  const [rows] = await pool.query(
    `SELECT o.id, o.order_number, o.user_id, o.customer_name, o.customer_phone, o.customer_email,
            o.shipping_city, o.delivery_method, o.payment_method, o.payment_status, o.order_status,
            o.total_amount, o.created_at,
            s.courier_name, s.tracking_number, s.expected_delivery_date, s.delivered_at,
            GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') AS products_summary,
            SUM(oi.quantity) AS total_units
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN shipments s ON s.order_id = o.id
     WHERE ${where.join(" AND ")}
     GROUP BY o.id, s.courier_name, s.tracking_number, s.expected_delivery_date, s.delivered_at
     ORDER BY ${orderSql}
     LIMIT ? OFFSET ?`,
    [...params, lim, offset]
  );

  const orders = rows.map((r) => {
    let deliveryStatus = "Awaiting Dispatch";
    if (["Cancelled", "Rejected"].includes(r.order_status)) deliveryStatus = r.order_status;
    else if (r.delivered_at || ["Delivered", "Return Requested", "Returned", "Refunded"].includes(r.order_status)) deliveryStatus = "Delivered";
    else if (["Shipped", "Out for Delivery"].includes(r.order_status)) deliveryStatus = r.order_status;
    else if (r.order_status === "Packed") deliveryStatus = "Ready to Ship";
    return { ...r, total_amount: Number(r.total_amount), delivery_status: deliveryStatus };
  });

  return {
    orders,
    total: Number(countRow.total || 0),
    page: pageNum,
    limit: lim,
    totalPages: Math.max(1, Math.ceil(Number(countRow.total || 0) / lim))
  };
}

async function getOrderDetailAdmin(orderNumber) {
  const order = await getOrderByNumber(orderNumber);
  if (!order) throw new AppError(404, "Order not found.");

  // Enrich status history with the admin/user name and role who performed each change
  const [history] = await pool.query(
    `SELECT osh.id, osh.previous_status, osh.new_status, osh.reason, osh.created_at,
            osh.changed_by_user_id, u.full_name AS changed_by_name, r.name AS changed_by_role
     FROM order_status_history osh
     LEFT JOIN users u ON u.id = osh.changed_by_user_id
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE osh.order_id = ?
     ORDER BY osh.id ASC`,
    [order.id]
  );

  let customerAccount = null;
  if (order.user_id) {
    const [[u]] = await pool.query(
      "SELECT id, full_name, email, phone, is_active FROM users WHERE id = ? LIMIT 1",
      [order.user_id]
    );
    customerAccount = u || null;
  }

  return { ...order, history, customerAccount };
}

async function updateOrderAdminMeta(orderNumber, { notes, paymentStatus, courierName, trackingNumber, expectedDeliveryDate }, adminUserId) {
  const validPayStatuses = ["Pending", "Paid", "Failed", "Refunded"];
  if (paymentStatus && !validPayStatuses.includes(paymentStatus)) {
    throw new AppError(400, "Invalid payment status.");
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[order]] = await conn.query("SELECT * FROM orders WHERE order_number = ? LIMIT 1 FOR UPDATE", [orderNumber]);
    if (!order) throw new AppError(404, "Order not found.");

    if (notes !== undefined || paymentStatus) {
      await conn.execute(
        `UPDATE orders
         SET notes = COALESCE(?, notes),
             payment_status = COALESCE(?, payment_status)
         WHERE id = ?`,
        [notes !== undefined ? String(notes).trim() : null, paymentStatus || null, order.id]
      );
      if (paymentStatus) {
        await conn.execute("UPDATE payments SET status = ? WHERE order_id = ?", [paymentStatus, order.id]);
      }
    }

    if (courierName !== undefined || trackingNumber !== undefined || expectedDeliveryDate !== undefined) {
      await conn.execute(
        `INSERT INTO shipments (order_id, courier_name, tracking_number, expected_delivery_date)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           courier_name = COALESCE(NULLIF(VALUES(courier_name), ''), courier_name),
           tracking_number = COALESCE(NULLIF(VALUES(tracking_number), ''), tracking_number),
           expected_delivery_date = COALESCE(VALUES(expected_delivery_date), expected_delivery_date)`,
        [
          order.id,
          courierName || "VALENOR Insured Courier",
          trackingNumber || null,
          expectedDeliveryDate || null
        ]
      );
    }

    await conn.commit();
    await logAdminActivity({
      userId: adminUserId,
      action: "order.meta_update",
      entityType: "order",
      entityId: orderNumber,
      details: `tracking=${trackingNumber || "-"}, pay=${paymentStatus || "-"}`
    });
    return getOrderDetailAdmin(orderNumber);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function listCustomersAdmin({ q = "", status = "all", sort = "newest", page = 1, limit = 15 } = {}) {
  const where = ["r.name = 'customer'"];
  const params = [];

  const cleanQ = String(q || "").trim();
  if (cleanQ) {
    const like = `%${cleanQ}%`;
    where.push("(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)");
    params.push(like, like, like);
  }
  if (status === "active") where.push("u.is_active = 1");
  else if (status === "blocked") where.push("u.is_active = 0");

  const pageNum = Math.max(1, Number(page || 1));
  const lim = Math.min(100, Math.max(1, Number(limit || 15)));
  const offset = (pageNum - 1) * lim;
  const sortSql = CUSTOMER_SORT_SQL[sort] || CUSTOMER_SORT_SQL.newest;

  const [[countRow]] = await pool.query(
    `SELECT COUNT(*) AS total FROM users u JOIN roles r ON r.id = u.role_id WHERE ${where.join(" AND ")}`,
    params
  );

  const [rows] = await pool.query(
    `SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.preferred_currency, u.created_at,
            COUNT(DISTINCT o.id) AS order_count,
            COUNT(DISTINCT ret.id) AS return_count,
            COALESCE(SUM(CASE WHEN o.order_status NOT IN ('Cancelled','Rejected','Refunded') THEN o.total_amount ELSE 0 END), 0) AS total_spent
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN orders o ON o.user_id = u.id
     LEFT JOIN returns ret ON ret.user_id = u.id
     WHERE ${where.join(" AND ")}
     GROUP BY u.id
     ORDER BY ${sortSql}
     LIMIT ? OFFSET ?`,
    [...params, lim, offset]
  );

  return {
    customers: rows.map((c) => ({ ...c, total_spent: Number(c.total_spent) })),
    total: Number(countRow.total || 0),
    page: pageNum,
    limit: lim,
    totalPages: Math.max(1, Math.ceil(Number(countRow.total || 0) / lim))
  };
}

async function getCustomerDetailAdmin(userId) {
  const uid = Number(userId);
  const [[customer]] = await pool.query(
    `SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.newsletter_opt_in, u.preferred_currency, u.created_at,
            r.name AS role
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.id = ? AND r.name = 'customer' LIMIT 1`,
    [uid]
  );
  if (!customer) throw new AppError(404, "Customer not found.");

  const [addresses] = await pool.query(
    "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC",
    [uid]
  );

  const [orders] = await pool.query(
    `SELECT o.order_number, o.order_status, o.payment_method, o.payment_status, o.total_amount, o.created_at,
            COUNT(oi.id) AS item_count
     FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ?
     GROUP BY o.id ORDER BY o.created_at DESC`,
    [uid]
  );

  const [returns] = await pool.query(
    `SELECT r.*, o.order_number, oi.product_name, ri.quantity AS return_qty
     FROM returns r
     JOIN orders o ON o.id = r.order_id
     LEFT JOIN return_items ri ON ri.return_id = r.id
     LEFT JOIN order_items oi ON oi.id = ri.order_item_id
     WHERE r.user_id = ? OR o.user_id = ?
     ORDER BY r.created_at DESC`,
    [uid, uid]
  );

  const stats = {
    totalOrders: orders.length,
    deliveredOrders: orders.filter((o) => o.order_status === "Delivered").length,
    cancelledOrders: orders.filter((o) => ["Cancelled", "Rejected"].includes(o.order_status)).length,
    totalReturns: returns.length,
    totalSpent: orders
      .filter((o) => !["Cancelled", "Rejected", "Refunded"].includes(o.order_status))
      .reduce((acc, o) => acc + Number(o.total_amount), 0)
  };

  return {
    customer,
    stats,
    addresses,
    orders: orders.map((o) => ({ ...o, total_amount: Number(o.total_amount) })),
    returns: returns.map((r) => ({ ...r, supporting_photos: r.supporting_photos ? JSON.parse(r.supporting_photos) : [] }))
  };
}

async function setCustomerActiveStatus(userId, isActive, reason = "", adminUserId) {
  const uid = Number(userId);
  const [[target]] = await pool.query(
    `SELECT u.id, u.full_name, u.email, r.name AS role
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.id = ? LIMIT 1`,
    [uid]
  );
  if (!target) throw new AppError(404, "Customer account not found.");
  if (target.role !== "customer") {
    throw new AppError(403, "Only ordinary customer accounts can be toggled from Customer Management.");
  }

  const nextVal = isActive ? 1 : 0;
  await pool.execute("UPDATE users SET is_active = ? WHERE id = ?", [nextVal, uid]);

  await logAdminActivity({
    userId: adminUserId,
    action: nextVal ? "customer.activate" : "customer.block",
    entityType: "user",
    entityId: uid,
    details: `${target.email}${reason ? `: ${reason}` : ""}`
  });

  return getCustomerDetailAdmin(uid);
}

module.exports = {
  listOrdersAdmin,
  getOrderDetailAdmin,
  updateOrderAdminMeta,
  listCustomersAdmin,
  getCustomerDetailAdmin,
  setCustomerActiveStatus
};

