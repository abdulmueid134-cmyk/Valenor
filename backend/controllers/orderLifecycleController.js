const orderLifecycleModel = require("../models/orderLifecycleModel");
const { AppError } = require("../models/checkoutModel");

async function cancelOrder(req, res, next) {
  try {
    const order = await orderLifecycleModel.cancelOrderByCustomer({
      orderNumber: req.params.orderNumber,
      user: req.user || null,
      contact: req.body?.contact,
      reason: req.body?.reason
    });
    res.json({ success: true, order });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

async function submitReturn(req, res, next) {
  try {
    const { orderNumber, orderItemId, quantity, reason, details, contact } = req.body || {};
    if (!orderNumber || !orderItemId || !reason) {
      return res.status(400).json({ success: false, message: "Order number, selected watch, and return reason are required." });
    }
    const photoPaths = (req.files || []).map((f) => `/uploads/returns/${f.filename}`);
    const order = await orderLifecycleModel.createReturnRequest({
      orderNumber: String(orderNumber).trim(),
      orderItemId,
      quantity,
      reason,
      details,
      photoPaths,
      user: req.user || null,
      contact
    });
    res.status(201).json({ success: true, order });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

async function getMyReturns(req, res, next) {
  try {
    const returns = await orderLifecycleModel.listReturnsByUser(req.user.id);
    res.json({ success: true, returns });
  } catch (err) {
    next(err);
  }
}

async function adminUpdateStatus(req, res, next) {
  try {
    const order = await orderLifecycleModel.adminUpdateOrderStatus({
      orderNumber: req.params.orderNumber,
      newStatus: req.body.status,
      adminUserId: req.user.id,
      reason: req.body.reason,
      courierName: req.body.courierName,
      trackingNumber: req.body.trackingNumber,
      expectedDeliveryDate: req.body.expectedDeliveryDate
    });
    res.json({ success: true, order });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

async function adminReturnDecision(req, res, next) {
  try {
    const order = await orderLifecycleModel.adminDecideReturn({
      returnId: req.params.id,
      decision: req.body.decision,
      adminUserId: req.user.id,
      adminNote: req.body.adminNote
    });
    res.json({ success: true, order });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

async function adminRefund(req, res, next) {
  try {
    const order = await orderLifecycleModel.adminProcessRefund({
      returnId: req.params.id,
      adminUserId: req.user.id,
      decision: req.body.decision || "approve",
      amount: req.body.amount,
      method: req.body.method,
      referenceCode: req.body.referenceCode,
      notes: req.body.notes
    });
    res.json({ success: true, order });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

module.exports = {
  cancelOrder,
  submitReturn,
  getMyReturns,
  adminUpdateStatus,
  adminReturnDecision,
  adminRefund
};

