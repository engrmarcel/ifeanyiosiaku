import { describe, expect, it } from "vitest";
import { serializeJsonLd } from "@/components/seo/JsonLd";
import { isSafeRedirectTarget } from "@/lib/cms/redirects";
import { FALLBACK_SITE_CONFIG, siteConfigSchema } from "@/lib/site/shared";
import { homepageSchema } from "./structuredData";

describe("serializeJsonLd", () => {
  it("cannot be used to close the script tag", () => {
    const json = serializeJsonLd({ name: "</script><script>alert(1)</script>" });

    expect(json).not.toContain("<");
    expect(JSON.parse(json)).toEqual({ name: "</script><script>alert(1)</script>" });
  });
});

describe("homepageSchema", () => {
  it("describes the person with real profile fields only", () => {
    const data = homepageSchema({
      ...FALLBACK_SITE_CONFIG,
      profile: { name: "Ifeanyi Osiaku", headline: "Software Developer", shortBio: null, portrait: null },
      social: [
        { network: "github", label: "GitHub", url: "https://github.com/example" },
        { network: "email", label: "Email", url: "mailto:hello@example.com" },
      ],
    });
    const person = (data["@graph"] as Record<string, unknown>[])[0];

    expect(person).toMatchObject({ "@type": "Person", name: "Ifeanyi Osiaku", jobTitle: "Software Developer" });
    expect(person.sameAs).toEqual(["https://github.com/example"]);
    expect(person).not.toHaveProperty("description");
    expect(person).not.toHaveProperty("image");
  });
});

describe("isSafeRedirectTarget", () => {
  it.each([
    ["/services", true],
    ["/#work", true],
    ["https://www.linkedin.com/in/example", true],
    ["//evil.example", false],
    ["/\\evil.example", false],
    ["http://insecure.example", false],
    ["javascript:alert(1)", false],
    ["", false],
  ])("%s → %s", (to, expected) => {
    expect(isSafeRedirectTarget(to)).toBe(expected);
  });
});

describe("siteConfigSchema", () => {
  it("accepts the fallback and rejects unsafe colour values", () => {
    expect(siteConfigSchema.safeParse(FALLBACK_SITE_CONFIG).success).toBe(true);

    const injected = { ...FALLBACK_SITE_CONFIG, theme: { accent: "x", tokens: { ...FALLBACK_SITE_CONFIG.theme.tokens, accent: "red;}body{display:none" } } };
    expect(siteConfigSchema.safeParse(injected).success).toBe(false);
  });
});
