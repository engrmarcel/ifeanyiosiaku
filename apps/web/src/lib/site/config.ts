import { cacheLife, cacheTag } from "next/cache";
import { serverGet } from "@/lib/api/server";
import { FALLBACK_SITE_CONFIG, SITE_TAG, siteConfigSchema, type SiteConfig } from "./shared";

export * from "./shared";

/**
 * Navigation, footer, social links, theme and identity for every page. Cached until the admin
 * changes them. If the API is unreachable (for example during a build), the defaults are used
 * and retried within a minute instead of being kept for hours.
 */
export async function getSiteConfig(): Promise<SiteConfig> {
  "use cache";
  cacheTag(SITE_TAG);

  try {
    const { status, body } = await serverGet<{ data: unknown }>("/api/v1/site");
    const parsed = status === 200 ? siteConfigSchema.safeParse(body?.data) : null;

    if (parsed?.success) {
      cacheLife("hours");
      return parsed.data;
    }

    console.error("[site] Unexpected site configuration response; using defaults.", status);
  } catch (error) {
    console.error("[site] Site configuration could not be loaded; using defaults.", error);
  }

  cacheLife("minutes");
  return FALLBACK_SITE_CONFIG;
}
