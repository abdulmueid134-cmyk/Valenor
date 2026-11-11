const crypto = require("crypto");
const pool = require("../config/db");
const { AppError, getOrderByNumber } = require("./checkoutModel");
const { logAdminActivity } = require("./adminDashboardModel");

const CUSTOMER_CANCELLABLE = ["Pending", "Confirmed", "Processing"];
const ALL_ORDER_STATUSES = [
  "Pending", "Confirmed", "Processing", "Packed", "Shipped",
  "Out for Delivery", "Delivered", "Cancelled", "Rejected",
  "Return Requested", "Returned", "Refunded"
];

function verifyOrderOwnership(order, user, contact = "") {
  if (user && order.user_id && order.user_id === user.id) return true;
  if (user && (order.customer_email || "").toLowerCase() === (user.email || "").toLowerCase()) return true;
  const cleanContact = String(contact || "").trim().toLowerCase();
  const normPhone = (s) => String(s || "").replace(/[\s-]/g, "");
  if (cleanContact) {
    if (cleanContact === (order.customer_email || "").toLowerCase()) return true;
    if (normPhone(cleanContact) && normPhone(cleanContact) === normPhone(order.customer_phone)) return true;
  }
  return false;
}

async function cancelOrderByCustomer({ orderNumber, user = null, contact = "", reason = "Cancelled by customer" }) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [[order]] = await conn.query("SELECT * FROM orders WHERE order_number = ? LIMIT 1 FOR UPDATE", [orderNumber]);
    if (!order) throw new AppError(404, "Order not found.");

    if (!verifyOrderOwnership(order, user, contact)) {
      throw new AppError(403, "Please verify with the order email/phone or sign in as the order owner to cancel.");
    }
    if (!CUSTOMER_CANCELLABLE.includes(order.order_status)) {
      throw new AppError(
        409,
        `Order cannot be cancelled once status is "${order.order_status}". You can request a return after delivery.`
      );
    }

    const [items] = await conn.query("SELECT * FROM order_items WHERE order_id = ?", [order.id]);
    for (const item of items) {
      await conn.execute(
        `UPDATE inventory
         SET available_stock = available_stock + ?,
             reserved_stock = GREATEST(0, reserved_stock - ?)
         WHERE product_id = ?`,
        [item.quantity, item.quantity, item.product_id]
      );
      await conn.execute(
        `INSERT INTO inventory_movements (product_id, order_id, change_qty, movement_type, note)
         VALUES (?, ?, ?, 'cancel_release', ?)`,
        [item.product_id, order.id, item.quantity, `Customer cancelled ${order.order_number}`]
      );
    }

    await conn.execute("UPDATE orders SET order_status = 'Cancelled' WHERE id = ?", [order.id]);
    await conn.execute(
      `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
       VALUES (?, ?, 'Cancelled', ?, ?)`,
      [order.id, order.order_status, user?.id || null, String(reason || "Cancelled by customer").slice(0, 500)]
    );

    await conn.commit();
    return getOrderByNumber(orderNumber);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function createReturnRequest({
  orderNumber,
  orderItemId,
  quantity,
  reason,
  details = "",
  photoPaths = [],
  user = null,
  contact = ""
}) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [[order]] = await conn.query("SELECT * FROM orders WHERE order_number = ? LIMIT 1 FOR UPDATE", [orderNumber]);
    if (!order) throw new AppError(404, "Order not found.");
    if (!verifyOrderOwnership(order, user, contact)) {
      throw new AppError(403, "Please verify with your order email/phone or sign in to submit a return.");
    }
    if (!["Delivered", "Return Requested"].includes(order.order_status)) {
      throw new AppError(409, `Returns are available only for Delivered orders (current status: ${order.order_status}).`);
    }

    const [[item]] = await conn.query(
      "SELECT * FROM order_items WHERE id = ? AND order_id = ? LIMIT 1",
      [Number(orderItemId), order.id]
    );
    if (!item) throw new AppError(404, "Selected watch item not found in this order.");

    const reqQty = Math.max(1, Math.floor(Number(quantity || 1)));
    if (reqQty > item.quantity) {
      throw new AppError(400, `Return quantity (${reqQty}) exceeds ordered quantity (${item.quantity}).`);
    }

    const [[openRet]] = await conn.query(
      `SELECT r.id FROM returns r
       JOIN return_items ri ON ri.return_id = r.id
       WHERE ri.order_item_id = ? AND r.status IN ('Pending', 'Approved', 'Returned', 'Refunded')
       LIMIT 1`,
      [item.id]
    );
    if (openRet) {
      throw new AppError(409, "A return request already exists for this item.");
    }

    const returnNumber = `RET-${new Date().getFullYear()}-${crypto.randomInt(100000, 999999)}`;
    const [retRes] = await conn.execute(
      `INSERT INTO returns (return_number, order_id, user_id, reason, details, supporting_photos, status)
       VALUES (?, ?, ?, ?, ?, ?, 'Pending')`,
      [
        returnNumber,
        order.id,
        user?.id || order.user_id || null,
        String(reason || "").trim(),
        String(details || "").trim(),
        JSON.stringify(photoPaths)
      ]
    );
    const returnId = retRes.insertId;

    await conn.execute(
      `INSERT INTO return_items (return_id, order_item_id, product_id, quantity, condition_note)
       VALUES (?, ?, ?, ?, ?)`,
      [returnId, item.id, item.product_id, reqQty, String(details || "").slice(0, 250)]
    );

    if (order.order_status !== "Return Requested") {
      await conn.execute("UPDATE orders SET order_status = 'Return Requested' WHERE id = ?", [order.id]);
      await conn.execute(
        `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
         VALUES (?, ?, 'Return Requested', ?, ?)`,
        [order.id, order.order_status, user?.id || null, `Return ${returnNumber}: ${reason}`]
      );
    }

    await conn.commit();
    return getOrderByNumber(orderNumber);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function listReturnsByUser(userId) {
  const [rows] = await pool.query(
    `SELECT r.*, o.order_number, ri.quantity AS return_qty, oi.product_name, oi.variant_name,
            ref.refund_number, ref.amount AS refund_amount, ref.status AS refund_status
     FROM returns r
     JOIN orders o ON o.id = r.order_id
     LEFT JOIN return_items ri ON ri.return_id = r.id
     LEFT JOIN order_items oi ON oi.id = ri.order_item_id
     LEFT JOIN refunds ref ON ref.return_id = r.id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC`,
    [userId]
  );
  return rows.map((r) => ({
    ...r,
    supporting_photos: r.supporting_photos ? JSON.parse(r.supporting_photos) : []
  }));
}

