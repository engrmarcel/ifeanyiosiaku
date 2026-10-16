<?php

namespace App\Domain\Content\Markdown;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Turns article Markdown into what the website renders (REQ-CMS-7):
 *
 * - "segments": sanitised HTML pieces, with top-level code blocks split out so the website can
 *   highlight them (code is never executed);
 * - "toc": the article's section headings (## and ###) with link anchors;
 * - reading time.
 *
 * Raw HTML in Markdown is removed, unsafe links are dropped, a "#" heading becomes a section
 * heading (the page title is the only top-level heading), and images must come from this site's
 * media library (other images become their description text).
 */
class MarkdownRenderer
{
    private const WORDS_PER_MINUTE = 220;

    private Environment $environment;

    public function __construct(private readonly MarkdownSanitizer $sanitizer)
    {
        $this->environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);
        $this->environment->addExtension(new CommonMarkCoreExtension);
        $this->environment->addExtension(new TableExtension);
        $this->environment->addExtension(new StrikethroughExtension);
        $this->environment->addExtension(new AutolinkExtension);
    }

    /**
     * @return array{
     *     segments: list<array{type: 'html', html: string}|array{type: 'code', language: string, title: string|null, code: string}>,
     *     toc: list<array{id: string, text: string, level: int}>,
     *     readingMinutes: int
     * }
     */
    public function render(string $markdown): array
    {
        $document = (new MarkdownParser($this->environment))->parse($markdown);

        $headings = [];
        $images = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Heading) {
                $headings[] = $node;
            } elseif ($node instanceof Image) {
                $images[] = $node;
            }
        }

        $origin = rtrim((string) config('app.url'), '/');
        foreach ($images as $image) {
            $url = $image->getUrl();
            if ($origin !== '' && str_starts_with($url, $origin.'/media/')) {
                $url = substr($url, strlen($origin));
                $image->setUrl($url);
            }
            if (! str_starts_with($url, '/media/') || str_contains($url, '..')) {
                $image->replaceWith(new Text(self::text($image)));
            }
        }

        $toc = [];
        $used = [];
        foreach ($headings as $heading) {
            if ($heading->getLevel() === 1) {
                $heading->setLevel(2);
            }

            $text = trim(self::text($heading));
            $base = Str::slug($text) ?: 'section';
            $id = $base;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = "{$base}-{$n}";
            }
            $used[$id] = true;
            $heading->data->set('attributes/id', $id);

            if ($heading->getLevel() <= 3 && $text !== '') {
                $toc[] = ['id' => $id, 'text' => $text, 'level' => $heading->getLevel()];
            }
        }

        // Consecutive non-code blocks are rendered together as one HTML segment.
        $renderer = new HtmlRenderer($this->environment);
        $segments = [];
        $pending = [];

        foreach ($document->children() as $block) {
            if ($block instanceof FencedCode || $block instanceof IndentedCode) {
                $this->appendHtml($segments, $renderer, $pending);
                $pending = [];
                [$language, $title] = $block instanceof FencedCode ? self::info($block) : ['plaintext', null];
                $segments[] = ['type' => 'code', 'language' => $language, 'title' => $title, 'code' => rtrim($block->getLiteral(), "\n")];
            } else {
                $pending[] = $block;
            }
        }
        $this->appendHtml($segments, $renderer, $pending);

        return ['segments' => $segments, 'toc' => $toc, 'readingMinutes' => self::readingMinutes($markdown)];
    }

    /**
     * @param  list<array{type: 'html', html: string}|array{type: 'code', language: string, title: string|null, code: string}>  $segments
     * @param  list<Node>  $blocks
     */
    private function appendHtml(array &$segments, HtmlRenderer $renderer, array $blocks): void
    {
        if ($blocks === []) {
            return;
        }

        $html = $this->sanitizer->clean($renderer->renderNodes($blocks));
        if ($html !== '') {
            $segments[] = ['type' => 'html', 'html' => $html];
        }
    }

    public static function readingMinutes(string $markdown): int
    {
        $words = str_word_count(strip_tags((string) preg_replace('/[#*_`>\[\]()!|-]+/', ' ', $markdown)));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /**
     * Language and optional title from a fence such as ```python title="etl.py"
     * Unknown languages fall back to plain text.
     *
     * @return array{0: string, 1: string|null}
     */
    private static function info(FencedCode $block): array
    {
        $words = $block->getInfoWords();
        $language = strtolower($words[0] ?? '');
        $language = array_key_exists($language, config('cms.code_languages', [])) ? $language : 'plaintext';

        $title = preg_match('/title="([^"]{1,120})"/', implode(' ', $words), $m) ? $m[1] : null;

        return [$language, $title];
    }

    private static function text(Node $node): string
    {
        $text = '';
        foreach ($node->iterator() as $child) {
            if ($child instanceof AbstractStringContainer) {
                $text .= $child->getLiteral();
            }
        }

        return $text;
    }
}
