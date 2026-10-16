"use client";

import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { api, ApiError } from "@/lib/api/client";

/**
 * Confirm or unsubscribe from an emailed link. Needs a button press on purpose: email security
 * scanners open links automatically, and must not subscribe or unsubscribe anyone by doing so.
 */
export function NewsletterLinkAction({ mode }: { mode: "confirm" | "unsubscribe" }) {
  const params = useSearchParams();
  const s = params.get("s") ?? "";
  const t = params.get("t") ?? "";
  const [state, setState] = useState<{ tone: "success" | "error"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  if (!s || !t) {
    return <Alert tone="error">This link is incomplete. Please open it again from your email.</Alert>;
  }

  async function run() {
    setBusy(true);
    try {
      const result = await api<{ message: string }>(`/api/v1/newsletter/${mode}`, { method: "POST", body: { s, t } });
      setState({ tone: "success", text: result.message });
    } catch (err) {
      setState({ tone: "error", text: err instanceof ApiError ? err.message : "Something went wrong. Please try again." });
    } finally {
      setBusy(false);
    }
  }

  if (state) {
    return <Alert tone={state.tone}>{state.text}</Alert>;
  }

  return (
    <Button size="lg" loading={busy} onClick={() => void run()} variant={mode === "unsubscribe" ? "secondary" : "primary"}>
      {mode === "confirm" ? "Confirm my subscription" : "Unsubscribe me"}
    </Button>
  );
}
