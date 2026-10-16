import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { RequireAuth } from "@/components/auth/RequireAuth";
import { storeIsOpen } from "@/lib/commerce/api";
import { CheckoutView } from "./CheckoutView";

// Rendered on each request: depends on the add-on switch and on who is signed in.
export const instant = false;

export const metadata: Metadata = { title: "Checkout", robots: { index: false, follow: false } };

export default async function CheckoutPage() {
  if (!(await storeIsOpen())) notFound();

  return (
    <div className="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
      <p className="font-mono text-xs text-accent">$ checkout</p>
      <h1 className="mt-1 mb-8 text-3xl font-semibold tracking-tight">Checkout</h1>
      <RequireAuth>
        <CheckoutView />
      </RequireAuth>
    </div>
  );
}
