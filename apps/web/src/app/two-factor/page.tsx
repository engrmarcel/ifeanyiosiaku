import type { Metadata } from "next";
import { Suspense } from "react";
import { TwoFactorForm } from "./TwoFactorForm";

export const metadata: Metadata = { title: "Two-factor verification", robots: { index: false } };

async function TwoFactorWithParams({ searchParams }: PageProps<"/two-factor">) {
  const { next } = await searchParams;

  return <TwoFactorForm next={typeof next === "string" ? next : null} />;
}

export default function TwoFactorPage(props: PageProps<"/two-factor">) {
  return (
    <Suspense>
      <TwoFactorWithParams {...props} />
    </Suspense>
  );
}
