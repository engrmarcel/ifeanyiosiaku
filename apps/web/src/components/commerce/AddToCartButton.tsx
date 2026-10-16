"use client";

import Link from "next/link";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/ui/Toast";
import { ApiError } from "@/lib/api/client";
import { useCart } from "@/lib/commerce/CartProvider";
import type { ProductCard } from "@/lib/commerce/schemas";

/** "Add to cart", or "In your cart" with a link once added. */
export function AddToCartButton({
  product,
  className,
}: {
  product: Pick<ProductCard, "id" | "title" | "isFree" | "available">;
  className?: string;
}) {
  const { has, add, status } = useCart();
  const { toast } = useToast();
  const [busy, setBusy] = useState(false);

  if (!product.available) return null;

  if (has(product.id)) {
    return (
      <div className={className}>
        <Button asChild variant="secondary" size="lg">
          <Link href="/cart">In your cart · View cart</Link>
        </Button>
      </div>
    );
  }

  async function onAdd() {
    setBusy(true);
    try {
      await add(product.id);
      toast({ tone: "success", title: "Added to your cart", description: product.title });
    } catch (error) {
      toast({
        tone: "error",
        title: "Could not add this item",
        description: error instanceof ApiError ? (error.field("product") ?? error.message) : "Please try again.",
      });
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className={className}>
      <Button size="lg" onClick={onAdd} loading={busy} disabled={status === "loading"}>
        {product.isFree ? "Get it free" : "Add to cart"}
      </Button>
    </div>
  );
}
