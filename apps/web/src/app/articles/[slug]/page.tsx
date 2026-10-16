import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ArticleView } from "@/components/content/ArticleView";
import { JsonLd } from "@/components/seo/JsonLd";
import { getArticle } from "@/lib/content/api";
import { articleSchema } from "@/lib/seo/structuredData";
import { getSiteConfig } from "@/lib/site/config";

/** An article. The proxy has already answered unknown addresses with a real 404 (or a 301 if it moved). */
export const instant = false;

export async function generateMetadata({ params }: PageProps<"/articles/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const article = await getArticle(slug);
  if (!article) return {};

  const image = article.seo.image;
  const share = image ? (image.variants.find((v) => v.name === "lg") ?? image) : null;

  return {
    title: article.seo.title || article.title,
    description: article.seo.description ?? undefined,
    alternates: { canonical: article.url },
    robots: article.seo.noindex ? { index: false, follow: false } : undefined,
    openGraph: {
      type: "article",
      title: article.seo.title || article.title,
      description: article.seo.description ?? undefined,
      url: article.url,
      publishedTime: article.publishedAt ?? undefined,
      modifiedTime: article.updatedAt ?? undefined,
      tags: article.tags.map((t) => t.name),
      images: share ? [{ url: share.url, width: share.width ?? undefined, height: share.height ?? undefined, alt: image?.alt }] : undefined,
    },
  };
}

export default async function ArticlePage({ params }: PageProps<"/articles/[slug]">) {
  const { slug } = await params;
  const [article, site] = await Promise.all([getArticle(slug), getSiteConfig()]);
  if (!article) notFound();

  return (
    <>
      <JsonLd data={articleSchema(article, site)} />
      <ArticleView article={article} />
    </>
  );
}
