const fs = require("fs");
const path = require("path");
const pool = require("../config/db");
const { AppError } = require("./checkoutModel");
const { logAdminActivity } = require("./adminDashboardModel");

const PRODUCTS_UPLOAD_DIR = path.resolve(__dirname, "..", "uploads", "products");
const VALID_GENDERS = ["Men", "Women", "Kids", "Couple", "Unisex"];

function slugify(text) {
  return String(text || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "") || `item-${Date.now()}`;
}

function tryUnlinkLocalUpload(imageUrl) {
  if (!imageUrl || !String(imageUrl).startsWith("/uploads/products/")) return;
  const target = path.join(PRODUCTS_UPLOAD_DIR, path.basename(imageUrl));
  if (target.startsWith(PRODUCTS_UPLOAD_DIR) && fs.existsSync(target)) {
    fs.unlink(target, () => {});
  }
}

async function getCatalogMeta() {
  const [categories] = await pool.query(
    "SELECT id, name, slug, description, sort_order, is_active FROM categories ORDER BY sort_order ASC, name ASC"
  );
  const [brands] = await pool.query(
    "SELECT id, name, slug, is_active FROM brands ORDER BY name ASC"
  );
  const [watchTypes] = await pool.query(
    "SELECT id, name, slug, description, is_active, sort_order FROM watch_types ORDER BY sort_order ASC, name ASC"
  );
  return { categories, brands, watchTypes, genders: VALID_GENDERS };
}

async function getProductDetailForAdmin(id) {
  const pid = Number(id);
  const [[product]] = await pool.query(
    `SELECT p.*, b.name AS brand_name, c.name AS category_name,
            COALESCE(inv.available_stock, 0) AS available_stock,
            COALESCE(inv.reserved_stock, 0) AS reserved_stock,
            COALESCE(inv.sold_quantity, 0) AS sold_quantity,
            COALESCE(inv.returned_quantity, 0) AS returned_quantity,
            COALESCE(inv.low_stock_threshold, 5) AS low_stock_threshold
     FROM products p
     JOIN brands b ON b.id = p.brand_id
     JOIN categories c ON c.id = p.primary_category_id
     LEFT JOIN inventory inv ON inv.product_id = p.id
     WHERE p.id = ? LIMIT 1`,
    [pid]
  );
  if (!product) throw new AppError(404, "Product not found.");

  const [catRows] = await pool.query("SELECT category_id FROM product_categories WHERE product_id = ?", [pid]);
  const [images] = await pool.query(
    "SELECT id, image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC",
    [pid]
  );
  const [variants] = await pool.query(
    "SELECT id, color_name, color_hex, variant_sku, price_delta, stock_quantity FROM product_variants WHERE product_id = ? ORDER BY id ASC",
    [pid]
  );

  return {
    ...product,
    original_price: Number(product.original_price),
    sale_price: Number(product.sale_price),
    cost_price: product.cost_price === null ? null : Number(product.cost_price),
    category_ids: catRows.map((c) => c.category_id),
    images,
    variants
  };
}

