import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

/**
 * Consistent horizontal width and vertical rhythm for every block. All blocks share the same left
 * edge as the page header and wide blocks; "narrow" only limits line length for comfortable reading.
 */
export function Section({
  id,
  width = "wide",
  className,
  children,
}: {
  id?: string | null;
  width?: "narrow" | "wide" | "full";
  className?: string;
  children: ReactNode;
}) {
  return (
    <section id={id ?? undefined} className={cn("scroll-mt-24 py-8 sm:py-10", className)}>
      {width === "full" ? (
        <div className="w-full">{children}</div>
      ) : (
        <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
          <div className={cn(width === "narrow" && "max-w-3xl")}>{children}</div>
        </div>
      )}
    </section>
  );
}
