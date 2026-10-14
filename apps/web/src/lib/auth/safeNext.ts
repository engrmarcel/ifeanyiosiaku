/**
 * Validates a post-login destination. Only same-site relative paths are allowed, which prevents
 * open redirects such as ?next=https://evil.example or ?next=//evil.example.
 */
export function safeNext(next: string | null | undefined, fallback = "/account"): string {
  if (!next) return fallback;
  if (!next.startsWith("/") || next.startsWith("//") || next.startsWith("/\\")) return fallback;
  if (/[\u0000-\u001f]/.test(next)) return fallback;
  return next;
}

/** Paths served by Laravel through the proxy need a full page load rather than client navigation. */
export function isServerRendered(path: string): boolean {
  return path === "/admin" || path.startsWith("/admin/") || path.startsWith("/api/");
}
