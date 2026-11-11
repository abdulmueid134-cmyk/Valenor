const path = require("path");
const crypto = require("crypto");
const express = require("express");
const multer = require("multer");
const ctrl = require("../controllers/orderLifecycleController");
const { authenticate, optionalAuthenticate, requireRole } = require("../middleware/authMiddleware");

const ALLOWED_MIME = new Set(["image/jpeg", "image/png", "image/webp"]);
const ALLOWED_EXT = new Set([".jpg", ".jpeg", ".png", ".webp"]);

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => {
    cb(null, path.resolve(__dirname, "..", "uploads", "returns"));
  },
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname || "").toLowerCase();
    const safeExt = ALLOWED_EXT.has(ext) ? ext : ".jpg";
    cb(null, `ret_${Date.now()}_${crypto.randomBytes(4).toString("hex")}${safeExt}`);
  }
});

const uploadPhotos = multer({
  storage,
  limits: { fileSize: 5 * 1024 * 1024, files: 3 },
  fileFilter: (_req, file, cb) => {
    const ext = path.extname(file.originalname || "").toLowerCase();
    if (!ALLOWED_MIME.has(file.mimetype) || !ALLOWED_EXT.has(ext)) {
      return cb(new Error("Only JPG, PNG, and WebP images up to 5 MB are allowed."));
    }
    cb(null, true);
  }
});

const router = express.Router();

// Customer endpoints
router.post("/orders/:orderNumber/cancel", optionalAuthenticate, ctrl.cancelOrder);
router.post("/returns", optionalAuthenticate, (req, res, next) => {
  uploadPhotos.array("photos", 3)(req, res, (err) => {
    if (err) return res.status(400).json({ success: false, message: err.message });
    next();
  });
}, ctrl.submitReturn);
router.get("/returns/mine", authenticate, ctrl.getMyReturns);

// Strict RBAC Administrative Lifecycle Endpoints (inaccessible to customers)
const ADMIN_ROLES = ["super_admin", "manager", "order_manager"];
router.patch("/admin/orders/:orderNumber/status", authenticate, requireRole(...ADMIN_ROLES), ctrl.adminUpdateStatus);
router.patch("/admin/returns/:id/decision", authenticate, requireRole(...ADMIN_ROLES), ctrl.adminReturnDecision);
router.post("/admin/returns/:id/refund", authenticate, requireRole(...ADMIN_ROLES), ctrl.adminRefund);

module.exports = router;

