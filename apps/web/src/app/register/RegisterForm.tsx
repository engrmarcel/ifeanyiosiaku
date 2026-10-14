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
import { useCaptcha } from "@/lib/captcha/useCaptcha";
import { formErrorMessage, honeypotValue } from "@/lib/forms";

export function RegisterForm() {
  const router = useRouter();
  const { refresh } = useAuth();
  const captcha = useCaptcha("register");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string | undefined>>({});

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setSubmitting(true);
    setError(null);
    setFields({});

    try {
      await api("/api/v1/auth/register", {
        method: "POST",
        body: {
          name: data.get("name"),
          email: data.get("email"),
          password: data.get("password"),
          password_confirmation: data.get("password_confirmation"),
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
          [captcha.honeypotField]: honeypotValue(form, captcha.honeypotField),
          [captcha.tokenField]: await captcha.getToken(),
        },
      });
      await refresh();
      router.push("/verify-email");
    } catch (err) {
      if (err instanceof ApiError) {
        setFields({ name: err.field("name"), email: err.field("email"), password: err.field("password") });
      }
      setError(formErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthShell
      eyebrow="$ auth --register"
      title="Create your account"
      description="Join to access courses, digital resources, events and the community."
      footer={
        <>
          Already registered?{" "}
          <Link href="/login" className="text-accent hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit} noValidate className="relative flex flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        <TextField label="Full name" name="name" autoComplete="name" required error={fields.name} />
        <TextField label="Email" name="email" type="email" autoComplete="email" required error={fields.email} />
        <TextField label="Password" name="password" type="password" autoComplete="new-password" required minLength={10} hint="At least 10 characters. A passphrase works well." error={fields.password} />
        <TextField label="Confirm password" name="password_confirmation" type="password" autoComplete="new-password" required />

        <CaptchaNotice status={captcha.status} driver={captcha.config?.driver} widgetRef={captcha.widgetRef} honeypotField={captcha.honeypotField} />

        <Button type="submit" size="lg" loading={submitting}>
          Create account
        </Button>
      </form>
    </AuthShell>
  );
}
