const pool = require("../config/db");

const SORT_SQL = {
  featured: "p.is_featured DESC, p.sales_count DESC, p.id DESC",
  best_selling: "p.sales_count DESC, p.rating DESC",
  newest: "p.is_new_arrival DESC, p.created_at DESC, p.id DESC",
  price_asc: "p.sale_price ASC, p.id ASC",
  price_desc: "p.sale_price DESC, p.id DESC",
  rating_desc: "p.rating DESC, p.review_count DESC",
  discount_desc: "p.discount_percent DESC, p.sale_price ASC"
};

async function getCategories() {
  const [rows] = await pool.query(
    `SELECT c.id, c.name, c.slug, c.description, c.image_url, c.sort_order,
            COUNT(DISTINCT pc.product_id) AS product_count
     FROM categories c
     LEFT JOIN product_categories pc ON pc.category_id = c.id
     WHERE c.is_active = 1
     GROUP BY c.id
     ORDER BY c.sort_order ASC, c.name ASC`
  );
  return rows;
}

// Prepared for Phase 6+ Admin Category CRUD
async function createCategory({ name, slug, description = "", imageUrl = null, sortOrder = 0 }) {
  const [res] = await pool.execute(
    `INSERT INTO categories (name, slug, description, image_url, sort_order) VALUES (?, ?, ?, ?, ?)`,
    [name, slug, description, imageUrl, sortOrder]
  );
  return { id: res.insertId, name, slug, description, imageUrl, sortOrder };
}

async function queryProducts(filters = {}) {
  const where = ["p.is_active = 1", "p.is_deleted = 0"];
  const params = [];

  if (filters.category) {
    where.push(`(c.slug = ? OR EXISTS (
      SELECT 1 FROM product_categories pc
      JOIN categories cx ON cx.id = pc.category_id
      WHERE pc.product_id = p.id AND cx.slug = ?
    ))`);
    params.push(filters.category, filters.category);
  }
  if (filters.gender) {
    where.push("p.gender = ?");
    params.push(filters.gender);
  }
  if (filters.brand) {
    where.push("b.name = ?");
    params.push(filters.brand);
  }
  if (filters.color) {
    where.push("p.color = ?");
    params.push(filters.color);
  }
  if (filters.strapMaterial) {
    where.push("p.strap_material = ?");
    params.push(filters.strapMaterial);
  }
  if (filters.watchType) {
    where.push("p.watch_type = ?");
    params.push(filters.watchType);
  }
  if (filters.minPrice !== undefined && filters.minPrice !== "") {
    where.push("p.sale_price >= ?");
    params.push(Number(filters.minPrice));
  }
  if (filters.maxPrice !== undefined && filters.maxPrice !== "") {
    where.push("p.sale_price <= ?");
    params.push(Number(filters.maxPrice));
  }
  if (filters.minDiscount !== undefined && filters.minDiscount !== "") {
    where.push("p.discount_percent >= ?");
    params.push(Number(filters.minDiscount));
  }
  if (filters.minRating !== undefined && filters.minRating !== "") {
    where.push("p.rating >= ?");
    params.push(Number(filters.minRating));
  }
  if (filters.availability === "in_stock") {
    where.push("COALESCE(inv.available_stock, 0) > 5");
  } else if (filters.availability === "limited") {
    where.push("COALESCE(inv.available_stock, 0) BETWEEN 1 AND 5");
  } else if (filters.availability === "out_of_stock") {
    where.push("COALESCE(inv.available_stock, 0) = 0");
  }
  if (filters.q) {
    const like = `%${String(filters.q).trim()}%`;
    where.push("(p.name LIKE ? OR p.description LIKE ? OR b.name LIKE ? OR p.sku LIKE ?)");
    params.push(like, like, like, like);
  }

  const orderBy = SORT_SQL[filters.sort] || SORT_SQL.featured;
  const page = Math.max(1, Number(filters.page || 1));
  const limit = Math.min(100, Math.max(1, Number(filters.limit || 24)));
  const offset = (page - 1) * limit;

  const baseFrom = `
    FROM products p
    JOIN brands b ON b.id = p.brand_id
    JOIN categories c ON c.id = p.primary_category_id
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE ${where.join(" AND ")}
  `;

  const [rows] = await pool.query(
    `SELECT p.id, p.sku, p.name, p.slug, b.name AS brand, c.name AS category_name, c.slug AS category_slug,
            p.gender, p.watch_type, p.strap_material, p.case_material, p.movement, p.water_resistance, p.warranty,
            p.description, p.original_price, p.sale_price, p.discount_percent, p.color, p.rating, p.review_count,
            p.sales_count, p.is_featured, p.is_best_seller, p.is_new_arrival, p.created_at,
            COALESCE(inv.available_stock, 0) AS stock_quantity,
            (SELECT GROUP_CONCAT(DISTINCT cx.slug)
             FROM product_categories pc
             JOIN categories cx ON cx.id = pc.category_id
             WHERE pc.product_id = p.id) AS extra_categories,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
     ${baseFrom}
     ORDER BY ${orderBy}
     LIMIT ? OFFSET ?`,
    [...params, limit, offset]
  );

  const products = rows.map((r) => {
    const qty = Number(r.stock_quantity || 0);
    const extra = r.extra_categories ? r.extra_categories.split(",") : [];
    const categorySlugs = [...new Set([r.category_slug, ...extra].filter(Boolean))];
    return {
      ...r,
      original_price: Number(r.original_price),
      sale_price: Number(r.sale_price),
      rating: Number(r.rating),
      stock_quantity: qty,
      stock_status: qty === 0 ? "Out of Stock" : qty <= 5 ? "Limited Stock" : "In Stock",
      category_slugs: categorySlugs
    };
  });

  const [[bounds]] = await pool.query(
    `SELECT MAX(sale_price) AS max_price
     FROM products WHERE is_active = 1 AND is_deleted = 0`
  );

  const [[countRow]] = await pool.query(`SELECT COUNT(*) AS total ${baseFrom}`, params);

  return {
    products,
    total: countRow.total,
    page,
    limit,
    priceRange: {
      min: 0,
      max: Math.ceil(Number(bounds.max_price || 0))
    }
  };
}

