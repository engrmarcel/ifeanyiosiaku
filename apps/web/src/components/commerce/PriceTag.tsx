import { cn } from "@/lib/cn";
import type { ProductCard } from "@/lib/commerce/schemas";

/** "₦12,500.00" with an optional crossed-out former price, "Free", or "Not sold in this currency". */
export function PriceTag({ product, className, large = false }: { product: Pick<ProductCard, "isFree" | "price" | "available">; className?: string; large?: boolean }) {
  if (product.isFree) {
    return <p className={cn("font-mono font-semibold text-accent", large ? "text-2xl" : "text-base", className)}>Free</p>;
  }

  if (!product.price) {
    return <p className={cn("text-sm text-muted", className)}>Not available in this currency</p>;
  }

  return (
    <p className={cn("flex flex-wrap items-baseline gap-2", className)}>
      <span className={cn("font-mono font-semibold text-text", large ? "text-2xl" : "text-base")}>{product.price.formatted}</span>
      {product.price.compareAt && (
        <s className="font-mono text-sm text-muted">
          <span className="sr-only">was </span>
          {product.price.compareAt.formatted}
        </s>
      )}
    </p>
  );
}
