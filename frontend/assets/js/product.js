// VALENOR Horology — Phase 2 Product Detail Page Controller
(() => {
  const DATA = window.VALENOR_DATA;
  const UI = window.VALENOR_UI;

  function getProductFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const id = Number(params.get("id") || 1);
    return DATA.products.find((p) => p.id === id) || DATA.products[0];
  }

  function renderProductDetail(p) {
    if (!p) {
      document.title = "Timepiece Unavailable — VALENOR Horology";
      document.getElementById("pdp-breadcrumb-name").textContent = "Unavailable";
      document.getElementById("pdp-root").innerHTML = `
        <div style="grid-column:1/-1;padding:40px;background:#fff;border:1px solid var(--border-subtle);border-radius:10px;text-align:center;">
          <h1 style="font-family:var(--font-display);font-size:32px;margin:0 0 8px;">Timepiece No Longer Available</h1>
          <p style="color:var(--ink-secondary);margin:0 0 18px;">This watch has been removed from our active catalog or archived.</p>
          <a href="catalog.html" class="btn btn-primary">Browse Active Watches</a>
        </div>`;
      return;
    }
    document.title = `${p.name} — VALENOR Horology`;
    document.getElementById("pdp-breadcrumb-name").textContent = p.name;

    const gallery = p.images?.length ? p.images : [p.image];
    const colors = p.colors?.length ? p.colors : [{ name: p.color, hex: "#141417" }];
    const isOut = p.stock_status === "Out of Stock" || p.stock_quantity === 0;
    const maxQty = Math.max(1, p.stock_quantity || 1);
    const isWish = window.VALENOR_APP ? window.VALENOR_APP.isWishlisted(p.id) : false;

    const root = document.getElementById("pdp-root");
    root.innerHTML = `
      <div class="pdp-gallery">
        <div class="pdp-main-img">
          <img id="pdp-active-img" src="${UI.resolveAsset(gallery[0])}" alt="${p.name}">
        </div>
        <div class="pdp-thumbs">
          ${gallery
            .map(
              (src, idx) => `
              <button type="button" class="pdp-thumb-btn ${idx === 0 ? "active" : ""}" data-thumb-src="${UI.resolveAsset(src)}" aria-label="View angle ${idx + 1}">
                <img src="${UI.resolveAsset(src)}" alt="${p.name} view ${idx + 1}">
              </button>
            `
            )
            .join("")}
        </div>
      </div>

      <div>
        <div class="product-brand-row" style="margin-bottom:8px;">
          <span class="product-brand">${p.brand} · SKU ${p.sku}</span>
          <span class="stock-pill ${isOut ? "stock-out" : p.stock_status === "Limited Stock" ? "stock-low" : ""}">
            ${isOut ? "Out of Stock" : `${p.stock_status} (${p.stock_quantity} available)`}
          </span>
        </div>

        <h1 style="font-family:var(--font-display);font-size:clamp(30px,3.6vw,44px);line-height:1.08;margin:0 0 12px;">${p.name}</h1>

        <div class="product-rating" style="margin-bottom:16px;">
          <span class="stars">${UI.renderStars(p.rating)}</span>
          <span><strong>${Number(p.rating).toFixed(1)}</strong> (${p.review_count} verified reviews)</span>
        </div>

        <div class="product-price-row" style="margin-bottom:18px;">
          <span class="price-sale" style="font-size:26px;">${UI.formatPrice(p.sale_price)}</span>
          ${p.original_price > p.sale_price ? `<span class="price-original" style="font-size:16px;">${UI.formatPrice(p.original_price)}</span>` : ""}
          ${p.discount_percent ? `<span class="badge-discount">${p.discount_percent}% OFF</span>` : ""}
        </div>

        <p style="color:var(--ink-secondary);line-height:1.7;margin:0 0 22px;">${p.description}</p>

        <div>
          <strong style="font-size:12.5px;text-transform:uppercase;letter-spacing:0.08em;">Available Color: <span id="selected-color-label">${colors[0].name}</span></strong>
          <div class="pdp-swatches" id="pdp-swatches">
            ${colors
              .map(
                (c, i) => `
                <button type="button" class="swatch-btn ${i === 0 ? "active" : ""}" data-color-name="${c.name}">
                  <span class="swatch-dot" style="background:${c.hex};"></span> ${c.name}
                </button>
              `
              )
              .join("")}
          </div>
        </div>

        <div class="pdp-actions-row">
          <div class="qty-stepper" aria-label="Quantity selector">
            <button type="button" id="pdp-qty-dec" aria-label="Decrease quantity">&minus;</button>
            <input type="number" id="pdp-qty-input" value="1" min="1" max="${maxQty}" aria-label="Quantity">
            <button type="button" id="pdp-qty-inc" aria-label="Increase quantity">+</button>
          </div>
          <button type="button" class="btn btn-primary" id="pdp-add-cart" ${isOut ? "disabled" : ""}>
            ${isOut ? "Out of Stock" : "Add to Cart"}
          </button>
          <button type="button" class="btn btn-gold" id="pdp-buy-now" ${isOut ? "disabled" : ""}>
            Buy Now
          </button>
          <button type="button" class="btn btn-outline" id="pdp-wishlist-btn">
            ${isWish ? "Wishlisted" : "Wishlist"}
          </button>
        </div>

        <h2 style="font-family:var(--font-display);font-size:24px;margin:28px 0 6px;">Technical Specifications</h2>
        <table class="specs-table">
          <tbody>
            <tr><th>SKU</th><td>${p.sku}</td></tr>
            <tr><th>Brand</th><td>${p.brand}</td></tr>
            <tr><th>Department</th><td>${p.gender}</td></tr>
            <tr><th>Watch Movement</th><td>${p.movement}</td></tr>
            <tr><th>Watch Type</th><td>${p.watch_type}</td></tr>
            <tr><th>Case Material</th><td>${p.case_material}</td></tr>
            <tr><th>Strap Material</th><td>${p.strap_material}</td></tr>
            <tr><th>Water Resistance</th><td>${p.water_resistance}</td></tr>
            <tr><th>Warranty</th><td>${p.warranty}</td></tr>
          </tbody>
        </table>
      </div>
    `;

    // Gallery thumbnail switcher
    root.querySelectorAll(".pdp-thumb-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        root.querySelectorAll(".pdp-thumb-btn").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        document.getElementById("pdp-active-img").src = btn.dataset.thumbSrc;
      });
    });

    // Color swatch switcher
    root.querySelectorAll(".swatch-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        root.querySelectorAll(".swatch-btn").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        document.getElementById("selected-color-label").textContent = btn.dataset.colorName;
      });
    });

    // Quantity stepper
    const qtyInput = document.getElementById("pdp-qty-input");
    document.getElementById("pdp-qty-dec").addEventListener("click", () => {
      qtyInput.value = Math.max(1, Number(qtyInput.value) - 1);
    });
    document.getElementById("pdp-qty-inc").addEventListener("click", () => {
      qtyInput.value = Math.min(maxQty, Number(qtyInput.value) + 1);
    });

    // Add to Cart / Buy Now / Wishlist
    document.getElementById("pdp-add-cart").addEventListener("click", () => {
      const qty = Math.max(1, Math.min(maxQty, Number(qtyInput.value) || 1));
      const variant = document.getElementById("selected-color-label")?.textContent || colors[0].name;
      window.VALENOR_APP?.addToCart(p.id, qty, variant);
    });

    document.getElementById("pdp-buy-now").addEventListener("click", () => {
      const qty = Math.max(1, Math.min(maxQty, Number(qtyInput.value) || 1));
      const variant = document.getElementById("selected-color-label")?.textContent || colors[0].name;
      window.VALENOR_APP?.addToCart(p.id, qty, variant);
      window.location.href = "checkout.html";
    });

    document.getElementById("pdp-wishlist-btn").addEventListener("click", (e) => {
      window.VALENOR_APP?.toggleWishlist(p.id);
      const saved = window.VALENOR_APP?.isWishlisted(p.id);
      e.target.textContent = saved ? "Wishlisted" : "Wishlist";
    });

    // Reviews
    const productReviews = DATA.reviews.filter((r) => r.product_id === p.id);
    const reviewsToShow = productReviews.length ? productReviews : DATA.reviews.slice(0, 3);
    document.getElementById("pdp-reviews-grid").innerHTML = reviewsToShow.map(UI.renderReviewCard).join("");

    // Related Products
    const renderRelated = () => {
      const related = p.related?.length
        ? p.related
        : DATA.products
            .filter((x) => x.id !== p.id && (x.category === p.category || x.brand === p.brand))
            .slice(0, 4);
      document.getElementById("pdp-related-grid").innerHTML = related
        .map((item) => UI.renderProductCard(item, window.VALENOR_APP?.isWishlisted(item.id)))
        .join("");
    };
    renderRelated();
    document.addEventListener("valenor:wishlist-updated", renderRelated);
  }

  async function resolveProduct() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id") || "1";
    if (/^https?:/.test(window.location.protocol)) {
      try {
        const res = await fetch(`/api/products/${encodeURIComponent(id)}`, { headers: { Accept: "application/json" } });
        if (res.status === 404) return null;
        if (res.ok) {
          const json = await res.json();
          if (json?.success && json.data) return json.data;
        }
      } catch {
        // Static preview fallback below
      }
    }
    return getProductFromUrl();
  }

  document.addEventListener("DOMContentLoaded", async () => {
    const product = await resolveProduct();
    renderProductDetail(product);
  });
})();