async function getProductByIdOrSlug(idOrSlug) {
  const isNumeric = /^\d+$/.test(String(idOrSlug));
  const field = isNumeric ? "p.id" : "p.slug";

  const [[product]] = await pool.query(
    `SELECT p.*, b.name AS brand, c.name AS category_name, c.slug AS category_slug,
            COALESCE(inv.available_stock, 0) AS stock_quantity
     FROM products p
     JOIN brands b ON b.id = p.brand_id
     JOIN categories c ON c.id = p.primary_category_id
     LEFT JOIN inventory inv ON inv.product_id = p.id
     WHERE ${field} = ? AND p.is_active = 1 AND p.is_deleted = 0
     LIMIT 1`,
    [idOrSlug]
  );
  if (!product) return null;

  const [images] = await pool.query(
    "SELECT id, image_url, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC",
    [product.id]
  );
  const [variants] = await pool.query(
    "SELECT id, color_name, color_hex, variant_sku, price_delta, stock_quantity FROM product_variants WHERE product_id = ?",
    [product.id]
  );
  const [reviews] = await pool.query(
    "SELECT id, author_name, city, rating, comment, is_verified, created_at FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC",
    [product.id]
  );
  const [related] = await pool.query(
    `SELECT p.id, p.name, p.slug, b.name AS brand, p.original_price, p.sale_price, p.discount_percent,
            p.rating, p.review_count,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS image
     FROM products p
     JOIN brands b ON b.id = p.brand_id
     WHERE p.primary_category_id = ? AND p.id <> ? AND p.is_active = 1 AND p.is_deleted = 0
     ORDER BY p.sales_count DESC LIMIT 4`,
    [product.primary_category_id, product.id]
  );

  const qty = Number(product.stock_quantity || 0);
  return {
    ...product,
    original_price: Number(product.original_price),
    sale_price: Number(product.sale_price),
    rating: Number(product.rating),
    stock_quantity: qty,
    stock_status: qty === 0 ? "Out of Stock" : qty <= 5 ? "Limited Stock" : "In Stock",
    image: images[0]?.image_url || "",
    images: images.map((i) => i.image_url),
    colors: variants.map((v) => ({ name: v.color_name, hex: v.color_hex })),
    variants,
    reviews,
    related: related.map((r) => ({
      ...r,
      original_price: Number(r.original_price),
      sale_price: Number(r.sale_price),
      rating: Number(r.rating),
      stock_status: "In Stock"
    }))
  };
}

module.exports = { getCategories, createCategory, queryProducts, getProductByIdOrSlug };

