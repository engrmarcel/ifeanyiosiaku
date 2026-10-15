import { cacheLife, cacheTag } from "next/cache";
import { serverGet } from "@/lib/api/server";
import { isSafeRedirectTarget } from "./redirects";
import type { CmsPage } from "./schemas";

export type PageLookup =
  | { kind: "page"; page: CmsPage }
  | { kind: "redirect"; to: string; status: 301 | 302 }
  | { kind: "missing" };

/** Cache tag for a public path. Laravel asks the website to refresh it when the page changes. */
export function pageTag(path: string): string {
  return `page:${path}`;
}

type ErrorBody = { error?: { code?: string; redirect?: { to: string; status: number } } };

/**
 * Published page for a public path ("/" or "/about"). Cached until the CMS reports a change; an
 * hourly lifetime is a safety net in case a refresh request is ever missed.
 */
export async function getPublishedPage(path: string): Promise<PageLookup> {
  "use cache";
  cacheTag(pageTag(path), "pages");
  cacheLife("hours");

  const endpoint = path === "/" ? "/api/v1/pages/home" : `/api/v1/pages/${path.replace(/^\/+/, "")}`;
  const { status, body } = await serverGet<{ data: CmsPage } & ErrorBody>(endpoint);

  if (status === 200 && body?.data) return { kind: "page", page: body.data };

  const redirect = body?.error?.redirect;
  if (status === 404 && redirect && isSafeRedirectTarget(redirect.to)) {
    return { kind: "redirect", to: redirect.to, status: redirect.status === 302 ? 302 : 301 };
  }

  return { kind: "missing" };
}

/** Draft shown through a signed preview link. Never cached. */
export async function getPreviewPage(token: string): Promise<CmsPage | null> {
  const { status, body } = await serverGet<{ data: CmsPage }>(`/api/v1/preview/page?token=${encodeURIComponent(token)}`);
  return status === 200 && body?.data ? body.data : null;
}
