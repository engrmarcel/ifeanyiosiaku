import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { MediaImage } from "@/components/blocks/MediaImage";
import { SafeHtml } from "@/components/blocks/SafeHtml";
import { getProject } from "@/lib/cms/projects";

/** Case study page. The proxy has already returned a real 404 for unknown projects. */
export const instant = false;

export async function generateMetadata({ params }: PageProps<"/projects/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const project = await getProject(slug);
  if (!project) return {};

  const image = project.cover?.variants.find((v) => v.name === "lg") ?? project.cover;

  return {
    title: project.title,
    description: project.summary ?? undefined,
    alternates: { canonical: project.url },
    openGraph: {
      type: "article",
      title: project.title,
      description: project.summary ?? undefined,
      url: project.url,
      images: image ? [{ url: image.url, width: image.width ?? undefined, height: image.height ?? undefined, alt: project.cover?.alt }] : undefined,
    },
  };
}

export default async function ProjectPage({ params }: PageProps<"/projects/[slug]">) {
  const { slug } = await params;
  const project = await getProject(slug);
  if (!project) notFound();

  const facts = [
    { label: "Role", value: project.role },
    { label: "Client", value: project.client },
    { label: "Period", value: project.period },
  ].filter((f): f is { label: string; value: string } => Boolean(f.value));

  return (
    <article className="flex flex-1 flex-col pb-20">
      <header className="relative isolate overflow-hidden border-b border-border/60">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
        <div className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
          <nav aria-label="Breadcrumb" className="font-mono text-sm text-muted">
            <Link href="/#work" className="text-accent hover:underline">
              work
            </Link>
            <span aria-hidden="true"> / </span>
            <span>{project.slug}</span>
          </nav>
          <h1 className="mt-3 max-w-4xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{project.title}</h1>
          {project.summary && <p className="mt-4 max-w-2xl text-lg text-muted">{project.summary}</p>}
          {(facts.length > 0 || project.technologies.length > 0) && (
            <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4 text-sm">
              {facts.map((fact) => (
                <div key={fact.label}>
                  <dt className="font-mono text-xs text-muted uppercase">{fact.label}</dt>
                  <dd className="mt-1 text-text">{fact.value}</dd>
                </div>
              ))}
              {project.technologies.length > 0 && (
                <div>
                  <dt className="font-mono text-xs text-muted uppercase">Stack</dt>
                  <dd className="mt-1 flex flex-wrap gap-1.5">
                    {project.technologies.map((tech) => (
                      <span key={tech} className="rounded-sm border border-border-strong px-2 py-0.5 font-mono text-xs text-accent">
                        {tech}
                      </span>
                    ))}
                  </dd>
                </div>
              )}
            </dl>
          )}
        </div>
      </header>

      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
        {project.cover && (
          <MediaImage
            media={project.cover}
            priority
            sizes="(min-width: 1152px) 1152px, 100vw"
            className="mt-10 aspect-[16/9] w-full rounded-lg border border-border object-cover object-[50%_25%]"
          />
        )}
        <div className="mt-10 max-w-3xl">
          <SafeHtml html={project.bodyHtml} />
          {project.links.length > 0 && (
            <ul className="mt-10 flex flex-wrap gap-3">
              {project.links.map((link) => (
                <li key={link.url}>
                  <a
                    href={link.url}
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 rounded-md border border-border-strong px-4 py-2 text-sm text-text hover:border-accent hover:text-accent"
                  >
                    {link.label} <span aria-hidden="true">↗</span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </article>
  );
}
