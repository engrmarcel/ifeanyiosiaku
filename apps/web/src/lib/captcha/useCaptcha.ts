"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/lib/api/client";
import type { CaptchaChallenge, CaptchaConfig, DataEnvelope } from "@/lib/api/types";
import { solve } from "./pow";

export type CaptchaPurpose =
  | "register"
  | "login"
  | "password_reset"
  | "contact"
  | "booking"
  | "newsletter"
  | "comment"
  | "community_post";

export type CaptchaStatus = "idle" | "loading" | "verifying" | "ready" | "error";

declare global {
  interface Window {
    turnstile?: {
      render: (el: HTMLElement, opts: Record<string, unknown>) => string;
      reset: (id?: string) => void;
    };
    grecaptcha?: {
      ready: (cb: () => void) => void;
      execute: (siteKey: string, opts: { action: string }) => Promise<string>;
    };
  }
}

const scriptPromises = new Map<string, Promise<void>>();

async function fetchCaptchaConfig(): Promise<CaptchaConfig> {
  return (await api<DataEnvelope<CaptchaConfig>>("/api/v1/captcha/config")).data;
}

function loadScript(src: string): Promise<void> {
  if (!scriptPromises.has(src)) {
    scriptPromises.set(
      src,
      new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Could not load ${src}`));
        document.head.appendChild(script);
      }),
    );
  }
  return scriptPromises.get(src)!;
}

function solveInWorker(challenge: string, difficulty: number): Promise<string> {
  if (typeof Worker === "undefined") return solve(challenge, difficulty);

  return new Promise((resolve, reject) => {
    const worker = new Worker(new URL("./pow.worker.ts", import.meta.url), { type: "module" });
    worker.onmessage = (event: MessageEvent<{ ok: boolean; nonce?: string; error?: string }>) => {
      worker.terminate();
      if (event.data.ok && event.data.nonce) resolve(event.data.nonce);
      else reject(new Error(event.data.error ?? "Verification failed"));
    };
    worker.onerror = () => {
      worker.terminate();
      // Fall back to the main thread if the worker cannot start.
      solve(challenge, difficulty).then(resolve, reject);
    };
    worker.postMessage({ challenge, difficulty });
  });
}

/**
 * Provides a CAPTCHA token for a form submission using whichever provider the administrator
 * selected. The first-party provider is invisible: the browser solves a small proof of work.
 * Tokens are always verified by the server; nothing here decides success.
 */
export function useCaptcha(purpose: CaptchaPurpose, options: { enabled?: boolean } = {}) {
  const enabled = options.enabled ?? true;
  const [config, setConfig] = useState<CaptchaConfig | null>(null);
  const [status, setStatus] = useState<CaptchaStatus>(enabled ? "loading" : "idle");
  const widgetRef = useRef<HTMLDivElement | null>(null);
  const turnstileToken = useRef<string | null>(null);
  const turnstileId = useRef<string | null>(null);

  const loadConfig = useCallback(async () => {
    const data = await fetchCaptchaConfig();
    setConfig(data);
    return data;
  }, []);

  useEffect(() => {
    if (!enabled) return;
    let cancelled = false;
    fetchCaptchaConfig()
      .then(async (data) => {
        if (cancelled) return;
        setConfig(data);
        if (data.driver === "turnstile" && data.site_key) {
          await loadScript("https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit");
          if (!cancelled && widgetRef.current && window.turnstile) {
            turnstileId.current = window.turnstile.render(widgetRef.current, {
              sitekey: data.site_key,
              action: purpose,
              theme: "dark",
              callback: (token: string) => {
                turnstileToken.current = token;
                setStatus("ready");
              },
              "expired-callback": () => {
                turnstileToken.current = null;
                setStatus("idle");
              },
            });
          }
        } else if (data.driver === "recaptcha" && data.site_key) {
          await loadScript(`https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(data.site_key)}`);
        }
        if (!cancelled && data.driver !== "turnstile") setStatus("idle");
      })
      .catch(() => !cancelled && setStatus("error"));
    return () => {
      cancelled = true;
    };
  }, [enabled, purpose]);

  /** Returns a fresh token for this submission (each token is single-use). */
  const getToken = useCallback(async (): Promise<string> => {
    const active = config ?? (await loadConfig());

    if (active.driver === "first_party") {
      setStatus("verifying");
      try {
        const { data } = await api<DataEnvelope<CaptchaChallenge>>("/api/v1/captcha/challenge", {
          method: "POST",
          body: { purpose },
        });
        const nonce = await solveInWorker(data.challenge, data.difficulty);
        setStatus("ready");
        return `${data.challenge}:${nonce}`;
      } catch (error) {
        setStatus("error");
        throw error;
      }
    }

    if (active.driver === "recaptcha" && active.site_key && window.grecaptcha) {
      const siteKey = active.site_key;
      return new Promise((resolve, reject) => {
        window.grecaptcha!.ready(() => {
          window.grecaptcha!.execute(siteKey, { action: purpose }).then(resolve, reject);
        });
      });
    }

    if (active.driver === "turnstile") {
      const token = turnstileToken.current;
      if (!token) throw new Error("Please complete the verification check.");
      turnstileToken.current = null;
      if (turnstileId.current) window.turnstile?.reset(turnstileId.current);
      return token;
    }

    throw new Error("Verification is not available right now.");
  }, [config, loadConfig, purpose]);

  return {
    config,
    status,
    getToken,
    widgetRef,
    tokenField: config?.token_field ?? "captcha_token",
    honeypotField: config?.honeypot_field ?? "website_url",
    loginRequiresCaptcha: config?.login_requires_captcha ?? false,
    refreshConfig: loadConfig,
  };
}
