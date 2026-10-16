import Link from "next/link";
import { MediaImage } from "@/components/blocks/MediaImage";
import { ShareBar } from "@/components/share/ShareBar";
import { articlesHref } from "@/lib/content/api";
import { formatDate } from "@/lib/content/format";
import type { Article } from "@/lib/content/schemas";
import { ArticleBody } from "./ArticleBody";
import { ArticleCard } from "./ArticleCard";
import { TableOfContents } from "./TableOfContents";

/** Technical article layout: title block, cover, text with highlighted code, contents list, tags, related reading. */
export function ArticleView({ article }: { article: Article }) {
  const published = formatDate(article.publishedAt);
  const updated = formatDate(article.updatedAt);

  return (
    <article className="flex flex-1 flex-col pb-20">
      <header className="relative isolate overflow-hidden border-b border-border/60">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
        <div className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
          <nav aria-label="Breadcrumb" className="font-mono text-sm text-muted">
            <Link href="/articles" className="text-accent hover:underline">
              articles
            </Link>
            <span aria-hidden="true"> / </span>
            <span className="break-all">{article.slug}</span>
          </nav>
          {article.categories.length > 0 && (
            <ul className="mt-5 flex flex-wrap gap-2" aria-label="Categories">
              {article.categories.map((category) => (
                <li key={category.slug}>
                  <Link
                    href={articlesHref({ category: category.slug })}
                    className="rounded-sm border border-border-strong px-2 py-0.5 font-mono text-xs text-accent hover:border-accent"
                  >
                    {category.name}
                  </Link>
                </li>
              ))}
            </ul>
          )}
          <h1 className="mt-4 max-w-4xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{article.title}</h1>
          {article.excerpt && <p className="mt-4 max-w-2xl text-lg text-muted">{article.excerpt}</p>}
          <p className="mt-6 font-mono text-xs text-muted">
            {published && <time dateTime={article.publishedAt ?? undefined}>{published}</time>}
            {published && " · "}
            {article.readingMinutes} min read
            {updated && updated !== published && (
              <>
                {" · updated "}
                <time dateTime={article.updatedAt ?? undefined}>{updated}</time>
              </>
            )}
          </p>
          {!article.draft && <ShareBar path={article.url} title={article.title} className="mt-6" />}
        </div>
      </header>

      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
        {article.cover && (
          <MediaImage
            media={article.cover}
            priority
            sizes="(min-width: 1152px) 1152px, 100vw"
            className="mt-10 aspect-[16/9] w-full rounded-lg border border-border object-cover"
          />
        )}

        <div className="mt-10 grid gap-10 lg:grid-cols-[minmax(0,1fr)_220px]">
          <div className="min-w-0 max-w-3xl">
            {article.toc.length >= 2 && (
              <details className="mb-8 rounded-lg border border-border bg-surface p-4 lg:hidden">
                <summary className="cursor-pointer font-mono text-sm text-text">Contents</summary>
                <TableOfContents items={article.toc} className="mt-3" />
              </details>
            )}
            <ArticleBody segments={article.segments} />

            {article.tags.length > 0 && (
              <ul className="mt-12 flex flex-wrap gap-2 border-t border-border pt-6" aria-label="Tags">
                {article.tags.map((tag) => (
                  <li key={tag.slug}>
                    <Link href={articlesHref({ tag: tag.slug })} className="rounded-sm bg-surface-raised px-2.5 py-1 font-mono text-xs text-muted hover:text-accent">
                      #{tag.name}
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <aside className="hidden lg:block">
            <TableOfContents items={article.toc} className="sticky top-24" />
          </aside>
        </div>

        {article.related.length > 0 && (
          <section aria-labelledby="related-heading" className="mt-16 border-t border-border pt-10">
            <h2 id="related-heading" className="text-2xl font-semibold tracking-tight">
              Related reading
            </h2>
            <ul className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {article.related.map((item) => (
                <li key={item.slug}>
                  <ArticleCard article={item} />
                </li>
              ))}
            </ul>
          </section>
        )}
      </div>
    </article>
  );
}
