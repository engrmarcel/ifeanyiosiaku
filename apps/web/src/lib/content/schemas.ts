import { z } from "zod";
import { mediaSchema } from "@/lib/cms/media";

/** Website-side checks for article and publication data from the API (malformed data is not rendered). */

const term = z.object({ slug: z.string(), name: z.string() });

export const articleCardSchema = z.object({
  slug: z.string(),
  url: z.string().startsWith("/articles/"),
  title: z.string(),
  excerpt: z.string().nullable(),
  cover: mediaSchema.nullable(),
  publishedAt: z.string().nullable(),
  readingMinutes: z.number().int(),
  categories: z.array(term),
});

const segment = z.discriminatedUnion("type", [
  z.object({ type: z.literal("html"), html: z.string() }),
  z.object({ type: z.literal("code"), language: z.string(), title: z.string().nullable(), code: z.string() }),
]);

export const articleSchema = articleCardSchema.extend({
  id: z.string(),
  draft: z.boolean(),
  updatedAt: z.string().nullable(),
  tags: z.array(term),
  segments: z.array(segment),
  toc: z.array(z.object({ id: z.string(), text: z.string(), level: z.number().int() })),
  seo: z.object({
    title: z.string().nullable(),
    description: z.string().nullable(),
    noindex: z.boolean(),
    image: mediaSchema.nullable(),
  }),
  related: z.array(articleCardSchema),
});

export const articleListSchema = z.object({
  data: z.array(articleCardSchema),
  meta: z.object({ page: z.number().int(), lastPage: z.number().int(), total: z.number().int() }),
  filters: z.object({
    categories: z.array(term.extend({ count: z.number().int() })),
    tags: z.array(term.extend({ count: z.number().int() })),
    active: z.object({ category: z.string().nullable(), tag: z.string().nullable(), q: z.string().nullable() }),
  }),
});

export const publicationCardSchema = z.object({
  slug: z.string(),
  url: z.string().startsWith("/publications/"),
  title: z.string(),
  type: z.string(),
  typeLabel: z.string(),
  summary: z.string().nullable(),
  authors: z.array(z.string()),
  cover: mediaSchema.nullable(),
  publishedAt: z.string().nullable(),
  latestVersion: z.string().nullable(),
});

export const publicationSchema = publicationCardSchema.extend({
  id: z.string(),
  abstract: z.string().nullable(),
  doi: z.string().nullable(),
  isbn: z.string().nullable(),
  updatedAt: z.string().nullable(),
  versions: z.array(
    z.object({
      label: z.string(),
      changelog: z.string().nullable(),
      releasedAt: z.string(),
      file: z.object({ url: z.string(), size: z.number(), name: z.string() }).nullable(),
      externalUrl: z.string().nullable(),
    }),
  ),
  seo: z.object({ title: z.string().nullable(), description: z.string().nullable(), noindex: z.boolean(), image: mediaSchema.nullable() }),
});

export const publicationListSchema = z.object({
  data: z.array(publicationCardSchema),
  filters: z.object({
    types: z.array(z.object({ type: z.string(), label: z.string() })),
    active: z.object({ type: z.string().nullable() }),
  }),
});

export type ArticleCard = z.infer<typeof articleCardSchema>;
export type Article = z.infer<typeof articleSchema>;
export type ArticleSegment = z.infer<typeof segment>;
export type ArticleList = z.infer<typeof articleListSchema>;
export type PublicationCard = z.infer<typeof publicationCardSchema>;
export type Publication = z.infer<typeof publicationSchema>;
export type PublicationList = z.infer<typeof publicationListSchema>;
