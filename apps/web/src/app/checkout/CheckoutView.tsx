"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { OrderSummary } from "@/components/commerce/OrderSummary";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { placedOrderSchema } from "@/lib/commerce/cart";
import { useCart } from "@/lib/commerce/CartProvider";

export function CheckoutView() {
  const { user } = useAuth();
  const { cart, status, refresh } = useCart();
  const router = useRouter();
  const [name, setName] = useState(user?.name ?? "");
  const [errors, setErrors] = useState<Record<string, string | undefined>>({});
  const [busy, setBusy] = useState(false);

  if (!user) return null;

  if (status === "idle" || status === "loading") {
    return <Skeleton className="h-64 w-full" />;
  }

  if (cart.items.length === 0) {
    return (
      <EmptyState
        title="Nothing to check out"
        description="Your cart is empty."
        action={
          <Button asChild>
            <Link href="/store">Visit the store</Link>
          </Button>
        }
      />
    );
  }

  const free = cart.total.amount === 0;

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const body = await api<{ data: unknown }>("/api/v1/store/checkout", {
        method: "POST",
        body: { billing_name: name },
      });
      const order = placedOrderSchema.parse(body.data);
      await refresh();
      router.push(`/account/orders/${order.id}?placed=1`);
    } catch (error) {
      if (error instanceof ApiError) {
        const billingName = error.field("billing_name");
        const general = error.status === 429 ? "Too many attempts. Please wait a minute." : error.message;
        setErrors({
          billing_name: billingName,
          form:
            error.field("email") ?? error.field("cart") ?? error.field("coupon") ?? (billingName ? undefined : general),
        });
        // The server's view of the cart may have changed (a product withdrawn, a code used up).
        void refresh();
      } else {
        setErrors({ form: "Your order could not be placed. Please check your connection and try again." });
      }
      setBusy(false);
    }
  }

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
      <div className="flex flex-col gap-6">
        {!user.email_verified && (
          <Alert tone="warning" title="Confirm your email address first">
            Receipts and downloads are sent to {user.email}.{" "}
            <Link href="/verify-email" className="text-accent hover:underline">
              Resend the confirmation link
            </Link>
          </Alert>
        )}

        <section aria-labelledby="details-heading" className="rounded-lg border border-border bg-surface p-5">
          <h2 id="details-heading" className="mb-4 font-mono text-xs tracking-wider text-muted uppercase">
            Your details
          </h2>
          <div className="flex flex-col gap-4">
            <TextField
              label="Name on the receipt"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={120}
              autoComplete="name"
              required
              error={errors.billing_name}
            />
            <p className="text-sm text-muted">
              Email: <span className="text-text">{user.email}</span>
            </p>
          </div>
        </section>

        <section aria-labelledby="items-heading" className="rounded-lg border border-border bg-surface p-5">
          <h2 id="items-heading" className="mb-4 font-mono text-xs tracking-wider text-muted uppercase">
            Items
          </h2>
          <ul className="divide-y divide-border text-sm">
            {cart.items.map((item) => (
              <li key={item.product.id} className="flex justify-between gap-4 py-2">
                <span>{item.product.title}</span>
                <span className="font-mono">{item.product.isFree ? "Free" : item.total.formatted}</span>
              </li>
            ))}
          </ul>
          <p className="mt-3 text-xs">
            <Link href="/cart" className="text-accent hover:underline">
              Change your cart
            </Link>
          </p>
        </section>
      </div>

      <aside className="flex flex-col gap-5 self-start rounded-lg border border-border bg-surface p-5">
        <h2 className="font-mono text-xs tracking-wider text-muted uppercase">Order total</h2>
        <OrderSummary
          subtotal={cart.subtotal}
          discount={cart.discount}
          total={cart.total}
          couponCode={cart.coupon?.code}
        />
        {errors.form && <Alert tone="error">{errors.form}</Alert>}
        {!free && (
          <p className="text-xs text-muted">
            The final price is checked again when you place the order. You will then be asked to pay; unpaid orders are
            cancelled after an hour.
          </p>
        )}
        <Button type="submit" size="lg" loading={busy} disabled={!cart.purchasable || !user.email_verified}>
          {free ? "Get it now" : `Place order · ${cart.total.formatted}`}
        </Button>
      </aside>
    </form>
  );
}