function normalizeProductInput(data, isUpdate = false) {
  const name = String(data.name || "").trim();
  const sku = String(data.sku || "").trim().toUpperCase();
  const brandId = Number(data.brand_id);
  const primaryCatId = Number(data.primary_category_id);
  const watchType = String(data.watch_type || "Analog").trim();
  const gender = VALID_GENDERS.includes(data.gender) ? data.gender : "Unisex";

  if (!name || !sku || !brandId || !primaryCatId || !watchType) {
    throw new AppError(400, "Name, SKU, Brand, Primary Category, and Product Type are required.");
  }

  const origPrice = Number(data.original_price);
  const salePrice = Number(data.sale_price !== "" && data.sale_price !== undefined ? data.sale_price : origPrice);
  const costPrice = data.cost_price !== "" && data.cost_price !== null && data.cost_price !== undefined
    ? Number(data.cost_price)
    : null;

  if (!Number.isFinite(origPrice) || origPrice <= 0) {
    throw new AppError(400, "Regular price must be greater than 0.");
  }
  if (!Number.isFinite(salePrice) || salePrice <= 0 || salePrice > origPrice) {
    throw new AppError(400, "Sale price must be > 0 and cannot exceed Regular price.");
  }
  if (costPrice !== null && (!Number.isFinite(costPrice) || costPrice < 0)) {
    throw new AppError(400, "Cost price must be a non-negative number.");
  }

  const discountPercent = origPrice > salePrice
    ? Math.min(95, Math.round(((origPrice - salePrice) / origPrice) * 100))
    : 0;

  const availableStock = Math.max(0, Math.floor(Number(data.available_stock ?? 0)));
  const lowThreshold = Math.max(0, Math.floor(Number(data.low_stock_threshold ?? 5)));

  const categoryIds = Array.isArray(data.category_ids)
    ? [...new Set([primaryCatId, ...data.category_ids.map(Number).filter(Boolean)])]
    : [primaryCatId];

  const variants = Array.isArray(data.variants)
    ? data.variants
        .filter((v) => v && String(v.color_name || "").trim())
        .map((v, idx) => ({
          color_name: String(v.color_name).trim(),
          color_hex: String(v.color_hex || "#141417").trim(),
          variant_sku: String(v.variant_sku || `${sku}-V${idx + 1}`).trim().toUpperCase(),
          price_delta: Number(v.price_delta || 0),
          stock_quantity: Math.max(0, Math.floor(Number(v.stock_quantity ?? availableStock)))
        }))
    : [];

  return {
    name,
    sku,
    slug: slugify(data.slug || name) + (isUpdate ? "" : `-${sku.toLowerCase().replace(/[^a-z0-9]/g, "").slice(-4)}`),
    brand_id: brandId,
    primary_category_id: primaryCatId,
    category_ids: categoryIds,
    gender,
    watch_type: watchType,
    strap_material: String(data.strap_material || "Stainless Steel").trim(),
    case_material: String(data.case_material || "316L Stainless Steel").trim(),
    movement: String(data.movement || "Swiss Quartz").trim(),
    water_resistance: String(data.water_resistance || "5 ATM (50m)").trim(),
    warranty: String(data.warranty || "2 Years VALENOR International Warranty").trim(),
    description: String(data.description || `${name} crafted by VALENOR Horology.`).trim(),
    original_price: origPrice,
    sale_price: salePrice,
    cost_price: costPrice,
    discount_percent: discountPercent,
    color: String(data.color || variants[0]?.color_name || "Obsidian Black").trim(),
    available_stock: availableStock,
    low_stock_threshold: lowThreshold,
    variants,
    is_featured: data.is_featured ? 1 : 0,
    is_best_seller: data.is_best_seller ? 1 : 0,
    is_new_arrival: data.is_new_arrival ? 1 : 0,
    is_active: data.is_active === false || data.is_active === 0 ? 0 : 1,
    initial_image: data.initial_image ? String(data.initial_image).trim() : null
  };
}

async function ensureWatchTypeExists(conn, typeName) {
  await conn.execute(
    "INSERT IGNORE INTO watch_types (name, slug, description, sort_order) VALUES (?, ?, 'Custom product type', 25)",
    [typeName, slugify(typeName)]
  );
}

async function syncProductRelations(conn, productId, input) {
  await conn.execute("DELETE FROM product_categories WHERE product_id = ?", [productId]);
  for (const cid of input.category_ids) {
    await conn.execute("INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)", [productId, cid]);
  }

  if (input.variants.length) {
    await conn.execute("DELETE FROM product_variants WHERE product_id = ?", [productId]);
    for (let i = 0; i < input.variants.length; i++) {
      const v = input.variants[i];
      await conn.execute(
        `INSERT INTO product_variants (product_id, color_name, color_hex, variant_sku, price_delta, stock_quantity)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [productId, v.color_name, v.color_hex, v.variant_sku, v.price_delta, v.stock_quantity]
      );
    }
  }
}

async function createProduct(data, adminUserId) {
  const input = normalizeProductInput(data, false);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    await ensureWatchTypeExists(conn, input.watch_type);

    const [res] = await conn.execute(
      `INSERT INTO products (
        sku, name, slug, brand_id, primary_category_id, gender, watch_type,
        strap_material, case_material, movement, water_resistance, warranty, description,
        original_price, sale_price, cost_price, discount_percent, color,
        is_featured, is_best_seller, is_new_arrival, is_active
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        input.sku, input.name, input.slug, input.brand_id, input.primary_category_id, input.gender, input.watch_type,
        input.strap_material, input.case_material, input.movement, input.water_resistance, input.warranty, input.description,
        input.original_price, input.sale_price, input.cost_price, input.discount_percent, input.color,
        input.is_featured, input.is_best_seller, input.is_new_arrival, input.is_active
      ]
    );
    const productId = res.insertId;

    await syncProductRelations(conn, productId, input);

    await conn.execute(
      "INSERT INTO inventory (product_id, available_stock, low_stock_threshold) VALUES (?, ?, ?)",
      [productId, input.available_stock, input.low_stock_threshold]
    );
    if (input.available_stock > 0) {
      await conn.execute(
        "INSERT INTO inventory_movements (product_id, change_qty, movement_type, note, updated_by_user_id) VALUES (?, ?, 'admin_adjust', 'Initial stock on product creation', ?)",
        [productId, input.available_stock, adminUserId]
      );
    }

    const fallbackImg = input.initial_image || "assets/images/watch-mens-chronograph.jpg";
    await conn.execute(
      "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, 1, 1)",
      [productId, fallbackImg]
    );

    await conn.commit();
    await logAdminActivity({
      userId: adminUserId,
      action: "product.create",
      entityType: "product",
      entityId: productId,
      details: `${input.sku} - ${input.name}`
    });
    return getProductDetailForAdmin(productId);
  } catch (err) {
    await conn.rollback();
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "A product with this SKU or slug already exists.");
    throw err;
  } finally {
    conn.release();
  }
}

