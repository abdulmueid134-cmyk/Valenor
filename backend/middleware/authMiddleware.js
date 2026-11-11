const crypto = require("crypto");
const jwt = require("jsonwebtoken");
const authModel = require("../models/authModel");

const DEV_EPHEMERAL_SECRET = crypto.randomBytes(32).toString("hex");

function getJwtSecret() {
  if (process.env.JWT_SECRET) return process.env.JWT_SECRET;
  if (process.env.NODE_ENV === "production") {
    throw new Error("JWT_SECRET environment variable must be configured in production.");
  }
  return DEV_EPHEMERAL_SECRET;
}

async function authenticate(req, res, next) {
  try {
    const authHeader = req.headers.authorization || "";
    const token = authHeader.startsWith("Bearer ") ? authHeader.slice(7).trim() : null;
    if (!token) {
      return res.status(401).json({ success: false, message: "Authentication required." });
    }

    const payload = jwt.verify(token, getJwtSecret());
    const user = await authModel.findUserById(payload.sub);
    if (!user || !user.is_active) {
      return res.status(401).json({ success: false, message: "Account is inactive or unavailable." });
    }

    req.user = user;
    next();
  } catch {
    return res.status(401).json({ success: false, message: "Invalid or expired session token." });
  }
}

function requireRole(...allowedRoles) {
  return (req, res, next) => {
    if (!req.user || !allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ success: false, message: "Forbidden: insufficient role permissions." });
    }
    next();
  };
}

async function optionalAuthenticate(req, _res, next) {
  try {
    const authHeader = req.headers.authorization || "";
    const token = authHeader.startsWith("Bearer ") ? authHeader.slice(7).trim() : null;
    if (token) {
      const payload = jwt.verify(token, getJwtSecret());
      const user = await authModel.findUserById(payload.sub);
      if (user && user.is_active) req.user = user;
    }
  } catch {
    // Guest checkout continues cleanly when token absent or invalid
  }
  next();
}

module.exports = { getJwtSecret, authenticate, optionalAuthenticate, requireRole };

