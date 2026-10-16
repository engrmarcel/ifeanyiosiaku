import type { Metadata } from "next";
import Link from "next/link";
import { ArticleCard } from "@/components/content/ArticleCard";
import { ErrorState } from "@/components/ui/ErrorState";
import { cn } from "@/lib/cn";
import { articlesHref, cleanFilters, getArticles } from "@/lib/content/api";

/** Article list with categories, tags, search and pages. The data is cached until an article changes. */
export const instant = false;

export async function generateMetadata({ searchParams }: PageProps<"/articles">): Promise<Metadata> {
  const filters = cleanFilters(await searchParams);

  return {
    title: "Articles",
    description: "Technical articles on software development, artificial intelligence, business intelligence and data analytics.",
    alternates: { canonical: filters.category || filters.tag ? articlesHref({ category: filters.category, tag: filters.tag }) : "/articles" },
    // Search results and later pages are for visitors, not search engines.
    robots: filters.q || filters.page ? { index: false, follow: true } : undefined,
  };
}

export default async function ArticlesPage({ searchParams }: PageProps<"/articles">) {
  const filters = cleanFilters(await searchParams);
  const list = await getArticles(filters).catch((error: unknown) => {
    console.error("[content] Articles could not be loaded", error);
    return null;
  });

  const filtered = Boolean(filters.category || filters.tag || filters.q);
  const activeTag = list?.filters.tags.find((t) => t.slug === filters.tag);

  return (
    <div className="flex flex-1 flex-col pb-20">
      <header className="relative isolate overflow-hidden border-b border-border/60">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
        <div className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
          <p className="font-mono text-sm text-accent">{"// articles"}</p>
          <h1 className="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Articles</h1>
          <p className="mt-4 max-w-2xl text-lg text-muted">
            Practical writing on software, artificial intelligence, business intelligence and data.
          </p>

          <form action="/articles" method="get" role="search" className="mt-8 flex max-w-xl gap-2">
            {filters.category && <input type="hidden" name="category" value={filters.category} />}
            {filters.tag && <input type="hidden" name="tag" value={filters.tag} />}
            <label htmlFor="article-search" className="sr-only">
              Search articles
            </label>
            <input
              id="article-search"
              type="search"
              name="q"
              defaultValue={filters.q}
              maxLength={100}
              placeholder="Search articles…"
              className="h-11 min-w-0 flex-1 rounded-md border border-border-strong bg-bg px-3 text-text placeholder:text-muted focus:border-accent focus:outline-none"
            />
            <button type="submit" className="h-11 rounded-md bg-accent px-4 text-sm font-medium text-accent-ink hover:bg-accent-strong">
              Search
            </button>
          </form>
        </div>
      </header>

      <div className="mx-auto w-full max-w-6xl px-4 pt-8 sm:px-6">
        {!list ? (
          <ErrorState title="Articles could not be loaded" description="Please try again in a moment." />
        ) : (
          <>
            {list.filters.categories.length > 0 && (
              <nav aria-label="Categories">
                <ul className="flex flex-wrap gap-2">
                  <li>
                    <FilterChip href={articlesHref({ tag: filters.tag, q: filters.q })} active={!filters.category}>
                      All
                    </FilterChip>
                  </li>
                  {list.filters.categories.map((category) => (
                    <li key={category.slug}>
                      <FilterChip href={articlesHref({ category: category.slug, tag: filters.tag, q: filters.q })} active={filters.category === category.slug}>
                        {category.name} <span className="text-muted">{category.count}</span>
                      </FilterChip>
                    </li>
                  ))}
                </ul>
              </nav>
            )}

            {(filters.tag || filters.q) && (
              <p className="mt-6 text-sm text-muted" role="status">
                {list.meta.total} {list.meta.total === 1 ? "article" : "articles"}
                {filters.tag && <> tagged <span className="font-mono text-accent">#{activeTag?.name ?? filters.tag}</span></>}
                {filters.q && <> matching “<span className="text-text">{filters.q}</span>”</>}
                {" · "}
                <Link href={articlesHref({ category: filters.category })} className="text-accent underline underline-offset-2">
                  Clear
                </Link>
              </p>
            )}

            {list.data.length === 0 ? (
              <div className="mt-10 rounded-lg border border-dashed border-border-strong p-10 text-center">
                <p className="text-lg text-text">{filtered ? "No articles match." : "No articles yet."}</p>
                <p className="mt-2 text-sm text-muted">{filtered ? "Try another search or category." : "New articles will appear here."}</p>
              </div>
            ) : (
              <ul className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {list.data.map((article) => (
                  <li key={article.slug}>
                    <ArticleCard article={article} headingLevel={2} />
                  </li>
                ))}
              </ul>
            )}

            {list.meta.lastPage > 1 && (
              <nav aria-label="Pages" className="mt-10 flex items-center justify-between gap-4 font-mono text-sm">
                {list.meta.page > 1 ? (
                  <Link href={articlesHref({ ...filters, page: list.meta.page - 1 })} className="text-accent hover:underline" rel="prev">
                    ← Newer
                  </Link>
                ) : (
                  <span />
                )}
                <span className="text-muted">
                  Page {list.meta.page} of {list.meta.lastPage}
                </span>
                {list.meta.page < list.meta.lastPage ? (
                  <Link href={articlesHref({ ...filters, page: list.meta.page + 1 })} className="text-accent hover:underline" rel="next">
                    Older →
                  </Link>
                ) : (
                  <span />
                )}
              </nav>
            )}
          </>
        )}
      </div>
    </div>
  );
}

function FilterChip({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
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
