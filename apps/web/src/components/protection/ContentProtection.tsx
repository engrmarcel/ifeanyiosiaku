"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api/client";
import type { DataEnvelope } from "@/lib/api/types";
import { useAuth } from "@/lib/auth/AuthProvider";
import { installContentProtection } from "@/lib/protection/contentProtection";

const SETTING = "protection.allow_saving_and_inspect";

/**
 * Applies content protection unless the administrator allows saving/Inspect (setting is OFF by
 * default) or the visitor is signed-in staff. Protection is active while the setting loads, so the
 * default is always the protected state.
 */
export function ContentProtection() {
  const { user } = useAuth();
  const [allowed, setAllowed] = useState(false);

  useEffect(() => {
    let active = true;
    api<DataEnvelope<Record<string, unknown>>>("/api/v1/settings/public").then(
      ({ data }) => active && setAllowed(data[SETTING] === true),
      () => undefined, // keep protection on if settings cannot be loaded
    );
    return () => {
      active = false;
    };
  }, []);

  const enabled = !allowed && !user?.is_staff;

  useEffect(() => {
    if (!enabled) return;
    return installContentProtection();
  }, [enabled]);

  return null;
}
