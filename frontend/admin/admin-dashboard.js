(function () {
  const TOKEN_KEY = "valenor_admin_token";
  const USER_KEY = "valenor_admin_user";

  const chartInstances = {};
  let currentRange = "30d";
  let currentSection = "dashboard";
  let currentSectionFilter = "all";
  let ordersState = { q: "", status: "all", dateFrom: "", dateTo: "", sort: "newest", page: 1 };
  let customersState = { q: "", status: "all", sort: "newest", page: 1 };
  let lastSectionData = null;
  let catalogMetaCache = null;
  let activeCurrency = "PKR";

  function getToken() {
    return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY) || "";
  }

  function clearSessionAndRedirect() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
    window.location.replace("./login.html");
  }

  const token = getToken();
  if (!token) {
    window.location.replace("./login.html");
    return;
  }

  function esc(val) {
    return String(val ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function resolveImg(url) {
    if (!url) return "../assets/images/watch-mens-chronograph.jpg";
    if (/^(https?:|data:|\/)/i.test(url)) return url;
    return `../${url}`;
  }

  function fmtMoney(amount) {
    if (amount === null || amount === undefined || Number.isNaN(Number(amount))) return "N/A";
    return `${activeCurrency} ${Math.round(Number(amount)).toLocaleString("en-PK")}`;
  }

  function fmtDate(val) {
    if (!val) return "—";
    const d = new Date(val);
    return Number.isNaN(d.getTime()) ? String(val) : d.toLocaleDateString("en-PK", { day: "2-digit", month: "short", year: "numeric" });
  }

  function statusClass(status) {
    const s = String(status || "").toLowerCase();
    if (s.includes("delivered") || s === "paid" || s === "processed" || s === "healthy" || s === "approved" || s === "refunded") return "ok";
    if (s.includes("cancel") || s.includes("reject") || s.includes("out of stock") || s === "failed" || s === "low stock") return "bad";
    return "warn";
  }

  function showAlert(msg, type = "error") {
    const box = document.getElementById("admin-global-alert");
    box.textContent = msg;
    box.className = `form-alert ${type}`;
  }

  function clearAlert() {
    document.getElementById("admin-global-alert").className = "form-alert";
  }

  async function apiFetch(url, opts = {}) {
    const headers = { Authorization: `Bearer ${token}`, Accept: "application/json", ...(opts.headers || {}) };
    if (opts.body && !(opts.body instanceof FormData) && !headers["Content-Type"]) {
      headers["Content-Type"] = "application/json";
    }
    const res = await fetch(url, { ...opts, headers });
    if (res.status === 401) {
      clearSessionAndRedirect();
      throw new Error("Unauthorized");
    }
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || "Request failed.");
    return json;
  }

  async function ensureMeta(force = false) {
    if (!catalogMetaCache || force) {
      catalogMetaCache = await apiFetch("/api/admin/catalog/meta");
    }
    return catalogMetaCache;
  }

  // ---------- Modal Helpers ----------
  const modalOverlay = document.getElementById("admin-modal-overlay");
  const modalCard = document.getElementById("admin-modal-card");
  const modalTitle = document.getElementById("admin-modal-title");
  const modalBody = document.getElementById("admin-modal-body");

  function openModal(title, html, { narrow = false } = {}) {
    modalTitle.textContent = title;
    modalCard.classList.toggle("narrow", narrow);
    modalBody.innerHTML = html;
    modalOverlay.classList.add("open");
  }

  function closeModal() {
    modalOverlay.classList.remove("open");
    modalBody.innerHTML = "";
  }

  document.getElementById("btn-close-modal").addEventListener("click", closeModal);
  modalOverlay.addEventListener("click", (e) => {
    if (e.target === modalOverlay) closeModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modalOverlay.classList.contains("open")) closeModal();
  });

  // ---------- Dashboard Analytics Rendering (Phase 6) ----------
  function renderKpis(kpis) {
    const profitMeta = kpis.profitMeta || {};
    const profitDisplay = kpis.totalProfit !== null ? fmtMoney(kpis.totalProfit) : "Cost N/A";
    const profitNote =
      profitMeta.costedUnits > 0
        ? `Costed units: ${profitMeta.costedUnits}/${profitMeta.totalUnits} sold`
        : "No unit cost_price recorded in window";

    const cards = [
      { label: "Total Revenue", val: fmtMoney(kpis.totalRevenue), note: "Net realized sales in window", cls: "gold" },
      { label: "Today's Sales", val: fmtMoney(kpis.todaySales), note: "Active sales placed today", cls: "emerald" },
      { label: "Total Orders", val: kpis.totalOrders, note: "All orders in selected window", cls: "" },
      { label: "Pending Orders", val: kpis.pendingOrders, note: "Awaiting confirmation", cls: "" },
      { label: "Delivered Orders", val: kpis.deliveredOrders, note: "Completed deliveries", cls: "emerald" },
      { label: "Cancelled Orders", val: kpis.cancelledOrders, note: "Customer/admin cancelled", cls: "crimson" },
      { label: "Rejected Orders", val: kpis.rejectedOrders, note: "Declined orders", cls: "crimson" },
      { label: "Returned Orders", val: kpis.returnedOrders, note: "Return requested / refunded", cls: "crimson" },
      { label: "Total Customers", val: kpis.totalCustomers, note: "Active registered shoppers", cls: "" },
      { label: "Total Products", val: kpis.totalProducts, note: "Active catalog SKUs", cls: "" },
      { label: "Low Stock Products", val: kpis.lowStockProducts, note: "At or below threshold", cls: kpis.lowStockProducts > 0 ? "crimson" : "emerald" },
      { label: "Total Profit", val: profitDisplay, note: profitNote, cls: "gold" }
    ];

    document.getElementById("kpi-grid").innerHTML = cards
      .map(
        (c) => `
      <article class="kpi-card ${c.cls}">
        <div class="kpi-label">${esc(c.label)}</div>
        <div class="kpi-value">${esc(c.val)}</div>
        <div class="kpi-note">${esc(c.note)}</div>
      </article>`
      )
      .join("");
  }

  function renderChart(canvasId, config) {
    if (typeof window.Chart === "undefined") return;
    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    chartInstances[canvasId] = new window.Chart(ctx, {
      ...config,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } }, ...(config.options?.plugins || {}) },
        ...(config.options || {})
      }
    });
  }

  function renderAllCharts(charts) {
    const dsDaily = charts.dailySales?.length ? charts.dailySales : [{ label: "No Orders", revenue: 0 }];
    const dsWeekly = charts.weeklySales?.length ? charts.weeklySales : [{ label: "No Data", revenue: 0 }];
    const dsMonthly = charts.monthlySales?.length ? charts.monthlySales : [{ label: "No Data", revenue: 0 }];
    const dsYearly = charts.yearlySales?.length ? charts.yearlySales : [{ label: "No Data", revenue: 0 }];
    const dsOrders = charts.ordersOverview?.length ? charts.ordersOverview : [{ status: "No Orders", count: 0 }];
    const dsProds = charts.productPerformance?.length ? charts.productPerformance : [{ name: "No Sales Yet", revenue: 0 }];
    const dsCats = charts.categoryPerformance?.length ? charts.categoryPerformance : [{ name: "No Sales Yet", revenue: 0 }];

    renderChart("chart-daily-sales", {
      type: "bar",
      data: { labels: dsDaily.map((r) => r.label), datasets: [{ label: `Revenue (${activeCurrency})`, data: dsDaily.map((r) => Number(r.revenue)), backgroundColor: "#0B0B0D", borderRadius: 4 }] }
    });
    renderChart("chart-weekly-sales", {
      type: "line",
      data: { labels: dsWeekly.map((r) => r.label), datasets: [{ label: `Weekly Sales (${activeCurrency})`, data: dsWeekly.map((r) => Number(r.revenue)), borderColor: "#B8934A", backgroundColor: "rgba(184,147,74,0.14)", fill: true, tension: 0.3 }] }
    });
    renderChart("chart-monthly-sales", {
      type: "bar",
      data: { labels: dsMonthly.map((r) => r.label), datasets: [{ label: `Monthly Sales (${activeCurrency})`, data: dsMonthly.map((r) => Number(r.revenue)), backgroundColor: "#B8934A", borderRadius: 4 }] }
    });
    renderChart("chart-yearly-sales", {
      type: "bar",
      data: { labels: dsYearly.map((r) => r.label), datasets: [{ label: `Yearly Sales (${activeCurrency})`, data: dsYearly.map((r) => Number(r.revenue)), backgroundColor: "#27272A", borderRadius: 4 }] }
    });
    const palette = ["#0B0B0D", "#B8934A", "#15803D", "#C42B2B", "#52525B", "#0284C7", "#D97706", "#7C3AED"];
    renderChart("chart-orders-overview", {
      type: "doughnut",
      data: { labels: dsOrders.map((r) => r.status), datasets: [{ data: dsOrders.map((r) => Number(r.count)), backgroundColor: palette }] },
      options: { plugins: { legend: { position: "right" } } }
    });
    renderChart("chart-revenue-overview", {
      type: "bar",
      data: {
        labels: (charts.revenueOverview || []).map((r) => r.label),
        datasets: [{ label: activeCurrency, data: (charts.revenueOverview || []).map((r) => Number(r.amount)), backgroundColor: ["#27272A", "#D97706", "#0284C7", "#C42B2B", "#15803D"], borderRadius: 4 }]
      },
      options: { plugins: { legend: { display: false } } }
    });
    renderChart("chart-product-perf", {
      type: "bar",
      data: { labels: dsProds.map((r) => (r.name.length > 26 ? `${r.name.slice(0, 24)}...` : r.name)), datasets: [{ label: `Revenue (${activeCurrency})`, data: dsProds.map((r) => Number(r.revenue)), backgroundColor: "#0B0B0D", borderRadius: 4 }] },
      options: { indexAxis: "y" }
    });
    renderChart("chart-category-perf", {
      type: "bar",
      data: { labels: dsCats.map((r) => r.name), datasets: [{ label: `Category Revenue (${activeCurrency})`, data: dsCats.map((r) => Number(r.revenue)), backgroundColor: "#B8934A", borderRadius: 4 }] }
    });
  }

  function buildMiniTable(heading, headers, rows) {
    const body = rows.length
      ? rows.map((cols) => `<tr>${cols.map((c) => `<td>${c}</td>`).join("")}</tr>`).join("")
      : `<tr><td colspan="${headers.length}" class="panel-sub">No records in selected range.</td></tr>`;
    return `${heading ? `<div style="font-weight:600;font-size:13px;margin-bottom:8px;">${esc(heading)}</div>` : ""}
      <table class="admin-table"><thead><tr>${headers.map((h) => `<th>${esc(h)}</th>`).join("")}</tr></thead><tbody>${body}</tbody></table>`;
  }

  function renderBusinessAnalytics(biz) {
    const items = [
      { label: "Total Gross Sales", val: fmtMoney(biz.grossSales) },
      { label: "Net Revenue", val: fmtMoney(biz.netRevenue) },
      { label: "Discounts Given", val: fmtMoney(biz.discountsGiven) },
      { label: "Refund Amount", val: fmtMoney(biz.refundAmount) },
      { label: "Delivery Charges", val: fmtMoney(biz.deliveryCharges) },
      { label: "Order Count", val: biz.orderCount },
      { label: "Average Order Value", val: fmtMoney(biz.averageOrderValue) },
      { label: "Return Rate", val: `${biz.returnRatePercent}%` },
      { label: "Cancellation Rate", val: `${biz.cancellationRatePercent}%` },
      { label: "Profit (Costed Items)", val: biz.totalProfit !== null ? fmtMoney(biz.totalProfit) : "Cost Not Recorded" }
    ];
    document.getElementById("biz-metrics-grid").innerHTML = items.map((m) => `<div class="biz-metric-box"><div class="label">${esc(m.label)}</div><div class="val">${esc(m.val)}</div></div>`).join("");
    document.getElementById("biz-lists-grid").innerHTML = `
      <div>${buildMiniTable("Best Selling Categories", ["Category", "Units", "Revenue"], (biz.bestSellingCategories || []).map((c) => [esc(c.name), c.units_sold, fmtMoney(c.revenue)]))}</div>
      <div>${buildMiniTable("Best Selling Products", ["Watch", "Units", "Revenue"], (biz.bestSellingProducts || []).map((p) => [esc(p.name), p.units_sold, fmtMoney(p.revenue)]))}</div>`;
  }

  function renderTables(t) {
    document.getElementById("tbl-recent-orders").innerHTML = buildMiniTable("", ["Order #", "Customer", "Total", "Status"], (t.recentOrders || []).map((r) => [`<strong>${esc(r.order_number)}</strong>`, esc(r.customer_name), fmtMoney(r.total_amount), `<span class="status-badge ${statusClass(r.order_status)}">${esc(r.order_status)}</span>`]));
    document.getElementById("tbl-latest-customers").innerHTML = buildMiniTable("", ["Collector", "Email / Phone", "Orders", "Spent"], (t.latestCustomers || []).map((c) => [`<strong>${esc(c.full_name)}</strong>`, `<div style="font-size:12px;">${esc(c.email)}<br><span class="panel-sub">${esc(c.phone)}</span></div>`, c.orders_count, fmtMoney(c.total_spent)]));
    document.getElementById("tbl-best-products").innerHTML = buildMiniTable("", ["Watch", "Units", "Revenue", "Stock"], (t.bestSellingProducts || []).map((p) => [`<strong>${esc(p.name)}</strong><div class="panel-sub">${esc(p.sku)}</div>`, p.units_sold, fmtMoney(p.revenue), p.available_stock]));
    document.getElementById("tbl-low-stock").innerHTML = buildMiniTable("", ["Watch", "SKU", "Available", "Threshold"], (t.lowStockProducts || []).map((p) => [`<strong>${esc(p.name)}</strong>`, esc(p.sku), `<span class="status-badge bad">${p.available_stock}</span>`, p.low_stock_threshold]));
    document.getElementById("tbl-recent-returns").innerHTML = buildMiniTable("", ["Return #", "Order #", "Reason", "Status"], (t.recentReturns || []).map((r) => [`<strong>${esc(r.return_number)}</strong>`, esc(r.order_number), esc(r.reason), `<span class="status-badge ${statusClass(r.status)}">${esc(r.status)}</span>`]));
    document.getElementById("tbl-recent-cancelled").innerHTML = buildMiniTable("", ["Order #", "Customer", "Amount", "Status"], (t.recentCancelledOrders || []).map((r) => [`<strong>${esc(r.order_number)}</strong>`, esc(r.customer_name), fmtMoney(r.total_amount), `<span class="status-badge bad">${esc(r.order_status)}</span>`]));
  }

  async function loadDashboard(range = currentRange, startDate = "", endDate = "") {
    clearAlert();
    currentRange = range;
    try {
      const qs = new URLSearchParams({ range });
      if (range === "custom") {
        qs.set("startDate", startDate);
        qs.set("endDate", endDate);
      }
      const res = await apiFetch(`/api/admin/dashboard?${qs.toString()}`);
      const { admin, data } = res;
      activeCurrency = data.currency || "PKR";
      document.getElementById("admin-user-name").textContent = admin.full_name || "Administrator";
      document.getElementById("admin-role-badge").textContent = String(admin.role || "admin").replace(/_/g, " ");
      document.getElementById("topbar-window-label").textContent = `Window: ${data.filter.label} (${data.filter.startDate} to ${data.filter.endDate})`;
      document.getElementById("filter-start-date").value = data.filter.startDate;
      document.getElementById("filter-end-date").value = data.filter.endDate;
      renderKpis(data.kpis);
      renderAllCharts(data.charts);
      renderBusinessAnalytics(data.businessAnalytics);
      renderTables(data.tables);
    } catch (err) {
      if (err.message !== "Unauthorized") showAlert(err.message);
    }
  }

  // ---------- Phase 7: Product & Entity Modals ----------
  function renderModalAlert(msg, type = "error") {
    const el = document.getElementById("modal-alert");
    if (!el) return;
    el.textContent = msg;
    el.className = `form-alert ${type}`;
  }

  function renderProductGalleryHtml(product) {
    if (!product?.id) {
      return `<p class="panel-sub">Optional initial watch photo upload (more photos can also be uploaded & managed right after saving):</p>
        <input type="file" id="new-prod-photos" class="field-control" accept="image/jpeg,image/png,image/webp" multiple>`;
    }
    const imgs = product.images || [];
    return `
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div><strong>Product Gallery (${imgs.length})</strong><div class="panel-sub">Select main image, replace individual photos, or upload additional views (JPG/PNG/WebP &le; 5MB).</div></div>
        <label class="btn btn-primary btn-sm" style="cursor:pointer;">
          + Upload Photos
          <input type="file" id="edit-prod-upload-input" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
        </label>
      </div>
      <div class="img-mgr-grid">
        ${imgs.map((img) => `
          <div class="img-mgr-item">
            <img src="${esc(resolveImg(img.image_url))}" alt="Product photo">
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;">
              ${img.is_primary ? `<span class="status-badge ok">Primary</span>` : `<button type="button" class="btn btn-outline btn-sm btn-img-primary" data-img-id="${img.id}">Set Main</button>`}
              <label class="btn btn-outline btn-sm" style="cursor:pointer;">Replace<input type="file" class="input-img-replace" data-img-id="${img.id}" accept="image/jpeg,image/png,image/webp" style="display:none;"></label>
              <button type="button" class="btn btn-danger btn-sm btn-img-del" data-img-id="${img.id}" ${imgs.length <= 1 ? "disabled title='Keep at least 1 photo'" : ""}>&times;</button>
            </div>
          </div>`).join("")}
      </div>`;
  }

  function renderVariantRowHtml(v = {}) {
    return `
      <div class="variant-edit-row">
        <input type="text" class="field-control field-control-sm v-name" placeholder="Color Name (e.g. Obsidian)" value="${esc(v.color_name || "")}">
        <input type="color" class="field-control field-control-sm v-hex" value="${esc(v.color_hex || "#141417")}" style="height:34px;padding:2px;">
        <input type="text" class="field-control field-control-sm v-sku" placeholder="Variant SKU" value="${esc(v.variant_sku || "")}">
        <input type="number" class="field-control field-control-sm v-stock" placeholder="Qty" min="0" value="${esc(v.stock_quantity ?? 5)}">
        <button type="button" class="btn btn-outline btn-sm btn-remove-variant">&times;</button>
      </div>`;
  }

  async function openProductModal(productId = null) {
    try {
      const meta = await ensureMeta(true);
      const prod = productId ? (await apiFetch(`/api/admin/products/${productId}`)).product : null;

      const selectedCats = new Set(prod?.category_ids || []);
      const watchTypes = meta.watchTypes || [];
      const prodType = prod?.watch_type || (watchTypes[0]?.name || "Analog");
      const hasKnownType = watchTypes.some((t) => t.name === prodType);

      const html = `
        <div id="modal-alert" class="form-alert"></div>
        <form id="form-admin-product">
          <div class="form-grid-2">
            <div class="field-group"><label>Product Name *</label><input type="text" id="p-name" class="field-control" value="${esc(prod?.name || "")}" required></div>
            <div class="field-group"><label>SKU *</label><input type="text" id="p-sku" class="field-control" value="${esc(prod?.sku || "")}" required></div>
          </div>

          <div class="form-grid-3">
            <div class="field-group">
              <label>Brand *</label>
              <select id="p-brand" class="field-control" required>
                ${meta.brands.map((b) => `<option value="${b.id}" ${prod?.brand_id === b.id ? "selected" : ""}>${esc(b.name)}</option>`).join("")}
              </select>
            </div>
            <div class="field-group">
              <label>Product Type *</label>
              <select id="p-type-select" class="field-control">
                ${watchTypes.map((t) => `<option value="${esc(t.name)}" ${prodType === t.name ? "selected" : ""}>${esc(t.name)}</option>`).join("")}
                ${!hasKnownType && prodType ? `<option value="${esc(prodType)}" selected>${esc(prodType)}</option>` : ""}
                <option value="__custom__">+ Type Custom Product Type...</option>
              </select>
              <input type="text" id="p-type-custom" class="field-control field-control-sm" placeholder="Enter new watch type..." style="display:none;margin-top:4px;">
            </div>
            <div class="field-group">
              <label>Department / Gender *</label>
              <select id="p-gender" class="field-control" required>
                ${meta.genders.map((g) => `<option value="${esc(g)}" ${prod?.gender === g ? "selected" : ""}>${esc(g)}</option>`).join("")}
              </select>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="field-group">
              <label>Primary Category *</label>
              <select id="p-primary-cat" class="field-control" required>
                ${meta.categories.map((c) => `<option value="${c.id}" ${prod?.primary_category_id === c.id ? "selected" : ""}>${esc(c.name)}</option>`).join("")}
              </select>
            </div>
            <div class="field-group">
              <label>Primary Dial / Case Color</label>
              <input type="text" id="p-color" class="field-control" value="${esc(prod?.color || "Obsidian Black")}">
            </div>
          </div>

          <div class="field-group">
            <label>Additional Categories</label>
            <div class="checkbox-cluster">
              ${meta.categories.map((c) => `<label><input type="checkbox" class="p-extra-cat" value="${c.id}" ${selectedCats.has(c.id) ? "checked" : ""}> ${esc(c.name)}</label>`).join("")}
            </div>
          </div>

          <div class="form-grid-3">
            <div class="field-group"><label>Regular Price (${esc(activeCurrency)}) *</label><input type="number" step="1" min="1" id="p-orig-price" class="field-control" value="${esc(prod?.original_price ?? 45000)}" required></div>
            <div class="field-group"><label>Discount %</label><input type="number" step="1" min="0" max="95" id="p-discount" class="field-control" value="${esc(prod?.discount_percent ?? 0)}"></div>
            <div class="field-group"><label>Sale Price (${esc(activeCurrency)}) *</label><input type="number" step="1" min="1" id="p-sale-price" class="field-control" value="${esc(prod?.sale_price ?? 45000)}" required></div>
          </div>

          <div class="form-grid-3">
            <div class="field-group"><label>Cost Price (Optional for Profit)</label><input type="number" step="1" min="0" id="p-cost-price" class="field-control" value="${esc(prod?.cost_price ?? "")}" placeholder="Unrecorded"></div>
            <div class="field-group"><label>Available Stock *</label><input type="number" step="1" min="0" id="p-stock" class="field-control" value="${esc(prod?.available_stock ?? 10)}" required></div>
            <div class="field-group"><label>Low-Stock Alert Threshold</label><input type="number" step="1" min="0" id="p-threshold" class="field-control" value="${esc(prod?.low_stock_threshold ?? 5)}"></div>
          </div>

          <div class="form-grid-2">
            <div class="field-group"><label>Movement</label><input type="text" id="p-movement" class="field-control" value="${esc(prod?.movement || "Swiss Quartz")}"></div>
            <div class="field-group"><label>Strap Material</label><input type="text" id="p-strap" class="field-control" value="${esc(prod?.strap_material || "Stainless Steel")}"></div>
            <div class="field-group"><label>Case Material</label><input type="text" id="p-case" class="field-control" value="${esc(prod?.case_material || "316L Stainless Steel")}"></div>
            <div class="field-group"><label>Water Resistance</label><input type="text" id="p-water" class="field-control" value="${esc(prod?.water_resistance || "5 ATM (50m)")}"></div>
          </div>

          <div class="field-group"><label>Warranty Information</label><input type="text" id="p-warranty" class="field-control" value="${esc(prod?.warranty || "2 Years VALENOR International Warranty")}"></div>
          <div class="field-group"><label>Product Description</label><textarea id="p-desc" class="field-control" rows="2">${esc(prod?.description || "")}</textarea></div>

          <div class="field-group">
            <label>Status &amp; Merchandising Badges</label>
            <div class="checkbox-cluster">
              <label><input type="checkbox" id="p-active" ${!prod || prod.is_active ? "checked" : ""}> Published / Active</label>
              <label><input type="checkbox" id="p-featured" ${prod?.is_featured ? "checked" : ""}> Featured</label>
              <label><input type="checkbox" id="p-bestseller" ${prod?.is_best_seller ? "checked" : ""}> Best Seller</label>
              <label><input type="checkbox" id="p-new" ${prod?.is_new_arrival ? "checked" : ""}> New Arrival</label>
            </div>
          </div>

          <div class="modal-divider">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
              <strong>Color Variants</strong>
              <button type="button" id="btn-add-variant" class="btn btn-outline btn-sm">+ Add Variant</button>
            </div>
            <div id="variant-rows-host">${(prod?.variants?.length ? prod.variants : [{ color_name: prod?.color || "Obsidian Black", color_hex: "#141417", stock_quantity: prod?.available_stock ?? 10 }]).map(renderVariantRowHtml).join("")}</div>
          </div>

          <div class="modal-divider" id="product-gallery-host">${renderProductGalleryHtml(prod)}</div>

          <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
            <button type="button" class="btn btn-outline" id="btn-modal-cancel">Cancel</button>
            <button type="submit" id="btn-save-product" class="btn btn-primary">${prod ? "Save Product Changes" : "Create Product"}</button>
          </div>
        </form>`;

      openModal(prod ? `Edit Watch — ${prod.name}` : "Add New Watch", html);

      document.getElementById("btn-modal-cancel").addEventListener("click", closeModal);
      const typeSel = document.getElementById("p-type-select");
      const typeCustom = document.getElementById("p-type-custom");
      typeSel.addEventListener("change", () => {
        typeCustom.style.display = typeSel.value === "__custom__" ? "block" : "none";
      });

      // Bidirectional price & discount % sync
      const origIn = document.getElementById("p-orig-price");
      const discIn = document.getElementById("p-discount");
      const saleIn = document.getElementById("p-sale-price");
      discIn.addEventListener("input", () => {
        const orig = Number(origIn.value || 0);
        const pct = Math.min(95, Math.max(0, Number(discIn.value || 0)));
        if (orig > 0) saleIn.value = Math.max(1, Math.round(orig * (1 - pct / 100)));
      });
      const recalcDiscount = () => {
        const orig = Number(origIn.value || 0);
        const sale = Number(saleIn.value || 0);
        if (orig > 0 && sale <= orig) discIn.value = Math.round(((orig - sale) / orig) * 100);
      };
      origIn.addEventListener("input", recalcDiscount);
      saleIn.addEventListener("input", recalcDiscount);

      // Variant rows
      const vHost = document.getElementById("variant-rows-host");
      document.getElementById("btn-add-variant").addEventListener("click", () => {
        vHost.insertAdjacentHTML("beforeend", renderVariantRowHtml());
      });
      vHost.addEventListener("click", (e) => {
        if (e.target.classList.contains("btn-remove-variant")) e.target.closest(".variant-edit-row")?.remove();
      });

      // Bind live image manager actions when editing existing product
      function bindGalleryEvents(currentProdId) {
        const gHost = document.getElementById("product-gallery-host");
        if (!gHost) return;
        document.getElementById("edit-prod-upload-input")?.addEventListener("change", async (e) => {
          if (!e.target.files?.length) return;
          const fd = new FormData();
          Array.from(e.target.files).forEach((f) => fd.append("images", f));
          try {
            const res = await apiFetch(`/api/admin/products/${currentProdId}/images`, { method: "POST", body: fd });
            gHost.innerHTML = renderProductGalleryHtml(res.product);
            bindGalleryEvents(currentProdId);
            renderModalAlert("Images uploaded to product gallery.", "success");
            loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
          } catch (err) {
            renderModalAlert(err.message);
          }
        });
        gHost.querySelectorAll(".btn-img-primary").forEach((btn) => {
          btn.addEventListener("click", async () => {
            try {
              const res = await apiFetch(`/api/admin/products/${currentProdId}/images/${btn.dataset.imgId}/primary`, { method: "PATCH" });
              gHost.innerHTML = renderProductGalleryHtml(res.product);
              bindGalleryEvents(currentProdId);
              loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
            } catch (err) {
              renderModalAlert(err.message);
            }
          });
        });
        gHost.querySelectorAll(".input-img-replace").forEach((inp) => {
          inp.addEventListener("change", async (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            const fd = new FormData();
            fd.append("image", file);
            try {
              const res = await apiFetch(`/api/admin/products/${currentProdId}/images/${inp.dataset.imgId}`, { method: "PUT", body: fd });
              gHost.innerHTML = renderProductGalleryHtml(res.product);
              bindGalleryEvents(currentProdId);
              loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
            } catch (err) {
              renderModalAlert(err.message);
            }
          });
        });
        gHost.querySelectorAll(".btn-img-del").forEach((btn) => {
          btn.addEventListener("click", async () => {
            try {
              const res = await apiFetch(`/api/admin/products/${currentProdId}/images/${btn.dataset.imgId}`, { method: "DELETE" });
              gHost.innerHTML = renderProductGalleryHtml(res.product);
              bindGalleryEvents(currentProdId);
              loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
            } catch (err) {
              renderModalAlert(err.message);
            }
          });
        });
      }
      if (prod?.id) bindGalleryEvents(prod.id);

      // Save Product
      document.getElementById("form-admin-product").addEventListener("submit", async (e) => {
        e.preventDefault();
        const resolvedType = typeSel.value === "__custom__" ? typeCustom.value.trim() : typeSel.value;
        if (!resolvedType) return renderModalAlert("Please select or enter a Product Type.");

        const variants = Array.from(vHost.querySelectorAll(".variant-edit-row")).map((row) => ({
          color_name: row.querySelector(".v-name").value.trim(),
          color_hex: row.querySelector(".v-hex").value,
          variant_sku: row.querySelector(".v-sku").value.trim(),
          stock_quantity: Number(row.querySelector(".v-stock").value || 0)
        })).filter((v) => v.color_name);

        const payload = {
          name: document.getElementById("p-name").value.trim(),
          sku: document.getElementById("p-sku").value.trim(),
          brand_id: Number(document.getElementById("p-brand").value),
          watch_type: resolvedType,
          gender: document.getElementById("p-gender").value,
          primary_category_id: Number(document.getElementById("p-primary-cat").value),
          category_ids: Array.from(document.querySelectorAll(".p-extra-cat:checked")).map((c) => Number(c.value)),
          color: document.getElementById("p-color").value.trim(),
          original_price: Number(origIn.value),
          sale_price: Number(saleIn.value),
          cost_price: document.getElementById("p-cost-price").value === "" ? null : Number(document.getElementById("p-cost-price").value),
          available_stock: Number(document.getElementById("p-stock").value),
          low_stock_threshold: Number(document.getElementById("p-threshold").value),
          movement: document.getElementById("p-movement").value.trim(),
          strap_material: document.getElementById("p-strap").value.trim(),
          case_material: document.getElementById("p-case").value.trim(),
          water_resistance: document.getElementById("p-water").value.trim(),
          warranty: document.getElementById("p-warranty").value.trim(),
          description: document.getElementById("p-desc").value.trim(),
          is_active: document.getElementById("p-active").checked,
          is_featured: document.getElementById("p-featured").checked,
          is_best_seller: document.getElementById("p-bestseller").checked,
          is_new_arrival: document.getElementById("p-new").checked,
          variants
        };

        try {
          const url = prod ? `/api/admin/products/${prod.id}` : "/api/admin/products";
          const saved = await apiFetch(url, { method: prod ? "PUT" : "POST", body: JSON.stringify(payload) });

          // If new product had files chosen, upload them right away
          const initFiles = document.getElementById("new-prod-photos")?.files;
          if (!prod && initFiles?.length) {
            const fd = new FormData();
            Array.from(initFiles).forEach((f) => fd.append("images", f));
            await apiFetch(`/api/admin/products/${saved.product.id}/images`, { method: "POST", body: fd });
          }

          await ensureMeta(true);
          closeModal();
          showAlert(`Saved watch "${saved.product.name}" successfully.`, "success");
          await loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
        } catch (err) {
          renderModalAlert(err.message);
        }
      });
    } catch (err) {
      showAlert(err.message);
    }
  }

  // Reusable modal for Watch Type / Category / Brand / Inventory / Coupon editing
  function openSimpleEntityModal(kind, existing = null) {
    let title = "";
    let fieldsHtml = "";
    if (kind === "watch-types" || kind === "categories") {
      const isCat = kind === "categories";
      title = `${existing ? "Edit" : "Add"} ${isCat ? "Category" : "Product Type"}`;
      fieldsHtml = `
        <div class="field-group"><label>Name *</label><input type="text" id="e-name" class="field-control" value="${esc(existing?.name || "")}" required></div>
        <div class="field-group"><label>Slug (Optional)</label><input type="text" id="e-slug" class="field-control" value="${esc(existing?.slug || "")}" placeholder="auto-generated"></div>
        <div class="field-group"><label>Description</label><textarea id="e-desc" class="field-control" rows="2">${esc(existing?.description || "")}</textarea></div>
        <div class="form-grid-2">
          <div class="field-group"><label>Sort Order</label><input type="number" id="e-sort" class="field-control" value="${esc(existing?.sort_order ?? 10)}"></div>
          <div class="field-group" style="justify-content:flex-end;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="e-active" ${!existing || existing.is_active ? "checked" : ""}> Active</label></div>
        </div>`;
    } else if (kind === "brands") {
      title = `${existing ? "Edit" : "Add"} Brand`;
      fieldsHtml = `
        <div class="field-group"><label>Brand Name *</label><input type="text" id="e-name" class="field-control" value="${esc(existing?.name || "")}" required></div>
        <div class="field-group"><label>Slug (Optional)</label><input type="text" id="e-slug" class="field-control" value="${esc(existing?.slug || "")}" placeholder="auto-generated"></div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px;"><input type="checkbox" id="e-active" ${!existing || existing.is_active ? "checked" : ""}> Active Brand</label>`;
    } else if (kind === "inventory") {
      title = `Adjust Inventory — ${existing.sku}`;
      fieldsHtml = `
        <p class="panel-sub" style="margin-top:0;"><strong>${esc(existing.name)}</strong> &bull; Reserved: ${existing.reserved_stock} &bull; Sold: ${existing.sold_quantity} &bull; Returned: ${existing.returned_quantity}</p>
        <div class="form-grid-2">
          <div class="field-group"><label>Available Stock *</label><input type="number" min="0" id="inv-stock" class="field-control" value="${esc(existing.available_stock)}" required></div>
          <div class="field-group"><label>Low-Stock Threshold *</label><input type="number" min="0" id="inv-thresh" class="field-control" value="${esc(existing.low_stock_threshold)}" required></div>
        </div>
        <div class="field-group"><label>Adjustment Reason / Note</label><input type="text" id="inv-note" class="field-control" value="Stock recount by admin" required></div>`;
    } else if (kind === "coupons") {
      title = `${existing ? "Edit" : "Create"} Coupon`;
      fieldsHtml = `
        <div class="form-grid-2">
          <div class="field-group"><label>Coupon Code *</label><input type="text" id="c-code" class="field-control" value="${esc(existing?.code || "")}" required></div>
          <div class="field-group"><label>Discount Type *</label>
            <select id="c-type" class="field-control">
              <option value="percent" ${existing?.discount_type === "percent" ? "selected" : ""}>Percent (%)</option>
              <option value="fixed" ${existing?.discount_type === "fixed" ? "selected" : ""}>Fixed Amount</option>
              <option value="free_shipping" ${existing?.discount_type === "free_shipping" ? "selected" : ""}>Free Shipping</option>
            </select>
          </div>
        </div>
        <div class="field-group"><label>Description *</label><input type="text" id="c-desc" class="field-control" value="${esc(existing?.description || "")}" required></div>
        <div class="form-grid-3">
          <div class="field-group"><label>Discount Value</label><input type="number" step="0.01" min="0" id="c-val" class="field-control" value="${esc(existing?.discount_value ?? 10)}"></div>
          <div class="field-group"><label>Min Order (${esc(activeCurrency)})</label><input type="number" min="0" id="c-min" class="field-control" value="${esc(existing?.min_purchase_amount ?? 0)}"></div>
          <div class="field-group"><label>Usage Limit</label><input type="number" min="1" id="c-limit" class="field-control" value="${esc(existing?.usage_limit ?? "")}" placeholder="Unlimited"></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px;"><input type="checkbox" id="c-active" ${!existing || existing.is_active ? "checked" : ""}> Active Coupon</label>`;
    }

    openModal(title, `
      <div id="modal-alert" class="form-alert"></div>
      <form id="form-entity-editor">
        ${fieldsHtml}
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
          <button type="button" class="btn btn-outline" id="btn-entity-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>`, { narrow: true });

    document.getElementById("btn-entity-cancel").addEventListener("click", closeModal);
    document.getElementById("form-entity-editor").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        if (kind === "watch-types" || kind === "categories") {
          const payload = {
            name: document.getElementById("e-name").value.trim(),
            slug: document.getElementById("e-slug").value.trim(),
            description: document.getElementById("e-desc").value.trim(),
            sort_order: Number(document.getElementById("e-sort").value || 0),
            is_active: document.getElementById("e-active").checked
          };
          const url = existing ? `/api/admin/${kind}/${existing.id}` : `/api/admin/${kind}`;
          await apiFetch(url, { method: existing ? "PUT" : "POST", body: JSON.stringify(payload) });
          await ensureMeta(true);
        } else if (kind === "brands") {
          const payload = {
            name: document.getElementById("e-name").value.trim(),
            slug: document.getElementById("e-slug").value.trim(),
            is_active: document.getElementById("e-active").checked
          };
          const url = existing ? `/api/admin/brands/${existing.id}` : `/api/admin/brands`;
          await apiFetch(url, { method: existing ? "PUT" : "POST", body: JSON.stringify(payload) });
          await ensureMeta(true);
        } else if (kind === "inventory") {
          await apiFetch(`/api/admin/inventory/${existing.product_id}`, {
            method: "PATCH",
            body: JSON.stringify({
              availableStock: Number(document.getElementById("inv-stock").value),
              lowStockThreshold: Number(document.getElementById("inv-thresh").value),
              note: document.getElementById("inv-note").value.trim()
            })
          });
        } else if (kind === "coupons") {
          const payload = {
            code: document.getElementById("c-code").value.trim(),
            description: document.getElementById("c-desc").value.trim(),
            discount_type: document.getElementById("c-type").value,
            discount_value: Number(document.getElementById("c-val").value || 0),
            min_purchase_amount: Number(document.getElementById("c-min").value || 0),
            usage_limit: document.getElementById("c-limit").value ? Number(document.getElementById("c-limit").value) : null,
            is_active: document.getElementById("c-active").checked
          };
          const url = existing ? `/api/admin/coupons/${existing.id}` : `/api/admin/coupons`;
          await apiFetch(url, { method: existing ? "PUT" : "POST", body: JSON.stringify(payload) });
        }
        closeModal();
        showAlert(`${title} saved successfully.`, "success");
        await loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
      } catch (err) {
        renderModalAlert(err.message);
      }
    });
  }

  // ---------- Interactive Section Tables (Products, Types, Categories, Brands, Inventory, Coupons, Reviews) ----------
  function renderEditableProductsTable(rows) {
    const filtered = rows.filter((r) => {
      if (currentSectionFilter === "active") return Boolean(r.is_active);
      if (currentSectionFilter === "featured") return Boolean(r.is_featured);
      if (currentSectionFilter === "low") return Number(r.stock) <= 5;
      return true;
    });

    if (!filtered.length) return `<p class="panel-sub" style="padding:18px 0;">No watches match this filter.</p>`;

    return `
      <table class="admin-table">
        <thead>
          <tr><th>Photo</th><th>SKU &amp; Watch</th><th>Type &amp; Dept</th><th>Brand / Category</th><th>Pricing</th><th>Stock</th><th>Flags (Click to Toggle)</th><th>Actions</th></tr>
        </thead>
        <tbody>
          ${filtered.map((r) => `
            <tr>
              <td><img src="${esc(resolveImg(r.image))}" alt="${esc(r.name)}" class="admin-thumb"></td>
              <td><strong>${esc(r.name)}</strong><div class="panel-sub">${esc(r.sku)}</div></td>
              <td><strong>${esc(r.watch_type)}</strong><div class="panel-sub">${esc(r.gender)}</div></td>
              <td>${esc(r.brand)}<div class="panel-sub">${esc(r.category)}</div></td>
              <td>
                <strong>${fmtMoney(r.sale_price)}</strong>
                ${r.discount_percent > 0 ? `<span class="status-badge bad">-${r.discount_percent}%</span>` : ""}
                <div class="panel-sub">Reg: ${fmtMoney(r.original_price)} &bull; Cost: ${r.cost_price === null ? "N/A" : fmtMoney(r.cost_price)}</div>
              </td>
              <td><span class="status-badge ${r.stock <= 5 ? "bad" : "ok"}">${r.stock}</span></td>
              <td>
                <div class="action-row-btns">
                  <button type="button" class="toggle-chip ${r.is_active ? "on-green" : ""} btn-flag-toggle" data-id="${r.id}" data-flag="is_active" data-val="${r.is_active ? 1 : 0}">${r.is_active ? "Active" : "Hidden"}</button>
                  <button type="button" class="toggle-chip ${r.is_featured ? "on" : ""} btn-flag-toggle" data-id="${r.id}" data-flag="is_featured" data-val="${r.is_featured ? 1 : 0}">Featured</button>
                  <button type="button" class="toggle-chip ${r.is_best_seller ? "on" : ""} btn-flag-toggle" data-id="${r.id}" data-flag="is_best_seller" data-val="${r.is_best_seller ? 1 : 0}">Best Seller</button>
                  <button type="button" class="toggle-chip ${r.is_new_arrival ? "on" : ""} btn-flag-toggle" data-id="${r.id}" data-flag="is_new_arrival" data-val="${r.is_new_arrival ? 1 : 0}">New</button>
                </div>
              </td>
              <td>
                <div class="action-row-btns">
                  <button type="button" class="btn btn-primary btn-sm btn-edit-product" data-id="${r.id}">Edit</button>
                  <button type="button" class="btn btn-outline btn-sm btn-del-product" data-id="${r.id}" data-name="${esc(r.name)}">Delete</button>
                </div>
              </td>
            </tr>`).join("")}
        </tbody>
      </table>`;
  }

  function fmtDateTime(val) {
    if (!val) return "—";
    const d = new Date(val);
    return Number.isNaN(d.getTime()) ? String(val) : d.toLocaleString("en-PK", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
  }

  // ---------- Phase 8: Order Detail & Customer Management Modals ----------
  async function openOrderDetailModal(orderNumber) {
    try {
      const { order } = await apiFetch(`/api/admin/orders/${encodeURIComponent(orderNumber)}`);
      const GROSS = Number(order.subtotal || 0) + Number(order.product_discount || 0);
      const validStatuses = ["Pending", "Confirmed", "Processing", "Packed", "Shipped", "Out for Delivery", "Delivered", "Cancelled", "Rejected"];

      const itemsHtml = (order.items || []).map((it) => `
        <tr>
          <td><strong>${esc(it.product_name)}</strong><div class="panel-sub">${esc(it.sku)} &bull; ${esc(it.variant_name || "Standard")}</div></td>
          <td>${it.quantity}</td>
          <td>${fmtMoney(it.unit_price)}</td>
          <td><strong>${fmtMoney(it.line_total)}</strong></td>
        </tr>`).join("");

      const returnsHtml = (order.returns || []).map((r) => `
        <div style="border:1px solid var(--border-subtle);border-radius:var(--radius-sm);padding:12px;margin-bottom:10px;background:var(--bg-canvas);">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
            <strong>${esc(r.return_number)} &mdash; ${esc(r.product_name || "Watch")} (&times;${r.return_qty || 1})</strong>
            <span class="status-badge ${statusClass(r.status)}">${esc(r.status)}</span>
          </div>
          <div class="panel-sub" style="margin-top:4px;">Reason: ${esc(r.reason)}${r.details ? ` &bull; ${esc(r.details)}` : ""}</div>
          ${r.supporting_photos?.length ? `<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">${r.supporting_photos.map((u) => `<a href="${esc(u)}" target="_blank" rel="noopener"><img src="${esc(u)}" alt="Return proof" class="admin-thumb"></a>`).join("")}</div>` : ""}
          ${["Pending", "Approved"].includes(r.status) ? `
            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
              <input type="text" class="field-control field-control-sm ret-note-inp" data-ret-id="${r.id}" placeholder="Admin review note..." value="${esc(r.admin_note || "")}" style="flex:1;min-width:180px;">
              ${r.status === "Pending" ? `<button type="button" class="btn btn-primary btn-sm btn-ret-decide" data-ret-id="${r.id}" data-decision="approve">Approve Return</button>` : ""}
              ${r.status === "Approved" ? `<button type="button" class="btn btn-primary btn-sm btn-ret-decide" data-ret-id="${r.id}" data-decision="mark_returned">Mark Returned &amp; Restock</button>` : ""}
              <button type="button" class="btn btn-danger btn-sm btn-ret-decide" data-ret-id="${r.id}" data-decision="reject">Reject Return</button>
            </div>` : ""}
          ${["Approved", "Returned"].includes(r.status) ? `
            <div style="margin-top:10px;padding-top:8px;border-top:1px dashed var(--border-subtle);display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
              <select class="field-control field-control-sm ref-method-sel" data-ret-id="${r.id}">
                <option value="bank_transfer">Bank Transfer</option>
                <option value="store_credit">Store Credit</option>
                <option value="original_payment">Original Payment</option>
              </select>
              <input type="text" class="field-control field-control-sm ref-code-inp" data-ret-id="${r.id}" placeholder="Refund Ref # (e.g. IBFT-123)" style="min-width:150px;">
              <button type="button" class="btn btn-gold btn-sm btn-ref-process" data-ret-id="${r.id}" data-decision="approve">Approve Refund</button>
              <button type="button" class="btn btn-outline btn-sm btn-ref-process" data-ret-id="${r.id}" data-decision="reject">Reject Refund</button>
            </div>` : ""}
        </div>`).join("");

      const refundsHtml = (order.refunds || []).map((rf) => `
        <div class="panel-sub" style="padding:6px 10px;background:#ECFDF5;border:1px solid #A7F3D0;border-radius:4px;margin-bottom:6px;color:#065F46;">
          <strong>${esc(rf.refund_number)}</strong> &bull; ${fmtMoney(rf.amount)} &bull; <span class="status-badge ${statusClass(rf.status)}">${esc(rf.status)}</span> (${esc(rf.method)}${rf.reference_code ? `, Ref: ${esc(rf.reference_code)}` : ""})
        </div>`).join("");

      const historyHtml = (order.history || []).map((h) => `
        <tr>
          <td>${fmtDateTime(h.created_at)}</td>
          <td>${esc(h.previous_status || "—")}</td>
          <td><span class="status-badge ${statusClass(h.new_status)}">${esc(h.new_status)}</span></td>
          <td>${h.changed_by_name ? `<strong>${esc(h.changed_by_name)}</strong> <span class="panel-sub">(${esc(h.changed_by_role || "staff")})</span>` : "Customer / System"}</td>
          <td>${esc(h.reason || "—")}</td>
        </tr>`).join("");

      const html = `
        <div id="modal-alert" class="form-alert"></div>
        <div class="form-grid-2" style="margin-bottom:14px;">
          <div style="background:var(--bg-canvas);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
            <div class="panel-sub" style="text-transform:uppercase;font-weight:600;">Customer &amp; Delivery Address</div>
            <div style="margin-top:6px;font-size:13px;">
              <strong>${esc(order.customer_name)}</strong><br>
              ${esc(order.customer_email)} &bull; ${esc(order.customer_phone)}<br>
              ${esc(order.shipping_street)}, ${esc(order.shipping_area)}, ${esc(order.shipping_city)} ${esc(order.shipping_postal_code || "")}
              ${order.delivery_instructions ? `<div class="panel-sub">Note: ${esc(order.delivery_instructions)}</div>` : ""}
              ${order.user_id ? `<div style="margin-top:6px;"><button type="button" class="btn btn-outline btn-sm" id="btn-jump-customer" data-uid="${order.user_id}">View Collector Profile &rarr;</button></div>` : ""}
            </div>
          </div>

          <div style="background:var(--bg-canvas);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
            <div class="panel-sub" style="text-transform:uppercase;font-weight:600;">Order Summary &amp; Price Breakdown</div>
            <div style="margin-top:6px;font-size:13px;line-height:1.55;">
              <div>Order Date: <strong>${fmtDateTime(order.created_at)}</strong></div>
              <div>Gross Retail: ${fmtMoney(GROSS)} &bull; Product Savings: -${fmtMoney(order.product_discount)}</div>
              <div>Coupon${order.coupon_code ? ` (${esc(order.coupon_code)})` : ""}: -${fmtMoney(order.coupon_discount)} &bull; Delivery: ${fmtMoney(order.delivery_charge)}</div>
              <div><strong>Final Total: ${fmtMoney(order.total_amount)}</strong> &bull; Method: ${esc(order.payment_method)}</div>
            </div>
          </div>
        </div>

        <div class="table-scroll" style="margin-bottom:14px;">
          <table class="admin-table"><thead><tr><th>Ordered Timepiece</th><th>Qty</th><th>Unit</th><th>Line Total</th></tr></thead><tbody>${itemsHtml}</tbody></table>
        </div>

        <div class="modal-divider">
          <div class="panel-sub" style="text-transform:uppercase;font-weight:600;margin-bottom:8px;">Update Order Status &amp; Dispatch Tracking</div>
          <form id="form-ord-status-update">
            <div class="form-grid-3">
              <div class="field-group"><label>New Order Status</label>
                <select id="ord-next-status" class="field-control field-control-sm">
                  ${validStatuses.map((s) => `<option value="${esc(s)}" ${order.order_status === s ? "selected" : ""}>${esc(s)}</option>`).join("")}
                </select>
              </div>
              <div class="field-group"><label>Courier Name</label><input type="text" id="ord-courier" class="field-control field-control-sm" value="${esc(order.shipment?.courier_name || "VALENOR Insured Courier")}"></div>
              <div class="field-group"><label>Tracking Number</label><input type="text" id="ord-tracking" class="field-control field-control-sm" value="${esc(order.shipment?.tracking_number || "")}" placeholder="e.g. TCS-PK-99201"></div>
            </div>
            <div class="form-grid-2">
              <div class="field-group"><label>Expected Delivery Date</label><input type="date" id="ord-exp-date" class="field-control field-control-sm" value="${order.shipment?.expected_delivery_date ? String(order.shipment.expected_delivery_date).slice(0, 10) : ""}"></div>
              <div class="field-group"><label>Status Reason (Required for Reject / Cancel)</label><input type="text" id="ord-status-reason" class="field-control field-control-sm" placeholder="Audit note recorded in order history"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" id="btn-apply-ord-status">Apply Status Change</button>
          </form>
        </div>

        <div class="modal-divider">
          <div class="panel-sub" style="text-transform:uppercase;font-weight:600;margin-bottom:8px;">Admin Notes &amp; Payment Status</div>
          <form id="form-ord-meta-save" class="form-grid-2">
            <div class="field-group"><label>Payment Status</label>
              <select id="ord-pay-status" class="field-control field-control-sm">
                ${["Pending", "Paid", "Failed", "Refunded"].map((ps) => `<option value="${ps}" ${order.payment_status === ps ? "selected" : ""}>${ps}</option>`).join("")}
              </select>
            </div>
            <div class="field-group"><label>Internal Admin Notes</label><input type="text" id="ord-admin-notes" class="field-control field-control-sm" value="${esc(order.notes || "")}" placeholder="Special instructions or verification memo"></div>
            <div><button type="submit" class="btn btn-outline btn-sm" id="btn-save-ord-meta">Save Notes &amp; Payment</button></div>
          </form>
        </div>

        ${(order.returns?.length || order.refunds?.length) ? `<div class="modal-divider"><strong>Returns &amp; Refunds</strong><div style="margin-top:8px;">${returnsHtml}${refundsHtml}</div></div>` : ""}

        <div class="modal-divider">
          <strong>Order Status History</strong>
          <div class="table-scroll" style="margin-top:8px;">
            <table class="admin-table"><thead><tr><th>Date &amp; Time</th><th>Previous</th><th>New</th><th>Changed By</th><th>Reason</th></tr></thead><tbody>${historyHtml}</tbody></table>
          </div>
        </div>`;

      openModal(`Order #${order.order_number} — ${order.order_status}`, html);

      document.getElementById("btn-jump-customer")?.addEventListener("click", (e) => {
        openCustomerDetailModal(e.target.dataset.uid);
      });

      document.getElementById("form-ord-status-update").addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
          await apiFetch(`/api/admin/orders/${encodeURIComponent(order.order_number)}/status`, {
            method: "PATCH",
            body: JSON.stringify({
              status: document.getElementById("ord-next-status").value,
              courierName: document.getElementById("ord-courier").value.trim(),
              trackingNumber: document.getElementById("ord-tracking").value.trim(),
              expectedDeliveryDate: document.getElementById("ord-exp-date").value || null,
              reason: document.getElementById("ord-status-reason").value.trim()
            })
          });
          await openOrderDetailModal(order.order_number);
          renderModalAlert("Order status & history updated.", "success");
          loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
        } catch (err) {
          renderModalAlert(err.message);
        }
      });

      document.getElementById("form-ord-meta-save").addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
          await apiFetch(`/api/admin/orders/${encodeURIComponent(order.order_number)}/meta`, {
            method: "PATCH",
            body: JSON.stringify({
              paymentStatus: document.getElementById("ord-pay-status").value,
              notes: document.getElementById("ord-admin-notes").value,
              courierName: document.getElementById("ord-courier").value.trim(),
              trackingNumber: document.getElementById("ord-tracking").value.trim(),
              expectedDeliveryDate: document.getElementById("ord-exp-date").value || null
            })
          });
          await openOrderDetailModal(order.order_number);
          renderModalAlert("Notes, courier info, and payment status saved.", "success");
          loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
        } catch (err) {
          renderModalAlert(err.message);
        }
      });

      modalBody.querySelectorAll(".btn-ret-decide").forEach((b) => {
        b.addEventListener("click", async () => {
          const rid = b.dataset.retId;
          const note = modalBody.querySelector(`.ret-note-inp[data-ret-id="${rid}"]`)?.value.trim() || "";
          try {
            await apiFetch(`/api/admin/returns/${rid}/decision`, {
              method: "PATCH",
              body: JSON.stringify({ decision: b.dataset.decision, adminNote: note })
            });
            await openOrderDetailModal(order.order_number);
            renderModalAlert("Return request updated.", "success");
            loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
          } catch (err) {
            renderModalAlert(err.message);
          }
        });
      });

      modalBody.querySelectorAll(".btn-ref-process").forEach((b) => {
        b.addEventListener("click", async () => {
          const rid = b.dataset.retId;
          const method = modalBody.querySelector(`.ref-method-sel[data-ret-id="${rid}"]`)?.value || "bank_transfer";
          const refCode = modalBody.querySelector(`.ref-code-inp[data-ret-id="${rid}"]`)?.value.trim() || "";
          const note = modalBody.querySelector(`.ret-note-inp[data-ret-id="${rid}"]`)?.value.trim() || "";
          try {
            await apiFetch(`/api/admin/returns/${rid}/refund`, {
              method: "POST",
              body: JSON.stringify({ decision: b.dataset.decision, method, referenceCode: refCode, notes: note })
            });
            await openOrderDetailModal(order.order_number);
            renderModalAlert(b.dataset.decision === "reject" ? "Refund rejected." : "Refund processed and order marked Refunded.", "success");
            loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
          } catch (err) {
            renderModalAlert(err.message);
          }
        });
      });
    } catch (err) {
      showAlert(err.message);
    }
  }

  async function openCustomerDetailModal(userId) {
    try {
      const data = await apiFetch(`/api/admin/customers/${userId}`);
      const { customer, stats, addresses, orders, returns } = data;
      const addrHtml = (addresses || []).map((a) => `
        <div class="panel-sub" style="padding:8px;border:1px solid var(--border-subtle);border-radius:4px;margin-bottom:6px;background:var(--bg-canvas);">
          <strong>${esc(a.full_name)}</strong> (${esc(a.phone)}) ${a.is_default ? `<span class="status-badge ok">Default</span>` : ""}<br>
          ${esc(a.street_address)}, ${esc(a.area)}, ${esc(a.city)} ${esc(a.postal_code || "")}
        </div>`).join("") || `<p class="panel-sub">No saved addresses.</p>`;

      const ordHtml = (orders || []).map((o) => `
        <tr>
          <td><strong>${esc(o.order_number)}</strong></td>
          <td>${fmtDate(o.created_at)}</td>
          <td>${fmtMoney(o.total_amount)}</td>
          <td><span class="status-badge ${statusClass(o.order_status)}">${esc(o.order_status)}</span></td>
          <td><button type="button" class="btn btn-outline btn-sm btn-cust-open-ord" data-ord="${esc(o.order_number)}">Inspect</button></td>
        </tr>`).join("") || `<tr><td colspan="5" class="panel-sub">No orders yet.</td></tr>`;

      const retHtml = (returns || []).map((r) => `
        <tr>
          <td><strong>${esc(r.return_number)}</strong></td>
          <td>${esc(r.order_number)}</td>
          <td>${esc(r.reason)}</td>
          <td><span class="status-badge ${statusClass(r.status)}">${esc(r.status)}</span></td>
        </tr>`).join("") || `<tr><td colspan="4" class="panel-sub">No return requests.</td></tr>`;

      const html = `
        <div id="modal-alert" class="form-alert"></div>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
          <div>
            <h4 style="margin:0;font-size:18px;">${esc(customer.full_name)} <span class="status-badge ${customer.is_active ? "ok" : "bad"}">${customer.is_active ? "Active" : "Blocked / Inactive"}</span></h4>
            <div class="panel-sub">${esc(customer.email)} &bull; ${esc(customer.phone)} &bull; Joined ${fmtDate(customer.created_at)}</div>
          </div>
          <button type="button" id="btn-modal-cust-toggle" class="btn ${customer.is_active ? "btn-danger" : "btn-primary"} btn-sm" data-next="${customer.is_active ? 0 : 1}">
            ${customer.is_active ? "Block / Deactivate Account" : "Reactivate Account"}
          </button>
        </div>

        <div class="biz-metrics-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:14px;">
          <div class="biz-metric-box"><div class="label">Total Orders</div><div class="val">${stats.totalOrders}</div></div>
          <div class="biz-metric-box"><div class="label">Delivered</div><div class="val">${stats.deliveredOrders}</div></div>
          <div class="biz-metric-box"><div class="label">Total Spending</div><div class="val">${fmtMoney(stats.totalSpent)}</div></div>
          <div class="biz-metric-box"><div class="label">Returns</div><div class="val">${stats.totalReturns}</div></div>
        </div>

        <div style="margin-bottom:14px;"><strong>Saved Addresses</strong><div style="margin-top:6px;">${addrHtml}</div></div>

        <div class="modal-divider">
          <strong>Order History (${orders.length})</strong>
          <div class="table-scroll" style="margin-top:6px;">
            <table class="admin-table"><thead><tr><th>Order #</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>${ordHtml}</tbody></table>
          </div>
        </div>

        <div class="modal-divider">
          <strong>Return History (${returns.length})</strong>
          <div class="table-scroll" style="margin-top:6px;">
            <table class="admin-table"><thead><tr><th>Return #</th><th>Order #</th><th>Reason</th><th>Status</th></tr></thead><tbody>${retHtml}</tbody></table>
          </div>
        </div>`;

      openModal(`Collector Profile — ${customer.full_name}`, html);

      document.getElementById("btn-modal-cust-toggle").addEventListener("click", async (e) => {
        const nextActive = e.target.dataset.next === "1";
        try {
          await apiFetch(`/api/admin/customers/${customer.id}/status`, {
            method: "PATCH",
            body: JSON.stringify({ is_active: nextActive, reason: nextActive ? "Reactivated by admin" : "Account blocked by admin" })
          });
          await openCustomerDetailModal(customer.id);
          renderModalAlert(nextActive ? "Customer account reactivated." : "Customer account blocked.", "success");
          loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
        } catch (err) {
          renderModalAlert(err.message);
        }
      });

      modalBody.querySelectorAll(".btn-cust-open-ord").forEach((b) => {
        b.addEventListener("click", () => openOrderDetailModal(b.dataset.ord));
      });
    } catch (err) {
      showAlert(err.message);
    }
  }

  function renderSimpleCrudTable(section, rows) {
    if (!rows.length) return `<p class="panel-sub" style="padding:18px 0;">No matching database records found.</p>`;

    if (section === "inventory") {
      const filtered = rows.filter((r) => {
        if (currentSectionFilter === "low") return r.stock_health === "Low Stock";
        if (currentSectionFilter === "out") return r.stock_health === "Out of Stock";
        return true;
      });
      return `
        <table class="admin-table">
          <thead><tr><th>SKU</th><th>Watch</th><th>Available</th><th>Reserved</th><th>Sold</th><th>Returned</th><th>Alert Threshold</th><th>Health</th><th>Action</th></tr></thead>
          <tbody>
            ${filtered.map((r) => `
              <tr>
                <td><strong>${esc(r.sku)}</strong></td>
                <td>${esc(r.name)}</td>
                <td><strong>${r.available_stock}</strong></td>
                <td>${r.reserved_stock}</td>
                <td>${r.sold_quantity}</td>
                <td>${r.returned_quantity}</td>
                <td>${r.low_stock_threshold}</td>
                <td><span class="status-badge ${statusClass(r.stock_health)}">${esc(r.stock_health)}</span></td>
                <td><button type="button" class="btn btn-primary btn-sm btn-edit-entity" data-kind="inventory" data-id="${r.product_id}">Adjust Stock</button></td>
              </tr>`).join("")}
          </tbody>
        </table>`;
    }

    if (section === "orders") {
      return `
        <table class="admin-table">
          <thead><tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Order Date</th><th>Products</th><th>Total</th><th>Payment</th><th>Pay Status</th><th>Delivery</th><th>Order Status</th><th>Action</th></tr></thead>
          <tbody>
            ${rows.map((r) => `
              <tr>
                <td><strong>${esc(r.order_number)}</strong></td>
                <td>${esc(r.customer_name)}</td>
                <td>${esc(r.customer_phone)}</td>
                <td>${fmtDate(r.created_at)}</td>
                <td style="max-width:210px;white-space:normal;font-size:12px;">${esc(r.products_summary || "—")}</td>
                <td><strong>${fmtMoney(r.total_amount)}</strong></td>
                <td>${esc(r.payment_method)}</td>
                <td><span class="status-badge ${statusClass(r.payment_status)}">${esc(r.payment_status)}</span></td>
                <td>${esc(r.delivery_status)}${r.tracking_number ? `<div class="panel-sub">${esc(r.tracking_number)}</div>` : ""}</td>
                <td><span class="status-badge ${statusClass(r.order_status)}">${esc(r.order_status)}</span></td>
                <td><button type="button" class="btn btn-primary btn-sm btn-manage-order" data-ord="${esc(r.order_number)}">Manage</button></td>
              </tr>`).join("")}
          </tbody>
        </table>`;
    }

    if (section === "customers") {
      return `
        <table class="admin-table">
          <thead><tr><th>Collector</th><th>Email &amp; Phone</th><th>Joined</th><th>Orders</th><th>Returns</th><th>Total Spending</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            ${rows.map((c) => `
              <tr>
                <td><strong>${esc(c.full_name)}</strong><div class="panel-sub">#${c.id}</div></td>
                <td style="font-size:12px;">${esc(c.email)}<div class="panel-sub">${esc(c.phone)}</div></td>
                <td>${fmtDate(c.created_at)}</td>
                <td>${c.order_count}</td>
                <td>${c.return_count}</td>
                <td><strong>${fmtMoney(c.total_spent)}</strong></td>
                <td><span class="status-badge ${c.is_active ? "ok" : "bad"}">${c.is_active ? "Active" : "Blocked"}</span></td>
                <td>
                  <div class="action-row-btns">
                    <button type="button" class="btn btn-primary btn-sm btn-view-customer" data-id="${c.id}">Profile</button>
                    <button type="button" class="btn ${c.is_active ? "btn-outline" : "btn-gold"} btn-sm btn-block-customer" data-id="${c.id}" data-next="${c.is_active ? 0 : 1}">${c.is_active ? "Block" : "Activate"}</button>
                  </div>
                </td>
              </tr>`).join("")}
          </tbody>
        </table>`;
    }

    const cols = Object.keys(rows[0]);
    const hasActions = ["watch-types", "categories", "brands", "coupons", "reviews", "returns"].includes(section);
    return `
      <table class="admin-table">
        <thead><tr>${cols.map((c) => `<th>${esc(c.replace(/_/g, " "))}</th>`).join("")}${hasActions ? "<th>Actions</th>" : ""}</tr></thead>
        <tbody>
          ${rows.map((r) => {
            const cells = cols.map((col) => {
              const val = r[col];
              if (col.includes("status") || col === "stock_health") return `<td><span class="status-badge ${statusClass(val)}">${esc(val)}</span></td>`;
              if (col.includes("price") || col.includes("amount") || col.includes("spent") || col.includes("sales")) return `<td>${val === null ? "—" : fmtMoney(val)}</td>`;
              if (col.endsWith("_at")) return `<td>${fmtDate(val)}</td>`;
              return `<td>${esc(val === null ? "—" : val)}</td>`;
            }).join("");
            if (!hasActions) return `<tr>${cells}</tr>`;
            let actionHtml = "";
            if (["watch-types", "categories", "brands"].includes(section)) {
              actionHtml = `<div class="action-row-btns">
                <button type="button" class="btn btn-primary btn-sm btn-edit-entity" data-kind="${section}" data-id="${r.id}">Edit</button>
                <button type="button" class="btn btn-outline btn-sm btn-del-entity" data-kind="${section}" data-id="${r.id}" data-name="${esc(r.name)}">Delete</button>
              </div>`;
            } else if (section === "coupons") {
              actionHtml = `<button type="button" class="btn btn-primary btn-sm btn-edit-entity" data-kind="coupons" data-id="${r.id}">Edit</button>`;
            } else if (section === "reviews") {
              actionHtml = `<button type="button" class="btn btn-outline btn-sm btn-toggle-review" data-id="${r.id}" data-val="${r.is_approved ? 1 : 0}">${r.is_approved ? "Hide Review" : "Approve Review"}</button>`;
            } else if (section === "returns") {
              actionHtml = `<button type="button" class="btn btn-primary btn-sm btn-manage-order" data-ord="${esc(r.order_number)}">Process Return</button>`;
            }
            return `<tr>${cells}<td>${actionHtml}</td></tr>`;
          }).join("")}
        </tbody>
      </table>`;
  }

  function renderPaginationBar(host, page, totalPages, total, onPage) {
    host.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding-top:10px;border-top:1px solid var(--border-subtle);">
        <span class="panel-sub">Total Records: <strong>${total}</strong> &bull; Page ${page} of ${totalPages}</span>
        <div class="action-row-btns">
          <button type="button" class="btn btn-outline btn-sm" id="btn-page-prev" ${page <= 1 ? "disabled" : ""}>&larr; Prev</button>
          <button type="button" class="btn btn-outline btn-sm" id="btn-page-next" ${page >= totalPages ? "disabled" : ""}>Next &rarr;</button>
        </div>
      </div>`;
    host.querySelector("#btn-page-prev")?.addEventListener("click", () => onPage(page - 1));
    host.querySelector("#btn-page-next")?.addEventListener("click", () => onPage(page + 1));
  }

  function renderSectionUi(section, res) {
    const actionsHost = document.getElementById("section-actions-host");
    const subbarHost = document.getElementById("section-subbar-host");
    const tableHost = document.getElementById("section-table-host");
    const extraHost = document.getElementById("section-extra-host");

    actionsHost.innerHTML = "";
    subbarHost.innerHTML = "";
    extraHost.innerHTML = "";

    const addMap = {
      products: { label: "+ Add New Watch", fn: () => openProductModal(null) },
      "watch-types": { label: "+ Add Product Type", fn: () => openSimpleEntityModal("watch-types", null) },
      categories: { label: "+ Add Category", fn: () => openSimpleEntityModal("categories", null) },
      brands: { label: "+ Add Brand", fn: () => openSimpleEntityModal("brands", null) },
      coupons: { label: "+ Add Coupon", fn: () => openSimpleEntityModal("coupons", null) }
    };
    if (addMap[section]) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-gold btn-sm";
      btn.id = "btn-section-add";
      btn.textContent = addMap[section].label;
      btn.addEventListener("click", addMap[section].fn);
      actionsHost.appendChild(btn);
    }

    if (section === "products" || section === "inventory") {
      const pills = section === "products"
        ? [["all", "All Watches"], ["active", "Active Only"], ["featured", "Featured"], ["low", "Low Stock (<=5)"]]
        : [["all", "All Stock"], ["low", "Low Stock"], ["out", "Out of Stock"]];
      subbarHost.innerHTML = `<div class="preset-pills">${pills.map(([k, l]) => `<button type="button" class="preset-btn ${currentSectionFilter === k ? "active" : ""} btn-sec-filter" data-filter="${k}">${esc(l)}</button>`).join("")}</div>`;
      subbarHost.querySelectorAll(".btn-sec-filter").forEach((b) => {
        b.addEventListener("click", () => {
          currentSectionFilter = b.dataset.filter;
          renderSectionUi(section, res);
        });
      });
    } else if (section === "orders") {
      const statuses = ["all", "Pending", "Confirmed", "Processing", "Packed", "Shipped", "Out for Delivery", "Delivered", "Cancelled", "Rejected", "Return Requested", "Returned", "Refunded"];
      subbarHost.innerHTML = `
        <form id="form-orders-filter" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <select id="ord-f-status" class="field-control field-control-sm">
            ${statuses.map((s) => `<option value="${s}" ${ordersState.status === s ? "selected" : ""}>${s === "all" ? "All Statuses" : s}</option>`).join("")}
          </select>
          <input type="date" id="ord-f-from" class="field-control field-control-sm" value="${esc(ordersState.dateFrom)}" title="From date">
          <input type="date" id="ord-f-to" class="field-control field-control-sm" value="${esc(ordersState.dateTo)}" title="To date">
          <select id="ord-f-sort" class="field-control field-control-sm">
            <option value="newest" ${ordersState.sort === "newest" ? "selected" : ""}>Sort: Newest</option>
            <option value="oldest" ${ordersState.sort === "oldest" ? "selected" : ""}>Sort: Oldest</option>
            <option value="total_desc" ${ordersState.sort === "total_desc" ? "selected" : ""}>Amount: High to Low</option>
            <option value="total_asc" ${ordersState.sort === "total_asc" ? "selected" : ""}>Amount: Low to High</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
          <button type="button" id="btn-ord-reset" class="btn btn-outline btn-sm">Reset</button>
        </form>`;
      document.getElementById("form-orders-filter").addEventListener("submit", (e) => {
        e.preventDefault();
        ordersState.status = document.getElementById("ord-f-status").value;
        ordersState.dateFrom = document.getElementById("ord-f-from").value;
        ordersState.dateTo = document.getElementById("ord-f-to").value;
        ordersState.sort = document.getElementById("ord-f-sort").value;
        ordersState.q = document.getElementById("section-search-input").value.trim();
        ordersState.page = 1;
        loadSectionDirectory("orders", ordersState.q);
      });
      document.getElementById("btn-ord-reset").addEventListener("click", () => {
        ordersState = { q: "", status: "all", dateFrom: "", dateTo: "", sort: "newest", page: 1 };
        document.getElementById("section-search-input").value = "";
        loadSectionDirectory("orders", "");
      });
    } else if (section === "customers") {
      subbarHost.innerHTML = `
        <form id="form-customers-filter" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <select id="cust-f-status" class="field-control field-control-sm">
            <option value="all" ${customersState.status === "all" ? "selected" : ""}>All Accounts</option>
            <option value="active" ${customersState.status === "active" ? "selected" : ""}>Active Only</option>
            <option value="blocked" ${customersState.status === "blocked" ? "selected" : ""}>Blocked / Deactivated</option>
          </select>
          <select id="cust-f-sort" class="field-control field-control-sm">
            <option value="newest" ${customersState.sort === "newest" ? "selected" : ""}>Sort: Newest</option>
            <option value="spent_desc" ${customersState.sort === "spent_desc" ? "selected" : ""}>Highest Spending</option>
            <option value="orders_desc" ${customersState.sort === "orders_desc" ? "selected" : ""}>Most Orders</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        </form>`;
      document.getElementById("form-customers-filter").addEventListener("submit", (e) => {
        e.preventDefault();
        customersState.status = document.getElementById("cust-f-status").value;
        customersState.sort = document.getElementById("cust-f-sort").value;
        customersState.q = document.getElementById("section-search-input").value.trim();
        customersState.page = 1;
        loadSectionDirectory("customers", customersState.q);
      });
    }

    const rows = res.rows || [];
    tableHost.innerHTML = section === "products" ? renderEditableProductsTable(rows) : renderSimpleCrudTable(section, rows);

    if (section === "inventory" && res.movements?.length) {
      extraHost.innerHTML = `
        <h4 style="font-family:var(--font-display);font-size:22px;margin:0 0 10px;">Inventory Movement History</h4>
        <div class="table-scroll">
          ${buildMiniTable("", ["Date", "SKU", "Watch", "Type", "Delta", "Order #", "By", "Note"], res.movements.map((m) => [
            fmtDate(m.created_at),
            esc(m.sku),
            esc(m.product_name),
            `<span class="status-badge">${esc(m.movement_type)}</span>`,
            `<strong>${m.change_qty > 0 ? `+${m.change_qty}` : m.change_qty}</strong>`,
            esc(m.order_number || "—"),
            esc(m.admin_name || "System"),
            esc(m.note || "—")
          ]))}
        </div>`;
    } else if (section === "orders" || section === "customers") {
      renderPaginationBar(extraHost, res.page || 1, res.totalPages || 1, res.total || 0, (nextPage) => {
        if (section === "orders") ordersState.page = nextPage;
        else customersState.page = nextPage;
        loadSectionDirectory(section, document.getElementById("section-search-input").value);
      });
    }

    // Bind row action handlers cleanly
    tableHost.querySelectorAll(".btn-manage-order").forEach((b) => b.addEventListener("click", () => openOrderDetailModal(b.dataset.ord)));
    tableHost.querySelectorAll(".btn-view-customer").forEach((b) => b.addEventListener("click", () => openCustomerDetailModal(b.dataset.id)));
    tableHost.querySelectorAll(".btn-block-customer").forEach((b) => {
      b.addEventListener("click", async () => {
        const nextActive = b.dataset.next === "1";
        try {
          await apiFetch(`/api/admin/customers/${b.dataset.id}/status`, {
            method: "PATCH",
            body: JSON.stringify({ is_active: nextActive, reason: nextActive ? "Reactivated by admin" : "Account blocked by admin" })
          });
          showAlert(nextActive ? "Customer reactivated." : "Customer blocked.", "success");
          loadSectionDirectory("customers", document.getElementById("section-search-input").value);
        } catch (err) {
          showAlert(err.message);
        }
      });
    });

    tableHost.querySelectorAll(".btn-edit-product").forEach((b) => b.addEventListener("click", () => openProductModal(b.dataset.id)));
    tableHost.querySelectorAll(".btn-del-product").forEach((b) => {
      b.addEventListener("click", async () => {
        if (!window.confirm(`Delete or archive "${b.dataset.name}"?`)) return;
        try {
          const out = await apiFetch(`/api/admin/products/${b.dataset.id}`, { method: "DELETE" });
          showAlert(out.message, "success");
          loadSectionDirectory(section, document.getElementById("section-search-input").value);
        } catch (err) {
          showAlert(err.message);
        }
      });
    });
    tableHost.querySelectorAll(".btn-flag-toggle").forEach((b) => {
      b.addEventListener("click", async () => {
        try {
          const next = b.dataset.val === "1" ? 0 : 1;
          await apiFetch(`/api/admin/products/${b.dataset.id}/flags`, { method: "PATCH", body: JSON.stringify({ [b.dataset.flag]: next }) });
          loadSectionDirectory(section, document.getElementById("section-search-input").value);
        } catch (err) {
          showAlert(err.message);
        }
      });
    });

    tableHost.querySelectorAll(".btn-edit-entity").forEach((b) => {
      b.addEventListener("click", () => {
        const keyCol = b.dataset.kind === "inventory" ? "product_id" : "id";
        const row = rows.find((r) => String(r[keyCol]) === String(b.dataset.id));
        if (row) openSimpleEntityModal(b.dataset.kind, row);
      });
    });
    tableHost.querySelectorAll(".btn-del-entity").forEach((b) => {
      b.addEventListener("click", async () => {
        if (!window.confirm(`Delete "${b.dataset.name}"?`)) return;
        try {
          await apiFetch(`/api/admin/${b.dataset.kind}/${b.dataset.id}`, { method: "DELETE" });
          await ensureMeta(true);
          showAlert(`Deleted "${b.dataset.name}".`, "success");
          loadSectionDirectory(section, document.getElementById("section-search-input").value);
        } catch (err) {
          showAlert(err.message);
        }
      });
    });
    tableHost.querySelectorAll(".btn-toggle-review").forEach((b) => {
      b.addEventListener("click", async () => {
        try {
          const next = b.dataset.val !== "1";
          await apiFetch(`/api/admin/reviews/${b.dataset.id}`, { method: "PATCH", body: JSON.stringify({ is_approved: next }) });
          loadSectionDirectory(section, document.getElementById("section-search-input").value);
        } catch (err) {
          showAlert(err.message);
        }
      });
    });
  }

  async function downloadAuthFile(url, filename) {
    const res = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
    if (!res.ok) {
      let msg = "Download failed.";
      try { msg = (await res.json()).message || msg; } catch {}
      throw new Error(msg);
    }
    const blob = await res.blob();
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 200);
  }

  // ---------- Phase 9: Reports, Website Settings, Promotions & Governance Renderers ----------
  const REPORT_TYPES = [
    ["daily_sales", "Daily Sales Report"],
    ["weekly_sales", "Weekly Sales Report"],
    ["monthly_sales", "Monthly Sales Report"],
    ["yearly_sales", "Yearly Sales Report"],
    ["orders", "Order Reports"],
    ["returns", "Return Reports"],
    ["rejected_orders", "Rejected Order Reports"],
    ["customers", "Customer Reports"],
    ["product_sales", "Product Sales Reports"],
    ["inventory", "Inventory Reports"],
    ["payments", "Payment Reports"]
  ];
  let reportState = { type: "daily_sales", range: "30d", startDate: "", endDate: "" };

  async function renderReportsWorkspace() {
    const subbar = document.getElementById("section-subbar-host");
    const tblHost = document.getElementById("section-table-host");
    const extraHost = document.getElementById("section-extra-host");
    document.getElementById("section-actions-host").innerHTML = "";
    extraHost.innerHTML = "";

    subbar.innerHTML = `
      <form id="form-reports-bar" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select id="rep-type" class="field-control field-control-sm">
          ${REPORT_TYPES.map(([k, l]) => `<option value="${k}" ${reportState.type === k ? "selected" : ""}>${esc(l)}</option>`).join("")}
        </select>
        <select id="rep-range" class="field-control field-control-sm">
          <option value="today" ${reportState.range === "today" ? "selected" : ""}>Today</option>
          <option value="yesterday" ${reportState.range === "yesterday" ? "selected" : ""}>Yesterday</option>
          <option value="7d" ${reportState.range === "7d" ? "selected" : ""}>Last 7 Days</option>
          <option value="30d" ${reportState.range === "30d" ? "selected" : ""}>Last 30 Days</option>
          <option value="this_month" ${reportState.range === "this_month" ? "selected" : ""}>This Month</option>
          <option value="prev_month" ${reportState.range === "prev_month" ? "selected" : ""}>Previous Month</option>
          <option value="this_year" ${reportState.range === "this_year" ? "selected" : ""}>This Year</option>
          <option value="custom" ${reportState.range === "custom" ? "selected" : ""}>Custom Dates</option>
        </select>
        <input type="date" id="rep-start" class="field-control field-control-sm" value="${esc(reportState.startDate)}" style="${reportState.range === "custom" ? "" : "display:none;"}">
        <input type="date" id="rep-end" class="field-control field-control-sm" value="${esc(reportState.endDate)}" style="${reportState.range === "custom" ? "" : "display:none;"}">
        <button type="submit" class="btn btn-primary btn-sm">Preview Report</button>
        <button type="button" id="btn-export-csv" class="btn btn-outline btn-sm">Download CSV</button>
        <button type="button" id="btn-export-pdf" class="btn btn-gold btn-sm">Download PDF</button>
      </form>`;

    const loadPreview = async () => {
      const qs = new URLSearchParams({ range: reportState.range });
      if (reportState.range === "custom") {
        qs.set("startDate", reportState.startDate);
        qs.set("endDate", reportState.endDate);
      }
      const { report } = await apiFetch(`/api/admin/reports/${encodeURIComponent(reportState.type)}?${qs.toString()}`);
      document.getElementById("section-view-title").textContent = report.title;
      document.getElementById("section-view-sub").textContent = `Period: ${report.subtitle} • Ready for CSV & PDF export`;
      const rows = report.rows || [];
      tblHost.innerHTML = rows.length
        ? `<table class="admin-table"><thead><tr>${report.columns.map((c) => `<th>${esc(c.label)}</th>`).join("")}</tr></thead><tbody>${rows.map((r) => `<tr>${report.columns.map((c) => `<td>${esc(r[c.key] ?? "—")}</td>`).join("")}</tr>`).join("")}</tbody></table>`
        : `<p class="panel-sub" style="padding:18px 0;">No data in selected date window.</p>`;
    };

    const syncReportInputs = () => {
      reportState.type = document.getElementById("rep-type").value;
      reportState.range = document.getElementById("rep-range").value;
      reportState.startDate = document.getElementById("rep-start").value;
      reportState.endDate = document.getElementById("rep-end").value;
    };

    document.getElementById("rep-range").addEventListener("change", (e) => {
      const show = e.target.value === "custom";
      document.getElementById("rep-start").style.display = show ? "" : "none";
      document.getElementById("rep-end").style.display = show ? "" : "none";
    });
    document.getElementById("form-reports-bar").addEventListener("submit", async (e) => {
      e.preventDefault();
      syncReportInputs();
      try { await loadPreview(); } catch (err) { showAlert(err.message); }
    });
    document.getElementById("btn-export-csv").addEventListener("click", async () => {
      syncReportInputs();
      const qs = new URLSearchParams({ range: reportState.range, format: "csv", startDate: reportState.startDate, endDate: reportState.endDate });
      try { await downloadAuthFile(`/api/admin/reports/${reportState.type}?${qs.toString()}`, `valenor-${reportState.type}.csv`); } catch (err) { showAlert(err.message); }
    });
    document.getElementById("btn-export-pdf").addEventListener("click", async () => {
      syncReportInputs();
      const qs = new URLSearchParams({ range: reportState.range, format: "pdf", startDate: reportState.startDate, endDate: reportState.endDate });
      try { await downloadAuthFile(`/api/admin/reports/${reportState.type}?${qs.toString()}`, `valenor-${reportState.type}.pdf`); } catch (err) { showAlert(err.message); }
    });

    await loadPreview();
  }

  async function renderSettingsWorkspace() {
    document.getElementById("section-actions-host").innerHTML = "";
    document.getElementById("section-subbar-host").innerHTML = "";
    document.getElementById("section-extra-host").innerHTML = "";
    const tblHost = document.getElementById("section-table-host");

    const { settings } = await apiFetch("/api/admin/settings");
    document.getElementById("topbar-heading").textContent = "Website Settings";
    document.getElementById("section-view-title").textContent = "Website Settings & Policies";
    document.getElementById("section-view-sub").textContent = "Manage storefront identity, brand logo, contact info, currency, taxes, shipping fees, and store policies";

    tblHost.innerHTML = `
      <form id="form-website-settings" style="max-width:940px;">
        <div class="form-grid-2">
          <div class="field-group"><label>Website Name</label><input type="text" id="set-name" class="field-control" value="${esc(settings.website_name || "")}"></div>
          <div class="field-group">
            <label>Brand Logo URL or Upload New Image</label>
            <div style="display:flex;gap:8px;align-items:center;">
              <img id="set-logo-preview" src="${esc(resolveImg(settings.brand_logo_url))}" alt="Logo" class="admin-thumb">
              <input type="text" id="set-logo-url" class="field-control" value="${esc(settings.brand_logo_url || "")}" style="flex:1;">
              <label class="btn btn-outline btn-sm" style="cursor:pointer;">Upload<input type="file" id="set-logo-file" accept="image/jpeg,image/png,image/webp" style="display:none;"></label>
            </div>
          </div>
        </div>

        <div class="form-grid-3">
          <div class="field-group"><label>Support Email</label><input type="email" id="set-email" class="field-control" value="${esc(settings.contact_email || "")}"></div>
          <div class="field-group"><label>Concierge Phone / WhatsApp</label><input type="text" id="set-phone" class="field-control" value="${esc(settings.contact_phone || "")}"></div>
          <div class="field-group"><label>Boutique &amp; Hours Info</label><input type="text" id="set-contact-info" class="field-control" value="${esc(settings.contact_info || "")}"></div>
        </div>

        <div class="form-grid-3">
          <div class="field-group"><label>Instagram URL</label><input type="url" id="set-ig" class="field-control" value="${esc(settings.social_instagram || "")}"></div>
          <div class="field-group"><label>Facebook URL</label><input type="url" id="set-fb" class="field-control" value="${esc(settings.social_facebook || "")}"></div>
          <div class="field-group"><label>YouTube URL</label><input type="url" id="set-yt" class="field-control" value="${esc(settings.social_youtube || "")}"></div>
        </div>

        <div class="form-grid-3">
          <div class="field-group"><label>Storefront Currency</label>
            <select id="set-currency" class="field-control">
              ${["PKR", "USD", "AED", "GBP", "EUR"].map((c) => `<option value="${c}" ${settings.currency === c ? "selected" : ""}>${c}</option>`).join("")}
            </select>
          </div>
          <div class="field-group"><label>Tax Rate (%)</label><input type="number" step="0.1" min="0" id="set-tax" class="field-control" value="${esc(settings.tax_rate_percent ?? 0)}"></div>
          <div class="field-group"><label>Free Shipping Threshold</label><input type="number" min="0" id="set-free-ship" class="field-control" value="${esc(settings.free_shipping_threshold ?? 15000)}"></div>
        </div>

        <div class="form-grid-2">
          <div class="field-group"><label>Standard Delivery Fee</label><input type="number" min="0" id="set-ship-std" class="field-control" value="${esc(settings.standard_shipping_fee ?? 250)}"></div>
          <div class="field-group"><label>Express Delivery Fee</label><input type="number" min="0" id="set-ship-exp" class="field-control" value="${esc(settings.express_shipping_fee ?? 550)}"></div>
        </div>

        <div class="field-group"><label>Return &amp; Refund Policy</label><textarea id="set-ret-policy" class="field-control" rows="2">${esc(settings.return_policy || "")}</textarea></div>
        <div class="field-group"><label>Privacy Policy</label><textarea id="set-priv-policy" class="field-control" rows="2">${esc(settings.privacy_policy || "")}</textarea></div>
        <div class="field-group"><label>Terms &amp; Conditions</label><textarea id="set-terms" class="field-control" rows="2">${esc(settings.terms_conditions || "")}</textarea></div>

        <div style="margin-top:10px;"><button type="submit" id="btn-save-settings" class="btn btn-primary">Save Website Settings</button></div>
      </form>`;

    document.getElementById("set-logo-file").addEventListener("change", async (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      const fd = new FormData();
      fd.append("asset", file);
      fd.append("target", "logo");
      try {
        const res = await apiFetch("/api/admin/branding/upload", { method: "POST", body: fd });
        document.getElementById("set-logo-url").value = res.url;
        document.getElementById("set-logo-preview").src = res.url;
        showAlert("Brand logo uploaded & saved.", "success");
      } catch (err) {
        showAlert(err.message);
      }
    });

    document.getElementById("form-website-settings").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await apiFetch("/api/admin/settings", {
          method: "PUT",
          body: JSON.stringify({
            website_name: document.getElementById("set-name").value.trim(),
            brand_logo_url: document.getElementById("set-logo-url").value.trim(),
            contact_email: document.getElementById("set-email").value.trim(),
            contact_phone: document.getElementById("set-phone").value.trim(),
            contact_info: document.getElementById("set-contact-info").value.trim(),
            social_instagram: document.getElementById("set-ig").value.trim(),
            social_facebook: document.getElementById("set-fb").value.trim(),
            social_youtube: document.getElementById("set-yt").value.trim(),
            currency: document.getElementById("set-currency").value,
            tax_rate_percent: document.getElementById("set-tax").value,
            free_shipping_threshold: document.getElementById("set-free-ship").value,
            standard_shipping_fee: document.getElementById("set-ship-std").value,
            express_shipping_fee: document.getElementById("set-ship-exp").value,
            return_policy: document.getElementById("set-ret-policy").value.trim(),
            privacy_policy: document.getElementById("set-priv-policy").value.trim(),
            terms_conditions: document.getElementById("set-terms").value.trim()
          })
        });
        showAlert("Website settings saved & synced to live storefront.", "success");
      } catch (err) {
        showAlert(err.message);
      }
    });
  }

  function openPromoModal(kind, existing = null) {
    const isCamp = kind === "campaign";
    const title = `${existing ? "Edit" : "Create"} ${isCamp ? "Promotional Campaign" : "Homepage Banner"}`;
    const body = isCamp
      ? `
        <div id="modal-alert" class="form-alert"></div>
        <form id="form-promo-modal">
          <div class="form-grid-2">
            <div class="field-group"><label>Campaign Name *</label><input type="text" id="pr-name" class="field-control" value="${esc(existing?.name || "")}" required></div>
            <div class="field-group"><label>Badge Label *</label><input type="text" id="pr-badge" class="field-control" value="${esc(existing?.badge_text || "FLASH SALE")}" required></div>
          </div>
          <div class="field-group"><label>Announcement Headline *</label><input type="text" id="pr-head" class="field-control" value="${esc(existing?.headline || "")}" required></div>
          <div class="form-grid-2">
            <div class="field-group"><label>Discount %</label><input type="number" min="0" max="95" id="pr-disc" class="field-control" value="${esc(existing?.discount_percent ?? 20)}"></div>
            <div class="field-group"><label>Promo Coupon Code</label><input type="text" id="pr-code" class="field-control" value="${esc(existing?.coupon_code || "")}" placeholder="e.g. SALE20"></div>
          </div>
          <div class="form-grid-2">
            <div class="field-group"><label>Starts At *</label><input type="datetime-local" id="pr-start" class="field-control" value="${existing?.starts_at ? new Date(existing.starts_at).toISOString().slice(0, 16) : "2026-11-01T00:00"}" required></div>
            <div class="field-group"><label>Ends At *</label><input type="datetime-local" id="pr-end" class="field-control" value="${existing?.ends_at ? new Date(existing.ends_at).toISOString().slice(0, 16) : "2028-12-31T23:59"}" required></div>
          </div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px;"><input type="checkbox" id="pr-active" ${!existing || existing.is_active ? "checked" : ""}> Active Campaign</label>
          <div style="display:flex;justify-content:flex-end;gap:10px;"><button type="button" class="btn btn-outline" id="btn-pr-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save Campaign</button></div>
        </form>`
      : `
        <div id="modal-alert" class="form-alert"></div>
        <form id="form-promo-modal">
          <div class="field-group"><label>Banner Title *</label><input type="text" id="bn-title" class="field-control" value="${esc(existing?.title || "")}" required></div>
          <div class="field-group"><label>Subtitle</label><input type="text" id="bn-sub" class="field-control" value="${esc(existing?.subtitle || "")}"></div>
          <div class="form-grid-2">
            <div class="field-group"><label>CTA Button Text</label><input type="text" id="bn-cta" class="field-control" value="${esc(existing?.cta_text || "Shop Collection")}"></div>
            <div class="field-group"><label>CTA Target Link</label><input type="text" id="bn-link" class="field-control" value="${esc(existing?.cta_link || "pages/catalog.html")}"></div>
          </div>
          <div class="form-grid-3">
            <div class="field-group"><label>Placement</label>
              <select id="bn-place" class="field-control">
                <option value="hero" ${existing?.placement === "hero" ? "selected" : ""}>Hero</option>
                <option value="mid_promo" ${existing?.placement === "mid_promo" ? "selected" : ""}>Mid Promo</option>
                <option value="top_announcement" ${existing?.placement === "top_announcement" ? "selected" : ""}>Top Strip</option>
              </select>
            </div>
            <div class="field-group"><label>Sort Order</label><input type="number" id="bn-sort" class="field-control" value="${esc(existing?.sort_order ?? 1)}"></div>
            <div class="field-group" style="justify-content:flex-end;"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="bn-active" ${!existing || existing.is_active ? "checked" : ""}> Active</label></div>
          </div>
          <div class="field-group">
            <label>Banner Image Path or Upload</label>
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="text" id="bn-img" class="field-control" value="${esc(existing?.image_url || "assets/images/hero-banner.jpg")}" style="flex:1;">
              <label class="btn btn-outline btn-sm" style="cursor:pointer;">Upload<input type="file" id="bn-file" accept="image/jpeg,image/png,image/webp" style="display:none;"></label>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:10px;"><button type="button" class="btn btn-outline" id="btn-pr-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save Banner</button></div>
        </form>`;

    openModal(title, body, { narrow: true });
    document.getElementById("btn-pr-cancel").addEventListener("click", closeModal);
    document.getElementById("bn-file")?.addEventListener("change", async (e) => {
      const f = e.target.files?.[0];
      if (!f) return;
      const fd = new FormData();
      fd.append("asset", f);
      try {
        const res = await apiFetch("/api/admin/branding/upload", { method: "POST", body: fd });
        document.getElementById("bn-img").value = res.url;
        renderModalAlert("Banner image uploaded.", "success");
      } catch (err) { renderModalAlert(err.message); }
    });
    document.getElementById("form-promo-modal").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        if (isCamp) {
          const payload = {
            name: document.getElementById("pr-name").value.trim(),
            badge_text: document.getElementById("pr-badge").value.trim(),
            headline: document.getElementById("pr-head").value.trim(),
            discount_percent: Number(document.getElementById("pr-disc").value || 0),
            coupon_code: document.getElementById("pr-code").value.trim(),
            starts_at: document.getElementById("pr-start").value.replace("T", " ") + ":00",
            ends_at: document.getElementById("pr-end").value.replace("T", " ") + ":00",
            is_active: document.getElementById("pr-active").checked
          };
          await apiFetch(existing ? `/api/admin/campaigns/${existing.id}` : "/api/admin/campaigns", { method: existing ? "PUT" : "POST", body: JSON.stringify(payload) });
        } else {
          const payload = {
            title: document.getElementById("bn-title").value.trim(),
            subtitle: document.getElementById("bn-sub").value.trim(),
            cta_text: document.getElementById("bn-cta").value.trim(),
            cta_link: document.getElementById("bn-link").value.trim(),
            placement: document.getElementById("bn-place").value,
            sort_order: Number(document.getElementById("bn-sort").value || 1),
            image_url: document.getElementById("bn-img").value.trim(),
            is_active: document.getElementById("bn-active").checked
          };
          await apiFetch(existing ? `/api/admin/banners/${existing.id}` : "/api/admin/banners", { method: existing ? "PUT" : "POST", body: JSON.stringify(payload) });
        }
        closeModal();
        showAlert(`${title} saved.`, "success");
        loadSectionDirectory("promotions");
      } catch (err) {
        renderModalAlert(err.message);
      }
    });
  }

  async function renderPromotionsWorkspace() {
    const actionsHost = document.getElementById("section-actions-host");
    const subbar = document.getElementById("section-subbar-host");
    const tblHost = document.getElementById("section-table-host");
    const extraHost = document.getElementById("section-extra-host");

    const { campaigns, banners, categories } = await apiFetch("/api/admin/promotions");
    document.getElementById("topbar-heading").textContent = "Promotional Management";
    document.getElementById("section-view-title").textContent = "Campaigns, Banners & Merchandising";
    document.getElementById("section-view-sub").textContent = "Control homepage banners, sale dates & discount campaigns, featured categories, coupons, best sellers, and new arrivals";

    actionsHost.innerHTML = `
      <button type="button" class="btn btn-gold btn-sm" id="btn-add-campaign">+ Add Campaign</button>
      <button type="button" class="btn btn-primary btn-sm" id="btn-add-banner">+ Add Banner</button>`;
    subbar.innerHTML = `
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn btn-outline btn-sm" id="btn-jump-coupons">Manage Coupons &rarr;</button>
        <button type="button" class="btn btn-outline btn-sm" id="btn-jump-prods">Manage Discounts / Best Sellers / New Arrivals &rarr;</button>
      </div>`;

    tblHost.innerHTML = `
      <h4 style="font-family:var(--font-display);font-size:21px;margin:0 0 8px;">Promotional Campaigns</h4>
      <table class="admin-table" style="margin-bottom:22px;">
        <thead><tr><th>Campaign</th><th>Badge</th><th>Discount</th><th>Coupon</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          ${campaigns.map((c) => `
            <tr>
              <td><strong>${esc(c.name)}</strong><div class="panel-sub">${esc(c.headline)}</div></td>
              <td><span class="status-badge">${esc(c.badge_text)}</span></td>
              <td>${c.discount_percent}%</td>
              <td>${esc(c.coupon_code || "—")}</td>
              <td style="font-size:12px;">${fmtDate(c.starts_at)} &rarr; ${fmtDate(c.ends_at)}</td>
              <td><span class="status-badge ${c.is_active ? "ok" : "bad"}">${c.is_active ? "Active" : "Paused"}</span></td>
              <td><div class="action-row-btns">
                <button type="button" class="btn btn-primary btn-sm btn-edit-camp" data-id="${c.id}">Edit</button>
                <button type="button" class="btn btn-outline btn-sm btn-del-camp" data-id="${c.id}">Delete</button>
              </div></td>
            </tr>`).join("")}
        </tbody>
      </table>

      <h4 style="font-family:var(--font-display);font-size:21px;margin:0 0 8px;">Homepage Banners</h4>
      <table class="admin-table">
        <thead><tr><th>Preview</th><th>Title &amp; CTA</th><th>Placement</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          ${banners.map((b) => `
            <tr>
              <td><img src="${esc(resolveImg(b.image_url))}" alt="${esc(b.title)}" class="admin-thumb"></td>
              <td><strong>${esc(b.title)}</strong><div class="panel-sub">${esc(b.cta_text)} (${esc(b.cta_link)})</div></td>
              <td><span class="status-badge">${esc(b.placement)}</span></td>
              <td><span class="status-badge ${b.is_active ? "ok" : "bad"}">${b.is_active ? "Active" : "Hidden"}</span></td>
              <td><div class="action-row-btns">
                <button type="button" class="btn btn-primary btn-sm btn-edit-banner" data-id="${b.id}">Edit</button>
                <button type="button" class="btn btn-outline btn-sm btn-del-banner" data-id="${b.id}">Delete</button>
              </div></td>
            </tr>`).join("")}
        </tbody>
      </table>`;

    extraHost.innerHTML = `
      <h4 style="font-family:var(--font-display);font-size:21px;margin:0 0 8px;">Featured Categories</h4>
      <div class="checkbox-cluster">
        ${categories.map((c) => `
          <button type="button" class="toggle-chip ${c.is_featured ? "on" : ""} btn-cat-feat" data-id="${c.id}" data-val="${c.is_featured ? 1 : 0}">
            ${c.is_featured ? "&#9733; " : ""}${esc(c.name)}
          </button>`).join("")}
      </div>`;

    document.getElementById("btn-add-campaign").addEventListener("click", () => openPromoModal("campaign", null));
    document.getElementById("btn-add-banner").addEventListener("click", () => openPromoModal("banner", null));
    document.getElementById("btn-jump-coupons").addEventListener("click", () => switchSection("coupons"));
    document.getElementById("btn-jump-prods").addEventListener("click", () => switchSection("products"));

    tblHost.querySelectorAll(".btn-edit-camp").forEach((b) => b.addEventListener("click", () => openPromoModal("campaign", campaigns.find((c) => String(c.id) === b.dataset.id))));
    tblHost.querySelectorAll(".btn-del-camp").forEach((b) => b.addEventListener("click", async () => {
      await apiFetch(`/api/admin/campaigns/${b.dataset.id}`, { method: "DELETE" });
      renderPromotionsWorkspace();
    }));
    tblHost.querySelectorAll(".btn-edit-banner").forEach((b) => b.addEventListener("click", () => openPromoModal("banner", banners.find((x) => String(x.id) === b.dataset.id))));
    tblHost.querySelectorAll(".btn-del-banner").forEach((b) => b.addEventListener("click", async () => {
      await apiFetch(`/api/admin/banners/${b.dataset.id}`, { method: "DELETE" });
      renderPromotionsWorkspace();
    }));
    extraHost.querySelectorAll(".btn-cat-feat").forEach((b) => b.addEventListener("click", async () => {
      const next = b.dataset.val !== "1";
      await apiFetch(`/api/admin/categories/${b.dataset.id}/featured`, { method: "PATCH", body: JSON.stringify({ is_featured: next }) });
      renderPromotionsWorkspace();
    }));
  }

  function openStaffModal(existing, roles) {
    const title = existing ? `Edit Staff — ${existing.full_name}` : "Add Admin Staff User";
    openModal(title, `
      <div id="modal-alert" class="form-alert"></div>
      <form id="form-staff-modal">
        <div class="field-group"><label>Full Name *</label><input type="text" id="st-name" class="field-control" value="${esc(existing?.full_name || "")}" required></div>
        <div class="form-grid-2">
          <div class="field-group"><label>Email *</label><input type="email" id="st-email" class="field-control" value="${esc(existing?.email || "")}" required></div>
          <div class="field-group"><label>Phone *</label><input type="text" id="st-phone" class="field-control" value="${esc(existing?.phone || "")}" required></div>
        </div>
        <div class="form-grid-2">
          <div class="field-group"><label>Role *</label>
            <select id="st-role" class="field-control">
              ${roles.map((r) => `<option value="${esc(r.name)}" ${existing?.role === r.name ? "selected" : ""}>${esc(r.name)}</option>`).join("")}
            </select>
          </div>
          <div class="field-group"><label>${existing ? "Reset Password (Optional, min 8)" : "Password * (min 8 chars)"}</label><input type="password" id="st-pass" class="field-control" minlength="8" ${existing ? "" : "required"}></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px;"><input type="checkbox" id="st-active" ${!existing || existing.is_active ? "checked" : ""}> Active Staff Account</label>
        <div style="display:flex;justify-content:flex-end;gap:10px;"><button type="button" class="btn btn-outline" id="btn-st-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save Staff User</button></div>
      </form>`, { narrow: true });

    document.getElementById("btn-st-cancel").addEventListener("click", closeModal);
    document.getElementById("form-staff-modal").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const payload = {
          fullName: document.getElementById("st-name").value.trim(),
          email: document.getElementById("st-email").value.trim(),
          phone: document.getElementById("st-phone").value.trim(),
          roleName: document.getElementById("st-role").value,
          password: document.getElementById("st-pass").value || undefined,
          isActive: document.getElementById("st-active").checked
        };
        await apiFetch(existing ? `/api/admin/staff/${existing.id}` : "/api/admin/staff", { method: existing ? "PUT" : "POST", body: JSON.stringify(payload) });
        closeModal();
        showAlert(`${title} saved.`, "success");
        loadSectionDirectory("admin-users", document.getElementById("section-search-input").value);
      } catch (err) {
        renderModalAlert(err.message);
      }
    });
  }

  async function renderGovernanceWorkspace(search = "") {
    const actionsHost = document.getElementById("section-actions-host");
    const subbar = document.getElementById("section-subbar-host");
    const tblHost = document.getElementById("section-table-host");
    const extraHost = document.getElementById("section-extra-host");
    subbar.innerHTML = "";

    const qs = search ? `?q=${encodeURIComponent(search)}` : "";
    const { staff, roles, permissions, rolePermissions, auditLogs } = await apiFetch(`/api/admin/governance${qs}`);
    document.getElementById("topbar-heading").textContent = "Admin Users & Permissions";
    document.getElementById("section-view-title").textContent = "Admin Staff, RBAC Permissions & Audit Log";
    document.getElementById("section-view-sub").textContent = "Super Admin governance: create staff accounts, configure role permissions, and inspect administrative activity";

    actionsHost.innerHTML = `<button type="button" class="btn btn-gold btn-sm" id="btn-add-staff">+ Add Admin Staff</button>`;
    document.getElementById("btn-add-staff").addEventListener("click", () => openStaffModal(null, roles));

    tblHost.innerHTML = `
      <h4 style="font-family:var(--font-display);font-size:21px;margin:0 0 8px;">Administrative Staff</h4>
      <table class="admin-table" style="margin-bottom:24px;">
        <thead><tr><th>Name</th><th>Email &amp; Phone</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead>
        <tbody>
          ${staff.map((u) => `
            <tr>
              <td><strong>${esc(u.full_name)}</strong></td>
              <td style="font-size:12px;">${esc(u.email)}<div class="panel-sub">${esc(u.phone)}</div></td>
              <td><span class="role-pill">${esc(u.role.replace(/_/g, " "))}</span></td>
              <td><span class="status-badge ${u.is_active ? "ok" : "bad"}">${u.is_active ? "Active" : "Disabled"}</span></td>
              <td>${fmtDate(u.created_at)}</td>
              <td><button type="button" class="btn btn-primary btn-sm btn-edit-staff" data-id="${u.id}">Edit</button></td>
            </tr>`).join("")}
        </tbody>
      </table>`;

    tblHost.querySelectorAll(".btn-edit-staff").forEach((b) => {
      b.addEventListener("click", () => openStaffModal(staff.find((u) => String(u.id) === b.dataset.id), roles));
    });

    const rolesHtml = roles.map((r) => {
      const assigned = new Set(rolePermissions[r.id] || []);
      const isSuper = r.name === "super_admin";
      return `
        <div class="panel-card" style="padding:14px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;">
            <div><strong>${esc(r.name.replace(/_/g, " ").toUpperCase())}</strong><div class="panel-sub">${esc(r.description || "")}</div></div>
            ${isSuper ? `<span class="status-badge ok">All Permissions</span>` : `<button type="button" class="btn btn-primary btn-sm btn-save-perms" data-role-id="${r.id}">Save Permissions</button>`}
          </div>
          <div class="checkbox-cluster" data-role-box="${r.id}">
            ${permissions.map((p) => `<label title="${esc(p.description || "")}"><input type="checkbox" value="${p.id}" ${assigned.has(p.id) ? "checked" : ""} ${isSuper ? "disabled" : ""}> ${esc(p.code)}</label>`).join("")}
          </div>
        </div>`;
    }).join("");

    extraHost.innerHTML = `
      <h4 style="font-family:var(--font-display);font-size:21px;margin:0 0 10px;">Role-Based Permissions Matrix</h4>
      <div style="display:grid;gap:12px;margin-bottom:24px;">${rolesHtml}</div>
      <h4 style="font-family:var(--font-display);font-size:21px;margin:0 0 10px;">Administrative Audit Trail</h4>
      <div class="table-scroll">
        ${buildMiniTable("", ["Date & Time", "Admin", "Role", "Action", "Target", "Details"], auditLogs.map((a) => [
          fmtDateTime(a.created_at),
          esc(a.admin_name),
          `<span class="status-badge">${esc(a.admin_role)}</span>`,
          `<strong>${esc(a.action)}</strong>`,
          esc(a.entity_id ? `${a.entity_type || ""} #${a.entity_id}` : a.entity_type || "—"),
          esc(a.details || "—")
        ]))}
      </div>`;

    extraHost.querySelectorAll(".btn-save-perms").forEach((b) => {
      b.addEventListener("click", async () => {
        const rid = b.dataset.roleId;
        const checked = Array.from(extraHost.querySelectorAll(`[data-role-box="${rid}"] input:checked`)).map((inp) => Number(inp.value));
        try {
          await apiFetch(`/api/admin/permissions/${rid}`, { method: "PUT", body: JSON.stringify({ permissionIds: checked }) });
          showAlert("Role permissions updated cleanly.", "success");
          renderGovernanceWorkspace(search);
        } catch (err) {
          showAlert(err.message);
        }
      });
    });
  }

  async function loadSectionDirectory(section, search = "") {
    clearAlert();
    try {
      if (section === "reports") return await renderReportsWorkspace();
      if (section === "settings") return await renderSettingsWorkspace();
      if (section === "promotions") return await renderPromotionsWorkspace();
      if (section === "admin-users") return await renderGovernanceWorkspace(search);

      let res;
      if (section === "orders") {
        ordersState.q = search;
        const qs = new URLSearchParams({
          q: ordersState.q || "",
          status: ordersState.status,
          dateFrom: ordersState.dateFrom,
          dateTo: ordersState.dateTo,
          sort: ordersState.sort,
          page: String(ordersState.page),
          limit: "12"
        });
        const apiOut = await apiFetch(`/api/admin/orders?${qs.toString()}`);
        res = {
          title: "Orders",
          subtitle: "Search, filter, update fulfillment stages, assign courier tracking, and process returns & refunds",
          rows: apiOut.orders,
          total: apiOut.total,
          page: apiOut.page,
          totalPages: apiOut.totalPages
        };
      } else if (section === "customers") {
        customersState.q = search;
        const qs = new URLSearchParams({
          q: customersState.q || "",
          status: customersState.status,
          sort: customersState.sort,
          page: String(customersState.page),
          limit: "12"
        });
        const apiOut = await apiFetch(`/api/admin/customers?${qs.toString()}`);
        res = {
          title: "Customers",
          subtitle: "Search collectors, inspect full order & return history, and manage account access",
          rows: apiOut.customers,
          total: apiOut.total,
          page: apiOut.page,
          totalPages: apiOut.totalPages
        };
      } else {
        const qs = search ? `?q=${encodeURIComponent(search)}` : "";
        res = await apiFetch(`/api/admin/section/${encodeURIComponent(section)}${qs}`);
      }
      lastSectionData = res;
      document.getElementById("topbar-heading").textContent = res.title;
      document.getElementById("section-view-title").textContent = res.title;
      document.getElementById("section-view-sub").textContent = res.subtitle || "";
      renderSectionUi(section, res);
    } catch (err) {
      if (err.message !== "Unauthorized") showAlert(err.message);
    }
  }

  function switchSection(section) {
    currentSection = section;
    currentSectionFilter = "all";
    document.querySelectorAll(".sidebar-link[data-section]").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.section === section);
    });

    const dashEl = document.getElementById("view-dashboard");
    const secEl = document.getElementById("view-section");

    if (section === "dashboard") {
      dashEl.style.display = "flex";
      secEl.style.display = "none";
      document.getElementById("topbar-heading").textContent = "Executive Dashboard";
      loadDashboard(currentRange);
    } else {
      dashEl.style.display = "none";
      secEl.style.display = "block";
      document.getElementById("section-search-input").value = "";
      loadSectionDirectory(section);
    }

    document.getElementById("admin-sidebar").classList.remove("open");
    document.getElementById("sidebar-backdrop").classList.remove("open");
  }

  // ---------- Global Events ----------
  document.querySelectorAll(".sidebar-link[data-section]").forEach((btn) => {
    btn.addEventListener("click", () => switchSection(btn.dataset.section));
  });
  document.querySelectorAll(".view-all-btn").forEach((btn) => {
    btn.addEventListener("click", () => switchSection(btn.dataset.section));
  });
  document.getElementById("btn-back-dashboard").addEventListener("click", () => switchSection("dashboard"));
  document.getElementById("btn-section-search").addEventListener("click", () => {
    loadSectionDirectory(currentSection, document.getElementById("section-search-input").value);
  });
  document.getElementById("section-search-input").addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      loadSectionDirectory(currentSection, e.target.value);
    }
  });
  document.querySelectorAll(".preset-btn[data-range]").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".preset-btn").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      loadDashboard(btn.dataset.range);
    });
  });
  document.getElementById("form-custom-range").addEventListener("submit", (e) => {
    e.preventDefault();
    const s = document.getElementById("filter-start-date").value;
    const end = document.getElementById("filter-end-date").value;
    document.querySelectorAll(".preset-btn").forEach((b) => b.classList.remove("active"));
    loadDashboard("custom", s, end);
  });
  document.getElementById("btn-admin-logout").addEventListener("click", clearSessionAndRedirect);

  const sidebar = document.getElementById("admin-sidebar");
  const backdrop = document.getElementById("sidebar-backdrop");
  document.getElementById("btn-open-sidebar").addEventListener("click", () => {
    sidebar.classList.add("open");
    backdrop.classList.add("open");
  });
  const closeDrawer = () => {
    sidebar.classList.remove("open");
    backdrop.classList.remove("open");
  };
  document.getElementById("btn-close-sidebar").addEventListener("click", closeDrawer);
  backdrop.addEventListener("click", closeDrawer);

  loadDashboard("30d");
})();
