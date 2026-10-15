import { act, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { axe } from "vitest-axe";
import { ErrorState } from "./ErrorState";
import { ToastProvider, useToast, type ToastOptions } from "./Toast";

function Trigger({ options }: { options: ToastOptions }) {
  const { toast } = useToast();
  return <button onClick={() => toast(options)}>Notify</button>;
}

describe("Toast", () => {
  afterEach(() => vi.useRealTimers());

  it("announces success messages politely and removes them after a few seconds", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    render(
      <ToastProvider>
        <Trigger options={{ title: "Settings saved", tone: "success" }} />
      </ToastProvider>,
    );

    await user.click(screen.getByRole("button", { name: "Notify" }));
    expect(screen.getByRole("status")).toHaveTextContent("Settings saved");

    act(() => vi.advanceTimersByTime(5100));
    expect(screen.queryByText("Settings saved")).not.toBeInTheDocument();
  });

  it("announces errors assertively and keeps them until dismissed", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    render(
      <ToastProvider>
        <Trigger options={{ title: "Payment failed", description: "Please try again.", tone: "error" }} />
      </ToastProvider>,
    );

    await user.click(screen.getByRole("button", { name: "Notify" }));
    act(() => vi.advanceTimersByTime(60_000));
    expect(screen.getByRole("alert")).toHaveTextContent("Payment failed");

    await user.click(screen.getByRole("button", { name: "Dismiss: Payment failed" }));
    expect(screen.queryByText("Payment failed")).not.toBeInTheDocument();
  });

  it("closes the newest message with Escape", async () => {
    const user = userEvent.setup();
    render(
      <ToastProvider>
        <Trigger options={{ title: "Copied", tone: "info", duration: 60_000 }} />
      </ToastProvider>,
    );

    await user.click(screen.getByRole("button", { name: "Notify" }));
    await user.keyboard("{Escape}");
    expect(screen.queryByText("Copied")).not.toBeInTheDocument();
  });
});

describe("ErrorState", () => {
  it("offers a retry and shows the support reference", async () => {
    const onRetry = vi.fn();
    const { container } = render(<ErrorState onRetry={onRetry} requestId="req-123" />);

    await userEvent.click(screen.getByRole("button", { name: "Try again" }));
    expect(onRetry).toHaveBeenCalledOnce();
    expect(screen.getByRole("alert")).toHaveTextContent("Reference: req-123");
    expect(await axe(container)).toHaveNoViolations();
  });
});
