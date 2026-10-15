import Link from "next/link";
import { isExternal, type SiteConfig } from "@/lib/site/shared";

/** Footer: admin-configured links, social profiles and the footer line. */
export function SiteFooter({ site }: { site: SiteConfig }) {
  const { footer } = site.navigation;

  return (
    <footer className="border-t border-border/70">
      <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-10 text-sm text-muted sm:px-6">
        {(footer.length > 0 || site.social.length > 0) && (
          <div className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            {footer.length > 0 && (
              <nav aria-label="Footer">
                <ul className="flex flex-wrap gap-x-6 gap-y-2">
                  {footer.map((link) => (
                    <li key={`${link.label}-${link.url}`}>
                      {isExternal(link.url) ? (
                        <a href={link.url} className="hover:text-accent" {...(link.newTab ? { target: "_blank", rel: "noopener noreferrer" } : { rel: "noopener" })}>
                          {link.label}
                        </a>
                      ) : (
                        <Link href={link.url} className="hover:text-accent" {...(link.newTab ? { target: "_blank" } : {})}>
                          {link.label}
                        </Link>
                      )}
                    </li>
                  ))}
                </ul>
              </nav>
            )}
            {site.social.length > 0 && (
              <ul aria-label="Social profiles" className="flex flex-wrap gap-2">
                {site.social.map((profile) => (
                  <li key={profile.url}>
                    <a
                      href={profile.url}
                      {...(profile.url.startsWith("mailto:") ? {} : { target: "_blank", rel: "me noopener noreferrer" })}
                      className="inline-flex items-center rounded-sm border border-border-strong px-2.5 py-1 font-mono text-xs text-muted hover:border-accent hover:text-accent"
                    >
                      {profile.label}
                      {!profile.url.startsWith("mailto:") && <span className="sr-only"> (opens in a new tab)</span>}
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}

        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <p>
            <span className="font-mono text-accent" aria-hidden="true">
              {"// "}
            </span>
            {site.year !== null && <>© {site.year} </>}
            {site.name}
          </p>
          {site.footerNote && <p className="font-mono text-xs">{site.footerNote}</p>}
        </div>
      </div>
    </footer>
  );
}
