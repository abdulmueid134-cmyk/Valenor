const crypto = require("crypto");
const pool = require("../config/db");

class AppError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}

async function getCheckoutConfig(connOrPool = pool) {
  const [rows] = await connOrPool.query("SELECT setting_key, setting_value FROM settings");
  const map = Object.fromEntries(rows.map((r) => [r.setting_key, r.setting_value]));
  return {
    currency: map.currency || "PKR",
    taxRatePercent: Number(map.tax_rate_percent || 0),
    standardShippingFee: Number(map.standard_shipping_fee ?? 250),
    expressShippingFee: Number(map.express_shipping_fee ?? 550),
    freeShippingThreshold: Number(map.free_shipping_threshold ?? 15000),
    paymentMethods: {
      cod: map.payment_cod_enabled !== "0",
      bank_transfer: map.payment_bank_enabled !== "0",
      online_gateway: map.payment_online_enabled === "1"
    },
    bankTransferDetails:
      map.bank_transfer_details || "Meezan Bank · VALENOR Horology · IBAN: PK12MEZN0002140105551111"
  };
}

async function evaluateCoupon(connOrPool, { code, subtotal, userId = null, customerEmail = null, lock = false }) {
  const cleanCode = String(code || "").trim().toUpperCase();
  if (!cleanCode) throw new AppError(400, "Please enter a coupon code.");

  const [[coupon]] = await connOrPool.query(
    `SELECT * FROM coupons WHERE code = ? LIMIT 1 ${lock ? "FOR UPDATE" : ""}`,
    [cleanCode]
  );

  if (!coupon || !coupon.is_active) {
    throw new AppError(404, `Coupon "${cleanCode}" is invalid or inactive.`);
  }
  const now = new Date();
  if (coupon.starts_at && new Date(coupon.starts_at) > now) {
    throw new AppError(400, `Coupon "${cleanCode}" is not active yet.`);
  }
  if (coupon.expires_at && new Date(coupon.expires_at) < now) {
    throw new AppError(400, `Coupon "${cleanCode}" has expired.`);
  }
  if (coupon.usage_limit !== null && coupon.used_count >= coupon.usage_limit) {
    throw new AppError(400, `Coupon "${cleanCode}" has reached its maximum usage limit.`);
  }
  if (subtotal < Number(coupon.min_purchase_amount || 0)) {
    throw new AppError(
      400,
      `Coupon "${cleanCode}" requires a minimum order of PKR ${Number(coupon.min_purchase_amount).toLocaleString("en-PK")}.`
    );
  }

  if (userId || customerEmail) {
    const emailClean = customerEmail ? String(customerEmail).trim().toLowerCase() : "";
    const [[usedRow]] = await connOrPool.query(
      `SELECT COUNT(*) AS cnt FROM coupon_usage
       WHERE coupon_id = ? AND ((? IS NOT NULL AND user_id = ?) OR (? <> '' AND customer_email = ?))`,
      [coupon.id, userId, userId, emailClean, emailClean]
    );
    if (usedRow.cnt >= coupon.per_user_limit) {
      throw new AppError(400, `You have already reached the usage limit for coupon "${cleanCode}".`);
    }
  }

  let discountAmount = 0;
  let freeShipping = false;
  if (coupon.discount_type === "percent") {
    discountAmount = Math.round(subtotal * (Number(coupon.discount_value) / 100));
    if (coupon.max_discount_amount !== null) {
      discountAmount = Math.min(discountAmount, Number(coupon.max_discount_amount));
    }
    discountAmount = Math.min(discountAmount, subtotal);
  } else if (coupon.discount_type === "fixed") {
    discountAmount = Math.min(Number(coupon.discount_value), subtotal);
  } else if (coupon.discount_type === "free_shipping") {
    freeShipping = true;
  }

  return {
    couponId: coupon.id,
    code: coupon.code,
    description: coupon.description,
    discountType: coupon.discount_type,
    discountAmount,
    freeShipping
  };
}

function generateOrderNumber() {
  const year = new Date().getFullYear();
  const suffix = crypto.randomInt(100000, 999999);
  return `VAL-${year}-${suffix}`;
}

