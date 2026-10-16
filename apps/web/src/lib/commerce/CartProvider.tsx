"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/AuthProvider";
import { useAddon } from "@/lib/settings/SiteSettingsProvider";
import { cartSchema, EMPTY_CART, type Cart } from "./cart";

type CartState = {
  cart: Cart;
  status: "idle" | "loading" | "ready" | "error";
  /** Product ids (ULIDs) currently in the cart. */
  has: (productId: string) => boolean;
  add: (productId: string) => Promise<void>;
  remove: (productId: string) => Promise<void>;
  applyCoupon: (code: string) => Promise<void>;
  removeCoupon: () => Promise<void>;
  setCurrency: (currency: string) => Promise<void>;
  refresh: () => Promise<void>;
};

const CartContext = createContext<CartState | null>(null);

function parse(body: unknown): Cart {
  const parsed = cartSchema.safeParse((body as { data?: unknown } | null)?.data);
  if (!parsed.success) {
    console.error("[cart] Unexpected cart data", parsed.error.issues.slice(0, 3));
    throw new Error("Unexpected cart data.");
  }
  return parsed.data;
}

/**
 * The visitor's cart, shared by the header count, product pages and the cart page. The server owns
 * the cart (a cookie for visitors, the account for members) and recalculates prices on every
 * answer; this only mirrors the latest answer. Nothing loads while the store add-on is off.
 */
export function CartProvider({ children }: { children: ReactNode }) {
  const enabled = useAddon("commerce");
  const { user, status: authStatus } = useAuth();
  const [cart, setCart] = useState<Cart>(EMPTY_CART);
  const [status, setStatus] = useState<CartState["status"]>("idle");
  const queue = useRef<Promise<unknown>>(Promise.resolve());

  // Cart requests are sent one after another. A visitor's first change creates their cart cookie,
  // so a request sent alongside it would not see the cart and could show it as empty.
  const run = useCallback(async (request: () => Promise<unknown>) => {
    const pending = queue.current.then(request);
    queue.current = pending.catch(() => undefined);
    const next = parse(await pending);
    setCart(next);
    setStatus("ready");
  }, []);

  const refresh = useCallback(async () => {
    setStatus((s) => (s === "ready" ? s : "loading"));
    try {
      await run(() => api("/api/v1/store/cart"));
    } catch {
      setStatus("error");
    }
  }, [run]);

  // Reload when the add-on state is known and whenever someone signs in or out (sign-in merges carts).
  useEffect(() => {
    if (!enabled || authStatus === "loading") return;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- loading the cart from the server is the point of this effect
    void refresh();
  }, [enabled, authStatus, user?.id, refresh]);

  const value = useMemo<CartState>(
    () => ({
      cart,
      status,
      has: (productId) => cart.items.some((item) => item.product.id === productId),
      add: (productId) => run(() => api("/api/v1/store/cart/items", { method: "POST", body: { product: productId } })),
      remove: (productId) =>
        run(() => api(`/api/v1/store/cart/items/${encodeURIComponent(productId)}`, { method: "DELETE" })),
      applyCoupon: (code) => run(() => api("/api/v1/store/cart/coupon", { method: "POST", body: { code } })),
      removeCoupon: () => run(() => api("/api/v1/store/cart/coupon", { method: "DELETE" })),
      setCurrency: (currency) => run(() => api("/api/v1/store/cart/currency", { method: "PUT", body: { currency } })),
      refresh,
    }),
    [cart, status, run, refresh],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartState {
  const context = useContext(CartContext);
  if (!context) throw new Error("useCart must be used inside <CartProvider>.");
  return context;
}
