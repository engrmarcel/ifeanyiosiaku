import { notFound } from "next/navigation";

/**
 * Target of the proxy's rewrite for unknown addresses. Calling notFound() before anything is
 * awaited makes the response a real HTTP 404 with the site's not-found page.
 */
export default function PageNotFound() {
  notFound();
}
