import { describe, expect, it } from "vitest";
import { rememberClosed, rememberShown, shouldShow, visitorId, type Frequency, type StorageLike } from "./frequency";

function memory(): StorageLike & { data: Map<string, string> } {
  const data = new Map<string, string>();
  return { data, getItem: (k) => data.get(k) ?? null, setItem: (k, v) => void data.set(k, v) };
}

const ann = (frequency: Frequency, version = "v1") => ({ id: "01abc", version, frequency });

describe("announcement frequency", () => {
  it("'once' keeps showing until the visitor closes it, and returns for a new version", () => {
    const stores = { local: memory(), session: memory() };

    rememberShown(ann("once"), stores);
    expect(shouldShow(ann("once"), stores)).toBe(true);

    rememberClosed(ann("once"), stores);
    expect(shouldShow(ann("once"), stores)).toBe(false);
    expect(shouldShow(ann("once", "v2"), stores)).toBe(true);
  });

  it("'session' shows once per visit", () => {
    const stores = { local: memory(), session: memory() };

    expect(shouldShow(ann("session"), stores)).toBe(true);
    rememberShown(ann("session"), stores);
    expect(shouldShow(ann("session"), stores)).toBe(false);
    expect(shouldShow(ann("session"), { ...stores, session: memory() })).toBe(true);
  });

  it("'daily' waits 24 hours", () => {
    const stores = { local: memory(), session: memory() };
    const now = Date.UTC(2026, 9, 16, 12);

    rememberShown(ann("daily"), stores, now);
    expect(shouldShow(ann("daily"), stores, now + 23 * 3600_000)).toBe(false);
    expect(shouldShow(ann("daily"), stores, now + 24 * 3600_000)).toBe(true);
  });

  it("'always' shows on every page view", () => {
    const stores = { local: memory(), session: memory() };
    rememberClosed(ann("always"), stores);
    expect(shouldShow(ann("always"), stores)).toBe(true);
  });

  it("copes with blocked storage", () => {
    const broken: StorageLike = {
      getItem: () => {
        throw new Error("blocked");
      },
      setItem: () => {
        throw new Error("blocked");
      },
    };

    expect(shouldShow(ann("once"), { local: broken, session: broken })).toBe(true);
    expect(() => rememberClosed(ann("once"), { local: broken, session: broken })).not.toThrow();
    expect(visitorId(broken)).toMatch(/^[A-Za-z0-9-]{16,64}$/);
  });

  it("keeps one random visitor id per browser", () => {
    const local = memory();
    const first = visitorId(local);

    expect(first).toMatch(/^[A-Za-z0-9-]{16,64}$/);
    expect(visitorId(local)).toBe(first);
  });
});
