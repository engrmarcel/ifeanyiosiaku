<?php

namespace App\Domain\Cms\Http;

use App\Domain\Cms\Blocks\Types\RawHtmlBlock;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Support\EmbedToken;
use Illuminate\Http\Response;

/**
 * Serves one trusted HTML block as its own document for a sandboxed frame (REQ-CMS-8).
 *
 * The response's Content-Security-Policy "sandbox" puts it in a unique, opaque origin even though
 * it is served from our domain, so its scripts cannot read cookies, storage or the parent page.
 * Only this site may frame it, and only the exact content that was signed is served.
 */
class EmbedController
{
    public const ROUTE = 'api.v1.embeds.show';

    /** Rules for the custom code itself: anything it loads, nothing it can reach of ours. */
    public const POLICY = 'sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms; '
        ."default-src * data: blob: 'unsafe-inline' 'unsafe-eval'; frame-ancestors 'self'; base-uri 'none'";

    public function show(string $token, EmbedToken $tokens): Response
    {
        $claims = $tokens->verify($token);
        $html = $claims !== null ? self::signedHtml($claims) : null;

        if ($claims === null || $html === null || ! hash_equals(EmbedToken::fingerprint($html), $claims['h'])) {
            return response('Not found', 404, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
        }

        return response(self::document($html), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::POLICY,
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex',
            // The address contains a fingerprint of the content, so it can be cached until it changes.
            'Cache-Control' => $claims['d'] ? 'no-store' : 'public, max-age=300',
        ]);
    }

    /**
     * The custom code the token points at: the page's draft (preview links) or its published
     * version, the block at that position, only if it is a visible custom-code block.
     *
     * @param  array{p: string, i: int, h: string, d: bool}  $claims
     */
    private static function signedHtml(array $claims): ?string
    {
        $page = Page::query()->with('publishedRevision')->where('ulid', $claims['p'])->first();

        $blocks = match (true) {
            $page === null => null,
            $claims['d'] => $page->blocks,
            $page->status === PageStatus::Published => $page->publishedRevision?->blocks,
            default => null,
        };

        $block = $blocks[$claims['i']] ?? null;

        if (! is_array($block) || $block['type'] !== RawHtmlBlock::type() || ($block['data']['visible'] ?? true) === false) {
            return null;
        }

        return (string) ($block['data']['html'] ?? '');
    }

    /** The block's code, plus a tiny script that tells the page how tall it is (for "fit content"). */
    private static function document(string $html): string
    {
        // Measures the content (the body), not the frame's own window, which is never smaller than the frame.
        $resize = '(function(){function s(){var b=document.body;parent.postMessage({type:"io-embed-height",height:Math.ceil(b.getBoundingClientRect().height)},"*")}'
            .'addEventListener("load",s);if(window.ResizeObserver){new ResizeObserver(s).observe(document.body)}s()})();';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark"><base target="_blank">'
            .'<style>:root{color-scheme:dark}html,body{margin:0;background:transparent;color:#f5f7fa;font-family:system-ui,sans-serif}body{display:flow-root}</style>'
            .'</head><body>'.$html.'<script>'.$resize.'</script></body></html>';
    }
}
