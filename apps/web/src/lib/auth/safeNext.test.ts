import { describe, expect, it } from "vitest";
import { isServerRendered, safeNext } from "./safeNext";

describe("safeNext", () => {
  it.each([
    ["/account", "/account"],
    ["/admin", "/admin"],
    ["/courses/intro?x=1", "/courses/intro?x=1"],
  ])("allows same-site path %s", (input, expected) => {
    expect(safeNext(input)).toBe(expected);
  });

  it.each(["https://evil.example", "//evil.example", "/\\evil.example", "javascript:alert(1)", "/ok\nSet-Cookie:x", ""])(
    "rejects unsafe destination %j",
    (input) => {
      expect(safeNext(input)).toBe("/account");
    },
  );

  it("identifies Laravel-served paths that need a full page load", () => {
    expect(isServerRendered("/admin")).toBe(true);
    expect(isServerRendered("/admin/users")).toBe(true);
    expect(isServerRendered("/administrator")).toBe(false);
    expect(isServerRendered("/account")).toBe(false);
  });
});
