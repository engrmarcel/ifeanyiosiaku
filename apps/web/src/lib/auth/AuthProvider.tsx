"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { api, ApiError } from "@/lib/api/client";
import type { CurrentUser, DataEnvelope } from "@/lib/api/types";

type AuthState = {
  user: CurrentUser | null;
  status: "loading" | "authenticated" | "guest";
  refresh: () => Promise<CurrentUser | null>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

async function fetchCurrentUser(): Promise<CurrentUser | null> {
  try {
    return (await api<DataEnvelope<CurrentUser>>("/api/v1/me")).data;
  } catch (error) {
    if (!(error instanceof ApiError) || error.status !== 401) {
      console.error("Could not load the current user", error);
    }
    return null;
  }
}

/**
 * Client-side view of the signed-in user. The session itself lives in an HttpOnly cookie that
 * JavaScript cannot read; this only mirrors what the API reports. Every protected action is still
 * authorised by the server.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [status, setStatus] = useState<AuthState["status"]>("loading");

  const apply = useCallback((next: CurrentUser | null) => {
    setUser(next);
    setStatus(next ? "authenticated" : "guest");
    return next;
  }, []);

  const refresh = useCallback(async () => apply(await fetchCurrentUser()), [apply]);

  const logout = useCallback(async () => {
    await api("/api/v1/auth/logout", { method: "POST" }).catch(() => undefined);
    setUser(null);
    setStatus("guest");
  }, []);

  useEffect(() => {
    let active = true;
    fetchCurrentUser().then((current) => {
      if (active) apply(current);
    });
    return () => {
      active = false;
    };
  }, [apply]);

  const value = useMemo(() => ({ user, status, refresh, logout }), [user, status, refresh, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used inside <AuthProvider>.");
  return context;
}
