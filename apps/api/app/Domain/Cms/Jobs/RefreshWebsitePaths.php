<?php

namespace App\Domain\Cms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the website to drop its cached copy of the given paths, so a publish shows up immediately.
 * Retries with back-off; if the website is unreachable the hourly cache lifetime is the fallback.
 */
class RefreshWebsitePaths implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    /**
     * @param  list<string>  $paths
     */
    public function __construct(public readonly array $paths) {}

    public function handle(): void
    {
        $secret = (string) config('platform.web_revalidate_secret');
        $url = rtrim((string) config('platform.web_internal_url'), '/').'/internal/revalidate';

        if (strlen($secret) < 32) {
            Log::info('Website refresh skipped: WEB_REVALIDATE_SECRET is not set (pages refresh within an hour).', ['paths' => $this->paths]);

            return;
        }

        Http::timeout(10)
            ->withHeaders(['X-Revalidate-Secret' => $secret])
            ->acceptJson()
            ->post($url, ['paths' => $this->paths])
            ->throw();
    }
}
