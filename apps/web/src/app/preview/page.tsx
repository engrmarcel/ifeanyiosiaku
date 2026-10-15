import type { Metadata } from "next";
import { Suspense } from "react";
import { CmsPageView } from "@/components/cms/CmsPageView";
import { Alert } from "@/components/ui/Alert";
import { getPreviewPage } from "@/lib/cms/pages";

export const metadata: Metadata = { title: "Preview", robots: { index: false, follow: false } };

async function Preview({ searchParams }: PageProps<"/preview">) {
  const { token } = await searchParams;
  const page = typeof token === "string" && token ? await getPreviewPage(token) : null;

  if (!page) {
    return (
      <div className="mx-auto w-full max-w-xl px-4 py-20">
        <Alert tone="error" title="This preview link has expired">
          Preview links work for 15 minutes. Open the page in the admin panel and press Preview again.
        </Alert>
      </div>
    );
  }

  return (
    <>
      <div role="status" className="sticky top-16 z-20 border-b border-amber/40 bg-warning-surface/95 backdrop-blur">
        <p className="mx-auto max-w-6xl px-4 py-2 text-center text-sm text-text sm:px-6">
          <span className="font-mono text-amber">PREVIEW</span> · This is the saved draft of “{page.title}”. Visitors cannot see it until it is published.
        </p>
      </div>
      <CmsPageView page={page} />
    </>
  );
}

/** Shows an unpublished draft through a signed, short-lived link. Never cached or indexed. */
export default function PreviewPage(props: PageProps<"/preview">) {
  return (
    <Suspense>
      <Preview {...props} />
    </Suspense>
  );
}
