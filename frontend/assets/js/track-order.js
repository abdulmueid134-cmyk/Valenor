// VALENOR Horology — Order Tracking Progress, Cancellations & Return Request Controller
(() => {
  const UI = window.VALENOR_UI;
  const AUTH = window.VALENOR_AUTH;

  const FORWARD_STEPS = [
    "Pending",
    "Confirmed",
    "Processing",
    "Packed",
    "Shipped",
    "Out for Delivery",
    "Delivered"
  ];
  const CANCELLABLE = ["Pending", "Confirmed", "Processing"];

  let currentOrder = null;

  function showAlert(msg, kind = "error") {
    const el = document.getElementById("track-alert");
    el.className = `form-alert show ${kind}`;
    el.textContent = msg;
  }

  function clearAlert() {
    const el = document.getElementById("track-alert");
    el.className = "form-alert";
    el.textContent = "";
  }

  async function fetchOrder(orderNumber) {
    const headers = { Accept: "application/json" };
    const sess = AUTH?.getSession();
    if (sess?.token) headers.Authorization = `Bearer ${sess.token}`;
    const res = await fetch(`/api/orders/${encodeURIComponent(orderNumber)}`, { headers });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || "Order not found.");
    return json.order;
  }

  function renderStepper(status) {
    const idx = FORWARD_STEPS.indexOf(status);
    let banner = "";
    if (status === "Cancelled" || status === "Rejected") {
      banner = `<div class="tracker-alert-banner">This order has been <strong>${status}</strong>. Reserved stock has been released.</div>`;
    } else if (["Return Requested", "Returned", "Refunded"].includes(status)) {
      banner = `<div class="tracker-alert-banner return-info">Post-Delivery Status: <strong>${status}</strong>. See return &amp; refund details below.</div>`;
    }

    const effectiveIdx = idx !== -1 ? idx : ["Return Requested", "Returned", "Refunded"].includes(status) ? FORWARD_STEPS.length - 1 : -1;

    return `
      ${banner}
      <div class="tracker-stepper" aria-label="Order Progress">
        ${FORWARD_STEPS.map((step, i) => {
          const isDone = effectiveIdx > i || (effectiveIdx === i && step === "Delivered");
          const isActive = effectiveIdx === i && step !== "Delivered";
          const cls = isDone ? "tracker-step done" : isActive ? "tracker-step active" : "tracker-step";
          return `
            <div class="${cls}">
              <div class="tracker-dot">${isDone ? "&#10003;" : i + 1}</div>
              <div class="tracker-step-label">${step === "Pending" ? "Order Placed" : step}</div>
            </div>
          `;
        }).join("")}
      </div>
    `;
  }

  function renderOrderView(order) {
    currentOrder = order;
    const host = document.getElementById("tracker-result-host");
    const canCancel = CANCELLABLE.includes(order.order_status);
    const canReturn = ["Delivered", "Return Requested"].includes(order.order_status);

    host.innerHTML = `
      <div class="tracker-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;">
          <div>
            <div class="section-eyebrow">Live Order Status</div>
            <h2 style="font-family:var(--font-display);font-size:32px;margin:2px 0 4px;">Order #${order.order_number}</h2>
            <div style="font-size:13.5px;color:var(--ink-muted);">
              Placed on ${new Date(order.created_at).toLocaleString("en-PK")} &bull; Current Status: <strong>${order.order_status}</strong>
            </div>
          </div>
          <div style="text-align:right;">
            <strong style="font-size:20px;">${UI.formatPrice(order.total_amount)}</strong>
            <div style="font-size:12.5px;color:var(--ink-muted);">
              ${order.payment_method === "cod" ? "Cash on Delivery" : "Bank Transfer"} (${order.payment_status})
            </div>
          </div>
        </div>

        ${renderStepper(order.order_status)}

        <div class="form-grid-2" style="margin-top:18px;">
          <div class="dash-stat-box">
            <strong>Delivery Address</strong>
            <div style="font-size:13.5px;margin-top:6px;">
              ${UI.escapeHtml(order.customer_name)} (${UI.escapeHtml(order.customer_phone)})<br>
              ${UI.escapeHtml(order.shipping_street)}, ${UI.escapeHtml(order.shipping_area)}, ${UI.escapeHtml(order.shipping_city)} ${UI.escapeHtml(order.shipping_postal_code || "")}
            </div>
          </div>
          <div class="dash-stat-box">
            <strong>Shipment &amp; Tracking</strong>
            <div style="font-size:13.5px;margin-top:6px;">
              Courier: ${UI.escapeHtml(order.shipment?.courier_name || "VALENOR Insured Courier")}<br>
              Tracking #: <strong>${UI.escapeHtml(order.shipment?.tracking_number || "Assigned upon dispatch")}</strong><br>
              Expected Delivery: ${order.shipment?.expected_delivery_date ? new Date(order.shipment.expected_delivery_date).toLocaleDateString("en-PK") : "2–4 business days after dispatch"}
            </div>
          </div>
        </div>
      </div>

      <!-- Ordered Items + Cancel Action -->
      <div class="tracker-card">
        <h3 style="font-family:var(--font-display);font-size:26px;margin:0 0 12px;">Ordered Timepieces</h3>
        <table class="specs-table">
          <thead><tr><th>Watch</th><th>Variant</th><th>Qty</th><th>Line Total</th></tr></thead>
          <tbody>
            ${(order.items || [])
              .map(
                (i) => `
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    ${i.image ? `<img src="${UI.resolveAsset(i.image)}" alt="${i.product_name}" style="width:44px;height:44px;object-fit:contain;">` : ""}
                    <span>${i.product_name}</span>
                  </div>
                </td>
                <td>${i.variant_name || "Standard"}</td>
                <td>${i.quantity}</td>
                <td><strong>${UI.formatPrice(i.line_total)}</strong></td>
              </tr>
            `
              )
              .join("")}
          </tbody>
        </table>

        ${
          canCancel
            ? `
            <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-subtle);">
              <form id="cancel-order-form" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <input type="text" id="cancel-reason" class="field-control" style="flex:1 1 260px;" placeholder="Optional cancellation reason (e.g. Changed strap preference)">
                <button type="submit" class="btn btn-outline" style="border-color:var(--accent-sale);color:var(--accent-sale);">Cancel Order</button>
              </form>
            </div>
          `
            : ""
        }
      </div>

      <!-- Return Request Form (Visible once Delivered) -->
      ${
        canReturn
          ? `
          <div class="tracker-card">
            <div class="section-eyebrow">7-Day Return Guarantee</div>
            <h3 style="font-family:var(--font-display);font-size:26px;margin:2px 0 14px;">Request a Return</h3>
            <form id="return-request-form" class="form-grid" enctype="multipart/form-data">
              <div class="form-grid-2">
                <div class="field-group">
                  <label for="ret-item">Select Product</label>
                  <select id="ret-item" class="field-control" required>
                    ${(order.items || [])
                      .map((i) => `<option value="${i.id}" data-max-qty="${i.quantity}">${i.product_name} (${i.variant_name || "Standard"}) &times; ${i.quantity}</option>`)
                      .join("")}
                  </select>
                </div>
                <div class="field-group">
                  <label for="ret-qty">Return Quantity</label>
                  <input type="number" id="ret-qty" class="field-control" value="1" min="1" max="${order.items?.[0]?.quantity || 1}" required>
                </div>
              </div>
              <div class="form-grid-2">
                <div class="field-group">
                  <label for="ret-reason">Return Reason</label>
                  <select id="ret-reason" class="field-control" required>
                    <option value="">Select reason...</option>
                    <option value="Damaged or defective watch">Damaged or defective watch</option>
                    <option value="Wrong model or color received">Wrong model or color received</option>
                    <option value="Size or strap fit issue">Size or strap fit issue</option>
                    <option value="Not as described">Not as described</option>
                    <option value="Changed mind within 7 days">Changed mind within 7 days</option>
                  </select>
                </div>
                <div class="field-group">
                  <label for="ret-photos">Supporting Photos (Optional, up to 3 JPG/PNG/WebP)</label>
                  <input type="file" id="ret-photos" class="field-control" accept="image/jpeg,image/png,image/webp" multiple>
                </div>
              </div>
              <div class="field-group">
                <label for="ret-details">Additional Details</label>
                <textarea id="ret-details" class="field-control" rows="2" placeholder="Describe the issue or packaging condition..."></textarea>
              </div>
              <div>
                <button type="submit" class="btn btn-primary">Submit Return Request</button>
              </div>
            </form>
          </div>
        `
          : ""
      }

      <!-- Return & Refund History -->
      ${
        (order.returns?.length || order.refunds?.length)
          ? `
          <div class="tracker-card">
            <h3 style="font-family:var(--font-display);font-size:26px;margin:0 0 14px;">Return &amp; Refund History</h3>
            ${(order.returns || [])
              .map(
                (r) => `
              <div style="padding:14px;border:1px solid var(--border-subtle);border-radius:var(--radius-sm);margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                  <strong>${UI.escapeHtml(r.return_number)} &mdash; ${UI.escapeHtml(r.product_name || "Watch")} (&times;${r.return_qty || 1})</strong>
                  <span class="default-pill">${UI.escapeHtml(r.status)}</span>
                </div>
                <div style="font-size:13px;color:var(--ink-secondary);margin-top:4px;">Reason: ${UI.escapeHtml(r.reason)}</div>
                ${r.details ? `<div style="font-size:12.5px;color:var(--ink-muted);">Details: ${UI.escapeHtml(r.details)}</div>` : ""}
                ${r.admin_note ? `<div style="font-size:12.5px;color:var(--ink-primary);margin-top:4px;"><strong>Admin Review:</strong> ${UI.escapeHtml(r.admin_note)}</div>` : ""}
                ${
                  r.supporting_photos?.length
                    ? `<div class="return-photo-strip">${r.supporting_photos.map((u) => `<img src="${UI.escapeHtml(u)}" alt="Supporting proof" class="return-photo-thumb">`).join("")}</div>`
                    : ""
                }
              </div>
            `
              )
              .join("")}
            ${(order.refunds || [])
              .map(
                (rf) => `
              <div style="padding:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:var(--radius-sm);font-size:13.5px;">
                <strong>Refund ${UI.escapeHtml(rf.refund_number)}:</strong> ${UI.formatPrice(rf.amount)} (${UI.escapeHtml(rf.status)}) via ${UI.escapeHtml(rf.method.replace("_", " "))}
                ${rf.reference_code ? ` &bull; Ref: ${UI.escapeHtml(rf.reference_code)}` : ""}
              </div>
            `
              )
              .join("")}
          </div>
        `
          : ""
      }

      <!-- Status History Log -->
      <div class="tracker-card">
        <h3 style="font-family:var(--font-display);font-size:26px;margin:0 0 14px;">Order Status Timeline</h3>
        <ul class="timeline-list">
          ${(order.history || [])
            .map(
              (h) => `
            <li class="timeline-row">
              <strong>${UI.escapeHtml(h.new_status)}</strong>
              <span style="color:var(--ink-muted);font-size:12px;margin-left:8px;">${new Date(h.created_at).toLocaleString("en-PK")}</span>
              ${h.reason ? `<div style="color:var(--ink-secondary);font-size:13px;">${UI.escapeHtml(h.reason)}</div>` : ""}
            </li>
          `
            )
            .join("")}
        </ul>
      </div>
    `;

    // Bind Cancel Order
    document.getElementById("cancel-order-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      try {
        const headers = { "Content-Type": "application/json", Accept: "application/json" };
        const sess = AUTH?.getSession();
        if (sess?.token) headers.Authorization = `Bearer ${sess.token}`;
        const res = await fetch(`/api/orders/${encodeURIComponent(order.order_number)}/cancel`, {
          method: "POST",
          headers,
          body: JSON.stringify({
            contact: document.getElementById("lookup-contact").value.trim(),
            reason: document.getElementById("cancel-reason").value.trim() || "Cancelled by customer"
          })
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || "Unable to cancel order.");
        showAlert(`Order #${order.order_number} has been cancelled and reserved stock released.`, "success");
        renderOrderView(json.order);
      } catch (err) {
        showAlert(err.message);
      }
    });

    // Sync return max qty on item select
    const retItemSel = document.getElementById("ret-item");
    retItemSel?.addEventListener("change", () => {
      const maxQ = Number(retItemSel.selectedOptions[0]?.dataset.maxQty || 1);
      document.getElementById("ret-qty").max = maxQ;
    });

    // Bind Return Request with multipart photo upload
    document.getElementById("return-request-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      const reason = document.getElementById("ret-reason").value;
      if (!reason) return showAlert("Please select a return reason.");

      const fd = new FormData();
      fd.append("orderNumber", order.order_number);
      fd.append("orderItemId", document.getElementById("ret-item").value);
      fd.append("quantity", document.getElementById("ret-qty").value);
      fd.append("reason", reason);
      fd.append("details", document.getElementById("ret-details").value.trim());
      fd.append("contact", document.getElementById("lookup-contact").value.trim());

      const files = document.getElementById("ret-photos").files;
      for (let i = 0; i < Math.min(3, files.length); i++) {
        fd.append("photos", files[i]);
      }

      try {
        const headers = { Accept: "application/json" };
        const sess = AUTH?.getSession();
        if (sess?.token) headers.Authorization = `Bearer ${sess.token}`;
        const res = await fetch("/api/returns", { method: "POST", headers, body: fd });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || "Unable to submit return request.");
        showAlert("Return request submitted cleanly. Our team will review your request shortly.", "success");
        renderOrderView(json.order);
      } catch (err) {
        showAlert(err.message);
      }
    });
  }

  async function lookupAndRender(orderNumber) {
    clearAlert();
    try {
      const order = await fetchOrder(orderNumber);
      window.history.replaceState({}, "", `?order=${encodeURIComponent(order.order_number)}`);
      renderOrderView(order);
    } catch (err) {
      document.getElementById("tracker-result-host").innerHTML = "";
      showAlert(err.message);
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    const params = new URLSearchParams(window.location.search);
    const initialOrder = params.get("order");
    const sess = AUTH?.getSession();
    if (sess?.user?.email) {
      document.getElementById("lookup-contact").value = sess.user.email;
    }
    if (initialOrder) {
      document.getElementById("lookup-order-num").value = initialOrder;
      await lookupAndRender(initialOrder);
    }

    document.getElementById("track-lookup-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      const num = document.getElementById("lookup-order-num").value.trim();
      if (!num) return showAlert("Enter an order number.");
      await lookupAndRender(num);
    });
  });
})();

