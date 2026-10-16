import type { ProjectDetail } from "@/lib/cms/schemas";
import type { Article, Publication } from "@/lib/content/schemas";
import type { SiteConfig } from "@/lib/site/shared";
import { SITE_URL, absoluteUrl } from "@/lib/site/url";

/**
 * schema.org data that helps search engines show the owner's name, role, photo and profiles.
 * Built only from real profile fields; empty fields are left out.
 */

const personId = `${SITE_URL}/#person`;
const websiteId = `${SITE_URL}/#website`;

type Media = SiteConfig["profile"]["portrait"];

function imageUrl(media: Media): string | undefined {
  if (!media) return undefined;
  const best = media.variants.find((v) => v.name === "lg") ?? media;
  return absoluteUrl(best.url);
}

function clean<T extends Record<string, unknown>>(data: T): T {
  return Object.fromEntries(
    Object.entries(data).filter(([, v]) => v !== undefined && v !== null && !(Array.isArray(v) && v.length === 0)),
  ) as T;
}

export function personSchema(site: SiteConfig): Record<string, unknown> {
  return clean({
    "@type": "Person",
    "@id": personId,
    name: site.profile.name,
    jobTitle: site.profile.headline ?? undefined,
    description: site.profile.shortBio ?? undefined,
    url: `${SITE_URL}/`,
    image: imageUrl(site.profile.portrait),
    sameAs: site.social.filter((s) => s.url.startsWith("https://")).map((s) => s.url),
  });
}

/** Homepage: the person and the website. */
export function homepageSchema(site: SiteConfig): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@graph": [
      personSchema(site),
      clean({
        "@type": "WebSite",
        "@id": websiteId,
        url: `${SITE_URL}/`,
        name: site.name,
        description: site.tagline ?? undefined,
        publisher: { "@id": personId },
      }),
    ],
  };
}

/** Case study page: the project, credited to the person. */
export function projectSchema(project: ProjectDetail, site: SiteConfig): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@graph": [
      clean({
        "@type": "CreativeWork",
        "@id": `${absoluteUrl(project.url)}#work`,
        name: project.title,
        description: project.summary ?? undefined,
        url: absoluteUrl(project.url),
        image: imageUrl(project.cover),
        keywords: project.technologies.length > 0 ? project.technologies.join(", ") : undefined,
        author: { "@id": personId },
      }),
      personSchema(site),
    ],
  };
}

/** Home › Section › Item trail shown in search results. */
export function breadcrumbSchema(items: { name: string; path: string }[]): Record<string, unknown> {
  return {
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: absoluteUrl(item.path),
    })),
  };
}

/** Article page: a technical article by the person, with its breadcrumb trail. */
export function articleSchema(article: Article, site: SiteConfig): Record<string, unknown> {
  const url = absoluteUrl(article.url);

  return {
    "@context": "https://schema.org",
    "@graph": [
      clean({
        "@type": "TechArticle",
        "@id": `${url}#article`,
        headline: article.title,
        description: article.seo.description ?? article.excerpt ?? undefined,
        url,
        mainEntityOfPage: url,
        image: imageUrl(article.seo.image ?? article.cover),
        datePublished: article.publishedAt ?? undefined,
        dateModified: article.updatedAt ?? article.publishedAt ?? undefined,
        timeRequired: `PT${article.readingMinutes}M`,
        articleSection: article.categories[0]?.name,
        keywords: article.tags.length > 0 ? article.tags.map((t) => t.name).join(", ") : undefined,
        author: { "@id": personId },
        publisher: { "@id": personId },
      }),
      breadcrumbSchema([
        { name: "Home", path: "/" },
        { name: "Articles", path: "/articles" },
        { name: article.title, path: article.url },
      ]),
      personSchema(site),
    ],
  };
}

/** Publication page: a book, paper or report, with identifiers when known. */
export function publicationSchema(publication: Publication, site: SiteConfig): Record<string, unknown> {
  const url = absoluteUrl(publication.url);
  const type = publication.type === "ebook" ? "Book" : publication.type === "technical" ? "Report" : "ScholarlyArticle";
  const latest = publication.versions[0];

  return {
    "@context": "https://schema.org",
    "@graph": [
      clean({
        "@type": type,
        "@id": `${url}#publication`,
        name: publication.title,
        headline: type === "ScholarlyArticle" ? publication.title : undefined,
        description: publication.seo.description ?? publication.summary ?? undefined,
        url,
        image: imageUrl(publication.cover),
        datePublished: publication.publishedAt ?? undefined,
        dateModified: publication.updatedAt ?? undefined,
        version: latest?.label,
        isbn: type === "Book" ? (publication.isbn ?? undefined) : undefined,
        sameAs: publication.doi ? [`https://doi.org/${publication.doi}`] : undefined,
        author: publication.authors.length > 0 ? publication.authors.map((name) => ({ "@type": "Person", name })) : { "@id": personId },
      }),
      breadcrumbSchema([
        { name: "Home", path: "/" },
        { name: "Publications", path: "/publications" },
        { name: publication.title, path: publication.url },
      ]),
      personSchema(site),
    ],
  };
}
