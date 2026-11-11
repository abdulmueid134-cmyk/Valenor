// CLI utility to create or promote an administrator securely (never stores plain text passwords)
const bcrypt = require("bcryptjs");
const mysql = require("mysql2/promise");
require("dotenv").config();

function getArg(flag, fallback = "") {
  const prefix = `${flag}=`;
  const hit = process.argv.slice(2).find((a) => a.startsWith(prefix));
  return hit ? hit.slice(prefix.length) : fallback;
}

async function main() {
  const fullName = getArg("--name", process.env.ADMIN_NAME || "VALENOR Administrator").trim();
  const email = getArg("--email", process.env.ADMIN_EMAIL || "").trim().toLowerCase();
  const phone = getArg("--phone", process.env.ADMIN_PHONE || "+923000000001").trim();
  const password = getArg("--password", process.env.ADMIN_PASSWORD || "");
  const roleName = getArg("--role", process.env.ADMIN_ROLE || "super_admin").trim();

  const allowedRoles = ["super_admin", "manager", "order_manager", "inventory_manager"];
  if (!allowedRoles.includes(roleName)) {
    throw new Error(`Role must be one of: ${allowedRoles.join(", ")}`);
  }
  if (!email || !email.includes("@")) {
    throw new Error("Pass --email=admin@domain.com or set ADMIN_EMAIL.");
  }
  if (!password || password.length < 8) {
    throw new Error("Pass --password=<min 8 chars> or set ADMIN_PASSWORD.");
  }

  const conn = await mysql.createConnection({
    host: process.env.DB_HOST || "localhost",
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || "root",
    password: process.env.DB_PASSWORD || "",
    database: process.env.DB_NAME || "valenor_watches"
  });

  try {
    const [[role]] = await conn.query("SELECT id FROM roles WHERE name = ? LIMIT 1", [roleName]);
    if (!role) throw new Error(`Role "${roleName}" not seeded in roles table.`);

    const hash = await bcrypt.hash(password, 12);
    const [[existing]] = await conn.query("SELECT id FROM users WHERE email = ? LIMIT 1", [email]);

    if (existing) {
      await conn.execute(
        "UPDATE users SET role_id = ?, full_name = ?, phone = ?, password_hash = ?, is_active = 1 WHERE id = ?",
        [role.id, fullName, phone, hash, existing.id]
      );
      console.log(`Updated admin user ${email} (${roleName}, id=${existing.id}).`);
    } else {
      const [res] = await conn.execute(
        "INSERT INTO users (role_id, full_name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 1)",
        [role.id, fullName, email, phone, hash]
      );
      console.log(`Created admin user ${email} (${roleName}, id=${res.insertId}).`);
    }
  } finally {
    await conn.end();
  }
}

main().catch((err) => {
  console.error("create_admin failed:", err.message);
  process.exit(1);
});

