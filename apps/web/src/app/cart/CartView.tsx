"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { OrderSummary } from "@/components/commerce/OrderSummary";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { Skeleton } from "@/components/ui/Skeleton";
import { TextField } from "@/components/ui/TextField";
import { useToast } from "@/components/ui/Toast";
import { ApiError } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { useCart } from "@/lib/commerce/CartProvider";

export function CartView() {
  const { cart, status, remove, refresh, setCurrency } = useCart();
  const { status: authStatus } = useAuth();
  const { toast } = useToast();
  const [removing, setRemoving] = useState<string | null>(null);

  if (status === "idle" || status === "loading") {
    return (
      <div className="flex flex-col gap-4" aria-busy="true" aria-label="Loading your cart">
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  if (status === "error") {
    return (
      <ErrorState
        title="Your cart could not be loaded"
        description="Please check your connection and try again."
        onRetry={refresh}
      />
    );
  }

  if (cart.items.length === 0) {
    return (
      <EmptyState
        title="Your cart is empty"
        description="Browse the store to find e-books, templates and tools."
        action={
          <Button asChild>
            <Link href="/store">Visit the store</Link>
          </Button>
        }
      />
    );
  }

  async function onRemove(id: string) {
    setRemoving(id);
    try {
      await remove(id);
    } catch {
      toast({ tone: "error", title: "Could not remove the item", description: "Please try again." });
    } finally {
      setRemoving(null);
    }
  }

  const checkoutHref = authStatus === "authenticated" ? "/checkout" : `/login?next=${encodeURIComponent("/checkout")}`;

  return (
    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
      <ul className="flex flex-col gap-3" aria-label="Items in your cart">
        {cart.items.map((item) => (
          <li
            key={item.product.id}
            className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-border bg-surface p-4"
          >
            <div className="min-w-0">
              <p className="font-mono text-xs text-accent">{item.product.typeLabel}</p>
              <Link href={item.product.url} className="font-semibold text-text hover:text-accent">
                {item.product.title}
              </Link>
              {item.problem && <p className="mt-1 text-sm text-danger">{item.problem}</p>}
            </div>
            <div className="flex items-center gap-4">
              <div className="text-right font-mono text-sm">
                {item.unit ? (
                  item.product.isFree ? (
                    <span className="text-accent">Free</span>
                  ) : item.discount.amount > 0 ? (
                    <>
                      <s className="block text-muted">
                        <span className="sr-only">was </span>
                        {item.unit.formatted}
                      </s>
                      <span className="text-text">{item.total.formatted}</span>
                    </>
                  ) : (
                    <span className="text-text">{item.unit.formatted}</span>
                  )
                ) : (
                  <span className="text-muted">—</span>
                )}
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onRemove(item.product.id)}
                loading={removing === item.product.id}
                aria-label={`Remove ${item.product.title}`}
              >
                Remove
              </Button>
            </div>
          </li>
        ))}
      </ul>

      <aside className="flex flex-col gap-5 self-start rounded-lg border border-border bg-surface p-5">
        <h2 className="font-mono text-xs tracking-wider text-muted uppercase">Summary</h2>

        {cart.currencies.length > 1 && (
          <label className="flex items-center justify-between gap-3 text-sm">
            <span className="text-muted">Currency</span>
            <select
              value={cart.currency}
              onChange={(event) =>
                setCurrency(event.target.value).catch(() =>
                  toast({ tone: "error", title: "Could not change the currency" }),
                )
              }
              className="h-9 rounded-md border border-border-strong bg-bg px-2 font-mono text-sm text-text focus:border-accent focus:outline-none"
            >
              {cart.currencies.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </label>
        )}

        <CouponForm />

        <OrderSummary
          subtotal={cart.subtotal}
          discount={cart.discount}
          total={cart.total}
          couponCode={cart.coupon?.code}
        />

        {!cart.purchasable && <Alert tone="warning">Remove the items marked above to continue.</Alert>}

        {cart.purchasable ? (
          <Button asChild size="lg">
            <Link href={checkoutHref}>
              {authStatus === "authenticated" ? "Continue to checkout" : "Sign in to check out"}
            </Link>
          </Button>
        ) : (
          <Button size="lg" disabled>
            Continue to checkout
          </Button>
        )}
        {authStatus !== "authenticated" && (
          <p className="text-xs text-muted">
            Orders are kept in your account. Your cart is kept when you sign in or{" "}
            <Link href="/register" className="text-accent hover:underline">
              create an account
            </Link>
            .
          </p>
        )}
      </aside>
    </div>
  );
}

function CouponForm() {
  const { cart, applyCoupon, removeCoupon } = useCart();
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | undefined>();
  const [busy, setBusy] = useState(false);

  if (cart.coupon) {
    return (
      <div className="flex items-center justify-between gap-3 rounded-md border border-accent/40 px-3 py-2 text-sm">
        <span>
          Code <span className="font-mono text-accent">{cart.coupon.code}</span> applied
          {cart.coupon.description && <span className="block text-xs text-muted">{cart.coupon.description}</span>}
        </span>
        <Button variant="ghost" size="sm" onClick={() => removeCoupon()}>
          Remove
        </Button>
      </div>
    );
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!code.trim()) return;
    setBusy(true);
    setError(undefined);
    try {
      await applyCoupon(code.trim());
      setCode("");
    } catch (e) {
      setError(e instanceof ApiError ? (e.field("code") ?? e.message) : "Could not check the code. Please try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="flex items-end gap-2" noValidate>
      <div className="flex-1">
        <TextField
          label="Discount code"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          maxLength={40}
          autoComplete="off"
          autoCapitalize="characters"
          error={error}
        />
      </div>
      <Button type="submit" variant="secondary" loading={busy} className="mb-px h-11">
        Apply
      </Button>
    </form>
  );
}
