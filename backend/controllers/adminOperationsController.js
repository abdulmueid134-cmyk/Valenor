const opsModel = require("../models/adminOperationsModel");
const { AppError } = require("../models/checkoutModel");

function handleErr(err, res, next) {
  if (err instanceof AppError) {
    return res.status(err.status).json({ success: false, message: err.message });
  }
  next(err);
}

async function listOrders(req, res, next) {
  try {
    const result = await opsModel.listOrdersAdmin(req.query);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function getOrderDetail(req, res, next) {
  try {
    const order = await opsModel.getOrderDetailAdmin(req.params.orderNumber);
    res.json({ success: true, order });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function updateOrderMeta(req, res, next) {
  try {
    const order = await opsModel.updateOrderAdminMeta(req.params.orderNumber, req.body || {}, req.user.id);
    res.json({ success: true, order });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function listCustomers(req, res, next) {
  try {
    const result = await opsModel.listCustomersAdmin(req.query);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function getCustomerDetail(req, res, next) {
  try {
    const detail = await opsModel.getCustomerDetailAdmin(req.params.id);
    res.json({ success: true, ...detail });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function toggleCustomerStatus(req, res, next) {
  try {
    const detail = await opsModel.setCustomerActiveStatus(
      req.params.id,
      Boolean(req.body?.is_active),
      req.body?.reason || "",
      req.user.id
    );
    res.json({ success: true, ...detail });
  } catch (err) {
    handleErr(err, res, next);
  }
}

module.exports = {
  listOrders,
  getOrderDetail,
  updateOrderMeta,
  listCustomers,
  getCustomerDetail,
  toggleCustomerStatus
};

