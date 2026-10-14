import type { Metadata } from "next";
import type { ReactNode } from "react";
import { RequireAuth } from "@/components/auth/RequireAuth";

export const metadata: Metadata = { title: "Account", robots: { index: false, follow: false } };

export default function AccountLayout({ children }: { children: ReactNode }) {
  return (
    <div className="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
      <RequireAuth>{children}</RequireAuth>
    </div>
  );
}
