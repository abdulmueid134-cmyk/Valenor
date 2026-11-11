const path = require("path");
const crypto = require("crypto");
const express = require("express");
const multer = require("multer");
const ctrl = require("../controllers/adminDashboardController");
const catCtrl = require("../controllers/adminCatalogController");
const opsCtrl = require("../controllers/adminOperationsController");
const govCtrl = require("../controllers/adminGovernanceController");
const { ADMIN_ROLES } = require("../models/adminDashboardModel");
const { PRODUCTS_UPLOAD_DIR } = require("../models/adminCatalogModel");
const { BRANDING_UPLOAD_DIR } = require("../models/adminGovernanceModel");
const { authenticate, requireRole } = require("../middleware/authMiddleware");
const { createRateLimiter } = require("../middleware/securityMiddleware");

const ALLOWED_MIME = new Set(["image/jpeg", "image/png", "image/webp"]);
const ALLOWED_EXT = new Set([".jpg", ".jpeg", ".png", ".webp"]);

const makeUpload = (destDir, maxFiles) => multer({
  storage: multer.diskStorage({
    destination: (_req, _file, cb) => cb(null, destDir),
    filename: (_req, file, cb) => {
      const ext = path.extname(file.originalname || "").toLowerCase();
      const safeExt = ALLOWED_EXT.has(ext) ? ext : ".jpg";
      cb(null, `asset_${Date.now()}_${crypto.randomBytes(4).toString("hex")}${safeExt}`);
    }
  }),
  limits: { fileSize: 5 * 1024 * 1024, files: maxFiles },
  fileFilter: (_req, file, cb) => {
    const ext = path.extname(file.originalname || "").toLowerCase();
    if (!ALLOWED_MIME.has(file.mimetype) || !ALLOWED_EXT.has(ext)) {
      return cb(new Error("Only JPG, PNG, and WebP images up to 5 MB are allowed."));
    }
    cb(null, true);
  }
});

const uploadProductPhotos = makeUpload(PRODUCTS_UPLOAD_DIR, 8);
const uploadBrandingPhoto = makeUpload(BRANDING_UPLOAD_DIR, 1);

const withUpload = (middleware) => (req, res, next) => {
  middleware(req, res, (err) => {
    if (err) return res.status(400).json({ success: false, message: err.message });
    next();
  });
};

const router = express.Router();

// Dedicated Admin Auth endpoint (rate-limited)
const adminLoginLimiter = createRateLimiter({ windowMs: 15 * 60_000, max: Number(process.env.RATE_LIMIT_AUTH_MAX || 40), keyPrefix: "admin_auth" });
router.post("/admin/auth/login", adminLoginLimiter, ctrl.adminLogin);

// Dashboard & Directory Read Routes (All admin staff roles)
const adminViewGuard = [authenticate, requireRole(...ADMIN_ROLES)];
router.get("/admin/me", ...adminViewGuard, ctrl.getAdminProfile);
router.get("/admin/dashboard", ...adminViewGuard, ctrl.getDashboard);
router.get("/admin/section/:section", ...adminViewGuard, ctrl.getSection);
router.get("/admin/catalog/meta", ...adminViewGuard, catCtrl.getMeta);
router.get("/admin/products/:id", ...adminViewGuard, catCtrl.getProduct);
router.get("/admin/inventory/movements", ...adminViewGuard, catCtrl.listInventoryMovements);

// Catalog, Product Types, Categories, Brands, Images & Inventory CRUD (super_admin, manager, inventory_manager)
const catalogGuard = [authenticate, requireRole("super_admin", "manager", "inventory_manager")];
router.post("/admin/products", ...catalogGuard, catCtrl.createProduct);
router.put("/admin/products/:id", ...catalogGuard, catCtrl.updateProduct);
router.patch("/admin/products/:id/flags", ...catalogGuard, catCtrl.patchProductFlags);
router.delete("/admin/products/:id", ...catalogGuard, catCtrl.deleteProduct);

