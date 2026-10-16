import { z } from "zod";

// Every schema module imports this file, so this runs before any data is checked. Zod otherwise probes
// whether eval() is allowed, which trips the site's Content-Security-Policy; the speed-up is not needed here.
z.config({ jitless: true });

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
