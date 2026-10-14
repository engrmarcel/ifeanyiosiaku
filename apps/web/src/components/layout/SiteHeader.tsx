"use client";

import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { useAuth } from "@/lib/auth/AuthProvider";

/**
 * Minimal header for Milestone 1. Navigation becomes CMS-configurable in Milestone 2.
 */
export function SiteHeader() {
  const { user, status } = useAuth();

  return (
    <header className="sticky top-0 z-30 border-b border-border/70 bg-bg/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6">
        <Link href="/" className="group flex items-center gap-2 font-mono text-sm text-text">
          <span aria-hidden="true" className="text-accent">
            {">_"}
          </span>
          <span className="font-sans font-semibold tracking-tight group-hover:text-accent">Ifeanyi Osiaku</span>
        </Link>

        <nav aria-label="Account" className="flex items-center gap-2">
          {status === "loading" ? (
            <span className="h-8 w-20" aria-hidden="true" />
          ) : user ? (
            <>
              {user.can_access_admin && (
                <Button asChild variant="ghost" size="sm">
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
      </div>
    </header>
  );
}
