"use client";

import type { RefObject } from "react";
import type { CaptchaStatus } from "@/lib/captcha/useCaptcha";

/**
 * Status line for the CAPTCHA plus the hidden honeypot field. The first-party check needs no
 * interaction; Turnstile renders its widget into `widgetRef`.
 */
export function CaptchaNotice({ status, driver, widgetRef, honeypotField }: {
  status: CaptchaStatus;
  driver?: string;
  widgetRef: RefObject<HTMLDivElement | null>;
  honeypotField: string;
}) {
  return (
    <>
      {/* Honeypot: hidden from people and assistive technology; bots that fill it are rejected. */}
      <div aria-hidden="true" className="absolute -left-[10000px] h-px w-px overflow-hidden">
        <label>
          Website
          <input type="text" name={honeypotField} tabIndex={-1} autoComplete="off" defaultValue="" />
        </label>
      </div>

      {driver === "turnstile" && <div ref={widgetRef} className="min-h-[65px]" />}

      <p aria-live="polite" className="font-mono text-xs text-muted">
        {status === "verifying" && "Running a quick automated check…"}
        {status === "error" && <span className="text-danger">Verification could not start. Please refresh and try again.</span>}
        {status !== "verifying" && status !== "error" && driver === "first_party" && "Protected by an automatic, privacy-friendly check."}
      </p>
    </>
  );
}
