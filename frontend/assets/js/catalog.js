// VALENOR Horology — Phase 2 Catalog Filtering, Dynamic Price Range Slider & Sorting
(() => {
  const DATA = window.VALENOR_DATA;
  const UI = window.VALENOR_UI;

  let allProducts = [...DATA.products];
  let priceBounds = { min: 0, max: 100000 };

  const state = {
    q: "",
    category: "",
    gender: "",
    brand: "",
    color: "",
    strapMaterial: "",
    watchType: "",
    minDiscount: "",
    minRating: "",
    availability: "",
    minPrice: 0,
    maxPrice: 100000,
    sort: "featured"
  };

  function computePriceBounds(products) {
    if (!products.length) return { min: 0, max: 50000 };
    const maxProductPrice = Math.max(...products.map((p) => Number(p.sale_price) || 0));
    return {
      min: 0,
      max: Math.max(1000, Math.ceil(maxProductPrice))
    };
  }

  function uniqueValues(field) {
    return [...new Set(allProducts.map((p) => p[field]).filter(Boolean))].sort();
  }

  function readQueryParams() {
    const params = new URLSearchParams(window.location.search);
    if (params.get("q")) state.q = params.get("q");
    if (params.get("category")) state.category = params.get("category");
    if (params.get("gender")) state.gender = params.get("gender");
    if (params.get("brand")) state.brand = params.get("brand");
    if (params.get("minDiscount")) state.minDiscount = params.get("minDiscount");
    if (params.get("sort")) state.sort = params.get("sort");
    if (params.get("minPrice")) state.minPrice = Number(params.get("minPrice"));
    if (params.get("maxPrice")) state.maxPrice = Number(params.get("maxPrice"));
  }

  function buildFilterControls() {
    document.getElementById("price-currency-label").textContent = DATA.settings.currency;

    // Categories (All 14)
    const catSelect = document.getElementById("filter-category");
    catSelect.innerHTML = `<option value="">All 14 Categories</option>` +
      DATA.catalogCategories
        .map((c) => `<option value="${c.slug}">${c.name}</option>`)
        .join("");
    catSelect.value = state.category;

    // Radio groups helper
    const renderRadioGroup = (containerId, name, values) => {
      const box = document.getElementById(containerId);
      if (!box) return;
      box.innerHTML =
        `<label class="filter-option"><span><input type="radio" name="${name}" value="" ${!state[name] ? "checked" : ""}> All</span></label>` +
        values
          .map(
            (v) =>
              `<label class="filter-option"><span><input type="radio" name="${name}" value="${v}" ${state[name] === v ? "checked" : ""}> ${v}</span></label>`
          )
          .join("");
    };

    renderRadioGroup("filter-gender-list", "gender", uniqueValues("gender"));
    renderRadioGroup("filter-brand-list", "brand", uniqueValues("brand"));
    renderRadioGroup("filter-type-list", "watchType", uniqueValues("watch_type"));
    renderRadioGroup("filter-strap-list", "strapMaterial", uniqueValues("strap_material"));
    renderRadioGroup("filter-color-list", "color", uniqueValues("color"));

    document.getElementById("filter-discount").value = state.minDiscount;
    document.getElementById("filter-rating").value = state.minRating;
    document.getElementById("filter-availability").value = state.availability;
    document.getElementById("catalog-sort").value = state.sort;
    document.getElementById("catalog-search-input").value = state.q;

    syncPriceControls();
  }

  function syncPriceControls() {
    const rMin = document.getElementById("range-min");
    const rMax = document.getElementById("range-max");
    const iMin = document.getElementById("input-min-price");
    const iMax = document.getElementById("input-max-price");
    const fill = document.getElementById("price-track-fill");

    [rMin, rMax].forEach((el) => {
      el.min = priceBounds.min;
      el.max = priceBounds.max;
      el.step = 1;
    });
    iMin.min = priceBounds.min;
    iMin.max = priceBounds.max;
    iMin.step = 1;
    iMax.min = priceBounds.min;
    iMax.max = priceBounds.max;
    iMax.step = 1;

    rMin.value = state.minPrice;
    rMax.value = state.maxPrice;
    iMin.value = state.minPrice;
    iMax.value = state.maxPrice;

    const span = Math.max(1, priceBounds.max - priceBounds.min);
    const leftPct = ((state.minPrice - priceBounds.min) / span) * 100;
    const rightPct = ((state.maxPrice - priceBounds.min) / span) * 100;
    fill.style.left = `${leftPct}%`;
    fill.style.width = `${Math.max(0, rightPct - leftPct)}%`;
  }

  function filterAndSortProducts() {
    const q = state.q.trim().toLowerCase();

    const filtered = allProducts.filter((p) => {
      if (state.category && !(p.category_slugs || []).includes(state.category)) return false;
      if (state.gender && p.gender !== state.gender) return false;
      if (state.brand && p.brand !== state.brand) return false;
      if (state.color && p.color !== state.color) return false;
      if (state.strapMaterial && p.strap_material !== state.strapMaterial) return false;
      if (state.watchType && p.watch_type !== state.watchType) return false;
      if (state.minDiscount && p.discount_percent < Number(state.minDiscount)) return false;
      if (state.minRating && p.rating < Number(state.minRating)) return false;
      if (state.availability === "in_stock" && p.stock_quantity <= 5) return false;
      if (state.availability === "limited" && (p.stock_quantity < 1 || p.stock_quantity > 5)) return false;
      if (state.availability === "out_of_stock" && p.stock_quantity !== 0) return false;
      if (p.sale_price < state.minPrice || p.sale_price > state.maxPrice) return false;
      if (q) {
        const hay = `${p.name} ${p.brand} ${p.sku} ${p.strap_material} ${p.watch_type} ${p.description}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    const sorters = {
      featured: (a, b) => Number(b.is_featured) - Number(a.is_featured) || b.sales_count - a.sales_count,
      best_selling: (a, b) => b.sales_count - a.sales_count,
      newest: (a, b) => String(b.created_at).localeCompare(String(a.created_at)),
      price_asc: (a, b) => a.sale_price - b.sale_price,
      price_desc: (a, b) => b.sale_price - a.sale_price,
      rating_desc: (a, b) => b.rating - a.rating || b.review_count - a.review_count,
      discount_desc: (a, b) => b.discount_percent - a.discount_percent || a.sale_price - b.sale_price
    };

    filtered.sort(sorters[state.sort] || sorters.featured);
    return filtered;
  }

  function renderActiveChips() {
    const chipsHost = document.getElementById("active-filter-chips");
    const chips = [];
    if (state.category) {
      const cat = DATA.catalogCategories.find((c) => c.slug === state.category);
      chips.push({ key: "category", label: cat ? cat.name : state.category });
    }
    if (state.gender) chips.push({ key: "gender", label: `Gender: ${state.gender}` });
    if (state.brand) chips.push({ key: "brand", label: `Brand: ${state.brand}` });
    if (state.watchType) chips.push({ key: "watchType", label: `Type: ${state.watchType}` });
    if (state.strapMaterial) chips.push({ key: "strapMaterial", label: `Strap: ${state.strapMaterial}` });
    if (state.color) chips.push({ key: "color", label: `Color: ${state.color}` });
    if (state.minDiscount) chips.push({ key: "minDiscount", label: `${state.minDiscount}%+ OFF` });
    if (state.minRating) chips.push({ key: "minRating", label: `${state.minRating}★ & up` });
    if (state.availability) chips.push({ key: "availability", label: `Stock: ${state.availability.replace("_", " ")}` });
    if (state.q) chips.push({ key: "q", label: `Search: "${state.q}"` });
    if (state.minPrice > priceBounds.min || state.maxPrice < priceBounds.max) {
      chips.push({
        key: "price",
        label: `${UI.formatPrice(state.minPrice)} – ${UI.formatPrice(state.maxPrice)}`
      });
    }

    chipsHost.innerHTML = chips
      .map(
        (c) => `
        <button type="button" class="active-chip" data-clear-filter="${c.key}">
          ${c.label} <span aria-hidden="true">&times;</span>
        </button>
      `
      )
      .join("");
  }

  function renderCatalog() {
    const items = filterAndSortProducts();
    const grid = document.getElementById("catalog-grid");
    const countEl = document.getElementById("catalog-count");
    const heading = document.getElementById("catalog-heading");

    const catObj = DATA.catalogCategories.find((c) => c.slug === state.category);
    heading.textContent = catObj ? catObj.name : state.q ? `Search: "${state.q}"` : "All Timepieces";
    countEl.textContent = `Showing ${items.length} of ${allProducts.length} watches`;

    renderActiveChips();

    if (!items.length) {
      grid.innerHTML = `
        <div class="empty-catalog-box">
          <h3 style="font-family:var(--font-display);font-size:28px;margin:0 0 8px;">No Matching Timepieces</h3>
          <p style="color:var(--ink-muted);margin:0 0 18px;">Try broadening your price range or resetting one of the selected filters.</p>
          <button type="button" class="btn btn-primary" id="btn-empty-reset">Reset All Filters</button>
        </div>
      `;
      document.getElementById("btn-empty-reset")?.addEventListener("click", resetAllFilters);
      return;
    }

    const isWish = window.VALENOR_APP ? window.VALENOR_APP.isWishlisted : () => false;
    grid.innerHTML = items.map((p) => UI.renderProductCard(p, isWish(p.id))).join("");
  }

  function resetPriceFilter() {
    state.minPrice = priceBounds.min;
    state.maxPrice = priceBounds.max;
    syncPriceControls();
    renderCatalog();
  }

  function resetAllFilters() {
    Object.assign(state, {
      q: "",
      category: "",
      gender: "",
      brand: "",
      color: "",
      strapMaterial: "",
      watchType: "",
      minDiscount: "",
      minRating: "",
      availability: "",
      minPrice: priceBounds.min,
      maxPrice: priceBounds.max,
      sort: "featured"
    });
    buildFilterControls();
    renderCatalog();
  }

  function bindCatalogEvents() {
    document.getElementById("filter-category").addEventListener("change", (e) => {
      state.category = e.target.value;
      renderCatalog();
    });

    ["gender", "brand", "watchType", "strapMaterial", "color"].forEach((name) => {
      document.addEventListener("change", (e) => {
        if (e.target.name === name) {
          state[name] = e.target.value;
          renderCatalog();
        }
      });
    });

    document.getElementById("filter-discount").addEventListener("change", (e) => {
      state.minDiscount = e.target.value;
      renderCatalog();
    });
    document.getElementById("filter-rating").addEventListener("change", (e) => {
      state.minRating = e.target.value;
      renderCatalog();
    });
    document.getElementById("filter-availability").addEventListener("change", (e) => {
      state.availability = e.target.value;
      renderCatalog();
    });
    document.getElementById("catalog-sort").addEventListener("change", (e) => {
      state.sort = e.target.value;
      renderCatalog();
    });

    document.getElementById("catalog-search-form").addEventListener("submit", (e) => {
      e.preventDefault();
      state.q = document.getElementById("catalog-search-input").value.trim();
      renderCatalog();
    });

    // Dual-handle price sliders + manual number inputs
    const rMin = document.getElementById("range-min");
    const rMax = document.getElementById("range-max");
    const iMin = document.getElementById("input-min-price");
    const iMax = document.getElementById("input-max-price");

    const parseBound = (raw, fallback) => (raw === "" || raw === null || Number.isNaN(Number(raw)) ? fallback : Number(raw));

    const clampPriceDraft = (minVal, maxVal) => {
      let lo = Math.max(0, Math.min(parseBound(minVal, 0), priceBounds.max));
      let hi = Math.min(priceBounds.max, Math.max(parseBound(maxVal, priceBounds.max), 0));
      if (lo > hi) [lo, hi] = [hi, lo];
      state.minPrice = lo;
      state.maxPrice = hi;
      syncPriceControls();
    };

    rMin.addEventListener("input", () => {
      if (Number(rMin.value) > Number(rMax.value)) rMin.value = rMax.value;
      clampPriceDraft(rMin.value, rMax.value);
      renderCatalog();
    });

    rMax.addEventListener("input", () => {
      if (Number(rMax.value) < Number(rMin.value)) rMax.value = rMin.value;
      clampPriceDraft(rMin.value, rMax.value);
      renderCatalog();
    });

    document.getElementById("btn-apply-price").addEventListener("click", () => {
      clampPriceDraft(iMin.value, iMax.value);
      renderCatalog();
    });

    document.getElementById("btn-reset-price").addEventListener("click", resetPriceFilter);
    document.getElementById("btn-reset-all").addEventListener("click", resetAllFilters);

    document.getElementById("active-filter-chips").addEventListener("click", (e) => {
      const chip = e.target.closest("[data-clear-filter]");
      if (!chip) return;
      const key = chip.dataset.clearFilter;
      if (key === "price") {
        resetPriceFilter();
        return;
      }
      state[key] = "";
      buildFilterControls();
      renderCatalog();
    });

    // Mobile filter drawer
    document.getElementById("btn-open-filters")?.addEventListener("click", () => {
      window.VALENOR_APP?.openOverlay(document.getElementById("filter-sidebar"));
    });
    document.getElementById("btn-close-filters")?.addEventListener("click", () => {
      window.VALENOR_APP?.closeAllOverlays();
    });
  }

  async function trySyncApiCatalog() {
    if (!/^https?:/.test(window.location.protocol)) return;
    try {
      const [pRes, cRes] = await Promise.all([
        fetch("/api/products?limit=100", { headers: { Accept: "application/json" } }),
        fetch("/api/categories", { headers: { Accept: "application/json" } })
      ]);
      if (pRes.ok) {
        const pJson = await pRes.json();
        if (pJson?.success && Array.isArray(pJson.products) && pJson.products.length) {
          allProducts = pJson.products;
          priceBounds = computePriceBounds(allProducts);
        }
      }
      if (cRes.ok) {
        const cJson = await cRes.json();
        if (cJson?.success && Array.isArray(cJson.data) && cJson.data.length) {
          DATA.catalogCategories = cJson.data;
        }
      }
    } catch {
      // Static preview mode continues seamlessly with VALENOR_DATA
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    priceBounds = computePriceBounds(allProducts);
    await trySyncApiCatalog();

    state.minPrice = 0;
    state.maxPrice = priceBounds.max;
    readQueryParams();
    state.minPrice = Math.max(0, Math.min(Number(state.minPrice) || 0, priceBounds.max));
    state.maxPrice = Math.min(priceBounds.max, Math.max(Number(state.maxPrice) || priceBounds.max, 0));

    buildFilterControls();
    renderCatalog();
    bindCatalogEvents();
    document.addEventListener("valenor:wishlist-updated", renderCatalog);
  });
})();