async function getOrderByNumber(orderNumber, userId = null) {
  const [[order]] = await pool.query(
    `SELECT * FROM orders WHERE order_number = ? LIMIT 1`,
    [orderNumber]
  );
  if (!order) return null;
  if (order.user_id && userId && order.user_id !== userId) {
    // Allow order lookup only when matching owner or guest order confirmation
  }
  const [items] = await pool.query(
    `SELECT oi.*, (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC LIMIT 1) AS image
     FROM order_items oi WHERE oi.order_id = ?`,
    [order.id]
  );
  const [history] = await pool.query(
    `SELECT previous_status, new_status, reason, created_at FROM order_status_history WHERE order_id = ? ORDER BY id ASC`,
    [order.id]
  );
  const [[payment]] = await pool.query(
    `SELECT method, amount, currency, status, reference_code, gateway_status, created_at FROM payments WHERE order_id = ? LIMIT 1`,
    [order.id]
  );
  const [[shipment]] = await pool.query(
    `SELECT courier_name, tracking_number, shipped_at, expected_delivery_date, delivered_at, notes FROM shipments WHERE order_id = ? LIMIT 1`,
    [order.id]
  );
  const [returns] = await pool.query(
    `SELECT r.*, ri.order_item_id, ri.quantity AS return_qty, oi.product_name
     FROM returns r
     LEFT JOIN return_items ri ON ri.return_id = r.id
     LEFT JOIN order_items oi ON oi.id = ri.order_item_id
     WHERE r.order_id = ?
     ORDER BY r.created_at DESC`,
    [order.id]
  );
  const [refunds] = await pool.query(
    `SELECT refund_number, amount, method, status, reference_code, processed_at, notes, created_at
     FROM refunds WHERE order_id = ? ORDER BY created_at DESC`,
    [order.id]
  );
  return {
    ...order,
    items,
    history,
    payment: payment || null,
    shipment: shipment || null,
    returns: returns.map((r) => ({
      ...r,
      supporting_photos: r.supporting_photos ? JSON.parse(r.supporting_photos) : []
    })),
    refunds
  };
}

async function listOrdersByUser(userId) {
  const [orders] = await pool.query(
    `SELECT o.*, COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC`,
    [userId]
  );
  return orders;
}

