"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { Skeleton } from "@/components/ui/Skeleton";
import { TextField } from "@/components/ui/TextField";
import { api } from "@/lib/api/client";
import type { AccountSession, DataEnvelope } from "@/lib/api/types";
import { formErrorMessage } from "@/lib/forms";

function describeAgent(agent: string | null): string {
  if (!agent) return "Unknown device";
  const browser = /Edg\//.test(agent) ? "Edge" : /Chrome\//.test(agent) ? "Chrome" : /Firefox\//.test(agent) ? "Firefox" : /Safari\//.test(agent) ? "Safari" : "Browser";
  const os = /Windows/.test(agent) ? "Windows" : /Android/.test(agent) ? "Android" : /iPhone|iPad/.test(agent) ? "iOS" : /Mac OS X/.test(agent) ? "macOS" : /Linux/.test(agent) ? "Linux" : "";
  return os ? `${browser} on ${os}` : browser;
}

async function fetchSessions(): Promise<AccountSession[]> {
  return (await api<DataEnvelope<AccountSession[]>>("/api/v1/account/sessions")).data;
}

export function SessionsSection() {
  const [sessions, setSessions] = useState<AccountSession[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      setSessions(await fetchSessions());
    } catch (err) {
      setError(formErrorMessage(err));
    }
  }, []);

  useEffect(() => {
    let active = true;
    fetchSessions().then(
      (list) => active && setSessions(list),
      (err) => active && setError(formErrorMessage(err)),
    );
    return () => {
      active = false;
    };
  }, []);

  async function revoke(id: string) {
    setError(null);
    try {
      await api(`/api/v1/account/sessions/${id}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(formErrorMessage(err));
    }
  }

  async function revokeOthers(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      const { data } = await api<DataEnvelope<{ revoked: number }>>("/api/v1/account/sessions/revoke-others", {
        method: "POST",
        body: { password: new FormData(form).get("password") },
      });
      setMessage(data.revoked === 1 ? "Signed out 1 other session." : `Signed out ${data.revoked} other sessions.`);
      form.reset();
      await load();
    } catch (err) {
      setError(formErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader title="Where you're signed in" description="If you don't recognise a session, sign it out and change your password." />
      <div className="flex flex-col gap-4">
        {error && <Alert tone="error">{error}</Alert>}
        {message && <Alert tone="success">{message}</Alert>}

        {sessions === null ? (
          <div className="flex flex-col gap-2" aria-busy="true">
            <Skeleton className="h-14" />
            <Skeleton className="h-14" />
          </div>
        ) : (
          <ul className="divide-y divide-border rounded-md border border-border">
            {sessions.map((session) => (
              <li key={session.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div className="text-sm">
                  <p className="text-text">
                    {describeAgent(session.user_agent)}
                    {session.is_current && <span className="ml-2 rounded-sm bg-success-surface px-1.5 py-0.5 font-mono text-xs text-accent">this device</span>}
                  </p>
                  <p className="text-xs text-muted">
                    {session.ip_address ?? "Unknown IP"} · last active{" "}
                    <time dateTime={session.last_active_at}>{new Date(session.last_active_at).toLocaleString()}</time>
                  </p>
                </div>
                {!session.is_current && (
                  <Button variant="ghost" size="sm" onClick={() => void revoke(session.id)}>
                    Sign out
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}

        <form onSubmit={revokeOthers} className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="flex-1">
            <TextField label="Confirm your password to sign out all other sessions" name="password" type="password" autoComplete="current-password" required />
          </div>
          <Button type="submit" variant="secondary" loading={busy}>
            Sign out other sessions
          </Button>
        </form>
      </div>
    </Card>
  );
}
