import { BlockRenderer } from "@/components/blocks/BlockRenderer";
import type { CmsPage } from "@/lib/cms/schemas";

/**
 * A CMS page. The standard template shows the page title unless the page opens with a hero block,
 * which carries the page's main heading itself (one H1 per page).
 */
export function CmsPageView({ page }: { page: CmsPage }) {
  const opensWithHero = page.blocks[0]?.type === "hero";
  const showTitle = page.template === "default" && !opensWithHero;

  return (
    <article className="flex flex-1 flex-col pb-16">
      {showTitle && (
        <header className="relative isolate overflow-hidden border-b border-border/60">
          <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
          <div className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
            <p className="font-mono text-sm text-accent">{`// ${page.slug.replace(/\//g, " / ")}`}</p>
            <h1 className="mt-3 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{page.title}</h1>
            {page.seo.description && <p className="mt-4 max-w-2xl text-lg text-muted">{page.seo.description}</p>}
          </div>
        </header>
      )}
      <BlockRenderer blocks={page.blocks} />
    </article>
  );
}
