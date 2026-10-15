import type { Metadata, Viewport } from "next";
import type { CSSProperties } from "react";
import { GeistMono } from "geist/font/mono";
import { GeistSans } from "geist/font/sans";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { ContentProtection } from "@/components/protection/ContentProtection";
import { ToastProvider } from "@/components/ui/Toast";
import { AuthProvider } from "@/lib/auth/AuthProvider";
import { SiteSettingsProvider } from "@/lib/settings/SiteSettingsProvider";
import { getSiteConfig } from "@/lib/site/config";
import { SITE_URL } from "@/lib/site/url";
import "./globals.css";

const baseMetadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: "Ifeanyi Osiaku — Software, AI & Data",
    template: "%s · Ifeanyi Osiaku",
  },
  description:
    "Software Developer, AI Enthusiast, and Business Intelligence & Data Analytics Professional turning technical knowledge and complex data into practical solutions.",
  applicationName: "Ifeanyi Osiaku",
  openGraph: {
    type: "website",
    siteName: "Ifeanyi Osiaku",
    images: [{ url: "/brand/ifeanyi-osiaku-portrait.jpg", width: 1054, height: 1492, alt: "Portrait of Ifeanyi Osiaku" }],
  },
};

export const viewport: Viewport = {
  themeColor: "#080A0D",
  colorScheme: "dark",
};

/** Search engines are asked to skip every page while indexing is switched off in Site settings. */
export async function generateMetadata(): Promise<Metadata> {
  const site = await getSiteConfig();
  return site.indexing ? baseMetadata : { ...baseMetadata, robots: { index: false, follow: false } };
}

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const site = await getSiteConfig();
  const { tokens } = site.theme;
  // The administrator's accent colour, applied through the design tokens in globals.css.
  const theme = {
    "--io-accent": tokens.accent,
    "--io-accent-strong": tokens.accentStrong,
    "--io-accent-ink": tokens.accentInk,
  } as CSSProperties;

  return (
    <html lang="en" style={theme} className={`${GeistSans.variable} ${GeistMono.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <a
          href="#main"
          className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-ink"
        >
          Skip to content
        </a>
        <SiteSettingsProvider>
          <AuthProvider>
            <ToastProvider>
              <ContentProtection />
              <SiteHeader name={site.name} links={site.navigation.header} />
              <main id="main" className="flex flex-1 flex-col">
                {children}
              </main>
              <SiteFooter site={site} />
            </ToastProvider>
          </AuthProvider>
        </SiteSettingsProvider>
      </body>
    </html>
  );
}
