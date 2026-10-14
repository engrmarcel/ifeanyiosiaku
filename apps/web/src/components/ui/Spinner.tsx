import { cn } from "@/lib/cn";

/** Accessible loading indicator. Pass label="" when a surrounding element already announces state. */
export function Spinner({ size = "md", label = "Loading", className }: { size?: "sm" | "md"; label?: string; className?: string }) {
  return (
    <span role={label ? "status" : undefined} className={cn("inline-flex items-center", className)}>
      <span
        aria-hidden="true"
        className={cn(
          "animate-spin rounded-full border-2 border-current border-r-transparent",
          size === "sm" ? "size-3.5" : "size-5",
        )}
      />
      {label && <span className="sr-only">{label}</span>}
    </span>
  );
}
