/**
 * How often a visitor sees an announcement. Remembered only in this browser (no cookies, nothing
 * sent to the server). If storage is unavailable (private mode), the popup shows at most once per page.
 */

export type Frequency = "once" | "session" | "daily" | "always";

export type StorageLike = Pick<Storage, "getItem" | "setItem">;

const DAY_MS = 24 * 60 * 60 * 1000;

function key(id: string, version: string): string {
  return `io-ann:${id}:${version}`;
}

function read(storage: StorageLike | null, name: string): string | null {
  try {
    return storage?.getItem(name) ?? null;
  } catch {
    return null;
  }
}

function write(storage: StorageLike | null, name: string, value: string): void {
  try {
    storage?.setItem(name, value);
  } catch {
    // Storage full or blocked: the popup simply may show again.
  }
}

export function shouldShow(
  announcement: { id: string; version: string; frequency: Frequency },
  stores: { local: StorageLike | null; session: StorageLike | null },
  now: number = Date.now(),
): boolean {
  const name = key(announcement.id, announcement.version);

  switch (announcement.frequency) {
    case "once":
      return read(stores.local, name) === null;
    case "session":
      return read(stores.session, name) === null;
    case "daily": {
      const last = Number(read(stores.local, name));
      return !Number.isFinite(last) || last === 0 || now - last >= DAY_MS;
    }
    case "always":
      return true;
  }
}

/** Called when the popup is shown. */
export function rememberShown(
  announcement: { id: string; version: string; frequency: Frequency },
  stores: { local: StorageLike | null; session: StorageLike | null },
  now: number = Date.now(),
): void {
  const name = key(announcement.id, announcement.version);
  if (announcement.frequency === "session") write(stores.session, name, "1");
  if (announcement.frequency === "daily") write(stores.local, name, String(now));
}

/** Called when the visitor closes the popup or uses its button. */
export function rememberClosed(
  announcement: { id: string; version: string; frequency: Frequency },
  stores: { local: StorageLike | null; session: StorageLike | null },
): void {
  if (announcement.frequency === "once") write(stores.local, key(announcement.id, announcement.version), "closed");
}

/** Random id for this browser, used only to avoid counting the same impression twice. */
export function visitorId(local: StorageLike | null): string {
  const existing = read(local, "io-visitor");
  if (existing && /^[A-Za-z0-9-]{16,64}$/.test(existing)) return existing;

  const id =
    typeof crypto !== "undefined" && "randomUUID" in crypto
      ? crypto.randomUUID()
      : `v-${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`.padEnd(20, "0");
  write(local, "io-visitor", id);
  return id;
}
