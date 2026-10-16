const DISPLAY_TIME_ZONE = "Africa/Lagos";

/** "15 Oct 2026" for an ISO date/time, or null when absent or invalid. */
export function formatDate(value: string | null | undefined): string | null {
  if (!value) return null;
  const date = new Date(value.length === 10 ? `${value}T12:00:00Z` : value);
  if (Number.isNaN(date.getTime())) return null;

  return new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: DISPLAY_TIME_ZONE }).format(date);
}

/** "2.4 MB" / "320 KB". */
export function formatBytes(bytes: number): string {
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
