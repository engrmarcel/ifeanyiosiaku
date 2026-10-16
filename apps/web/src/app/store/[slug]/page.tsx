import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { MediaImage } from "@/components/blocks/MediaImage";
import { SafeHtml } from "@/components/blocks/SafeHtml";
import { PriceTag } from "@/components/commerce/PriceTag";
import { ProductCard } from "@/components/commerce/ProductCard";
import { JsonLd } from "@/components/seo/JsonLd";
import { ShareBar } from "@/components/share/ShareBar";
import { getProduct, storeHref } from "@/lib/commerce/api";
import { formatBytes, formatDate } from "@/lib/content/format";
import { productSchema } from "@/lib/seo/structuredData";
import { getSiteConfig } from "@/lib/site/config";

export const instant = false;

function currencyParam(value: string | string[] | undefined): string | undefined {
  const one = Array.isArray(value) ? value[0] : value;
  return one && /^[A-Za-z]{3}$/.test(one) ? one.toUpperCase() : undefined;
}

export async function generateMetadata({ params, searchParams }: PageProps<"/store/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const product = await getProduct(slug, currencyParam((await searchParams).currency));
  if (!product) return {};

  const share = product.seo.image ? (product.seo.image.variants.find((v) => v.name === "lg") ?? product.seo.image) : null;

  return {
    title: product.seo.title || product.title,
    description: product.seo.description ?? undefined,
    alternates: { canonical: product.url },
    robots: product.seo.noindex ? { index: false, follow: false } : undefined,
    openGraph: {
      type: "website",
      title: product.title,
      description: product.seo.description ?? undefined,
      url: product.url,
      images: share ? [{ url: share.url, width: share.width ?? undefined, height: share.height ?? undefined, alt: product.seo.image?.alt }] : undefined,
    },
  };
}

export default async function ProductPage({ params, searchParams }: PageProps<"/store/[slug]">) {
  const { slug } = await params;
  const [product, site] = await Promise.all([getProduct(slug, currencyParam((await searchParams).currency)), getSiteConfig()]);
  if (!product) notFound();

  return (
    <article className="flex flex-1 flex-col pb-20">
      <JsonLd data={productSchema(product, site)} />
      <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 pt-10 sm:px-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <div>
          <nav aria-label="Breadcrumb" className="font-mono text-sm text-muted">
            <Link href="/store" className="text-accent hover:underline">
              store
            </Link>
            <span aria-hidden="true"> / </span>
            <span className="break-all">{product.slug}</span>
          </nav>
          {product.cover ? (
            <MediaImage media={product.cover} priority sizes="(min-width: 1024px) 600px, 100vw" className="mt-6 aspect-[4/3] w-full rounded-lg border border-border object-cover" />
          ) : (
            <div aria-hidden="true" className="io-grid-backdrop mt-6 flex aspect-[4/3] items-center justify-center rounded-lg border border-border font-mono text-muted">
              {`{ ${product.typeLabel.toLowerCase()} }`}
            </div>
          )}
          {product.preview && (
            <p className="mt-4 text-sm">
              <a href={product.preview.url} download={product.preview.kind === "pdf" ? true : undefined} className="text-accent underline underline-offset-2">
                {product.preview.kind === "pdf" ? "Download a free sample (PDF)" : "View a free sample"}
              </a>
            </p>
          )}
        </div>

        <div>
          <p className="font-mono text-xs text-accent">{product.typeLabel}</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{product.title}</h1>
          {product.summary && <p className="mt-4 text-lg text-muted">{product.summary}</p>}
          <PriceTag product={product} large className="mt-6" />
          {product.currencies.length > 1 && (
            <p className="mt-2 font-mono text-xs text-muted">
              Prices in{" "}
              {product.currencies.map((c, i) => (
                <span key={c}>
                  {i > 0 && " · "}
                  {c === product.currency ? (
                    <span className="text-text">{c}</span>
                  ) : (
                    <Link href={`${product.url}?currency=${c}`} className="underline underline-offset-2 hover:text-accent">
                      {c}
                    </Link>
                  )}
                </span>
              ))}
            </p>
          )}

          <ShareBar path={product.url} title={product.title} className="mt-8" />

          {product.includes.length > 0 && (
            <section aria-labelledby="includes-heading" className="mt-8 rounded-lg border border-border bg-surface p-5">
              <h2 id="includes-heading" className="font-mono text-xs tracking-wider text-muted uppercase">
                What you get
              </h2>
              <ul className="mt-3 space-y-2 text-sm">
                {product.includes.map((file) => (
                  <li key={file.label} className="flex justify-between gap-3">
                    <span className="text-text">{file.label}</span>
                    <span className="shrink-0 font-mono text-xs text-muted">
                      {file.extension.toUpperCase()} · {formatBytes(file.size)}
                    </span>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {product.categories.length > 0 && (
            <ul className="mt-6 flex flex-wrap gap-2" aria-label="Categories">
              {product.categories.map((c) => (
                <li key={c.slug}>
                  <Link href={storeHref({ category: c.slug })} className="rounded-sm border border-border-strong px-2 py-0.5 font-mono text-xs text-accent hover:border-accent">
                    {c.name}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <div className="mx-auto mt-12 grid w-full max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div className="min-w-0 max-w-3xl">
          {product.descriptionHtml && <SafeHtml html={product.descriptionHtml} />}

          {product.bundle.length > 0 && (
            <section aria-labelledby="bundle-heading" className="mt-12">
              <h2 id="bundle-heading" className="text-2xl font-semibold tracking-tight">
                Included in this bundle
              </h2>
              <ul className="mt-6 grid gap-5 sm:grid-cols-2">
                {product.bundle.map((item) => (
                  <li key={item.id}>
                    <ProductCard product={item} />
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>

        <aside className="space-y-6">
          {product.versions.length > 0 && (
            <section aria-labelledby="versions-heading" className="rounded-lg border border-border bg-surface p-5">
              <h2 id="versions-heading" className="font-mono text-xs tracking-wider text-muted uppercase">
                Versions
              </h2>
              <ol className="mt-3 space-y-3 text-sm">
                {product.versions.map((v, i) => (
                  <li key={v.label}>
                    <p className="font-mono text-text">
                      v{v.label}
                      {i === 0 && <span className="ml-2 rounded-sm bg-accent/15 px-1.5 py-0.5 text-xs text-accent">latest</span>}
                      <span className="ml-2 text-xs text-muted">{formatDate(v.releasedAt)}</span>
                    </p>
                    {v.changelog && <p className="mt-1 whitespace-pre-line text-muted">{v.changelog}</p>}
                  </li>
                ))}
              </ol>
            </section>
          )}
          {product.licenceTerms && (
            <section aria-labelledby="licence-heading" className="rounded-lg border border-border bg-surface p-5">
              <h2 id="licence-heading" className="font-mono text-xs tracking-wider text-muted uppercase">
                Licence
              </h2>
              <p className="mt-3 text-sm whitespace-pre-line text-muted">{product.licenceTerms}</p>
            </section>
          )}
        </aside>
      </div>

      {product.related.length > 0 && (
        <section aria-labelledby="related-heading" className="mx-auto mt-16 w-full max-w-6xl border-t border-border px-4 pt-10 sm:px-6">
          <h2 id="related-heading" className="text-2xl font-semibold tracking-tight">
            You may also like
          </h2>
          <ul className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {product.related.map((item) => (
              <li key={item.id}>
                <ProductCard product={item} />
              </li>
            ))}
          </ul>
        </section>
      )}
    </article>
  );
}
