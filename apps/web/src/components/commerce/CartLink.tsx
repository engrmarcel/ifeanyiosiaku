"use client";

import Link from "next/link";
import { useCart } from "@/lib/commerce/CartProvider";
import { useAddon } from "@/lib/settings/SiteSettingsProvider";

/** Header link to the cart with the number of items. Hidden while the store add-on is off. */
export function CartLink() {
  const enabled = useAddon("commerce");
  const { cart } = useCart();

  if (!enabled) return null;

  const label = cart.count === 1 ? "Cart, 1 item" : `Cart, ${cart.count} items`;

  return (
    <Link
      href="/cart"
      aria-label={label}
      className="relative inline-flex size-9 items-center justify-center rounded-md text-muted hover:bg-surface hover:text-text"
    >
      <svg
        aria-hidden="true"
        viewBox="0 0 20 20"
        className="size-5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M2.5 3h2l1.6 9.2a1.5 1.5 0 0 0 1.5 1.3h6.9a1.5 1.5 0 0 0 1.5-1.2L17.2 6H5.3" />
        <circle cx="8" cy="16.8" r="1" />
        <circle cx="14.5" cy="16.8" r="1" />
      </svg>
      {cart.count > 0 && (
        <span
          aria-hidden="true"
          className="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-accent px-1 text-center font-mono text-[10px] leading-4 font-semibold text-accent-ink"
        >
          {cart.count > 9 ? "9+" : cart.count}
        </span>
      )}
    </Link>
  );
}
