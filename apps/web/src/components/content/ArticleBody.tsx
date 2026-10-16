import { SafeHtml } from "@/components/blocks/SafeHtml";
import { HighlightedCode } from "@/components/code/HighlightedCode";
import type { ArticleSegment } from "@/lib/content/schemas";

/**
 * Article text: sanitised HTML from the API, with code blocks highlighted on the server.
 * Code is only displayed, never executed.
 */
export function ArticleBody({ segments }: { segments: ArticleSegment[] }) {
  return (
    <div className="space-y-6">
      {segments.map((segment, index) =>
        segment.type === "html" ? (
          <SafeHtml key={index} html={segment.html} className="io-prose--article" />
        ) : (
          <HighlightedCode key={index} code={segment.code} language={segment.language} title={segment.title} />
        ),
      )}
    </div>
  );
}
