"use client";

import { useEffect, useState } from "react";

const buttonClass =
  "inline-flex size-9 items-center justify-center rounded-full border border-border-strong text-muted transition-colors hover:border-accent hover:text-accent focus-visible:outline-2 focus-visible:outline-accent";

/** "Copy link" and, on devices that offer it, the phone's own share sheet. */
export function ShareActions({ url, title }: { url: string; title: string }) {
  const [copied, setCopied] = useState<"idle" | "copied" | "failed">("idle");
  const [canShare, setCanShare] = useState(false);

  useEffect(() => {
    // Only known after the page loads in the browser, so the button appears without a mismatch.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setCanShare(typeof navigator !== "undefined" && typeof navigator.share === "function");
  }, []);

  async function copy() {
    try {
      await navigator.clipboard.writeText(url);
      setCopied("copied");
    } catch {
      setCopied("failed");
    }
    setTimeout(() => setCopied("idle"), 2000);
  }

  return (
    <>
      <li>
        <button type="button" onClick={() => void copy()} className={buttonClass} aria-label="Copy link">
          <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5" />
            <path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5" />
          </svg>
        </button>
        <span aria-live="polite" className={copied === "idle" ? "sr-only" : "ml-2 font-mono text-xs text-accent"}>
          {copied === "copied" ? "Link copied" : copied === "failed" ? "Could not copy" : ""}
        </span>
      </li>
      {canShare && (
        <li>
          <button
            type="button"
            onClick={() => void navigator.share({ title, url }).catch(() => undefined)}
            className={buttonClass}
            aria-label="More sharing options"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7" />
              <path d="M16 6l-4-4-4 4" />
              <path d="M12 2v13" />
            </svg>
          </button>
        </li>
      )}
    </>
  );
}
