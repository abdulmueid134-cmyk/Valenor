const pool = require("../config/db");
const checkoutModel = require("../models/checkoutModel");

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_RE = /^[+\d][\d\s-]{7,18}$/;

async function getConfig(_req, res, next) {
  try {
    const config = await checkoutModel.getCheckoutConfig();
    res.json({ success: true, config });
  } catch (err) {
    next(err);
  }
}

async function validateCoupon(req, res, next) {
  try {
    const code = req.body.code;
    const subtotal = Number(req.body.subtotal || 0);
    const customerEmail = req.body.email || req.user?.email || null;
    if (subtotal <= 0) {
      return res.status(400).json({ success: false, message: "Add items to your bag before applying a coupon." });
    }
    const result = await checkoutModel.evaluateCoupon(pool, {
      code,
      subtotal,
      userId: req.user?.id || null,
      customerEmail
    });
    res.json({ success: true, coupon: result });
  } catch (err) {
    if (err instanceof checkoutModel.AppError) {
      return res.status(err.status).json({ success: false, message: err.message });
    }
    next(err);
  }
}

async function placeOrder(req, res, next) {
  try {
    const body = req.body || {};
    const customer = {
      fullName: String(body.customer?.fullName || "").trim(),
      email: String(body.customer?.email || "").trim().toLowerCase(),
      phone: String(body.customer?.phone || "").trim()
    };
    const address = {
      city: String(body.address?.city || "").trim(),
      area: String(body.address?.area || "").trim(),
      streetAddress: String(body.address?.streetAddress || "").trim(),
      postalCode: String(body.address?.postalCode || "").trim(),
      deliveryInstructions: String(body.address?.deliveryInstructions || "").trim()
    };

    if (customer.fullName.length < 2 || !EMAIL_RE.test(customer.email) || !PHONE_RE.test(customer.phone)) {
      return res.status(400).json({ success: false, message: "Valid customer name, email, and phone number are required." });
    }
    if (!address.city || !address.area || address.streetAddress.length < 4) {
      return res.status(400).json({ success: false, message: "Complete delivery city, area, and street address are required." });
    }

    const result = await checkoutModel.createOrder({
      idempotencyKey: String(body.idempotencyKey || "").trim(),
      userId: req.user?.id || null,
      customer,
      address,
      deliveryMethod: body.deliveryMethod,
      paymentMethod: body.paymentMethod,
      paymentReference: body.paymentReference,
      couponCode: body.couponCode,
      items: body.items
    });

    res.status(result.duplicatePrevented ? 200 : 201).json({
      success: true,
      duplicatePrevented: result.duplicatePrevented,
      order: result.order
    });
  } catch (err) {
    if (err instanceof checkoutModel.AppError) {
      return res.status(err.status).json({ success: false, message: err.message });
    }
    next(err);
  }
}

async function getMyOrders(req, res, next) {
  try {
    const orders = await checkoutModel.listOrdersByUser(req.user.id);
    res.json({ success: true, orders });
  } catch (err) {
    next(err);
  }
}

async function getOrderDetail(req, res, next) {
  try {
    const order = await checkoutModel.getOrderByNumber(req.params.orderNumber, req.user?.id || null);
    if (!order) {
      return res.status(404).json({ success: false, message: "Order not found." });
    }
    res.json({ success: true, order });
  } catch (err) {
    next(err);
  }
}

module.exports = { getConfig, validateCoupon, placeOrder, getMyOrders, getOrderDetail };

