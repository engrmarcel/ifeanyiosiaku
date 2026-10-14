import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type Tone = "info" | "success" | "warning" | "error";

const tones: Record<Tone, string> = {
  info: "border-violet/40 bg-surface text-text",
  success: "border-accent/40 bg-success-surface text-text",
  warning: "border-amber/40 bg-warning-surface text-text",
  error: "border-danger/50 bg-danger-surface text-text",
};

const markers: Record<Tone, string> = {
  info: "text-violet-text",
  success: "text-accent",
  warning: "text-amber",
  error: "text-danger",
};

/** Inline message. Errors are announced immediately; other tones politely. */
export function Alert({ tone = "info", title, children, className }: { tone?: Tone; title?: string; children?: ReactNode; className?: string }) {
  return (
    <div
      role={tone === "error" ? "alert" : "status"}
      className={cn("flex gap-3 rounded-md border px-4 py-3 text-sm", tones[tone], className)}
    >
      <span aria-hidden="true" className={cn("font-mono leading-5", markers[tone])}>
        {tone === "error" ? "✕" : tone === "success" ? "✓" : tone === "warning" ? "!" : "›"}
      </span>
      <div className="flex flex-col gap-1">
        {title && <p className="font-medium">{title}</p>}
        {children && <div className="text-muted">{children}</div>}
      </div>
    </div>
  );
}
