import { z } from "zod";
import { mediaSchema } from "@/lib/cms/media";

/** Website-side checks for store data from the API (malformed data is not rendered). */

const money = z.object({ amount: z.number().int(), currency: z.string().length(3), formatted: z.string() });
const price = money.extend({ compareAt: money.nullable() });
const term = z.object({ slug: z.string(), name: z.string() });

export const productCardSchema = z.object({
  id: z.string(),
  slug: z.string(),
  url: z.string().startsWith("/store/"),
  title: z.string(),
  summary: z.string().nullable(),
  type: z.string(),
  typeLabel: z.string(),
  cover: mediaSchema.nullable(),
  isFree: z.boolean(),
  price: price.nullable(),
  available: z.boolean(),
  isFeatured: z.boolean(),
  categories: z.array(term),
});

export const productSchema = productCardSchema.extend({
  descriptionHtml: z.string(),
  tags: z.array(term),
  licenceTerms: z.string().nullable(),
  versions: z.array(z.object({ label: z.string(), changelog: z.string().nullable(), releasedAt: z.string() })),
  includes: z.array(z.object({ label: z.string(), size: z.number(), extension: z.string() })),
  bundle: z.array(productCardSchema),
  preview: z
    .object({ url: z.string(), kind: z.enum(["image", "pdf"]), name: z.string(), image: mediaSchema.nullable() })
    .nullable(),
  currency: z.string(),
  currencies: z.array(z.string()),
  updatedAt: z.string().nullable(),
  seo: z.object({ title: z.string().nullable(), description: z.string().nullable(), noindex: z.boolean(), image: mediaSchema.nullable() }),
  related: z.array(productCardSchema),
});

export const productListSchema = z.object({
  data: z.array(productCardSchema),
  meta: z.object({ page: z.number().int(), lastPage: z.number().int(), total: z.number().int(), currency: z.string(), currencies: z.array(z.string()) }),
  filters: z.object({
    categories: z.array(term.extend({ count: z.number().int() })),
    types: z.array(z.object({ type: z.string(), label: z.string() })),
    active: z.object({ category: z.string().nullable(), type: z.string().nullable(), q: z.string().nullable() }),
  }),
});

export type ProductCard = z.infer<typeof productCardSchema>;
export type Product = z.infer<typeof productSchema>;
export type ProductList = z.infer<typeof productListSchema>;
