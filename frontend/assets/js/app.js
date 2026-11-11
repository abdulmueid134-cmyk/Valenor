// VALENOR Horology — Phase 1 Storefront Controller
(() => {
  const DATA = window.VALENOR_DATA;
  const UI = window.VALENOR_UI;

  const STORAGE_CART = "valenor_cart_v1";
  const STORAGE_WISHLIST = "valenor_wishlist_v1";
  const STORAGE_NEWSLETTER = "valenor_newsletter_v1";

  let cart = loadJson(STORAGE_CART, []);
  let wishlist = loadJson(STORAGE_WISHLIST, []);
  const activeSubcats = { mens: "all", womens: "all", kids: "all", couple: "all", smart: "all" };

  function loadJson(key, fallback) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch {
      return fallback;
    }
  }

  function saveJson(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch {
      // Ignore storage quota errors in restricted browsing contexts
    }
  }

  function findProduct(id) {
    return DATA.products.find((p) => p.id === Number(id));
  }

  // --------------------------------------------------------------------------
  // 1. Dynamic Announcement Countdown (Never expires to 00:00:00)
  // --------------------------------------------------------------------------
  function startCountdown() {
    const elDays = document.getElementById("cd-days");
    const elHours = document.getElementById("cd-hours");
    const elMins = document.getElementById("cd-mins");
    const elSecs = document.getElementById("cd-secs");
    if (!elDays) return;

    let targetMs = Date.parse(DATA.campaign.targetDateIso);
    if (Number.isNaN(targetMs) || targetMs <= Date.now()) {
      targetMs = Date.now() + 48 * 3600 * 1000;
    }

    function tick() {
      let diff = targetMs - Date.now();
      if (diff <= 0) {
        targetMs = Date.now() + 48 * 3600 * 1000;
        diff = targetMs - Date.now();
      }
      const totalSec = Math.floor(diff / 1000);
      const days = Math.floor(totalSec / 86400);
      const hours = Math.floor((totalSec % 86400) / 3600);
      const mins = Math.floor((totalSec % 3600) / 60);
      const secs = totalSec % 60;

      elDays.textContent = String(days).padStart(2, "0") + "d";
      elHours.textContent = String(hours).padStart(2, "0") + "h";
      elMins.textContent = String(mins).padStart(2, "0") + "m";
      elSecs.textContent = String(secs).padStart(2, "0") + "s";
    }

    tick();
    setInterval(tick, 1000);
  }

  // --------------------------------------------------------------------------
  // 2. Render Homepage Sections & Department Sub-Categories
  // --------------------------------------------------------------------------
  function countDeptSubcat(deptKey, subSlug) {
    return DATA.products.filter(
      (p) => p.category === deptKey && (subSlug === "all" || (p.subcategories || []).includes(subSlug))
    ).length;
  }

  function renderDepartmentControls() {
    Object.entries(DATA.departments || {}).forEach(([deptKey, dept]) => {
      const current = activeSubcats[deptKey] || "all";

      const menu = document.querySelector(`[data-dept-menu="${deptKey}"]`);
      if (menu) {
        menu.innerHTML = dept.subcategories
          .map(
            (sub) => `
            <button type="button" class="nav-dropdown-item ${current === sub.slug ? "active" : ""}" data-select-dept="${deptKey}" data-select-subcat="${sub.slug}" data-scroll="true">
              ${sub.label} <span>${countDeptSubcat(deptKey, sub.slug)}</span>
            </button>
          `
          )
          .join("");
      }

      const mobileBox = document.querySelector(`[data-mobile-subcats="${deptKey}"]`);
      if (mobileBox) {
        mobileBox.innerHTML = dept.subcategories
          .map(
            (sub) => `
            <button type="button" class="mobile-subcat-chip" data-select-dept="${deptKey}" data-select-subcat="${sub.slug}" data-scroll="true">${sub.label}</button>
          `
          )
          .join("");
      }

      const tabBar = document.getElementById(dept.subcatBarId);
      if (tabBar) {
        tabBar.innerHTML = dept.subcategories
          .map(
            (sub) => `
            <button type="button" role="tab" aria-selected="${current === sub.slug}" class="subcat-pill ${current === sub.slug ? "active" : ""}" data-select-dept="${deptKey}" data-select-subcat="${sub.slug}">
              ${sub.label} (${countDeptSubcat(deptKey, sub.slug)})
            </button>
          `
          )
          .join("");
      }
    });
  }

  function filterByDept(deptKey) {
    const sub = activeSubcats[deptKey] || "all";
    return DATA.products.filter(
      (p) => p.category === deptKey && (sub === "all" || (p.subcategories || []).includes(sub))
    );
  }

  function renderSections() {
    const catGrid = document.getElementById("category-grid");
    if (catGrid) {
      catGrid.innerHTML = DATA.categories.map(UI.renderCategoryCard).join("");
    }

    renderDepartmentControls();

    const sectionMap = {
      "grid-best-sellers": () => DATA.products.filter((p) => p.collections.includes("best_sellers")),
      "grid-new-arrivals": () => DATA.products.filter((p) => p.collections.includes("new_arrivals")),
      "grid-mens": () => filterByDept("mens"),
      "grid-womens": () => filterByDept("womens"),
      "grid-kids": () => filterByDept("kids"),
      "grid-couple": () => filterByDept("couple"),
      "grid-smart": () => filterByDept("smart"),
      "grid-special-offers": () => DATA.products.filter((p) => p.collections.includes("special_offers"))
    };

    Object.entries(sectionMap).forEach(([elId, getter]) => {
      const container = document.getElementById(elId);
      if (!container) return;
      const items = ["grid-mens", "grid-womens", "grid-kids", "grid-couple", "grid-smart"].includes(elId)
        ? getter()
        : getter().slice(0, 4);
      container.innerHTML = items
        .map((p) => UI.renderProductCard(p, wishlist.includes(p.id)))
        .join("");
    });

    const capsuleGrid = document.getElementById("featured-capsules-grid");
    if (capsuleGrid) {
      capsuleGrid.innerHTML = DATA.featuredCollections.map(UI.renderCapsuleCard).join("");
    }

    const reviewGrid = document.getElementById("reviews-grid");
    if (reviewGrid) {
      reviewGrid.innerHTML = DATA.reviews.map(UI.renderReviewCard).join("");
    }
  }

  function closeAllDropdowns() {
    document.querySelectorAll(".nav-item-dropdown.open").forEach((el) => {
      el.classList.remove("open");
      el.querySelector(".nav-dropdown-trigger")?.setAttribute("aria-expanded", "false");
    });
  }

  function selectDepartmentSubcategory(deptKey, subSlug, shouldScroll) {
    if (!DATA.departments?.[deptKey]) return;
    activeSubcats[deptKey] = subSlug;
    renderSections();
    closeAllDropdowns();

    const mobileDrawer = document.getElementById("mobile-nav-drawer");
    const mobileBtn = document.getElementById("mobile-menu-btn");
    mobileDrawer?.classList.remove("open");
    mobileBtn?.setAttribute("aria-expanded", "false");

    if (shouldScroll) {
      document.getElementById(DATA.departments[deptKey].sectionId)?.scrollIntoView({ behavior: "smooth" });
    }
  }

  // --------------------------------------------------------------------------
  // 3. Cart, Wishlist, Quick View & Toast State
  // --------------------------------------------------------------------------
  function showToast(msg) {
    const toast = document.getElementById("site-toast");
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add("show");
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => toast.classList.remove("show"), 2600);
  }

  function updateHeaderCounts() {
    const cartTotalQty = cart.reduce((sum, item) => sum + item.qty, 0);
    const cartBadge = document.getElementById("cart-count-badge");
    const wishBadge = document.getElementById("wishlist-count-badge");
    if (cartBadge) cartBadge.textContent = cartTotalQty;
    if (wishBadge) wishBadge.textContent = wishlist.length;
  }

  function toggleWishlist(productId) {
    const id = Number(productId);
    const prod = findProduct(id);
    if (!prod) return;
    if (wishlist.includes(id)) {
      wishlist = wishlist.filter((x) => x !== id);
      showToast(`Removed "${prod.name}" from wishlist.`);
    } else {
      wishlist.push(id);
      showToast(`Saved "${prod.name}" to wishlist.`);
    }
    saveJson(STORAGE_WISHLIST, wishlist);
    updateHeaderCounts();
    renderSections();
    renderWishlistDrawer();
    document.dispatchEvent(new CustomEvent("valenor:wishlist-updated"));
  }

  function addToCart(productId, qty = 1, variant = null) {
    const id = Number(productId);
    const prod = findProduct(id);
    if (!prod) return;
    if (prod.stock_status === "Out of Stock" || prod.stock_quantity === 0) {
      showToast(`"${prod.name}" is currently out of stock.`);
      return;
    }
    const chosenVariant = variant || prod.colors?.[0]?.name || prod.color || "Standard";
    const existing = cart.find((item) => item.id === id && (item.variant || "") === chosenVariant);
    if (existing) {
      existing.qty += qty;
    } else {
      cart.push({ id, qty, variant: chosenVariant });
    }
    saveJson(STORAGE_CART, cart);
    updateHeaderCounts();
    renderCartDrawer();
    document.dispatchEvent(new CustomEvent("valenor:cart-updated"));
    showToast(`Added "${prod.name}" (${chosenVariant}) to your bag.`);
  }

  function updateCartQty(productId, delta, variant = null) {
    const id = Number(productId);
    const item = cart.find((x) => x.id === id && (variant === null || (x.variant || "") === variant));
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
      cart = cart.filter((x) => x !== item);
    }
    saveJson(STORAGE_CART, cart);
    updateHeaderCounts();
    renderCartDrawer();
    document.dispatchEvent(new CustomEvent("valenor:cart-updated"));
  }

  function removeFromCart(productId, variant = null) {
    const id = Number(productId);
    cart = cart.filter((x) => !(x.id === id && (variant === null || (x.variant || "") === variant)));
    saveJson(STORAGE_CART, cart);
    updateHeaderCounts();
    renderCartDrawer();
    document.dispatchEvent(new CustomEvent("valenor:cart-updated"));
  }

  function clearCart() {
    cart = [];
    saveJson(STORAGE_CART, cart);
    updateHeaderCounts();
    renderCartDrawer();
    document.dispatchEvent(new CustomEvent("valenor:cart-updated"));
  }

  function renderCartDrawer() {
    const body = document.getElementById("cart-drawer-body");
    const subtotalEl = document.getElementById("cart-drawer-subtotal");
    if (!body || !subtotalEl) return;

    if (!cart.length) {
      body.innerHTML = `<p style="color:var(--ink-muted);margin:24px 0;">Your shopping bag is currently empty. Explore our collections to select a timepiece.</p>`;
      subtotalEl.textContent = UI.formatPrice(0);
      return;
    }

    let subtotal = 0;
    body.innerHTML = cart
      .map((item, idx) => {
        const p = findProduct(item.id);
        if (!p) return "";
        subtotal += p.sale_price * item.qty;
        const variantLabel = item.variant || p.colors?.[0]?.name || p.color || "Standard";
        return `
          <div class="drawer-item">
            <img src="${UI.resolveAsset(p.image)}" alt="${p.name}">
            <div>
              <div style="font-weight:600;font-size:14px;">${p.name}</div>
              <div style="font-size:12px;color:var(--ink-muted);">Variant: ${variantLabel}</div>
              <div style="font-size:12.5px;color:var(--ink-muted);">${UI.formatPrice(p.sale_price)}</div>
              <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
                <button type="button" class="close-btn" style="width:26px;height:26px;font-size:14px;" data-cart-dec="${p.id}" data-cart-idx="${idx}" aria-label="Decrease quantity">-</button>
                <span style="font-size:13px;font-weight:600;">${item.qty}</span>
                <button type="button" class="close-btn" style="width:26px;height:26px;font-size:14px;" data-cart-inc="${p.id}" data-cart-idx="${idx}" aria-label="Increase quantity">+</button>
              </div>
            </div>
            <div style="font-weight:700;font-size:13.5px;">${UI.formatPrice(p.sale_price * item.qty)}</div>
          </div>
        `;
      })
      .join("");
    subtotalEl.textContent = UI.formatPrice(subtotal);
  }

  function renderWishlistDrawer() {
    const body = document.getElementById("wishlist-drawer-body");
    if (!body) return;

    if (!wishlist.length) {
      body.innerHTML = `<p style="color:var(--ink-muted);margin:24px 0;">No saved watches yet. Tap the heart icon on any timepiece to save it here.</p>`;
      return;
    }

    body.innerHTML = wishlist
      .map((id) => {
        const p = findProduct(id);
        if (!p) return "";
        return `
          <div class="drawer-item">
            <img src="${UI.resolveAsset(p.image)}" alt="${p.name}">
            <div>
              <div style="font-weight:600;font-size:14px;">${p.name}</div>
              <div style="font-size:12.5px;color:var(--ink-muted);">${UI.formatPrice(p.sale_price)}</div>
              <button type="button" class="btn btn-primary" style="padding:6px 12px;font-size:11px;margin-top:8px;" data-action="add-cart" data-id="${p.id}">Move to Bag</button>
            </div>
            <button type="button" class="close-btn" data-action="wishlist" data-id="${p.id}" aria-label="Remove from wishlist">&times;</button>
          </div>
        `;
      })
      .join("");
  }

  function openQuickView(productId) {
    const p = findProduct(productId);
    const modal = document.getElementById("quick-view-modal");
    const content = document.getElementById("quick-view-content");
    if (!p || !modal || !content) return;
    const isOut = p.stock_status === "Out of Stock" || p.stock_quantity === 0;
    const detailHref = `${UI.pageHref("pages/product.html")}?id=${p.id}`;

    content.innerHTML = `
      <div class="modal-grid">
        <div class="modal-media">
          <img src="${UI.resolveAsset(p.image)}" alt="${p.name}">
        </div>
        <div class="modal-body">
          <button type="button" class="close-btn" style="position:absolute;top:16px;right:16px;" data-close-overlays aria-label="Close quick view">&times;</button>
          <span class="product-brand">${p.brand} · SKU ${p.sku}</span>
          <h2 style="font-family:var(--font-display);font-size:30px;margin:6px 0 8px;">${p.name}</h2>
          <div class="product-rating">
            <span class="stars">${UI.renderStars(p.rating)}</span>
            <span>${p.rating.toFixed(1)} (${p.review_count} verified reviews)</span>
          </div>
          <div class="product-price-row">
            <span class="price-sale" style="font-size:22px;">${UI.formatPrice(p.sale_price)}</span>
            <span class="price-original">${UI.formatPrice(p.original_price)}</span>
            ${p.discount_percent ? `<span class="badge-discount">${p.discount_percent}% OFF</span>` : ""}
          </div>
          <p style="color:var(--ink-secondary);font-size:14px;margin:0 0 18px;">${p.description}</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;font-size:12.5px;padding:14px 0;border-top:1px solid var(--border-subtle);border-bottom:1px solid var(--border-subtle);margin-bottom:20px;">
            <div><strong>Movement:</strong> ${p.movement}</div>
            <div><strong>Case:</strong> ${p.case_material}</div>
            <div><strong>Strap:</strong> ${p.strap_material}</div>
            <div><strong>Water Resistance:</strong> ${p.water_resistance}</div>
            <div><strong>Availability:</strong> ${p.stock_status}</div>
            <div><strong>Warranty:</strong> ${p.warranty}</div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" class="btn btn-primary" data-action="add-cart" data-id="${p.id}" ${isOut ? "disabled" : ""}>${isOut ? "Out of Stock" : "Add to Bag"}</button>
            <a href="${detailHref}" class="btn btn-gold">Full Details</a>
            <button type="button" class="btn btn-outline" data-action="wishlist" data-id="${p.id}">Wishlist</button>
          </div>
        </div>
      </div>
    `;
    openOverlay(modal);
  }

  // --------------------------------------------------------------------------
  // 4. Overlay & Drawer Management + Search
  // --------------------------------------------------------------------------
  const getBackdrop = () => document.getElementById("overlay-backdrop");
  const overlays = () => document.querySelectorAll(".drawer, .modal, .search-bar-overlay, .filter-sidebar");

  function closeAllOverlays() {
    overlays().forEach((el) => el.classList.remove("open"));
    getBackdrop()?.classList.remove("open");
  }

  function openOverlay(el) {
    closeAllOverlays();
    if (!el) return;
    el.classList.add("open");
    getBackdrop()?.classList.add("open");
  }

  function renderSearchResults(query) {
    const container = document.getElementById("search-results");
    if (!container) return;
    const q = query.trim().toLowerCase();
    const matches = q
      ? DATA.products.filter(
          (p) =>
            p.name.toLowerCase().includes(q) ||
            p.brand.toLowerCase().includes(q) ||
            p.category.toLowerCase().includes(q) ||
            p.strap_material.toLowerCase().includes(q)
        )
      : DATA.products.slice(0, 4);

    if (!matches.length) {
      container.innerHTML = `<p style="color:var(--ink-muted);grid-column:1/-1;">No watches matched "${query}". Try searching "chronograph", "phillipes", "stiveson", "gold", or "smart".</p>`;
      return;
    }

    container.innerHTML = matches
      .slice(0, 4)
      .map(
        (p) => `
        <div class="product-card" style="box-shadow:none;">
          <div class="product-media" style="padding:10px;">
            <a href="${UI.pageHref("pages/product.html")}?id=${p.id}" style="display:block;width:100%;height:100%;">
              <img src="${UI.resolveAsset(p.image)}" alt="${p.name}">
            </a>
          </div>
          <div class="product-body" style="padding:12px;">
            <div class="product-brand">${p.brand}</div>
            <div style="font-weight:600;font-size:14px;margin:4px 0;"><a href="${UI.pageHref("pages/product.html")}?id=${p.id}">${p.name}</a></div>
            <div style="font-weight:700;font-size:13.5px;margin-bottom:8px;">${UI.formatPrice(p.sale_price)}</div>
            <a href="${UI.pageHref("pages/product.html")}?id=${p.id}" class="btn btn-primary" style="padding:7px 10px;font-size:11px;">View Watch</a>
          </div>
        </div>
      `
      )
      .join("");
  }

  // --------------------------------------------------------------------------
  // 5. Event Binding
  // --------------------------------------------------------------------------
  function bindEvents() {
    // Delegated product actions across sections, drawers & quick view
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-action]");
      if (btn) {
        const action = btn.dataset.action;
        const id = btn.dataset.id;
        if (action === "add-cart") addToCart(id, 1);
        else if (action === "wishlist") toggleWishlist(id);
        else if (action === "quick-view") openQuickView(id);
        return;
      }

      if (e.target.closest("[data-close-overlays]") || e.target === getBackdrop()) {
        closeAllOverlays();
        return;
      }

      const inc = e.target.closest("[data-cart-inc]");
      if (inc) {
        const idx = Number(inc.dataset.cartIdx);
        if (!Number.isNaN(idx) && cart[idx]) {
          updateCartQty(cart[idx].id, 1, cart[idx].variant || null);
        } else {
          updateCartQty(inc.dataset.cartInc, 1);
        }
      }

      const dec = e.target.closest("[data-cart-dec]");
      if (dec) {
        const idx = Number(dec.dataset.cartIdx);
        if (!Number.isNaN(idx) && cart[idx]) {
          updateCartQty(cart[idx].id, -1, cart[idx].variant || null);
        } else {
          updateCartQty(dec.dataset.cartDec, -1);
        }
      }

      const subBtn = e.target.closest("[data-select-dept]");
      if (subBtn) {
        selectDepartmentSubcategory(
          subBtn.dataset.selectDept,
          subBtn.dataset.selectSubcat,
          subBtn.dataset.scroll === "true"
        );
        return;
      }

      const dropTrigger = e.target.closest(".nav-dropdown-trigger");
      if (dropTrigger) {
        const parent = dropTrigger.closest(".nav-item-dropdown");
        const wasOpen = parent.classList.contains("open");
        closeAllDropdowns();
        if (!wasOpen) {
          parent.classList.add("open");
          dropTrigger.setAttribute("aria-expanded", "true");
        }
        return;
      }

      const mobTrigger = e.target.closest(".mobile-dept-trigger");
      if (mobTrigger) {
        mobTrigger.closest(".mobile-dept-group")?.classList.toggle("open");
        return;
      }

      if (!e.target.closest(".nav-item-dropdown")) {
        closeAllDropdowns();
      }
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeAllOverlays();
        closeAllDropdowns();
      }
    });

    // Header buttons
    document.getElementById("btn-open-search")?.addEventListener("click", () => {
      const panel = document.getElementById("search-overlay");
      openOverlay(panel);
      renderSearchResults("");
      document.getElementById("search-input")?.focus();
    });

    document.getElementById("search-input")?.addEventListener("input", (e) => {
      renderSearchResults(e.target.value);
    });

    document.getElementById("global-search-form")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const q = document.getElementById("search-input")?.value.trim() || "";
      window.location.href = `${UI.pageHref("pages/catalog.html")}${q ? `?q=${encodeURIComponent(q)}` : ""}`;
    });

    document.getElementById("btn-open-wishlist")?.addEventListener("click", () => {
      renderWishlistDrawer();
      openOverlay(document.getElementById("wishlist-drawer"));
    });

    document.getElementById("btn-open-cart")?.addEventListener("click", () => {
      renderCartDrawer();
      openOverlay(document.getElementById("cart-drawer"));
    });

    document.getElementById("btn-open-account")?.addEventListener("click", () => {
      const target = window.VALENOR_AUTH?.isAuthenticated() ? "pages/account.html" : "pages/auth.html";
      window.location.href = UI.pageHref(target);
    });

    // Mobile hamburger menu
    const mobileBtn = document.getElementById("mobile-menu-btn");
    const mobileDrawer = document.getElementById("mobile-nav-drawer");
    mobileBtn?.addEventListener("click", () => {
      const isOpen = mobileDrawer.classList.toggle("open");
      mobileBtn.setAttribute("aria-expanded", String(isOpen));
    });
    mobileDrawer?.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        mobileDrawer.classList.remove("open");
        mobileBtn?.setAttribute("aria-expanded", "false");
      });
    });

    // Copy promo code
    document.getElementById("btn-copy-promo")?.addEventListener("click", () => {
      navigator.clipboard?.writeText("SALE20").catch(() => {});
      showToast("Promo code SALE20 copied — ready for checkout.");
    });

    // Navigate to Checkout
    document.getElementById("btn-proceed-checkout")?.addEventListener("click", () => {
      if (!cart.length) {
        showToast("Add a watch to your bag first.");
        return;
      }
      window.location.href = UI.pageHref("pages/checkout.html");
    });

    // Newsletter subscription (MySQL + local fallback)
    document.getElementById("newsletter-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const input = document.getElementById("newsletter-email");
      const status = document.getElementById("newsletter-status");
      const email = input.value.trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        status.textContent = "Please enter a valid email address.";
        return;
      }
      if (/^https?:/.test(window.location.protocol)) {
        try {
          await fetch("/api/newsletter", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email })
          });
        } catch {
          // Local storage preserves email when static previewing
        }
      }
      const list = loadJson(STORAGE_NEWSLETTER, []);
      if (!list.includes(email)) {
        list.push(email);
        saveJson(STORAGE_NEWSLETTER, list);
      }
      status.textContent = `Welcome to the VALENOR Private List (${email}). Use WELCOME10 for 10% off your first order.`;
      input.value = "";
    });
  }

  window.VALENOR_APP = {
    getCart: () => cart.map((item) => ({ ...item })),
    addToCart,
    updateCartQty,
    removeFromCart,
    clearCart,
    toggleWishlist,
    openQuickView,
    showToast,
    renderSections,
    openOverlay,
    closeAllOverlays,
    isWishlisted: (id) => wishlist.includes(Number(id))
  };

  function mapDbProductToHomepage(p, staticLookup) {
    const prev = staticLookup.get(Number(p.id));
    const slugs = Array.isArray(p.category_slugs) ? p.category_slugs : [];
    const genderL = String(p.gender || "").toLowerCase();
    const typeL = String(p.watch_type || "").toLowerCase();
    const strapL = String(p.strap_material || "").toLowerCase();

    let dept = prev?.category || "mens";
    if (slugs.includes("smart-watches") || typeL.includes("smart")) dept = "smart";
    else if (genderL === "women" || slugs.includes("womens-watches")) dept = "womens";
    else if (genderL === "kids" || slugs.includes("kids-watches")) dept = "kids";
    else if (genderL === "couple" || slugs.includes("couple-watches")) dept = "couple";
    else if (genderL === "men" || slugs.includes("mens-watches")) dept = "mens";

    const subcats = new Set(prev?.subcategories || []);
    if (!subcats.size) {
      if (dept === "smart") {
        if (genderL === "women") subcats.add("womens");
        else if (genderL === "kids") subcats.add("kids");
        else subcats.add("mens");
      }
      if (typeL.includes("chrono")) subcats.add("chronograph");
      if (typeL.includes("auto")) subcats.add("automatic");
      if (typeL.includes("digit")) subcats.add("digital");
      if (typeL.includes("analog")) subcats.add("analog");
      if (strapL.includes("leather")) subcats.add("leather");
      if (strapL.includes("steel")) subcats.add("steel");
      if (slugs.includes("luxury-watches")) subcats.add("luxury");
      if (slugs.includes("formal-watches")) subcats.add("formal");
      if (slugs.includes("sports-watches")) subcats.add("sports");
      if (slugs.includes("casual-watches")) subcats.add("casual");
    }

    const collections = [];
    if (p.is_best_seller || p.is_featured) collections.push("best_sellers");
    if (p.is_new_arrival) collections.push("new_arrivals");
    if (Number(p.discount_percent || 0) >= 25) collections.push("special_offers");

    return {
      ...prev,
      ...p,
      category: dept,
      subcategories: [...subcats],
      collections
    };
  }

  async function syncStorefrontConfig() {
    if (!/^https?:/.test(window.location.protocol)) return;
    try {
      const [cfgRes, prodRes] = await Promise.all([
        fetch("/api/storefront/config", { headers: { Accept: "application/json" } }),
        fetch("/api/products?limit=100", { headers: { Accept: "application/json" } })
      ]);

      if (cfgRes.ok) {
        const json = await cfgRes.json();
        const cfg = json?.config;
        if (cfg) {
          if (cfg.currency) DATA.settings.currency = cfg.currency;
          if (cfg.website_name) {
            document.querySelectorAll(".brand-name").forEach((el) => { el.textContent = cfg.website_name.split(" ")[0] || cfg.website_name; });
          }
          if (cfg.campaign) {
            DATA.campaign.targetDateIso = cfg.campaign.ends_at;
            const pill = document.querySelector(".promo-pill");
            if (pill && cfg.campaign.badge_text) pill.textContent = cfg.campaign.badge_text;
          }
        }
      }

      if (prodRes.ok) {
        const pJson = await prodRes.json();
        if (pJson?.success && Array.isArray(pJson.products)) {
          const staticLookup = new Map(DATA.products.map((sp) => [Number(sp.id), sp]));
          const liveList = pJson.products.map((lp) => mapDbProductToHomepage(lp, staticLookup));
          DATA.products.splice(0, DATA.products.length, ...liveList);

          // Prune any removed/deleted watches from cart & wishlist cleanly
          const liveIds = new Set(liveList.map((x) => Number(x.id)));
          const cleanedCart = cart.filter((c) => liveIds.has(Number(c.id)));
          if (cleanedCart.length !== cart.length) {
            cart = cleanedCart;
            saveJson(STORAGE_CART, cart);
          }
          const cleanedWish = wishlist.filter((id) => liveIds.has(Number(id)));
          if (cleanedWish.length !== wishlist.length) {
            wishlist = cleanedWish;
            saveJson(STORAGE_WISHLIST, wishlist);
          }
        }
      }

      renderSections();
      updateHeaderCounts();
      renderCartDrawer();
      renderWishlistDrawer();
    } catch {
      // Offline / static preview safely keeps seeded defaults
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.getElementById("shared-header-host") && UI.mountSubpageChrome) {
      UI.mountSubpageChrome(document.body.dataset.nav || "catalog");
    }
    if (window.VALENOR_AUTH?.isAuthenticated()) {
      const acctBtn = document.getElementById("btn-open-account");
      if (acctBtn && !acctBtn.querySelector(".account-active-dot")) {
        acctBtn.insertAdjacentHTML("beforeend", '<span class="account-active-dot" aria-hidden="true"></span>');
        acctBtn.title = `My Account (${window.VALENOR_AUTH.getSession().user.full_name})`;
      }
    }
    syncStorefrontConfig();
    startCountdown();
    renderSections();
    updateHeaderCounts();
    renderCartDrawer();
    renderWishlistDrawer();
    bindEvents();
  });
})();

