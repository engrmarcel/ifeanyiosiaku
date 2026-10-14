"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { AuthShell } from "@/components/auth/AuthShell";
import { CaptchaNotice } from "@/components/auth/CaptchaNotice";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { useCaptcha } from "@/lib/captcha/useCaptcha";
import { formErrorMessage, honeypotValue } from "@/lib/forms";

export default function ForgotPasswordPage() {
  const captcha = useCaptcha("password_reset");
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | undefined>();

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setSubmitting(true);
    setError(null);
    setEmailError(undefined);

    try {
      const result = await api<{ message: string }>("/api/v1/auth/forgot-password", {
        method: "POST",
        body: {
          email: new FormData(form).get("email"),
          [captcha.honeypotField]: honeypotValue(form, captcha.honeypotField),
          [captcha.tokenField]: await captcha.getToken(),
        },
      });
      setSent(result.message);
    } catch (err) {
      if (err instanceof ApiError) setEmailError(err.field("email"));
      setError(formErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthShell
      eyebrow="$ auth --recover"
      title="Reset your password"
      description="Enter your email address and we will send you a link to choose a new password."
      footer={
        <Link href="/login" className="text-accent hover:underline">
          Back to sign in
        </Link>
      }
    >
      {sent ? (
        <Alert tone="success" title="Check your inbox">
          {sent}
        </Alert>
      ) : (
        <form onSubmit={onSubmit} noValidate className="relative flex flex-col gap-4">
          {error && <Alert tone="error">{error}</Alert>}
          <TextField label="Email" name="email" type="email" autoComplete="email" required error={emailError} />
          <CaptchaNotice status={captcha.status} driver={captcha.config?.driver} widgetRef={captcha.widgetRef} honeypotField={captcha.honeypotField} />
          <Button type="submit" size="lg" loading={submitting}>
            Send reset link
          </Button>
        </form>
      )}
    </AuthShell>
  );
}
