"use client";

import { useId, useState, type FormEvent } from "react";
import { CaptchaNotice } from "@/components/auth/CaptchaNotice";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { api, ApiError } from "@/lib/api/client";
import { useCaptcha } from "@/lib/captcha/useCaptcha";
import { cn } from "@/lib/cn";
import { formErrorMessage, honeypotValue } from "@/lib/forms";

/**
 * Newsletter sign-up (double opt-in): nothing is subscribed until the person clicks the link we email.
 * `source` records where they signed up, as part of the consent record.
 */
export function NewsletterForm({
  buttonLabel,
  consentText,
  source,
  compact = false,
}: {
  buttonLabel: string;
  consentText: string;
  source: string;
  compact?: boolean;
}) {
  const captcha = useCaptcha("newsletter");
  const id = useId();
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | undefined>();
  const [consentError, setConsentError] = useState<string | undefined>();

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setSubmitting(true);
    setError(null);
    setEmailError(undefined);
    setConsentError(undefined);

    try {
      const result = await api<{ message: string }>("/api/v1/newsletter/subscribe", {
        method: "POST",
        body: {
          email: data.get("email"),
          consent: data.get("consent") === "on",
          source,
          [captcha.honeypotField]: honeypotValue(form, captcha.honeypotField),
          [captcha.tokenField]: await captcha.getToken(),
        },
      });
      setDone(result.message);
    } catch (err) {
      if (err instanceof ApiError) {
        setEmailError(err.field("email"));
        setConsentError(err.field("consent"));
      }
      setError(formErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  if (done) {
    return (
      <Alert tone="success" title="Check your inbox">
        {done}
      </Alert>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="relative flex flex-col gap-3">
      {error && <Alert tone="error">{error}</Alert>}
      <div className={cn("flex gap-2", compact ? "flex-row" : "flex-col sm:flex-row")}>
        <label htmlFor={`${id}-email`} className="sr-only">
          Email address
        </label>
        <input
          id={`${id}-email`}
          name="email"
          type="email"
          autoComplete="email"
          required
          maxLength={254}
          placeholder="you@example.com"
          aria-invalid={emailError ? true : undefined}
          aria-describedby={emailError ? `${id}-email-error` : undefined}
          className={cn(
            "h-11 min-w-0 flex-1 rounded-md border bg-bg px-3 text-[15px] text-text placeholder:text-muted/60 focus:border-accent focus:outline-none",
            emailError ? "border-danger" : "border-border-strong",
          )}
        />
        <Button type="submit" size="lg" loading={submitting} className="shrink-0">
          {buttonLabel}
        </Button>
      </div>
      {emailError && (
        <p id={`${id}-email-error`} className="text-xs text-danger">
          {emailError}
        </p>
      )}
      <label className="flex items-start gap-2 text-xs leading-relaxed text-muted">
        <input type="checkbox" name="consent" required className="mt-0.5 size-4 shrink-0 accent-[var(--io-accent)]" aria-invalid={consentError ? true : undefined} />
        <span>{consentText}</span>
      </label>
      {consentError && <p className="text-xs text-danger">{consentError}</p>}
      <CaptchaNotice status={captcha.status} driver={captcha.config?.driver} widgetRef={captcha.widgetRef} honeypotField={captcha.honeypotField} />
    </form>
  );
}
