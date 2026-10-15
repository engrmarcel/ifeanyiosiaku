import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import { Button } from "./Button";

/**
 * Shown in place of content that failed to load. Offers a retry when one is possible and shows
 * the request ID (if known) so support can find the matching server log entry.
 */
export function ErrorState({
  title = "Something went wrong",
  description = "This section could not be loaded. Please try again.",
  onRetry,
  retrying = false,
  requestId,
  action,
  className,
}: {
  title?: string;
  description?: string;
  onRetry?: () => void;
  retrying?: boolean;
  requestId?: string;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <div
      role="alert"
      className={cn(
        "flex flex-col items-center gap-2 rounded-lg border border-danger/40 bg-danger-surface/40 px-6 py-10 text-center",
        className,
      )}
    >
      <p aria-hidden="true" className="font-mono text-sm text-danger">
        {"// error"}
      </p>
      <h3 className="text-base font-medium text-text">{title}</h3>
      <p className="max-w-sm text-sm text-muted">{description}</p>
      {(onRetry || action) && (
        <div className="mt-3 flex flex-wrap justify-center gap-2">
          {onRetry && (
            <Button variant="secondary" onClick={onRetry} loading={retrying}>
              Try again
            </Button>
          )}
          {action}
        </div>
      )}
      {requestId && <p className="mt-2 font-mono text-xs text-muted/80">Reference: {requestId}</p>}
    </div>
  );
}
