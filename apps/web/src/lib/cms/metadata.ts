import type { Metadata } from "next";
import type { CmsPage } from "./schemas";

/** Page metadata (title, description, canonical, robots, social sharing) from CMS SEO fields. */
export function cmsMetadata(page: CmsPage): Metadata {
  const title = page.seo.title || page.title;
  const description = page.seo.description ?? undefined;
  const image = page.seo.image;
  const shareImage = image ? (image.variants.find((v) => v.name === "lg") ?? image) : null;

  return {
    title: page.isHomepage ? { absolute: title } : title,
    description,
    alternates: { canonical: page.seo.canonicalUrl || page.path },
    robots: page.seo.noindex ? { index: false, follow: false } : undefined,
    openGraph: {
      type: "website",
      title,
      description,
      url: page.path,
      images: shareImage
        ? [{ url: shareImage.url, width: shareImage.width ?? undefined, height: shareImage.height ?? undefined, alt: image?.alt }]
        : undefined,
    },
  };
}
