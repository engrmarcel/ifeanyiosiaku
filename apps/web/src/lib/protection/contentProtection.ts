/**
 * Content protection (ADR-024): discourages saving images, right-click menus and developer-tool
 * shortcuts on the public website. It is a deterrent only. Browsers always keep their own menu
 * entries, screenshots work, and files remain visible in network tools, so nothing here is relied
 * upon for security. Paid files are protected server-side (signed URLs, entitlements).
 */

export const PROTECTION_CLASS = "io-protect";

/** Elements where the context menu stays available (paste, spell-check, autofill). */
export function isEditable(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) return false;
  const editable = target.closest("input, textarea, select, [contenteditable=''], [contenteditable='true']");
  return editable !== null;
}

type KeyLike = Pick<KeyboardEvent, "key" | "ctrlKey" | "metaKey" | "shiftKey" | "altKey">;

/**
 * Developer-tools, view-source and save-page shortcuts on Windows/Linux and macOS:
 * F12 · Ctrl+Shift+I/J/C/K · Ctrl+U · Ctrl+S · ⌘⌥I/J/C · ⌘⌥U · ⌘U · ⌘S
 */
export function isBlockedShortcut(event: KeyLike): boolean {
  // Browser autofill sends keydown events without a key; those are never shortcuts.
  if (typeof event.key !== "string") return false;
  const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;

  if (key === "F12") return true;

  const mod = event.ctrlKey || event.metaKey;
  if (!mod) return false;

  if (event.shiftKey && ["i", "j", "c", "k"].includes(key)) return true;
  if (event.metaKey && event.altKey && ["i", "j", "c", "u"].includes(key)) return true;
  if (!event.shiftKey && !event.altKey && (key === "u" || key === "s")) return true;

  return false;
}

/** Installs the deterrents on a document and returns a function that removes them. */
export function installContentProtection(doc: Document = document): () => void {
  const onContextMenu = (event: MouseEvent) => {
    if (!isEditable(event.target)) event.preventDefault();
  };

  const onKeyDown = (event: KeyboardEvent) => {
    if (isBlockedShortcut(event)) {
      event.preventDefault();
      event.stopPropagation();
    }
  };

  const onDragStart = (event: DragEvent) => {
    if (event.target instanceof HTMLImageElement || event.target instanceof HTMLVideoElement) {
      event.preventDefault();
    }
  };

  doc.addEventListener("contextmenu", onContextMenu);
  doc.addEventListener("keydown", onKeyDown, { capture: true });
  doc.addEventListener("dragstart", onDragStart);
  doc.documentElement.classList.add(PROTECTION_CLASS);

  return () => {
    doc.removeEventListener("contextmenu", onContextMenu);
    doc.removeEventListener("keydown", onKeyDown, { capture: true });
    doc.removeEventListener("dragstart", onDragStart);
    doc.documentElement.classList.remove(PROTECTION_CLASS);
  };
}
