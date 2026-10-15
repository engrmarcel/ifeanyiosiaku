import { revalidateTag } from "next/cache";
import { timingSafeEqual } from "node:crypto";
import { pageTag } from "@/lib/cms/pages";
import { SITE_TAG } from "@/lib/site/shared";

/** Shared caches Laravel may refresh by name, besides individual page paths. */
const ALLOWED_TAGS = new Set([SITE_TAG]);

/**
 * Called by Laravel when published content changes, so visitors see it immediately.
 * Protected by a shared secret (WEB_REVALIDATE_SECRET) sent in the X-Revalidate-Secret header.
 */
export async function POST(request: Request) {
  const secret = process.env.WEB_REVALIDATE_SECRET ?? "";
  const provided = request.headers.get("x-revalidate-secret") ?? "";

  const valid =
    secret.length >= 32 &&
    provided.length === secret.length &&
    timingSafeEqual(Buffer.from(provided), Buffer.from(secret));

  if (!valid) {
    return Response.json({ error: "unauthorised" }, { status: 401 });
  }

  const body = (await request.json().catch(() => null)) as { paths?: unknown; tags?: unknown } | null;
  const paths = Array.isArray(body?.paths)
    ? body.paths.filter((p): p is string => typeof p === "string" && p.startsWith("/") && p.length <= 300).slice(0, 100)
    : [];

  const tags = Array.isArray(body?.tags) ? body.tags.filter((t): t is string => typeof t === "string" && ALLOWED_TAGS.has(t)) : [];

  for (const tag of tags) {
    revalidateTag(tag, { expire: 0 });
  }
  for (const path of paths) {
    revalidateTag(pageTag(path), { expire: 0 });
  }
  revalidateTag("sitemap", { expire: 0 });

  return Response.json({ revalidated: paths, tags });
}
