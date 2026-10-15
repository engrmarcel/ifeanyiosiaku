"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { Suspense, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { useAuth } from "@/lib/auth/AuthProvider";
import { useAddon } from "@/lib/settings/SiteSettingsProvider";
import { VerifiedBanner } from "./VerifiedBanner";

export default function AccountPage() {
  const { user, logout } = useAuth();
  const router = useRouter();
  const [signingOut, setSigningOut] = useState(false);
  const commerce = useAddon("commerce");
  const learning = useAddon("learning");

  if (!user) return null;

  async function signOut() {
    setSigningOut(true);
    await logout();
    router.push("/");
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-mono text-xs text-accent">$ whoami</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight">{user.profile?.display_name ?? user.name}</h1>
          <p className="mt-1 text-sm text-muted">{user.email}</p>
        </div>
        <Button variant="secondary" onClick={signOut} loading={signingOut}>
          Sign out
        </Button>
      </div>

      <Suspense>
        <VerifiedBanner />
      </Suspense>

      {!user.email_verified && (
        <Alert tone="warning" title="Confirm your email address">
          Some features stay locked until you confirm your email.{" "}
          <Link href="/verify-email" className="text-accent hover:underline">
            Resend the link
          </Link>
        </Alert>
      )}

      {user.two_factor_required && !user.two_factor_enabled && (
        <Alert tone="warning" title="Two-factor authentication required">
          Staff accounts must turn on two-factor authentication before using the admin panel.{" "}
          <Link href="/account/security?setup=two-factor" className="text-accent hover:underline">
            Set it up now
          </Link>
        </Alert>
      )}

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader title="Security" description="Password, two-factor authentication and signed-in devices." />
          <p className="mb-4 text-sm">
            Two-factor authentication:{" "}
            <span className={user.two_factor_enabled ? "text-accent" : "text-amber"}>{user.two_factor_enabled ? "on" : "off"}</span>
          </p>
          <Button asChild variant="secondary">
            <Link href="/account/security">Manage security</Link>
          </Button>
        </Card>

        {(commerce || learning) && (
          <Card>
            <CardHeader
              title={commerce && learning ? "Learning & purchases" : learning ? "Learning" : "Purchases"}
              description={
                commerce && learning
                  ? "Your courses, downloads and orders will appear here."
                  : learning
                    ? "Your courses and progress will appear here."
                    : "Your downloads and orders will appear here."
              }
            />
            <EmptyState title="Nothing yet" description="These features arrive in upcoming releases." />
          </Card>
        )}
      </div>

      {user.can_access_admin && (
        <Card>
          <CardHeader title="Administration" description="Manage the platform." />
          <Button asChild>
            {/* /admin is served by Laravel through the proxy, so it needs a full page load, not client navigation. */}
            {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
            <a href="/admin">Open admin panel</a>
          </Button>
        </Card>
      )}
    </div>
  );
}
