import { z } from "zod";
import { productCardSchema } from "./schemas";

/** Website-side checks for cart and order data. Every amount is calculated by the server. */

const money = z.object({ amount: z.number().int(), currency: z.string().length(3), formatted: z.string() });

export const cartSchema = z.object({
  currency: z.string().length(3),
  items: z.array(
    z.object({
      product: productCardSchema,
      unit: money.nullable(),
      discount: money,
      total: money,
      problem: z.string().nullable(),
    }),
  ),
  subtotal: money,
  discount: money,
  total: money,
  coupon: z.object({ code: z.string(), description: z.string().nullable() }).nullable(),
  couponProblem: z.string().nullable(),
  purchasable: z.boolean(),
  count: z.number().int(),
  currencies: z.array(z.string()),
});

export const orderSchema = z.object({
  id: z.string(),
  number: z.string(),
  status: z.enum(["pending", "paid", "cancelled", "expired", "partially_refunded", "refunded"]),
  statusLabel: z.string(),
  currency: z.string(),
  subtotal: money,
  discount: money,
  total: money,
  couponCode: z.string().nullable(),
  expiresAt: z.string().nullable(),
  paidAt: z.string().nullable(),
  createdAt: z.string().nullable(),
  items: z.array(
    z.object({
      title: z.string(),
      type: z.string(),
      url: z.string().nullable(),
      unit: money,
      discount: money,
      total: money,
    }),
  ),
});

export const placedOrderSchema = orderSchema.extend({ next: z.enum(["complete", "pay"]) });

export type Cart = z.infer<typeof cartSchema>;
export type Order = z.infer<typeof orderSchema>;

export const EMPTY_CART: Cart = {
  currency: "NGN",
  items: [],
  subtotal: { amount: 0, currency: "NGN", formatted: "₦0.00" },
  discount: { amount: 0, currency: "NGN", formatted: "₦0.00" },
  total: { amount: 0, currency: "NGN", formatted: "₦0.00" },
  coupon: null,
  couponProblem: null,
  purchasable: false,
  count: 0,
  currencies: ["NGN"],
};

/** Order ids are lower-case ULIDs; anything else is not sent to the API. */
export function isOrderId(value: string): boolean {
  return /^[0-9a-z]{26}$/.test(value);
}
