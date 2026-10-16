import { formatBytes } from "@/lib/content/format";
import type { BlockProps } from "@/lib/cms/schemas";
import { EmbedFrame } from "./EmbedFrame";
import { MermaidDiagram } from "./MermaidDiagram";
import { Section } from "./Section";

type Base = { anchor: string | null; first: boolean };

/** Custom code: shown only through its sandboxed address (nothing renders without one). */
export function RawHtmlBlock({ props, anchor }: Base & { props: BlockProps<"raw_html"> }) {
  if (!props.src) return null;

  return (
    <Section id={anchor}>
      <EmbedFrame src={props.src} title={props.title} height={props.height} />
    </Section>
  );
}

export function DownloadsBlock({ props, anchor }: Base & { props: BlockProps<"downloads"> }) {
  if (props.files.length === 0) return null;

  return (
    <Section id={anchor} width="narrow">
      {props.eyebrow && <p className="font-mono text-sm text-accent">{props.eyebrow}</p>}
      <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{props.heading}</h2>
      {props.intro && <p className="mt-3 text-muted">{props.intro}</p>}
      <ul className="mt-6 divide-y divide-border rounded-lg border border-border bg-surface">
        {props.files.map((file) => (
          <li key={file.url} className="flex flex-wrap items-center justify-between gap-3 p-4">
            <div className="min-w-0">
              <p className="font-medium text-text">{file.name}</p>
              {file.description && <p className="mt-0.5 text-sm text-muted">{file.description}</p>}
            </div>
            <a
              href={file.url}
              download
              className="inline-flex shrink-0 items-center gap-2 rounded-md border border-border-strong px-3 py-1.5 text-sm text-text hover:border-accent hover:text-accent"
            >
              <svg aria-hidden="true" viewBox="0 0 20 20" className="size-4" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <path d="M10 3v10M6 9l4 4 4-4M4 17h12" />
              </svg>
              PDF · {formatBytes(file.size)}
              <span className="sr-only">, download {file.name}</span>
            </a>
          </li>
        ))}
      </ul>
    </Section>
  );
}

export function DiagramBlock({ props, anchor }: Base & { props: BlockProps<"diagram"> }) {
  return (
    <Section id={anchor}>
      <figure className="rounded-lg border border-border bg-surface p-4 sm:p-6">
        <MermaidDiagram source={props.source} description={props.description} />
        {(props.caption || props.showSource) && (
          <figcaption className="mt-4 space-y-3 text-sm text-muted">
            {props.caption && <p>{props.caption}</p>}
            {props.showSource && (
              <details>
                <summary className="cursor-pointer font-mono text-xs">View diagram source</summary>
                <pre className="mt-2 overflow-x-auto rounded-md bg-bg p-3 font-mono text-xs text-text">{props.source}</pre>
              </details>
            )}
          </figcaption>
        )}
      </figure>
    </Section>
  );
}