async function updateProduct(id, data, adminUserId) {
  const pid = Number(id);
  const input = normalizeProductInput(data, true);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[existing]] = await conn.query("SELECT id FROM products WHERE id = ? LIMIT 1 FOR UPDATE", [pid]);
    if (!existing) throw new AppError(404, "Product not found.");

    await ensureWatchTypeExists(conn, input.watch_type);

    await conn.execute(
      `UPDATE products SET
        sku = ?, name = ?, brand_id = ?, primary_category_id = ?, gender = ?, watch_type = ?,
        strap_material = ?, case_material = ?, movement = ?, water_resistance = ?, warranty = ?, description = ?,
        original_price = ?, sale_price = ?, cost_price = ?, discount_percent = ?, color = ?,
        is_featured = ?, is_best_seller = ?, is_new_arrival = ?, is_active = ?
       WHERE id = ?`,
      [
        input.sku, input.name, input.brand_id, input.primary_category_id, input.gender, input.watch_type,
        input.strap_material, input.case_material, input.movement, input.water_resistance, input.warranty, input.description,
        input.original_price, input.sale_price, input.cost_price, input.discount_percent, input.color,
        input.is_featured, input.is_best_seller, input.is_new_arrival, input.is_active, pid
      ]
    );

    await syncProductRelations(conn, pid, input);

    const [[inv]] = await conn.query("SELECT available_stock FROM inventory WHERE product_id = ? LIMIT 1 FOR UPDATE", [pid]);
    const prevStock = inv ? Number(inv.available_stock) : 0;
    await conn.execute(
      `INSERT INTO inventory (product_id, available_stock, low_stock_threshold)
       VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE available_stock = VALUES(available_stock), low_stock_threshold = VALUES(low_stock_threshold)`,
      [pid, input.available_stock, input.low_stock_threshold]
    );
    const delta = input.available_stock - prevStock;
    if (delta !== 0) {
      await conn.execute(
        "INSERT INTO inventory_movements (product_id, change_qty, movement_type, note, updated_by_user_id) VALUES (?, ?, 'admin_adjust', 'Stock updated via Product Editor', ?)",
        [pid, delta, adminUserId]
      );
    }

    await conn.commit();
    await logAdminActivity({
      userId: adminUserId,
      action: "product.update",
      entityType: "product",
      entityId: pid,
      details: `${input.sku} - ${input.name}`
    });
    return getProductDetailForAdmin(pid);
  } catch (err) {
    await conn.rollback();
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "Another product already uses that SKU.");
    throw err;
  } finally {
    conn.release();
  }
}

async function patchProductFlags(id, patch, adminUserId) {
  const pid = Number(id);
  const allowedCols = ["is_active", "is_featured", "is_best_seller", "is_new_arrival"];
  const sets = [];
  const params = [];

  for (const col of allowedCols) {
    if (patch[col] !== undefined) {
      sets.push(`${col} = ?`);
      params.push(patch[col] ? 1 : 0);
    }
  }
  if (!sets.length) throw new AppError(400, "No valid product flags provided.");
  params.push(pid);

  const [res] = await pool.execute(`UPDATE products SET ${sets.join(", ")} WHERE id = ?`, params);
  if (!res.affectedRows) throw new AppError(404, "Product not found.");

  await logAdminActivity({
    userId: adminUserId,
    action: "product.flags",
    entityType: "product",
    entityId: pid,
    details: JSON.stringify(patch)
  });
  return getProductDetailForAdmin(pid);
}

