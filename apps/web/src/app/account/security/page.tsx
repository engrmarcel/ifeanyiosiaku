import { Suspense } from "react";
import { PasswordSection } from "./PasswordSection";
import { SessionsSection } from "./SessionsSection";
import { TwoFactorSection } from "./TwoFactorSection";

export default function SecurityPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <p className="font-mono text-xs text-accent">$ security --status</p>
        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Security</h1>
        <p className="mt-1 text-sm text-muted">Protect your account and see where you are signed in.</p>
      </div>
      <Suspense>
        <TwoFactorSection />
      </Suspense>
      <SessionsSection />
      <PasswordSection />
    </div>
  );
}
