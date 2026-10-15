import Link from "next/link";
import { HighlightedCode } from "@/components/code/HighlightedCode";
import { TerminalPanel } from "@/components/terminal/TerminalPanel";
import { Accordion } from "@/components/ui/Accordion";
import { Button } from "@/components/ui/Button";
import { Tabs } from "@/components/ui/Tabs";
import { cn } from "@/lib/cn";
import type { BlockProps } from "@/lib/cms/schemas";
import { MediaImage } from "./MediaImage";
import { SafeHtml } from "./SafeHtml";
import { Section } from "./Section";
import { VideoEmbed } from "./VideoEmbed";

type Base = { anchor: string | null; first: boolean };

/** Internal paths use client navigation; everything else is a normal link. */
function SmartLink({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) {
  const external = /^https?:\/\//.test(href) || href.startsWith("mailto:");
  return external ? (
    <a href={href} className={className} rel={href.startsWith("mailto:") ? undefined : "noopener noreferrer"}>
      {children}
    </a>
  ) : (
    <Link href={href} className={className}>
      {children}
    </Link>
  );
}

function CtaButton({ link, variant = "primary" }: { link: { label: string; url: string }; variant?: "primary" | "secondary" }) {
  return (
    <Button asChild size="lg" variant={variant}>
      <SmartLink href={link.url}>{link.label}</SmartLink>
    </Button>
  );
}

export function HeroBlock({ props, anchor, first }: Base & { props: BlockProps<"hero"> }) {
  const HeadingTag = first ? "h1" : "h2";

  return (
    <section id={anchor ?? undefined} className="relative isolate overflow-hidden">
      <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute top-[-20%] left-1/2 -z-10 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(124_92_252/0.22),transparent)] blur-2xl"
      />
      <div
        className={cn(
          "mx-auto grid w-full max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:items-center lg:py-24",
          (props.media || props.terminal) && "lg:grid-cols-[1.1fr_1fr]",
          props.media && props.terminal && "xl:pb-40",
        )}
      >
        <div>
          {props.eyebrow && <p className="font-mono text-sm text-accent">{props.eyebrow}</p>}
          <HeadingTag className="mt-4 text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
            {props.heading}
          </HeadingTag>
          {props.subheading && <p className="mt-4 text-lg text-muted sm:text-xl">{props.subheading}</p>}
          {props.body && <p className="mt-6 max-w-xl text-base leading-relaxed text-muted">{props.body}</p>}
          {(props.primary || props.secondary) && (
            <div className="mt-10 flex flex-wrap gap-3">
              {props.primary && <CtaButton link={props.primary} />}
              {props.secondary && <CtaButton link={props.secondary} variant="secondary" />}
            </div>
          )}
        </div>

        {props.media ? (
          <div className="relative mx-auto w-full max-w-md lg:max-w-none">
            <figure className="relative overflow-hidden rounded-lg border border-border-strong bg-surface shadow-[0_40px_120px_-50px_rgb(124_92_252/0.55)]">
              <MediaImage
                media={props.media}
                priority={first}
                sizes="(min-width: 1024px) 480px, (min-width: 640px) 448px, 100vw"
                className="aspect-[4/5] w-full object-cover object-top"
              />
              <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-bg/80 to-transparent" />
            </figure>
            {props.terminal && props.terminal.length > 0 && (
              <div className="relative -mt-16 px-4 sm:px-8 xl:absolute xl:-bottom-28 xl:-left-20 xl:mt-0 xl:w-[23rem] xl:px-0">
                <TerminalPanel title="~/ifeanyi" lines={props.terminal} />
              </div>
            )}
          </div>
        ) : (
          props.terminal && props.terminal.length > 0 && <TerminalPanel title="~/ifeanyi" lines={props.terminal} />
        )}
      </div>
    </section>
  );
}