// Strict RBAC Administrative Lifecycle Transitions
async function adminUpdateOrderStatus({
  orderNumber,
  newStatus,
  adminUserId,
  reason = "",
  courierName = "",
  trackingNumber = "",
  expectedDeliveryDate = null
}) {
  if (!ALL_ORDER_STATUSES.includes(newStatus)) {
    throw new AppError(400, `Invalid order status: ${newStatus}`);
  }
  if (["Returned", "Refunded"].includes(newStatus)) {
    throw new AppError(400, "Use return/refund approval actions to mark an order Returned or Refunded.");
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [[order]] = await conn.query("SELECT * FROM orders WHERE order_number = ? LIMIT 1 FOR UPDATE", [orderNumber]);
    if (!order) throw new AppError(404, "Order not found.");

    const prevStatus = order.order_status;
    if (prevStatus === newStatus) {
      await conn.commit();
      return getOrderByNumber(orderNumber);
    }

    if (["Cancelled", "Rejected"].includes(newStatus)) {
      const adminCancelAllowed = ["Pending", "Confirmed", "Processing", "Packed", "Shipped", "Out for Delivery"];
      if (!adminCancelAllowed.includes(prevStatus)) {
        throw new AppError(409, `Cannot mark order as "${newStatus}" from current status "${prevStatus}". Use return/refund controls after delivery.`);
      }
      if (!String(reason || "").trim()) {
        throw new AppError(400, `Please provide a reason when marking an order as ${newStatus}.`);
      }
    }

    const [items] = await conn.query("SELECT * FROM order_items WHERE order_id = ?", [order.id]);

    // Inventory state synchronization when cancelled/rejected vs delivered
    if (["Cancelled", "Rejected"].includes(newStatus) && !["Cancelled", "Rejected"].includes(prevStatus)) {
      for (const item of items) {
        await conn.execute(
          `UPDATE inventory
           SET available_stock = available_stock + ?,
               reserved_stock = GREATEST(0, reserved_stock - ?)
           WHERE product_id = ?`,
          [item.quantity, item.quantity, item.product_id]
        );
        await conn.execute(
          `INSERT INTO inventory_movements (product_id, order_id, change_qty, movement_type, note)
           VALUES (?, ?, ?, 'cancel_release', ?)`,
          [item.product_id, order.id, item.quantity, `Admin marked ${newStatus}: ${reason}`]
        );
      }
    } else if (newStatus === "Delivered" && prevStatus !== "Delivered") {
      for (const item of items) {
        await conn.execute(
          `UPDATE inventory
           SET reserved_stock = GREATEST(0, reserved_stock - ?),
               sold_quantity = sold_quantity + ?
           WHERE product_id = ?`,
          [item.quantity, item.quantity, item.product_id]
        );
        await conn.execute("UPDATE products SET sales_count = sales_count + ? WHERE id = ?", [
          item.quantity,
          item.product_id
        ]);
      }
      if (order.payment_method === "cod") {
        await conn.execute("UPDATE orders SET payment_status = 'Paid' WHERE id = ?", [order.id]);
        await conn.execute("UPDATE payments SET status = 'Paid' WHERE order_id = ?", [order.id]);
      }
    }

    if (trackingNumber || expectedDeliveryDate || newStatus === "Shipped" || newStatus === "Delivered") {
      await conn.execute(
        `INSERT INTO shipments (order_id, courier_name, tracking_number, shipped_at, expected_delivery_date, delivered_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           courier_name = COALESCE(NULLIF(VALUES(courier_name), ''), courier_name),
           tracking_number = COALESCE(NULLIF(VALUES(tracking_number), ''), tracking_number),
           shipped_at = COALESCE(shipped_at, VALUES(shipped_at)),
           expected_delivery_date = COALESCE(VALUES(expected_delivery_date), expected_delivery_date),
           delivered_at = COALESCE(delivered_at, VALUES(delivered_at))`,
        [
          order.id,
          courierName || "VALENOR Insured Courier",
          trackingNumber || null,
          newStatus === "Shipped" ? new Date() : null,
          expectedDeliveryDate || null,
          newStatus === "Delivered" ? new Date() : null
        ]
      );
    }

    await conn.execute("UPDATE orders SET order_status = ? WHERE id = ?", [newStatus, order.id]);
    await conn.execute(
      `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
       VALUES (?, ?, ?, ?, ?)`,
      [order.id, prevStatus, newStatus, adminUserId, reason || `Updated to ${newStatus}`]
    );

    await conn.commit();
    await logAdminActivity({
      userId: adminUserId,
      action: `order.status.${newStatus.toLowerCase().replace(/\s+/g, "_")}`,
      entityType: "order",
      entityId: orderNumber,
      details: `${prevStatus} -> ${newStatus}${reason ? `: ${reason}` : ""}`
    });
    return getOrderByNumber(orderNumber);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function adminDecideReturn({ returnId, decision, adminUserId, adminNote = "" }) {
  if (!["approve", "reject", "mark_returned"].includes(decision)) {
    throw new AppError(400, "Decision must be approve, reject, or mark_returned.");
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [[ret]] = await conn.query("SELECT * FROM returns WHERE id = ? LIMIT 1 FOR UPDATE", [Number(returnId)]);
    if (!ret) throw new AppError(404, "Return request not found.");

    const [[order]] = await conn.query("SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE", [ret.order_id]);

    if (decision === "approve") {
      if (ret.status !== "Pending") throw new AppError(409, "Only Pending return requests can be approved.");
      await conn.execute(
        "UPDATE returns SET status = 'Approved', admin_note = ?, reviewed_by_user_id = ?, reviewed_at = NOW() WHERE id = ?",
        [adminNote, adminUserId, ret.id]
      );
    } else if (decision === "reject") {
      if (!["Pending", "Approved"].includes(ret.status)) {
        throw new AppError(409, "This return request can no longer be rejected.");
      }
      await conn.execute(
        "UPDATE returns SET status = 'Rejected', admin_note = ?, reviewed_by_user_id = ?, reviewed_at = NOW() WHERE id = ?",
        [adminNote, adminUserId, ret.id]
      );
      await conn.execute("UPDATE orders SET order_status = 'Delivered' WHERE id = ?", [order.id]);
      await conn.execute(
        `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
         VALUES (?, ?, 'Delivered', ?, ?)`,
        [order.id, order.order_status, adminUserId, `Return ${ret.return_number} rejected: ${adminNote}`]
      );
    } else if (decision === "mark_returned") {
      if (ret.status !== "Approved") {
        throw new AppError(409, "Return must be Approved by an administrator before marking Returned.");
      }
      const [retItems] = await conn.query("SELECT * FROM return_items WHERE return_id = ?", [ret.id]);
      for (const ri of retItems) {
        await conn.execute(
          `UPDATE inventory
           SET available_stock = available_stock + ?,
               returned_quantity = returned_quantity + ?
           WHERE product_id = ?`,
          [ri.quantity, ri.quantity, ri.product_id]
        );
        await conn.execute(
          `INSERT INTO inventory_movements (product_id, order_id, change_qty, movement_type, note)
           VALUES (?, ?, ?, 'return_restock', ?)`,
          [ri.product_id, order.id, ri.quantity, `Return ${ret.return_number} received`]
        );
      }
      await conn.execute("UPDATE returns SET status = 'Returned', admin_note = COALESCE(NULLIF(?, ''), admin_note) WHERE id = ?", [
        adminNote,
        ret.id
      ]);
      await conn.execute("UPDATE orders SET order_status = 'Returned' WHERE id = ?", [order.id]);
      await conn.execute(
        `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
         VALUES (?, ?, 'Returned', ?, ?)`,
        [order.id, order.order_status, adminUserId, `Return ${ret.return_number} received & restocked`]
      );
    }

    await conn.commit();
    await logAdminActivity({
      userId: adminUserId,
      action: `return.${decision}`,
      entityType: "return",
      entityId: ret.return_number,
      details: `Order ${order.order_number}: ${adminNote || decision}`
    });
    return getOrderByNumber(order.order_number);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function adminProcessRefund({
  returnId,
  adminUserId,
  decision = "approve",
  amount = null,
  method = "bank_transfer",
  referenceCode = "",
  notes = ""
}) {
  if (!["approve", "reject"].includes(decision)) {
    throw new AppError(400, "Refund decision must be approve or reject.");
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [[ret]] = await conn.query("SELECT * FROM returns WHERE id = ? LIMIT 1 FOR UPDATE", [Number(returnId)]);
    if (!ret) throw new AppError(404, "Return request not found.");
    if (!["Pending", "Approved", "Returned"].includes(ret.status)) {
      throw new AppError(409, "Return request already completed or rejected.");
    }

    const [[order]] = await conn.query("SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE", [ret.order_id]);
    const [[sumRow]] = await conn.query(
      `SELECT COALESCE(SUM(ri.quantity * oi.unit_price), 0) AS items_value
       FROM return_items ri JOIN order_items oi ON oi.id = ri.order_item_id WHERE ri.return_id = ?`,
      [ret.id]
    );
    const refundAmount = Math.min(
      Number(order.total_amount),
      Number(amount || sumRow.items_value || order.total_amount)
    );
    const refundNumber = `REF-${new Date().getFullYear()}-${crypto.randomInt(100000, 999999)}`;

    if (decision === "reject") {
      await conn.execute(
        `INSERT INTO refunds (refund_number, return_id, order_id, amount, method, status, reference_code, processed_by_user_id, processed_at, notes)
         VALUES (?, ?, ?, ?, ?, 'Rejected', ?, ?, NOW(), ?)`,
        [refundNumber, ret.id, order.id, refundAmount, method, referenceCode || null, adminUserId, notes || "Refund rejected by administrator"]
      );
      await conn.execute(
        "UPDATE returns SET status = 'Rejected', admin_note = COALESCE(NULLIF(?, ''), admin_note), reviewed_by_user_id = ?, reviewed_at = NOW() WHERE id = ?",
        [notes, adminUserId, ret.id]
      );
      await conn.execute("UPDATE orders SET order_status = 'Delivered' WHERE id = ?", [order.id]);
      await conn.execute(
        `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
         VALUES (?, ?, 'Delivered', ?, ?)`,
        [order.id, order.order_status, adminUserId, `Refund ${refundNumber} rejected${notes ? `: ${notes}` : ""}`]
      );
    } else {
      if (!["Approved", "Returned"].includes(ret.status)) {
        throw new AppError(409, "Return must be Approved or Returned before issuing a refund.");
      }
      await conn.execute(
        `INSERT INTO refunds (refund_number, return_id, order_id, amount, method, status, reference_code, processed_by_user_id, processed_at, notes)
         VALUES (?, ?, ?, ?, ?, 'Processed', ?, ?, NOW(), ?)`,
        [refundNumber, ret.id, order.id, refundAmount, method, referenceCode || null, adminUserId, notes]
      );
      await conn.execute("UPDATE returns SET status = 'Refunded' WHERE id = ?", [ret.id]);
      await conn.execute("UPDATE orders SET order_status = 'Refunded', payment_status = 'Refunded' WHERE id = ?", [order.id]);
      await conn.execute("UPDATE payments SET status = 'Refunded' WHERE order_id = ?", [order.id]);
      await conn.execute(
        `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
         VALUES (?, ?, 'Refunded', ?, ?)`,
        [order.id, order.order_status, adminUserId, `Refund ${refundNumber} processed (PKR ${refundAmount})`]
      );
    }

    await conn.commit();
    await logAdminActivity({
      userId: adminUserId,
      action: `refund.${decision}`,
      entityType: "refund",
      entityId: refundNumber,
      details: `Order ${order.order_number} (PKR ${refundAmount})`
    });
    return getOrderByNumber(order.order_number);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

module.exports = {
  cancelOrderByCustomer,
  createReturnRequest,
  listReturnsByUser,
  adminUpdateOrderStatus,
  adminDecideReturn,
  adminProcessRefund
};

