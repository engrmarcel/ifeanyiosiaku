import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { CmsBlock } from "@/lib/cms/schemas";
import { BlockRenderer } from "./BlockRenderer";

vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

const header = { eyebrow: null, intro: null };
const block = (type: string, props: unknown): CmsBlock => ({ id: `b-${type}`, type, version: 1, anchor: null, props });

describe("portfolio blocks", () => {
  it("render nothing when there is no content yet", () => {
    const { container } = render(
      <BlockRenderer
        blocks={[
          block("projects", { ...header, heading: "Selected work", items: [] }),
          block("services", { ...header, heading: "Services", items: [] }),
          block("experience", { ...header, heading: "Experience", items: [] }),
          block("certifications", { ...header, heading: "Certifications", items: [] }),
          block("testimonials", { ...header, heading: "Kind words", items: [] }),
        ]}
      />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("links project cards to their case study", () => {
    render(
      <BlockRenderer
        blocks={[
          block("projects", {
            ...header,
            heading: "Selected work",
            items: [
              { slug: "sales-dashboard", url: "/projects/sales-dashboard", title: "Sales dashboard", summary: "Revenue at a glance.", role: "Lead", period: "2024", technologies: ["Power BI"], cover: null },
            ],
          }),
        ]}
      />,
    );

    expect(screen.getByRole("link", { name: "Sales dashboard" })).toHaveAttribute("href", "/projects/sales-dashboard");
    expect(screen.getByText("Lead · 2024")).toBeInTheDocument();
  });

  it("shows years of experience only when provided", () => {
    const profile = { ...header, heading: "About me", name: "Ifeanyi Osiaku", headline: null, shortBio: "Bio.", bioHtml: "", portrait: null };

    const { unmount } = render(<BlockRenderer blocks={[block("profile_intro", { ...profile, yearsExperience: 12 })]} />);
    expect(screen.getByText("12+")).toBeInTheDocument();
    unmount();

    render(<BlockRenderer blocks={[block("profile_intro", { ...profile, yearsExperience: null })]} />);
    expect(screen.queryByText("Years of experience")).not.toBeInTheDocument();
  });
});
