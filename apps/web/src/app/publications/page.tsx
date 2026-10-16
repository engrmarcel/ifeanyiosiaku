import type { Metadata } from "next";
import Link from "next/link";
import { PublicationCard } from "@/components/content/PublicationCard";
import { ErrorState } from "@/components/ui/ErrorState";
import { cn } from "@/lib/cn";
import { getPublications } from "@/lib/content/api";

export const instant = false;

export const metadata: Metadata = {
  title: "Publications",
  description: "eBooks, research, papers and technical documents by Ifeanyi Osiaku.",
  alternates: { canonical: "/publications" },
};

export default async function PublicationsPage({ searchParams }: PageProps<"/publications">) {
  const { type } = await searchParams;
  const selected = typeof type === "string" ? type : undefined;
  const list = await getPublications(selected).catch((error: unknown) => {
    console.error("[content] Publications could not be loaded", error);
    return null;
  });

  return (
    <div className="flex flex-1 flex-col pb-20">
      <header className="relative isolate overflow-hidden border-b border-border/60">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
        <div className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
          <p className="font-mono text-sm text-accent">{"// publications"}</p>
          <h1 className="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Publications</h1>
          <p className="mt-4 max-w-2xl text-lg text-muted">eBooks, research, papers and technical documents.</p>
        </div>
      </header>

      <div className="mx-auto w-full max-w-4xl px-4 pt-8 sm:px-6">
        {!list ? (
          <ErrorState title="Publications could not be loaded" description="Please try again in a moment." />
        ) : (
          <>
            {list.filters.types.length > 1 && (
              <nav aria-label="Publication types">
                <ul className="flex flex-wrap gap-2">
                  {[{ type: undefined, label: "All" }, ...list.filters.types].map((option) => {
                    const active = list.filters.active.type === (option.type ?? null);
                    return (
                      <li key={option.label}>
                        <Link
                          href={option.type ? `/publications?type=${option.type}` : "/publications"}
                          aria-current={active ? "page" : undefined}
                          className={cn(
                            "inline-flex rounded-full border px-3 py-1 text-sm",
                            active ? "border-accent bg-accent/10 text-accent" : "border-border-strong text-text hover:border-accent hover:text-accent",
                          )}
                        >
                          {option.label}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </nav>
            )}

            {list.data.length === 0 ? (
              <div className="mt-10 rounded-lg border border-dashed border-border-strong p-10 text-center">
                <p className="text-lg text-text">No publications yet.</p>
                <p className="mt-2 text-sm text-muted">New publications will appear here.</p>
              </div>
            ) : (
              <ul className="mt-8 space-y-4">
                {list.data.map((publication) => (
                  <li key={publication.slug}>
                    <PublicationCard publication={publication} />
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>
    </div>
  );
}
