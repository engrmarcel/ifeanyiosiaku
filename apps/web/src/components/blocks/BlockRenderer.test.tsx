import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { CmsBlock } from "@/lib/cms/schemas";
import { BlockRenderer } from "./BlockRenderer";

vi.mock("next/link", () => ({ default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a> }));

const block = (type: string, props: unknown, anchor: string | null = null): CmsBlock => ({ id: `b-${type}`, type, version: 1, anchor, props });

describe("BlockRenderer", () => {
  it("renders known blocks in order with anchors", () => {
    render(
      <BlockRenderer
        blocks={[
          block("heading", { eyebrow: "01", text: "What I do", intro: null, level: 2, align: "left" }, "services"),
          block("quote", { text: "Data changes decisions.", attribution: "Ifeanyi", role: null }),
        ]}
      />,
    );

    expect(screen.getByRole("heading", { level: 2, name: "What I do" }).closest("section")).toHaveAttribute("id", "services");
    expect(screen.getByText("“Data changes decisions.”")).toBeInTheDocument();
  });

  it("skips unknown and malformed blocks instead of breaking the page", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});

    render(
      <BlockRenderer
        blocks={[
          block("mystery", { anything: true }),
          block("stats", { items: "not-a-list" }),
          block("heading", { eyebrow: null, text: "Still here", intro: null, level: 2, align: "left" }),
        ]}
      />,
    );

    expect(screen.getByRole("heading", { name: "Still here" })).toBeInTheDocument();
    expect(warn).toHaveBeenCalledTimes(2);
    warn.mockRestore();
  });

  it("uses an H1 only for a hero that opens the page", () => {
    const hero = { eyebrow: null, heading: "Ifeanyi Osiaku", subheading: null, body: null, primary: null, secondary: null, media: null, terminal: null };
    const { unmount } = render(<BlockRenderer blocks={[block("hero", hero)]} />);
    expect(screen.getByRole("heading", { level: 1, name: "Ifeanyi Osiaku" })).toBeInTheDocument();
    unmount();

    render(<BlockRenderer blocks={[block("divider", { style: "space", size: "sm" }), block("hero", hero)]} />);
    expect(screen.queryByRole("heading", { level: 1 })).not.toBeInTheDocument();
    expect(screen.getByRole("heading", { level: 2, name: "Ifeanyi Osiaku" })).toBeInTheDocument();
  });

  it("renders sanitised rich text and responsive images", () => {
    const media = {
      id: "m1",
      url: "/media/m1/photo.jpg",
      mime: "image/jpeg",
      width: 1600,
      height: 900,
      alt: "Workshop",
      title: null,
      variants: [{ name: "md", url: "/media/m1/photo-md.webp", width: 768, height: 432, mime: "image/webp" }],
    };

    render(
      <BlockRenderer
        blocks={[
          block("rich_text", { html: "<p>Hello <strong>world</strong></p>", width: "narrow" }),
          block("image", { media, caption: "Session", size: "normal" }),
        ]}
      />,
    );

    expect(screen.getByText("world").tagName).toBe("STRONG");
    const img = screen.getByRole("img", { name: "Workshop" });
    expect(img).toHaveAttribute("srcset", "/media/m1/photo-md.webp 768w, /media/m1/photo.jpg 1600w");
    expect(img).toHaveAttribute("width", "1600");
    expect(img).toHaveAttribute("loading", "lazy"); // not the first block, so it loads when scrolled near
  });
});
