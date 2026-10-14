import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api, ApiError } from "./client";

function jsonResponse(status: number, body: unknown): Response {
  return new Response(body === null ? null : JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

describe("api client", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "XSRF-TOKEN=token%3D123; path=/";
  });

  afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  });

  it("sends same-origin credentials and the decoded CSRF token on writes", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { ok: true }));

    await api("/api/v1/thing", { method: "POST", body: { a: 1 } });

    const [, init] = fetchMock.mock.calls[0];
    expect(init.credentials).toBe("same-origin");
    expect(init.headers["X-XSRF-TOKEN"]).toBe("token=123");
    expect(init.headers.Accept).toBe("application/json");
    expect(init.body).toBe(JSON.stringify({ a: 1 }));
  });

  it("does not send the CSRF header on reads", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: 1 }));

    await api("/api/v1/me");

    expect(fetchMock.mock.calls[0][1].headers["X-XSRF-TOKEN"]).toBeUndefined();
  });

  it("maps the API error envelope to ApiError", async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse(422, {
        error: { code: "validation_failed", message: "Invalid", fields: { email: ["Taken"] }, request_id: "r-1" },
      }),
    );

    const error = (await api("/api/v1/x", { method: "POST" }).catch((e: unknown) => e)) as ApiError;

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(422);
    expect(error.code).toBe("validation_failed");
    expect(error.field("email")).toBe("Taken");
    expect(error.requestId).toBe("r-1");
  });

  it("refreshes the CSRF cookie and retries once after a 419", async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(419, { error: { code: "csrf_token_mismatch", message: "Expired" } }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(jsonResponse(200, { ok: true }));

    await expect(api("/api/v1/x", { method: "POST" })).resolves.toEqual({ ok: true });
    expect(fetchMock.mock.calls[1][0]).toBe("/api/v1/csrf-cookie");
    expect(fetchMock).toHaveBeenCalledTimes(3);
  });

  it("returns undefined for 204 responses", async () => {
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));
    await expect(api("/api/v1/x", { method: "DELETE" })).resolves.toBeUndefined();
  });
});