router.post("/admin/products/:id/images", ...catalogGuard, withUpload(uploadProductPhotos.array("images", 8)), catCtrl.uploadProductImages);
router.patch("/admin/products/:id/images/:imageId/primary", ...catalogGuard, catCtrl.setPrimaryImage);
router.put("/admin/products/:id/images/:imageId", ...catalogGuard, withUpload(uploadProductPhotos.single("image")), catCtrl.replaceImage);
router.delete("/admin/products/:id/images/:imageId", ...catalogGuard, catCtrl.deleteImage);

router.post("/admin/watch-types", ...catalogGuard, catCtrl.saveWatchType);
router.put("/admin/watch-types/:id", ...catalogGuard, catCtrl.saveWatchType);
router.delete("/admin/watch-types/:id", ...catalogGuard, catCtrl.deleteWatchType);

router.post("/admin/categories", ...catalogGuard, catCtrl.saveCategory);
router.put("/admin/categories/:id", ...catalogGuard, catCtrl.saveCategory);
router.delete("/admin/categories/:id", ...catalogGuard, catCtrl.deleteCategory);

router.post("/admin/brands", ...catalogGuard, catCtrl.saveBrand);
router.put("/admin/brands/:id", ...catalogGuard, catCtrl.saveBrand);
router.delete("/admin/brands/:id", ...catalogGuard, catCtrl.deleteBrand);

router.patch("/admin/inventory/:productId", ...catalogGuard, catCtrl.adjustInventory);

// Coupons & Reviews moderation (super_admin, manager)
const managerGuard = [authenticate, requireRole("super_admin", "manager")];
router.post("/admin/coupons", ...managerGuard, catCtrl.saveCoupon);
router.put("/admin/coupons/:id", ...managerGuard, catCtrl.saveCoupon);
router.patch("/admin/reviews/:id", ...managerGuard, catCtrl.moderateReview);

// Order & Customer Management (Phase 8 — super_admin, manager, order_manager)
const orderOpsGuard = [authenticate, requireRole("super_admin", "manager", "order_manager")];
router.get("/admin/orders", ...orderOpsGuard, opsCtrl.listOrders);
router.get("/admin/orders/:orderNumber", ...orderOpsGuard, opsCtrl.getOrderDetail);
router.patch("/admin/orders/:orderNumber/meta", ...orderOpsGuard, opsCtrl.updateOrderMeta);
router.get("/admin/customers", ...orderOpsGuard, opsCtrl.listCustomers);
router.get("/admin/customers/:id", ...orderOpsGuard, opsCtrl.getCustomerDetail);
router.patch("/admin/customers/:id/status", ...orderOpsGuard, opsCtrl.toggleCustomerStatus);

// Reports, Website Settings, Promotions & Permissions (Phase 9)
router.get("/admin/reports/:type", ...managerGuard, govCtrl.getReport);
router.get("/admin/promotions", ...managerGuard, govCtrl.listPromotions);
router.post("/admin/campaigns", ...managerGuard, govCtrl.saveCampaign);
router.put("/admin/campaigns/:id", ...managerGuard, govCtrl.saveCampaign);
router.delete("/admin/campaigns/:id", ...managerGuard, govCtrl.removeCampaign);
router.post("/admin/banners", ...managerGuard, govCtrl.saveBanner);
router.put("/admin/banners/:id", ...managerGuard, govCtrl.saveBanner);
router.delete("/admin/banners/:id", ...managerGuard, govCtrl.removeBanner);
router.patch("/admin/categories/:id/featured", ...managerGuard, govCtrl.toggleCategoryFeatured);

router.get("/admin/settings", ...managerGuard, govCtrl.getSettings);
const superAdminGuard = [authenticate, requireRole("super_admin")];
router.put("/admin/settings", ...superAdminGuard, govCtrl.saveSettings);
router.post("/admin/branding/upload", ...superAdminGuard, withUpload(uploadBrandingPhoto.single("asset")), govCtrl.uploadBrandAsset);
router.get("/admin/governance", ...superAdminGuard, govCtrl.getGovernance);
router.post("/admin/staff", ...superAdminGuard, govCtrl.saveStaffUser);
router.put("/admin/staff/:id", ...superAdminGuard, govCtrl.saveStaffUser);
router.put("/admin/permissions/:roleId", ...superAdminGuard, govCtrl.saveRolePermissions);

module.exports = router;
