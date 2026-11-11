const path = require("path");
const express = require("express");
const cors = require("cors");
require("dotenv").config();

const catalogRoutes = require("./routes/catalogRoutes");
const authRoutes = require("./routes/authRoutes");
const checkoutRoutes = require("./routes/checkoutRoutes");
const orderLifecycleRoutes = require("./routes/orderLifecycleRoutes");
const adminRoutes = require("./routes/adminRoutes");
const { securityHeaders, createRateLimiter } = require("./middleware/securityMiddleware");

const app = express();
const PORT = Number(process.env.PORT || 5000);
const frontendDir = path.resolve(__dirname, "..", "frontend");
const uploadsDir = path.resolve(__dirname, "uploads");
const chartJsPath = path.resolve(__dirname, "..", "node_modules", "chart.js", "dist", "chart.umd.js");

app.disable("x-powered-by");
app.use(securityHeaders);
app.use(cors({ origin: process.env.CORS_ORIGIN ? process.env.CORS_ORIGIN.split(",").map((s) => s.trim()) : true }));
app.use(express.json({ limit: "1mb" }));

// Static asset caching (7-day cache for immutable-style media & vendored Chart.js; ETag for HTML/JS/CSS)
app.use("/uploads", express.static(uploadsDir, { maxAge: "7d", etag: true }));
app.use("/assets/images", express.static(path.join(frontendDir, "assets", "images"), { maxAge: "7d", etag: true }));
app.use("/assets/icons", express.static(path.join(frontendDir, "assets", "icons"), { maxAge: "7d", etag: true }));
app.get("/admin/vendor/chart.umd.js", (_req, res) => res.sendFile(chartJsPath, { maxAge: "7d" }));
app.use(express.static(frontendDir, { etag: true }));

app.use("/api", createRateLimiter({ windowMs: 60_000, max: Number(process.env.RATE_LIMIT_API_MAX || 400), keyPrefix: "api" }));

app.get("/api/health", (_req, res) => {
  res.json({ status: "ok", phase: 10, service: "VALENOR Horology API" });
});

app.use("/api", catalogRoutes);
app.use("/api", authRoutes);
app.use("/api", checkoutRoutes);
app.use("/api", orderLifecycleRoutes);
app.use("/api", adminRoutes);

app.use((req, res, next) => {
  if (req.path.startsWith("/api/")) {
    return res.status(404).json({ success: false, message: "API route not found" });
  }
  next();
});

app.use((err, _req, res, _next) => {
  if (err && err.type === "entity.parse.failed") {
    return res.status(400).json({ success: false, message: "Invalid JSON payload." });
  }
  console.error(err);
  res.status(err?.status || 500).json({ success: false, message: err?.status ? err.message : "Internal server error" });
});

if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`VALENOR server listening on http://localhost:${PORT}`);
  });
}

module.exports = app;

