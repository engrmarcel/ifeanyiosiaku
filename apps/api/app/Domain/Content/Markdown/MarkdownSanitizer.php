<?php

namespace App\Domain\Content\Markdown;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Allow-list for HTML produced from article Markdown (SECURITY_THREAT_MODEL T2). Like the rich-text
 * sanitiser, plus heading anchors, table alignment and images from this site's media library.
 */
class MarkdownSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes([])
            ->allowRelativeMedias()
            ->withMaxInputLength(500_000);

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 's', 'del', 'sub', 'sup', 'code', 'pre', 'blockquote',
            'ul', 'ol', 'li', 'hr', 'table', 'thead', 'tbody', 'tr', 'mark', 'small'] as $element) {
            $config = $config->allowElement($element);
        }

        foreach (['h2', 'h3', 'h4'] as $heading) {
            $config = $config->allowElement($heading, ['id']);
        }

        $config = $config
            ->allowElement('th', ['align'])
            ->allowElement('td', ['align'])
            ->allowElement('ol', ['start'])
            ->allowElement('img', ['src', 'alt', 'title'])
            ->forceAttribute('img', 'loading', 'lazy')
            ->allowElement('a', ['href', 'title'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(string $html): string
    {
        return trim($this->sanitizer->sanitize($html));
    }
}
