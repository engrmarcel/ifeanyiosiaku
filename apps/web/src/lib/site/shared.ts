import { z } from "zod";
import { mediaSchema } from "@/lib/cms/media";

/** Site configuration types and defaults, safe to use in browser components. */

/** Cache tag for the shared layout. Laravel refreshes it when navigation, theme or profile change. */
export const SITE_TAG = "site";

const navLink = z.object({ label: z.string().min(1), url: z.string().min(1), newTab: z.boolean() });

export const siteConfigSchema = z.object({
  name: z.string(),
  tagline: z.string().nullable(),
  footerNote: z.string().nullable(),
  year: z.number().int().nullable(),
  navigation: z.object({ header: z.array(navLink), footer: z.array(navLink) }),
  social: z.array(z.object({ network: z.string(), label: z.string(), url: z.string() })),
  theme: z.object({
    accent: z.string(),
    tokens: z.object({
      accent: z.string().regex(/^#[0-9a-f]{6}$/i),
      accentStrong: z.string().regex(/^#[0-9a-f]{6}$/i),
      accentInk: z.string().regex(/^#[0-9a-f]{6}$/i),
    }),
  }),
  indexing: z.boolean(),
  newsletter: z.object({ enabled: z.boolean(), consentText: z.string() }),
  profile: z.object({
    name: z.string(),
    headline: z.string().nullable(),
    shortBio: z.string().nullable(),
    portrait: mediaSchema.nullable(),
  }),
});

export type SiteConfig = z.infer<typeof siteConfigSchema>;
export type NavLink = z.infer<typeof navLink>;

/** Used only when the API cannot be reached; mirrors the API's own defaults. */
export const FALLBACK_SITE_CONFIG: SiteConfig = {
  name: "Ifeanyi Osiaku",
  tagline: "Software Developer | AI Enthusiast | Business Intelligence & Data Analytics Professional",
  footerNote: "Software · AI · Business Intelligence · Data",
  year: null,
  navigation: {
    header: [
      { label: "About", url: "/#about", newTab: false },
      { label: "Work", url: "/#work", newTab: false },
      { label: "Services", url: "/#services", newTab: false },
      { label: "Contact", url: "/contact", newTab: false },
    ],
    footer: [],
  },
  social: [],
  theme: { accent: "green", tokens: { accent: "#a3f7bf", accentStrong: "#7fe9a3", accentInk: "#062012" } },
  indexing: true,
  newsletter: { enabled: false, consentText: "" },
  profile: { name: "Ifeanyi Osiaku", headline: null, shortBio: null, portrait: null },
};

export { isExternal } from "./links";
