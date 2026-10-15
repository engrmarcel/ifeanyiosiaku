import { NextResponse, type NextRequest } from "next/server";
import { isSafeRedirectTarget } from "@/lib/cms/redirects";

const API_ORIGIN = (process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8110").replace(/\/$/, "");

type Resolution = { status: "page" } | { status: "redirect"; to: string; code: number } | { status: "missing" };

/**
 * Before a CMS page renders, check whether its address exists, so the response can carry a real
 * HTTP status: 301 for moved pages and 404 for missing ones (once rendering streams, the status can
 * no longer change). The lookup is cached by the API and cleared on publish. If the API cannot be
 * reached, the request continues and the page handles it.
 */
export async function proxy(request: NextRequest) {
  const path = request.nextUrl.pathname.replace(/\/+$/, "") || "/";

  let resolution: Resolution;
  try {
    const response = await fetch(`${API_ORIGIN}/api/v1/page-resolve?path=${encodeURIComponent(path)}`, {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(2500),
    });
    if (!response.ok) return NextResponse.next();
    resolution = ((await response.json()) as { data: Resolution }).data;
  } catch {
    return NextResponse.next();
  }

  if (resolution.status === "redirect" && isSafeRedirectTarget(resolution.to)) {
    return NextResponse.redirect(new URL(resolution.to, request.url), resolution.code === 302 ? 302 : 301);
  }

  if (resolution.status === "missing") {
    return NextResponse.rewrite(new URL("/page-not-found", request.url));
  }

  return NextResponse.next();
}

export const config = {
  // Only addresses that could be CMS pages: everything except the website's own routes, the
  // Laravel-served paths, framework assets and files with an extension.
  matcher: [
    "/((?!(?:_next|api|admin|media|css|js|fonts|filament|brand|internal|preview|page-not-found|healthz|login|register|account|two-factor|verify-email|forgot-password|reset-password)(?:/|$))(?!livewire-)(?!.*\\.[a-zA-Z0-9]+$).+)",
  ],
};
