import type { ProjectDetail } from "@/lib/cms/schemas";
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
