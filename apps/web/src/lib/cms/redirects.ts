/**
 * Redirect destinations the website will follow: a path on this site, or a full https:// address
 * (editors may send an old address to, say, a LinkedIn profile). Anything else is ignored.
 */
export function isSafeRedirectTarget(to: string): boolean {
  if (to.startsWith("/")) {
    return !to.startsWith("//") && !to.startsWith("/\\");
  }

  try {
    return new URL(to).protocol === "https:";
  } catch {
    return false;
  }
}
