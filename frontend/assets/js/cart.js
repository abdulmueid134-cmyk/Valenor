// VALENOR Horology — Shopping Cart & Coupon Calculation Controller
(() => {
  const DATA = window.VALENOR_DATA;
  const UI = window.VALENOR_UI;
  const COUPON_KEY = "valenor_active_coupon_v1";

  let config = {
    taxRatePercent: 0,
    standardShippingFee: 250,
    freeShippingThreshold: 15000
  };
  let appliedCoupon = null;

  try {
    const raw = localStorage.getItem(COUPON_KEY);
    if (raw) appliedCoupon = JSON.parse(raw);
  } catch {
    appliedCoupon = null;
  }

  function saveActiveCoupon(c) {
    appliedCoupon = c;
    try {
      if (c) localStorage.setItem(COUPON_KEY, JSON.stringify(c));
      else localStorage.removeItem(COUPON_KEY);
    } catch {}
  }

  async function loadCheckoutConfig() {
    if (!/^https?:/.test(window.location.protocol)) return;
    try {
      const res = await fetch("/api/checkout/config");
      if (res.ok) {
        const json = await res.json();
        if (json?.success && json.config) config = json.config;
      }
    } catch {}
  }

  async function validateCouponCode(code, subtotal) {
    const clean = String(code || "").trim().toUpperCase();
    if (!clean) throw new Error("Please enter a coupon code.");

    if (/^https?:/.test(window.location.protocol)) {
      const headers = { "Content-Type": "application/json", Accept: "application/json" };
      const sess = window.VALENOR_AUTH?.getSession();
      if (sess?.token) headers.Authorization = `Bearer ${sess.token}`;
      const res = await fetch("/api/coupons/validate", {
        method: "POST",
        headers,
        body: JSON.stringify({ code: clean, subtotal, email: sess?.user?.email || null })
      });
      const ct = res.headers.get("content-type") || "";
      if (ct.includes("application/json")) {
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || "Invalid coupon.");
        return json.coupon;
      }
    }

    // Fallback offline rules mirroring seed data
    const rules = {
      WELCOME10: { code: "WELCOME10", discountType: "percent", discountValue: 10, max: 10000, min: 5000, description: "10% off welcome order" },
      SALE20: { code: "SALE20", discountType: "percent", discountValue: 20, max: 25000, min: 15000, description: "20% off orders PKR 15,000+" },
      FREESHIP: { code: "FREESHIP", discountType: "free_shipping", discountValue: 0, min: 3000, description: "Free Standard Delivery" }
    };
    const r = rules[clean];
    if (!r) throw new Error(`Coupon "${clean}" is invalid or expired.`);
    if (subtotal < r.min) throw new Error(`Minimum purchase of ${UI.formatPrice(r.min)} required.`);
    const discountAmount =
      r.discountType === "percent" ? Math.min(r.max, Math.round(subtotal * (r.discountValue / 100))) : 0;
    return { ...r, discountAmount, freeShipping: r.discountType === "free_shipping" };
  }

  function renderCartPage() {
    const app = window.VALENOR_APP;
    const cart = app ? app.getCart() : [];
    const host = document.getElementById("cart-items-host");
    const checkoutBtn = document.getElementById("btn-cart-checkout");

    if (!cart.length) {
      host.innerHTML = `
        <div style="text-align:center;padding:40px 12px;">
          <h2 style="font-family:var(--font-display);font-size:30px;margin:0 0 8px;">Your Shopping Bag is Empty</h2>
          <p style="color:var(--ink-muted);margin:0 0 20px;">Explore our Swiss-caliber timepieces to begin your order.</p>
          <a href="catalog.html" class="btn btn-primary">Browse Watches</a>
        </div>
      `;
      checkoutBtn.style.pointerEvents = "none";
      checkoutBtn.style.opacity = "0.5";
      updateSummary(0, 0);
      return;
    }

    checkoutBtn.style.pointerEvents = "auto";
    checkoutBtn.style.opacity = "1";

    let subtotal = 0;
    let productDiscount = 0;

    host.innerHTML = cart
      .map((item) => {
        const p = DATA.products.find((x) => x.id === Number(item.id));
        if (!p) return "";
        const variant = item.variant || p.colors?.[0]?.name || p.color || "Standard";
        const unitOrig = Number(p.original_price || p.sale_price);
        const unitSale = Number(p.sale_price);
        subtotal += unitSale * item.qty;
        productDiscount += Math.max(0, unitOrig - unitSale) * item.qty;

        return `
          <article class="cart-item-row">
            <a href="product.html?id=${p.id}">
              <img src="${UI.resolveAsset(p.image)}" alt="${p.name}" class="cart-item-img">
            </a>
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--accent-gold);text-transform:uppercase;letter-spacing:0.08em;">${p.brand}</div>
              <h3 style="font-family:var(--font-display);font-size:22px;margin:2px 0 4px;">
                <a href="product.html?id=${p.id}">${p.name}</a>
              </h3>
              <div style="font-size:13px;color:var(--ink-secondary);margin-bottom:4px;">Selected Variant: <strong>${variant}</strong></div>
              <div style="font-size:13.5px;">
                <strong>${UI.formatPrice(unitSale)}</strong>
                ${unitOrig > unitSale ? `<span class="price-original" style="margin-left:6px;">${UI.formatPrice(unitOrig)}</span>` : ""}
              </div>
              <div style="display:flex;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap;">
                <div class="qty-stepper" style="height:36px;">
                  <button type="button" style="height:36px;" data-page-dec="${p.id}" data-variant="${variant}" aria-label="Decrease quantity">&minus;</button>
                  <input type="text" readonly value="${item.qty}" style="height:36px;width:42px;" aria-label="Quantity">
                  <button type="button" style="height:36px;" data-page-inc="${p.id}" data-variant="${variant}" aria-label="Increase quantity">+</button>
                </div>
                <button type="button" style="background:none;border:0;color:var(--accent-sale);font-size:12.5px;text-decoration:underline;" data-page-remove="${p.id}" data-variant="${variant}">Remove</button>
              </div>
            </div>
            <div style="font-weight:700;font-size:16px;text-align:right;">
              ${UI.formatPrice(unitSale * item.qty)}
            </div>
          </article>
        `;
      })
      .join("");

    updateSummary(subtotal, productDiscount);
  }

  function updateSummary(subtotal, productDiscount) {
    let couponDiscount = 0;
    let freeShip = false;

    if (appliedCoupon && subtotal > 0) {
      if (appliedCoupon.discountType === "free_shipping") freeShip = true;
      else couponDiscount = Math.min(subtotal, Number(appliedCoupon.discountAmount || 0));
      document.getElementById("cart-coupon-input").value = appliedCoupon.code;
      document.getElementById("cart-coupon-msg").innerHTML =
        `<span style="color:var(--accent-emerald);">Applied <strong>${appliedCoupon.code}</strong> (${appliedCoupon.description || ""})</span> <button type="button" id="btn-remove-coupon" style="background:none;border:0;color:#c42b2b;text-decoration:underline;font-size:12px;">Remove</button>`;
    } else {
      document.getElementById("cart-coupon-msg").textContent = "";
    }

    const net = Math.max(0, subtotal - couponDiscount);
    const delivery =
      subtotal === 0 ? 0 : freeShip || net >= config.freeShippingThreshold ? 0 : config.standardShippingFee;
    const tax = Math.round(net * ((config.taxRatePercent || 0) / 100));
    const total = net + delivery + tax;

    document.getElementById("sum-subtotal").textContent = UI.formatPrice(subtotal);
    document.getElementById("sum-product-discount").textContent = productDiscount ? `-${UI.formatPrice(productDiscount)}` : UI.formatPrice(0);
    document.getElementById("sum-coupon-discount").textContent = couponDiscount ? `-${UI.formatPrice(couponDiscount)}` : UI.formatPrice(0);
    document.getElementById("sum-delivery").textContent = delivery === 0 && subtotal > 0 ? "FREE" : UI.formatPrice(delivery);
    document.getElementById("sum-tax").textContent = UI.formatPrice(tax);
    document.getElementById("sum-total").textContent = UI.formatPrice(total);
  }

  document.addEventListener("DOMContentLoaded", async () => {
    await loadCheckoutConfig();
    renderCartPage();

    document.getElementById("cart-items-host").addEventListener("click", (e) => {
      const inc = e.target.closest("[data-page-inc]");
      if (inc) window.VALENOR_APP.updateCartQty(inc.dataset.pageInc, 1, inc.dataset.variant);

      const dec = e.target.closest("[data-page-dec]");
      if (dec) window.VALENOR_APP.updateCartQty(dec.dataset.pageDec, -1, dec.dataset.variant);

      const rem = e.target.closest("[data-page-remove]");
      if (rem) window.VALENOR_APP.removeFromCart(rem.dataset.pageRemove, rem.dataset.variant);
    });

    document.getElementById("cart-coupon-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      const cart = window.VALENOR_APP.getCart();
      const subtotal = cart.reduce((s, i) => {
        const p = DATA.products.find((x) => x.id === Number(i.id));
        return s + (p ? p.sale_price * i.qty : 0);
      }, 0);
      const msgEl = document.getElementById("cart-coupon-msg");
      try {
        const coupon = await validateCouponCode(document.getElementById("cart-coupon-input").value, subtotal);
        saveActiveCoupon(coupon);
        renderCartPage();
      } catch (err) {
        saveActiveCoupon(null);
        renderCartPage();
        msgEl.innerHTML = `<span style="color:#c42b2b;">${err.message}</span>`;
      }
    });

    document.addEventListener("click", (e) => {
      if (e.target.id === "btn-remove-coupon") {
        saveActiveCoupon(null);
        document.getElementById("cart-coupon-input").value = "";
        renderCartPage();
      }
    });

    document.addEventListener("valenor:cart-updated", renderCartPage);
  });
})();

