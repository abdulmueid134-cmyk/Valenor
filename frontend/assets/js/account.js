// VALENOR Horology — Customer Dashboard & Address Book Controller
(() => {
  const AUTH = window.VALENOR_AUTH;
  const DATA = window.VALENOR_DATA;
  const UI = window.VALENOR_UI;

  function showDashAlert(msg, kind = "success") {
    const el = document.getElementById("dash-alert");
    el.className = `form-alert show ${kind}`;
    el.textContent = msg;
    el.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function clearDashAlert() {
    const el = document.getElementById("dash-alert");
    el.className = "form-alert";
    el.textContent = "";
  }

  function switchTab(tab) {
    clearDashAlert();
    document.querySelectorAll("[data-dash-tab]").forEach((b) => {
      b.classList.toggle("active", b.dataset.dashTab === tab);
    });
    document.querySelectorAll(".dashboard-panel").forEach((p) => {
      p.classList.toggle("active", p.id === `panel-${tab}`);
    });
  }

  function renderProfile(user) {
    document.getElementById("dash-welcome-title").textContent = `Welcome, ${user.full_name}`;
    document.getElementById("dash-sidebar-name").textContent = user.full_name;
    document.getElementById("dash-sidebar-email").textContent = user.email;

    document.getElementById("info-fullname").textContent = user.full_name;
    document.getElementById("info-email").textContent = user.email;
    document.getElementById("info-phone").textContent = user.phone;
    document.getElementById("info-role").textContent = (user.role || "customer").toUpperCase();
    document.getElementById("info-joined").textContent = user.created_at
      ? new Date(user.created_at).toLocaleDateString("en-PK", { year: "numeric", month: "short", day: "numeric" })
      : "2026";

    document.getElementById("edit-fullname").value = user.full_name || "";
    document.getElementById("edit-email").value = user.email || "";
    document.getElementById("edit-phone").value = user.phone || "";
    document.getElementById("setting-newsletter").checked = Boolean(user.newsletter_opt_in);
    document.getElementById("setting-currency").value = user.preferred_currency || "PKR";
  }

  async function renderAddresses() {
    const list = await AUTH.listAddresses();
    document.getElementById("stat-addresses").textContent = list.length;
    const grid = document.getElementById("address-list-grid");

    if (!list.length) {
      grid.innerHTML = `<p style="color:var(--ink-muted);grid-column:1/-1;">No saved delivery addresses yet. Add one above for faster checkout.</p>`;
      return;
    }

    grid.innerHTML = list
      .map(
        (a) => `
        <article class="address-card ${a.is_default ? "is-default" : ""}">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <strong>${UI.escapeHtml(a.full_name)}</strong>
            ${a.is_default ? `<span class="default-pill">Default</span>` : ""}
          </div>
          <div style="font-size:13px;color:var(--ink-secondary);">${UI.escapeHtml(a.phone)}</div>
          <div style="font-size:13.5px;">${UI.escapeHtml(a.street_address)}, ${UI.escapeHtml(a.area)}, ${UI.escapeHtml(a.city)} ${UI.escapeHtml(a.postal_code || "")}</div>
          ${a.delivery_instructions ? `<div style="font-size:12px;color:var(--ink-muted);">Note: ${UI.escapeHtml(a.delivery_instructions)}</div>` : ""}
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
            <button type="button" class="btn btn-outline" style="padding:5px 10px;font-size:11px;" data-edit-addr="${a.id}">Edit</button>
            ${!a.is_default ? `<button type="button" class="btn btn-primary" style="padding:5px 10px;font-size:11px;" data-default-addr="${a.id}">Set Default</button>` : ""}
            <button type="button" class="btn btn-outline" style="padding:5px 10px;font-size:11px;border-color:#c42b2b;color:#c42b2b;" data-del-addr="${a.id}">Delete</button>
          </div>
        </article>
      `
      )
      .join("");
  }

  function renderWishlist() {
    let ids = [];
    try {
      ids = JSON.parse(localStorage.getItem("valenor_wishlist_v1") || "[]");
    } catch {
      ids = [];
    }
    document.getElementById("stat-wishlist").textContent = ids.length;
    const grid = document.getElementById("dash-wishlist-grid");
    const items = DATA.products.filter((p) => ids.includes(p.id));

    if (!items.length) {
      grid.innerHTML = `<p style="color:var(--ink-muted);grid-column:1/-1;">Your wishlist is empty. <a href="catalog.html" style="text-decoration:underline;color:var(--ink-primary);">Browse watches</a> to save favorites.</p>`;
      return;
    }
    grid.innerHTML = items.map((p) => UI.renderProductCard(p, true)).join("");
  }

  async function renderOrdersAndReturns() {
    let orders = [];
    let returns = [];
    const sess = AUTH.getSession();
    if (sess?.token && /^https?:/.test(window.location.protocol)) {
      try {
        const res = await fetch("/api/orders/mine", {
          headers: { Authorization: `Bearer ${sess.token}`, Accept: "application/json" }
        });
        if (res.ok) {
          const json = await res.json();
          if (json?.success && Array.isArray(json.orders)) orders = json.orders;
        }
        const rRes = await fetch("/api/returns/mine", {
          headers: { Authorization: `Bearer ${sess.token}`, Accept: "application/json" }
        });
        if (rRes.ok) {
          const rJson = await rRes.json();
          if (rJson?.success && Array.isArray(rJson.returns)) returns = rJson.returns;
        }
      } catch {}
    }

    if (!orders.length) {
      try {
        orders = JSON.parse(localStorage.getItem("valenor_orders_v1") || "[]");
      } catch {
        orders = [];
      }
    }
    if (!returns.length) {
      try {
        returns = JSON.parse(localStorage.getItem("valenor_returns_v1") || "[]");
      } catch {
        returns = [];
      }
    }

    document.getElementById("stat-orders").textContent = orders.length;
    const ordersBody = document.getElementById("dash-orders-body");
    if (!orders.length) {
      ordersBody.innerHTML = `
        <p style="color:var(--ink-muted);margin:0 0 16px;">You have not placed any orders yet.</p>
        <a href="catalog.html" class="btn btn-primary">Explore Watches</a>
      `;
    } else {
      ordersBody.innerHTML = orders
        .map(
          (o) => `
          <div style="border:1px solid var(--border-subtle);padding:16px;border-radius:var(--radius-sm);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
              <strong>Order #${o.order_number}</strong> &bull;
              <span class="default-pill" style="background:var(--bg-surface);color:var(--ink-primary);">${o.order_status || o.status}</span>
              <div style="font-size:12.5px;color:var(--ink-muted);margin-top:4px;">
                ${new Date(o.created_at || Date.now()).toLocaleDateString("en-PK")} &bull; ${o.payment_method === "cod" ? "Cash on Delivery" : "Bank Transfer"}
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <strong>${UI.formatPrice(o.total_amount)}</strong>
              <a href="track-order.html?order=${encodeURIComponent(o.order_number)}" class="btn btn-primary" style="padding:6px 12px;font-size:11.5px;">Track / Return</a>
              <a href="checkout.html?order=${encodeURIComponent(o.order_number)}" class="btn btn-outline" style="padding:6px 12px;font-size:11.5px;">Receipt</a>
            </div>
          </div>
        `
        )
        .join("");
    }

    const returnsBody = document.getElementById("dash-returns-body");
    returnsBody.innerHTML = returns.length
      ? returns
          .map(
            (r) => `
            <div style="border:1px solid var(--border-subtle);padding:14px;border-radius:var(--radius-sm);margin-bottom:12px;">
              <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                <strong>${r.return_number} &mdash; Order #${r.order_number}</strong>
                <span class="default-pill">${r.status}</span>
              </div>
              <div style="font-size:13px;color:var(--ink-secondary);margin-top:4px;">${r.product_name || "Watch"} (&times;${r.return_qty || 1}) &bull; ${r.reason}</div>
              ${r.refund_number ? `<div style="font-size:12.5px;color:var(--accent-emerald);margin-top:4px;">Refund ${r.refund_number}: ${UI.formatPrice(r.refund_amount)} (${r.refund_status})</div>` : ""}
            </div>
          `
          )
          .join("")
      : `<p style="margin:0;">No return requests submitted yet. Once an order is delivered, open it from My Orders to request a return within 7 days.</p>`;
  }

  function openAddressForm(addr = null) {
    const form = document.getElementById("form-address");
    form.style.display = "grid";
    document.getElementById("addr-edit-id").value = addr ? addr.id : "";
    document.getElementById("addr-fullname").value = addr ? addr.full_name : AUTH.getSession().user.full_name;
    document.getElementById("addr-phone").value = addr ? addr.phone : AUTH.getSession().user.phone;
    document.getElementById("addr-city").value = addr ? addr.city : "";
    document.getElementById("addr-area").value = addr ? addr.area : "";
    document.getElementById("addr-street").value = addr ? addr.street_address : "";
    document.getElementById("addr-postal").value = addr ? addr.postal_code || "" : "";
    document.getElementById("addr-instructions").value = addr ? addr.delivery_instructions || "" : "";
    document.getElementById("addr-default").checked = addr ? Boolean(addr.is_default) : false;
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!AUTH.isAuthenticated()) {
      window.location.href = "auth.html?next=account.html";
      return;
    }

    const user = AUTH.getSession().user;
    renderProfile(user);
    await renderAddresses();
    renderWishlist();
    await renderOrdersAndReturns();

    document.querySelectorAll("[data-dash-tab]").forEach((btn) => {
      btn.addEventListener("click", () => switchTab(btn.dataset.dashTab));
    });
    document.querySelectorAll("[data-jump-tab]").forEach((btn) => {
      btn.addEventListener("click", () => switchTab(btn.dataset.jumpTab));
    });

    document.getElementById("btn-logout").addEventListener("click", () => {
      AUTH.logout();
      window.location.href = "auth.html";
    });

    document.getElementById("form-edit-profile").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearDashAlert();
      try {
        const updated = await AUTH.updateProfile({
          fullName: document.getElementById("edit-fullname").value,
          email: document.getElementById("edit-email").value,
          phone: document.getElementById("edit-phone").value
        });
        renderProfile(updated);
        showDashAlert("Profile details updated successfully.");
      } catch (err) {
        showDashAlert(err.message, "error");
      }
    });

    document.getElementById("form-change-password").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearDashAlert();
      try {
        const msg = await AUTH.changePassword({
          currentPassword: document.getElementById("cur-password").value,
          newPassword: document.getElementById("new-password").value,
          confirmPassword: document.getElementById("confirm-new-password").value
        });
        e.target.reset();
        showDashAlert(msg);
      } catch (err) {
        showDashAlert(err.message, "error");
      }
    });

    document.getElementById("btn-new-address").addEventListener("click", () => openAddressForm(null));
    document.getElementById("btn-cancel-address").addEventListener("click", () => {
      document.getElementById("form-address").style.display = "none";
    });

    document.getElementById("form-address").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearDashAlert();
      try {
        const editId = document.getElementById("addr-edit-id").value || null;
        await AUTH.saveAddress(
          {
            fullName: document.getElementById("addr-fullname").value,
            phone: document.getElementById("addr-phone").value,
            city: document.getElementById("addr-city").value,
            area: document.getElementById("addr-area").value,
            streetAddress: document.getElementById("addr-street").value,
            postalCode: document.getElementById("addr-postal").value,
            deliveryInstructions: document.getElementById("addr-instructions").value,
            isDefault: document.getElementById("addr-default").checked
          },
          editId
        );
        document.getElementById("form-address").style.display = "none";
        await renderAddresses();
        showDashAlert(editId ? "Address updated." : "Address saved to your delivery book.");
      } catch (err) {
        showDashAlert(err.message, "error");
      }
    });

    document.getElementById("address-list-grid").addEventListener("click", async (e) => {
      const editBtn = e.target.closest("[data-edit-addr]");
      if (editBtn) {
        const list = await AUTH.listAddresses();
        const found = list.find((a) => String(a.id) === editBtn.dataset.editAddr);
        if (found) openAddressForm(found);
        return;
      }

      const defBtn = e.target.closest("[data-default-addr]");
      if (defBtn) {
        await AUTH.setDefaultAddress(defBtn.dataset.defaultAddr);
        await renderAddresses();
        showDashAlert("Default delivery address updated.");
        return;
      }

      const delBtn = e.target.closest("[data-del-addr]");
      if (delBtn) {
        await AUTH.deleteAddress(delBtn.dataset.delAddr);
        await renderAddresses();
        showDashAlert("Address removed.");
      }
    });

    document.getElementById("form-track-lookup").addEventListener("submit", (e) => {
      e.preventDefault();
      const num = document.getElementById("track-order-input").value.trim();
      if (!num) return;
      window.location.href = `track-order.html?order=${encodeURIComponent(num)}`;
    });

    document.getElementById("form-account-settings").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearDashAlert();
      try {
        const updated = await AUTH.updateProfile({
          newsletterOptIn: document.getElementById("setting-newsletter").checked,
          preferredCurrency: document.getElementById("setting-currency").value
        });
        renderProfile(updated);
        showDashAlert("Account preferences saved.");
      } catch (err) {
        showDashAlert(err.message, "error");
      }
    });

    document.addEventListener("valenor:wishlist-updated", renderWishlist);
  });
})();

