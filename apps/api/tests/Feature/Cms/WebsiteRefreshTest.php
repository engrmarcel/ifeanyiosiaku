<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Jobs\RefreshWebsitePaths;
use App\Domain\Cms\Models\Page;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('asks the website to refresh the affected paths after publishing', function () {
    Queue::fake();
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => 'About', 'slug' => 'about', 'blocks' => []], null);

    app(PublishPage::class)->handle($page, null);

    Queue::assertPushed(RefreshWebsitePaths::class, fn (RefreshWebsitePaths $job) => $job->paths === ['/about']);
});

it('sends the refresh request with the shared secret', function () {
    config(['platform.web_revalidate_secret' => str_repeat('s', 64), 'platform.web_internal_url' => 'http://web.internal']);
    Http::fake(['web.internal/*' => Http::response(['revalidated' => ['/about']])]);

    (new RefreshWebsitePaths(['/about', '/']))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'http://web.internal/internal/revalidate'
        && $request->header('X-Revalidate-Secret')[0] === str_repeat('s', 64)
        && $request['paths'] === ['/about', '/']);
});

it('skips the request when no secret is configured', function () {
    config(['platform.web_revalidate_secret' => null]);
    Http::fake();

    (new RefreshWebsitePaths(['/about']))->handle();

    Http::assertNothingSent();
});

it('fails (and is retried) when the website rejects the request', function () {
    config(['platform.web_revalidate_secret' => str_repeat('s', 64), 'platform.web_internal_url' => 'http://web.internal']);
    Http::fake(['web.internal/*' => Http::response(['error' => 'unauthorised'], 401)]);

    (new RefreshWebsitePaths(['/about']))->handle();
})->throws(RequestException::class);

it('never lets a refresh failure block publishing', function () {
    config(['platform.web_revalidate_secret' => str_repeat('s', 64), 'platform.web_internal_url' => 'http://web.internal']);
    Http::fake(['web.internal/*' => Http::response('down', 500)]);
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => 'About', 'slug' => 'about', 'blocks' => []], null);

    app(PublishPage::class)->handle($page, null);

    expect($page->fresh()->isLive())->toBeTrue();
});