export function HeadingBlock({ props, anchor }: Base & { props: BlockProps<"heading"> }) {
  const Tag = props.level === 3 ? "h3" : "h2";
  return (
    <Section id={anchor} className={cn("pb-2", props.align === "center" && "text-center")}>
      {props.eyebrow && <p className="font-mono text-sm text-accent">{props.eyebrow}</p>}
      <Tag className={cn("mt-2 font-semibold tracking-tight text-balance", props.level === 3 ? "text-2xl" : "text-3xl sm:text-4xl")}>
        {props.text}
      </Tag>
      {props.intro && <p className={cn("mt-3 max-w-2xl text-muted", props.align === "center" && "mx-auto")}>{props.intro}</p>}
    </Section>
  );
}

export function RichTextBlock({ props, anchor }: Base & { props: BlockProps<"rich_text"> }) {
  return (
    <Section id={anchor} width={props.width === "wide" ? "wide" : "narrow"}>
      <SafeHtml html={props.html} />
    </Section>
  );
}

export function ImageBlock({ props, anchor, first }: Base & { props: BlockProps<"image"> }) {
  if (!props.media) return null;
  return (
    <Section id={anchor} width={props.size === "normal" ? "narrow" : props.size === "wide" ? "wide" : "full"}>
      <figure>
        <MediaImage
          media={props.media}
          priority={first}
          sizes={props.size === "full" ? "100vw" : props.size === "wide" ? "(min-width: 1152px) 1152px, 100vw" : "(min-width: 768px) 768px, 100vw"}
          className={cn("w-full", props.size !== "full" && "rounded-lg border border-border")}
        />
        {props.caption && <figcaption className="mt-3 text-center text-sm text-muted">{props.caption}</figcaption>}
      </figure>
    </Section>
  );
}

const gridCols: Record<number, string> = { 2: "sm:grid-cols-2", 3: "sm:grid-cols-2 lg:grid-cols-3", 4: "sm:grid-cols-2 lg:grid-cols-4" };

export function GalleryBlock({ props, anchor }: Base & { props: BlockProps<"gallery"> }) {
  return (
    <Section id={anchor}>
      <ul className={cn("grid gap-4", gridCols[props.columns] ?? gridCols[3])}>
        {props.images.map((image) => (
          <li key={image.media.id}>
            <figure>
              <MediaImage media={image.media} sizes="(min-width: 1024px) 384px, (min-width: 640px) 50vw, 100vw" className="aspect-[4/3] w-full rounded-lg border border-border object-cover" />
              {image.caption && <figcaption className="mt-2 text-sm text-muted">{image.caption}</figcaption>}
            </figure>
          </li>
        ))}
      </ul>
    </Section>
  );
}

export function CardsBlock({ props, anchor }: Base & { props: BlockProps<"cards"> }) {
  return (
    <Section id={anchor}>
      <ul className={cn("grid gap-5", gridCols[props.columns] ?? gridCols[3])}>
        {props.items.map((item, index) => (
          <li
            key={`${item.title}-${index}`}
            className="group relative flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-[border-color,box-shadow] duration-300 hover:border-accent/50 hover:shadow-glow"
          >
            {item.media && <MediaImage media={item.media} sizes="(min-width: 1024px) 384px, 100vw" className="aspect-[16/9] w-full object-cover" />}
            <div className="flex flex-1 flex-col p-6">
              {item.eyebrow && <p className="font-mono text-xs text-accent">{item.eyebrow}</p>}
              <h3 className="mt-1 text-lg font-semibold text-text">{item.title}</h3>
              {item.body && <p className="mt-2 flex-1 text-sm leading-relaxed text-muted">{item.body}</p>}
              {item.url && (
                <SmartLink href={item.url} className="mt-4 text-sm font-medium text-accent after:absolute after:inset-0 hover:underline">
                  {item.linkLabel || "Learn more"} <span aria-hidden="true">→</span>
                </SmartLink>
              )}
            </div>
          </li>
        ))}
      </ul>
    </Section>
  );
}

