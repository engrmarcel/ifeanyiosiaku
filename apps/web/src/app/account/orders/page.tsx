import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { storeIsOpen } from "@/lib/commerce/api";
import { OrdersList } from "./OrdersList";

// Rendered on each request: depends on the add-on switch and on who is signed in.
export const instant = false;

export const metadata: Metadata = { title: "Your orders" };

export default async function OrdersPage() {
  if (!(await storeIsOpen())) notFound();

  return (
    <div className="flex flex-col gap-6">
      <nav aria-label="Breadcrumb" className="font-mono text-sm text-muted">
        <Link href="/account" className="text-accent hover:underline">
          account
        </Link>
        <span aria-hidden="true"> / </span>orders
      </nav>
      <h1 className="text-3xl font-semibold tracking-tight">Your orders</h1>
      <OrdersList />
    </div>
  );
}
