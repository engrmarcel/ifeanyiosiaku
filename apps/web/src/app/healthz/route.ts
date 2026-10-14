/** Liveness probe for the website process (the API has its own at /api/v1/health). */
export function GET() {
  return Response.json({ status: "ok" }, { headers: { "Cache-Control": "no-store" } });
}
