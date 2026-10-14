"use client";

import * as RadixAccordion from "@radix-ui/react-accordion";
import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export type AccordionItem = { value: string; title: string; content: ReactNode };

export function Accordion({ items, className }: { items: AccordionItem[]; className?: string }) {
  return (
    <RadixAccordion.Root type="multiple" className={cn("divide-y divide-border rounded-lg border border-border", className)}>
      {items.map((item) => (
        <RadixAccordion.Item key={item.value} value={item.value}>
          <RadixAccordion.Header>
            <RadixAccordion.Trigger className="group flex w-full items-center justify-between px-5 py-4 text-left text-sm font-medium text-text hover:text-accent">
              {item.title}
              <span aria-hidden="true" className="font-mono text-muted transition-transform group-data-[state=open]:rotate-90">
                ›
              </span>
            </RadixAccordion.Trigger>
          </RadixAccordion.Header>
          <RadixAccordion.Content className="px-5 pb-4 text-sm text-muted">{item.content}</RadixAccordion.Content>
        </RadixAccordion.Item>
      ))}
    </RadixAccordion.Root>
  );
}
