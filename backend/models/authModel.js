const pool = require("../config/db");

const SAFE_USER_COLS = `
  u.id, u.full_name, u.email, u.phone, u.is_active,
  u.newsletter_opt_in, u.preferred_currency, u.created_at,
  r.name AS role
`;

async function findUserById(id) {
  const [[user]] = await pool.query(
    `SELECT ${SAFE_USER_COLS}
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.id = ? LIMIT 1`,
    [id]
  );
  return user || null;
}

async function findAuthRowByIdentifier(identifier) {
  const clean = String(identifier || "").trim();
  const cleanPhone = clean.replace(/[\s-]/g, "");
  const [[user]] = await pool.query(
    `SELECT ${SAFE_USER_COLS}, u.password_hash
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.email = ? OR REPLACE(REPLACE(u.phone, ' ', ''), '-', '') = ?
     LIMIT 1`,
    [clean.toLowerCase(), cleanPhone]
  );
  return user || null;
}

async function createCustomerUser({ fullName, email, phone, passwordHash }) {
  const [[roleRow]] = await pool.query("SELECT id FROM roles WHERE name = 'customer' LIMIT 1");
  if (!roleRow) throw new Error("Customer role missing in roles table.");

  const [res] = await pool.execute(
    `INSERT INTO users (role_id, full_name, email, phone, password_hash)
     VALUES (?, ?, ?, ?, ?)`,
    [roleRow.id, fullName, email.toLowerCase(), phone, passwordHash]
  );
  return findUserById(res.insertId);
}

async function updateUserProfile(userId, { fullName, email, phone, newsletterOptIn, preferredCurrency }) {
  await pool.execute(
    `UPDATE users
     SET full_name = COALESCE(?, full_name),
         email = COALESCE(?, email),
         phone = COALESCE(?, phone),
         newsletter_opt_in = COALESCE(?, newsletter_opt_in),
         preferred_currency = COALESCE(?, preferred_currency)
     WHERE id = ?`,
    [
      fullName ?? null,
      email ? email.toLowerCase() : null,
      phone ?? null,
      typeof newsletterOptIn === "boolean" ? Number(newsletterOptIn) : null,
      preferredCurrency ?? null,
      userId
    ]
  );
  return findUserById(userId);
}

async function updateUserPassword(userId, passwordHash) {
  await pool.execute("UPDATE users SET password_hash = ? WHERE id = ?", [passwordHash, userId]);
}

async function createPasswordReset(userId, tokenHash, expiresAt) {
  await pool.execute(
    "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
    [userId, tokenHash, expiresAt]
  );
}

async function consumePasswordReset(tokenHash, newPasswordHash) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[row]] = await conn.query(
      `SELECT id, user_id FROM password_resets
       WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
       ORDER BY id DESC LIMIT 1 FOR UPDATE`,
      [tokenHash]
    );
    if (!row) {
      await conn.rollback();
      return false;
    }
    await conn.execute("UPDATE users SET password_hash = ? WHERE id = ?", [newPasswordHash, row.user_id]);
    await conn.execute("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [row.id]);
    await conn.commit();
    return true;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function listAddresses(userId) {
  const [rows] = await pool.query(
    `SELECT id, full_name, phone, city, area, street_address, postal_code, delivery_instructions, is_default, created_at
     FROM user_addresses
     WHERE user_id = ?
     ORDER BY is_default DESC, id DESC`,
    [userId]
  );
  return rows;
}

async function createAddress(userId, addr) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[existing]] = await conn.query("SELECT COUNT(*) AS cnt FROM user_addresses WHERE user_id = ?", [userId]);
    const shouldDefault = addr.isDefault || existing.cnt === 0;
    if (shouldDefault) {
      await conn.execute("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?", [userId]);
    }
    const [res] = await conn.execute(
      `INSERT INTO user_addresses
       (user_id, full_name, phone, city, area, street_address, postal_code, delivery_instructions, is_default)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        userId,
        addr.fullName,
        addr.phone,
        addr.city,
        addr.area,
        addr.streetAddress,
        addr.postalCode || "",
        addr.deliveryInstructions || "",
        Number(shouldDefault)
      ]
    );
    await conn.commit();
    return listAddresses(userId).then((list) => list.find((a) => a.id === res.insertId));
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function updateAddress(userId, addressId, addr) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    if (addr.isDefault) {
      await conn.execute("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?", [userId]);
    }
    const [res] = await conn.execute(
      `UPDATE user_addresses
       SET full_name = ?, phone = ?, city = ?, area = ?, street_address = ?,
           postal_code = ?, delivery_instructions = ?, is_default = COALESCE(?, is_default)
       WHERE id = ? AND user_id = ?`,
      [
        addr.fullName,
        addr.phone,
        addr.city,
        addr.area,
        addr.streetAddress,
        addr.postalCode || "",
        addr.deliveryInstructions || "",
        typeof addr.isDefault === "boolean" ? Number(addr.isDefault) : null,
        addressId,
        userId
      ]
    );
    await conn.commit();
    return res.affectedRows > 0;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function setDefaultAddress(userId, addressId) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[owned]] = await conn.query("SELECT id FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1", [addressId, userId]);
    if (!owned) {
      await conn.rollback();
      return false;
    }
    await conn.execute("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?", [userId]);
    await conn.execute("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?", [addressId, userId]);
    await conn.commit();
    return true;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function deleteAddress(userId, addressId) {
  const [res] = await pool.execute("DELETE FROM user_addresses WHERE id = ? AND user_id = ?", [addressId, userId]);
  return res.affectedRows > 0;
}

module.exports = {
  findUserById,
  findAuthRowByIdentifier,
  createCustomerUser,
  updateUserProfile,
  updateUserPassword,
  createPasswordReset,
  consumePasswordReset,
  listAddresses,
  createAddress,
  updateAddress,
  setDefaultAddress,
  deleteAddress
};

