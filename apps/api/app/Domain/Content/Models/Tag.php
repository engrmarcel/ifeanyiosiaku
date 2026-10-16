<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\ContentObserver;
use App\Policies\ContentPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property string $name
 * @property string $slug
 */
#[ObservedBy(ContentObserver::class)]
#[UsePolicy(ContentPolicy::class)]
class Tag extends Model
{
    protected $guarded = ['id'];

    /** @return MorphToMany<Article, $this> */
    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }

    /** @return array{slug: string, name: string} */
    public function toPublic(): array
    {
        return ['slug' => $this->slug, 'name' => $this->name];
    }
}
