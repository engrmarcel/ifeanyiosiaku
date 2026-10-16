"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { z } from "zod";
import { OrderSummary } from "@/components/commerce/OrderSummary";
import { formatDateTime, OrderStatusBadge } from "@/components/commerce/OrderStatusBadge";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { ErrorState } from "@/components/ui/ErrorState";
import { Skeleton } from "@/components/ui/Skeleton";
import { useToast } from "@/components/ui/Toast";
import { api, ApiError } from "@/lib/api/client";
import { isOrderId, orderSchema, type Order } from "@/lib/commerce/cart";

const envelope = z.object({ data: orderSchema });

export function OrderDetail({ id }: { id: string }) {
  const placed = useSearchParams().get("placed") === "1";
  const { toast } = useToast();
  const [order, setOrder] = useState<Order | null>(null);
  const [state, setState] = useState<"loading" | "ready" | "missing" | "error">(isOrderId(id) ? "loading" : "missing");
  const [cancelling, setCancelling] = useState(false);

  useEffect(() => {
    if (!isOrderId(id)) return;
    let active = true;
    api(`/api/v1/store/orders/${id}`)
      .then((body) => envelope.parse(body).data)
      .then(
        (data) => {
          if (!active) return;
          setOrder(data);
          setState("ready");
        },
        (error) => active && setState(error instanceof ApiError && error.status === 404 ? "missing" : "error"),
      );
    return () => {
      active = false;
    };
  }, [id]);

  if (state === "loading") return <Skeleton className="h-64 w-full" />;
  if (state === "missing")
    return (
      <ErrorState title="Order not found" description="This order does not exist or belongs to another account." />
    );
  if (state === "error" || !order)
    return <ErrorState title="This order could not be loaded" onRetry={() => window.location.reload()} />;

  async function cancel() {
    setCancelling(true);
    try {
      const body = await api(`/api/v1/store/orders/${id}/cancel`, { method: "POST" });
      setOrder(envelope.parse(body).data);
      toast({ tone: "success", title: "Order cancelled" });
    } catch (error) {
      toast({
        tone: "error",
        title: "Could not cancel the order",
        description: error instanceof ApiError ? (error.field("order") ?? error.message) : undefined,
      });
    } finally {
      setCancelling(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      {placed && order.status === "paid" && (
        <Alert tone="success" title="Thank you, your order is complete">
          This order is saved in your account.
        </Alert>
      )}

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-mono text-2xl font-semibold tracking-tight">{order.number}</h1>
          <p className="mt-1 text-sm text-muted">Placed {formatDateTime(order.createdAt)}</p>
        </div>
        <OrderStatusBadge order={order} className="text-sm" />
      </div>

      {order.status === "pending" && (
        <Alert tone="warning" title="Awaiting payment">
          Online payment is being connected and is not available yet, so this order cannot be paid for at the moment. It
          will be cancelled automatically
          {order.expiresAt ? ` at ${formatDateTime(order.expiresAt)}` : " after an hour"} if it is not paid.
        </Alert>
      )}

      <section aria-labelledby="order-items" className="rounded-lg border border-border bg-surface p-5">
        <h2 id="order-items" className="mb-3 font-mono text-xs tracking-wider text-muted uppercase">
          Items
        </h2>
        <ul className="divide-y divide-border text-sm">
          {order.items.map((item, i) => (
            <li key={`${item.title}-${i}`} className="flex justify-between gap-4 py-2">
              {item.url ? (
                <Link href={item.url} className="hover:text-accent">
                  {item.title}
                </Link>
              ) : (
                <span>{item.title}</span>
              )}
              <span className="font-mono">
                {item.total.amount === 0 && item.unit.amount === 0 ? "Free" : item.total.formatted}
              </span>
            </li>
          ))}
        </ul>
        <div className="mt-4 border-t border-border pt-4">
          <OrderSummary
            subtotal={order.subtotal}
            discount={order.discount}
            total={order.total}
            couponCode={order.couponCode}
          >
            {order.paidAt && (
              <div className="flex justify-between gap-4">
                <dt className="text-muted">Paid</dt>
                <dd>{formatDateTime(order.paidAt)}</dd>
              </div>
            )}
          </OrderSummary>
        </div>
      </section>

      <div className="flex flex-wrap gap-3">
        <Button asChild variant="secondary">
          <Link href="/account/orders">All orders</Link>
        </Button>
        {order.status === "pending" && (
          <Button variant="danger" onClick={cancel} loading={cancelling}>
            Cancel this order
          </Button>
        )}
      </div>
    </div>
  );
}
