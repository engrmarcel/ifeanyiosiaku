import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { EMPTY_CART, isOrderId, type Cart } from "@/lib/commerce/cart";

const api = vi.fn();
const toast = vi.fn();
let commerceOn = true;

vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));
vi.mock("@/lib/api/client", async (original) => ({
  ...(await original<typeof import("@/lib/api/client")>()),
  api: (...args: unknown[]) => api(...args),
}));
vi.mock("@/lib/auth/AuthProvider", () => ({ useAuth: () => ({ user: null, status: "guest" }) }));
vi.mock("@/lib/settings/SiteSettingsProvider", () => ({ useAddon: () => commerceOn }));
vi.mock("@/components/ui/Toast", () => ({ useToast: () => ({ toast }) }));

const { CartProvider, useCart } = await import("@/lib/commerce/CartProvider");
const { CartLink } = await import("./CartLink");
const { AddToCartButton } = await import("./AddToCartButton");
const { OrderSummary } = await import("./OrderSummary");

const ngn = (amount: number, formatted: string) => ({ amount, currency: "NGN", formatted });
const product = {
  id: "01jabcdefghjkmnpqrstvwxyz0",
  slug: "dax-handbook",
  url: "/store/dax-handbook",
  title: "The DAX Handbook",
  summary: null,
  type: "ebook",
  typeLabel: "eBook",
  cover: null,
  isFree: false,
  price: { ...ngn(1_250_000, "₦12,500.00"), compareAt: null },
  available: true,
  isFeatured: false,
  categories: [],
};
const withItem: Cart = {
  ...EMPTY_CART,
  items: [
    {
      product,
      unit: ngn(1_250_000, "₦12,500.00"),
      discount: ngn(0, "₦0.00"),
      total: ngn(1_250_000, "₦12,500.00"),
      problem: null,
    },
  ],
  subtotal: ngn(1_250_000, "₦12,500.00"),
  total: ngn(1_250_000, "₦12,500.00"),
  purchasable: true,
  count: 1,
};

beforeEach(() => {
  api.mockReset();
  toast.mockReset();
  commerceOn = true;
});

describe("cart", () => {
  it("shows the item count in the header and switches the button once added", async () => {
    api.mockResolvedValueOnce({ data: EMPTY_CART }).mockResolvedValueOnce({ data: withItem });

    render(
      <CartProvider>
        <CartLink />
        <AddToCartButton product={product} />
      </CartProvider>,
    );

    await waitFor(() => expect(screen.getByRole("link", { name: "Cart, 0 items" })).toBeInTheDocument());
    await act(async () => fireEvent.click(screen.getByRole("button", { name: "Add to cart" })));

    expect(api).toHaveBeenLastCalledWith("/api/v1/store/cart/items", { method: "POST", body: { product: product.id } });
    expect(screen.getByRole("link", { name: "Cart, 1 item" })).toHaveAttribute("href", "/cart");
    expect(screen.getByRole("link", { name: "In your cart · View cart" })).toBeInTheDocument();
    expect(toast).toHaveBeenCalledWith(expect.objectContaining({ tone: "success" }));
  });

  it("sends cart requests one at a time, so a slow first load cannot hide a new item", async () => {
    let finishLoad: (value: unknown) => void = () => undefined;
    api.mockImplementationOnce(() => new Promise((resolve) => (finishLoad = resolve))).mockResolvedValueOnce({ data: withItem });

    let addNow: () => Promise<void> = async () => undefined;
    function Grab() {
      const { add } = useCart();
      addNow = () => add(product.id);
      return null;
    }

    render(
      <CartProvider>
        <Grab />
        <CartLink />
      </CartProvider>,
    );

    await waitFor(() => expect(api).toHaveBeenCalledTimes(1));
    let added: Promise<void> = Promise.resolve();
    act(() => {
      added = addNow();
    });
    // The add waits for the load that started first…
    expect(api).toHaveBeenCalledTimes(1);
    // …and the (now outdated) empty load does not overwrite it.
    await act(async () => {
      finishLoad({ data: EMPTY_CART });
      await added;
    });
    expect(api).toHaveBeenCalledTimes(2);
    expect(screen.getByRole("link", { name: "Cart, 1 item" })).toBeInTheDocument();
  });

  it("does nothing while the store is switched off", () => {
    commerceOn = false;
    render(
      <CartProvider>
        <CartLink />
      </CartProvider>,
    );
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
    expect(api).not.toHaveBeenCalled();
  });

  it("ignores cart data in an unexpected shape", async () => {
    const error = vi.spyOn(console, "error").mockImplementation(() => undefined);
    api.mockResolvedValueOnce({ data: { total: "free!" } });
    render(
      <CartProvider>
        <CartLink />
      </CartProvider>,
    );
    await waitFor(() => expect(error).toHaveBeenCalled());
    expect(screen.getByRole("link", { name: "Cart, 0 items" })).toBeInTheDocument();
    error.mockRestore();
  });

  it("hides the button for products that cannot be bought", () => {
    api.mockResolvedValue({ data: EMPTY_CART });
    render(
      <CartProvider>
        <AddToCartButton product={{ ...product, available: false }} />
      </CartProvider>,
    );
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
  });

  it("summarises totals and only shows a discount when there is one", () => {
    const { rerender } = render(
      <OrderSummary subtotal={ngn(100, "₦1.00")} discount={ngn(0, "₦0.00")} total={ngn(100, "₦1.00")} />,
    );
    expect(screen.queryByText(/Discount/)).not.toBeInTheDocument();
    rerender(
      <OrderSummary
        subtotal={ngn(100, "₦1.00")}
        discount={ngn(10, "₦0.10")}
        total={ngn(90, "₦0.90")}
        couponCode="TEN"
      />,
    );
    expect(screen.getByText("Discount (TEN)")).toBeInTheDocument();
    expect(screen.getByText("₦0.90")).toBeInTheDocument();
  });

  it("only accepts real order ids", () => {
    expect(isOrderId("01jabcdefghjkmnpqrstvwxyz0")).toBe(true);
    expect(isOrderId("../admin")).toBe(false);
    expect(isOrderId("01JABCDEFGHJKMNPQRSTVWXYZ0")).toBe(false);
  });
});
