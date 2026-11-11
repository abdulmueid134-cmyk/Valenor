// Shared UI Component Renderers & Multi-Page Shell
// Works across root (index.html), subpages (pages/catalog.html, pages/product.html), file://, and HTTP servers.

window.VALENOR_UI = (() => {
  const { settings } = window.VALENOR_DATA;
  const IS_SUBPAGE = window.location.pathname.includes("/pages/");
  const BASE = IS_SUBPAGE ? "../" : "";

  function escapeHtml(val) {
    return String(val ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function resolveAsset(path) {
    if (!path) return "";
    if (/^(https?:|data:|\/)/i.test(path)) return path;
    return BASE + path;
  }

  function pageHref(pathFromFrontend) {
    return BASE + pathFromFrontend;
  }

  function formatPrice(amount) {
    const num = Number(amount) || 0;
    return `${settings.currency} ${num.toLocaleString("en-PK")}`;
  }

  function renderStars(rating) {
    const full = Math.round(Number(rating) || 5);
    return "★".repeat(Math.min(5, full)) + "☆".repeat(Math.max(0, 5 - full));
  }

  function renderCategoryCard(cat) {
    return `
      <a class="category-card" href="${pageHref(cat.href)}" data-category="${escapeHtml(cat.slug)}">
        <div class="category-img-wrap">
          <img src="${resolveAsset(cat.image)}" alt="${escapeHtml(cat.name)}" loading="lazy" decoding="async">
        </div>
        <div class="category-meta">
          <h3 class="category-name">${escapeHtml(cat.name)}</h3>
          <span class="category-count">${escapeHtml(cat.countLabel)}</span>
        </div>
      </a>
    `;
  }

  function renderProductCard(product, isWishlisted) {
    const wishClass = isWishlisted ? "circle-action-btn active-wishlist" : "circle-action-btn";
    const detailHref = `${pageHref("pages/product.html")}?id=${product.id}`;
    const isOut = product.stock_status === "Out of Stock" || product.stock_quantity === 0;
    const stockClass = isOut ? "stock-pill stock-out" : product.stock_status === "Limited Stock" ? "stock-pill stock-low" : "stock-pill";
    const pName = escapeHtml(product.name);
    const pBrand = escapeHtml(product.brand);

    return `
      <article class="product-card" data-product-id="${product.id}">
        <div class="product-media">
          <div class="badge-stack">
            ${product.discount_percent ? `<span class="badge-discount">${product.discount_percent}% OFF</span>` : ""}
            ${product.badge ? `<span class="badge-tag">${escapeHtml(product.badge)}</span>` : ""}
          </div>
          <div class="card-float-actions">
            <button type="button" class="${wishClass}" data-action="wishlist" data-id="${product.id}" aria-label="Toggle wishlist for ${pName}" title="Save to Wishlist">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="${isWishlisted ? "currentColor" : "none"}" stroke="currentColor" stroke-width="1.8">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
            </button>
            <button type="button" class="circle-action-btn" data-action="quick-view" data-id="${product.id}" aria-label="Quick view ${pName}" title="Quick View">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <a href="${detailHref}" aria-label="View ${pName} details" style="display:block;width:100%;height:100%;">
            <img src="${resolveAsset(product.image)}" alt="${pName}" loading="lazy" decoding="async">
          </a>
        </div>
        <div class="product-body">
          <div class="product-brand-row">
            <span class="product-brand">${pBrand}</span>
            <span class="${stockClass}">${escapeHtml(product.stock_status)}</span>
          </div>
          <h3 class="product-title"><a href="${detailHref}">${pName}</a></h3>
          <p class="product-specs-mini">${escapeHtml(product.movement)} · ${escapeHtml(product.strap_material)}</p>
          <div class="product-rating" aria-label="Rated ${product.rating} out of 5">
            <span class="stars">${renderStars(product.rating)}</span>
            <span>${Number(product.rating).toFixed(1)}</span>
            <span class="review-count">(${product.review_count})</span>
          </div>
          <div class="product-price-row">
            <span class="price-sale">${formatPrice(product.sale_price)}</span>
            ${product.original_price > product.sale_price ? `<span class="price-original">${formatPrice(product.original_price)}</span>` : ""}
          </div>
          <div class="product-card-footer">
            <button type="button" class="btn btn-primary btn-card-add" data-action="add-cart" data-id="${product.id}" ${isOut ? "disabled" : ""}>
              ${isOut ? "Out of Stock" : "Add to Bag"}
            </button>
            <a href="${detailHref}" class="btn-card-view" style="display:inline-flex;align-items:center;justify-content:center;">Details</a>
          </div>
        </div>
      </article>
    `;
  }

  function renderCapsuleCard(cap) {
    return `
      <a class="capsule-card" href="${pageHref(cap.href)}">
        <div class="capsule-img">
          <img src="${resolveAsset(cap.image)}" alt="${cap.title}">
        </div>
        <div class="capsule-info">
          <h3>${cap.title}</h3>
          <p>${cap.description}</p>
          <span class="section-link">Explore Capsule &rarr;</span>
        </div>
      </a>
    `;
  }

  function renderReviewCard(rev) {
    return `
      <article class="review-card">
        <div class="stars" aria-label="${rev.rating} stars">${renderStars(rev.rating)}</div>
        <p class="review-quote">“${escapeHtml(rev.quote)}”</p>
        <div class="review-author">
          <div>
            <div class="review-name">${escapeHtml(rev.author)} · ${escapeHtml(rev.city)}</div>
            <div class="review-product">${escapeHtml(rev.product)}</div>
          </div>
          <span class="verified-badge">Verified Buyer</span>
        </div>
      </article>
    `;
  }

  // Shared header, footer & interactive drawers mounted on subpages (catalog.html, product.html)
  function mountSubpageChrome(activeNav = "catalog") {
    const headerHost = document.getElementById("shared-header-host");
    const footerHost = document.getElementById("shared-footer-host");
    const overlaysHost = document.getElementById("shared-overlays-host");

    if (headerHost) {
      headerHost.innerHTML = `
        <div class="announcement-bar" role="region" aria-label="Promotional Announcement">
          <div class="announcement-inner">
            <div class="announcement-promo">
              <span class="promo-pill">11.11 Horology Sale</span>
              <span>Up to <strong>50% OFF</strong> Selected Watches &bull; Extra 20% with <strong>SALE20</strong></span>
            </div>
            <div class="announcement-right">
              <div class="countdown" aria-label="Sale countdown timer">
                <span class="countdown-label">Ends in</span>
                <span class="countdown-box" id="cd-days">02d</span>
                <span class="countdown-box" id="cd-hours">14h</span>
                <span class="countdown-box" id="cd-mins">35m</span>
                <span class="countdown-box" id="cd-secs">00s</span>
              </div>
              <a href="${pageHref("pages/catalog.html?minDiscount=50")}" class="announcement-cta">Shop Now</a>
            </div>
          </div>
        </div>
        <header class="site-header" role="banner">
          <div class="navbar">
            <a href="${pageHref("index.html")}" class="brand-logo" aria-label="VALENOR Home">
              <svg class="brand-logo-svg" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <polygon points="32,7 51,18 51,46 32,57 13,46 13,18" stroke="#B8934A" stroke-width="2.5" stroke-linejoin="round"/>
                <circle cx="32" cy="32" r="14" stroke="#FFFFFF" stroke-opacity="0.22" stroke-width="1.2"/>
                <polygon points="32,14 34.5,18 32,20 29.5,18" fill="#B8934A"/>
                <path d="M23.5 25.5L32 32L41.5 23.5" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="32" cy="32" r="2.6" fill="#B8934A"/>
              </svg>
              <div class="brand-wordmark">
                <span class="brand-name">VALENOR</span>
                <span class="brand-tag">Genève · Horology</span>
              </div>
            </a>
            <nav aria-label="Primary Navigation">
              <ul class="nav-links">
                <li><a href="${pageHref("index.html")}" class="nav-link">Home</a></li>
                <li><a href="${pageHref("pages/catalog.html")}" class="nav-link ${activeNav === "catalog" ? "active" : ""}">All Catalog</a></li>
                <li><a href="${pageHref("pages/catalog.html?minDiscount=50")}" class="nav-link nav-link-sale">50% Sale</a></li>
                <li><a href="${pageHref("pages/catalog.html?category=mens-watches")}" class="nav-link">Men's Watches</a></li>
                <li><a href="${pageHref("pages/catalog.html?category=womens-watches")}" class="nav-link">Women's Watches</a></li>
                <li><a href="${pageHref("pages/catalog.html?category=kids-watches")}" class="nav-link">Kids' Watches</a></li>
                <li><a href="${pageHref("pages/catalog.html?category=couple-watches")}" class="nav-link">Couple Watches</a></li>
                <li><a href="${pageHref("pages/catalog.html?category=smart-watches")}" class="nav-link">Smart Watches</a></li>
                <li><a href="${pageHref("pages/catalog.html?sort=newest")}" class="nav-link">New Arrivals</a></li>
                <li><a href="${pageHref("pages/catalog.html?sort=best_selling")}" class="nav-link">Best Sellers</a></li>
              </ul>
            </nav>
            <div class="nav-actions">
              <button type="button" class="icon-btn" id="btn-open-account" aria-label="Customer Account" title="Account">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </button>
              <button type="button" class="icon-btn" id="btn-open-search" aria-label="Search Watches" title="Search">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </button>
              <button type="button" class="icon-btn" id="btn-open-wishlist" aria-label="Wishlist" title="Wishlist">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="icon-badge" id="wishlist-count-badge">0</span>
              </button>
              <button type="button" class="icon-btn" id="btn-open-cart" aria-label="Shopping Bag" title="Shopping Bag">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="icon-badge" id="cart-count-badge">0</span>
              </button>
              <button type="button" class="hamburger-btn" id="mobile-menu-btn" aria-label="Toggle Menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
              </button>
            </div>
          </div>
          <div class="mobile-nav-drawer" id="mobile-nav-drawer">
            <ul class="mobile-nav-list">
              <li><a href="${pageHref("index.html")}">Home</a></li>
              <li><a href="${pageHref("pages/catalog.html")}">All Catalog</a></li>
              <li><a href="${pageHref("pages/catalog.html?minDiscount=50")}" style="color:#ff8e8e;">50% Sale</a></li>
              <li><a href="${pageHref("pages/catalog.html?category=mens-watches")}">Men's Watches</a></li>
              <li><a href="${pageHref("pages/catalog.html?category=womens-watches")}">Women's Watches</a></li>
              <li><a href="${pageHref("pages/catalog.html?category=kids-watches")}">Kids' Watches</a></li>
              <li><a href="${pageHref("pages/catalog.html?category=couple-watches")}">Couple Watches</a></li>
              <li><a href="${pageHref("pages/catalog.html?category=smart-watches")}">Smart Watches</a></li>
            </ul>
          </div>
        </header>
      `;
    }

    if (footerHost) {
      footerHost.innerHTML = `
        <footer class="site-footer" id="footer">
          <div class="container">
            <div class="footer-grid">
              <div class="footer-col">
                <a href="${pageHref("index.html")}" class="brand-logo" style="margin-bottom:14px;">
                  <svg class="brand-logo-svg" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                    <polygon points="32,7 51,18 51,46 32,57 13,46 13,18" stroke="#B8934A" stroke-width="2.5" stroke-linejoin="round"/>
                    <path d="M23.5 25.5L32 32L41.5 23.5" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="32" cy="32" r="2.6" fill="#B8934A"/>
                  </svg>
                  <div class="brand-wordmark">
                    <span class="brand-name">VALENOR</span>
                    <span class="brand-tag">Genève · Horology</span>
                  </div>
                </a>
                <p style="font-size:13.5px;color:#a1a1aa;max-width:300px;margin:0;">
                  Independent luxury watch house delivering Swiss-caliber precision, transparent PKR pricing, and Cash on Delivery nationwide.
                </p>
              </div>
              <div class="footer-col">
                <h4>Shop Departments</h4>
                <ul class="footer-links">
                  <li><a href="${pageHref("pages/catalog.html?category=mens-watches")}">Men's Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=womens-watches")}">Women's Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=couple-watches")}">Couple Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=kids-watches")}">Kids' Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=smart-watches")}">Smart Watches</a></li>
                </ul>
              </div>
              <div class="footer-col">
                <h4>Watch Styles</h4>
                <ul class="footer-links">
                  <li><a href="${pageHref("pages/catalog.html?category=luxury-watches")}">Luxury Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=automatic-watches")}">Automatic Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=formal-watches")}">Formal Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=sports-watches")}">Sports Watches</a></li>
                  <li><a href="${pageHref("pages/catalog.html?category=leather-strap-watches")}">Leather Strap Watches</a></li>
                  <li><a href="${pageHref("pages/track-order.html")}">Track Order &amp; Returns</a></li>
                  <li><a href="${pageHref("pages/policies.html")}">Return, Privacy &amp; Terms</a></li>
                </ul>
              </div>
              <div class="footer-col">
                <h4>Concierge &amp; Boutiques</h4>
                <ul class="footer-links">
                  <li>Email: concierge@valenor.pk</li>
                  <li>WhatsApp / Phone: +92 300 048 1111</li>
                  <li>Lahore · Karachi · Islamabad</li>
                  <li>Mon–Sat: 10:00 AM – 8:00 PM PKT</li>
                </ul>
              </div>
            </div>
            <div class="footer-bottom">
              <span>&copy; 2026 VALENOR Horology. All rights reserved. Prices shown in PKR.</span>
              <div class="payment-badges">
                <span class="payment-pill">Cash on Delivery</span>
                <span class="payment-pill">Bank Transfer</span>
                <span class="payment-pill">Insured Courier</span>
                <span class="payment-pill">2–3 Yr Warranty</span>
              </div>
            </div>
          </div>
        </footer>
      `;
    }

    if (overlaysHost) {
      overlaysHost.innerHTML = `
        <div class="overlay-backdrop" id="overlay-backdrop"></div>
        <div class="search-bar-overlay" id="search-overlay" role="dialog" aria-label="Search Watches">
          <div class="container">
            <form id="global-search-form" style="display:flex;align-items:center;gap:12px;">
              <input type="search" id="search-input" placeholder="Search watches by name, brand, strap, movement..." style="flex:1;padding:13px 16px;border:1px solid var(--border-strong);border-radius:var(--radius-sm);font-size:15px;">
              <button type="submit" class="btn btn-primary" style="padding:13px 18px;">View All</button>
              <button type="button" class="close-btn" data-close-overlays aria-label="Close search">&times;</button>
            </form>
            <div class="search-results-grid" id="search-results"></div>
          </div>
        </div>
        <aside class="drawer" id="wishlist-drawer" role="dialog" aria-label="Saved Wishlist">
          <div class="drawer-header"><h3>Saved Wishlist</h3><button type="button" class="close-btn" data-close-overlays aria-label="Close wishlist">&times;</button></div>
          <div class="drawer-body" id="wishlist-drawer-body"></div>
        </aside>
        <aside class="drawer" id="cart-drawer" role="dialog" aria-label="Shopping Bag">
          <div class="drawer-header"><h3>Shopping Bag</h3><button type="button" class="close-btn" data-close-overlays aria-label="Close shopping bag">&times;</button></div>
          <div class="drawer-body" id="cart-drawer-body"></div>
          <div class="drawer-footer">
            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;margin-bottom:12px;">
              <span>Estimated Subtotal</span><span id="cart-drawer-subtotal">PKR 0</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <a href="${pageHref("pages/cart.html")}" class="btn btn-outline" style="padding:11px 12px;">View Cart</a>
              <button type="button" class="btn btn-primary" id="btn-proceed-checkout" style="padding:11px 12px;">Checkout</button>
            </div>
          </div>
        </aside>
        <div class="modal" id="quick-view-modal" role="dialog" aria-label="Watch Quick View"><div id="quick-view-content"></div></div>
        <div class="modal" id="account-modal" role="dialog" aria-label="Customer Account" style="width:min(460px,92vw);padding:32px;">
          <button type="button" class="close-btn" style="position:absolute;top:16px;right:16px;" data-close-overlays aria-label="Close account modal">&times;</button>
          <div class="section-eyebrow">Customer Portal</div>
          <h3 style="font-family:var(--font-display);font-size:30px;margin:4px 0 10px;">VALENOR Collector Account</h3>
          <p style="color:var(--ink-secondary);font-size:14px;margin:0 0 18px;">
            Full registration, sign-in, saved delivery addresses, and order tracking connect to MySQL in Phase 3. Your Wishlist and Shopping Bag remain saved locally on this device.
          </p>
          <button type="button" class="btn btn-outline" data-close-overlays>Close</button>
        </div>
        <div class="toast" id="site-toast" role="status" aria-live="polite"></div>
      `;
    }
  }

  return {
    IS_SUBPAGE,
    escapeHtml,
    resolveAsset,
    pageHref,
    formatPrice,
    renderStars,
    renderCategoryCard,
    renderProductCard,
    renderCapsuleCard,
    renderReviewCard,
    mountSubpageChrome
  };
})();
