"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { AuthShell } from "@/components/auth/AuthShell";
import { CaptchaNotice } from "@/components/auth/CaptchaNotice";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { isServerRendered, safeNext } from "@/lib/auth/safeNext";
import { useCaptcha } from "@/lib/captcha/useCaptcha";
import { formErrorMessage, honeypotValue } from "@/lib/forms";

export function LoginForm({ next: requestedNext, reason, reset }: { next: string | null; reason: string | null; reset: boolean }) {
  const router = useRouter();
  const next = safeNext(requestedNext);
  const { refresh } = useAuth();
  const captcha = useCaptcha("login");

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string | undefined>>({});

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    try {
      const body: Record<string, unknown> = {
        email: data.get("email"),
        password: data.get("password"),
        remember: data.get("remember") === "on",
        [captcha.honeypotField]: honeypotValue(form, captcha.honeypotField),
      };

      // A CAPTCHA is only requested after repeated failed attempts from this network.
      const latest = await captcha.refreshConfig();
      if (latest.login_requires_captcha) body[captcha.tokenField] = await captcha.getToken();

      const result = await api<{ two_factor?: boolean }>("/api/v1/auth/login", { method: "POST", body });

      if (result?.two_factor) {
        router.push(`/two-factor?next=${encodeURIComponent(next)}`);
        return;
      }

      await refresh();
      if (isServerRendered(next)) window.location.assign(next);
      else router.push(next);
    } catch (err) {
      if (err instanceof ApiError) setFieldErrors({ email: err.field("email"), password: err.field("password") });
      setError(err instanceof ApiError && err.code === "validation_failed" ? (err.field("email") ?? formErrorMessage(err)) : formErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthShell
      eyebrow="$ auth --login"
      title="Welcome back"
      description="Sign in to your courses, purchases and community."
      footer={
        <>
          New here?{" "}
          <Link href="/register" className="text-accent hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      {reason === "account_inactive" && (
        <Alert tone="warning" title="Your session has ended" className="mb-5">
          This account is not active. Please contact support if you think this is a mistake.
        </Alert>
      )}
      {reset && (
        <Alert tone="success" title="Password updated" className="mb-5">
          You can now sign in with your new password.
        </Alert>
      )}

      <form onSubmit={onSubmit} noValidate className="relative flex flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        <TextField label="Email" name="email" type="email" autoComplete="email" required error={fieldErrors.email && fieldErrors.email !== error ? fieldErrors.email : undefined} />
        <TextField label="Password" name="password" type="password" autoComplete="current-password" required error={fieldErrors.password} />

        <div className="flex items-center justify-between text-sm">
          <label className="flex items-center gap-2 text-muted">
            <input type="checkbox" name="remember" className="size-4 accent-[var(--io-accent)]" />
            Keep me signed in
          </label>
          <Link href="/forgot-password" className="text-accent hover:underline">
            Forgot password?
          </Link>
        </div>

        <CaptchaNotice status={captcha.status} driver={captcha.loginRequiresCaptcha ? captcha.config?.driver : undefined} widgetRef={captcha.widgetRef} honeypotField={captcha.honeypotField} />

        <Button type="submit" size="lg" loading={submitting}>
          Sign in
        </Button>
      </form>
    </AuthShell>
  );
}
