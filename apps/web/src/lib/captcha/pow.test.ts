import { createHash } from "node:crypto";
import { describe, expect, it } from "vitest";
import { hasLeadingZeroBits, solve } from "./pow";

describe("hasLeadingZeroBits", () => {
  it("checks whole and partial bytes", () => {
    expect(hasLeadingZeroBits(new Uint8Array([0, 0, 0xff]), 16)).toBe(true);
    expect(hasLeadingZeroBits(new Uint8Array([0, 1]), 16)).toBe(false);
    expect(hasLeadingZeroBits(new Uint8Array([0, 0b0001_0000]), 11)).toBe(true);
    expect(hasLeadingZeroBits(new Uint8Array([0, 0b0010_0000]), 11)).toBe(false);
  });
});

describe("solve", () => {
  it("finds a hex nonce the server will accept", async () => {
    const challenge = "eyJpZCI6InRlc3QifQ.signature";
    const nonce = await solve(challenge, 10);

    expect(nonce).toMatch(/^[0-9a-f]+$/);
    const hash = createHash("sha256").update(`${challenge}:${nonce}`).digest();
    expect(hasLeadingZeroBits(new Uint8Array(hash), 10)).toBe(true);
  });

  it("can be aborted", async () => {
    const controller = new AbortController();
    controller.abort();
    await expect(solve("x", 30, { signal: controller.signal })).rejects.toThrow("Aborted");
  });
});
