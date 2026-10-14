"use client";

import { useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { formErrorMessage } from "@/lib/forms";

export function PasswordSection() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string | undefined>>({});
  const [done, setDone] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setBusy(true);
    setError(null);
    setFields({});
    setDone(false);

    try {
      await api("/api/v1/auth/user/password", {
        method: "PUT",
        body: {
          current_password: data.get("current_password"),
          password: data.get("password"),
          password_confirmation: data.get("password_confirmation"),
        },
      });
      form.reset();
      setDone(true);
    } catch (err) {
      if (err instanceof ApiError) setFields({ current_password: err.field("current_password"), password: err.field("password") });
      setError(formErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader title="Change password" />
      <form onSubmit={onSubmit} noValidate className="flex max-w-md flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        {done && <Alert tone="success">Your password has been updated.</Alert>}
        <TextField label="Current password" name="current_password" type="password" autoComplete="current-password" required error={fields.current_password} />
        <TextField label="New password" name="password" type="password" autoComplete="new-password" required minLength={10} hint="At least 10 characters." error={fields.password} />
        <TextField label="Confirm new password" name="password_confirmation" type="password" autoComplete="new-password" required />
        <Button type="submit" loading={busy} className="self-start">
          Update password
        </Button>
      </form>
    </Card>
  );
}
