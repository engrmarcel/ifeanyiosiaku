"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { AuthShell } from "@/components/auth/AuthShell";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { formErrorMessage } from "@/lib/forms";

export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | undefined>();

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    setSubmitting(true);
    setError(null);
    setPasswordError(undefined);

    try {
      await api("/api/v1/auth/reset-password", {
        method: "POST",
        body: {
          token,
          email,
          password: data.get("password"),
          password_confirmation: data.get("password_confirmation"),
        },
      });
      router.push("/login?reset=1");
    } catch (err) {
      if (err instanceof ApiError) {
        setPasswordError(err.field("password"));
        const emailMessage = err.field("email");
        setError(emailMessage ? "This reset link is invalid or has expired. Please request a new one." : formErrorMessage(err));
      } else {
        setError(formErrorMessage(err));
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (!token || !email) {
    return (
      <AuthShell eyebrow="$ auth --reset" title="Reset link incomplete">
        <Alert tone="error">
          This link is missing information. Please use the link from your email, or{" "}
          <Link href="/forgot-password" className="text-accent hover:underline">
            request a new one
          </Link>
          .
        </Alert>
      </AuthShell>
    );
  }

  return (
    <AuthShell eyebrow="$ auth --reset" title="Choose a new password" description={<>For <span className="text-text">{email}</span>. All other sessions will be signed out.</>}>
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        <TextField label="New password" name="password" type="password" autoComplete="new-password" required minLength={10} hint="At least 10 characters." error={passwordError} />
        <TextField label="Confirm new password" name="password_confirmation" type="password" autoComplete="new-password" required />
        <Button type="submit" size="lg" loading={submitting}>
          Update password
        </Button>
      </form>
    </AuthShell>
  );
}
