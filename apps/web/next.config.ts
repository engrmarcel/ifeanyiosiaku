import type { NextConfig } from "next";

/**
 * Same-origin architecture (ADR-003): the browser only ever talks to this website. Requests for the
 * API, the admin panel and the admin panel's assets are proxied to Laravel, so session cookies are
 * first-party and no CORS is needed.
 *
 * API_INTERNAL_URL is read at build time (rewrites are compiled into the build), so set it before
 * `npm run build` in each environment.
 */
const apiOrigin = (process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8110").replace(/\/$/, "");

const laravelPaths = [
  "/api/:path*",
  "/admin",
  "/admin/:path*",
  // Filament (admin panel) assets and Livewire endpoints.
  "/css/filament/:path*",
  "/js/filament/:path*",
  "/fonts/filament/:path*",
  "/filament/:path*",
  "/:livewire(livewire-[a-f0-9]+)/:path*",
];

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), payment=()" },
  { key: "X-Frame-Options", value: "DENY" },
  // Report-only until the policy is tuned against real third-party hosts (Milestone 8).
  {
    key: "Content-Security-Policy-Report-Only",
    value: [
      "default-src 'self'",
      "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://www.google.com https://www.gstatic.com",
      "style-src 'self' 'unsafe-inline'",
      "img-src 'self' data: blob:",
      "font-src 'self'",
      "connect-src 'self'",
      "frame-src https://challenges.cloudflare.com https://www.google.com",
      "worker-src 'self' blob:",
      "frame-ancestors 'none'",
      "base-uri 'self'",
      "form-action 'self'",
    ].join("; "),
  },
];

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  poweredByHeader: false,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  async rewrites() {
    return {
      beforeFiles: laravelPaths.map((source) => ({
        source,
        destination: `${apiOrigin}${source.replace("(livewire-[a-f0-9]+)", "")}`,
      })),
      afterFiles: [],
      fallback: [],
    };
  },
  async headers() {
    return [
      { source: "/:path*", headers: securityHeaders },
      {
        // Private areas must never be cached by shared caches or indexed.
        source: "/account/:path*",
        headers: [
          { key: "Cache-Control", value: "private, no-store" },
          { key: "X-Robots-Tag", value: "noindex, nofollow" },
        ],
      },
    ];
  },
};

export default nextConfig;
