import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { cn } from "@/lib/cn";
import type { BlockProps, ProjectCard } from "@/lib/cms/schemas";
import { MediaImage } from "./MediaImage";
import { SafeHtml } from "./SafeHtml";
import { Section } from "./Section";

type Base = { anchor: string | null; first: boolean };

function SectionHeading({ eyebrow, heading, intro }: { eyebrow: string | null; heading: string; intro: string | null }) {
  return (
    <header className="mb-8 max-w-2xl">
      {eyebrow && <p className="font-mono text-sm text-accent">{eyebrow}</p>}
      <h2 className="mt-2 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{heading}</h2>
      {intro && <p className="mt-3 text-muted">{intro}</p>}
    </header>
  );
}

export function ProfileIntroBlock({ props, anchor }: Base & { props: BlockProps<"profile_intro"> }) {
  const hasPortrait = props.portrait !== null;

  return (
    <Section id={anchor}>
      <div className={cn("grid items-start gap-10", hasPortrait && "md:grid-cols-[minmax(0,320px)_1fr]")}>
        {props.portrait && (
          <MediaImage media={props.portrait} sizes="(min-width: 768px) 320px, 100vw" className="aspect-[4/5] w-full rounded-lg border border-border object-cover object-top" />
        )}
        <div>
          <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
          {props.headline && <p className="text-lg text-text">{props.headline}</p>}
          {props.shortBio && <p className="mt-4 max-w-3xl text-lg leading-relaxed text-muted">{props.shortBio}</p>}
          {props.bioHtml && <SafeHtml html={props.bioHtml} className="mt-4 max-w-3xl" />}
          {props.yearsExperience !== null && (
            <dl className="mt-8 inline-flex flex-col-reverse rounded-lg border border-border bg-surface px-6 py-4">
              <dt className="text-sm text-muted">Years of experience</dt>
              <dd className="font-mono text-3xl font-semibold text-accent">{props.yearsExperience}+</dd>
            </dl>
          )}
        </div>
      </div>
    </Section>
  );
}

export function ExpertiseBlock({ props, anchor }: Base & { props: BlockProps<"expertise"> }) {
  if (props.expertise.length === 0 && props.technologies.length === 0) return null;

  return (
    <Section id={anchor}>
      <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      {props.expertise.length > 0 && (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Areas of expertise">
          {props.expertise.map((item, index) => (
            <li
              key={item.name}
              className="group rounded-lg border border-border bg-surface p-6 transition-[border-color,box-shadow] duration-300 hover:border-accent/50 hover:shadow-glow"
            >
              <p className="font-mono text-xs text-muted">{String(index + 1).padStart(2, "0")}</p>
              <h3 className="mt-2 text-lg font-semibold text-text group-hover:text-accent">{item.name}</h3>
              {item.description && <p className="mt-2 text-sm leading-relaxed text-muted">{item.description}</p>}
            </li>
          ))}
        </ul>
      )}
      {props.technologies.length > 0 && (
        <div className="mt-8">
          <h3 className="font-mono text-sm text-muted">{"// tech stack"}</h3>
          <ul className="mt-3 flex flex-wrap gap-2" aria-label="Technologies">
            {props.technologies.map((tech) => (
              <li key={tech.name} title={tech.description ?? undefined} className="rounded-md border border-border-strong bg-bg px-3 py-1.5 font-mono text-sm text-accent">
                {tech.name}
              </li>
            ))}
          </ul>
        </div>
      )}
    </Section>
  );
}

