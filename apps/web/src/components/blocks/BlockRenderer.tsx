import type { ComponentType } from "react";
import { blockSchemas, type BlockType, type CmsBlock } from "@/lib/cms/schemas";
import * as A from "./advanced";
import * as B from "./blocks";
import * as C from "./content";
import * as E from "./engagement";
import * as P from "./portfolio";

type Renderer = ComponentType<{ props: never; anchor: string | null; first: boolean }>;

const renderers: Record<BlockType, Renderer> = {
  hero: B.HeroBlock as Renderer,
  heading: B.HeadingBlock as Renderer,
  rich_text: B.RichTextBlock as Renderer,
  image: B.ImageBlock as Renderer,
  gallery: B.GalleryBlock as Renderer,
  cards: B.CardsBlock as Renderer,
  two_column: B.TwoColumnBlock as Renderer,
  stats: B.StatsBlock as Renderer,
  timeline: B.TimelineBlock as Renderer,
  accordion: B.AccordionBlock as Renderer,
  tabs: B.TabsBlock as Renderer,
  code: B.CodeBlockView as Renderer,
  terminal: B.TerminalBlock as Renderer,
  callout: B.CalloutBlock as Renderer,
  quote: B.QuoteBlock as Renderer,
  video: B.VideoBlock as Renderer,
  table: B.TableBlock as Renderer,
  cta: B.CtaBlock as Renderer,
  divider: B.DividerBlock as Renderer,
  profile_intro: P.ProfileIntroBlock as Renderer,
  expertise: P.ExpertiseBlock as Renderer,
  projects: P.ProjectsBlock as Renderer,
  services: P.ServicesBlock as Renderer,
  experience: P.ExperienceBlock as Renderer,
  certifications: P.CertificationsBlock as Renderer,
  testimonials: P.TestimonialsBlock as Renderer,
  articles: C.ArticlesBlock as Renderer,
  contact_form: E.ContactFormBlock as Renderer,
  newsletter_signup: E.NewsletterSignupBlock as Renderer,
  raw_html: A.RawHtmlBlock as Renderer,
  downloads: A.DownloadsBlock as Renderer,
  diagram: A.DiagramBlock as Renderer,
};

export function isKnownBlock(type: string): type is BlockType {
  return Object.hasOwn(renderers, type);
}

/**
 * Renders CMS blocks in order. Each block's props are checked against its schema; unknown or
 * malformed blocks are skipped (and logged) instead of breaking the page.
 */
export function BlockRenderer({ blocks }: { blocks: CmsBlock[] }) {
  return blocks.map((block, index) => {
    if (!isKnownBlock(block.type)) {
      console.warn(`[cms] Unknown block type "${block.type}" skipped`);
      return null;
    }

    const parsed = blockSchemas[block.type].safeParse(block.props);
    if (!parsed.success) {
      console.warn(`[cms] Invalid "${block.type}" block skipped`, parsed.error.issues.slice(0, 3));
      return null;
    }

    const Component = renderers[block.type];
    return <Component key={block.id} props={parsed.data as never} anchor={block.anchor} first={index === 0} />;
  });
}