export function TwoColumnBlock({ props, anchor }: Base & { props: BlockProps<"two_column"> }) {
  const cols = props.ratio === "2:1" ? "md:grid-cols-[2fr_1fr]" : props.ratio === "1:2" ? "md:grid-cols-[1fr_2fr]" : "md:grid-cols-2";
  const column = (side: BlockProps<"two_column">["left"]) =>
    side.media ? (
      <MediaImage media={side.media} sizes="(min-width: 768px) 50vw, 100vw" className="w-full rounded-lg border border-border" />
    ) : (
      <SafeHtml html={side.html} />
    );

  return (
    <Section id={anchor}>
      <div className={cn("grid items-start gap-10", cols)}>
        <div>{column(props.left)}</div>
        <div>{column(props.right)}</div>
      </div>
    </Section>
  );
}

const statCols: Record<number, string> = {
  1: "grid-cols-1 sm:max-w-sm",
  2: "sm:grid-cols-2",
  3: "sm:grid-cols-3",
  4: "sm:grid-cols-2 lg:grid-cols-4",
};

export function StatsBlock({ props, anchor }: Base & { props: BlockProps<"stats"> }) {
  return (
    <Section id={anchor}>
      <dl className={cn("grid gap-4", statCols[Math.min(props.items.length, 4)] ?? statCols[4])}>
        {props.items.map((item) => (
          <div key={item.label} className="flex flex-col-reverse gap-1 rounded-lg border border-border bg-surface p-6">
            <dt className="text-sm text-muted">
              {item.label}
              {item.description && <span className="mt-1 block text-xs text-muted/80">{item.description}</span>}
            </dt>
            <dd className="font-mono text-3xl font-semibold text-accent">{item.value}</dd>
          </div>
        ))}
      </dl>
    </Section>
  );
}

export function TimelineBlock({ props, anchor }: Base & { props: BlockProps<"timeline"> }) {
  return (
    <Section id={anchor} width="narrow">
      <ol className="relative space-y-8 border-l border-border pl-8">
        {props.items.map((item, index) => (
          <li key={`${item.when}-${index}`} className="relative">
            <span aria-hidden="true" className="absolute top-1.5 -left-[37px] size-3 rounded-full border-2 border-accent bg-bg" />
            <p className="font-mono text-xs text-accent">{item.when}</p>
            <h3 className="mt-1 text-lg font-semibold">{item.title}</h3>
            {item.subtitle && <p className="text-sm text-muted">{item.subtitle}</p>}
            {item.body && <p className="mt-2 text-sm leading-relaxed text-muted">{item.body}</p>}
          </li>
        ))}
      </ol>
    </Section>
  );
}

export function AccordionBlock({ props, anchor }: Base & { props: BlockProps<"accordion"> }) {
  return (
    <Section id={anchor} width="narrow">
      <Accordion items={props.items.map((item, i) => ({ value: `item-${i}`, title: item.title, content: <SafeHtml html={item.html} /> }))} />
    </Section>
  );
}

export function TabsBlock({ props, anchor }: Base & { props: BlockProps<"tabs"> }) {
  return (
    <Section id={anchor} width="narrow">
      <Tabs label={props.label} items={props.items.map((item, i) => ({ value: `tab-${i}`, label: item.title, content: <SafeHtml html={item.html} /> }))} />
    </Section>
  );
}

export function CodeBlockView({ props, anchor }: Base & { props: BlockProps<"code"> }) {
  return (
    <Section id={anchor} width="narrow">
      <HighlightedCode
        code={props.code}
        language={props.language}
        title={props.title}
        lineNumbers={props.lineNumbers}
        copyButton={props.copyButton}
        highlightLines={props.highlight}
        annotations={props.annotations}
        caption={props.caption}
      />
    </Section>
  );
}

export function TerminalBlock({ props, anchor }: Base & { props: BlockProps<"terminal"> }) {
  return (
    <Section id={anchor} width="narrow">
      <TerminalPanel title={props.title} lines={props.lines} animate={props.animate} />
    </Section>
  );
}

const calloutTones = {
  info: { border: "border-violet/50", label: "Note", marker: "text-violet-text" },
  success: { border: "border-accent/50", label: "Tip", marker: "text-accent" },
  warning: { border: "border-amber/50", label: "Warning", marker: "text-amber" },
  danger: { border: "border-danger/60", label: "Important", marker: "text-danger" },
} as const;

