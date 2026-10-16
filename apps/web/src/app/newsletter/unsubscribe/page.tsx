import type { Metadata } from "next";
import { Suspense } from "react";
import { NewsletterLinkAction } from "@/components/engagement/NewsletterLinkAction";

export const metadata: Metadata = { title: "Unsubscribe", robots: { index: false, follow: false } };

export default function UnsubscribeNewsletterPage() {
  return (
    <div className="mx-auto w-full max-w-xl px-4 py-20 sm:px-6">
      <p className="font-mono text-sm text-accent">{"// newsletter"}</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">Unsubscribe</h1>
      <p className="mt-3 mb-8 text-muted">Press the button to stop receiving the newsletter. This takes effect immediately.</p>
      <Suspense>
        <NewsletterLinkAction mode="unsubscribe" />
      </Suspense>
    </div>
  );
}
