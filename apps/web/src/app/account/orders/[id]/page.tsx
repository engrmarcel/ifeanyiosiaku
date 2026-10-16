import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { Skeleton } from "@/components/ui/Skeleton";
import { storeIsOpen } from "@/lib/commerce/api";
import { OrderDetail } from "./OrderDetail";

// Rendered on each request: depends on the add-on switch and on who is signed in.
export const instant = false;

export const metadata: Metadata = { title: "Order" };

export default async function OrderPage({ params }: PageProps<"/account/orders/[id]">) {
  if (!(await storeIsOpen())) notFound();
  const { id } = await params;

  return (
    <div className="flex flex-col gap-6">
      <nav aria-label="Breadcrumb" className="font-mono text-sm text-muted">
        <Link href="/account" className="text-accent hover:underline">
          account
        </Link>
        <span aria-hidden="true"> / </span>
        <Link href="/account/orders" className="text-accent hover:underline">
          orders
        </Link>
      </nav>
      <Suspense fallback={<Skeleton className="h-64 w-full" />}>
        <OrderDetail id={id} />
      </Suspense>
    </div>
  );
}
