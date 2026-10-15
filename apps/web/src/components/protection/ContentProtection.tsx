"use client";

import { useEffect } from "react";
import { useAuth } from "@/lib/auth/AuthProvider";
import { installContentProtection } from "@/lib/protection/contentProtection";
import { useSiteSettings } from "@/lib/settings/SiteSettingsProvider";

/**
 * Applies content protection unless the administrator allows saving/Inspect (setting is OFF by
 * default) or the visitor is signed-in staff. Protection is active while settings load and if they
 * cannot be loaded, so the default is always the protected state.
 */
export function ContentProtection() {
  const { user } = useAuth();
  const { settings } = useSiteSettings();

  const enabled = settings["protection.allow_saving_and_inspect"] !== true && !user?.is_staff;

  useEffect(() => {
    if (!enabled) return;
    return installContentProtection();
  }, [enabled]);

  return null;
}
