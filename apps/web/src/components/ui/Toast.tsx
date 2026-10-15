"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { cn } from "@/lib/cn";

type Tone = "success" | "error" | "info";

export type ToastOptions = {
  title: string;
  description?: string;
  tone?: Tone;
  /** Milliseconds before auto-dismiss. Errors stay until dismissed unless set explicitly. */
  duration?: number;
};

type ToastItem = Required<Pick<ToastOptions, "title" | "tone">> & ToastOptions & { id: number };

type ToastApi = {
  toast: (options: ToastOptions) => number;
  dismiss: (id: number) => void;
};

const ToastContext = createContext<ToastApi | null>(null);

const tones: Record<Tone, string> = {
  success: "border-accent/40",
  error: "border-danger/50",
  info: "border-violet/40",
};

const markers: Record<Tone, string> = {
  success: "text-accent",
  error: "text-danger",
  info: "text-violet-text",
};

/**
 * Transient notifications. Messages are rendered inside polite (or assertive, for errors) live
 * regions so screen readers announce them; each can be dismissed with its close button or Escape.
 */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);
  const nextId = useRef(1);
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());

  const dismiss = useCallback((id: number) => {
    setItems((current) => current.filter((item) => item.id !== id));
    const timer = timers.current.get(id);
    if (timer) clearTimeout(timer);
    timers.current.delete(id);
  }, []);

  const toast = useCallback(
    (options: ToastOptions) => {
      const id = nextId.current++;
      const tone = options.tone ?? "info";
      setItems((current) => [...current.slice(-3), { ...options, tone, id }]);

      const duration = options.duration ?? (tone === "error" ? undefined : 5000);
      if (duration !== undefined) {
        timers.current.set(id, setTimeout(() => dismiss(id), duration));
      }
      return id;
    },
    [dismiss],
  );

  useEffect(() => {
    const pending = timers.current;
    return () => pending.forEach(clearTimeout);
  }, []);

  useEffect(() => {
    if (items.length === 0) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") dismiss(items[items.length - 1].id);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [items, dismiss]);

  const api = useMemo(() => ({ toast, dismiss }), [toast, dismiss]);
  const polite = items.filter((item) => item.tone !== "error");
  const assertive = items.filter((item) => item.tone === "error");

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end">
        <div aria-live="assertive" role="alert" className="flex w-full flex-col items-center gap-2 sm:items-end">
          {assertive.map((item) => (
            <ToastCard key={item.id} item={item} onDismiss={dismiss} />
          ))}
        </div>
        <div aria-live="polite" role="status" className="flex w-full flex-col items-center gap-2 sm:items-end">
          {polite.map((item) => (
            <ToastCard key={item.id} item={item} onDismiss={dismiss} />
          ))}
        </div>
      </div>
    </ToastContext.Provider>
  );
}

function ToastCard({ item, onDismiss }: { item: ToastItem; onDismiss: (id: number) => void }) {
  return (
    <div
      className={cn(
        "pointer-events-auto flex w-full max-w-sm gap-3 rounded-md border bg-surface-raised px-4 py-3 text-sm shadow-lg",
        tones[item.tone],
      )}
    >
      <span aria-hidden="true" className={cn("font-mono leading-5", markers[item.tone])}>
        {item.tone === "error" ? "✕" : item.tone === "success" ? "✓" : "›"}
      </span>
      <div className="flex-1">
        <p className="font-medium text-text">{item.title}</p>
        {item.description && <p className="mt-0.5 text-muted">{item.description}</p>}
      </div>
      <button
        type="button"
        onClick={() => onDismiss(item.id)}
        className="-m-1 self-start rounded-sm p-1 text-muted hover:text-text"
        aria-label={`Dismiss: ${item.title}`}
      >
        <span aria-hidden="true">✕</span>
      </button>
    </div>
  );
}

export function useToast(): ToastApi {
  const context = useContext(ToastContext);
  if (!context) throw new Error("useToast must be used inside <ToastProvider>.");
  return context;
}
