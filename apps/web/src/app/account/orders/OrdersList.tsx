"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { z } from "zod";
import { formatDateTime, OrderStatusBadge } from "@/components/commerce/OrderStatusBadge";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { Skeleton } from "@/components/ui/Skeleton";
import { api } from "@/lib/api/client";
import { orderSchema, type Order } from "@/lib/commerce/cart";

const listSchema = z.object({ data: z.array(orderSchema) });

export function OrdersList() {
  const [orders, setOrders] = useState<Order[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let active = true;
    api("/api/v1/store/orders")
      .then((body) => listSchema.parse(body).data)
      .then(
        (data) => active && setOrders(data),
        () => active && setFailed(true),
      );
    return () => {
      active = false;
    };
  }, [attempt]);

  if (failed) {
    return (
      <ErrorState
        title="Your orders could not be loaded"
        onRetry={() => {
          setFailed(false);
          setAttempt((n) => n + 1);
        }}
      />
    );
  }

  if (!orders) {
    return (
      <div className="flex flex-col gap-3" aria-busy="true" aria-label="Loading your orders">
        <Skeleton className="h-20 w-full" />
        <Skeleton className="h-20 w-full" />
      </div>
    );
  }

  if (orders.length === 0) {
    return (
      <EmptyState
        title="No orders yet"
        description="Things you buy or get for free from the store will be listed here."
        action={
          <Button asChild>
            <Link href="/store">Visit the store</Link>
          </Button>
        }
      />
    );
  }

  return (
    <ul className="flex flex-col gap-3">
      {orders.map((order) => (
        <li key={order.id}>
          <Link
            href={`/account/orders/${order.id}`}
            className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border bg-surface p-4 hover:border-accent/60"
          >
            <div className="min-w-0">
              <p className="font-mono text-sm text-text">{order.number}</p>
              <p className="mt-1 truncate text-sm text-muted">
                {order.items.map((i) => i.title).join(", ")} · {formatDateTime(order.createdAt)}
              </p>
            </div>
            <div className="flex items-center gap-3">
              <OrderStatusBadge order={order} />
              <span className="font-mono text-sm">{order.total.formatted}</span>
            </div>
          </Link>
        </li>
      ))}
    </ul>
  );
}
