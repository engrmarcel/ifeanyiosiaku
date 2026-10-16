<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Models\Page;
use App\Domain\Content\Models\Article;

/**
 * Short-lived signed link that shows one unpublished draft (a page or an article). Anyone holding
 * the link can view that one draft until it expires (15 minutes by default); it grants nothing else.
 */
class PreviewToken
{
    public const PAGE = 'page';

    public const ARTICLE = 'article';

    public function issue(Page $page): string
    {
        return $this->issueFor(self::PAGE, $page->ulid);
    }

    public function issueForArticle(Article $article): string
    {
        return $this->issueFor(self::ARTICLE, $article->ulid);
    }

    /** Returns the ULID for a valid, unexpired token of the given kind. */
    public function verify(?string $token, string $kind = self::PAGE): ?string
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

        // Links issued before articles existed carry no kind and are page links.
        if (($data['k'] ?? self::PAGE) !== $kind) {
            return null;
        }

        return $data['p'];
    }

    private function issueFor(string $kind, string $ulid): string
    {
        $payload = $this->encode((string) json_encode([
            'k' => $kind,
            'p' => $ulid,
            'e' => now()->addMinutes((int) config('cms.preview_ttl_minutes', 15))->getTimestamp(),
        ]));

        return $payload.'.'.$this->sign($payload);
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
