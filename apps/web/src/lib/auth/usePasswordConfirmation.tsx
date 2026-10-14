"use client";

import { useCallback, useRef, useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Dialog } from "@/components/ui/Dialog";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";

/**
 * Sensitive actions (enabling 2FA, viewing recovery codes) require a recent password confirmation.
 * When the API answers 423, ask for the password, confirm it, then retry the action once.
 */
export function usePasswordConfirmation() {
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const pending = useRef<{ resolve: () => void; reject: (e: unknown) => void } | null>(null);

  const withConfirmation = useCallback(async <T,>(action: () => Promise<T>): Promise<T> => {
    try {
      return await action();
    } catch (err) {
      if (!(err instanceof ApiError) || err.status !== 423) throw err;
      await new Promise<void>((resolve, reject) => {
        pending.current = { resolve, reject };
        setError(null);
        setOpen(true);
      });
      return action();
    }
  }, []);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      await api("/api/v1/auth/user/confirm-password", {
        method: "POST",
        body: { password: new FormData(event.currentTarget).get("password") },
      });
      setOpen(false);
      pending.current?.resolve();
    } catch {
      setError("That password is not correct.");
    } finally {
      setSubmitting(false);
    }
  }

  function onOpenChange(next: boolean) {
    setOpen(next);
    if (!next) pending.current?.reject(new Error("Password confirmation cancelled."));
  }

  const dialog = (
    <Dialog open={open} onOpenChange={onOpenChange} title="Confirm your password" description="For your security, please confirm your password to continue.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        <TextField label="Password" name="password" type="password" autoComplete="current-password" required autoFocus />
        <Button type="submit" loading={submitting}>
          Confirm
        </Button>
      </form>
    </Dialog>
  );

  return { withConfirmation, dialog };
}
