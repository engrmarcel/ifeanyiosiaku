/// <reference lib="webworker" />
import { solve } from "./pow";

type SolveMessage = { challenge: string; difficulty: number };

self.onmessage = async (event: MessageEvent<SolveMessage>) => {
  try {
    const nonce = await solve(event.data.challenge, event.data.difficulty);
    self.postMessage({ ok: true, nonce });
  } catch (error) {
    self.postMessage({ ok: false, error: error instanceof Error ? error.message : "Failed" });
  }
};
