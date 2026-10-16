/**
 * Link helpers safe for browser components. Kept apart from the site-config schemas so the header
 * does not pull the schema library into every visitor's download.
 */

/** Whether a link points outside this website. */
export function isExternal(url: string): boolean {
  return /^(https?:|mailto:)/i.test(url);
}