async function deleteProduct(id, adminUserId) {
  const pid = Number(id);
  const [[prod]] = await pool.query("SELECT id, sku, slug FROM products WHERE id = ? LIMIT 1", [pid]);
  if (!prod) throw new AppError(404, "Product not found.");

  const [[refOrders]] = await pool.query("SELECT COUNT(*) AS cnt FROM order_items WHERE product_id = ?", [pid]);
  const [[refReturns]] = await pool.query("SELECT COUNT(*) AS cnt FROM return_items WHERE product_id = ?", [pid]);
  if (refOrders.cnt > 0 || refReturns.cnt > 0) {
    await pool.execute(
      `UPDATE products
       SET is_active = 0, is_deleted = 1, is_featured = 0, is_best_seller = 0, is_new_arrival = 0,
           sku = CONCAT(sku, '__DEL_', id), slug = CONCAT(slug, '-del-', id)
       WHERE id = ?`,
      [pid]
    );
    await pool.execute("DELETE FROM cart_items WHERE product_id = ?", [pid]);
    await logAdminActivity({ userId: adminUserId, action: "product.delete_soft", entityType: "product", entityId: pid, details: `Removed ${prod.sku} from catalog (referenced in past orders)` });
    return { deleted: true, archived: true, message: "Watch deleted from Admin Catalog and Customer Storefront (past order records preserved)." };
  }

  const [imgs] = await pool.query("SELECT image_url FROM product_images WHERE product_id = ?", [pid]);
  const [res] = await pool.execute("DELETE FROM products WHERE id = ?", [pid]);
  if (!res.affectedRows) throw new AppError(404, "Product not found.");
  imgs.forEach((img) => tryUnlinkLocalUpload(img.image_url));

  await logAdminActivity({ userId: adminUserId, action: "product.delete", entityType: "product", entityId: pid, details: prod.sku });
  return { deleted: true, archived: false, message: "Watch permanently deleted from catalog." };
}

// ---------- Image Management ----------
async function addProductImages(productId, urls, adminUserId) {
  const pid = Number(productId);
  if (!urls.length) throw new AppError(400, "Please upload at least one valid image.");

  const [[existing]] = await pool.query("SELECT COUNT(*) AS cnt, MAX(sort_order) AS max_sort FROM product_images WHERE product_id = ?", [pid]);
  let sort = Number(existing.max_sort || 0);
  const hasAny = Number(existing.cnt || 0) > 0;

  for (let i = 0; i < urls.length; i++) {
    sort += 1;
    await pool.execute(
      "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)",
      [pid, urls[i], !hasAny && i === 0 ? 1 : 0, sort]
    );
  }
  await logAdminActivity({ userId: adminUserId, action: "product.images_add", entityType: "product", entityId: pid, details: `Added ${urls.length} image(s)` });
  return getProductDetailForAdmin(pid);
}

