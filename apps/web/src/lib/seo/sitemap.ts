import { cacheLife, cacheTag } from "next/cache";
import { z } from "zod";
import { serverGet } from "@/lib/api/server";

const entriesSchema = z.object({ entries: z.array(z.object({ path: z.string().startsWith("/"), lastModified: z.string().nullable() })) });

export type SitemapEntry = z.infer<typeof entriesSchema>["entries"][number];

/** Indexable public addresses. Refreshed on every publish; retried within a minute if the API is down. */
export async function getSitemapEntries(): Promise<SitemapEntry[]> {
  "use cache";
  cacheTag("sitemap");

  try {
    const { status, body } = await serverGet<{ data: unknown }>("/api/v1/sitemap");
    const parsed = status === 200 ? entriesSchema.safeParse(body?.data) : null;

    if (parsed?.success) {
      cacheLife("hours");
      return parsed.data.entries;
    }
  } catch (error) {
    console.error("[seo] Sitemap entries could not be loaded.", error);
  }

  cacheLife("minutes");
  return [];
}
