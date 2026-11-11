const crypto = require("crypto");
const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");
const authModel = require("../models/authModel");
const { getJwtSecret } = require("../middleware/authMiddleware");

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_RE = /^[+\d][\d\s-]{7,18}$/;

function signToken(user, rememberMe = false) {
  return jwt.sign({ sub: user.id, role: user.role }, getJwtSecret(), {
    expiresIn: rememberMe ? "30d" : "1d"
  });
}

async function register(req, res, next) {
  try {
    const fullName = String(req.body.fullName || "").trim();
    const email = String(req.body.email || "").trim().toLowerCase();
    const phone = String(req.body.phone || "").trim();
    const password = String(req.body.password || "");
    const confirmPassword = String(req.body.confirmPassword || "");

    if (fullName.length < 2) return res.status(400).json({ success: false, message: "Full name is required." });
    if (!EMAIL_RE.test(email)) return res.status(400).json({ success: false, message: "Valid email address is required." });
    if (!PHONE_RE.test(phone)) return res.status(400).json({ success: false, message: "Valid phone number is required." });
    if (password.length < 8) return res.status(400).json({ success: false, message: "Password must be at least 8 characters." });
    if (password !== confirmPassword) return res.status(400).json({ success: false, message: "Passwords do not match." });

    const existingEmail = await authModel.findAuthRowByIdentifier(email);
    const existingPhone = await authModel.findAuthRowByIdentifier(phone);
    if (existingEmail || existingPhone) {
      return res.status(409).json({ success: false, message: "An account with this email or phone already exists." });
    }

    const passwordHash = await bcrypt.hash(password, 10);
    const user = await authModel.createCustomerUser({ fullName, email, phone, passwordHash });
    const token = signToken(user, false);

    res.status(201).json({ success: true, token, user });
  } catch (err) {
    next(err);
  }
}

async function login(req, res, next) {
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

    const { password_hash: _omit, ...safeUser } = authUser;
    const token = signToken(safeUser, rememberMe);
    res.json({ success: true, token, user: safeUser });
  } catch (err) {
    next(err);
  }
}

async function forgotPassword(req, res, next) {
  try {
    const identifier = String(req.body.identifier || "").trim();
    if (!identifier) {
      return res.status(400).json({ success: false, message: "Enter your email or phone number." });
    }

    const user = await authModel.findAuthRowByIdentifier(identifier);
    if (user && user.is_active) {
      const rawToken = crypto.randomBytes(16).toString("hex");
      const tokenHash = crypto.createHash("sha256").update(rawToken).digest("hex");
      const expiresAt = new Date(Date.now() + 30 * 60 * 1000);
      await authModel.createPasswordReset(user.id, tokenHash, expiresAt);
      // Production: send rawToken via email/SMS provider; never expose in production logs
    }

    res.json({
      success: true,
      message: "If a matching active account exists, password reset instructions have been issued."
    });
  } catch (err) {
    next(err);
  }
}

async function resetPassword(req, res, next) {
  try {
    const token = String(req.body.token || "").trim();
    const password = String(req.body.password || "");
    const confirmPassword = String(req.body.confirmPassword || "");

    if (!token) return res.status(400).json({ success: false, message: "Reset token is required." });
    if (password.length < 8) return res.status(400).json({ success: false, message: "Password must be at least 8 characters." });
    if (password !== confirmPassword) return res.status(400).json({ success: false, message: "Passwords do not match." });

    const tokenHash = crypto.createHash("sha256").update(token).digest("hex");
    const newHash = await bcrypt.hash(password, 10);
    const updated = await authModel.consumePasswordReset(tokenHash, newHash);

    if (!updated) {
      return res.status(400).json({ success: false, message: "Invalid or expired reset token." });
    }
    res.json({ success: true, message: "Password updated. You may now sign in." });
  } catch (err) {
    next(err);
  }
}

async function getProfile(req, res, next) {
  try {
    const addresses = await authModel.listAddresses(req.user.id);
    res.json({ success: true, user: req.user, addresses });
  } catch (err) {
    next(err);
  }
}

