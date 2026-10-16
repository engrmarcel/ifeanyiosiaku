import localFont from "next/font/local";

/**
 * Geist Mono, as shipped by the geist package, but not preloaded: it is used for small labels and
 * code, never for the main content, so it should not compete with the hero image on slow phones.
 * Text shows in the system monospace font until it arrives.
 */
export const GeistMonoLazy = localFont({
  src: "../../node_modules/geist/dist/fonts/geist-mono/GeistMono-Variable.woff2",
  variable: "--font-geist-mono",
  preload: false,
  display: "swap",
  adjustFontFallback: false,
  fallback: ["ui-monospace", "SFMono-Regular", "Menlo", "Monaco", "Consolas", "monospace"],
  weight: "100 900",
});
