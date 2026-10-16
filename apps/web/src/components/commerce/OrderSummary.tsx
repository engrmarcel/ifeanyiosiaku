import type { ReactNode } from "react";

type Money = { amount: number; formatted: string };

/** Subtotal, discount and total rows shared by the cart, checkout and order pages. */
export function OrderSummary({
  subtotal,
  discount,
  total,
  couponCode,
  children,
}: {
  subtotal: Money;
  discount: Money;
  total: Money;
  couponCode?: string | null;
  children?: ReactNode;
}) {
  return (
    <dl className="space-y-2 text-sm">
      <div className="flex justify-between gap-4">
        <dt className="text-muted">Subtotal</dt>
        <dd className="font-mono">{subtotal.formatted}</dd>
      </div>
      {discount.amount > 0 && (
        <div className="flex justify-between gap-4">
          <dt className="text-muted">Discount{couponCode ? ` (${couponCode})` : ""}</dt>
          <dd className="font-mono text-accent">−{discount.formatted}</dd>
        </div>
      )}
      {children}
      <div className="flex justify-between gap-4 border-t border-border pt-3 text-base">
        <dt className="font-semibold">Total</dt>
        <dd className="font-mono font-semibold">{total.formatted}</dd>
      </div>
    </dl>
  );
}
