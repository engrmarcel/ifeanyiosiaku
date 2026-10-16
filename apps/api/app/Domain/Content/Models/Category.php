<?php

namespace App\Domain\Content\Models;

use App\Domain\Commerce\Models\Product;
use App\Domain\Content\ContentObserver;
use App\Policies\ContentPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A category for one kind of content (articles and store products so far; courses and events add theirs).
 *
 * @property string $type
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 */
#[ObservedBy(ContentObserver::class)]
#[UsePolicy(ContentPolicy::class)]
class Category extends Model
{
    public const TYPE_ARTICLE = 'article';

    public const TYPE_PRODUCT = 'product';

    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['type' => self::TYPE_ARTICLE, 'sort_order' => 0];

    /** @return MorphToMany<Article, $this> */
    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'categorizable');
    }

    /** @return MorphToMany<Product, $this> */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }

    /** @return array{slug: string, name: string} */
    public function toPublic(): array
    {
        return ['slug' => $this->slug, 'name' => $this->name];
    }
}
