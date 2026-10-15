import Link from "next/link";
import { Button } from "@/components/ui/Button";

export default function NotFound() {
  return (
    <div className="relative flex flex-1 items-center justify-center px-4 py-20">
      <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0" />
      <div className="relative max-w-md text-center">
        <p className="font-mono text-sm text-accent">$ curl -I this-page</p>
        <p className="mt-2 font-mono text-6xl font-semibold text-text">404</p>
        <h1 className="mt-4 text-2xl font-semibold tracking-tight">This page could not be found</h1>
        <p className="mt-2 text-muted">It may have moved, or the address may be mistyped.</p>
        <Button asChild className="mt-8">
          <Link href="/">Back to the homepage</Link>
        </Button>
      </div>
    </div>
  );
}
