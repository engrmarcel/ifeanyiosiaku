import { act, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { BlockRenderer } from "./BlockRenderer";

const renderDiagram = vi.fn();
vi.mock("mermaid", () => ({ default: { initialize: vi.fn(), render: (...args: unknown[]) => renderDiagram(...args) } }));

const block = (type: string, props: unknown) => ({ id: `b-${type}`, type, version: 1, anchor: null, props });

describe("Custom code block", () => {
  it("renders only as a sandboxed frame without same-origin access", () => {
    render(<BlockRenderer blocks={[block("raw_html", { title: "Sales chart", src: "/api/v1/embeds/abc.def", height: null })]} />);
    const frame = screen.getByTitle("Sales chart");

    expect(frame.tagName).toBe("IFRAME");
    expect(frame).toHaveAttribute("src", "/api/v1/embeds/abc.def");
    expect(frame.getAttribute("sandbox")).toBe("allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms");
    expect(frame.getAttribute("sandbox")).not.toContain("allow-same-origin");
    expect(frame).toHaveAttribute("referrerpolicy", "no-referrer");
  });

  it("ignores height messages that do not come from its own frame, and clamps the rest", () => {
    render(<BlockRenderer blocks={[block("raw_html", { title: "Widget", src: "/api/v1/embeds/abc.def", height: null })]} />);
    const frame = screen.getByTitle("Widget") as HTMLIFrameElement;

    act(() => window.dispatchEvent(new MessageEvent("message", { data: { type: "io-embed-height", height: 900 }, source: window })));
    expect(frame.style.height).toBe("300px");

    act(() => window.dispatchEvent(new MessageEvent("message", { data: { type: "io-embed-height", height: 99999 }, source: frame.contentWindow })));
    expect(frame.style.height).toBe("4000px");
  });

  it("does not render when the API gives no sandboxed address", () => {
    const { container } = render(<BlockRenderer blocks={[block("raw_html", { title: "X", src: null, height: 300 })]} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("rejects any address other than the sandboxed embed endpoint", () => {
    const { container } = render(<BlockRenderer blocks={[block("raw_html", { title: "X", src: "https://evil.example/x", height: 300 })]} />);
    expect(container).toBeEmptyDOMElement();
  });
});

describe("Downloads block", () => {
  it("lists files with their size as download links", () => {
    render(
      <BlockRenderer
        blocks={[block("downloads", { eyebrow: null, heading: "Resources", intro: null, files: [{ name: "DAX guide", description: "Cheat sheet", url: "/media/x/guide.pdf", size: 2_500_000 }] })]}
      />,
    );

    const link = screen.getByRole("link", { name: /download DAX guide/ });
    expect(link).toHaveAttribute("href", "/media/x/guide.pdf");
    expect(link).toHaveAttribute("download");
    expect(link).toHaveTextContent("PDF · 2.4 MB");
  });
});

describe("Diagram block", () => {
  const props = { source: "flowchart LR\n A-->B", description: "Data flows from A to B.", caption: "Pipeline", showSource: true };

  it("shows the description until drawn, then an accessible image", async () => {
    renderDiagram.mockResolvedValue({ svg: '<svg><text>A</text></svg>' });
    render(<BlockRenderer blocks={[block("diagram", props)]} />);

    expect(screen.getByText("Data flows from A to B.")).toBeInTheDocument();
    expect((await screen.findByRole("img", { name: "Data flows from A to B." })).querySelector("svg")).not.toBeNull();
    expect(screen.getByText("View diagram source")).toBeInTheDocument();
  });

  it("falls back to the description if the diagram cannot be drawn", async () => {
    renderDiagram.mockRejectedValue(new Error("Parse error"));
    render(<BlockRenderer blocks={[block("diagram", props)]} />);

    expect(await screen.findByText(/This diagram could not be drawn/)).toBeInTheDocument();
  });
});
