"use client";

import { useSearchParams } from "next/navigation";
import { Alert } from "@/components/ui/Alert";

/** Shown after following the email confirmation link (Fortify redirects with ?verified=1). */
export function VerifiedBanner() {
  if (useSearchParams().get("verified") !== "1") return null;

  return (
    <Alert tone="success" title="Email confirmed">
      Thanks — your email address is confirmed.
    </Alert>
  );
}
