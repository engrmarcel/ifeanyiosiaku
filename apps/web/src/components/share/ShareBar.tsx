import { siFacebook, siTelegram, siWhatsapp, siX } from "simple-icons";
import { cn } from "@/lib/cn";
import { absoluteUrl } from "@/lib/site/url";
import { ShareActions } from "./ShareActions";
import { shareLinks, type ShareTarget } from "./shareLinks";

/** Official brand shapes from simple-icons. LinkedIn is not offered there, so a plain "in" mark is drawn. */
const ICONS: Record<ShareTarget, React.ReactNode> = {
  linkedin: (
    <>
      <rect x="2" y="2" width="20" height="20" rx="3" fill="currentColor" />
      <g fill="var(--io-bg)">
        <rect x="5.5" y="9.5" width="3" height="9" />
        <circle cx="7" cy="6.6" r="1.75" />
        <path d="M10.5 9.5h2.9v1.3c.5-.9 1.6-1.6 3.1-1.6 2.6 0 3.5 1.7 3.5 4.3v5h-3v-4.5c0-1.2-.3-2.2-1.6-2.2s-1.9 1-1.9 2.2v4.5h-3z" />
      </g>
    </>
  ),
  x: <path d={siX.path} fill="currentColor" />,
  facebook: <path d={siFacebook.path} fill="currentColor" />,
  whatsapp: <path d={siWhatsapp.path} fill="currentColor" />,
  telegram: <path d={siTelegram.path} fill="currentColor" />,
  email: (
    <g fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M3 7l9 6 9-6" />
    </g>
  ),
};

/**
 * Share buttons for a content page (articles, publications; later events, courses and store items).
 * `path` is the page's own address, e.g. "/articles/power-bi-measures".
 */
export function ShareBar({ path, title, className }: { path: string; title: string; className?: string }) {
  const url = absoluteUrl(path);
  const labelId = `share${path.replace(/[^a-z0-9]+/gi, "-")}`;

  return (
    <div className={cn("flex flex-wrap items-center gap-3", className)}>
      <p id={labelId} className="font-mono text-xs tracking-wider text-muted uppercase">
        Share
      </p>
      <ul aria-labelledby={labelId} className="flex flex-wrap items-center gap-2">
        {shareLinks(url, title).map((link) => (
          <li key={link.network}>
            <a
              href={link.href}
              {...(link.network === "email" ? {} : { target: "_blank", rel: "noopener noreferrer" })}
              aria-label={link.network === "email" ? "Share by email" : `Share on ${link.label} (opens in a new tab)`}
              title={link.label}
              className="inline-flex size-9 items-center justify-center rounded-full border border-border-strong text-muted transition-colors hover:border-accent hover:text-accent focus-visible:outline-2 focus-visible:outline-accent"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4">
                {ICONS[link.network]}
              </svg>
            </a>
          </li>
        ))}
        <ShareActions url={url} title={title} />
      </ul>
    </div>
  );
}
