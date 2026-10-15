import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("next/cache", () => ({ cacheTag: vi.fn(), cacheLife: vi.fn() }));
const serverGet = vi.fn();
vi.mock("@/lib/api/server", () => ({ serverGet }));

const { getPublishedPage } = await import("./pages");

describe("getPublishedPage", () => {
  afterEach(() => serverGet.mockReset());

  it("loads the homepage from /pages/home and other pages by slug", async () => {
    serverGet.mockResolvedValue({ status: 200, body: { data: { title: "Home" } } });

    await getPublishedPage("/");
    await getPublishedPage("/services/training");

    expect(serverGet).toHaveBeenNthCalledWith(1, "/api/v1/pages/home");
    expect(serverGet).toHaveBeenNthCalledWith(2, "/api/v1/pages/services/training");
  });

  it("follows CMS redirects only to same-site paths", async () => {
    serverGet.mockResolvedValueOnce({ status: 404, body: { error: { redirect: { to: "/about-me", status: 301 } } } });
    expect(await getPublishedPage("/about")).toEqual({ kind: "redirect", to: "/about-me", status: 301 });

    serverGet.mockResolvedValueOnce({ status: 404, body: { error: { redirect: { to: "//evil.example", status: 301 } } } });
    expect(await getPublishedPage("/about")).toEqual({ kind: "missing" });
  });

  it("reports missing pages", async () => {
    serverGet.mockResolvedValue({ status: 404, body: { error: { code: "not_found" } } });
    expect(await getPublishedPage("/nope")).toEqual({ kind: "missing" });
  });
});
