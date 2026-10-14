import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { axe } from "vitest-axe";
import { Alert } from "./Alert";
import { Button } from "./Button";
import { Dialog } from "./Dialog";
import { TextField } from "./TextField";

describe("TextField", () => {
  it("associates label, hint and error with the input", () => {
    render(<TextField label="Email" hint="We never share it." error="Required" />);

    const input = screen.getByLabelText("Email");
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input).toHaveAccessibleDescription("Required");
  });

  it("has no detectable accessibility violations", async () => {
    const { container } = render(
      <form>
        <TextField label="Email" type="email" hint="Used for sign-in." required />
        <Button type="submit">Send</Button>
      </form>,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});

describe("Button", () => {
  it("is disabled and busy while loading", async () => {
    const onClick = vi.fn();
    render(
      <Button loading onClick={onClick}>
        Save
      </Button>,
    );

    const button = screen.getByRole("button", { name: "Save" });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute("aria-busy", "true");
    await userEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
  });
});

describe("Alert", () => {
  it("announces errors assertively and other messages politely", () => {
    render(
      <>
        <Alert tone="error">Broken</Alert>
        <Alert tone="success">Saved</Alert>
      </>,
    );

    expect(screen.getByRole("alert")).toHaveTextContent("Broken");
    expect(screen.getByRole("status")).toHaveTextContent("Saved");
  });
});

describe("Dialog", () => {
  it("is labelled, closes on Escape and reports the change", async () => {
    const onOpenChange = vi.fn();
    render(
      <Dialog open onOpenChange={onOpenChange} title="Confirm your password">
        <button>Inside</button>
      </Dialog>,
    );

    expect(screen.getByRole("dialog", { name: "Confirm your password" })).toBeInTheDocument();
    await userEvent.keyboard("{Escape}");
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });
});
