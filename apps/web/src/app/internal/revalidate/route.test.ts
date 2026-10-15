import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const revalidateTag = vi.fn();
vi.mock("next/cache", () => ({ revalidateTag, cacheTag: vi.fn(), cacheLife: vi.fn() }));

const SECRET = "s".repeat(64);

async function call(headers: Record<string, string>, body: unknown) {
  const { POST } = await import("./route");
  return POST(new Request("http://localhost/internal/revalidate", { method: "POST", headers: { "content-type": "application/json", ...headers }, body: JSON.stringify(body) }));
}

describe("POST /internal/revalidate", () => {
  beforeEach(() => vi.stubEnv("WEB_REVALIDATE_SECRET", SECRET));
  afterEach(() => {
    vi.unstubAllEnvs();
    revalidateTag.mockReset();
  });

  it("refuses requests without the shared secret", async () => {
    const response = await call({}, { paths: ["/about"] });
    expect(response.status).toBe(401);
    expect(revalidateTag).not.toHaveBeenCalled();
  });

  it("refuses a wrong secret", async () => {
    const response = await call({ "x-revalidate-secret": "x".repeat(64) }, { paths: ["/about"] });
    expect(response.status).toBe(401);
  });

  it("refuses everything when no secret is configured", async () => {
    vi.stubEnv("WEB_REVALIDATE_SECRET", "");
    const response = await call({ "x-revalidate-secret": "" }, { paths: ["/about"] });
    expect(response.status).toBe(401);
  });

  it("refreshes only well-formed paths", async () => {
    const response = await call({ "x-revalidate-secret": SECRET }, { paths: ["/about", "/", "https://evil.example", 42] });

    expect(response.status).toBe(200);
    expect(await response.json()).toEqual({ revalidated: ["/about", "/"] });
    expect(revalidateTag).toHaveBeenCalledWith("page:/about", { expire: 0 });
    expect(revalidateTag).toHaveBeenCalledWith("page:/", { expire: 0 });
    expect(revalidateTag).toHaveBeenCalledWith("sitemap", { expire: 0 });
  });
});
