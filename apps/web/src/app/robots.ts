import type { MetadataRoute } from "next";
import { getSiteConfig } from "@/lib/site/config";
import { SITE_URL } from "@/lib/site/url";

/** /robots.txt: keeps private areas out of search results; blocks everything when indexing is off. */
export default async function robots(): Promise<MetadataRoute.Robots> {
  const site = await getSiteConfig();

  if (!site.indexing) {
    return { rules: { userAgent: "*", disallow: "/" } };
  }

  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: [
        "/admin",
        "/api/",
        "/account",
        "/preview",
        "/internal/",
        "/login",
        "/register",
        "/forgot-password",
        "/reset-password",
        "/two-factor",
        "/verify-email",
        "/page-not-found",
      ],
    },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
