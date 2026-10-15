<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Models\Page;

/**
 * Short-lived signed link that shows a page's unpublished draft. Anyone holding the link can view
 * that one draft until it expires (15 minutes by default); it grants nothing else.
 */
class PreviewToken
{
    public function issue(Page $page): string
    {
        $payload = $this->encode((string) json_encode([
            'p' => $page->ulid,
            'e' => now()->addMinutes((int) config('cms.preview_ttl_minutes', 15))->getTimestamp(),
        ]));

        return $payload.'.'.$this->sign($payload);
    }

    /** Returns the page ULID for a valid, unexpired token. */
    public function verify(?string $token): ?string
    {
        if (! is_string($token) || substr_count($token, '.') !== 1 || strlen($token) > 400) {
            return null;
        }

        [$payload, $signature] = explode('.', $token);

        if (! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (! is_array($data) || ! is_string($data['p'] ?? null) || ! is_int($data['e'] ?? null) || $data['e'] < now()->getTimestamp()) {
            return null;
        }

        return $data['p'];
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, hash_hmac('sha256', 'cms-preview', (string) config('app.key'), true), true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
