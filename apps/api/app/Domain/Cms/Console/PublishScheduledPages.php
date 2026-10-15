<?php

namespace App\Domain\Cms\Console;

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\UnpublishPage;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('cms:publish-scheduled')]
#[Description('Publish pages whose scheduled time has arrived and unpublish expired ones')]
class PublishScheduledPages extends Command
{
    public function handle(PublishPage $publish, UnpublishPage $unpublish): int
    {
        $due = Page::query()->where('status', PageStatus::Scheduled)->where('publish_at', '<=', now())->get();

        foreach ($due as $page) {
            try {
                $publish->handle($page, null);
                $this->components->info("Published {$page->slug}");
            } catch (Throwable $e) {
                report($e);
                $this->components->error("Could not publish {$page->slug}: {$e->getMessage()}");
            }
        }

        $expired = Page::query()->where('status', PageStatus::Published)->where('is_homepage', false)
            ->whereNotNull('unpublish_at')->where('unpublish_at', '<=', now())->get();

        foreach ($expired as $page) {
            $unpublish->handle($page, null);
            $this->components->info("Unpublished {$page->slug}");
        }

        return self::SUCCESS;
    }
}
