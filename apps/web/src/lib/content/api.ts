import { cacheLife, cacheTag } from "next/cache";
import type { z } from "zod";
import { serverGet } from "@/lib/api/server";
import { pageTag } from "@/lib/cms/pages";
import {
  articleListSchema,
  articleSchema,
  publicationListSchema,
  publicationSchema,
  type Article,
  type ArticleList,
  type Publication,
  type PublicationList,
} from "./schemas";

const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export type ArticleFilters = { category?: string; tag?: string; q?: string; page?: number };

/** Keeps only well-formed filter values, so arbitrary input never reaches the API or the cache. */
export function cleanFilters(params: Record<string, string | string[] | undefined>): ArticleFilters {
  const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  const category = one(params.category);
  const tag = one(params.tag);
  const q = one(params.q)?.trim().slice(0, 100);
  const page = Number.parseInt(one(params.page) ?? "1", 10);

  return {
    category: category && SLUG.test(category) ? category : undefined,
    tag: tag && SLUG.test(tag) ? tag : undefined,
    q: q || undefined,
    page: Number.isFinite(page) && page > 1 ? Math.min(page, 1000) : undefined,
  };
}

function query(filters: ArticleFilters): string {
  const params = new URLSearchParams();
  if (filters.category) params.set("category", filters.category);
  if (filters.tag) params.set("tag", filters.tag);
  if (filters.q) params.set("q", filters.q);
  if (filters.page) params.set("page", String(filters.page));
  const text = params.toString();
  return text ? `?${text}` : "";
}

function parse<T>(schema: z.ZodType<T>, value: unknown, what: string): T | null {
  const parsed = schema.safeParse(value);
  if (!parsed.success) console.error(`[content] Unexpected ${what} data`, parsed.error.issues.slice(0, 3));
  return parsed.success ? parsed.data : null;
}

async function fetchArticles(filters: ArticleFilters): Promise<ArticleList | null> {
  const { status, body } = await serverGet<unknown>(`/api/v1/articles${query(filters)}`);
  return status === 200 ? parse(articleListSchema, body, "article list") : null;
}

/** Category, tag and page views are cached until an article changes. */
async function cachedArticles(filters: Omit<ArticleFilters, "q">): Promise<ArticleList | null> {
  "use cache";
  cacheTag(pageTag("/articles"), "pages");
  cacheLife("hours");
  return fetchArticles(filters);
}

/** Search results are fetched fresh (search terms are endless, so they are not cached here). */
export async function getArticles(filters: ArticleFilters): Promise<ArticleList | null> {
  if (filters.q) return fetchArticles(filters);
  return cachedArticles({ category: filters.category, tag: filters.tag, page: filters.page });
}

export async function getArticle(slug: string): Promise<Article | null> {
  "use cache";
  cacheTag(pageTag(`/articles/${slug}`), "pages");
  cacheLife("hours");

  if (!SLUG.test(slug)) return null;
  const { status, body } = await serverGet<{ data: unknown }>(`/api/v1/articles/${slug}`);
  return status === 200 ? parse(articleSchema, body?.data, "article") : null;
}

/** Draft shown through a signed preview link. Never cached. */
export async function getArticlePreview(token: string): Promise<Article | null> {
  const { status, body } = await serverGet<{ data: unknown }>(`/api/v1/preview/article?token=${encodeURIComponent(token)}`);
  return status === 200 ? parse(articleSchema, body?.data, "article preview") : null;
}

export async function getPublications(type?: string): Promise<PublicationList | null> {
  "use cache";
  cacheTag(pageTag("/publications"), "pages");
  cacheLife("hours");

  const suffix = type && SLUG.test(type) ? `?type=${type}` : "";
  const { status, body } = await serverGet<unknown>(`/api/v1/publications${suffix}`);
  return status === 200 ? parse(publicationListSchema, body, "publication list") : null;
}

export async function getPublication(slug: string): Promise<Publication | null> {
  "use cache";
  cacheTag(pageTag(`/publications/${slug}`), "pages");
  cacheLife("hours");

  if (!SLUG.test(slug)) return null;
  const { status, body } = await serverGet<{ data: unknown }>(`/api/v1/publications/${slug}`);
  return status === 200 ? parse(publicationSchema, body?.data, "publication") : null;
}

/** Link to the article list with the given filters (used by filter chips and pagination). */
export function articlesHref(filters: ArticleFilters): string {
  return `/articles${query(filters)}`;
}
