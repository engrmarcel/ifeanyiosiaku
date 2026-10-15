import { TerminalPanel } from "@/components/terminal/TerminalPanel";

/** Default terminal shown on the interim homepage (before a CMS homepage is published). */
export function TerminalIntro({ stack }: { stack: string[] }) {
  return (
    <TerminalPanel
      title="~/ifeanyi"
      lines={[
        { kind: "command", text: "whoami" },
        { kind: "output", text: "ifeanyi-osiaku — software · ai · business intelligence" },
        { kind: "command", text: "cat stack.txt" },
        { kind: "highlight", text: stack.join("  ·  ") },
        { kind: "command", text: "echo $MISSION" },
        { kind: "comment", text: "complex data → practical solutions" },
      ]}
    />
  );
}
