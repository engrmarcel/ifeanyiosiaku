import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ProductCard } from "@/components/commerce/ProductCard";
import { cn } from "@/lib/cn";
import { cleanStoreFilters, getProducts, storeHref } from "@/lib/commerce/api";

/** Store catalogue. Exists only while the Commerce add-on is on (the API then answers 404). */
export const instant = false;

export async function generateMetadata({ searchParams }: PageProps<"/store">): Promise<Metadata> {
  const filters = cleanStoreFilters(await searchParams);

  return {
    title: "Store",
    description: "eBooks, templates, code packages and other digital resources on software, AI, business intelligence and data.",
    alternates: { canonical: filters.category ? storeHref({ category: filters.category }) : "/store" },
    robots: filters.q || filters.page || filters.type || filters.currency ? { index: false, follow: true } : undefined,
  };
}

export default async function StorePage({ searchParams }: PageProps<"/store">) {
  const filters = cleanStoreFilters(await searchParams);
  const list = await getProducts(filters);
  if (!list) notFound();

  const filtered = Boolean(filters.category || filters.type || filters.q);
  const keep = { currency: filters.currency };

  return (
    <div className="flex flex-1 flex-col pb-20">
      <header className="relative isolate overflow-hidden border-b border-border/60">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
        <div className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
          <p className="font-mono text-sm text-accent">{"// store"}</p>
          <h1 className="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Store</h1>
          <p className="mt-4 max-w-2xl text-lg text-muted">Practical digital resources: eBooks, templates, code packages and more.</p>

          <form action="/store" method="get" role="search" className="mt-8 flex max-w-xl gap-2">
            {filters.category && <input type="hidden" name="category" value={filters.category} />}
            {filters.type && <input type="hidden" name="type" value={filters.type} />}
            {filters.currency && <input type="hidden" name="currency" value={filters.currency} />}
            <label htmlFor="store-search" className="sr-only">
              Search the store
            </label>
            <input
              id="store-search"
              type="search"
              name="q"
              defaultValue={filters.q}
              maxLength={100}
              placeholder="Search products…"
              className="h-11 min-w-0 flex-1 rounded-md border border-border-strong bg-bg px-3 text-text placeholder:text-muted focus:border-accent focus:outline-none"
            />
            <button type="submit" className="h-11 rounded-md bg-accent px-4 text-sm font-medium text-accent-ink hover:bg-accent-strong">
              Search
            </button>
          </form>
        </div>
      </header>

      <div className="mx-auto w-full max-w-6xl px-4 pt-8 sm:px-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          {list.filters.categories.length > 0 && (
            <nav aria-label="Categories">
              <ul className="flex flex-wrap gap-2">
                <li>
                  <Chip href={storeHref({ ...keep, type: filters.type, q: filters.q })} active={!filters.category}>
                    All
                  </Chip>
                </li>
                {list.filters.categories.map((c) => (
                  <li key={c.slug}>
                    <Chip href={storeHref({ ...keep, category: c.slug, type: filters.type, q: filters.q })} active={filters.category === c.slug}>
                      {c.name} <span className="text-muted">{c.count}</span>
                    </Chip>
                  </li>
                ))}
              </ul>
            </nav>
          )}
          {list.meta.currencies.length > 1 && (
            <nav aria-label="Currency" className="flex gap-1 font-mono text-xs">
              {list.meta.currencies.map((c) => (
                <Chip key={c} href={storeHref({ ...filters, currency: c, page: undefined })} active={list.meta.currency === c}>
                  {c}
                </Chip>
              ))}
            </nav>
          )}
        </div>

        {list.filters.types.length > 1 && (
          <nav aria-label="Product types" className="mt-4">
            <ul className="flex flex-wrap gap-2 text-sm">
              {list.filters.types.map((t) => (
                <li key={t.type}>
                  <Link
                    href={storeHref({ ...keep, category: filters.category, q: filters.q, type: filters.type === t.type ? undefined : t.type })}
                    aria-current={filters.type === t.type ? "page" : undefined}
                    className={cn("font-mono text-xs underline-offset-4 hover:text-accent hover:underline", filters.type === t.type ? "text-accent underline" : "text-muted")}
                  >
                    {t.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        )}

        {filters.q && (
          <p className="mt-6 text-sm text-muted" role="status">
            {list.meta.total} {list.meta.total === 1 ? "product" : "products"} matching “<span className="text-text">{filters.q}</span>” ·{" "}
            <Link href={storeHref({ ...keep, category: filters.category })} className="text-accent underline underline-offset-2">
              Clear
            </Link>
          </p>
        )}

        {list.data.length === 0 ? (
          <div className="mt-10 rounded-lg border border-dashed border-border-strong p-10 text-center">
            <p className="text-lg text-text">{filtered ? "No products match." : "No products yet."}</p>
            <p className="mt-2 text-sm text-muted">{filtered ? "Try another search or category." : "New products will appear here."}</p>
          </div>
        ) : (
          <ul className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {list.data.map((product) => (
              <li key={product.id}>
                <ProductCard product={product} headingLevel={2} />
              </li>
            ))}
          </ul>
        )}

        {list.meta.lastPage > 1 && (
          <nav aria-label="Pages" className="mt-10 flex items-center justify-between gap-4 font-mono text-sm">
            {list.meta.page > 1 ? (
              <Link href={storeHref({ ...filters, page: list.meta.page - 1 })} rel="prev" className="text-accent hover:underline">
                ← Previous
              </Link>
            ) : (
              <span />
            )}
            <span className="text-muted">
              Page {list.meta.page} of {list.meta.lastPage}
            </span>
            {list.meta.page < list.meta.lastPage ? (
              <Link href={storeHref({ ...filters, page: list.meta.page + 1 })} rel="next" className="text-accent hover:underline">
                Next →
              </Link>
            ) : (
              <span />
            )}
          </nav>
        )}
      </div>
    </div>
  );
}

function Chip({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      aria-current={active ? "page" : undefined}
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm",
        active ? "border-accent bg-accent/10 text-accent" : "border-border-strong text-text hover:border-accent hover:text-accent",
      )}
    >
      {children}
    </Link>
  );
}
