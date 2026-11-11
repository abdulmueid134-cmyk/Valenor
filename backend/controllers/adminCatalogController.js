const adminCatalogModel = require("../models/adminCatalogModel");
const { AppError } = require("../models/checkoutModel");

function handleErr(err, res, next) {
  if (err instanceof AppError) {
    return res.status(err.status).json({ success: false, message: err.message });
  }
  next(err);
}

async function getMeta(_req, res, next) {
  try {
    const meta = await adminCatalogModel.getCatalogMeta();
    res.json({ success: true, ...meta });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function getProduct(req, res, next) {
  try {
    const product = await adminCatalogModel.getProductDetailForAdmin(req.params.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function createProduct(req, res, next) {
  try {
    const product = await adminCatalogModel.createProduct(req.body || {}, req.user.id);
    res.status(201).json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function updateProduct(req, res, next) {
  try {
    const product = await adminCatalogModel.updateProduct(req.params.id, req.body || {}, req.user.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function patchProductFlags(req, res, next) {
  try {
    const product = await adminCatalogModel.patchProductFlags(req.params.id, req.body || {}, req.user.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function deleteProduct(req, res, next) {
  try {
    const result = await adminCatalogModel.deleteProduct(req.params.id, req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function uploadProductImages(req, res, next) {
  try {
    const urls = (req.files || []).map((f) => `/uploads/products/${f.filename}`);
    const product = await adminCatalogModel.addProductImages(req.params.id, urls, req.user.id);
    res.status(201).json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function setPrimaryImage(req, res, next) {
  try {
    const product = await adminCatalogModel.setPrimaryProductImage(req.params.id, req.params.imageId, req.user.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function replaceImage(req, res, next) {
  try {
    if (!req.file) return res.status(400).json({ success: false, message: "Please select a replacement image file." });
    const newUrl = `/uploads/products/${req.file.filename}`;
    const product = await adminCatalogModel.replaceProductImage(req.params.id, req.params.imageId, newUrl, req.user.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function deleteImage(req, res, next) {
  try {
    const product = await adminCatalogModel.deleteProductImage(req.params.id, req.params.imageId, req.user.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveWatchType(req, res, next) {
  try {
    const type = await adminCatalogModel.upsertWatchType({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, type });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function deleteWatchType(req, res, next) {
  try {
    const result = await adminCatalogModel.deleteWatchType(req.params.id, req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveCategory(req, res, next) {
  try {
    const category = await adminCatalogModel.upsertCategory({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, category });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function deleteCategory(req, res, next) {
  try {
    const result = await adminCatalogModel.deleteCategory(req.params.id, req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveBrand(req, res, next) {
  try {
    const brand = await adminCatalogModel.upsertBrand({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, brand });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function deleteBrand(req, res, next) {
  try {
    const result = await adminCatalogModel.deleteBrand(req.params.id, req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function adjustInventory(req, res, next) {
  try {
    const product = await adminCatalogModel.adjustInventory(req.params.productId, req.body || {}, req.user.id);
    res.json({ success: true, product });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function listInventoryMovements(req, res, next) {
  try {
    const movements = await adminCatalogModel.listInventoryMovements(req.query.limit || 50);
    res.json({ success: true, movements });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveCoupon(req, res, next) {
  try {
    const coupon = await adminCatalogModel.upsertCoupon({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, coupon });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function moderateReview(req, res, next) {
  try {
    const review = await adminCatalogModel.setReviewApproval(req.params.id, Boolean(req.body?.is_approved), req.user.id);
    res.json({ success: true, review });
  } catch (err) {
    handleErr(err, res, next);
  }
}

module.exports = {
  getMeta,
  getProduct,
  createProduct,
  updateProduct,
  patchProductFlags,
  deleteProduct,
  uploadProductImages,
  setPrimaryImage,
  replaceImage,
  deleteImage,
  saveWatchType,
  deleteWatchType,
  saveCategory,
  deleteCategory,
  saveBrand,
  deleteBrand,
  adjustInventory,
  listInventoryMovements,
  saveCoupon,
  moderateReview
};

