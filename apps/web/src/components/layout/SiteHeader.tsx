"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { Button } from "@/components/ui/Button";
import { useAuth } from "@/lib/auth/AuthProvider";
import { cn } from "@/lib/cn";
import { isExternal, type NavLink } from "@/lib/site/shared";

type SiteHeaderProps = {
  name: string;
  links: NavLink[];
};

/** Site header: name, the admin-configured menu (a toggle on phones) and account links. */
export function SiteHeader({ name, links }: SiteHeaderProps) {
  const { user, status } = useAuth();
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const menuId = useId();

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => event.key === "Escape" && setOpen(false);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open]);

  const close = () => setOpen(false);

  return (
    <header className="sticky top-0 z-30 border-b border-border/70 bg-bg/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <Link href="/" onClick={close} className="group flex shrink-0 items-center gap-2 font-mono text-sm text-text">
          <span aria-hidden="true" className="text-accent">
            {">_"}
          </span>
          <span className="font-sans font-semibold tracking-tight group-hover:text-accent">{name}</span>
        </Link>

        {links.length > 0 && (
          <nav aria-label="Main" className="hidden flex-1 justify-center md:flex">
            <ul className="flex items-center gap-1">
              {links.map((link) => (
                <li key={`${link.label}-${link.url}`}>
                  <NavItem link={link} pathname={pathname} className="rounded-md px-3 py-2 text-sm text-muted hover:bg-surface hover:text-text" />
                </li>
              ))}
            </ul>
          </nav>
        )}

        <div className="flex items-center gap-2">
          <nav aria-label="Account" className="flex items-center gap-2">
            {status === "loading" ? (
              <span className="h-8 w-20" aria-hidden="true" />
            ) : user ? (
              <>
                {user.can_access_admin && (
                  <Button asChild variant="ghost" size="sm">
                    {/* Served by Laravel through the proxy: needs a full page load. */}
                    {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
                    <a href="/admin">Admin</a>
                  </Button>
                )}
                <Button asChild variant="secondary" size="sm">
                  <Link href="/account">Account</Link>
                </Button>
              </>
            ) : (
              <>
                <Button asChild variant="ghost" size="sm">
                  <Link href="/login">Sign in</Link>
                </Button>
                <Button asChild size="sm">
                  <Link href="/register">Join</Link>
                </Button>
              </>
            )}
          </nav>

          {links.length > 0 && (
            <button
              type="button"
              className="inline-flex size-9 items-center justify-center rounded-md border border-border-strong text-text hover:border-accent hover:text-accent md:hidden"
              aria-expanded={open}
              aria-controls={menuId}
              onClick={() => setOpen((value) => !value)}
            >
              <span className="sr-only">{open ? "Close menu" : "Open menu"}</span>
              <svg aria-hidden="true" viewBox="0 0 20 20" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
                {open ? <path d="M5 5l10 10M15 5L5 15" /> : <path d="M3 6h14M3 10h14M3 14h14" />}
              </svg>
            </button>
          )}
        </div>
      </div>

      {links.length > 0 && (
        <nav id={menuId} aria-label="Main" hidden={!open} className="border-t border-border/70 bg-bg md:hidden">
          <ul className="mx-auto flex w-full max-w-6xl flex-col px-4 py-2 sm:px-6">
            {links.map((link) => (
              <li key={`${link.label}-${link.url}`}>
                <NavItem link={link} pathname={pathname} onClick={close} className="block rounded-md px-2 py-3 text-base text-text hover:bg-surface hover:text-accent" />
              </li>
            ))}
          </ul>
        </nav>
      )}
    </header>
  );
}

function NavItem({ link, pathname, className, onClick }: { link: NavLink; pathname: string; className: string; onClick?: () => void }) {
  const current = link.url === pathname;
  const classes = cn(className, current && "text-accent");

  if (isExternal(link.url)) {
    return (
      <a href={link.url} className={classes} onClick={onClick} {...(link.newTab ? { target: "_blank", rel: "noopener noreferrer" } : { rel: "noopener" })}>
        {link.label}
        {link.newTab && <span className="sr-only"> (opens in a new tab)</span>}
      </a>
    );
  }

  return (
    <Link href={link.url} className={classes} onClick={onClick} aria-current={current ? "page" : undefined} {...(link.newTab ? { target: "_blank" } : {})}>
      {link.label}
      {link.newTab && <span className="sr-only"> (opens in a new tab)</span>}
    </Link>
  );
}
