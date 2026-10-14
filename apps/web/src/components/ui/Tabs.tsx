"use client";

import * as RadixTabs from "@radix-ui/react-tabs";
import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export type TabItem = { value: string; label: string; content: ReactNode };

/** Keyboard-accessible tabs (arrow keys move between tabs). */
export function Tabs({ items, defaultValue, label, className }: { items: TabItem[]; defaultValue?: string; label: string; className?: string }) {
  return (
    <RadixTabs.Root defaultValue={defaultValue ?? items[0]?.value} className={className}>
      <RadixTabs.List aria-label={label} className="flex gap-1 border-b border-border">
        {items.map((item) => (
          <RadixTabs.Trigger
            key={item.value}
            value={item.value}
            className={cn(
              "-mb-px border-b-2 border-transparent px-4 py-2 text-sm text-muted transition-colors",
              "hover:text-text data-[state=active]:border-accent data-[state=active]:text-text",
            )}
          >
            {item.label}
          </RadixTabs.Trigger>
        ))}
      </RadixTabs.List>
      {items.map((item) => (
        <RadixTabs.Content key={item.value} value={item.value} className="pt-4 focus-visible:outline-none">
          {item.content}
        </RadixTabs.Content>
      ))}
    </RadixTabs.Root>
  );
}
