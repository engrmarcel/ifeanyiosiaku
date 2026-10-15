"use client";

import { useState } from "react";

/** Copies code to the clipboard and confirms politely for screen readers. */
export function CopyButton({ text }: { text: string }) {
  const [state, setState] = useState<"idle" | "copied" | "failed">("idle");

  async function copy() {
    try {
      await navigator.clipboard.writeText(text);
      setState("copied");
    } catch {
      setState("failed");
    }
    setTimeout(() => setState("idle"), 2000);
  }

  return (
    <button
      type="button"
      onClick={() => void copy()}
      className="rounded-sm px-1.5 py-0.5 font-mono text-xs text-muted transition-colors hover:text-accent"
    >
      <span aria-live="polite">{state === "copied" ? "Copied" : state === "failed" ? "Copy failed" : "Copy"}</span>
      <span className="sr-only"> code to clipboard</span>
    </button>
  );
}
