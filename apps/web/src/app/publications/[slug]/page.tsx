import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { MediaImage } from "@/components/blocks/MediaImage";
import { JsonLd } from "@/components/seo/JsonLd";
import { ShareBar } from "@/components/share/ShareBar";
import { getPublication } from "@/lib/content/api";
import { formatBytes, formatDate } from "@/lib/content/format";
import { publicationSchema } from "@/lib/seo/structuredData";
import { getSiteConfig } from "@/lib/site/config";

export const instant = false;

export async function generateMetadata({ params }: PageProps<"/publications/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const publication = await getPublication(slug);
  if (!publication) return {};

  return {
    title: publication.seo.title || publication.title,
    description: publication.seo.description ?? undefined,
    alternates: { canonical: publication.url },
    robots: publication.seo.noindex ? { index: false, follow: false } : undefined,
    openGraph: { type: "article", title: publication.title, description: publication.seo.description ?? undefined, url: publication.url },
  };
}

export default async function PublicationPage({ params }: PageProps<"/publications/[slug]">) {
  const { slug } = await params;
  const [publication, site] = await Promise.all([getPublication(slug), getSiteConfig()]);
  if (!publication) notFound();

  const [latest, ...earlier] = publication.versions;

  return (
    <article className="flex flex-1 flex-col pb-20">
      <JsonLd data={publicationSchema(publication, site)} />
      <header className="relative isolate overflow-hidden border-b border-border/60">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
        <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-14 sm:px-6 sm:py-20 md:grid-cols-[1fr_auto]">
          <div>
            <nav aria-label="Breadcrumb" className="font-mono text-sm text-muted">
              <Link href="/publications" className="text-accent hover:underline">
                publications
              </Link>
              <span aria-hidden="true"> / </span>
              <span className="break-all">{publication.slug}</span>
            </nav>
            <p className="mt-5 font-mono text-xs text-accent">{publication.typeLabel}</p>
            <h1 className="mt-2 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{publication.title}</h1>
            {publication.authors.length > 0 && <p className="mt-4 text-lg text-muted">{publication.authors.join(", ")}</p>}
            {latest && <VersionActions version={latest} primary />}
            <ShareBar path={publication.url} title={publication.title} className="mt-8" />
          </div>
          {publication.cover && (
            <MediaImage media={publication.cover} priority sizes="240px" className="hidden aspect-[3/4] w-60 rounded-lg border border-border object-cover md:block" />
          )}
        </div>
      </header>

      <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 pt-10 sm:px-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div className="max-w-3xl">
          {publication.abstract && (
            <section aria-labelledby="abstract-heading">
              <h2 id="abstract-heading" className="text-2xl font-semibold tracking-tight">
                {publication.type === "ebook" ? "About this book" : "Abstract"}
              </h2>
              <p className="mt-4 text-lg leading-relaxed whitespace-pre-line text-muted">{publication.abstract}</p>
            </section>
          )}

          {publication.versions.length > 0 && (
            <section aria-labelledby="versions-heading" className="mt-12">
              <h2 id="versions-heading" className="text-2xl font-semibold tracking-tight">
                Versions
              </h2>
              <ol className="mt-6 space-y-4">
                {[latest, ...earlier].filter(Boolean).map((version, index) => (
                  <li key={version.label} className="rounded-lg border border-border bg-surface p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <p className="font-mono text-sm text-text">
                        v{version.label}
                        {index === 0 && <span className="ml-2 rounded-sm bg-accent/15 px-1.5 py-0.5 text-xs text-accent">latest</span>}
                      </p>
                      <p className="font-mono text-xs text-muted">
                        <time dateTime={version.releasedAt}>{formatDate(version.releasedAt)}</time>
                      </p>
                    </div>
                    {version.changelog && <p className="mt-3 text-sm leading-relaxed whitespace-pre-line text-muted">{version.changelog}</p>}
                    {index > 0 && <VersionActions version={version} />}
                  </li>
                ))}
              </ol>
            </section>
          )}
        </div>

        {(publication.doi || publication.isbn || publication.publishedAt) && (
          <aside>
            <dl className="space-y-4 rounded-lg border border-border bg-surface p-5 text-sm">
              {publication.publishedAt && (
                <div>
                  <dt className="font-mono text-xs text-muted uppercase">Published</dt>
                  <dd className="mt-1 text-text">{formatDate(publication.publishedAt)}</dd>
                </div>
              )}
              {publication.doi && (
                <div>
                  <dt className="font-mono text-xs text-muted uppercase">DOI</dt>
                  <dd className="mt-1 break-all">
                    <a href={`https://doi.org/${publication.doi}`} rel="noopener noreferrer" className="text-accent underline underline-offset-2">
                      {publication.doi}
                    </a>
                  </dd>
                </div>
              )}
              {publication.isbn && (
                <div>
                  <dt className="font-mono text-xs text-muted uppercase">ISBN</dt>
                  <dd className="mt-1 font-mono text-text">{publication.isbn}</dd>
                </div>
              )}
            </dl>
          </aside>
        )}
      </div>
    </article>
  );
}

function VersionActions({ version, primary = false }: { version: { label: string; file: { url: string; size: number; name: string } | null; externalUrl: string | null }; primary?: boolean }) {
  if (!version.file && !version.externalUrl) return null;

  return (
    <div className={primary ? "mt-8 flex flex-wrap gap-3" : "mt-4 flex flex-wrap gap-3"}>
      {version.file && (
        <a
          href={version.file.url}
          download
          className={
            primary
              ? "inline-flex h-11 items-center gap-2 rounded-md bg-accent px-5 text-sm font-medium text-accent-ink hover:bg-accent-strong"
              : "inline-flex items-center gap-2 rounded-md border border-border-strong px-3 py-1.5 text-sm text-text hover:border-accent hover:text-accent"
          }
        >
          Download PDF <span className="font-mono text-xs opacity-80">({formatBytes(version.file.size)})</span>
          <span className="sr-only">, version {version.label}</span>
        </a>
      )}
      {version.externalUrl && (
        <a
          href={version.externalUrl}
          rel="noopener noreferrer"
          className={
            primary
              ? "inline-flex h-11 items-center gap-1 rounded-md border border-border-strong px-5 text-sm text-text hover:border-accent hover:text-accent"
              : "inline-flex items-center gap-1 rounded-md border border-border-strong px-3 py-1.5 text-sm text-text hover:border-accent hover:text-accent"
          }
        >
          View at publisher <span aria-hidden="true">↗</span>
          <span className="sr-only">, version {version.label}</span>
        </a>
      )}
    </div>
  );
}
