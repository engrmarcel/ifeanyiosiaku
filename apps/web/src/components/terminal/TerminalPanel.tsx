import type { CSSProperties } from "react";
import { cn } from "@/lib/cn";

export type TerminalLine = { kind: "command" | "output" | "highlight" | "comment"; text: string };

const tones: Record<TerminalLine["kind"], string> = {
  command: "text-text",
  output: "text-muted",
  highlight: "text-accent",
  comment: "text-violet-text",
};

/**
 * Terminal window whose lines appear one after another. The reveal is pure CSS (see globals.css):
 * it always ends fully visible, needs no JavaScript, and is skipped for visitors who prefer
 * reduced motion. Text is real page content (searchable, read by screen readers).
 */
export function TerminalPanel({
  title = "~",
  lines,
  animate = true,
  className,
}: {
  title?: string;
  lines: TerminalLine[];
  animate?: boolean;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "relative overflow-hidden rounded-lg border border-border-strong bg-surface shadow-[0_40px_120px_-50px_color-mix(in_srgb,var(--io-accent)_35%,transparent)]",
        animate && "io-term-animate",
        className,
      )}
    >
      <div className="flex items-center gap-2 border-b border-border px-4 py-3" aria-hidden="true">
        <span className="size-2.5 rounded-full bg-[#ff5f57]/80" />
        <span className="size-2.5 rounded-full bg-[#febc2e]/80" />
        <span className="size-2.5 rounded-full bg-[#28c840]/80" />
        <span className="ml-3 truncate font-mono text-xs text-muted">{title}</span>
      </div>
      <div className="space-y-2 overflow-x-auto p-5 font-mono text-sm leading-relaxed sm:p-6">
        {lines.map((line, index) => (
          <p
            key={index}
            style={{ "--i": index } as CSSProperties}
            className={cn("io-term-line whitespace-pre-wrap break-words", tones[line.kind])}
          >
            {line.kind === "command" && (
              <span className="mr-2 text-accent" aria-hidden="true">
                $
              </span>
            )}
            {line.kind === "comment" && (
              <span className="mr-2" aria-hidden="true">
                #
              </span>
            )}
            {line.text}
          </p>
        ))}
        <p aria-hidden="true" className="io-term-line" style={{ "--i": lines.length } as CSSProperties}>
          <span className="mr-2 text-accent">$</span>
          <span className="io-caret inline-block h-4 w-2 translate-y-0.5 bg-accent" />
        </p>
      </div>
    </div>
  );
}
