/** The public origin of the website, used for absolute links in metadata, sitemaps and structured data. */
export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3100").replace(/\/$/, "");

/** Absolute URL for a site path such as "/projects/x" or "/media/a.webp". */
export function absoluteUrl(path: string): string {
  return new URL(path, `${SITE_URL}/`).toString();
}
