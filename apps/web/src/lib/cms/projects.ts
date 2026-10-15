import { cacheLife, cacheTag } from "next/cache";
import { serverGet } from "@/lib/api/server";
import { pageTag } from "./pages";
import type { ProjectDetail } from "./schemas";

/** A published case study. Cached until the portfolio changes (same tags as pages). */
export async function getProject(slug: string): Promise<ProjectDetail | null> {
  "use cache";
  cacheTag(pageTag(`/projects/${slug}`), "pages");
  cacheLife("hours");

  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) return null;

  const { status, body } = await serverGet<{ data: ProjectDetail }>(`/api/v1/projects/${slug}`);
  return status === 200 && body?.data ? body.data : null;
}
