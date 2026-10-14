/**
 * First-party CAPTCHA proof of work (mirrors App\Domain\Captcha\Drivers\FirstPartyCaptcha).
 * Find a hex nonce so that SHA-256("{challenge}:{nonce}") starts with `difficulty` zero bits.
 */

export function hasLeadingZeroBits(hash: Uint8Array, bits: number): boolean {
  const fullBytes = Math.floor(bits / 8);
  const remainingBits = bits % 8;

  for (let i = 0; i < fullBytes; i++) {
    if (hash[i] !== 0) return false;
  }

  if (remainingBits === 0) return true;

  return hash[fullBytes] >> (8 - remainingBits) === 0;
}

const BATCH = 512;

export async function solve(
  challenge: string,
  difficulty: number,
  options: { signal?: AbortSignal; maxAttempts?: number } = {},
): Promise<string> {
  const encoder = new TextEncoder();
  const maxAttempts = options.maxAttempts ?? 2 ** Math.min(difficulty + 8, 30);

  for (let start = 0; start < maxAttempts; start += BATCH) {
    if (options.signal?.aborted) throw new DOMException("Aborted", "AbortError");

    const nonces = Array.from({ length: BATCH }, (_, i) => (start + i).toString(16));
    const hashes = await Promise.all(
      nonces.map((nonce) => crypto.subtle.digest("SHA-256", encoder.encode(`${challenge}:${nonce}`))),
    );

    const index = hashes.findIndex((hash) => hasLeadingZeroBits(new Uint8Array(hash), difficulty));
    if (index !== -1) return nonces[index];
  }

  throw new Error("Proof of work not found within the attempt limit.");
}
