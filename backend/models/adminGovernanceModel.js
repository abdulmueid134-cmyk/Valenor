const bcrypt = require("bcryptjs");
const path = require("path");
const pool = require("../config/db");
const { AppError } = require("./checkoutModel");
const { resolveDateRange, logAdminActivity } = require("./adminDashboardModel");

const BRANDING_UPLOAD_DIR = path.resolve(__dirname, "..", "uploads", "branding");

const SETTING_KEYS = [
  "website_name", "brand_logo_url", "contact_info", "contact_email", "contact_phone",
  "social_instagram", "social_facebook", "social_youtube",
  "currency", "tax_rate_percent", "standard_shipping_fee", "express_shipping_fee", "free_shipping_threshold",
  "return_policy", "privacy_policy", "terms_conditions",
  "payment_cod_enabled", "payment_bank_enabled", "payment_online_enabled", "bank_transfer_details"
];

// ---------- Pure-JS RFC-4180 CSV & Valid PDF-1.4 Builders ----------
function buildCsvBuffer(columns, rows) {
  const escCsv = (val) => {
    const s = String(val ?? "");
    return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const header = columns.map((c) => escCsv(c.label)).join(",");
  const lines = rows.map((r) => columns.map((c) => escCsv(r[c.key])).join(","));
  return Buffer.from("\uFEFF" + [header, ...lines].join("\r\n"), "utf8");
}

function pdfSafeText(str, maxLen = 40) {
  const s = String(str ?? "")
    .replace(/[\u2014\u2013]/g, "-")
    .replace(/\u2022/g, "-")
    .replace(/[^\x20-\x7E]/g, " ")
    .trim();
  const clipped = s.length > maxLen ? `${s.slice(0, Math.max(1, maxLen - 3))}...` : s;
  return clipped.replace(/\\/g, "\\\\").replace(/\(/g, "\\(").replace(/\)/g, "\\)");
}

function buildPdfReportBuffer({ title, subtitle, columns, rows }) {
  // A4 Landscape: 842 x 595 pt
  const pageW = 842;
  const pageH = 595;
  const leftM = 36;
  const usableW = pageW - leftM * 2;
  const rowsPerPage = 25;
  const chunks = [];
  for (let i = 0; i < Math.max(1, rows.length); i += rowsPerPage) {
    chunks.push(rows.slice(i, i + rowsPerPage));
  }

  const colW = Math.floor(usableW / Math.max(1, columns.length));
  const charCap = Math.max(10, Math.floor(colW / 5.4));

  const objects = [];
  const addObj = (body) => {
    objects.push(body);
    return objects.length;
  };

  const fontRegId = addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
  const fontBoldId = addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");
  const pagesPlaceholderIdx = addObj(""); // ID 3 reserved for Pages root
  const pageIds = [];

  chunks.forEach((slice, pageIdx) => {
    const ops = [];
    // Dark Obsidian top accent bar
    ops.push("0.043 0.043 0.051 rg");
    ops.push(`0 ${pageH - 54} ${pageW} 54 re f`);
    ops.push("1 1 1 rg");
    ops.push(`BT /F2 15 Tf ${leftM} ${pageH - 28} Td (${pdfSafeText(`VALENOR HOROLOGY - ${title}`, 95)}) Tj ET`);
    ops.push("0.722 0.576 0.290 rg");
    ops.push(`BT /F1 9 Tf ${leftM} ${pageH - 44} Td (${pdfSafeText(`${subtitle} | Page ${pageIdx + 1} of ${chunks.length}`, 120)}) Tj ET`);

    // Column Header strip
    const headerY = pageH - 84;
    ops.push("0.96 0.96 0.95 rg");
    ops.push(`${leftM - 4} ${headerY - 6} ${usableW + 8} 20 re f`);
    ops.push("0.08 0.08 0.09 rg");
    columns.forEach((c, idx) => {
      const x = leftM + idx * colW;
      ops.push(`BT /F2 8.5 Tf ${x} ${headerY} Td (${pdfSafeText(c.label.toUpperCase(), charCap)}) Tj ET`);
    });

    // Data rows
    if (!slice.length) {
      ops.push(`BT /F1 9.5 Tf ${leftM} ${headerY - 26} Td (No records matching selected report range.) Tj ET`);
    } else {
      slice.forEach((row, rIdx) => {
        const y = headerY - 24 - rIdx * 18;
        if (rIdx % 2 === 1) {
          ops.push("0.98 0.98 0.98 rg");
          ops.push(`${leftM - 4} ${y - 4} ${usableW + 8} 16 re f`);
        }
        ops.push("0.15 0.15 0.17 rg");
        columns.forEach((c, idx) => {
          const x = leftM + idx * colW;
          ops.push(`BT /F1 8.5 Tf ${x} ${y} Td (${pdfSafeText(row[c.key] ?? "-", charCap)}) Tj ET`);
        });
      });
    }

    const streamStr = ops.join("\n");
    const streamLen = Buffer.byteLength(streamStr, "latin1");
    const contentId = addObj(`<< /Length ${streamLen} >>\nstream\n${streamStr}\nendstream`);
    const pageId = addObj(
      `<< /Type /Page /Parent ${pagesPlaceholderIdx} 0 R /MediaBox [0 0 ${pageW} ${pageH}] /Resources << /Font << /F1 ${fontRegId} 0 R /F2 ${fontBoldId} 0 R >> >> /Contents ${contentId} 0 R >>`
    );
    pageIds.push(pageId);
  });

  objects[pagesPlaceholderIdx - 1] = `<< /Type /Pages /Kids [${pageIds.map((id) => `${id} 0 R`).join(" ")}] /Count ${pageIds.length} >>`;
  const catalogId = addObj(`<< /Type /Catalog /Pages ${pagesPlaceholderIdx} 0 R >>`);

  // Serialize with byte-accurate xref
  let pdf = "%PDF-1.4\n";
  const offsets = [0];
  for (let i = 0; i < objects.length; i++) {
    offsets.push(Buffer.byteLength(pdf, "latin1"));
    pdf += `${i + 1} 0 obj\n${objects[i]}\nendobj\n`;
  }
  const xrefPos = Buffer.byteLength(pdf, "latin1");
  pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
  for (let i = 1; i < offsets.length; i++) {
    pdf += `${String(offsets[i]).padStart(10, "0")} 00000 n \n`;
  }
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root ${catalogId} 0 R >>\nstartxref\n${xrefPos}\n%%EOF`;

  return Buffer.from(pdf, "latin1");
}

// ---------- All 11 Report Queries ----------
async function generateReportData(reportType, { range = "30d", startDate = "", endDate = "" } = {}) {
  const win = resolveDateRange(range, startDate, endDate);
  const { startSql, endSql } = win;

  const defs = {
    daily_sales: async () => {
      const [rows] = await pool.query(
        `SELECT DATE_FORMAT(DATE(created_at), '%Y-%m-%d') AS date,
                COUNT(*) AS orders,
                SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN order_status IN ('Cancelled','Rejected') THEN 1 ELSE 0 END) AS voided,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled','Rejected','Refunded') THEN total_amount ELSE 0 END), 0) AS net_sales
         FROM orders WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY date DESC`,
        [startSql, endSql]
      );
      return {
        title: "Daily Sales Report",
        columns: [
          { key: "date", label: "Date" }, { key: "orders", label: "Orders" },
          { key: "delivered", label: "Delivered" }, { key: "voided", label: "Cancel/Reject" }, { key: "net_sales", label: "Net Sales" }
        ],
        rows
      };
    },
    weekly_sales: async () => {
      const [rows] = await pool.query(
        `SELECT CONCAT('W', LPAD(MOD(YEARWEEK(created_at, 1), 100), 2, '0'), ' ', FLOOR(YEARWEEK(created_at, 1) / 100)) AS week,
                YEARWEEK(created_at, 1) AS sort_k,
                COUNT(*) AS orders,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled','Rejected','Refunded') THEN total_amount ELSE 0 END), 0) AS net_sales
         FROM orders WHERE created_at BETWEEN ? AND ?
         GROUP BY sort_k, week ORDER BY sort_k DESC`,
        [startSql, endSql]
      );
      return {
        title: "Weekly Sales Report",
        columns: [{ key: "week", label: "ISO Week" }, { key: "orders", label: "Orders" }, { key: "net_sales", label: "Net Sales" }],
        rows
      };
    },
    monthly_sales: async () => {
      const [rows] = await pool.query(
        `SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                COUNT(*) AS orders,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled','Rejected','Refunded') THEN total_amount ELSE 0 END), 0) AS net_sales
         FROM orders WHERE created_at BETWEEN ? AND ?
         GROUP BY month ORDER BY month DESC`,
        [startSql, endSql]
      );
      return {
        title: "Monthly Sales Report",
        columns: [{ key: "month", label: "Month" }, { key: "orders", label: "Orders" }, { key: "net_sales", label: "Net Sales" }],
        rows
      };
    },
    yearly_sales: async () => {
      const [rows] = await pool.query(
        `SELECT CAST(YEAR(created_at) AS CHAR) AS year,
                COUNT(*) AS orders,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('Cancelled','Rejected','Refunded') THEN total_amount ELSE 0 END), 0) AS net_sales
         FROM orders WHERE created_at BETWEEN ? AND ?
         GROUP BY YEAR(created_at) ORDER BY year DESC`,
        [startSql, endSql]
      );
      return {
        title: "Yearly Sales Report",
        columns: [{ key: "year", label: "Year" }, { key: "orders", label: "Orders" }, { key: "net_sales", label: "Net Sales" }],
        rows
      };
    },
    orders: async () => {
      const [rows] = await pool.query(
        `SELECT o.order_number, o.customer_name, o.customer_phone, o.payment_method,
                o.payment_status, o.order_status, o.total_amount, DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i') AS created_at
         FROM orders o WHERE o.created_at BETWEEN ? AND ?
         ORDER BY o.created_at DESC`,
        [startSql, endSql]
      );
      return {
        title: "Order Report",
        columns: [
          { key: "order_number", label: "Order #" }, { key: "customer_name", label: "Customer" },
          { key: "customer_phone", label: "Phone" }, { key: "payment_method", label: "Pay Method" },
          { key: "payment_status", label: "Pay Status" }, { key: "order_status", label: "Order Status" },
          { key: "total_amount", label: "Total" }, { key: "created_at", label: "Date" }
        ],
        rows
      };
    },
    returns: async () => {
      const [rows] = await pool.query(
        `SELECT r.return_number, o.order_number, o.customer_name, r.reason, r.status,
                DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i') AS created_at
         FROM returns r JOIN orders o ON o.id = r.order_id
         WHERE r.created_at BETWEEN ? AND ? ORDER BY r.created_at DESC`,
        [startSql, endSql]
      );
      return {
        title: "Return Requests Report",
        columns: [
          { key: "return_number", label: "Return #" }, { key: "order_number", label: "Order #" },
          { key: "customer_name", label: "Customer" }, { key: "reason", label: "Reason" },
          { key: "status", label: "Status" }, { key: "created_at", label: "Requested At" }
        ],
        rows
      };
    },
    rejected_orders: async () => {
      const [rows] = await pool.query(
        `SELECT o.order_number, o.customer_name, o.customer_phone, o.total_amount,
                COALESCE((SELECT osh.reason FROM order_status_history osh WHERE osh.order_id = o.id AND osh.new_status = 'Rejected' ORDER BY osh.id DESC LIMIT 1), 'Rejected') AS reason,
                DATE_FORMAT(o.updated_at, '%Y-%m-%d %H:%i') AS updated_at
         FROM orders o WHERE o.order_status = 'Rejected' AND o.created_at BETWEEN ? AND ?
         ORDER BY o.updated_at DESC`,
        [startSql, endSql]
      );
      return {
        title: "Rejected Orders Report",
        columns: [
          { key: "order_number", label: "Order #" }, { key: "customer_name", label: "Customer" },
          { key: "customer_phone", label: "Phone" }, { key: "total_amount", label: "Amount" },
          { key: "reason", label: "Rejection Reason" }, { key: "updated_at", label: "Rejected At" }
        ],
        rows
      };
    },
    customers: async () => {
      const [rows] = await pool.query(
        `SELECT u.id, u.full_name, u.email, u.phone,
                CASE WHEN u.is_active = 1 THEN 'Active' ELSE 'Blocked' END AS status,
                COUNT(DISTINCT o.id) AS orders,
                COALESCE(SUM(CASE WHEN o.order_status NOT IN ('Cancelled','Rejected','Refunded') THEN o.total_amount ELSE 0 END), 0) AS total_spent
         FROM users u JOIN roles r ON r.id = u.role_id
         LEFT JOIN orders o ON o.user_id = u.id
         WHERE r.name = 'customer'
         GROUP BY u.id ORDER BY total_spent DESC, orders DESC`
      );
      return {
        title: "Customer Directory Report",
        columns: [
          { key: "id", label: "ID" }, { key: "full_name", label: "Name" }, { key: "email", label: "Email" },
          { key: "phone", label: "Phone" }, { key: "status", label: "Status" },
          { key: "orders", label: "Orders" }, { key: "total_spent", label: "Lifetime Spend" }
        ],
        rows
      };
    },
    product_sales: async () => {
      const [rows] = await pool.query(
        `SELECT p.sku, p.name, p.watch_type, SUM(oi.quantity) AS units_sold,
                COALESCE(SUM(oi.line_total), 0) AS revenue,
                p.cost_price
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         JOIN products p ON p.id = oi.product_id
         WHERE o.created_at BETWEEN ? AND ? AND o.order_status NOT IN ('Cancelled','Rejected','Refunded')
         GROUP BY p.id ORDER BY revenue DESC`,
        [startSql, endSql]
      );
      return {
        title: "Product Sales Report",
        columns: [
          { key: "sku", label: "SKU" }, { key: "name", label: "Product" }, { key: "watch_type", label: "Type" },
          { key: "units_sold", label: "Units Sold" }, { key: "revenue", label: "Revenue" }, { key: "cost_price", label: "Unit Cost" }
        ],
        rows: rows.map((r) => ({ ...r, cost_price: r.cost_price === null ? "Not Set" : r.cost_price }))
      };
    },
    inventory: async () => {
      const [rows] = await pool.query(
        `SELECT p.sku, p.name, inv.available_stock, inv.reserved_stock, inv.sold_quantity,
                inv.returned_quantity, inv.low_stock_threshold,
                CASE WHEN inv.available_stock <= 0 THEN 'Out of Stock'
                     WHEN inv.available_stock <= inv.low_stock_threshold THEN 'Low Stock'
                     ELSE 'Healthy' END AS health
         FROM inventory inv JOIN products p ON p.id = inv.product_id
         ORDER BY inv.available_stock ASC`
      );
      return {
        title: "Inventory Valuation & Stock Report",
        columns: [
          { key: "sku", label: "SKU" }, { key: "name", label: "Product" }, { key: "available_stock", label: "Available" },
          { key: "reserved_stock", label: "Reserved" }, { key: "sold_quantity", label: "Sold" },
          { key: "returned_quantity", label: "Returned" }, { key: "health", label: "Health" }
        ],
        rows
      };
    },
    payments: async () => {
      const [rows] = await pool.query(
        `SELECT o.order_number, pay.method, pay.amount, pay.currency, pay.status,
                COALESCE(pay.reference_code, '-') AS reference_code,
                DATE_FORMAT(pay.created_at, '%Y-%m-%d %H:%i') AS created_at
         FROM payments pay JOIN orders o ON o.id = pay.order_id
         WHERE pay.created_at BETWEEN ? AND ? ORDER BY pay.created_at DESC`,
        [startSql, endSql]
      );
      return {
        title: "Payment Ledger Report",
        columns: [
          { key: "order_number", label: "Order #" }, { key: "method", label: "Method" }, { key: "amount", label: "Amount" },
          { key: "currency", label: "Currency" }, { key: "status", label: "Status" },
          { key: "reference_code", label: "Reference" }, { key: "created_at", label: "Date" }
        ],
        rows
      };
    }
  };

  const fn = defs[reportType];
  if (!fn) throw new AppError(400, `Unsupported report type "${reportType}".`);
  const report = await fn();
  return {
    type: reportType,
    ...report,
    subtitle: `${win.label} (${win.startDate} to ${win.endDate})`
  };
}

// ---------- Website Settings & Storefront Config ----------
async function getAllSettings() {
  const [rows] = await pool.query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key ASC");
  return Object.fromEntries(rows.map((r) => [r.setting_key, r.setting_value]));
}

async function updateSettings(payload, adminUserId) {
  for (const key of SETTING_KEYS) {
    if (payload[key] !== undefined) {
      await pool.execute(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [key, String(payload[key] ?? "").trim()]
      );
    }
  }
  await logAdminActivity({
    userId: adminUserId,
    action: "settings.update",
    entityType: "settings",
    details: `Updated settings (${Object.keys(payload || {}).filter((k) => SETTING_KEYS.includes(k)).join(", ")})`
  });
  return getAllSettings();
}

async function getPublicStorefrontConfig() {
  const settings = await getAllSettings();
  const [banners] = await pool.query(
    "SELECT id, title, subtitle, cta_text, cta_link, image_url, placement, sort_order FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
  );
  const [[campaign]] = await pool.query(
    `SELECT id, name, badge_text, headline, discount_percent, coupon_code, starts_at, ends_at
     FROM promotional_campaigns
     WHERE is_active = 1 AND ends_at >= NOW()
     ORDER BY id ASC LIMIT 1`
  );
  return {
    website_name: settings.website_name || "VALENOR Horology",
    brand_logo_url: settings.brand_logo_url || "assets/icons/favicon.svg",
    contact_info: settings.contact_info || "",
    contact_email: settings.contact_email || "concierge@valenor.pk",
    contact_phone: settings.contact_phone || "+92 300 048 1111",
    social_instagram: settings.social_instagram || "",
    social_facebook: settings.social_facebook || "",
    social_youtube: settings.social_youtube || "",
    currency: settings.currency || "PKR",
    return_policy: settings.return_policy || "",
    privacy_policy: settings.privacy_policy || "",
    terms_conditions: settings.terms_conditions || "",
    campaign: campaign || null,
    banners
  };
}

// ---------- Promotional Management (Campaigns, Banners & Featured Categories) ----------
async function getPromotionsOverview() {
  const [campaigns] = await pool.query("SELECT * FROM promotional_campaigns ORDER BY id DESC");
  const [banners] = await pool.query("SELECT * FROM banners ORDER BY sort_order ASC, id ASC");
  const [categories] = await pool.query("SELECT id, name, slug, is_featured, is_active FROM categories ORDER BY sort_order ASC");
  return { campaigns, banners, categories };
}

async function upsertCampaign(data, adminUserId) {
  const name = String(data.name || "").trim();
  const headline = String(data.headline || "").trim();
  if (!name || !headline || !data.starts_at || !data.ends_at) {
    throw new AppError(400, "Campaign name, headline, start date, and end date are required.");
  }
  const badgeText = String(data.badge_text || "PROMO").trim();
  const discPct = Math.min(95, Math.max(0, Math.floor(Number(data.discount_percent || 0))));
  const couponCode = data.coupon_code ? String(data.coupon_code).trim().toUpperCase() : null;
  const isActive = data.is_active === false || data.is_active === 0 ? 0 : 1;

  if (data.id) {
    await pool.execute(
      `UPDATE promotional_campaigns SET name = ?, badge_text = ?, headline = ?, discount_percent = ?,
       coupon_code = ?, starts_at = ?, ends_at = ?, is_active = ? WHERE id = ?`,
      [name, badgeText, headline, discPct, couponCode, data.starts_at, data.ends_at, isActive, Number(data.id)]
    );
    await logAdminActivity({ userId: adminUserId, action: "campaign.update", entityType: "campaign", entityId: data.id, details: name });
    return { id: Number(data.id), name };
  }
  const [res] = await pool.execute(
    `INSERT INTO promotional_campaigns (name, badge_text, headline, discount_percent, coupon_code, starts_at, ends_at, is_active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    [name, badgeText, headline, discPct, couponCode, data.starts_at, data.ends_at, isActive]
  );
  await logAdminActivity({ userId: adminUserId, action: "campaign.create", entityType: "campaign", entityId: res.insertId, details: name });
  return { id: res.insertId, name };
}

