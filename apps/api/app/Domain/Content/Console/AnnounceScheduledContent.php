<?php

namespace App\Domain\Content\Console;

use App\Domain\Content\ContentObserver;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Publication;
use App\Domain\Portfolio\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Articles, publications and projects can be published with a future time. Nothing changes in the
 * database when that time arrives, so this tells the website to show them once it has passed.
 */
#[Signature('content:announce-scheduled')]
#[Description('Refresh the website for articles, publications and projects whose scheduled publish time has arrived')]
class AnnounceScheduledContent extends Command
{
    public const LAST_RUN_KEY = 'content:scheduled:last-run';

    public function handle(): int
    {
        $now = now();
        $stored = Cache::get(self::LAST_RUN_KEY);
        // First run (or lost cache): look back a little, so nothing due in the last minutes is missed.
        $since = is_string($stored) ? Carbon::parse($stored) : $now->copy()->subMinutes(10);

        $count = 0;
        foreach ([Article::class, Publication::class, Project::class] as $model) {
            /** @var iterable<Model> $due */
            $due = $model::query()->where('status', 'published')
                ->where('published_at', '>', $since)->where('published_at', '<=', $now)->get();

            foreach ($due as $item) {
                $item->syncOriginal();
                if ($item instanceof Project) {
                    $item->touch(); // the portfolio observer refreshes every page that shows projects
                } else {
                    ContentObserver::refresh($item);
                }
                $count++;
            }
        }

        Cache::forever(self::LAST_RUN_KEY, $now->toIso8601String());

        if ($count > 0) {
            $this->components->info("Announced {$count} scheduled item(s).");
        }

        return self::SUCCESS;
    }
}
