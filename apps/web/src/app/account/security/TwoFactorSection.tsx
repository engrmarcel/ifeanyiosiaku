"use client";

import { useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { TextField } from "@/components/ui/TextField";
import { api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { safeNext } from "@/lib/auth/safeNext";
import { usePasswordConfirmation } from "@/lib/auth/usePasswordConfirmation";
import { formErrorMessage } from "@/lib/forms";

type Stage = "idle" | "scan" | "codes";

export function TwoFactorSection() {
  const params = useSearchParams();
  const setupRequested = params.get("setup") === "two-factor";
  const next = params.get("next") ? safeNext(params.get("next")) : null;
  const { user, refresh } = useAuth();
  const { withConfirmation, dialog } = usePasswordConfirmation();

  const [stage, setStage] = useState<Stage>("idle");
  const [qrSvg, setQrSvg] = useState<string | null>(null);
  const [secret, setSecret] = useState<string | null>(null);
  const [codes, setCodes] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!user) return null;

  async function run(action: () => Promise<void>) {
    setBusy(true);
    setError(null);
    try {
      await action();
    } catch (err) {
      if (!(err instanceof Error && err.message === "Password confirmation cancelled.")) setError(formErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const enable = () =>
    run(async () => {
      await withConfirmation(() => api("/api/v1/auth/user/two-factor-authentication", { method: "POST" }));
      const [qr, key] = await Promise.all([
        api<{ svg: string }>("/api/v1/auth/user/two-factor-qr-code"),
        api<{ secretKey: string }>("/api/v1/auth/user/two-factor-secret-key"),
      ]);
      setQrSvg(qr.svg);
      setSecret(key.secretKey);
      setStage("scan");
    });

  const confirm = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const code = String(new FormData(event.currentTarget).get("code") ?? "").replace(/\s/g, "");
    return run(async () => {
      await api("/api/v1/auth/user/confirmed-two-factor-authentication", { method: "POST", body: { code } });
      setCodes(await api<string[]>("/api/v1/auth/user/two-factor-recovery-codes"));
      setStage("codes");
      await refresh();
    });
  };

  const showCodes = () =>
    run(async () => {
      setCodes(await withConfirmation(() => api<string[]>("/api/v1/auth/user/two-factor-recovery-codes")));
      setStage("codes");
    });

  const regenerate = () =>
    run(async () => {
      await withConfirmation(() => api("/api/v1/auth/user/two-factor-recovery-codes", { method: "POST" }));
      setCodes(await api<string[]>("/api/v1/auth/user/two-factor-recovery-codes"));
      setStage("codes");
    });

  const disable = () =>
    run(async () => {
      if (user.two_factor_required && !window.confirm("Staff accounts cannot open the admin panel without two-factor authentication. Turn it off anyway?")) return;
      await withConfirmation(() => api("/api/v1/auth/user/two-factor-authentication", { method: "DELETE" }));
      setStage("idle");
      setCodes([]);
      await refresh();
    });

  return (
    <Card>
      <CardHeader title="Two-factor authentication" description="Require a code from an authenticator app (such as Google Authenticator, 1Password or Authy) when signing in." />
      {dialog}

      <div className="flex flex-col gap-4">
        {setupRequested && !user.two_factor_enabled && (
          <Alert tone="warning" title="Two-factor authentication is required">
            {user.two_factor_required ? "Staff accounts must turn this on before using the admin panel." : "Please turn on two-factor authentication to continue."}
          </Alert>
        )}
        {error && <Alert tone="error">{error}</Alert>}

        {user.two_factor_enabled && stage !== "codes" && (
          <>
            <p className="text-sm">
              Status: <span className="text-accent">on</span>
            </p>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={showCodes} loading={busy}>
                Show recovery codes
              </Button>
              <Button variant="secondary" onClick={regenerate} disabled={busy}>
                Regenerate codes
              </Button>
              <Button variant="danger" onClick={disable} disabled={busy}>
                Turn off
              </Button>
            </div>
            {next && (
              <Button onClick={() => window.location.assign(next)}>
                Continue
              </Button>
            )}
          </>
        )}

        {!user.two_factor_enabled && stage === "idle" && (
          <div>
            <p className="mb-4 text-sm">
              Status: <span className="text-amber">off</span>
            </p>
            <Button onClick={enable} loading={busy}>
              Turn on two-factor authentication
            </Button>
          </div>
        )}

        {stage === "scan" && qrSvg && (
          <div className="flex flex-col gap-4">
            <ol className="list-decimal space-y-1 pl-5 text-sm text-muted">
              <li>Scan this QR code with your authenticator app.</li>
              <li>Enter the 6-digit code it shows to finish setup.</li>
            </ol>
            <div className="flex flex-col items-start gap-4 sm:flex-row">
              {/* Rendered as an image so the SVG cannot run scripts. */}
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrSvg)}`}
                alt="QR code for adding this account to an authenticator app"
                width={192}
                height={192}
                className="rounded-md bg-white p-2"
              />
              {secret && (
                <div className="text-sm">
                  <p className="text-muted">Can&apos;t scan? Enter this key manually:</p>
                  <code className="mt-1 block rounded-sm bg-bg px-2 py-1 font-mono text-accent break-all">{secret}</code>
                </div>
              )}
            </div>
            <form onSubmit={confirm} className="flex max-w-xs flex-col gap-3">
              <TextField label="Authentication code" name="code" inputMode="numeric" autoComplete="one-time-code" required className="font-mono tracking-widest" />
              <Button type="submit" loading={busy}>
                Confirm and turn on
              </Button>
            </form>
          </div>
        )}

        {stage === "codes" && (
          <div className="flex flex-col gap-3">
            <Alert tone="info" title="Save your recovery codes">
              Store these somewhere safe, such as a password manager. Each code can be used once if you lose access to your authenticator app.
            </Alert>
            <ul className="grid grid-cols-1 gap-2 rounded-md border border-border bg-bg p-4 font-mono text-sm sm:grid-cols-2" aria-label="Recovery codes">
              {codes.map((code) => (
                <li key={code}>{code}</li>
              ))}
            </ul>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => void navigator.clipboard?.writeText(codes.join("\n"))}>
                Copy codes
              </Button>
              <Button onClick={() => (next ? window.location.assign(next) : setStage("idle"))}>{next ? "Continue" : "Done"}</Button>
            </div>
          </div>
        )}
      </div>
    </Card>
  );
}
