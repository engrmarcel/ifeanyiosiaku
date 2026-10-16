<?php

namespace App\Domain\Cms\Support;

/**
 * Signed address for one trusted HTML block (REQ-CMS-8). It names the page, the block's position
 * and a fingerprint of its content, so a changed or removed block stops being served. Links for
 * unpublished drafts expire like preview links.
 */
class EmbedToken
{
    public function issue(string $pageUlid, bool $draft, int $index, string $content): string
    {
        $payload = [
            'p' => $pageUlid,
            'i' => $index,
            'h' => self::fingerprint($content),
            'd' => $draft,
        ];

        if ($draft) {
            $payload['e'] = now()->addMinutes((int) config('cms.preview_ttl_minutes', 15))->getTimestamp();
        }

        $encoded = self::encode((string) json_encode($payload));

        return $encoded.'.'.$this->sign($encoded);
    }

    /**
     * @return array{p: string, i: int, h: string, d: bool}|null
     */
    public function verify(string $token): ?array
    {
        if (substr_count($token, '.') !== 1 || strlen($token) > 500) {
            return null;
        }

        [$encoded, $signature] = explode('.', $token);

        if (! hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($encoded, '-_', '+/'), true), true);

        if (! is_array($data) || ! is_string($data['p'] ?? null) || ! is_int($data['i'] ?? null)
            || ! is_string($data['h'] ?? null) || ! is_bool($data['d'] ?? null)) {
            return null;
        }

        if ($data['d'] && (! is_int($data['e'] ?? null) || $data['e'] < now()->getTimestamp())) {
            return null;
        }

        return ['p' => $data['p'], 'i' => $data['i'], 'h' => $data['h'], 'd' => $data['d']];
    }

    public static function fingerprint(string $content): string
    {
        return substr(hash('sha256', $content), 0, 20);
    }

    private function sign(string $payload): string
    {
        return self::encode(hash_hmac('sha256', $payload, hash_hmac('sha256', 'cms-embed', (string) config('app.key'), true), true));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
