<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Actions\SetHomepage;
use App\Domain\Cms\Models\Page;

function resolvePath(string $path)
{
    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/page-resolve?path='.urlencode($path));
}

function livePage(string $slug): Page
{
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => ucfirst($slug), 'slug' => $slug, 'blocks' => []], null);

    return app(PublishPage::class)->handle($page, null);
}

it('reports published pages, redirects and missing addresses', function () {
    $page = livePage('about');

    resolvePath('/about')->assertExactJson(['data' => ['status' => 'page']]);
    resolvePath('/nothing-here')->assertExactJson(['data' => ['status' => 'missing']]);

    app(PublishPage::class)->handle(app(SavePageDraft::class)->handle($page, ['title' => 'About', 'slug' => 'about-me', 'blocks' => []], null), null);

    resolvePath('/about')->assertExactJson(['data' => ['status' => 'redirect', 'to' => '/about-me', 'code' => 301]]);
    resolvePath('/about-me')->assertExactJson(['data' => ['status' => 'page']]);
});

it('redirects the homepage slug to "/"', function () {
    app(SetHomepage::class)->handle(livePage('start'), null);

    resolvePath('/start')->assertJsonPath('data.status', 'redirect')->assertJsonPath('data.to', '/');
});

it('treats malformed paths as missing', function (string $path) {
    resolvePath($path)->assertExactJson(['data' => ['status' => 'missing']]);
})->with(['/../../etc/passwd', '/<script>', str_repeat('/a', 200)]);

it('does not throttle the website server, but still throttles visitors', function () {
    for ($i = 0; $i < 70; $i++) {
        $this->getJson('/api/v1/page-resolve?path=/x')->assertOk();
    }

    for ($i = 0; $i < 60; $i++) {
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->getJson('/api/v1/page-resolve?path=/x');
    }

    $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->getJson('/api/v1/page-resolve?path=/x')->assertTooManyRequests();
});
