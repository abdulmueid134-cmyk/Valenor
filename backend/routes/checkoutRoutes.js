const express = require("express");
const checkoutController = require("../controllers/checkoutController");
const { authenticate, optionalAuthenticate } = require("../middleware/authMiddleware");

const router = express.Router();

router.get("/checkout/config", checkoutController.getConfig);
router.post("/coupons/validate", optionalAuthenticate, checkoutController.validateCoupon);
router.post("/orders", optionalAuthenticate, checkoutController.placeOrder);
router.get("/orders/mine", authenticate, checkoutController.getMyOrders);
router.get("/orders/:orderNumber", optionalAuthenticate, checkoutController.getOrderDetail);

module.exports = router;

