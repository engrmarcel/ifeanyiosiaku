<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\CatalogueObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid file, stored on the private "products" disk. It has no public address: buyers download
 * it through a short-lived signed link after an entitlement check (Milestone 3, step 4).
 *
 * @property string $ulid
 * @property int $product_id
 * @property int|null $product_version_id
 * @property string $label
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property string|null $checksum_sha256
 * @property int|null $download_limit
 * @property int $sort_order
 */
#[ObservedBy(CatalogueObserver::class)]
class ProductFile extends Model
{
    use HasUlids;

    /** File types sellers may upload (never executed or displayed by the site; always downloaded). */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'epub', 'mobi', 'zip', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'md', 'json',
        'pbix', 'pbit', 'qvf', 'twbx', 'ipynb', 'sql', 'py', 'png', 'jpg', 'jpeg', 'svg', 'mp3', 'mp4',
    ];

    public const MAX_KILOBYTES = 512 * 1024; // 512 MB

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['disk' => 'products', 'size' => 0, 'sort_order' => 0];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'product_version_id');
    }
}
