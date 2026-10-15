import type { Metadata, Viewport } from "next";
import { GeistMono } from "geist/font/mono";
import { GeistSans } from "geist/font/sans";
import { SiteFooter } from "@/components/layout/SiteFooter";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { ContentProtection } from "@/components/protection/ContentProtection";
import { AuthProvider } from "@/lib/auth/AuthProvider";
import { SiteSettingsProvider } from "@/lib/settings/SiteSettingsProvider";
import "./globals.css";

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3100";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
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

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className={`${GeistSans.variable} ${GeistMono.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <a
          href="#main"
          className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-ink"
        >
          Skip to content
        </a>
        <SiteSettingsProvider>
          <AuthProvider>
            <ContentProtection />
            <SiteHeader />
            <main id="main" className="flex flex-1 flex-col">
              {children}
            </main>
            <SiteFooter />
          </AuthProvider>
        </SiteSettingsProvider>
      </body>
    </html>
  );
}
