import type { ReactNode } from "react";

/** Centered card layout shared by sign-in, registration and recovery screens. */
export function AuthShell({ eyebrow, title, description, children, footer }: {
  eyebrow: string;
  title: string;
  description?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
}) {
  return (
    <div className="relative flex flex-1 items-center justify-center px-4 py-12 sm:py-20">
      <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0" />
      <div className="relative w-full max-w-md">
        <div className="rounded-lg border border-border bg-surface/95 p-6 shadow-[0_30px_80px_-40px_rgb(124_92_252/0.45)] sm:p-8">
          <p className="font-mono text-xs tracking-wide text-accent">{eyebrow}</p>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight text-text">{title}</h1>
          {description && <div className="mt-2 text-sm text-muted">{description}</div>}
          <div className="mt-6">{children}</div>
        </div>
        {footer && <div className="mt-5 text-center text-sm text-muted">{footer}</div>}
      </div>
    </div>
  );
}
