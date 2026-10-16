import { render, screen } from "@testing-library/react";
import { axe } from "vitest-axe";
import { describe, expect, it, vi } from "vitest";
import { BlockRenderer } from "@/components/blocks/BlockRenderer";
import type { ArticleCard as ArticleCardData } from "@/lib/content/schemas";
import { ArticleCard } from "./ArticleCard";
import { TableOfContents } from "./TableOfContents";

vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

const card: ArticleCardData = {
  slug: "power-bi-measures",
  url: "/articles/power-bi-measures",
  title: "Writing Power BI measures",
  excerpt: "DAX patterns that stay fast.",
  cover: null,
  publishedAt: "2026-10-14T09:00:00+00:00",
  readingMinutes: 4,
  categories: [{ slug: "bi", name: "Business intelligence" }],
};

describe("ArticleCard", () => {
  it("links to the article and shows its date and reading time", async () => {
    const { container } = render(<ArticleCard article={card} />);

    expect(screen.getByRole("link", { name: "Writing Power BI measures" })).toHaveAttribute("href", "/articles/power-bi-measures");
    expect(screen.getByText("Business intelligence")).toBeInTheDocument();
    expect(screen.getByText(/14 Oct 2026/)).toBeInTheDocument();
    expect(screen.getByText(/4 min read/)).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });
});

describe("TableOfContents", () => {
  it("lists sections as in-page links and is hidden for very short articles", () => {
    const { rerender } = render(
      <TableOfContents
        items={[
          { id: "setup", text: "Setup", level: 2 },
          { id: "install", text: "Install", level: 3 },
        ]}
      />,
    );

    expect(screen.getByRole("navigation", { name: "On this page" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Install" })).toHaveAttribute("href", "#install");

    rerender(<TableOfContents items={[{ id: "only", text: "Only", level: 2 }]} />);
    expect(screen.queryByRole("navigation", { name: "On this page" })).not.toBeInTheDocument();
  });
});

describe("Latest articles section", () => {
  const block = (items: ArticleCardData[]) => ({
    id: "b1",
    type: "articles",
    version: 1,
    anchor: null,
    props: { eyebrow: null, heading: "Latest articles", intro: null, items, allUrl: "/articles" },
  });

  it("renders nothing until an article is published", () => {
    const { container } = render(<BlockRenderer blocks={[block([])]} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("shows article cards and a link to all articles", () => {
    render(<BlockRenderer blocks={[block([card])]} />);

    expect(screen.getByRole("heading", { name: "Latest articles" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /All articles/ })).toHaveAttribute("href", "/articles");
  });
});
