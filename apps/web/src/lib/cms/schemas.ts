import { z } from "zod";
import { articleCardSchema } from "@/lib/content/schemas";
import { mediaSchema } from "./media";

/**
 * Website-side schemas for CMS block props (ADR-007). The API already validates and sanitises
 * content; these guard the renderer so a malformed block is skipped instead of breaking the page.
 */

export { mediaSchema };

const link = z.object({ label: z.string(), url: z.string() });
const sectionHeader = z.object({ eyebrow: z.string().nullable(), heading: z.string(), intro: z.string().nullable() });
const skill = z.object({ name: z.string(), description: z.string().nullable() });
const projectCard = z.object({
  slug: z.string(),
  url: z.string(),
  title: z.string(),
  summary: z.string().nullable(),
  role: z.string().nullable(),
  period: z.string().nullable(),
  technologies: z.array(z.string()),
  cover: mediaSchema.nullable(),
});
const line = z.object({ kind: z.enum(["command", "output", "highlight", "comment"]), text: z.string() });
const html = z.string();

export const blockSchemas = {
  hero: z.object({
    eyebrow: z.string().nullable(),
    heading: z.string(),
    subheading: z.string().nullable(),
    body: z.string().nullable(),
    primary: link.nullable(),
    secondary: link.nullable(),
    media: mediaSchema.nullable(),
    terminal: z.array(line).nullable(),
  }),
  heading: z.object({
    eyebrow: z.string().nullable(),
    text: z.string(),
    intro: z.string().nullable(),
    level: z.union([z.literal(2), z.literal(3)]),
    align: z.enum(["left", "center"]),
  }),
  rich_text: z.object({ html, width: z.enum(["narrow", "wide"]) }),
  image: z.object({ media: mediaSchema.nullable(), caption: z.string().nullable(), size: z.enum(["normal", "wide", "full"]) }),
  gallery: z.object({
    columns: z.number(),
    images: z.array(z.object({ media: mediaSchema, caption: z.string().nullable() })),
  }),
  cards: z.object({
    columns: z.number(),
    items: z.array(
      z.object({
        eyebrow: z.string().nullable(),
        title: z.string(),
        body: z.string().nullable(),
        url: z.string().nullable(),
        linkLabel: z.string().nullable(),
        media: mediaSchema.nullable(),
      }),
    ),
  }),
  two_column: z.object({
    ratio: z.enum(["1:1", "2:1", "1:2"]),
    left: z.object({ html, media: mediaSchema.nullable() }),
    right: z.object({ html, media: mediaSchema.nullable() }),
  }),
  stats: z.object({ items: z.array(z.object({ value: z.string(), label: z.string(), description: z.string().nullable() })) }),
  timeline: z.object({
    items: z.array(z.object({ when: z.string(), title: z.string(), subtitle: z.string().nullable(), body: z.string().nullable() })),
  }),
  accordion: z.object({ faq: z.boolean(), items: z.array(z.object({ title: z.string(), html })) }),
  tabs: z.object({ label: z.string(), items: z.array(z.object({ title: z.string(), html })) }),
  code: z.object({
    language: z.string(),
    title: z.string().nullable(),
    code: z.string(),
    lineNumbers: z.boolean(),
    copyButton: z.boolean(),
    highlight: z.array(z.number()),
    annotations: z.array(z.object({ line: z.number(), note: z.string() })),
    caption: z.string().nullable(),
  }),
  terminal: z.object({ title: z.string(), animate: z.boolean(), lines: z.array(line) }),
  callout: z.object({ tone: z.enum(["info", "success", "warning", "danger"]), title: z.string().nullable(), html }),
  quote: z.object({ text: z.string(), attribution: z.string().nullable(), role: z.string().nullable() }),
  video: z.object({
    provider: z.enum(["youtube", "vimeo"]).nullable(),
    videoId: z.string().nullable(),
    title: z.string(),
    caption: z.string().nullable(),
  }),
  table: z.object({ caption: z.string(), header: z.array(z.string()).nullable(), rows: z.array(z.array(z.string())) }),
  cta: z.object({
    eyebrow: z.string().nullable(),
    heading: z.string(),
    body: z.string().nullable(),
    primary: link,
    secondary: link.nullable(),
  }),
  divider: z.object({ style: z.enum(["line", "space"]), size: z.enum(["sm", "md", "lg"]) }),

  // Data-driven portfolio sections (content comes from the Portfolio admin screens).
  profile_intro: sectionHeader.extend({
    name: z.string(),
    headline: z.string().nullable(),
    shortBio: z.string().nullable(),
    bioHtml: z.string(),
    yearsExperience: z.number().nullable(),
    portrait: mediaSchema.nullable(),
  }),
  expertise: sectionHeader.extend({
    expertise: z.array(skill),
    technologies: z.array(skill),
  }),
  projects: sectionHeader.extend({ items: z.array(projectCard) }),
  services: sectionHeader.extend({
    items: z.array(z.object({ title: z.string(), summary: z.string().nullable(), cta: link.nullable() })),
  }),
  experience: sectionHeader.extend({
    items: z.array(
      z.object({
        title: z.string(),
        organisation: z.string().nullable(),
        location: z.string().nullable(),
        period: z.string().nullable(),
        description: z.string().nullable(),
      }),
    ),
  }),
  certifications: sectionHeader.extend({
    items: z.array(
      z.object({
        name: z.string(),
        issuer: z.string().nullable(),
        issued: z.string().nullable(),
        url: z.string().nullable(),
        media: mediaSchema.nullable(),
      }),
    ),
  }),
  articles: sectionHeader.extend({ items: z.array(articleCardSchema), allUrl: z.string().nullable() }),
  contact_form: sectionHeader.extend({ buttonLabel: z.string() }),
  raw_html: z.object({ title: z.string(), src: z.string().startsWith("/api/v1/embeds/").nullable(), height: z.number().int().nullable() }),
  downloads: sectionHeader.extend({
    files: z.array(z.object({ name: z.string(), description: z.string().nullable(), url: z.string(), size: z.number() })),
  }),
  diagram: z.object({ source: z.string(), description: z.string(), caption: z.string().nullable(), showSource: z.boolean() }),
  newsletter_signup: sectionHeader.extend({ buttonLabel: z.string(), enabled: z.boolean(), consentText: z.string() }),
  testimonials: sectionHeader.extend({
    items: z.array(z.object({ quote: z.string(), author: z.string(), title: z.string().nullable(), avatar: mediaSchema.nullable() })),
  }),
} as const;

export type ProjectCard = z.infer<typeof projectCard>;

export type ProjectDetail = ProjectCard & {
  client: string | null;
  bodyHtml: string;
  links: { label: string; url: string }[];
  publishedAt: string | null;
  updatedAt: string | null;
};

export type BlockType = keyof typeof blockSchemas;
export type BlockProps<T extends BlockType> = z.infer<(typeof blockSchemas)[T]>;
export type Media = z.infer<typeof mediaSchema>;

export type CmsBlock = { id: string; type: string; version: number; anchor: string | null; props: unknown };

export type CmsPage = {
  id: string;
  title: string;
  slug: string;
  path: string;
  isHomepage: boolean;
  template: "default" | "landing";
  draft: boolean;
  publishedAt: string | null;
  updatedAt: string | null;
  seo: {
    title: string | null;
    description: string | null;
    canonicalUrl: string | null;
    noindex: boolean;
    image: Media | null;
  };
  blocks: CmsBlock[];
};
