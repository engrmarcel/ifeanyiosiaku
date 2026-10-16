<?php

use App\Domain\Content\Markdown\MarkdownRenderer;

function md(string $markdown): array
{
    return app(MarkdownRenderer::class)->render($markdown);
}

function mdHtml(string $markdown): string
{
    return implode("\n", array_map(fn ($s) => $s['html'] ?? '', md($markdown)['segments']));
}

it('builds a contents list from section headings with unique anchors', function () {
    $result = md("# Getting started\n\nIntro.\n\n## Setup\n\n### Install `pip`\n\n## Setup\n\n#### Deep detail");

    expect($result['toc'])->toBe([
        ['id' => 'getting-started', 'text' => 'Getting started', 'level' => 2],
        ['id' => 'setup', 'text' => 'Setup', 'level' => 2],
        ['id' => 'install-pip', 'text' => 'Install pip', 'level' => 3],
        ['id' => 'setup-2', 'text' => 'Setup', 'level' => 2],
    ]);

    // The page title is the only top-level heading, so "#" becomes a section heading.
    expect(mdHtml('# Title'))->toBe('<h2 id="title">Title</h2>');
});

it('splits code blocks out for highlighting, with language and title', function () {
    $segments = md("Before\n\n```python title=\"etl.py\"\nprint('hi')\n```\n\n```cobol\nDISPLAY 'X'.\n```\n\nAfter")['segments'];

    expect($segments)->toHaveCount(4)
        ->and($segments[0])->toBe(['type' => 'html', 'html' => '<p>Before</p>'])
        ->and($segments[1])->toBe(['type' => 'code', 'language' => 'python', 'title' => 'etl.py', 'code' => "print('hi')"])
        ->and($segments[2]['language'])->toBe('plaintext')
        ->and($segments[3]['html'])->toBe('<p>After</p>');
});

it('removes raw HTML, scripts and unsafe links', function () {
    $html = mdHtml("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1)) and [ok](https://example.com)\n\n<iframe src=\"https://evil.example\"></iframe>");

    expect($html)->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe')
        ->toContain('href="https://example.com"')
        ->toContain('rel="noopener noreferrer nofollow"');
});

it('only shows images from the media library', function () {
    config(['app.url' => 'https://ifeanyiosiaku.com']);

    $html = mdHtml("![Dashboard](/media/01abc/dashboard.png)\n\n![Same site](https://ifeanyiosiaku.com/media/01abc/chart.png)\n\n![Tracker](https://evil.example/pixel.gif)\n\n![Sneaky](/media/../../.env)");

    expect($html)->toContain('<img src="/media/01abc/dashboard.png" alt="Dashboard" loading="lazy"')
        ->toContain('src="/media/01abc/chart.png"')
        ->not->toContain('evil.example')
        ->toContain('Tracker')
        ->not->toContain('.env');
});

it('supports tables and strikethrough', function () {
    $html = mdHtml("| Tool | Use |\n|:--|--:|\n| DAX | Measures |\n\n~~old~~");

    expect($html)->toContain('<table>')->toContain('<td align="left">DAX</td>')->toContain('<del>old</del>');
});

it('estimates reading time', function () {
    expect(MarkdownRenderer::readingMinutes('short text'))->toBe(1)
        ->and(MarkdownRenderer::readingMinutes(str_repeat('word ', 1000)))->toBe(5);
});
