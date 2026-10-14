"use client";

import Link from "next/link";
import { useState } from "react";
import { AuthShell } from "@/components/auth/AuthShell";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Spinner";
import { api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { formErrorMessage } from "@/lib/forms";

export default function VerifyEmailPage() {
  const { user, status } = useAuth();
  const [sending, setSending] = useState(false);
  const [message, setMessage] = useState<{ tone: "success" | "error"; text: string } | null>(null);

  async function resend() {
    setSending(true);
    setMessage(null);
    try {
      await api("/api/v1/auth/email/verification-notification", { method: "POST" });
      setMessage({ tone: "success", text: "A new confirmation link is on its way." });
    } catch (err) {
      setMessage({ tone: "error", text: formErrorMessage(err) });
    } finally {
      setSending(false);
    }
  }

  if (status === "loading") {
    return (
      <AuthShell eyebrow="$ auth --verify" title="Confirm your email">
        <Spinner />
      </AuthShell>
    );
  }

  if (!user) {
    return (
      <AuthShell eyebrow="$ auth --verify" title="Confirm your email">
        <p className="text-sm text-muted">
          Please{" "}
          <Link href="/login?next=/verify-email" className="text-accent hover:underline">
            sign in
          </Link>{" "}
          to confirm your email address.
        </p>
      </AuthShell>
    );
  }

  if (user.email_verified) {
    return (
      <AuthShell eyebrow="$ auth --verify" title="Email confirmed">
        <Alert tone="success" title="You're all set">Your email address is confirmed.</Alert>
        <Button asChild className="mt-5 w-full">
          <Link href="/account">Go to your account</Link>
        </Button>
      </AuthShell>
    );
  }

  return (
    <AuthShell
      eyebrow="$ auth --verify"
      title="Confirm your email"
      description={<>We sent a confirmation link to <span className="text-text">{user.email}</span>. Open it on this device to activate your account.</>}
    >
      <div className="flex flex-col gap-4">
        {message && <Alert tone={message.tone}>{message.text}</Alert>}
        <Button variant="secondary" onClick={resend} loading={sending}>
          Send the link again
        </Button>
        <p className="text-xs text-muted">Can&apos;t find it? Check your spam or promotions folder.</p>
      </div>
    </AuthShell>
  );
}