async function setPrimaryProductImage(productId, imageId, adminUserId) {
  const pid = Number(productId);
  const imgId = Number(imageId);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[target]] = await conn.query("SELECT id FROM product_images WHERE id = ? AND product_id = ? LIMIT 1", [imgId, pid]);
    if (!target) throw new AppError(404, "Product image not found.");
    await conn.execute("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [pid]);
    await conn.execute("UPDATE product_images SET is_primary = 1 WHERE id = ?", [imgId]);
    await conn.commit();
    await logAdminActivity({ userId: adminUserId, action: "product.image_primary", entityType: "product", entityId: pid, details: `Image ${imgId}` });
    return getProductDetailForAdmin(pid);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function replaceProductImage(productId, imageId, newUrl, adminUserId) {
  const pid = Number(productId);
  const imgId = Number(imageId);
  const [[target]] = await pool.query("SELECT image_url FROM product_images WHERE id = ? AND product_id = ? LIMIT 1", [imgId, pid]);
  if (!target) throw new AppError(404, "Product image not found.");
  await pool.execute("UPDATE product_images SET image_url = ? WHERE id = ?", [newUrl, imgId]);
  tryUnlinkLocalUpload(target.image_url);
  await logAdminActivity({ userId: adminUserId, action: "product.image_replace", entityType: "product", entityId: pid, details: `Replaced image ${imgId}` });
  return getProductDetailForAdmin(pid);
}

async function deleteProductImage(productId, imageId, adminUserId) {
  const pid = Number(productId);
  const imgId = Number(imageId);
  const [imgs] = await pool.query("SELECT id, image_url, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC", [pid]);
  if (imgs.length <= 1) throw new AppError(409, "At least one product image must remain. Upload or replace instead of deleting the last image.");
  const target = imgs.find((i) => i.id === imgId);
  if (!target) throw new AppError(404, "Product image not found.");

  await pool.execute("DELETE FROM product_images WHERE id = ?", [imgId]);
  tryUnlinkLocalUpload(target.image_url);
  if (target.is_primary) {
    const next = imgs.find((i) => i.id !== imgId);
    if (next) await pool.execute("UPDATE product_images SET is_primary = 1 WHERE id = ?", [next.id]);
  }
  await logAdminActivity({ userId: adminUserId, action: "product.image_delete", entityType: "product", entityId: pid, details: `Deleted image ${imgId}` });
  return getProductDetailForAdmin(pid);
}

// ---------- Watch Types / Categories / Brands CRUD ----------
async function upsertWatchType(data, adminUserId) {
  const name = String(data.name || "").trim();
  if (!name) throw new AppError(400, "Product type name is required.");
  const slug = slugify(data.slug || name);
  const desc = String(data.description || "").trim();
  const sortOrder = Math.floor(Number(data.sort_order ?? 10));
  const isActive = data.is_active === false || data.is_active === 0 ? 0 : 1;

  if (data.id) {
    const tid = Number(data.id);
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [[oldRow]] = await conn.query("SELECT name FROM watch_types WHERE id = ? LIMIT 1 FOR UPDATE", [tid]);
      if (!oldRow) throw new AppError(404, "Product type not found.");
      await conn.execute(
        "UPDATE watch_types SET name = ?, slug = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?",
        [name, slug, desc, sortOrder, isActive, tid]
      );
      if (oldRow.name !== name) {
        await conn.execute("UPDATE products SET watch_type = ? WHERE watch_type = ?", [name, oldRow.name]);
      }
      await conn.commit();
      await logAdminActivity({ userId: adminUserId, action: "watch_type.update", entityType: "watch_type", entityId: tid, details: `${oldRow.name} -> ${name}` });
      return { id: tid, name, slug, description: desc, sort_order: sortOrder, is_active: isActive };
    } catch (err) {
      await conn.rollback();
      if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "Product type with that name/slug already exists.");
      throw err;
    } finally {
      conn.release();
    }
  }

  try {
    const [res] = await pool.execute(
      "INSERT INTO watch_types (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)",
      [name, slug, desc, sortOrder, isActive]
    );
    await logAdminActivity({ userId: adminUserId, action: "watch_type.create", entityType: "watch_type", entityId: res.insertId, details: name });
    return { id: res.insertId, name, slug, description: desc, sort_order: sortOrder, is_active: isActive };
  } catch (err) {
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "Product type already exists.");
    throw err;
  }
}

async function deleteWatchType(id, adminUserId) {
  const tid = Number(id);
  const [[row]] = await pool.query("SELECT name FROM watch_types WHERE id = ? LIMIT 1", [tid]);
  if (!row) throw new AppError(404, "Product type not found.");
  const [[inUse]] = await pool.query("SELECT COUNT(*) AS cnt FROM products WHERE watch_type = ?", [row.name]);
  if (inUse.cnt > 0) {
    throw new AppError(409, `Cannot delete "${row.name}" because ${inUse.cnt} watch(es) currently use this type.`);
  }
  await pool.execute("DELETE FROM watch_types WHERE id = ?", [tid]);
  await logAdminActivity({ userId: adminUserId, action: "watch_type.delete", entityType: "watch_type", entityId: tid, details: row.name });
  return { deleted: true };
}

