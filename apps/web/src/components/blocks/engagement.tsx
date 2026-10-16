import { ContactForm } from "@/components/engagement/ContactForm";
import { NewsletterForm } from "@/components/engagement/NewsletterForm";
import type { BlockProps } from "@/lib/cms/schemas";
import { Section } from "./Section";

type Base = { anchor: string | null; first: boolean };

function Heading({ eyebrow, heading, intro }: { eyebrow: string | null; heading: string; intro: string | null }) {
  return (
    <header className="mb-8">
      {eyebrow && <p className="font-mono text-sm text-accent">{eyebrow}</p>}
      <h2 className="mt-2 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{heading}</h2>
      {intro && <p className="mt-3 text-muted">{intro}</p>}
    </header>
  );
}

export function ContactFormBlock({ props, anchor }: Base & { props: BlockProps<"contact_form"> }) {
  return (
    <Section id={anchor} width="narrow">
      <Heading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
      <div className="rounded-lg border border-border bg-surface p-6 sm:p-8">
        <ContactForm buttonLabel={props.buttonLabel} />
      </div>
    </Section>
  );
}

/** Hidden entirely while newsletter sign-up is switched off in Site settings. */
export function NewsletterSignupBlock({ props, anchor }: Base & { props: BlockProps<"newsletter_signup"> }) {
  if (!props.enabled) return null;

  return (
    <Section id={anchor} width="narrow">
      <div className="rounded-lg border border-border-strong bg-surface p-6 sm:p-8">
        <Heading eyebrow={props.eyebrow} heading={props.heading} intro={props.intro} />
        <NewsletterForm buttonLabel={props.buttonLabel} consentText={props.consentText} source={`block${anchor ? `:${anchor}` : ""}`} />
      </div>
    </Section>
  );
}
