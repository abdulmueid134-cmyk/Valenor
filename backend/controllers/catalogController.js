const catalogModel = require("../models/catalogModel");

async function listCategories(req, res, next) {
  try {
    const categories = await catalogModel.getCategories();
    res.json({ success: true, data: categories });
  } catch (err) {
    next(err);
  }
}

async function listProducts(req, res, next) {
  try {
    const result = await catalogModel.queryProducts(req.query);
    res.json({ success: true, ...result });
  } catch (err) {
    next(err);
  }
}

async function getProductDetail(req, res, next) {
  try {
    const product = await catalogModel.getProductByIdOrSlug(req.params.idOrSlug);
    if (!product) {
      return res.status(404).json({ success: false, message: "Timepiece not found" });
    }
    res.json({ success: true, data: product });
  } catch (err) {
    next(err);
  }
}

module.exports = { listCategories, listProducts, getProductDetail };

