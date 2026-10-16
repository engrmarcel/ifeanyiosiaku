"use client";

import { usePathname } from "next/navigation";
import { useState, type FormEvent } from "react";
import { CaptchaNotice } from "@/components/auth/CaptchaNotice";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextArea } from "@/components/ui/TextArea";
import { TextField } from "@/components/ui/TextField";
import { api, ApiError } from "@/lib/api/client";
import { useCaptcha } from "@/lib/captcha/useCaptcha";
import { formErrorMessage, honeypotValue } from "@/lib/forms";

type Fields = "name" | "email" | "subject" | "message";

/** Contact form with the site's CAPTCHA. Messages go to the admin inbox (and email, if configured). */
export function ContactForm({ buttonLabel }: { buttonLabel: string }) {
  const captcha = useCaptcha("contact");
  const pathname = usePathname();
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<Fields, string>>>({});

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    try {
      const result = await api<{ message: string }>("/api/v1/contact", {
        method: "POST",
        body: {
          name: data.get("name"),
          email: data.get("email"),
          subject: data.get("subject"),
          message: data.get("message"),
          page: pathname,
          [captcha.honeypotField]: honeypotValue(form, captcha.honeypotField),
          [captcha.tokenField]: await captcha.getToken(),
        },
      });
      setSent(result.message);
      form.reset();
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors({ name: err.field("name"), email: err.field("email"), subject: err.field("subject"), message: err.field("message") });
      }
      setError(formErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  if (sent) {
    return (
      <Alert tone="success" title="Message sent">
        {sent}
      </Alert>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="relative flex flex-col gap-4">
      {error && <Alert tone="error">{error}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Your name" name="name" autoComplete="name" required maxLength={120} error={fieldErrors.name} />
        <TextField label="Email" name="email" type="email" autoComplete="email" required maxLength={254} error={fieldErrors.email} />
      </div>
      <TextField label="Subject" name="subject" maxLength={150} error={fieldErrors.subject} />
      <TextArea label="Message" name="message" required minLength={10} maxLength={5000} rows={6} error={fieldErrors.message} />
      <CaptchaNotice status={captcha.status} driver={captcha.config?.driver} widgetRef={captcha.widgetRef} honeypotField={captcha.honeypotField} />
      <p className="text-xs text-muted">Your details are used only to reply to your message.</p>
      <div>
        <Button type="submit" size="lg" loading={submitting}>
          {buttonLabel}
        </Button>
      </div>
    </form>
  );
}
