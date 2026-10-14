"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { AuthShell } from "@/components/auth/AuthShell";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { isServerRendered, safeNext } from "@/lib/auth/safeNext";
import { formErrorMessage } from "@/lib/forms";

export function TwoFactorForm({ next: requestedNext }: { next: string | null }) {
  const router = useRouter();
  const next = safeNext(requestedNext);
  const { refresh } = useAuth();
  const [useRecovery, setUseRecovery] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const value = String(new FormData(event.currentTarget).get("value") ?? "").trim();
    setSubmitting(true);
    setError(null);

    try {
      await api("/api/v1/auth/two-factor-challenge", {
        method: "POST",
        body: useRecovery ? { recovery_code: value } : { code: value.replace(/\s/g, "") },
      });
      await refresh();
      if (isServerRendered(next)) window.location.assign(next);
      else router.push(next);
    } catch (err) {
      setError(
        err instanceof ApiError && err.status === 422
          ? useRecovery
            ? "That recovery code is not valid."
            : "That code is not valid. Check your authenticator app and try again."
          : formErrorMessage(err),
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthShell
      eyebrow="$ auth --2fa"
      title="Two-factor verification"
      description={useRecovery ? "Enter one of your saved recovery codes. Each code works once." : "Enter the 6-digit code from your authenticator app."}
      footer={
        <Link href="/login" className="text-accent hover:underline">
          Start over
        </Link>
      }
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        <TextField
          key={useRecovery ? "recovery" : "code"}
          label={useRecovery ? "Recovery code" : "Authentication code"}
          name="value"
          autoComplete="one-time-code"
          inputMode={useRecovery ? "text" : "numeric"}
          autoFocus
          required
          className="font-mono tracking-widest"
        />
        <Button type="submit" size="lg" loading={submitting}>
          Verify
        </Button>
        <button type="button" onClick={() => { setUseRecovery(!useRecovery); setError(null); }} className="text-sm text-muted hover:text-accent">
          {useRecovery ? "Use an authenticator code instead" : "Lost your device? Use a recovery code"}
        </button>
      </form>
    </AuthShell>
  );
}
