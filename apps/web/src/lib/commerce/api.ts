import { cacheLife, cacheTag } from "next/cache";
import type { z } from "zod";
import { serverGet } from "@/lib/api/server";
import { pageTag } from "@/lib/cms/pages";
import { productListSchema, productSchema, type Product, type ProductList } from "./schemas";

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const CURRENCY = /^[A-Z]{3}$/;

export type StoreFilters = { category?: string; type?: string; q?: string; page?: number; currency?: string };

/** Keeps only well-formed filter values, so arbitrary input never reaches the API or the cache. */
export function cleanStoreFilters(params: Record<string, string | string[] | undefined>): StoreFilters {
  const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  const category = one(params.category);
  const type = one(params.type);
  const q = one(params.q)?.trim().slice(0, 100);
  const page = Number.parseInt(one(params.page) ?? "1", 10);
  const currency = one(params.currency)?.toUpperCase();

  return {
    category: category && SLUG.test(category) ? category : undefined,
    type: type && /^[a-z_]{2,30}$/.test(type) ? type : undefined,
    q: q || undefined,
    page: Number.isFinite(page) && page > 1 ? Math.min(page, 1000) : undefined,
    currency: currency && CURRENCY.test(currency) ? currency : undefined,
  };
}

function query(filters: StoreFilters): string {
  const params = new URLSearchParams();
  for (const key of ["category", "type", "q", "page", "currency"] as const) {
    const value = filters[key];
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const text = params.toString();
  return text ? `?${text}` : "";
}

function parse<T>(schema: z.ZodType<T>, value: unknown, what: string): T | null {
  const parsed = schema.safeParse(value);
  if (!parsed.success) console.error(`[store] Unexpected ${what} data`, parsed.error.issues.slice(0, 3));
  return parsed.success ? parsed.data : null;
}

/** 404 from the API means the store is switched off (or the product does not exist). */
async function fetchProducts(filters: StoreFilters): Promise<ProductList | null> {
  const { status, body } = await serverGet<unknown>(`/api/v1/store/products${query(filters)}`);
  return status === 200 ? parse(productListSchema, body, "product list") : null;
}

async function cachedProducts(filters: Omit<StoreFilters, "q">): Promise<ProductList | null> {
  "use cache";
  cacheTag(pageTag("/store"), "pages", "site");
  cacheLife("hours");
  return fetchProducts(filters);
}

export async function getProducts(filters: StoreFilters): Promise<ProductList | null> {
  if (filters.q) return fetchProducts(filters);
  return cachedProducts({ category: filters.category, type: filters.type, page: filters.page, currency: filters.currency });
}

export async function getProduct(slug: string, currency?: string): Promise<Product | null> {
  "use cache";
  cacheTag(pageTag(`/store/${slug}`), "pages", "site");
  cacheLife("hours");

  if (!SLUG.test(slug)) return null;
  const suffix = currency && CURRENCY.test(currency) ? `?currency=${currency}` : "";
  const { status, body } = await serverGet<{ data: unknown }>(`/api/v1/store/products/${slug}${suffix}`);
  return status === 200 ? parse(productSchema, body?.data, "product") : null;
}

export function storeHref(filters: StoreFilters): string {
  return `/store${query(filters)}`;
}