async function upsertCategory(data, adminUserId) {
  const name = String(data.name || "").trim();
  if (!name) throw new AppError(400, "Category name is required.");
  const slug = slugify(data.slug || name);
  const desc = String(data.description || "").trim();
  const sortOrder = Math.floor(Number(data.sort_order ?? 10));
  const isActive = data.is_active === false || data.is_active === 0 ? 0 : 1;

  try {
    if (data.id) {
      const cid = Number(data.id);
      const [res] = await pool.execute(
        "UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?",
        [name, slug, desc, sortOrder, isActive, cid]
      );
      if (!res.affectedRows) throw new AppError(404, "Category not found.");
      await logAdminActivity({ userId: adminUserId, action: "category.update", entityType: "category", entityId: cid, details: name });
      return { id: cid, name, slug, description: desc, sort_order: sortOrder, is_active: isActive };
    }
    const [res] = await pool.execute(
      "INSERT INTO categories (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)",
      [name, slug, desc, sortOrder, isActive]
    );
    await logAdminActivity({ userId: adminUserId, action: "category.create", entityType: "category", entityId: res.insertId, details: name });
    return { id: res.insertId, name, slug, description: desc, sort_order: sortOrder, is_active: isActive };
  } catch (err) {
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "Category slug already exists.");
    throw err;
  }
}

async function deleteCategory(id, adminUserId) {
  const cid = Number(id);
  const [[inUse]] = await pool.query("SELECT COUNT(*) AS cnt FROM products WHERE primary_category_id = ?", [cid]);
  if (inUse.cnt > 0) {
    throw new AppError(409, `Cannot delete category used as primary category by ${inUse.cnt} product(s). Reassign or deactivate instead.`);
  }
  const [res] = await pool.execute("DELETE FROM categories WHERE id = ?", [cid]);
  if (!res.affectedRows) throw new AppError(404, "Category not found.");
  await logAdminActivity({ userId: adminUserId, action: "category.delete", entityType: "category", entityId: cid });
  return { deleted: true };
}

async function upsertBrand(data, adminUserId) {
  const name = String(data.name || "").trim();
  if (!name) throw new AppError(400, "Brand name is required.");
  const slug = slugify(data.slug || name);
  const isActive = data.is_active === false || data.is_active === 0 ? 0 : 1;

  try {
    if (data.id) {
      const bid = Number(data.id);
      const [res] = await pool.execute("UPDATE brands SET name = ?, slug = ?, is_active = ? WHERE id = ?", [name, slug, isActive, bid]);
      if (!res.affectedRows) throw new AppError(404, "Brand not found.");
      await logAdminActivity({ userId: adminUserId, action: "brand.update", entityType: "brand", entityId: bid, details: name });
      return { id: bid, name, slug, is_active: isActive };
    }
    const [res] = await pool.execute("INSERT INTO brands (name, slug, is_active) VALUES (?, ?, ?)", [name, slug, isActive]);
    await logAdminActivity({ userId: adminUserId, action: "brand.create", entityType: "brand", entityId: res.insertId, details: name });
    return { id: res.insertId, name, slug, is_active: isActive };
  } catch (err) {
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "Brand name/slug already exists.");
    throw err;
  }
}

async function deleteBrand(id, adminUserId) {
  const bid = Number(id);
  const [[inUse]] = await pool.query("SELECT COUNT(*) AS cnt FROM products WHERE brand_id = ?", [bid]);
  if (inUse.cnt > 0) {
    throw new AppError(409, `Cannot delete brand assigned to ${inUse.cnt} product(s).`);
  }
  const [res] = await pool.execute("DELETE FROM brands WHERE id = ?", [bid]);
  if (!res.affectedRows) throw new AppError(404, "Brand not found.");
  await logAdminActivity({ userId: adminUserId, action: "brand.delete", entityType: "brand", entityId: bid });
  return { deleted: true };
}

