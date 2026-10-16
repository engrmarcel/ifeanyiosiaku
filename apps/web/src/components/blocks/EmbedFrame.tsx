"use client";

import { useEffect, useRef, useState } from "react";

const MIN_HEIGHT = 60;
const MAX_HEIGHT = 4000;

/**
 * Trusted custom code (REQ-CMS-8) in a sandboxed frame. The frame has no "allow-same-origin", and
 * the server adds a sandbox policy too, so the code runs in a unique origin: it cannot read the
 * visitor's session, storage or this page. With "fit content", the frame reports its height via
 * postMessage; only messages from this exact frame are accepted, and the value is clamped.
 */
export function EmbedFrame({ src, title, height }: { src: string; title: string; height: number | null }) {
  const frame = useRef<HTMLIFrameElement>(null);
  const [autoHeight, setAutoHeight] = useState(300);

  useEffect(() => {
    if (height !== null) return;

    function onMessage(event: MessageEvent) {
      if (event.source !== frame.current?.contentWindow) return;
      const data = event.data as { type?: unknown; height?: unknown } | null;
      if (data?.type !== "io-embed-height" || typeof data.height !== "number" || !Number.isFinite(data.height)) return;
      setAutoHeight(Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.ceil(data.height))));
    }

    window.addEventListener("message", onMessage);
    return () => window.removeEventListener("message", onMessage);
  }, [height]);

  return (
    <iframe
      ref={frame}
      src={src}
      title={title}
      sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms"
      referrerPolicy="no-referrer"
      loading="lazy"
      className="block w-full rounded-lg border border-border bg-transparent"
      style={{ height: height ?? autoHeight }}
    />
  );
}
