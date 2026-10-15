import Image from "next/image";
import Link from "next/link";
import portrait from "../../../public/brand/ifeanyi-osiaku-portrait.jpg";
import { Button } from "@/components/ui/Button";
import { TerminalIntro } from "@/components/home/TerminalIntro";

/**
 * Shown at "/" until a CMS homepage is published (and if the API cannot be reached). Uses the
 * approved draft profile copy (PRODUCT_REQUIREMENTS §3).
 */
export function InterimHome() {
  const expertise = ["Software Development", "Artificial Intelligence", "Business Intelligence", "Data Analytics", "Data Visualization"];
  const stack = ["Python", "Qlik", "Power BI", "Tableau"];

  return (
    <section className="relative isolate flex flex-1 items-center overflow-hidden">
      <div aria-hidden="true" className="io-grid-backdrop pointer-events-none absolute inset-0 -z-10" />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute top-[-20%] left-1/2 -z-10 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(124_92_252/0.22),transparent)] blur-2xl"
      />

      <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:items-center lg:py-24 xl:pb-40">
        <div>
          <p className="font-mono text-sm text-accent">{"// software · ai · data"}</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
            Ifeanyi Osiaku
          </h1>
          <p className="mt-4 text-lg text-muted sm:text-xl">
            Software Developer <span className="text-border-strong">|</span> AI Enthusiast{" "}
            <span className="text-border-strong">|</span> Business Intelligence &amp; Data Analytics Professional
          </p>
          <p className="mt-6 max-w-xl text-base leading-relaxed text-muted">
            Turning technical knowledge and complex data into practical solutions to real-world problems — through
            software, artificial intelligence, business intelligence and data visualization.
          </p>

          <ul className="mt-8 flex flex-wrap gap-2" aria-label="Areas of expertise">
            {expertise.map((item) => (
              <li key={item} className="rounded-full border border-border bg-surface px-3 py-1 text-xs text-muted">
                {item}
              </li>
            ))}
          </ul>

          <div className="mt-10 flex flex-wrap gap-3">
            <Button asChild size="lg">
              <Link href="/register">Join the community</Link>
            </Button>
            <Button asChild size="lg" variant="secondary">
              <Link href="/login">Sign in</Link>
            </Button>
          </div>
        </div>

        <div className="relative mx-auto w-full max-w-md lg:max-w-none">
          <figure className="relative overflow-hidden rounded-lg border border-border-strong bg-surface shadow-[0_40px_120px_-50px_rgb(124_92_252/0.55)]">
            <Image
              src={portrait}
              alt="Portrait of Ifeanyi Osiaku"
              priority
              placeholder="blur"
              sizes="(min-width: 1024px) 480px, (min-width: 640px) 448px, 100vw"
              className="aspect-[4/5] h-auto w-full object-cover object-top"
            />
            <div
              aria-hidden="true"
              className="pointer-events-none absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-bg/80 to-transparent"
            />
          </figure>
          <div className="relative -mt-16 px-4 sm:px-8 xl:absolute xl:-bottom-28 xl:-left-20 xl:mt-0 xl:w-[23rem] xl:px-0">
            <TerminalIntro stack={stack} />
          </div>
        </div>
      </div>
    </section>
  );
}
