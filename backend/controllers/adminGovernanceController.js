const govModel = require("../models/adminGovernanceModel");
const { logAdminActivity } = require("../models/adminDashboardModel");
const { AppError } = require("../models/checkoutModel");

function handleErr(err, res, next) {
  if (err instanceof AppError) {
    return res.status(err.status).json({ success: false, message: err.message });
  }
  next(err);
}

// ---------- Reports (JSON / CSV / PDF) ----------
async function getReport(req, res, next) {
  try {
    const type = req.params.type;
    const format = String(req.query.format || "json").toLowerCase();
    const report = await govModel.generateReportData(type, req.query);

    if (format === "csv") {
      await logAdminActivity({ userId: req.user.id, action: `report.export.${type}.csv`, entityType: "report", entityId: type, details: report.subtitle });
      const buf = govModel.buildCsvBuffer(report.columns, report.rows);
      res.setHeader("Content-Type", "text/csv; charset=utf-8");
      res.setHeader("Content-Disposition", `attachment; filename="valenor-${type}-report.csv"`);
      return res.send(buf);
    }

    if (format === "pdf") {
      await logAdminActivity({ userId: req.user.id, action: `report.export.${type}.pdf`, entityType: "report", entityId: type, details: report.subtitle });
      const buf = govModel.buildPdfReportBuffer(report);
      res.setHeader("Content-Type", "application/pdf");
      res.setHeader("Content-Disposition", `attachment; filename="valenor-${type}-report.pdf"`);
      return res.send(buf);
    }

    res.json({ success: true, report });
  } catch (err) {
    handleErr(err, res, next);
  }
}

// ---------- Website Settings ----------
async function getSettings(_req, res, next) {
  try {
    const settings = await govModel.getAllSettings();
    res.json({ success: true, settings });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveSettings(req, res, next) {
  try {
    const settings = await govModel.updateSettings(req.body || {}, req.user.id);
    res.json({ success: true, settings });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function uploadBrandAsset(req, res, next) {
  try {
    if (!req.file) return res.status(400).json({ success: false, message: "Please select a valid image file." });
    const url = `/uploads/branding/${req.file.filename}`;
    if (req.body?.target === "logo") {
      await govModel.updateSettings({ brand_logo_url: url }, req.user.id);
    }
    res.status(201).json({ success: true, url });
  } catch (err) {
    handleErr(err, res, next);
  }
}

// ---------- Promotional Management ----------
async function listPromotions(_req, res, next) {
  try {
    const data = await govModel.getPromotionsOverview();
    res.json({ success: true, ...data });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveCampaign(req, res, next) {
  try {
    const campaign = await govModel.upsertCampaign({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, campaign });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function removeCampaign(req, res, next) {
  try {
    const result = await govModel.deleteCampaign(req.params.id, req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveBanner(req, res, next) {
  try {
    const banner = await govModel.upsertBanner({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, banner });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function removeBanner(req, res, next) {
  try {
    const result = await govModel.deleteBanner(req.params.id, req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function toggleCategoryFeatured(req, res, next) {
  try {
    const category = await govModel.setCategoryFeatured(req.params.id, Boolean(req.body?.is_featured), req.user.id);
    res.json({ success: true, category });
  } catch (err) {
    handleErr(err, res, next);
  }
}

// ---------- Governance, Permissions & Audit Log (Super Admin) ----------
async function getGovernance(req, res, next) {
  try {
    const data = await govModel.getGovernanceMatrix(req.query.q || "");
    res.json({ success: true, ...data });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveStaffUser(req, res, next) {
  try {
    const staff = await govModel.saveAdminStaff({ ...(req.body || {}), id: req.params.id || req.body?.id }, req.user.id);
    res.status(req.params.id ? 200 : 201).json({ success: true, staff });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function saveRolePermissions(req, res, next) {
  try {
    const result = await govModel.updateRolePermissions(req.params.roleId, req.body?.permissionIds || [], req.user.id);
    res.json({ success: true, ...result });
  } catch (err) {
    handleErr(err, res, next);
  }
}

// ---------- Public Storefront Endpoints ----------
async function getStorefrontConfig(_req, res, next) {
  try {
    const config = await govModel.getPublicStorefrontConfig();
    res.json({ success: true, config });
  } catch (err) {
    handleErr(err, res, next);
  }
}

async function subscribeNewsletter(req, res, next) {
  try {
    const out = await govModel.subscribeNewsletter(req.body?.email);
    res.status(201).json({ success: true, ...out, message: "Subscribed to VALENOR collector alerts." });
  } catch (err) {
    handleErr(err, res, next);
  }
}

module.exports = {
  getReport,
  getSettings,
  saveSettings,
  uploadBrandAsset,
  listPromotions,
  saveCampaign,
  removeCampaign,
  saveBanner,
  removeBanner,
  toggleCategoryFeatured,
  getGovernance,
  saveStaffUser,
  saveRolePermissions,
  getStorefrontConfig,
  subscribeNewsletter
};

