import { cn } from "@/lib/cn";
import type { Article } from "@/lib/content/schemas";

/** "On this page" list of the article's sections. Hidden for short articles with fewer than two. */
export function TableOfContents({ items, className }: { items: Article["toc"]; className?: string }) {
  if (items.length < 2) return null;

  return (
    <nav aria-label="On this page" className={cn("text-sm", className)}>
      <p className="font-mono text-xs tracking-wider text-muted uppercase">On this page</p>
      <ol className="mt-3 space-y-2 border-l border-border">
        {items.map((item) => (
          <li key={item.id} className={cn(item.level === 3 && "pl-4")}>
            <a href={`#${item.id}`} className="-ml-px block border-l border-transparent pl-3 text-muted hover:border-accent hover:text-accent">
              {item.text}
            </a>
          </li>
        ))}
      </ol>
    </nav>
  );
}
