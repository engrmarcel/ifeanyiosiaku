<?php

namespace App\Domain\Cms\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Allow-list sanitiser for editor rich text (SECURITY_THREAT_MODEL T2). Only formatting elements
 * survive; scripts, event handlers, styles, iframes and javascript: links are removed. Applied
 * when content is saved and again when it is served.
 */
class RichTextSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->withMaxInputLength(200_000);

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'code', 'pre', 'blockquote',
            'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'mark', 'small'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config
            ->allowElement('a', ['href', 'title', 'target'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim($this->sanitizer->sanitize($html));
    }
}
