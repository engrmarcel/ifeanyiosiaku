import { fireEvent, render, screen, within } from "@testing-library/react";
import { axe } from "vitest-axe";
import { describe, expect, it, vi } from "vitest";
import { FALLBACK_SITE_CONFIG, type SiteConfig } from "@/lib/site/shared";
import { SiteFooter } from "./SiteFooter";
import { SiteHeader } from "./SiteHeader";

vi.mock("next/navigation", () => ({ usePathname: () => "/services" }));
vi.mock("@/lib/auth/AuthProvider", () => ({ useAuth: () => ({ user: null, status: "guest" }) }));
vi.mock("@/lib/settings/SiteSettingsProvider", () => ({ useAddon: () => true }));
vi.mock("@/lib/commerce/CartProvider", () => ({ useCart: () => ({ cart: { count: 2 } }) }));

const links = [
  { label: "Work", url: "/#work", newTab: false },
  { label: "Services", url: "/services", newTab: false },
  { label: "Blog", url: "https://blog.example", newTab: true },
];

describe("SiteHeader", () => {
  it("shows the configured menu, marks the current page and opens external links safely", () => {
    render(<SiteHeader name="Ifeanyi Osiaku" links={links} />);

    const desktop = screen.getAllByRole("navigation", { name: "Main" })[0];
    expect(within(desktop).getByRole("link", { name: "Services" })).toHaveAttribute("aria-current", "page");

    const blog = within(desktop).getByRole("link", { name: /Blog/ });
    expect(blog).toHaveAttribute("href", "https://blog.example");
    expect(blog).toHaveAttribute("target", "_blank");
    expect(blog).toHaveAttribute("rel", "noopener noreferrer");
    expect(screen.getByRole("link", { name: "Cart, 2 items" })).toHaveAttribute("href", "/cart");
  });

  it("toggles the phone menu and closes it with Escape", () => {
    const { container } = render(<SiteHeader name="Ifeanyi Osiaku" links={links} />);
    const toggle = screen.getByRole("button", { name: "Open menu" });
    const panel = container.querySelector(`#${CSS.escape(toggle.getAttribute("aria-controls") ?? "")}`);

    expect(panel).toHaveAttribute("hidden");
    fireEvent.click(toggle);
    expect(toggle).toHaveAttribute("aria-expanded", "true");
    expect(panel).not.toHaveAttribute("hidden");

    fireEvent.keyDown(window, { key: "Escape" });
    expect(toggle).toHaveAttribute("aria-expanded", "false");
  });

  it("has no menu button when no links are configured", () => {
    render(<SiteHeader name="Ifeanyi Osiaku" links={[]} />);
    expect(screen.queryByRole("button", { name: "Open menu" })).not.toBeInTheDocument();
  });

  it("has no accessibility violations", async () => {
    const { container } = render(<SiteHeader name="Ifeanyi Osiaku" links={links} />);
    expect(await axe(container)).toHaveNoViolations();
  });
});

describe("SiteFooter", () => {
  const site: SiteConfig = {
    ...FALLBACK_SITE_CONFIG,
    year: 2026,
    navigation: { header: [], footer: [{ label: "Privacy", url: "/privacy", newTab: false }] },
    social: [
      { network: "github", label: "GitHub", url: "https://github.com/example" },
      { network: "email", label: "Email", url: "mailto:hello@example.com" },
    ],
  };

  it("renders footer links, social profiles and the copyright line", () => {
    render(<SiteFooter site={site} />);

    expect(screen.getByRole("link", { name: "Privacy" })).toHaveAttribute("href", "/privacy");
    expect(screen.getByRole("link", { name: /GitHub/ })).toHaveAttribute("rel", "me noopener noreferrer");
    expect(screen.getByRole("link", { name: "Email" })).not.toHaveAttribute("target");
    expect(screen.getByText(/© 2026/)).toBeInTheDocument();
  });

  it("leaves out the year and empty sections when they are not available", () => {
    render(<SiteFooter site={FALLBACK_SITE_CONFIG} />);

    expect(screen.queryByText(/©/)).not.toBeInTheDocument();
    expect(screen.queryByRole("navigation", { name: "Footer" })).not.toBeInTheDocument();
    expect(screen.queryByRole("list", { name: "Social profiles" })).not.toBeInTheDocument();
  });

  it("has no accessibility violations", async () => {
    const { container } = render(<SiteFooter site={site} />);
    expect(await axe(container)).toHaveNoViolations();
  });
});
