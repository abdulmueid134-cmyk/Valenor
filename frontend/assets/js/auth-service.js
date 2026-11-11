// VALENOR Horology — Customer Authentication & Account API Service
// Calls /api/auth/* & /api/account/* when served by Node/Express; falls back to salted local crypto in static preview.

window.VALENOR_AUTH = (() => {
  const SESSION_KEY = "valenor_session_v1";
  const USERS_KEY = "valenor_users_v1";
  const ADDR_KEY = "valenor_addresses_v1";
  const RESETS_KEY = "valenor_resets_v1";

  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const PHONE_RE = /^[+\d][\d\s-]{7,18}$/;

  function readStorage(storage, key, fallback) {
    try {
      const raw = storage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch {
      return fallback;
    }
  }

  function writeStorage(storage, key, val) {
    try {
      storage.setItem(key, JSON.stringify(val));
    } catch {
      // Ignore restricted quota
    }
  }

  function getSession() {
    return readStorage(localStorage, SESSION_KEY, null) || readStorage(sessionStorage, SESSION_KEY, null);
  }

  function saveSession(sessionObj, rememberMe) {
    localStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem(SESSION_KEY);
    writeStorage(rememberMe ? localStorage : sessionStorage, SESSION_KEY, sessionObj);
  }

  function isAuthenticated() {
    return Boolean(getSession()?.token && getSession()?.user);
  }

  function logout() {
    localStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem(SESSION_KEY);
  }

  async function hashPassword(password, salt) {
    const input = `${salt}:${password}`;
    if (window.crypto?.subtle && window.TextEncoder) {
      const bytes = new TextEncoder().encode(input);
      const digest = await window.crypto.subtle.digest("SHA-256", bytes);
      return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, "0")).join("");
    }
    // Lightweight fallback hash for file:// contexts without WebCrypto subtle
    let h = 2166136261;
    for (let i = 0; i < input.length; i++) {
      h ^= input.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return `fnv_${(h >>> 0).toString(16)}`;
  }

  function makeSalt() {
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
  }

  async function apiRequest(path, method = "GET", body = null) {
    if (!/^https?:/.test(window.location.protocol)) return { unavailable: true };
    try {
      const session = getSession();
      const headers = { "Content-Type": "application/json", Accept: "application/json" };
      if (session?.token) headers.Authorization = `Bearer ${session.token}`;
      const res = await fetch(path, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined
      });
      // Static preview server returns 404 or non-JSON for /api/*
      const ct = res.headers.get("content-type") || "";
      if (!ct.includes("application/json")) return { unavailable: true };
      const json = await res.json();
      return { ok: res.ok, status: res.status, data: json };
    } catch {
      return { unavailable: true };
    }
  }

  async function register({ fullName, email, phone, password, confirmPassword }) {
    const cleanName = String(fullName || "").trim();
    const cleanEmail = String(email || "").trim().toLowerCase();
    const cleanPhone = String(phone || "").trim();

    if (cleanName.length < 2) throw new Error("Please enter your full name.");
    if (!EMAIL_RE.test(cleanEmail)) throw new Error("Please enter a valid email address.");
    if (!PHONE_RE.test(cleanPhone)) throw new Error("Please enter a valid phone number.");
    if (!password || password.length < 8) throw new Error("Password must be at least 8 characters.");
    if (password !== confirmPassword) throw new Error("Passwords do not match.");

    const api = await apiRequest("/api/auth/register", "POST", {
      fullName: cleanName,
      email: cleanEmail,
      phone: cleanPhone,
      password,
      confirmPassword
    });
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Registration failed.");
      saveSession({ token: api.data.token, user: api.data.user }, true);
      return api.data.user;
    }

    const users = readStorage(localStorage, USERS_KEY, []);
    if (users.some((u) => u.email === cleanEmail || u.phone === cleanPhone)) {
      throw new Error("An account with this email or phone already exists.");
    }

    const salt = makeSalt();
    const passwordHash = await hashPassword(password, salt);
    const newUser = {
      id: Date.now(),
      role: "customer",
      full_name: cleanName,
      email: cleanEmail,
      phone: cleanPhone,
      newsletter_opt_in: false,
      preferred_currency: "PKR",
      created_at: new Date().toISOString()
    };

    users.push({ ...newUser, salt, password_hash: passwordHash });
    writeStorage(localStorage, USERS_KEY, users);
    saveSession({ token: `local_${makeSalt()}`, user: newUser, rememberMe: true }, true);
    return newUser;
  }

  async function login({ identifier, password, rememberMe = false }) {
    const cleanId = String(identifier || "").trim();
    if (!cleanId || !password) throw new Error("Enter your email or phone number and password.");

    const api = await apiRequest("/api/auth/login", "POST", { identifier: cleanId, password, rememberMe });
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Invalid credentials.");
      saveSession({ token: api.data.token, user: api.data.user, rememberMe }, rememberMe);
      return api.data.user;
    }

    const users = readStorage(localStorage, USERS_KEY, []);
    const normPhone = (s) => String(s || "").replace(/[\s-]/g, "");
    const match = users.find((u) => u.email === cleanId.toLowerCase() || normPhone(u.phone) === normPhone(cleanId));
    if (!match) throw new Error("Invalid email/phone or password.");

    const checkHash = await hashPassword(password, match.salt);
    if (checkHash !== match.password_hash) throw new Error("Invalid email/phone or password.");

    const { password_hash: _p, salt: _s, ...safeUser } = match;
    saveSession({ token: `local_${makeSalt()}`, user: safeUser, rememberMe }, rememberMe);
    return safeUser;
  }

  async function requestPasswordReset(identifier) {
    const cleanId = String(identifier || "").trim();
    if (!cleanId) throw new Error("Enter your registered email or phone number.");

    const api = await apiRequest("/api/auth/forgot-password", "POST", { identifier: cleanId });
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Unable to request reset.");
      return { message: api.data.message };
    }

    const users = readStorage(localStorage, USERS_KEY, []);
    const normPhone = (s) => String(s || "").replace(/[\s-]/g, "");
    const match = users.find((u) => u.email === cleanId.toLowerCase() || normPhone(u.phone) === normPhone(cleanId));
    if (!match) {
      return { message: "If a matching account exists, a reset code has been generated." };
    }

    const code = String(Math.floor(100000 + Math.random() * 900000));
    const resets = readStorage(localStorage, RESETS_KEY, {});
    resets[code] = { userId: match.id, expiresAt: Date.now() + 30 * 60 * 1000 };
    writeStorage(localStorage, RESETS_KEY, resets);
    return {
      message: `Password reset code generated for static preview: ${code} (valid 30 minutes).`,
      previewCode: code
    };
  }

  async function resetPassword({ token, password, confirmPassword }) {
    const cleanToken = String(token || "").trim();
    if (!cleanToken) throw new Error("Verification code is required.");
    if (!password || password.length < 8) throw new Error("Password must be at least 8 characters.");
    if (password !== confirmPassword) throw new Error("Passwords do not match.");

    const api = await apiRequest("/api/auth/reset-password", "POST", { token: cleanToken, password, confirmPassword });
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Invalid reset token.");
      return api.data.message;
    }

    const resets = readStorage(localStorage, RESETS_KEY, {});
    const entry = resets[cleanToken];
    if (!entry || entry.expiresAt < Date.now()) {
      throw new Error("Invalid or expired reset code.");
    }

    const users = readStorage(localStorage, USERS_KEY, []);
    const idx = users.findIndex((u) => u.id === entry.userId);
    if (idx === -1) throw new Error("Account not found.");

    const salt = makeSalt();
    users[idx].salt = salt;
    users[idx].password_hash = await hashPassword(password, salt);
    writeStorage(localStorage, USERS_KEY, users);
    delete resets[cleanToken];
    writeStorage(localStorage, RESETS_KEY, resets);
    return "Password updated cleanly. Please sign in with your new password.";
  }

  async function updateProfile({ fullName, email, phone, newsletterOptIn, preferredCurrency }) {
    const sess = getSession();
    if (!sess) throw new Error("Session expired.");

    const cleanName = fullName !== undefined ? String(fullName).trim() : sess.user.full_name;
    const cleanEmail = email !== undefined ? String(email).trim().toLowerCase() : sess.user.email;
    const cleanPhone = phone !== undefined ? String(phone).trim() : sess.user.phone;

    if (cleanName.length < 2) throw new Error("Full name must be at least 2 characters.");
    if (!EMAIL_RE.test(cleanEmail)) throw new Error("Valid email address is required.");
    if (!PHONE_RE.test(cleanPhone)) throw new Error("Valid phone number is required.");

    const api = await apiRequest("/api/account/profile", "PUT", {
      fullName: cleanName,
      email: cleanEmail,
      phone: cleanPhone,
      newsletterOptIn,
      preferredCurrency
    });
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Update failed.");
      saveSession({ ...sess, user: api.data.user }, Boolean(sess.rememberMe));
      return api.data.user;
    }

    const users = readStorage(localStorage, USERS_KEY, []);
    const idx = users.findIndex((u) => u.id === sess.user.id);
    if (idx === -1) throw new Error("Account not found.");

    users[idx].full_name = cleanName;
    users[idx].email = cleanEmail;
    users[idx].phone = cleanPhone;
    if (typeof newsletterOptIn === "boolean") users[idx].newsletter_opt_in = newsletterOptIn;
    if (preferredCurrency) users[idx].preferred_currency = preferredCurrency;
    writeStorage(localStorage, USERS_KEY, users);

    const { password_hash: _p, salt: _s, ...safeUser } = users[idx];
    saveSession({ ...sess, user: safeUser }, Boolean(sess.rememberMe));
    return safeUser;
  }

  async function changePassword({ currentPassword, newPassword, confirmPassword }) {
    if (!newPassword || newPassword.length < 8) throw new Error("New password must be at least 8 characters.");
    if (newPassword !== confirmPassword) throw new Error("New passwords do not match.");

    const api = await apiRequest("/api/account/password", "PUT", { currentPassword, newPassword, confirmPassword });
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Could not change password.");
      return api.data.message;
    }

    const sess = getSession();
    const users = readStorage(localStorage, USERS_KEY, []);
    const idx = users.findIndex((u) => u.id === sess?.user?.id);
    if (idx === -1) throw new Error("Account not found.");

    const valid = (await hashPassword(currentPassword, users[idx].salt)) === users[idx].password_hash;
    if (!valid) throw new Error("Current password is incorrect.");

    const salt = makeSalt();
    users[idx].salt = salt;
    users[idx].password_hash = await hashPassword(newPassword, salt);
    writeStorage(localStorage, USERS_KEY, users);
    return "Password updated successfully.";
  }

  async function listAddresses() {
    const sess = getSession();
    if (!sess) return [];
    const api = await apiRequest("/api/account/addresses", "GET");
    if (!api.unavailable && api.ok) return api.data.addresses || [];

    const all = readStorage(localStorage, ADDR_KEY, {});
    return (all[sess.user.id] || []).slice().sort((a, b) => Number(b.is_default) - Number(a.is_default));
  }

  async function saveAddress(addr, editId = null) {
    const sess = getSession();
    if (!sess) throw new Error("Sign in required.");

    const payload = {
      fullName: String(addr.fullName || "").trim(),
      phone: String(addr.phone || "").trim(),
      city: String(addr.city || "").trim(),
      area: String(addr.area || "").trim(),
      streetAddress: String(addr.streetAddress || "").trim(),
      postalCode: String(addr.postalCode || "").trim(),
      deliveryInstructions: String(addr.deliveryInstructions || "").trim(),
      isDefault: Boolean(addr.isDefault)
    };
    if (payload.fullName.length < 2 || !PHONE_RE.test(payload.phone) || !payload.city || !payload.area || payload.streetAddress.length < 4) {
      throw new Error("Please complete full name, valid phone, city, area, and street address.");
    }

    const api = await apiRequest(
      editId ? `/api/account/addresses/${editId}` : "/api/account/addresses",
      editId ? "PUT" : "POST",
      payload
    );
    if (!api.unavailable) {
      if (!api.ok) throw new Error(api.data?.message || "Failed to save address.");
      return listAddresses();
    }

    const all = readStorage(localStorage, ADDR_KEY, {});
    const list = all[sess.user.id] || [];
    const shouldDefault = payload.isDefault || list.length === 0;
    if (shouldDefault) list.forEach((a) => { a.is_default = false; });

    const record = {
      id: editId ? Number(editId) : Date.now(),
      full_name: payload.fullName,
      phone: payload.phone,
      city: payload.city,
      area: payload.area,
      street_address: payload.streetAddress,
      postal_code: payload.postalCode,
      delivery_instructions: payload.deliveryInstructions,
      is_default: shouldDefault
    };

    if (editId) {
      const idx = list.findIndex((a) => a.id === Number(editId));
      if (idx !== -1) list[idx] = { ...list[idx], ...record, is_default: shouldDefault || list[idx].is_default };
    } else {
      list.push(record);
    }

    all[sess.user.id] = list;
    writeStorage(localStorage, ADDR_KEY, all);
    return listAddresses();
  }

  async function setDefaultAddress(addressId) {
    const sess = getSession();
    if (!sess) return [];
    const api = await apiRequest(`/api/account/addresses/${addressId}/default`, "PATCH");
    if (!api.unavailable && api.ok) return api.data.addresses || [];

    const all = readStorage(localStorage, ADDR_KEY, {});
    const list = all[sess.user.id] || [];
    list.forEach((a) => { a.is_default = a.id === Number(addressId); });
    all[sess.user.id] = list;
    writeStorage(localStorage, ADDR_KEY, all);
    return listAddresses();
  }

  async function deleteAddress(addressId) {
    const sess = getSession();
    if (!sess) return [];
    const api = await apiRequest(`/api/account/addresses/${addressId}`, "DELETE");
    if (!api.unavailable && api.ok) return api.data.addresses || [];

    const all = readStorage(localStorage, ADDR_KEY, {});
    const next = (all[sess.user.id] || []).filter((a) => a.id !== Number(addressId));
    if (next.length && !next.some((a) => a.is_default)) next[0].is_default = true;
    all[sess.user.id] = next;
    writeStorage(localStorage, ADDR_KEY, all);
    return listAddresses();
  }

  return {
    getSession,
    isAuthenticated,
    logout,
    register,
    login,
    requestPasswordReset,
    resetPassword,
    updateProfile,
    changePassword,
    listAddresses,
    saveAddress,
    setDefaultAddress,
    deleteAddress
  };
})();