async function createOrder({
  idempotencyKey,
  userId = null,
  customer,
  address,
  deliveryMethod = "standard",
  paymentMethod = "cod",
  paymentReference = "",
  couponCode = "",
  items = []
}) {
  if (!idempotencyKey) throw new AppError(400, "Missing idempotency key.");
  if (!Array.isArray(items) || !items.length) throw new AppError(400, "Your shopping bag is empty.");

  const [[existing]] = await pool.query(
    "SELECT order_number FROM orders WHERE idempotency_key = ? LIMIT 1",
    [idempotencyKey]
  );
  if (existing) {
    const order = await getOrderByNumber(existing.order_number);
    return { order, duplicatePrevented: true };
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const cfg = await getCheckoutConfig(conn);
    if (paymentMethod === "online_gateway" && !cfg.paymentMethods.online_gateway) {
      throw new AppError(400, "Online Payment Gateway is not connected yet. Please select Cash on Delivery or Bank Transfer.");
    }
    if (!cfg.paymentMethods[paymentMethod]) {
      throw new AppError(400, "Selected payment method is unavailable.");
    }

    let subtotal = 0;
    let productDiscount = 0;
    const verifiedItems = [];

    for (const rawItem of items) {
      const productId = Number(rawItem.productId || rawItem.id);
      const qty = Math.max(1, Math.min(20, Math.floor(Number(rawItem.quantity || rawItem.qty || 1))));
      const variantName = String(rawItem.variantName || rawItem.variant || "").trim().slice(0, 120);

      const [[prod]] = await conn.query(
        `SELECT p.id, p.sku, p.name, p.original_price, p.sale_price, p.is_active,
                COALESCE(inv.available_stock, 0) AS available_stock
         FROM products p
         LEFT JOIN inventory inv ON inv.product_id = p.id
         WHERE p.id = ? LIMIT 1 FOR UPDATE`,
        [productId]
      );
      if (!prod || !prod.is_active) {
        throw new AppError(404, `Watch #${productId} is no longer available.`);
      }
      if (Number(prod.available_stock) < qty) {
        throw new AppError(
          409,
          `Insufficient stock for "${prod.name}". Only ${prod.available_stock} remaining.`
        );
      }

      const unitOriginal = Number(prod.original_price);
      const unitSale = Number(prod.sale_price);
      const lineTotal = unitSale * qty;

      subtotal += lineTotal;
      productDiscount += Math.max(0, unitOriginal - unitSale) * qty;

      verifiedItems.push({
        productId: prod.id,
        sku: prod.sku,
        productName: prod.name,
        variantName,
        unitOriginal,
        unitSale,
        qty,
        lineTotal
      });
    }

    let appliedCoupon = null;
    if (couponCode && String(couponCode).trim()) {
      appliedCoupon = await evaluateCoupon(conn, {
        code: couponCode,
        subtotal,
        userId,
        customerEmail: customer.email,
        lock: true
      });
    }

    const couponDiscount = appliedCoupon ? appliedCoupon.discountAmount : 0;
    const netAfterCoupon = Math.max(0, subtotal - couponDiscount);
    const qualifiesFreeStandard =
      (appliedCoupon && appliedCoupon.freeShipping) || netAfterCoupon >= cfg.freeShippingThreshold;
    const deliveryCharge =
      deliveryMethod === "express"
        ? cfg.expressShippingFee
        : qualifiesFreeStandard
          ? 0
          : cfg.standardShippingFee;

    const taxAmount = Math.round(netAfterCoupon * (cfg.taxRatePercent / 100));
    const totalAmount = netAfterCoupon + deliveryCharge + taxAmount;

    const orderNumber = generateOrderNumber();

    const [orderRes] = await conn.execute(
      `INSERT INTO orders (
        order_number, idempotency_key, user_id, customer_name, customer_email, customer_phone,
        shipping_city, shipping_area, shipping_street, shipping_postal_code, delivery_instructions,
        delivery_method, payment_method, payment_status, order_status,
        subtotal, product_discount, coupon_code, coupon_discount, delivery_charge, tax_amount, total_amount
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', ?, ?, ?, ?, ?, ?, ?)`,
      [
        orderNumber,
        idempotencyKey,
        userId,
        customer.fullName,
        customer.email.toLowerCase(),
        customer.phone,
        address.city,
        address.area,
        address.streetAddress,
        address.postalCode || "",
        address.deliveryInstructions || "",
        deliveryMethod === "express" ? "express" : "standard",
        paymentMethod,
        subtotal,
        productDiscount,
        appliedCoupon ? appliedCoupon.code : null,
        couponDiscount,
        deliveryCharge,
        taxAmount,
        totalAmount
      ]
    );
    const orderId = orderRes.insertId;

    for (const item of verifiedItems) {
      await conn.execute(
        `INSERT INTO order_items
         (order_id, product_id, sku, product_name, variant_name, unit_original_price, unit_price, quantity, line_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          orderId,
          item.productId,
          item.sku,
          item.productName,
          item.variantName,
          item.unitOriginal,
          item.unitSale,
          item.qty,
          item.lineTotal
        ]
      );

      await conn.execute(
        `UPDATE inventory
         SET available_stock = available_stock - ?, reserved_stock = reserved_stock + ?
         WHERE product_id = ?`,
        [item.qty, item.qty, item.productId]
      );

      await conn.execute(
        `INSERT INTO inventory_movements (product_id, order_id, change_qty, movement_type, note)
         VALUES (?, ?, ?, 'sale_reserve', ?)`,
        [item.productId, orderId, -item.qty, `Order ${orderNumber} placed`]
      );
    }

    await conn.execute(
      `INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_user_id, reason)
       VALUES (?, NULL, 'Pending', ?, 'Customer placed order at checkout')`,
      [orderId, userId]
    );

    const safeRef = String(paymentReference || "").trim().slice(0, 100);
    await conn.execute(
      `INSERT INTO payments (order_id, method, amount, currency, status, reference_code, gateway_status)
       VALUES (?, ?, ?, ?, 'Pending', ?, ?)`,
      [
        orderId,
        paymentMethod,
        totalAmount,
        cfg.currency,
        safeRef || null,
        paymentMethod === "cod" ? "cash_on_delivery" : "awaiting_bank_transfer"
      ]
    );

    if (appliedCoupon) {
      await conn.execute("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?", [appliedCoupon.couponId]);
      await conn.execute(
        `INSERT INTO coupon_usage (coupon_id, order_id, user_id, customer_email, discount_applied)
         VALUES (?, ?, ?, ?, ?)`,
        [appliedCoupon.couponId, orderId, userId, customer.email.toLowerCase(), couponDiscount]
      );
    }

    await conn.commit();
    const created = await getOrderByNumber(orderNumber);
    return { order: created, duplicatePrevented: false };
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

module.exports = {
  AppError,
  getCheckoutConfig,
  evaluateCoupon,
  createOrder,
  getOrderByNumber,
  listOrdersByUser
};

