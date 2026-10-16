import { cn } from "@/lib/cn";
import type { Order } from "@/lib/commerce/cart";

const tones: Record<Order["status"], string> = {
  pending: "border-amber/40 text-amber",
  paid: "border-accent/40 text-accent",
  cancelled: "border-border-strong text-muted",
  expired: "border-border-strong text-muted",
  partially_refunded: "border-border-strong text-text",
  refunded: "border-border-strong text-text",
};

export function OrderStatusBadge({
  order,
  className,
}: {
  order: Pick<Order, "status" | "statusLabel">;
  className?: string;
}) {
  return (
    <span
      className={cn("inline-block rounded-sm border px-2 py-0.5 font-mono text-xs", tones[order.status], className)}
    >
      {order.statusLabel}
    </span>
  );
}

export function formatDateTime(iso: string | null): string {
  if (!iso) return "";
  return new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short" }).format(new Date(iso));
}
