import type { Metadata } from "next";
import { Suspense } from "react";
import { ArticleView } from "@/components/content/ArticleView";
import { Alert } from "@/components/ui/Alert";
import { getArticlePreview } from "@/lib/content/api";

export const metadata: Metadata = { title: "Preview", robots: { index: false, follow: false } };

async function Preview({ searchParams }: PageProps<"/preview/article">) {
  const { token } = await searchParams;
  const article = typeof token === "string" && token ? await getArticlePreview(token) : null;

  if (!article) {
    return (
      <div className="mx-auto w-full max-w-xl px-4 py-20">
        <Alert tone="error" title="This preview link has expired">
          Preview links work for 15 minutes. Open the article in the admin panel and press Preview again.
        </Alert>
      </div>
    );
  }

  return (
    <>
      <div role="status" className="sticky top-16 z-20 border-b border-amber/40 bg-warning-surface/95 backdrop-blur">
        <p className="mx-auto max-w-6xl px-4 py-2 text-center text-sm text-text sm:px-6">
          <span className="font-mono text-amber">PREVIEW</span> · This is the saved version of “{article.title}”. Visitors cannot see it until it is published.
        </p>
      </div>
      <ArticleView article={article} />
    </>
  );
}

/** Shows an unpublished article through a signed, short-lived link. Never cached or indexed. */
export default function ArticlePreviewPage(props: PageProps<"/preview/article">) {
  return (
    <Suspense>
      <Preview {...props} />
    </Suspense>
  );
}
