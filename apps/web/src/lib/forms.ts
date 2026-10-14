import { ApiError } from "@/lib/api/client";

/** Maps an API error to a friendly top-level message for a form. */
export function formErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return error instanceof Error && error.message ? error.message : "Something went wrong. Please try again.";
  }

  switch (error.code) {
    case "captcha_failed":
      return "We could not verify this submission. Please try again.";
    case "captcha_unavailable":
      return "Verification is temporarily unavailable. Please try again in a moment.";
    case "too_many_requests":
      return "Too many attempts. Please wait a minute and try again.";
    case "csrf_token_mismatch":
      return "Your session expired. Please try again.";
    case "validation_failed":
      return "Please check the highlighted fields.";
    default:
      return error.message || "Something went wrong. Please try again.";
  }
}

/** Reads the honeypot value from the submitted form (normally empty). */
export function honeypotValue(form: HTMLFormElement, field: string): string {
  const input = form.elements.namedItem(field);
  return input instanceof HTMLInputElement ? input.value : "";
}
