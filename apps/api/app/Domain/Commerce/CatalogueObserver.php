<?php

namespace App\Domain\Commerce;

use App\Domain\Cms\AutomaticRedirects;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\LivePaths;
use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Models\ProductFile;
use App\Domain\Commerce\Models\ProductPrice;
use App\Domain\Commerce\Models\ProductVersion;
use App\Domain\Media\MediaUsageRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the website in step with the catalogue: sanitises descriptions, records media in use,
 * redirects the old address of a live product that moved, and refreshes the affected pages.
 */
class CatalogueObserver
{
    public function __construct(private readonly MediaUsageRecorder $usages) {}

    public function saving(Model $model): void
    {
        if ($model instanceof Product && $model->isDirty('description')) {
            $model->description = app(RichTextSanitizer::class)->clean($model->description) ?: null;
        }

        // Record the stored file's real size, type and fingerprint (never the browser's claims).
        if ($model instanceof ProductFile && $model->isDirty('path')) {
            $disk = Storage::disk($model->disk);

            if ($disk->exists($model->path)) {
                $model->size = $disk->size($model->path);
                $model->mime = (string) $disk->mimeType($model->path) ?: null;
                $model->checksum_sha256 = hash_file('sha256', $disk->path($model->path)) ?: null;
            }
        }
    }

    public function saved(Model $model): void
    {
        if ($model instanceof Product) {
            $this->usages->sync($model, 'cover_media_id', array_filter([(int) $model->cover_media_id]));
            $this->usages->sync($model, 'preview_media_id', array_filter([(int) $model->preview_media_id]));

            $publishedAt = $model->getOriginal('published_at');
            $wasLive = $model->getOriginal('status') === Product::STATUS_PUBLISHED && $publishedAt instanceof Carbon && $publishedAt->lte(now());

            if ($model->wasChanged('slug') && $wasLive) {
                $userId = Auth::id();
                AutomaticRedirects::moved('/store/'.$model->getOriginal('slug'), $model->path(), is_int($userId) ? $userId : null);
            }
        }

        self::refresh($model);
    }

    public function deleted(Model $model): void
    {
        self::refresh($model);
    }

    public function restored(Model $model): void
    {
        self::refresh($model);
    }

    public function forceDeleted(Model $model): void
    {
        if ($model instanceof Product) {
            $this->usages->forget($model);
        }
    }

    /** A removed file's bytes are deleted from private storage as well. */
    public function deleting(Model $model): void
    {
        if ($model instanceof ProductFile && Storage::disk($model->disk)->exists($model->path)) {
            Storage::disk($model->disk)->delete($model->path);
        }
    }

    /** Asks the website to refresh the store list, the product page and pages listing products. */
    public static function refresh(Model $model): void
    {
        $product = match (true) {
            $model instanceof Product => $model,
            $model instanceof ProductPrice, $model instanceof ProductVersion, $model instanceof ProductFile => $model->product,
            default => null,
        };

        $paths = [...LivePaths::all(), '/store'];

        if ($product !== null) {
            $paths[] = $product->path();
            if ($product->wasChanged('slug') && is_string($product->getOriginal('slug'))) {
                $paths[] = '/store/'.$product->getOriginal('slug');
            }
        }

        PageContentChanged::dispatch(array_values(array_unique($paths)));
    }
}