async function updateProfile(req, res, next) {
  try {
    const fullName = req.body.fullName !== undefined ? String(req.body.fullName).trim() : undefined;
    const email = req.body.email !== undefined ? String(req.body.email).trim().toLowerCase() : undefined;
    const phone = req.body.phone !== undefined ? String(req.body.phone).trim() : undefined;
    const newsletterOptIn = typeof req.body.newsletterOptIn === "boolean" ? req.body.newsletterOptIn : undefined;
    const preferredCurrency = req.body.preferredCurrency !== undefined ? String(req.body.preferredCurrency).trim() : undefined;

    if (fullName !== undefined && fullName.length < 2) {
      return res.status(400).json({ success: false, message: "Full name must be at least 2 characters." });
    }
    if (email !== undefined && !EMAIL_RE.test(email)) {
      return res.status(400).json({ success: false, message: "Invalid email format." });
    }
    if (phone !== undefined && !PHONE_RE.test(phone)) {
      return res.status(400).json({ success: false, message: "Invalid phone number." });
    }

    const user = await authModel.updateUserProfile(req.user.id, {
      fullName,
      email,
      phone,
      newsletterOptIn,
      preferredCurrency
    });
    res.json({ success: true, user });
  } catch (err) {
    next(err);
  }
}

async function changePassword(req, res, next) {
  try {
    const currentPassword = String(req.body.currentPassword || "");
    const newPassword = String(req.body.newPassword || "");
    const confirmPassword = String(req.body.confirmPassword || "");

    if (newPassword.length < 8) return res.status(400).json({ success: false, message: "New password must be at least 8 characters." });
    if (newPassword !== confirmPassword) return res.status(400).json({ success: false, message: "Passwords do not match." });

    const authUser = await authModel.findAuthRowByIdentifier(req.user.email);
    const valid = await bcrypt.compare(currentPassword, authUser.password_hash);
    if (!valid) return res.status(400).json({ success: false, message: "Current password is incorrect." });

    const hash = await bcrypt.hash(newPassword, 10);
    await authModel.updateUserPassword(req.user.id, hash);
    res.json({ success: true, message: "Password updated successfully." });
  } catch (err) {
    next(err);
  }
}

function validateAddressBody(body) {
  const fullName = String(body.fullName || "").trim();
  const phone = String(body.phone || "").trim();
  const city = String(body.city || "").trim();
  const area = String(body.area || "").trim();
  const streetAddress = String(body.streetAddress || "").trim();
  const postalCode = String(body.postalCode || "").trim();
  const deliveryInstructions = String(body.deliveryInstructions || "").trim();
  const isDefault = Boolean(body.isDefault);

  if (fullName.length < 2 || !PHONE_RE.test(phone) || !city || !area || streetAddress.length < 4) {
    return null;
  }
  return { fullName, phone, city, area, streetAddress, postalCode, deliveryInstructions, isDefault };
}

async function listAddresses(req, res, next) {
  try {
    const addresses = await authModel.listAddresses(req.user.id);
    res.json({ success: true, addresses });
  } catch (err) {
    next(err);
  }
}

async function addAddress(req, res, next) {
  try {
    const addr = validateAddressBody(req.body);
    if (!addr) return res.status(400).json({ success: false, message: "Complete name, phone, city, area, and street address are required." });
    const address = await authModel.createAddress(req.user.id, addr);
    res.status(201).json({ success: true, address });
  } catch (err) {
    next(err);
  }
}

async function editAddress(req, res, next) {
  try {
    const addr = validateAddressBody(req.body);
    if (!addr) return res.status(400).json({ success: false, message: "Valid address details are required." });
    const ok = await authModel.updateAddress(req.user.id, Number(req.params.id), addr);
    if (!ok) return res.status(404).json({ success: false, message: "Address not found." });
    const addresses = await authModel.listAddresses(req.user.id);
    res.json({ success: true, addresses });
  } catch (err) {
    next(err);
  }
}

async function setDefaultAddress(req, res, next) {
  try {
    const ok = await authModel.setDefaultAddress(req.user.id, Number(req.params.id));
    if (!ok) return res.status(404).json({ success: false, message: "Address not found." });
    const addresses = await authModel.listAddresses(req.user.id);
    res.json({ success: true, addresses });
  } catch (err) {
    next(err);
  }
}

async function removeAddress(req, res, next) {
  try {
    const ok = await authModel.deleteAddress(req.user.id, Number(req.params.id));
    if (!ok) return res.status(404).json({ success: false, message: "Address not found." });
    const addresses = await authModel.listAddresses(req.user.id);
    res.json({ success: true, addresses });
  } catch (err) {
    next(err);
  }
}

module.exports = {
  register,
  login,
  forgotPassword,
  resetPassword,
  getProfile,
  updateProfile,
  changePassword,
  listAddresses,
  addAddress,
  editAddress,
  setDefaultAddress,
  removeAddress
};

