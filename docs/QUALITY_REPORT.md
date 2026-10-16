# Quality report — Milestone 2 (measured)

Measured on 2026-10-16. These are real runs, not estimates. Re-run before launch on the production server
(Milestone 8), where network, CPU and caching differ.

## How it was measured

- **Tool:** Lighthouse 13.5.0 (includes axe-core accessibility checks), headless Chrome.
- **Target:** local **production build** (`next build` + `next start`) with the Laravel API (`php artisan serve`)
  and the local database (homepage with portrait, contact page; articles and publications empty).
- **Profiles:** Lighthouse "mobile" (simulated mid-range phone, slow 4G, 4× CPU slowdown) and "desktop".
- Scores vary by a few points between runs.

## Results

| Page | Phone performance | Desktop performance | Accessibility | Best practices | SEO |
|---|---|---|---|---|---|
| Homepage `/` | 86 (LCP 3.8 s, CLS 0.001, TBT 170 ms) | 100 (LCP 0.7 s) | 100 | 100 | 100 |
| Articles `/articles` | 96 | 100 | 100 | 100 | 100 |
| Contact `/contact` | 97 | 100 | 100 | 100 | 100 |
| Publications `/publications` | 94 | 100 | 100 | 100 | 100 |
| Sign-in `/login` | 94 | 100 | 100 | 100 | 63 \* |
| Test page with custom code + diagram (above the fold) | 65 (TBT 1.2 s, CLS 0.10) | 97 | 100 | 100 | 100 |

\* The sign-in page is deliberately hidden from search engines (`noindex` and robots.txt), which Lighthouse
scores as an SEO failure. This is intended.

**Accessibility 100 on every page.** Real-browser checks were also made for the announcement dialog (focus moves
into it, Escape closes it), keyboard menus and the phone layout. Every page was checked at 390 px width with no
horizontal scrolling.

## What was fixed because of these measurements

- **Console error on every page for visitors:** the website asked `/api/v1/me`, which answers 401 for visitors,
  and browsers log that as an error. It now asks `/api/v1/session` (answers "nobody signed in" normally).
  Best practices went from 92 to 100.
- **Content-Security-Policy report on every page:** the Zod validation library probes `eval()` at start-up.
  Turned off with `z.config({ jitless: true })`; zero policy violations on all pages.
- **Phone homepage 76 → 86:** the code font (Geist Mono) was preloaded and competed with the portrait on slow
  connections. It now loads after the main content (`lib/fonts.ts`). The header no longer pulls the validation
  library into the browser download.
- **Diagrams:** the diagram library now loads only when a diagram is about to be seen, and its space is reserved.

## Known limits (honest)

- **Diagrams are heavy on phones.** Drawing a diagram takes about 1 second of phone CPU (simulated), which hurts the
  score when a diagram is at the top of a page (65). Below the fold it costs nothing until scrolled to. For key
  landing pages, prefer an image of the diagram.
- **Custom code set to "fit content" can shift the page slightly** when it reports its height (CLS ≈ 0.06–0.10 when
  at the top). Choose a fixed height for custom code near the top of a page.
- **Phone LCP on the homepage is 3.8 s on simulated slow 4G** (the portrait). The image is already the right size
  (58 KB WebP), preloaded and high priority. A CDN and HTTP/2 on the production server should help; re-measure there.
