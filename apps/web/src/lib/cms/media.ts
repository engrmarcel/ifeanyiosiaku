import { z } from "zod";

/** A media library image as the API sends it (shared by CMS blocks, articles and site settings). */
export const mediaSchema = z.object({
  id: z.string(),
  url: z.string(),
  mime: z.string(),
  width: z.number().nullable(),
  height: z.number().nullable(),
  alt: z.string(),
  title: z.string().nullable(),
  variants: z.array(z.object({ name: z.string(), url: z.string(), width: z.number(), height: z.number(), mime: z.string() })),
});
