const express = require("express");
const catalogController = require("../controllers/catalogController");
const govController = require("../controllers/adminGovernanceController");

const router = express.Router();

router.get("/categories", catalogController.listCategories);
router.get("/products", catalogController.listProducts);
router.get("/products/:idOrSlug", catalogController.getProductDetail);
router.get("/storefront/config", govController.getStorefrontConfig);
router.post("/newsletter", govController.subscribeNewsletter);

module.exports = router;