export function CalloutBlock({ props, anchor }: Base & { props: BlockProps<"callout"> }) {
  const tone = calloutTones[props.tone];
  return (
    <Section id={anchor} width="narrow">
      <aside className={cn("rounded-lg border-l-4 bg-surface px-5 py-4", tone.border)} aria-label={props.title ?? tone.label}>
        <p className={cn("font-mono text-xs tracking-wide uppercase", tone.marker)}>{props.title ?? tone.label}</p>
        <SafeHtml html={props.html} className="mt-2" />
      </aside>
    </Section>
  );
}

export function QuoteBlock({ props, anchor }: Base & { props: BlockProps<"quote"> }) {
  return (
    <Section id={anchor} width="narrow">
      <figure className="border-l-2 border-accent pl-6">
        <blockquote className="text-xl leading-relaxed text-balance text-text sm:text-2xl">“{props.text}”</blockquote>
        {(props.attribution || props.role) && (
          <figcaption className="mt-4 text-sm text-muted">
            {props.attribution && <span className="font-medium text-text">{props.attribution}</span>}
            {props.attribution && props.role && " · "}
            {props.role}
          </figcaption>
        )}
      </figure>
    </Section>
  );
}

export function VideoBlock({ props, anchor }: Base & { props: BlockProps<"video"> }) {
  if (!props.provider || !props.videoId) return null;
  return (
    <Section id={anchor} width="narrow">
      <figure>
        <VideoEmbed provider={props.provider} videoId={props.videoId} title={props.title} />
        {props.caption && <figcaption className="mt-3 text-center text-sm text-muted">{props.caption}</figcaption>}
      </figure>
    </Section>
  );
}

export function TableBlock({ props, anchor }: Base & { props: BlockProps<"table"> }) {
  return (
    <Section id={anchor} width="narrow">
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full border-collapse text-left text-sm">
          <caption className="border-b border-border px-4 py-3 text-left text-sm font-medium text-text">{props.caption}</caption>
          {props.header && (
            <thead className="bg-surface-raised">
              <tr>
                {props.header.map((cell, i) => (
                  <th key={i} scope="col" className="px-4 py-3 font-semibold text-text">
                    {cell}
                  </th>
                ))}
              </tr>
            </thead>
          )}
          <tbody className="divide-y divide-border">
            {props.rows.map((row, r) => (
              <tr key={r} className="bg-surface">
                {row.map((cell, c) => (
                  <td key={c} className="px-4 py-3 text-muted">
                    {cell}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Section>
  );
}

export function CtaBlock({ props, anchor }: Base & { props: BlockProps<"cta"> }) {
  return (
    <Section id={anchor}>
      <div className="relative overflow-hidden rounded-lg border border-border-strong bg-surface px-6 py-12 text-center shadow-[0_30px_100px_-60px_rgb(163_247_191/0.5)] sm:px-12">
        <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0" />
        <div className="relative">
          {props.eyebrow && <p className="font-mono text-sm text-accent">{props.eyebrow}</p>}
          <h2 className="mt-2 text-3xl font-semibold tracking-tight text-balance">{props.heading}</h2>
          {props.body && <p className="mx-auto mt-3 max-w-xl text-muted">{props.body}</p>}
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <CtaButton link={props.primary} />
            {props.secondary && <CtaButton link={props.secondary} variant="secondary" />}
          </div>
        </div>
      </div>
    </Section>
  );
}

export function DividerBlock({ props, anchor }: Base & { props: BlockProps<"divider"> }) {
  const space = { sm: "py-2", md: "py-6", lg: "py-12" }[props.size];
  return (
    <div id={anchor ?? undefined} className={cn("mx-auto w-full max-w-6xl px-4 sm:px-6", space)}>
      {props.style === "line" ? <hr className="border-border" /> : <div aria-hidden="true" />}
    </div>
  );
}
