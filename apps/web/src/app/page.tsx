import type { Metadata } from "next";
import { connection } from "next/server";
import { CmsPageView } from "@/components/cms/CmsPageView";
import { InterimHome } from "@/components/home/InterimHome";
import { cmsMetadata } from "@/lib/cms/metadata";
import { getPublishedPage, type PageLookup } from "@/lib/cms/pages";

/** The CMS homepage once one is published; the interim design before that. */
export const instant = false;

async function homepage(): Promise<PageLookup> {
  // Rendered per request (data is still cached), so builds never depend on the API being up.
  await connection();

  try {
    return await getPublishedPage("/");
  } catch (error) {
    console.error("[cms] Homepage could not be loaded; showing the interim homepage.", error);
    return { kind: "missing" };
  }
}

export async function generateMetadata(): Promise<Metadata> {
  const result = await homepage();
  return result.kind === "page" ? cmsMetadata(result.page) : {};
}

export default async function HomePage() {
  const result = await homepage();
  return result.kind === "page" ? <CmsPageView page={result.page} /> : <InterimHome />;
}
