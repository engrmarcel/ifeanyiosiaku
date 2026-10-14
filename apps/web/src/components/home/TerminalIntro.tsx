"use client";

import { motion, useReducedMotion } from "motion/react";

/**
 * Animated terminal panel. Lines reveal in sequence; with reduced motion everything is shown at once.
 * The text is real DOM content, so it stays readable and searchable without animation.
 */
export function TerminalIntro({ stack }: { stack: string[] }) {
  const reduceMotion = useReducedMotion();

  const lines: { prompt?: boolean; text: string; tone?: "accent" | "muted" | "violet" }[] = [
    { prompt: true, text: "whoami" },
    { text: "ifeanyi-osiaku — software · ai · business intelligence", tone: "muted" },
    { prompt: true, text: "cat stack.txt" },
    { text: stack.join("  ·  "), tone: "accent" },
    { prompt: true, text: "echo $MISSION" },
    { text: "complex data → practical solutions", tone: "violet" },
  ];

  return (
    <div className="relative rounded-lg border border-border-strong bg-surface shadow-[0_40px_120px_-50px_rgb(163_247_191/0.35)]">
      <div className="flex items-center gap-2 border-b border-border px-4 py-3" aria-hidden="true">
        <span className="size-2.5 rounded-full bg-[#ff5f57]/80" />
        <span className="size-2.5 rounded-full bg-[#febc2e]/80" />
        <span className="size-2.5 rounded-full bg-[#28c840]/80" />
        <span className="ml-3 font-mono text-xs text-muted">~/ifeanyi</span>
      </div>
      <div className="space-y-2 p-5 font-mono text-sm leading-relaxed sm:p-6" role="presentation">
        {lines.map((line, index) => (
          <motion.p
            key={index}
            initial={reduceMotion ? false : { opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: reduceMotion ? 0 : 0.25 + index * 0.35, duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
            className={
              line.tone === "accent" ? "text-accent" : line.tone === "violet" ? "text-violet-text" : line.tone === "muted" ? "text-muted" : "text-text"
            }
          >
            {line.prompt && (
              <span className="mr-2 text-accent" aria-hidden="true">
                $
              </span>
            )}
            {line.text}
          </motion.p>
        ))}
        <p aria-hidden="true">
          <span className="mr-2 text-accent">$</span>
          <span className="io-caret inline-block h-4 w-2 translate-y-0.5 bg-accent" />
        </p>
      </div>
    </div>
  );
}
