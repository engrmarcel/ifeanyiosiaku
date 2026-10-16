import { act, fireEvent, render, screen } from "@testing-library/react";
import { axe } from "vitest-axe";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ShareBar } from "./ShareBar";
import { shareLinks } from "./shareLinks";

describe("shareLinks", () => {
  it("encodes the address and title for every network", () => {
    const links = Object.fromEntries(shareLinks("https://ifeanyiosiaku.com/articles/a&b", "DAX & Power BI").map((l) => [l.network, l.href]));

    expect(links.linkedin).toBe("https://www.linkedin.com/sharing/share-offsite/?url=https%3A%2F%2Fifeanyiosiaku.com%2Farticles%2Fa%26b");
    expect(links.x).toBe("https://x.com/intent/post?url=https%3A%2F%2Fifeanyiosiaku.com%2Farticles%2Fa%26b&text=DAX%20%26%20Power%20BI");
    expect(links.whatsapp).toBe("https://wa.me/?text=DAX%20%26%20Power%20BI%20https%3A%2F%2Fifeanyiosiaku.com%2Farticles%2Fa%26b");
    expect(links.email).toMatch(/^mailto:\?subject=DAX%20%26%20Power%20BI&body=/);
    expect(Object.keys(links)).toEqual(["linkedin", "x", "facebook", "whatsapp", "telegram", "email"]);
  });
});

describe("ShareBar", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("links to each network in a new tab, without loading their scripts", async () => {
    const { container } = render(<ShareBar path="/articles/power-bi-measures" title="Power BI measures" />);

    const linkedin = screen.getByRole("link", { name: "Share on LinkedIn (opens in a new tab)" });
    expect(linkedin).toHaveAttribute("target", "_blank");
    expect(linkedin).toHaveAttribute("rel", "noopener noreferrer");
    expect(linkedin.getAttribute("href")).toContain(encodeURIComponent("/articles/power-bi-measures"));
    expect(screen.getByRole("link", { name: "Share by email" })).not.toHaveAttribute("target");
    expect(container.querySelector("script, iframe")).toBeNull();
    expect(await axe(container)).toHaveNoViolations();
  });

  it("copies the page address", async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    vi.stubGlobal("navigator", { ...navigator, clipboard: { writeText } });
    render(<ShareBar path="/publications/study" title="Study" />);

    await act(async () => fireEvent.click(screen.getByRole("button", { name: "Copy link" })));

    expect(writeText).toHaveBeenCalledWith(expect.stringMatching(/\/publications\/study$/));
    expect(screen.getByText("Link copied")).toBeInTheDocument();
  });

  it("offers the device's share sheet only where available", () => {
    const { unmount } = render(<ShareBar path="/a" title="A" />);
    expect(screen.queryByRole("button", { name: "More sharing options" })).not.toBeInTheDocument();
    unmount();

    vi.stubGlobal("navigator", { ...navigator, share: vi.fn().mockResolvedValue(undefined) });
    render(<ShareBar path="/a" title="A" />);
    expect(screen.getByRole("button", { name: "More sharing options" })).toBeInTheDocument();
  });
});
