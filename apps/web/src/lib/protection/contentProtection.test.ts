import { afterEach, describe, expect, it } from "vitest";
import { installContentProtection, isBlockedShortcut, isEditable, PROTECTION_CLASS } from "./contentProtection";

const key = (k: string, mods: Partial<Record<"ctrlKey" | "metaKey" | "shiftKey" | "altKey", boolean>> = {}) => ({
  key: k,
  ctrlKey: false,
  metaKey: false,
  shiftKey: false,
  altKey: false,
  ...mods,
});

describe("isBlockedShortcut", () => {
  it.each([
    ["F12", key("F12")],
    ["Ctrl+Shift+I", key("I", { ctrlKey: true, shiftKey: true })],
    ["Ctrl+Shift+J", key("J", { ctrlKey: true, shiftKey: true })],
    ["Ctrl+Shift+C", key("C", { ctrlKey: true, shiftKey: true })],
    ["Ctrl+U", key("u", { ctrlKey: true })],
    ["Ctrl+S", key("s", { ctrlKey: true })],
    ["Cmd+Opt+I", key("i", { metaKey: true, altKey: true })],
    ["Cmd+Opt+U", key("u", { metaKey: true, altKey: true })],
    ["Cmd+S", key("s", { metaKey: true })],
  ])("blocks %s", (_, event) => {
    expect(isBlockedShortcut(event)).toBe(true);
  });

  it.each([
    ["Ctrl+C (copy text)", key("c", { ctrlKey: true })],
    ["Ctrl+V (paste)", key("v", { ctrlKey: true })],
    ["Ctrl+A", key("a", { ctrlKey: true })],
    ["Tab", key("Tab")],
    ["plain letters", key("i")],
    ["Shift+S", key("S", { shiftKey: true })],
  ])("allows %s", (_, event) => {
    expect(isBlockedShortcut(event)).toBe(false);
  });
});

describe("installContentProtection", () => {
  let remove: (() => void) | undefined;

  afterEach(() => {
    remove?.();
    document.body.innerHTML = "";
  });

  it("blocks the context menu except in form fields", () => {
    document.body.innerHTML = '<img alt="x" src="data:,"><input id="field">';
    remove = installContentProtection();

    const onImage = new MouseEvent("contextmenu", { bubbles: true, cancelable: true });
    document.querySelector("img")!.dispatchEvent(onImage);
    expect(onImage.defaultPrevented).toBe(true);

    const onInput = new MouseEvent("contextmenu", { bubbles: true, cancelable: true });
    document.getElementById("field")!.dispatchEvent(onInput);
    expect(onInput.defaultPrevented).toBe(false);
    expect(isEditable(document.getElementById("field"))).toBe(true);
  });

  it("blocks dragging images and devtools shortcuts, and can be removed", () => {
    document.body.innerHTML = '<img alt="x" src="data:,">';
    remove = installContentProtection();
    expect(document.documentElement.classList.contains(PROTECTION_CLASS)).toBe(true);

    const drag = new Event("dragstart", { bubbles: true, cancelable: true });
    document.querySelector("img")!.dispatchEvent(drag);
    expect(drag.defaultPrevented).toBe(true);

    const f12 = new KeyboardEvent("keydown", { key: "F12", bubbles: true, cancelable: true });
    document.body.dispatchEvent(f12);
    expect(f12.defaultPrevented).toBe(true);

    remove();
    remove = undefined;
    expect(document.documentElement.classList.contains(PROTECTION_CLASS)).toBe(false);

    const afterRemoval = new MouseEvent("contextmenu", { bubbles: true, cancelable: true });
    document.querySelector("img")!.dispatchEvent(afterRemoval);
    expect(afterRemoval.defaultPrevented).toBe(false);
  });
});
