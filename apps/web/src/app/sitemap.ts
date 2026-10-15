import type { MetadataRoute } from "next";
import { getSitemapEntries } from "@/lib/seo/sitemap";
import { absoluteUrl } from "@/lib/site/url";

/** /sitemap.xml: published pages and case studies that allow indexing (empty when indexing is off). */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const entries = await getSitemapEntries();

  return entries.map((entry) => ({
    url: absoluteUrl(entry.path),
    lastModified: entry.lastModified ?? undefined,
  }));
}
