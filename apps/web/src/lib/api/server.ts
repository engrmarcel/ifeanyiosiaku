/**
 * Server-side calls from the website to the Laravel API over the internal network (never exposed to
 * the browser). Only public, unauthenticated endpoints are called this way.
 */

const API_ORIGIN = (process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8110").replace(/\/$/, "");

export type ServerResponse<T> = { status: number; body: T | null };

export async function serverGet<T>(path: string): Promise<ServerResponse<T>> {
  const response = await fetch(`${API_ORIGIN}${path}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
    // Never let a slow API hang a page render or a build.
    signal: AbortSignal.timeout(8000),
  });

  const text = await response.text();
  let body: T | null = null;

  try {
    body = text ? (JSON.parse(text) as T) : null;
  } catch {
    body = null;
  }

  if (response.status >= 500) {
    // Not cached by "use cache" because it throws; the next request retries.
    throw new Error(`API ${path} failed with ${response.status}`);
  }

  return { status: response.status, body };
}
