"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Dialog } from "@/components/ui/Dialog";
import { rememberClosed, rememberShown, shouldShow, visitorId, type Frequency, type StorageLike } from "@/lib/announcements/frequency";
import { api } from "@/lib/api/client";
import type { Media } from "@/lib/cms/schemas";
import { useSiteSettings } from "@/lib/settings/SiteSettingsProvider";

type Announcement = {
  id: string;
  title: string;
  message: string;
  image: Media | null;
  cta: { label: string; url: string } | null;
  frequency: Frequency;
  version: string;
};

/** Areas where a marketing popup would get in the way. */
const QUIET_PREFIXES = ["/admin", "/account", "/login", "/register", "/two-factor", "/verify-email", "/forgot-password", "/reset-password", "/preview", "/newsletter", "/page-not-found"];

function stores(): { local: StorageLike | null; session: StorageLike | null } {
  try {
    return { local: window.localStorage, session: window.sessionStorage };
  } catch {
    return { local: null, session: null };
  }
}

/**
 * Site announcement popup (REQ-ANN-1/2): accessible dialog (focus trap, Escape, focus returned).
 * Only asks the server when the global switch is on; the server enforces the switch as well.
 */
export function AnnouncementPopup() {
  const { settings, loaded } = useSiteSettings();
  const pathname = usePathname();
  const [announcement, setAnnouncement] = useState<Announcement | null>(null);
  const [open, setOpen] = useState(false);
  const shownOn = useRef<string | null>(null);

  const enabled = loaded && settings["announcements.enabled"] === true;
  const quiet = QUIET_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));

  useEffect(() => {
    if (!enabled || quiet || shownOn.current === pathname) return;
    let active = true;

    api<{ data: Announcement | null }>(`/api/v1/announcements/current?path=${encodeURIComponent(pathname)}`).then(
      ({ data }) => {
        if (!active || !data || !shouldShow(data, stores())) return;
        shownOn.current = pathname;
        rememberShown(data, stores());
        setAnnouncement(data);
        setOpen(true);
        void record(data.id, "impression");
      },
      () => undefined, // an announcement is never worth an error message
    );

    return () => {
      active = false;
    };
  }, [enabled, quiet, pathname]);

  if (!announcement) return null;

  function close(reason: "dismiss" | "click") {
    if (!announcement) return;
    rememberClosed(announcement, stores());
    setOpen(false);
    void record(announcement.id, reason);
  }

  const cta = announcement.cta;
  const external = cta !== null && /^(https?:|mailto:)/i.test(cta.url);

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? setOpen(true) : close("dismiss"))} title={announcement.title} className="max-w-md">
      {announcement.image && (
        // eslint-disable-next-line @next/next/no-img-element -- media library image with known size
        <img
          src={(announcement.image.variants.find((v) => v.name === "md") ?? announcement.image).url}
          alt={announcement.image.alt}
          className="mb-4 aspect-[16/9] w-full rounded-md border border-border object-cover"
        />
      )}
      <p className="text-sm leading-relaxed whitespace-pre-line text-muted">{announcement.message}</p>
      <div className="mt-6 flex flex-wrap justify-end gap-2">
        <Button variant="ghost" onClick={() => close("dismiss")}>
          Not now
        </Button>
        {cta && (
          <Button asChild>
            {external ? (
              <a href={cta.url} target="_blank" rel="noopener noreferrer" onClick={() => close("click")}>
                {cta.label}
                <span className="sr-only"> (opens in a new tab)</span>
              </a>
            ) : (
              <Link href={cta.url} onClick={() => close("click")}>
                {cta.label}
              </Link>
            )}
          </Button>
        )}
      </div>
    </Dialog>
  );
}

async function record(id: string, type: "impression" | "click" | "dismiss"): Promise<void> {
  try {
    await api(`/api/v1/announcements/${id}/events`, { method: "POST", body: { type, visitor: visitorId(stores().local) } });
  } catch {
    // Statistics are best-effort.
  }
}
