import { cn } from "@/lib/cn";

/** Placeholder shown while content loads. Hidden from assistive technology. */
export function Skeleton({ className }: { className?: string }) {
  return <div aria-hidden="true" className={cn("animate-pulse rounded-md bg-surface-raised", className)} />;
}
