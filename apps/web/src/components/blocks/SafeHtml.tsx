import { cn } from "@/lib/cn";

/**
 * The only place the website inserts HTML. It must only ever receive rich text from the CMS API,
 * which sanitises it with an allow-list when it is saved and again when it is served
 * (SECURITY_THREAT_MODEL T2). Never pass user-supplied or third-party HTML here.
 */
export function SafeHtml({ html, className }: { html: string; className?: string }) {
  if (!html) return null;

  return <div className={cn("io-prose", className)} dangerouslySetInnerHTML={{ __html: html }} />;
}
