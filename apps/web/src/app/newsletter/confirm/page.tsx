import type { Metadata } from "next";
import { Suspense } from "react";
import { NewsletterLinkAction } from "@/components/engagement/NewsletterLinkAction";

export const metadata: Metadata = { title: "Confirm subscription", robots: { index: false, follow: false } };

export default function ConfirmNewsletterPage() {
  return (
    <div className="mx-auto w-full max-w-xl px-4 py-20 sm:px-6">
      <p className="font-mono text-sm text-accent">{"// newsletter"}</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">Confirm your subscription</h1>
      <p className="mt-3 mb-8 text-muted">One last step: press the button to start receiving occasional updates. You can unsubscribe at any time.</p>
      <Suspense>
        <NewsletterLinkAction mode="confirm" />
      </Suspense>
    </div>
  );
}
