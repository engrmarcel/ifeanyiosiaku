import Link from "next/link";
import { MediaImage } from "@/components/blocks/MediaImage";
import { formatDate } from "@/lib/content/format";
import type { ArticleCard as ArticleCardData } from "@/lib/content/schemas";

/** Article summary used in lists, "latest articles" sections and related reading. */
export function ArticleCard({ article, headingLevel = 3 }: { article: ArticleCardData; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const date = formatDate(article.publishedAt);

  return (
    <article className="group relative flex h-full flex-col overflow-hidden rounded-lg border border-border bg-surface transition-[border-color,box-shadow] duration-300 hover:border-accent/50 hover:shadow-glow">
      {article.cover && (
        <MediaImage
          media={article.cover}
          sizes="(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw"
          className="aspect-[16/9] w-full border-b border-border object-cover"
        />
      )}
      <div className="flex flex-1 flex-col p-5">
        {article.categories.length > 0 && (
          <p className="font-mono text-xs text-accent">{article.categories.map((c) => c.name).join(" · ")}</p>
        )}
        <Heading className="mt-2 text-lg font-semibold tracking-tight text-text text-balance group-hover:text-accent">
          {/* The whole card is clickable through this link's stretched area. */}
          <Link href={article.url} className="after:absolute after:inset-0 focus-visible:outline-none">
            {article.title}
          </Link>
        </Heading>
        {article.excerpt && <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-muted">{article.excerpt}</p>}
        <p className="mt-auto pt-4 font-mono text-xs text-muted">
          {date && <time dateTime={article.publishedAt ?? undefined}>{date}</time>}
          {date && " · "}
          {article.readingMinutes} min read
        </p>
      </div>
    </article>
  );
}
