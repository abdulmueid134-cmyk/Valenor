const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");
const authModel = require("../models/authModel");
const adminModel = require("../models/adminDashboardModel");
const { getJwtSecret } = require("../middleware/authMiddleware");
const { AppError } = require("../models/checkoutModel");

async function adminLogin(req, res, next) {
  try {
    const identifier = String(req.body.identifier || "").trim();
    const password = String(req.body.password || "");
    const rememberMe = Boolean(req.body.rememberMe);

    if (!identifier || !password) {
      return res.status(400).json({ success: false, message: "Email or phone and password are required." });
    }

    const authUser = await authModel.findAuthRowByIdentifier(identifier);
    if (!authUser || !authUser.is_active) {
      return res.status(401).json({ success: false, message: "Invalid credentials." });
    }

    const valid = await bcrypt.compare(password, authUser.password_hash);
    if (!valid) {
      return res.status(401).json({ success: false, message: "Invalid credentials." });
    }

    if (!adminModel.ADMIN_ROLES.includes(authUser.role)) {
      return res.status(403).json({ success: false, message: "Access denied: administrator role required." });
    }

    const { password_hash: _omit, ...safeUser } = authUser;
    const token = jwt.sign(
      { sub: safeUser.id, role: safeUser.role },
      getJwtSecret(),
      { expiresIn: rememberMe ? "14d" : "12h" }
    );

    await adminModel.logAdminActivity({
      userId: safeUser.id,
      action: "admin.login",
      entityType: "user",
      entityId: safeUser.id,
      details: `Signed in as ${safeUser.role}`,
      ipAddress: req.ip
    });

    res.json({ success: true, token, user: safeUser });
  } catch (err) {
    next(err);
  }
}

async function getAdminProfile(req, res) {
  res.json({ success: true, user: req.user });
}

async function getDashboard(req, res, next) {
  try {
    const data = await adminModel.getDashboardAnalytics({
      range: req.query.range || "30d",
      startDate: req.query.startDate || "",
      endDate: req.query.endDate || ""
    });
    res.json({ success: true, admin: req.user, data });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

async function getSection(req, res, next) {
  try {
    const result = await adminModel.getSectionRecords(req.params.section, req.query.q || "");
    res.json({ success: true, ...result });
  } catch (err) {
    if (err instanceof AppError) return res.status(err.status).json({ success: false, message: err.message });
    next(err);
  }
}

module.exports = { adminLogin, getAdminProfile, getDashboard, getSection };

