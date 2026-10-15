"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "@/lib/api/client";
import type { DataEnvelope } from "@/lib/api/types";

export type AddonName = "commerce" | "learning" | "community" | "rewards";

export type PublicSettings = Record<string, unknown>;

type SiteSettingsState = {
  settings: PublicSettings;
  loaded: boolean;
};

const SiteSettingsContext = createContext<SiteSettingsState | null>(null);

/** Loads the public (non-secret) site settings once per page load and shares them. */
export function SiteSettingsProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<SiteSettingsState>({ settings: {}, loaded: false });

  useEffect(() => {
    let active = true;
    api<DataEnvelope<PublicSettings>>("/api/v1/settings/public").then(
      ({ data }) => active && setState({ settings: data, loaded: true }),
      () => active && setState({ settings: {}, loaded: true }),
    );
    return () => {
      active = false;
    };
  }, []);

  return <SiteSettingsContext.Provider value={state}>{children}</SiteSettingsContext.Provider>;
}

export function useSiteSettings(): SiteSettingsState {
  const context = useContext(SiteSettingsContext);
  if (!context) throw new Error("useSiteSettings must be used inside <SiteSettingsProvider>.");
  return context;
}

/**
 * Whether an add-on module is switched on (ADR-025). Modules default to ON, so this is true until
 * settings load or if they cannot be loaded; the server independently refuses disabled modules.
 */
export function useAddon(name: AddonName): boolean {
  const { settings } = useSiteSettings();
  return settings[`addons.${name}.enabled`] !== false;
}
