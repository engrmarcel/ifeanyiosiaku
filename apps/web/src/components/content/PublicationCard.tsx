import Link from "next/link";
import { MediaImage } from "@/components/blocks/MediaImage";
import { formatDate } from "@/lib/content/format";
import type { PublicationCard as PublicationCardData } from "@/lib/content/schemas";

export function PublicationCard({ publication }: { publication: PublicationCardData }) {
  const date = formatDate(publication.publishedAt);

  return (
    <article className="group relative flex gap-5 rounded-lg border border-border bg-surface p-5 transition-[border-color,box-shadow] duration-300 hover:border-accent/50 hover:shadow-glow">
      {publication.cover && (
        <MediaImage media={publication.cover} sizes="96px" className="hidden aspect-[3/4] w-24 shrink-0 rounded-sm border border-border object-cover sm:block" />
      )}
      <div className="min-w-0 flex-1">
        <p className="font-mono text-xs text-accent">
          {publication.typeLabel}
          {publication.latestVersion && <span className="text-muted"> · v{publication.latestVersion}</span>}
        </p>
        <h2 className="mt-1 text-lg font-semibold tracking-tight text-text text-balance group-hover:text-accent">
          <Link href={publication.url} className="after:absolute after:inset-0 focus-visible:outline-none">
            {publication.title}
          </Link>
        </h2>
        {publication.authors.length > 0 && <p className="mt-1 text-sm text-muted">{publication.authors.join(", ")}</p>}
        {publication.summary && <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-muted">{publication.summary}</p>}
        {date && (
          <p className="mt-3 font-mono text-xs text-muted">
            <time dateTime={publication.publishedAt ?? undefined}>{date}</time>
          </p>
        )}
      </div>
    </article>
  );
}
