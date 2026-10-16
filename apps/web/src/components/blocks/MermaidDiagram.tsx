"use client";

import { useEffect, useId, useRef, useState } from "react";

let loader: Promise<typeof import("mermaid")["default"]> | null = null;

/** The diagram library is large, so it is only downloaded when a diagram is about to be seen. */
function loadMermaid() {
  loader ??= import("mermaid").then(({ default: mermaid }) => {
    mermaid.initialize({
      startOnLoad: false,
      // Strict: diagram text is sanitised, no scripts, no click actions, no HTML labels.
      securityLevel: "strict",
      theme: "dark",
      fontFamily: "var(--font-geist-sans), system-ui, sans-serif",
      themeVariables: { background: "transparent", primaryColor: "#161c25", primaryBorderColor: "#313a46", lineColor: "#aab4c0", primaryTextColor: "#f5f7fa" },
    });
    return mermaid;
  });
  return loader;
}

/**
 * Draws a Mermaid diagram in the browser once it is near the screen. Until it is drawn (or if it
 * cannot be), the text description is shown, so the content is never lost; screen readers always
 * get the description. Space is reserved so the page does not jump when the drawing appears.
 */
export function MermaidDiagram({ source, description }: { source: string; description: string }) {
  const id = useId().replace(/[^a-zA-Z0-9]/g, "");
  const box = useRef<HTMLDivElement>(null);
  // Without IntersectionObserver (very old browsers), draw straight away. Only gates the effect below,
  // so server and browser render the same placeholder.
  const [visible, setVisible] = useState(() => typeof IntersectionObserver === "undefined");
  const [svg, setSvg] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const element = box.current;
    if (!element || typeof IntersectionObserver === "undefined") return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          setVisible(true);
          observer.disconnect();
        }
      },
      { rootMargin: "300px" },
    );
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    if (!visible) return;
    let active = true;

    loadMermaid()
      .then((mermaid) => mermaid.render(`io-diagram-${id}`, source))
      .then(
        ({ svg: drawn }) => active && setSvg(drawn),
        () => active && setFailed(true),
      );

    return () => {
      active = false;
    };
  }, [visible, id, source]);

  return (
    <div ref={box} className="flex min-h-48 items-center justify-center">
      {failed || svg === null ? (
        <p className="w-full rounded-md border border-dashed border-border-strong p-4 text-sm text-muted" aria-live="polite">
          {failed ? "This diagram could not be drawn. " : ""}
          {description}
        </p>
      ) : (
        <div
          role="img"
          aria-label={description}
          className="w-full overflow-x-auto [&_svg]:mx-auto [&_svg]:h-auto [&_svg]:max-w-full"
          // Produced by Mermaid in strict mode (sanitised SVG), never raw editor HTML.
          dangerouslySetInnerHTML={{ __html: svg }}
        />
      )}
    </div>
  );
}
