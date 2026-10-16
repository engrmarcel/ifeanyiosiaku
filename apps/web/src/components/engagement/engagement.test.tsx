import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { axe } from "vitest-axe";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { BlockRenderer } from "@/components/blocks/BlockRenderer";

const api = vi.fn();
const settings = { settings: { "announcements.enabled": true } as Record<string, unknown>, loaded: true };
let pathname = "/";
let search = new URLSearchParams();

vi.mock("@/lib/api/client", async (original) => ({ ...(await original<typeof import("@/lib/api/client")>()), api: (...args: unknown[]) => api(...args) }));
vi.mock("@/lib/settings/SiteSettingsProvider", () => ({ useSiteSettings: () => settings }));
vi.mock("next/navigation", () => ({ usePathname: () => pathname, useSearchParams: () => search }));
vi.mock("@/lib/captcha/useCaptcha", () => ({
  useCaptcha: () => ({
    status: "ready",
    config: { driver: "first_party" },
    widgetRef: { current: null },
    honeypotField: "website",
    tokenField: "captcha_token",
    getToken: async () => "token-123",
  }),
}));
vi.mock("next/link", () => ({
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

const { AnnouncementPopup } = await import("./AnnouncementPopup");
const { ContactForm } = await import("./ContactForm");
const { NewsletterLinkAction } = await import("./NewsletterLinkAction");

const announcement = {
  id: "01jabcdefghjkmnpqrstvwxyz0",
  title: "New course launching",
  message: "Power BI from zero.",
  image: null,
  cta: { label: "Learn more", url: "/courses" },
  frequency: "once",
  version: "v1",
};

beforeEach(() => {
  api.mockReset();
  window.localStorage.clear();
  window.sessionStorage.clear();
  pathname = "/";
  search = new URLSearchParams();
  settings.settings["announcements.enabled"] = true;
});
afterEach(() => vi.clearAllMocks());

describe("AnnouncementPopup", () => {
  it("shows the announcement in an accessible dialog and records what happened", async () => {
    api.mockImplementation(async (path: string) => (path.startsWith("/api/v1/announcements/current") ? { data: announcement } : undefined));
    render(<AnnouncementPopup />);

    const dialog = await screen.findByRole("dialog", { name: "New course launching" });
    expect(dialog).toHaveTextContent("Power BI from zero.");
    expect(api).toHaveBeenCalledWith(`/api/v1/announcements/${announcement.id}/events`, expect.objectContaining({ body: expect.objectContaining({ type: "impression" }) }));
    expect(await axe(document.body)).toHaveNoViolations();

    fireEvent.click(screen.getByRole("button", { name: "Not now" }));
    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
    expect(api).toHaveBeenCalledWith(expect.stringContaining("/events"), expect.objectContaining({ body: expect.objectContaining({ type: "dismiss" }) }));
    expect(window.localStorage.getItem(`io-ann:${announcement.id}:v1`)).toBe("closed");
  });

  it("does not even ask the server while the global switch is off", async () => {
    settings.settings["announcements.enabled"] = false;
    render(<AnnouncementPopup />);
    await act(async () => undefined);
    expect(api).not.toHaveBeenCalled();
  });

  it("stays quiet on account and sign-in pages", async () => {
    pathname = "/login";
    render(<AnnouncementPopup />);
    await act(async () => undefined);
    expect(api).not.toHaveBeenCalled();
  });

  it("does not show again after being closed", async () => {
    window.localStorage.setItem(`io-ann:${announcement.id}:v1`, "closed");
    api.mockResolvedValue({ data: announcement });
    render(<AnnouncementPopup />);
    await act(async () => undefined);
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});

describe("ContactForm", () => {
  it("sends the message with the CAPTCHA token and shows a confirmation", async () => {
    api.mockResolvedValue({ message: "Thank you — your message has been sent." });
    pathname = "/contact";
    render(<ContactForm buttonLabel="Send message" />);

    fireEvent.change(screen.getByLabelText(/Your name/), { target: { value: "Ada" } });
    fireEvent.change(screen.getByLabelText(/Email/), { target: { value: "ada@example.com" } });
    fireEvent.change(screen.getByLabelText(/Message/), { target: { value: "Could you run a workshop?" } });
    await act(async () => fireEvent.click(screen.getByRole("button", { name: "Send message" })));

    expect(api).toHaveBeenCalledWith("/api/v1/contact", expect.objectContaining({
      method: "POST",
      body: expect.objectContaining({ name: "Ada", email: "ada@example.com", page: "/contact", captcha_token: "token-123", website: "" }),
    }));
    expect(await screen.findByText("Thank you — your message has been sent.")).toBeInTheDocument();
  });

  it("has no accessibility violations", async () => {
    const { container } = render(<ContactForm buttonLabel="Send message" />);
    expect(await axe(container)).toHaveNoViolations();
  });
});

describe("Newsletter sign-up section", () => {
  const block = (enabled: boolean) => ({
    id: "n1",
    type: "newsletter_signup",
    version: 1,
    anchor: "newsletter",
    props: { eyebrow: null, heading: "Stay in the loop", intro: null, buttonLabel: "Subscribe", enabled, consentText: "Send me occasional emails." },
  });

  it("is hidden while sign-up is switched off", () => {
    const { container } = render(<BlockRenderer blocks={[block(false)]} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("asks for explicit consent and records where the person signed up", async () => {
    api.mockResolvedValue({ message: "Almost done — please check your inbox." });
    render(<BlockRenderer blocks={[block(true)]} />);

    fireEvent.change(screen.getByLabelText("Email address"), { target: { value: "reader@example.com" } });
    fireEvent.click(screen.getByRole("checkbox", { name: "Send me occasional emails." }));
    await act(async () => fireEvent.click(screen.getByRole("button", { name: "Subscribe" })));

    expect(api).toHaveBeenCalledWith("/api/v1/newsletter/subscribe", expect.objectContaining({
      body: expect.objectContaining({ email: "reader@example.com", consent: true, source: "block:newsletter" }),
    }));
    expect(await screen.findByText(/check your inbox/)).toBeInTheDocument();
  });
});

describe("Newsletter email links", () => {
  it("only acts after the button is pressed", async () => {
    search = new URLSearchParams({ s: "01abc", t: "token" });
    api.mockResolvedValue({ message: "You are subscribed. Thank you!" });
    render(<NewsletterLinkAction mode="confirm" />);

    expect(api).not.toHaveBeenCalled();
    await act(async () => fireEvent.click(screen.getByRole("button", { name: "Confirm my subscription" })));
    expect(api).toHaveBeenCalledWith("/api/v1/newsletter/confirm", { method: "POST", body: { s: "01abc", t: "token" } });
    expect(await screen.findByText("You are subscribed. Thank you!")).toBeInTheDocument();
  });

  it("explains incomplete links", () => {
    render(<NewsletterLinkAction mode="unsubscribe" />);
    expect(screen.getByText(/link is incomplete/)).toBeInTheDocument();
  });
});
