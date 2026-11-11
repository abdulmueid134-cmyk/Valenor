// Initializes MySQL/MariaDB schema, migrations, and complete catalog seed from home-data.js
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const mysql = require("mysql2/promise");
require("dotenv").config();

function slugify(text) {
  return String(text || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

async function runSqlFile(conn, filePath) {
  const sql = fs.readFileSync(filePath, "utf8");
  await conn.query(sql);
}

async function main() {
  const conn = await mysql.createConnection({
    host: process.env.DB_HOST || "localhost",
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || "root",
    password: process.env.DB_PASSWORD || "",
    multipleStatements: true
  });

  try {
    const schemaOnly = process.argv.includes("--schema-only");
    const seedOnly = process.argv.includes("--seed-only");
    const dbDir = __dirname;

    if (!seedOnly) {
      await runSqlFile(conn, path.join(dbDir, "schema.sql"));
      const migDir = path.join(dbDir, "migrations");
      if (fs.existsSync(migDir)) {
        for (const file of fs.readdirSync(migDir).sort()) {
          if (file.endsWith(".sql")) await runSqlFile(conn, path.join(migDir, file));
        }
      }
      console.log(`Initialized schema & system migrations for ${process.env.DB_NAME || "valenor_watches"}.`);
    }

    if (schemaOnly) {
      return;
    }

    await runSqlFile(conn, path.join(dbDir, "seed.sql"));

    // Load VALENOR_DATA from frontend/assets/js/home-data.js to sync all watches cleanly
    const sandbox = { window: {} };
    const homeDataCode = fs.readFileSync(
      path.resolve(dbDir, "..", "frontend", "assets", "js", "home-data.js"),
      "utf8"
    );
    vm.runInNewContext(homeDataCode, sandbox);
    const data = sandbox.window.VALENOR_DATA;

    await conn.query(`USE \`${process.env.DB_NAME || "valenor_watches"}\``);

    for (const p of data.products) {
      const brandSlug = slugify(p.brand);
      await conn.execute(
        "INSERT INTO brands (name, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)",
        [p.brand, brandSlug]
      );
      const [[brandRow]] = await conn.query("SELECT id FROM brands WHERE name = ? OR slug = ? LIMIT 1", [p.brand, brandSlug]);

      const primaryCatSlug = (p.category_slugs || [])[0] || "mens-watches";
      const [[catRow]] = await conn.query("SELECT id FROM categories WHERE slug = ? LIMIT 1", [primaryCatSlug]);
      if (!catRow) continue;

      const prodSlug = slugify(p.name);
      const isBest = (p.collections || []).includes("best_sellers") ? 1 : 0;
      const isNew = (p.collections || []).includes("new_arrivals") ? 1 : 0;

      await conn.execute(
        `INSERT INTO products (
          id, sku, name, slug, brand_id, primary_category_id, gender, watch_type,
          strap_material, case_material, movement, water_resistance, warranty, description,
          original_price, sale_price, discount_percent, color, rating, review_count, sales_count,
          is_featured, is_best_seller, is_new_arrival
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          name = VALUES(name), original_price = VALUES(original_price), sale_price = VALUES(sale_price),
          discount_percent = VALUES(discount_percent), rating = VALUES(rating), review_count = VALUES(review_count)`,
        [
          p.id, p.sku, p.name, prodSlug, brandRow.id, catRow.id, p.gender, p.watch_type,
          p.strap_material, p.case_material, p.movement, p.water_resistance, p.warranty, p.description,
          p.original_price, p.sale_price, p.discount_percent, p.color, p.rating, p.review_count,
          p.sales_count || 0, p.is_featured ? 1 : 0, isBest, isNew
        ]
      );

      for (const cSlug of p.category_slugs || []) {
        const [[cx]] = await conn.query("SELECT id FROM categories WHERE slug = ? LIMIT 1", [cSlug]);
        if (cx) {
          await conn.execute(
            "INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)",
            [p.id, cx.id]
          );
        }
      }

      await conn.execute(
        `INSERT INTO inventory (product_id, available_stock, sold_quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE available_stock = VALUES(available_stock)`,
        [p.id, Number(p.stock_quantity ?? 10), Number(p.sales_count || 0)]
      );

      const imgs = p.images?.length ? p.images : [p.image];
      await conn.execute("DELETE FROM product_images WHERE product_id = ?", [p.id]);
      for (let i = 0; i < imgs.length; i++) {
        await conn.execute(
          "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)",
          [p.id, imgs[i], i === 0 ? 1 : 0, i + 1]
        );
      }

      const colors = p.colors?.length ? p.colors : [{ name: p.color, hex: "#141417" }];
      for (let i = 0; i < colors.length; i++) {
        const vSku = `${p.sku}-C${i + 1}`;
        await conn.execute(
          `INSERT INTO product_variants (product_id, color_name, color_hex, variant_sku, stock_quantity)
           VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE color_name = VALUES(color_name), color_hex = VALUES(color_hex)`,
          [p.id, colors[i].name, colors[i].hex, vSku, Number(p.stock_quantity ?? 10)]
        );
      }
    }

    for (const r of data.reviews || []) {
      const [[exists]] = await conn.query(
        "SELECT id FROM reviews WHERE product_id = ? AND author_name = ? LIMIT 1",
        [r.product_id || 1, r.author]
      );
      if (!exists) {
        await conn.execute(
          "INSERT INTO reviews (product_id, author_name, city, rating, comment) VALUES (?, ?, ?, ?, ?)",
          [r.product_id || 1, r.author, r.city || "", r.rating, r.quote]
        );
      }
    }

    console.log(`Seeded ${data.products.length} products into ${process.env.DB_NAME || "valenor_watches"}.`);
  } finally {
    await conn.end();
  }
}

main().catch((err) => {
  console.error("DB init failed:", err.message);
  process.exit(1);
});