async function deleteCampaign(id, adminUserId) {
  await pool.execute("DELETE FROM promotional_campaigns WHERE id = ?", [Number(id)]);
  await logAdminActivity({ userId: adminUserId, action: "campaign.delete", entityType: "campaign", entityId: id });
  return { deleted: true };
}

async function upsertBanner(data, adminUserId) {
  const title = String(data.title || "").trim();
  const imageUrl = String(data.image_url || "assets/images/hero-banner.jpg").trim();
  const placement = ["hero", "mid_promo", "top_announcement"].includes(data.placement) ? data.placement : "hero";
  if (!title) throw new AppError(400, "Banner title is required.");
  const subtitle = String(data.subtitle || "").trim();
  const ctaText = String(data.cta_text || "Shop Collection").trim();
  const ctaLink = String(data.cta_link || "pages/catalog.html").trim();
  const sortOrder = Math.floor(Number(data.sort_order ?? 1));
  const isActive = data.is_active === false || data.is_active === 0 ? 0 : 1;

  if (data.id) {
    await pool.execute(
      `UPDATE banners SET title = ?, subtitle = ?, cta_text = ?, cta_link = ?, image_url = ?, placement = ?, sort_order = ?, is_active = ? WHERE id = ?`,
      [title, subtitle, ctaText, ctaLink, imageUrl, placement, sortOrder, isActive, Number(data.id)]
    );
    await logAdminActivity({ userId: adminUserId, action: "banner.update", entityType: "banner", entityId: data.id, details: title });
    return { id: Number(data.id), title };
  }
  const [res] = await pool.execute(
    `INSERT INTO banners (title, subtitle, cta_text, cta_link, image_url, placement, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    [title, subtitle, ctaText, ctaLink, imageUrl, placement, sortOrder, isActive]
  );
  await logAdminActivity({ userId: adminUserId, action: "banner.create", entityType: "banner", entityId: res.insertId, details: title });
  return { id: res.insertId, title };
}

async function deleteBanner(id, adminUserId) {
  await pool.execute("DELETE FROM banners WHERE id = ?", [Number(id)]);
  await logAdminActivity({ userId: adminUserId, action: "banner.delete", entityType: "banner", entityId: id });
  return { deleted: true };
}

async function setCategoryFeatured(categoryId, isFeatured, adminUserId) {
  await pool.execute("UPDATE categories SET is_featured = ? WHERE id = ?", [isFeatured ? 1 : 0, Number(categoryId)]);
  await logAdminActivity({ userId: adminUserId, action: "category.featured", entityType: "category", entityId: categoryId, details: `is_featured=${isFeatured}` });
  return { id: Number(categoryId), is_featured: isFeatured ? 1 : 0 };
}

// ---------- Admin Users, Role Permissions & Activity Audit Log (Super Admin) ----------
async function getGovernanceMatrix(q = "") {
  const [staff] = await pool.query(
    `SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.created_at, r.id AS role_id, r.name AS role
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.name IN ('super_admin', 'manager', 'order_manager', 'inventory_manager')
     ORDER BY u.id ASC`
  );
  const [roles] = await pool.query(
    "SELECT id, name, description FROM roles WHERE name <> 'customer' ORDER BY id ASC"
  );
  const [permissions] = await pool.query("SELECT id, code, description FROM permissions ORDER BY id ASC");
  const [rpRows] = await pool.query("SELECT role_id, permission_id FROM role_permissions");

  const permMap = {};
  for (const r of roles) permMap[r.id] = [];
  for (const rp of rpRows) {
    if (permMap[rp.role_id]) permMap[rp.role_id].push(rp.permission_id);
  }

  const cleanQ = String(q || "").trim();
  const like = `%${cleanQ}%`;
  const [auditLogs] = await pool.query(
    `SELECT a.id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at,
            u.full_name AS admin_name, u.email AS admin_email, r.name AS admin_role
     FROM admin_activity_logs a
     JOIN users u ON u.id = a.user_id
     JOIN roles r ON r.id = u.role_id
     WHERE (? = '' OR a.action LIKE ? OR a.details LIKE ? OR u.full_name LIKE ?)
     ORDER BY a.id DESC LIMIT 60`,
    [cleanQ, like, like, like]
  );

  return { staff, roles, permissions, rolePermissions: permMap, auditLogs };
}

async function saveAdminStaff({ id, fullName, email, phone, password, roleName, isActive = true }, superAdminId) {
  const allowedStaffRoles = ["super_admin", "manager", "order_manager", "inventory_manager"];
  if (!allowedStaffRoles.includes(roleName)) throw new AppError(400, "Invalid admin role.");
  const [[roleRow]] = await pool.query("SELECT id FROM roles WHERE name = ? LIMIT 1", [roleName]);
  if (!roleRow) throw new AppError(404, "Role not found.");

  const cleanName = String(fullName || "").trim();
  const cleanEmail = String(email || "").trim().toLowerCase();
  const cleanPhone = String(phone || "").trim();
  if (!cleanName || !cleanEmail || !cleanPhone) throw new AppError(400, "Full name, email, and phone are required.");

  if (id) {
    const uid = Number(id);
    if (uid === superAdminId && (!isActive || roleName !== "super_admin")) {
      throw new AppError(409, "Super Admin cannot demote or deactivate their own active account.");
    }
    if (password) {
      if (String(password).length < 8) throw new AppError(400, "New password must be at least 8 characters.");
      const hash = await bcrypt.hash(String(password), 12);
      await pool.execute(
        "UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, is_active = ?, password_hash = ? WHERE id = ?",
        [cleanName, cleanEmail, cleanPhone, roleRow.id, isActive ? 1 : 0, hash, uid]
      );
    } else {
      await pool.execute(
        "UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, is_active = ? WHERE id = ?",
        [cleanName, cleanEmail, cleanPhone, roleRow.id, isActive ? 1 : 0, uid]
      );
    }
    await logAdminActivity({ userId: superAdminId, action: "staff.update", entityType: "user", entityId: uid, details: `${cleanEmail} (${roleName})` });
    return { id: uid, full_name: cleanName, email: cleanEmail, role: roleName };
  }

  if (!password || String(password).length < 8) {
    throw new AppError(400, "Password of at least 8 characters is required for new admin users.");
  }
  const hash = await bcrypt.hash(String(password), 12);
  try {
    const [res] = await pool.execute(
      "INSERT INTO users (role_id, full_name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, ?, ?)",
      [roleRow.id, cleanName, cleanEmail, cleanPhone, hash, isActive ? 1 : 0]
    );
    await logAdminActivity({ userId: superAdminId, action: "staff.create", entityType: "user", entityId: res.insertId, details: `${cleanEmail} (${roleName})` });
    return { id: res.insertId, full_name: cleanName, email: cleanEmail, role: roleName };
  } catch (err) {
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "An account with that email or phone already exists.");
    throw err;
  }
}

async function updateRolePermissions(roleId, permissionIds, superAdminId) {
  const rid = Number(roleId);
  const [[role]] = await pool.query("SELECT id, name FROM roles WHERE id = ? LIMIT 1", [rid]);
  if (!role) throw new AppError(404, "Role not found.");
  if (role.name === "customer") throw new AppError(400, "Customer role does not hold admin permissions.");
  if (role.name === "super_admin") throw new AppError(409, "Super Admin retains all permissions automatically.");

  const validIds = Array.isArray(permissionIds) ? [...new Set(permissionIds.map(Number).filter(Boolean))] : [];
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    await conn.execute("DELETE FROM role_permissions WHERE role_id = ?", [rid]);
    for (const pid of validIds) {
      await conn.execute("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [rid, pid]);
    }
    await conn.commit();
    await logAdminActivity({
      userId: superAdminId,
      action: "role_permissions.update",
      entityType: "role",
      entityId: rid,
      details: `${role.name}: ${validIds.length} permission(s)`
    });
    return { roleId: rid, permissionIds: validIds };
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function subscribeNewsletter(email) {
  const clean = String(email || "").trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clean)) throw new AppError(400, "Please enter a valid email address.");
  await pool.execute(
    "INSERT INTO newsletter_subscribers (email, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1",
    [clean]
  );
  return { email: clean };
}

module.exports = {
  BRANDING_UPLOAD_DIR,
  buildCsvBuffer,
  buildPdfReportBuffer,
  generateReportData,
  getAllSettings,
  updateSettings,
  getPublicStorefrontConfig,
  getPromotionsOverview,
  upsertCampaign,
  deleteCampaign,
  upsertBanner,
  deleteBanner,
  setCategoryFeatured,
  getGovernanceMatrix,
  saveAdminStaff,
  updateRolePermissions,
  subscribeNewsletter
};
