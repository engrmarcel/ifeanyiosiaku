<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\CatalogueObserver;
use App\Domain\Commerce\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** Shared save rules for the product create and edit screens. */
trait SavesProducts
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareProductData(array $data, ?Product $record): array
    {
        $status = $data['status'] ?? $record->status ?? Product::STATUS_DRAFT;

        if ($status === Product::STATUS_PUBLISHED && blank($data['published_at'] ?? $record?->published_at)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /** Free products keep no prices; record the change and refresh the website. */
    protected function afterProductSaved(Product $product, string $action): void
    {
        if ($product->is_free) {
            $product->prices()->delete();
        }

        $actor = Auth::user();
        app(AuditLogger::class)->record($action, $product, [
            'slug' => $product->slug,
            'status' => $product->status,
            'free' => $product->is_free,
            'prices' => $product->prices()->get(['currency', 'amount'])->toArray(),
        ], $actor instanceof User ? $actor : null);

        CatalogueObserver::refresh($product->refresh());
    }
}
