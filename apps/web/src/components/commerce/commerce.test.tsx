import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { Product, ProductCard as ProductCardData } from "@/lib/commerce/schemas";
import { productSchema } from "@/lib/seo/structuredData";
import { FALLBACK_SITE_CONFIG } from "@/lib/site/shared";
import { PriceTag } from "./PriceTag";
import { ProductCard } from "./ProductCard";

vi.mock("next/cache", () => ({ cacheTag: vi.fn(), cacheLife: vi.fn() }));
vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

const { cleanStoreFilters, storeHref } = await import("@/lib/commerce/api");

const card: ProductCardData = {
  id: "01p",
  slug: "dax-handbook",
  url: "/store/dax-handbook",
  title: "The DAX Handbook",
  summary: "Practical DAX patterns.",
  type: "ebook",
  typeLabel: "eBook",
  cover: null,
  isFree: false,
  price: { amount: 1_250_000, currency: "NGN", formatted: "₦12,500.00", compareAt: { amount: 1_500_000, currency: "NGN", formatted: "₦15,000.00" } },
  available: true,
  isFeatured: true,
  categories: [],
};

describe("PriceTag", () => {
  it("shows the price, the former price for screen readers, free and unavailable states", () => {
    const { rerender } = render(<PriceTag product={card} />);
    expect(screen.getByText("₦12,500.00")).toBeInTheDocument();
    expect(screen.getByText("₦15,000.00").closest("s")).toHaveTextContent("was ₦15,000.00");

    rerender(<PriceTag product={{ ...card, isFree: true, price: null }} />);
    expect(screen.getByText("Free")).toBeInTheDocument();

    rerender(<PriceTag product={{ ...card, price: null, available: false }} />);
    expect(screen.getByText("Not available in this currency")).toBeInTheDocument();
  });
});

describe("ProductCard", () => {
  it("links to the product page", () => {
    render(<ProductCard product={card} />);
    expect(screen.getByRole("link", { name: "The DAX Handbook" })).toHaveAttribute("href", "/store/dax-handbook");
    expect(screen.getByText("featured")).toBeInTheDocument();
  });
});

describe("store filters", () => {
  it("keeps only well-formed values and builds links", () => {
    expect(cleanStoreFilters({ category: "power-bi", type: "ebook", currency: "usd", page: "2", q: " dax " })).toEqual({
      category: "power-bi", type: "ebook", currency: "USD", page: 2, q: "dax",
    });
    expect(cleanStoreFilters({ category: "<x>", type: "DROP TABLE", currency: "dollars" })).toEqual({
      category: undefined, type: undefined, currency: undefined, page: undefined, q: undefined,
    });
    expect(storeHref({ category: "power-bi", currency: "USD" })).toBe("/store?category=power-bi&currency=USD");
  });
});

describe("product structured data", () => {
  const product = { ...card, currency: "NGN", currencies: ["NGN"], seo: { title: null, description: null, noindex: false, image: null } } as unknown as Product;

  it("offers the exact price, and no offer when it cannot be bought", () => {
    const node = (productSchema(product, FALLBACK_SITE_CONFIG)["@graph"] as Record<string, unknown>[])[0];
    expect(node.offers).toMatchObject({ "@type": "Offer", price: "12500.00", priceCurrency: "NGN" });

    const unavailable = (productSchema({ ...product, price: null, available: false }, FALLBACK_SITE_CONFIG)["@graph"] as Record<string, unknown>[])[0];
    expect(unavailable).not.toHaveProperty("offers");
  });
});