// ---------- Inventory Adjustment & Movement History ----------
async function adjustInventory(productId, { availableStock, lowStockThreshold, note }, adminUserId) {
  const pid = Number(productId);
  const newStock = Math.max(0, Math.floor(Number(availableStock)));
  const newThreshold = Math.max(0, Math.floor(Number(lowStockThreshold ?? 5)));
  const reasonNote = String(note || "Manual admin stock update").trim();

  if (!Number.isFinite(newStock)) throw new AppError(400, "Invalid available stock number.");

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [[inv]] = await conn.query("SELECT available_stock FROM inventory WHERE product_id = ? LIMIT 1 FOR UPDATE", [pid]);
    if (!inv) throw new AppError(404, "Inventory record not found for product.");
    const delta = newStock - Number(inv.available_stock);

    await conn.execute(
      "UPDATE inventory SET available_stock = ?, low_stock_threshold = ? WHERE product_id = ?",
      [newStock, newThreshold, pid]
    );
    await conn.execute(
      "INSERT INTO inventory_movements (product_id, change_qty, movement_type, note, updated_by_user_id) VALUES (?, ?, 'admin_adjust', ?, ?)",
      [pid, delta, reasonNote, adminUserId]
    );
    await conn.commit();
    await logAdminActivity({ userId: adminUserId, action: "inventory.adjust", entityType: "product", entityId: pid, details: `delta=${delta}, stock=${newStock}, threshold=${newThreshold}` });
    return getProductDetailForAdmin(pid);
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function listInventoryMovements(limit = 50) {
  const [rows] = await pool.query(
    `SELECT im.id, p.sku, p.name AS product_name, im.change_qty, im.movement_type,
            o.order_number, im.note, u.full_name AS admin_name, im.created_at
     FROM inventory_movements im
     JOIN products p ON p.id = im.product_id
     LEFT JOIN orders o ON o.id = im.order_id
     LEFT JOIN users u ON u.id = im.updated_by_user_id
     ORDER BY im.id DESC
     LIMIT ?`,
    [Math.min(200, Math.max(1, Number(limit || 50)))]
  );
  return rows;
}

// ---------- Extra Editable Items (Coupons & Review Approval) ----------
async function upsertCoupon(data, adminUserId) {
  const code = String(data.code || "").trim().toUpperCase();
  const desc = String(data.description || "").trim();
  const type = ["percent", "fixed", "free_shipping"].includes(data.discount_type) ? data.discount_type : "percent";
  const val = Number(data.discount_value || 0);
  const minPurchase = Number(data.min_purchase_amount || 0);
  const maxDiscount = data.max_discount_amount ? Number(data.max_discount_amount) : null;
  const usageLimit = data.usage_limit ? Number(data.usage_limit) : null;
  const isActive = data.is_active === false || data.is_active === 0 ? 0 : 1;
  if (!code || !desc) throw new AppError(400, "Coupon code and description are required.");

  try {
    if (data.id) {
      await pool.execute(
        `UPDATE coupons SET code = ?, description = ?, discount_type = ?, discount_value = ?,
         max_discount_amount = ?, min_purchase_amount = ?, usage_limit = ?, is_active = ? WHERE id = ?`,
        [code, desc, type, val, maxDiscount, minPurchase, usageLimit, isActive, Number(data.id)]
      );
      await logAdminActivity({ userId: adminUserId, action: "coupon.update", entityType: "coupon", entityId: data.id, details: code });
      return { id: Number(data.id), code };
    }
    const [res] = await pool.execute(
      `INSERT INTO coupons (code, description, discount_type, discount_value, max_discount_amount, min_purchase_amount, usage_limit, is_active, starts_at, expires_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR))`,
      [code, desc, type, val, maxDiscount, minPurchase, usageLimit, isActive]
    );
    await logAdminActivity({ userId: adminUserId, action: "coupon.create", entityType: "coupon", entityId: res.insertId, details: code });
    return { id: res.insertId, code };
  } catch (err) {
    if (err.code === "ER_DUP_ENTRY") throw new AppError(409, "Coupon code already exists.");
    throw err;
  }
}

async function setReviewApproval(id, isApproved, adminUserId) {
  const [res] = await pool.execute("UPDATE reviews SET is_approved = ? WHERE id = ?", [isApproved ? 1 : 0, Number(id)]);
  if (!res.affectedRows) throw new AppError(404, "Review not found.");
  await logAdminActivity({ userId: adminUserId, action: "review.moderate", entityType: "review", entityId: id, details: isApproved ? "Approved" : "Hidden" });
  return { id: Number(id), is_approved: isApproved ? 1 : 0 };
}

module.exports = {
  PRODUCTS_UPLOAD_DIR,
  getCatalogMeta,
  getProductDetailForAdmin,
  createProduct,
  updateProduct,
  patchProductFlags,
  deleteProduct,
  addProductImages,
  setPrimaryProductImage,
  replaceProductImage,
  deleteProductImage,
  upsertWatchType,
  deleteWatchType,
  upsertCategory,
  deleteCategory,
  upsertBrand,
  deleteBrand,
  adjustInventory,
  listInventoryMovements,
  upsertCoupon,
  setReviewApproval
};

