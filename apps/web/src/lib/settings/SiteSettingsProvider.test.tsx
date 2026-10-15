import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { SiteSettingsProvider, useAddon, type AddonName } from "./SiteSettingsProvider";

function Probe({ name }: { name: AddonName }) {
  return <span data-testid={name}>{useAddon(name) ? "on" : "off"}</span>;
}

function renderWith(response: Promise<Response>) {
  vi.stubGlobal("fetch", vi.fn(() => response));
  return render(
    <SiteSettingsProvider>
      <Probe name="commerce" />
      <Probe name="community" />
    </SiteSettingsProvider>,
  );
}

describe("useAddon", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("follows the administrator's switches", async () => {
    renderWith(
      Promise.resolve(
        Response.json({ data: { "addons.commerce.enabled": true, "addons.community.enabled": false } }),
      ),
    );

    await waitFor(() => expect(screen.getByTestId("community")).toHaveTextContent("off"));
    expect(screen.getByTestId("commerce")).toHaveTextContent("on");
  });

  it("treats modules as on until settings load or if they cannot be loaded", async () => {
    renderWith(Promise.reject(new Error("network down")));

    expect(screen.getByTestId("community")).toHaveTextContent("on");
    await waitFor(() => expect(screen.getByTestId("commerce")).toHaveTextContent("on"));
  });
});
