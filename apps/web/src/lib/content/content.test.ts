import { describe, expect, it, vi } from "vitest";
import { FALLBACK_SITE_CONFIG } from "@/lib/site/shared";
import { articleSchema as articleJsonLd, publicationSchema as publicationJsonLd } from "@/lib/seo/structuredData";
import { formatBytes, formatDate } from "./format";
import type { Article, Publication } from "./schemas";

vi.mock("next/cache", () => ({ cacheTag: vi.fn(), cacheLife: vi.fn() }));

const { articlesHref, cleanFilters } = await import("./api");

describe("cleanFilters", () => {
  it("keeps only well-formed filters", () => {
    expect(cleanFilters({ category: "business-intelligence", tag: "<script>", q: "  dax  ", page: "3" })).toEqual({
      category: "business-intelligence",
      tag: undefined,
      q: "dax",
      page: 3,
    });
    expect(cleanFilters({ page: "-5", q: "x".repeat(300) })).toEqual({ category: undefined, tag: undefined, q: "x".repeat(100), page: undefined });
    expect(cleanFilters({ category: ["a", "b"] }).category).toBe("a");
  });

  it("builds list links from filters", () => {
    expect(articlesHref({})).toBe("/articles");
    expect(articlesHref({ category: "bi", q: "sum & avg", page: 2 })).toBe("/articles?category=bi&q=sum+%26+avg&page=2");
  });
});

describe("format helpers", () => {
  it("formats dates in Lagos time and file sizes", () => {
    expect(formatDate("2026-10-14T23:30:00Z")).toBe("15 Oct 2026");
    expect(formatDate("2025-06-01")).toBe("1 Jun 2025");
    expect(formatDate("not a date")).toBeNull();
    expect(formatBytes(2_500_000)).toBe("2.4 MB");
    expect(formatBytes(300)).toBe("1 KB");
  });
});

describe("structured data", () => {
  const article: Article = {
    id: "01",
    slug: "power-bi-measures",
    url: "/articles/power-bi-measures",
    title: "Writing Power BI measures",
    excerpt: "DAX patterns.",
    cover: null,
    publishedAt: "2026-10-14T09:00:00+00:00",
    updatedAt: "2026-10-15T09:00:00+00:00",
    readingMinutes: 4,
    categories: [{ slug: "bi", name: "BI" }],
    tags: [{ slug: "dax", name: "DAX" }],
    draft: false,
    segments: [],
    toc: [],
    seo: { title: null, description: "DAX patterns.", noindex: false, image: null },
    related: [],
  };

  it("describes an article with its author and breadcrumb trail", () => {
    const graph = articleJsonLd(article, FALLBACK_SITE_CONFIG)["@graph"] as Record<string, unknown>[];

    expect(graph[0]).toMatchObject({ "@type": "TechArticle", headline: "Writing Power BI measures", timeRequired: "PT4M", keywords: "DAX" });
    expect(graph[1]).toMatchObject({ "@type": "BreadcrumbList" });
    expect((graph[1].itemListElement as unknown[]).length).toBe(3);
  });

  it("describes an eBook as a Book with its ISBN and named authors", () => {
    const publication: Publication = {
      id: "02",
      slug: "practical-dax",
      url: "/publications/practical-dax",
      title: "Practical DAX",
      type: "ebook",
      typeLabel: "eBook",
      summary: null,
      authors: ["Ifeanyi Osiaku"],
      cover: null,
      publishedAt: null,
      latestVersion: "1.0",
      abstract: null,
      doi: "10.1234/abc",
      isbn: "978-3-16-148410-0",
      updatedAt: null,
      versions: [{ label: "1.0", changelog: null, releasedAt: "2026-10-01", file: null, externalUrl: null }],
      seo: { title: null, description: null, noindex: false, image: null },
    };

    const node = (publicationJsonLd(publication, FALLBACK_SITE_CONFIG)["@graph"] as Record<string, unknown>[])[0];
    expect(node).toMatchObject({ "@type": "Book", isbn: "978-3-16-148410-0", version: "1.0", sameAs: ["https://doi.org/10.1234/abc"] });
    expect(node.author).toEqual([{ "@type": "Person", name: "Ifeanyi Osiaku" }]);
  });
});
