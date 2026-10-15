import type { Metadata } from "next";
import { notFound, permanentRedirect, redirect } from "next/navigation";
import { CmsPageView } from "@/components/cms/CmsPageView";
import { cmsMetadata } from "@/lib/cms/metadata";
import { getPublishedPage } from "@/lib/cms/pages";

/**
 * Any address not handled by a dedicated route is looked up in the CMS. The page renders on the
 * server so missing pages return a real 404 and moved pages a real 301 (important for search
 * engines). The page data itself is cached until the CMS reports a change.
 */
export const instant = false;

function toPath(slug: string[]): string {
  return "/" + slug.map((part) => part.toLowerCase()).join("/");
}

async function lookup(params: PageProps<"/[...slug]">["params"]) {
  const { slug } = await params;
  return getPublishedPage(toPath(slug));
}

export async function generateMetadata({ params }: PageProps<"/[...slug]">): Promise<Metadata> {
  const result = await lookup(params);
  return result.kind === "page" ? cmsMetadata(result.page) : {};
}

export default async function CmsPage({ params }: PageProps<"/[...slug]">) {
  const result = await lookup(params);

  if (result.kind === "redirect") {
    if (result.status === 301) permanentRedirect(result.to);
    redirect(result.to);
  }

  if (result.kind === "missing") notFound();

  return <CmsPageView page={result.page} />;
}