export function ProjectCardView({ project, headingLevel = 3 }: { project: ProjectCard; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";

  return (
    <article className="group relative flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-0.5 hover:border-accent/50 hover:shadow-glow">
      {project.cover ? (
        <MediaImage media={project.cover} sizes="(min-width: 1024px) 384px, (min-width: 640px) 50vw, 100vw" className="aspect-[16/10] w-full object-cover" />
      ) : (
        <div aria-hidden="true" className="io-grid-backdrop aspect-[16/10] w-full bg-surface-raised" />
      )}
      <div className="flex flex-1 flex-col p-6">
        {(project.role || project.period) && <p className="font-mono text-xs text-accent">{[project.role, project.period].filter(Boolean).join(" · ")}</p>}
        <Heading className="mt-1 text-lg font-semibold text-text">
          <Link href={project.url} className="after:absolute after:inset-0 focus-visible:outline-none">
            {project.title}
          </Link>
        </Heading>
        {project.summary && <p className="mt-2 flex-1 text-sm leading-relaxed text-muted">{project.summary}</p>}
        {project.technologies.length > 0 && (
          <ul className="mt-4 flex flex-wrap gap-1.5" aria-label="Technologies used">
            {project.technologies.slice(0, 5).map((tech) => (
              <li key={tech} className="rounded-sm bg-bg px-2 py-0.5 font-mono text-[11px] text-muted">
                {tech}
              </li>
            ))}
          </ul>
        )}
      </div>
    </article>
  );
}

export function ProjectsBlock({ props, anchor }: Base & { props: BlockProps<"projects"> }) {
  if (props.items.length === 0) return null;

  return (
    <Section id={anchor}>
      <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {props.items.map((project) => (
          <li key={project.slug} className="flex">
            <ProjectCardView project={project} />
          </li>
        ))}
      </ul>
    </Section>
  );
}

export function ServicesBlock({ props, anchor }: Base & { props: BlockProps<"services"> }) {
  if (props.items.length === 0) return null;

  return (
    <Section id={anchor}>
      <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {props.items.map((service) => (
          <li key={service.title} className="flex flex-col rounded-lg border border-border bg-surface p-6">
            <h3 className="text-lg font-semibold text-text">{service.title}</h3>
            {service.summary && <p className="mt-2 flex-1 text-sm leading-relaxed text-muted">{service.summary}</p>}
            {service.cta && (
              <Button asChild variant="secondary" size="sm" className="mt-5 self-start">
                <Link href={service.cta.url}>{service.cta.label}</Link>
              </Button>
            )}
          </li>
        ))}
      </ul>
    </Section>
  );
}

export function ExperienceBlock({ props, anchor }: Base & { props: BlockProps<"experience"> }) {
  if (props.items.length === 0) return null;

  return (
    <Section id={anchor} width="narrow">
      <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      <ol className="relative space-y-8 border-l border-border pl-8">
        {props.items.map((item, index) => (
          <li key={`${item.title}-${index}`} className="relative">
            <span aria-hidden="true" className="absolute top-1.5 -left-[37px] size-3 rounded-full border-2 border-accent bg-bg" />
            {item.period && <p className="font-mono text-xs text-accent">{item.period}</p>}
            <h3 className="mt-1 text-lg font-semibold">{item.title}</h3>
            {(item.organisation || item.location) && <p className="text-sm text-muted">{[item.organisation, item.location].filter(Boolean).join(" · ")}</p>}
            {item.description && <p className="mt-2 text-sm leading-relaxed whitespace-pre-line text-muted">{item.description}</p>}
          </li>
        ))}
      </ol>
    </Section>
  );
}

export function CertificationsBlock({ props, anchor }: Base & { props: BlockProps<"certifications"> }) {
  if (props.items.length === 0) return null;

  return (
    <Section id={anchor}>
      <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {props.items.map((cert) => (
          <li key={cert.name} className="flex items-start gap-4 rounded-lg border border-border bg-surface p-5">
            {cert.media && <MediaImage media={cert.media} sizes="56px" className="size-14 shrink-0 rounded-md object-contain" />}
            <div>
              <h3 className="font-semibold text-text">{cert.name}</h3>
              <p className="text-sm text-muted">{[cert.issuer, cert.issued].filter(Boolean).join(" · ")}</p>
              {cert.url && (
                <a href={cert.url} rel="noopener noreferrer" className="mt-2 inline-block text-sm text-accent hover:underline">
                  Verify credential <span aria-hidden="true">↗</span>
                </a>
              )}
            </div>
          </li>
        ))}
      </ul>
    </Section>
  );
}

export function TestimonialsBlock({ props, anchor }: Base & { props: BlockProps<"testimonials"> }) {
  if (props.items.length === 0) return null;

  return (
    <Section id={anchor}>
      <SectionHeading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      <ul className="grid gap-5 md:grid-cols-2">
        {props.items.map((t, index) => (
          <li key={`${t.author}-${index}`} className="rounded-lg border border-border bg-surface p-6">
            <figure>
              <blockquote className="text-base leading-relaxed text-text">“{t.quote}”</blockquote>
              <figcaption className="mt-5 flex items-center gap-3">
                {t.avatar && <MediaImage media={t.avatar} sizes="40px" className="size-10 rounded-full object-cover" alt="" />}
                <span className="text-sm">
                  <span className="block font-medium text-text">{t.author}</span>
                  {t.title && <span className="text-muted">{t.title}</span>}
                </span>
              </figcaption>
            </figure>
          </li>
        ))}
      </ul>
    </Section>
  );
}
