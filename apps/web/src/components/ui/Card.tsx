import type { HTMLAttributes } from "react";
import { cn } from "@/lib/cn";

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("rounded-lg border border-border bg-surface p-6", className)} {...props} />;
}

export function CardHeader({ title, description, className }: { title: string; description?: string; className?: string }) {
  return (
    <div className={cn("mb-5 flex flex-col gap-1", className)}>
      <h2 className="text-lg font-semibold tracking-tight text-text">{title}</h2>
      {description && <p className="text-sm text-muted">{description}</p>}
    </div>
  );
}
