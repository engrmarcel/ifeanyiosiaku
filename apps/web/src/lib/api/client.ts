/**
 * Browser API client. All calls are same-origin (proxied to Laravel), carry the session cookie, and
 * send the CSRF token Laravel places in the XSRF-TOKEN cookie.
 */

export type ApiErrorBody = {
  code: string;
  message: string;
  fields?: Record<string, string[]>;
  reason?: string;
  request_id?: string;
};

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fields: Record<string, string[]>;
  readonly reason?: string;
  readonly requestId?: string;

  constructor(status: number, body: Partial<ApiErrorBody>) {
    super(body.message ?? "Request failed.");
    this.name = "ApiError";
    this.status = status;
    this.code = body.code ?? "http_error";
    this.fields = body.fields ?? {};
    this.reason = body.reason;
    this.requestId = body.request_id;
  }

  /** First validation message for a field, if any. */
  field(name: string): string | undefined {
    return this.fields[name]?.[0];
  }
}

type RequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  signal?: AbortSignal;
};

const CSRF_COOKIE = "XSRF-TOKEN";
const SAFE_METHODS = new Set(["GET", "HEAD", "OPTIONS"]);

export function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.split("; ").find((part) => part.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

async function ensureCsrfCookie(): Promise<void> {
  if (readCookie(CSRF_COOKIE)) return;
  await fetch("/api/v1/csrf-cookie", { credentials: "same-origin", headers: { Accept: "application/json" } });
}

async function send(path: string, { method = "GET", body, signal }: RequestOptions): Promise<Response> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  if (body !== undefined) headers["Content-Type"] = "application/json";

  if (!SAFE_METHODS.has(method)) {
    await ensureCsrfCookie();
    const token = readCookie(CSRF_COOKIE);
    if (token) headers["X-XSRF-TOKEN"] = token;
  }

  return fetch(path, {
    method,
    headers,
    credentials: "same-origin",
    body: body === undefined ? undefined : JSON.stringify(body),
    signal,
  });
}

async function parse<T>(response: Response): Promise<T> {
  if (response.status === 204) return undefined as T;

  const text = await response.text();
  const json: unknown = text ? safeJson(text) : null;

  if (!response.ok) {
    const errorBody =
      json && typeof json === "object" && "error" in json
        ? (json as { error: Partial<ApiErrorBody> }).error
        : // Fortify validation responses use Laravel's default {message, errors} shape.
          json && typeof json === "object" && "errors" in json
          ? {
              code: "validation_failed",
              message: (json as { message?: string }).message,
              fields: (json as { errors: Record<string, string[]> }).errors,
            }
          : { message: response.statusText };
    throw new ApiError(response.status, errorBody);
  }

  return json as T;
}

function safeJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

/**
 * Calls the API. A 419 (expired CSRF token) is retried once with a fresh token, which happens
 * naturally after the session rotates (for example right after login).
 */
export async function api<T = unknown>(path: string, options: RequestOptions = {}): Promise<T> {
  let response = await send(path, options);

  if (response.status === 419) {
    await fetch("/api/v1/csrf-cookie", { credentials: "same-origin", headers: { Accept: "application/json" } });
    response = await send(path, options);
  }

  return parse<T>(response);
}
