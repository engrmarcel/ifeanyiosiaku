import type { Metadata } from "next";
import { Suspense } from "react";
import { LoginForm } from "./LoginForm";

export const metadata: Metadata = { title: "Sign in", robots: { index: false } };

async function LoginWithParams({ searchParams }: PageProps<"/login">) {
  const params = await searchParams;
  const value = (key: string) => (typeof params[key] === "string" ? (params[key] as string) : null);

  return <LoginForm next={value("next")} reason={value("reason")} reset={value("reset") === "1"} />;
}

// The query string is read on the server, so the form is part of the streamed HTML response.
export default function LoginPage(props: PageProps<"/login">) {
  return (
    <Suspense>
      <LoginWithParams {...props} />
    </Suspense>
  );
}
