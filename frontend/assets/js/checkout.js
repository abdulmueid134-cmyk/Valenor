// VALENOR Horology — Checkout, Coupon Validation, Payment Methods & Order Confirmation
(() => {
  const DATA = window.VALENOR_DATA;
  const UI = window.VALENOR_UI;
  const AUTH = window.VALENOR_AUTH;
  const COUPON_KEY = "valenor_active_coupon_v1";

  let config = {
    currency: "PKR",
    taxRatePercent: 0,
    standardShippingFee: 250,
    expressShippingFee: 550,
    freeShippingThreshold: 15000,
    paymentMethods: { cod: true, bank_transfer: true, online_gateway: false },
    bankTransferDetails: "Meezan Bank · VALENOR Horology · IBAN: PK12MEZN0002140105551111"
  };
  let appliedCoupon = null;
  let idempotencyKey = makeIdempotencyKey();
  let isSubmitting = false;

  function makeIdempotencyKey() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `idem_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
  }

  function showAlert(msg, kind = "error") {
    const el = document.getElementById("checkout-alert");
    el.className = `form-alert show ${kind}`;
    el.textContent = msg;
    el.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function clearAlert() {
    const el = document.getElementById("checkout-alert");
    el.className = "form-alert";
    el.textContent = "";
  }

  function loadStoredCoupon() {
    try {
      const raw = localStorage.getItem(COUPON_KEY);
      appliedCoupon = raw ? JSON.parse(raw) : null;
    } catch {
      appliedCoupon = null;
    }
  }

  function saveStoredCoupon(c) {
    appliedCoupon = c;
    try {
      if (c) localStorage.setItem(COUPON_KEY, JSON.stringify(c));
      else localStorage.removeItem(COUPON_KEY);
    } catch {}
  }

  async function loadConfig() {
    try {
      const res = await fetch("/api/checkout/config");
      if (res.ok) {
        const json = await res.json();
        if (json?.success && json.config) config = json.config;
      }
    } catch {}
    document.getElementById("co-bank-details").textContent = config.bankTransferDetails;
    document.getElementById("label-express-fee").textContent = UI.formatPrice(config.expressShippingFee);

    if (config.paymentMethods?.online_gateway) {
      const card = document.getElementById("pay-card-online");
      card.classList.remove("disabled");
      card.querySelector("input").disabled = false;
      document.getElementById("co-online-status").textContent = "Redirect securely to payment gateway after placing order.";
    }
  }

  async function prefillCustomerAndAddresses() {
    const sess = AUTH?.getSession();
    if (!sess?.user) return;
    document.getElementById("co-fullname").value = sess.user.full_name || "";
    document.getElementById("co-email").value = sess.user.email || "";
    document.getElementById("co-phone").value = sess.user.phone || "";

    const addresses = await AUTH.listAddresses();
    if (!addresses.length) return;

    const host = document.getElementById("co-saved-addresses");
    host.style.display = "block";
    host.innerHTML =
      `<div style="font-size:12.5px;font-weight:600;margin-bottom:8px;">Use a Saved Delivery Address:</div>` +
      addresses
        .map(
          (a, i) => `
          <label class="choice-option ${i === 0 ? "selected" : ""}" style="padding:10px 14px;" data-saved-addr-idx="${i}">
            <span style="display:flex;gap:8px;align-items:center;font-size:13px;">
              <input type="radio" name="savedAddressPick" value="${i}" ${i === 0 ? "checked" : ""}>
              <span><strong>${a.full_name}</strong> &mdash; ${a.street_address}, ${a.area}, ${a.city}</span>
            </span>
            ${a.is_default ? `<span class="default-pill">Default</span>` : ""}
          </label>
        `
        )
        .join("");

    const applyAddr = (idx) => {
      const a = addresses[idx];
      if (!a) return;
      document.getElementById("co-fullname").value = a.full_name || sess.user.full_name || "";
      document.getElementById("co-phone").value = a.phone || sess.user.phone || "";
      document.getElementById("co-city").value = a.city || "";
      document.getElementById("co-area").value = a.area || "";
      document.getElementById("co-street").value = a.street_address || "";
      document.getElementById("co-postal").value = a.postal_code || "";
      document.getElementById("co-instructions").value = a.delivery_instructions || "";
    };

    applyAddr(0);
    host.addEventListener("change", (e) => {
      if (e.target.name === "savedAddressPick") applyAddr(Number(e.target.value));
    });
  }

  function renderOrderSummary() {
    const cart = window.VALENOR_APP ? window.VALENOR_APP.getCart() : [];
    const listHost = document.getElementById("co-items-list");
    const placeBtn = document.getElementById("btn-place-order");

    if (!cart.length) {
      listHost.innerHTML = `<p style="color:var(--ink-muted);margin:0;">Your shopping bag is empty. <a href="catalog.html" style="text-decoration:underline;color:var(--ink-primary);">Browse watches</a>.</p>`;
      placeBtn.disabled = true;
      return;
    }
    placeBtn.disabled = false;

    let subtotal = 0;
    let prodDiscount = 0;

    listHost.innerHTML = cart
      .map((item) => {
        const p = DATA.products.find((x) => x.id === Number(item.id));
        if (!p) return "";
        const variant = item.variant || p.colors?.[0]?.name || p.color || "Standard";
        subtotal += p.sale_price * item.qty;
        prodDiscount += Math.max(0, (p.original_price || p.sale_price) - p.sale_price) * item.qty;
        return `
          <div style="display:flex;gap:10px;align-items:center;padding-bottom:10px;border-bottom:1px solid var(--border-subtle);">
            <img src="${UI.resolveAsset(p.image)}" alt="${p.name}" style="width:52px;height:52px;object-fit:contain;background:#fff;border-radius:4px;padding:3px;">
            <div style="flex:1;min-width:0;">
              <div style="font-size:13.5px;font-weight:600;">${p.name}</div>
              <div style="font-size:12px;color:var(--ink-muted);">${variant} &times; ${item.qty}</div>
            </div>
            <strong style="font-size:13.5px;">${UI.formatPrice(p.sale_price * item.qty)}</strong>
          </div>
        `;
      })
      .join("");

    let couponDiscount = 0;
    let freeShip = false;
    if (appliedCoupon) {
      if (appliedCoupon.discountType === "free_shipping") freeShip = true;
      else couponDiscount = Math.min(subtotal, Number(appliedCoupon.discountAmount || 0));
      document.getElementById("co-coupon-input").value = appliedCoupon.code;
      document.getElementById("co-coupon-msg").innerHTML =
        `<span style="color:var(--accent-emerald);">Applied <strong>${appliedCoupon.code}</strong></span> <button type="button" id="co-remove-coupon" style="background:none;border:0;color:#c42b2b;text-decoration:underline;font-size:12px;">Remove</button>`;
    } else {
      document.getElementById("co-coupon-msg").textContent = "";
    }

    const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked')?.value || "standard";
    const net = Math.max(0, subtotal - couponDiscount);
    const stdFree = freeShip || net >= config.freeShippingThreshold;
    document.getElementById("label-standard-fee").textContent = stdFree ? "FREE" : UI.formatPrice(config.standardShippingFee);

    const delivery = deliveryMethod === "express" ? config.expressShippingFee : stdFree ? 0 : config.standardShippingFee;
    const tax = Math.round(net * ((config.taxRatePercent || 0) / 100));
    const total = net + delivery + tax;

    document.getElementById("co-sum-subtotal").textContent = UI.formatPrice(subtotal);
    document.getElementById("co-sum-prod-disc").textContent = prodDiscount ? `-${UI.formatPrice(prodDiscount)}` : UI.formatPrice(0);
    document.getElementById("co-sum-coupon").textContent = couponDiscount ? `-${UI.formatPrice(couponDiscount)}` : UI.formatPrice(0);
    document.getElementById("co-sum-delivery").textContent = delivery === 0 ? "FREE" : UI.formatPrice(delivery);
    document.getElementById("co-sum-tax").textContent = UI.formatPrice(tax);
    document.getElementById("co-sum-total").textContent = UI.formatPrice(total);
  }

  function renderConfirmation(order) {
    document.getElementById("checkout-form-section").style.display = "none";
    document.getElementById("checkout-page-title").textContent = "Order Confirmed";

    const host = document.getElementById("checkout-confirmation-section");
    host.style.display = "block";
    host.innerHTML = `
      <div class="confirmation-banner">
        <div class="section-eyebrow">Thank You for Your Purchase</div>
        <h2 style="font-family:var(--font-display);font-size:36px;margin:4px 0 8px;">Order #${order.order_number} Confirmed</h2>
        <p style="color:var(--ink-secondary);margin:0 0 22px;">
          Status: <strong>${order.order_status}</strong> &bull; Placed on ${new Date(order.created_at || Date.now()).toLocaleString("en-PK")}
        </p>

        <div class="form-grid-2" style="margin-bottom:22px;">
          <div class="dash-stat-box">
            <strong>Customer &amp; Delivery Address</strong>
            <div style="font-size:13.5px;margin-top:6px;">
              ${order.customer_name} (${order.customer_phone})<br>
              ${order.shipping_street}, ${order.shipping_area}, ${order.shipping_city} ${order.shipping_postal_code || ""}
            </div>
          </div>
          <div class="dash-stat-box">
            <strong>Payment &amp; Delivery</strong>
            <div style="font-size:13.5px;margin-top:6px;">
              Method: ${order.payment_method === "cod" ? "Cash on Delivery" : "Bank Transfer"} (${order.payment_status})<br>
              Courier: ${order.delivery_method === "express" ? "Express Priority Courier" : "Standard Insured Delivery"}
            </div>
          </div>
        </div>

        <table class="specs-table" style="margin-bottom:20px;">
          <thead>
            <tr><th>Timepiece</th><th>Variant</th><th>Qty</th><th>Price</th></tr>
          </thead>
          <tbody>
            ${(order.items || [])
              .map(
                (i) => `
              <tr>
                <td>${i.product_name}</td>
                <td>${i.variant_name || "Standard"}</td>
                <td>${i.quantity}</td>
                <td>${UI.formatPrice(i.line_total)}</td>
              </tr>
            `
              )
              .join("")}
          </tbody>
        </table>

        <div style="max-width:340px;margin-left:auto;margin-bottom:24px;">
          <div class="summary-row"><span>Subtotal</span><span>${UI.formatPrice(order.subtotal)}</span></div>
          <div class="summary-row"><span>Coupon (${order.coupon_code || "None"})</span><span>-${UI.formatPrice(order.coupon_discount)}</span></div>
          <div class="summary-row"><span>Delivery</span><span>${Number(order.delivery_charge) === 0 ? "FREE" : UI.formatPrice(order.delivery_charge)}</span></div>
          <div class="summary-row summary-total"><span>Total Paid / Due</span><span>${UI.formatPrice(order.total_amount)}</span></div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <a href="track-order.html?order=${encodeURIComponent(order.order_number)}" class="btn btn-primary">Track Order</a>
          <a href="account.html" class="btn btn-outline">My Orders</a>
          <a href="catalog.html" class="btn btn-outline">Continue Shopping</a>
        </div>
      </div>
    `;
  }

  document.addEventListener("DOMContentLoaded", async () => {
    await loadConfig();
    loadStoredCoupon();

    // Check if returning to view a confirmed order (?order=VAL-...)
    const params = new URLSearchParams(window.location.search);
    const orderParam = params.get("order");
    if (orderParam) {
      try {
        const res = await fetch(`/api/orders/${encodeURIComponent(orderParam)}`);
        if (res.ok) {
          const json = await res.json();
          if (json?.success && json.order) {
            renderConfirmation(json.order);
            return;
          }
        }
      } catch {}
    }

    await prefillCustomerAndAddresses();
    renderOrderSummary();

    document.querySelectorAll('input[name="deliveryMethod"]').forEach((r) => {
      r.addEventListener("change", () => {
        document.querySelectorAll("[data-delivery-card]").forEach((c) => {
          c.classList.toggle("selected", c.dataset.deliveryCard === r.value);
        });
        renderOrderSummary();
      });
    });

    document.querySelectorAll('input[name="paymentMethod"]').forEach((r) => {
      r.addEventListener("change", () => {
        document.getElementById("pay-card-cod").classList.toggle("selected", r.value === "cod");
        document.getElementById("pay-card-bank").classList.toggle("selected", r.value === "bank_transfer");
        document.getElementById("bank-details-box").style.display = r.value === "bank_transfer" ? "block" : "none";
      });
    });

    document.getElementById("co-coupon-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      const cart = window.VALENOR_APP.getCart();
      const subtotal = cart.reduce((s, i) => {
        const p = DATA.products.find((x) => x.id === Number(i.id));
        return s + (p ? p.sale_price * i.qty : 0);
      }, 0);
      const code = document.getElementById("co-coupon-input").value.trim().toUpperCase();
      const msgEl = document.getElementById("co-coupon-msg");
      try {
        const headers = { "Content-Type": "application/json", Accept: "application/json" };
        const sess = AUTH?.getSession();
        if (sess?.token) headers.Authorization = `Bearer ${sess.token}`;
        const res = await fetch("/api/coupons/validate", {
          method: "POST",
          headers,
          body: JSON.stringify({ code, subtotal, email: document.getElementById("co-email").value.trim() || sess?.user?.email })
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || "Invalid coupon.");
        saveStoredCoupon(json.coupon);
        renderOrderSummary();
      } catch (err) {
        saveStoredCoupon(null);
        renderOrderSummary();
        msgEl.innerHTML = `<span style="color:#c42b2b;">${err.message}</span>`;
      }
    });

    document.addEventListener("click", (e) => {
      if (e.target.id === "co-remove-coupon") {
        saveStoredCoupon(null);
        document.getElementById("co-coupon-input").value = "";
        renderOrderSummary();
      }
    });

    document.getElementById("checkout-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      if (isSubmitting) return;
      clearAlert();

      const cart = window.VALENOR_APP.getCart();
      if (!cart.length) return showAlert("Your shopping bag is empty.");

      const btn = document.getElementById("btn-place-order");
      isSubmitting = true;
      btn.disabled = true;
      btn.textContent = "Placing Order...";

      try {
        const headers = { "Content-Type": "application/json", Accept: "application/json" };
        const sess = AUTH?.getSession();
        if (sess?.token) headers.Authorization = `Bearer ${sess.token}`;

        const payload = {
          idempotencyKey,
          customer: {
            fullName: document.getElementById("co-fullname").value,
            email: document.getElementById("co-email").value,
            phone: document.getElementById("co-phone").value
          },
          address: {
            city: document.getElementById("co-city").value,
            area: document.getElementById("co-area").value,
            streetAddress: document.getElementById("co-street").value,
            postalCode: document.getElementById("co-postal").value,
            deliveryInstructions: document.getElementById("co-instructions").value
          },
          deliveryMethod: document.querySelector('input[name="deliveryMethod"]:checked')?.value || "standard",
          paymentMethod: document.querySelector('input[name="paymentMethod"]:checked')?.value || "cod",
          paymentReference: document.getElementById("co-bank-ref").value,
          couponCode: appliedCoupon?.code || "",
          items: cart.map((i) => ({ productId: i.id, quantity: i.qty, variantName: i.variant || "" }))
        };

        const res = await fetch("/api/orders", {
          method: "POST",
          headers,
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || "Could not place order.");

        // Store summary locally for instant account history & clear cart
        try {
          const prev = JSON.parse(localStorage.getItem("valenor_orders_v1") || "[]");
          if (!prev.some((o) => o.order_number === json.order.order_number)) {
            prev.unshift(json.order);
            localStorage.setItem("valenor_orders_v1", JSON.stringify(prev));
          }
        } catch {}

        window.VALENOR_APP.clearCart();
        saveStoredCoupon(null);
        idempotencyKey = makeIdempotencyKey();

        window.history.replaceState({}, "", `?order=${encodeURIComponent(json.order.order_number)}`);
        renderConfirmation(json.order);
      } catch (err) {
        showAlert(err.message);
        isSubmitting = false;
        btn.disabled = false;
        btn.textContent = "Place Order";
      }
    });
  });
})();

