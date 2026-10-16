import Link from "next/link";
import { MediaImage } from "@/components/blocks/MediaImage";
import type { ProductCard as ProductCardData } from "@/lib/commerce/schemas";
import { PriceTag } from "./PriceTag";

export function ProductCard({ product, headingLevel = 3 }: { product: ProductCardData; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";

  return (
    <article className="group relative flex h-full flex-col overflow-hidden rounded-lg border border-border bg-surface transition-[border-color,box-shadow] duration-300 hover:border-accent/50 hover:shadow-glow">
      {product.cover ? (
        <MediaImage media={product.cover} sizes="(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw" className="aspect-[4/3] w-full border-b border-border object-cover" />
      ) : (
        <div aria-hidden="true" className="io-grid-backdrop flex aspect-[4/3] items-center justify-center border-b border-border font-mono text-sm text-muted">
          {`{ ${product.typeLabel.toLowerCase()} }`}
        </div>
      )}
      <div className="flex flex-1 flex-col p-5">
        <p className="font-mono text-xs text-accent">
          {product.typeLabel}
          {product.isFeatured && <span className="ml-2 rounded-sm bg-accent/15 px-1.5 py-0.5 text-accent">featured</span>}
        </p>
        <Heading className="mt-2 text-lg font-semibold tracking-tight text-text text-balance group-hover:text-accent">
          <Link href={product.url} className="after:absolute after:inset-0 focus-visible:outline-none">
            {product.title}
          </Link>
        </Heading>
        {product.summary && <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-muted">{product.summary}</p>}
        <PriceTag product={product} className="mt-auto pt-4" />
      </div>
    </article>
  );
}
