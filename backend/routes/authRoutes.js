const express = require("express");
const authController = require("../controllers/authController");
const { authenticate } = require("../middleware/authMiddleware");
const { createRateLimiter } = require("../middleware/securityMiddleware");

const router = express.Router();

const authLimiter = createRateLimiter({ windowMs: 15 * 60_000, max: Number(process.env.RATE_LIMIT_AUTH_MAX || 40), keyPrefix: "cust_auth" });
const resetLimiter = createRateLimiter({ windowMs: 15 * 60_000, max: 10, keyPrefix: "pwd_reset", message: "Too many password reset attempts. Please try again in 15 minutes." });

// Public authentication endpoints (rate-limited)
router.post("/auth/register", authLimiter, authController.register);
router.post("/auth/login", authLimiter, authController.login);
router.post("/auth/forgot-password", resetLimiter, authController.forgotPassword);
router.post("/auth/reset-password", resetLimiter, authController.resetPassword);

// Authenticated customer account endpoints
router.get("/account/profile", authenticate, authController.getProfile);
router.put("/account/profile", authenticate, authController.updateProfile);
router.put("/account/password", authenticate, authController.changePassword);

router.get("/account/addresses", authenticate, authController.listAddresses);
router.post("/account/addresses", authenticate, authController.addAddress);
router.put("/account/addresses/:id", authenticate, authController.editAddress);
router.patch("/account/addresses/:id/default", authenticate, authController.setDefaultAddress);
router.delete("/account/addresses/:id", authenticate, authController.removeAddress);

module.exports = router;

