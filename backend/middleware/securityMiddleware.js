// Security HTTP Headers & Zero-Dependency In-Memory Rate Limiters (Phase 10)

function securityHeaders(_req, res, next) {
  res.setHeader("X-Content-Type-Options", "nosniff");
  res.setHeader("X-Frame-Options", "DENY");
  res.setHeader("Referrer-Policy", "strict-origin-when-cross-origin");
  res.setHeader("X-XSS-Protection", "1; mode=block");
  res.setHeader("Permissions-Policy", "camera=(), microphone=(), geolocation=()");
  res.setHeader(
    "Content-Security-Policy",
    [
      "default-src 'self'",
      "script-src 'self' 'unsafe-inline'",
      "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
      "font-src 'self' https://fonts.gstatic.com data:",
      "img-src 'self' data: blob:",
      "connect-src 'self'"
    ].join("; ")
  );
  next();
}

function createRateLimiter({ windowMs = 60_000, max = 120, keyPrefix = "api", message = "Too many requests. Please slow down and try again shortly." } = {}) {
  const buckets = new Map();

  // Periodic sweep keeps memory bounded cleanly in long-running processes
  const timer = setInterval(() => {
    const now = Date.now();
    for (const [key, entry] of buckets.entries()) {
      if (entry.resetAt <= now) buckets.delete(key);
    }
  }, Math.max(30_000, windowMs));
  if (typeof timer.unref === "function") timer.unref();

  return (req, res, next) => {
    const now = Date.now();
    const ip = req.ip || req.socket?.remoteAddress || "local";
    const key = `${keyPrefix}:${ip}`;
    let entry = buckets.get(key);

    if (!entry || entry.resetAt <= now) {
      entry = { count: 0, resetAt: now + windowMs };
      buckets.set(key, entry);
    }
    entry.count += 1;

    res.setHeader("X-RateLimit-Limit", String(max));
    res.setHeader("X-RateLimit-Remaining", String(Math.max(0, max - entry.count)));

    if (entry.count > max) {
      const retryAfterSec = Math.max(1, Math.ceil((entry.resetAt - now) / 1000));
      res.setHeader("Retry-After", String(retryAfterSec));
      return res.status(429).json({ success: false, message });
    }
    next();
  };
}

module.exports = { securityHeaders, createRateLimiter };

