import Link from "next/link";
import { ArticleCard } from "@/components/content/ArticleCard";
import type { BlockProps } from "@/lib/cms/schemas";
import { Section } from "./Section";

type Base = { anchor: string | null; first: boolean };

/** "Latest articles" section. Renders nothing until an article is published. */
export function ArticlesBlock({ props, anchor }: Base & { props: BlockProps<"articles"> }) {
  if (props.items.length === 0) return null;

  return (
    <Section id={anchor}>
      <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div className="max-w-2xl">
          {props.eyebrow && <p className="font-mono text-sm text-accent">{props.eyebrow}</p>}
          <h2 className="mt-2 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{props.heading}</h2>
          {props.intro && <p className="mt-3 text-muted">{props.intro}</p>}
        </div>
        {props.allUrl && (
          <Link href={props.allUrl} className="font-mono text-sm text-accent hover:underline">
            All articles →
          </Link>
        )}
      </header>
      <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {props.items.map((article) => (
          <li key={article.slug}>
            <ArticleCard article={article} />
          </li>
        ))}
      </ul>
    </Section>
  );
}
