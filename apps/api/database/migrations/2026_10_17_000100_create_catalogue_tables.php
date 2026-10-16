<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store catalogue (Milestone 3, DATABASE_DESIGN §2.3): products, per-currency prices, versions,
 * downloadable files kept on private storage, and bundles. Money is integer minor units (ADR-005).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('type', 30);
            $table->string('slug', 150)->unique();
            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable(); // sanitised rich text
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->foreignId('preview_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_free')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->text('licence_terms')->nullable();
            $table->nullableMorphs('sellable'); // a course, ticket type or booking service (later modules)
            $table->boolean('coin_redeemable')->default(false);
            $table->unsignedInteger('max_coin_ratio_bp')->nullable();
            $table->json('seo')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'status']);
            $table->index(['status', 'published_at']);
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('compare_at_amount')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'currency']);
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version_label', 40);
            $table->text('changelog')->nullable();
            $table->date('released_at');
            $table->timestamps();
            $table->unique(['product_id', 'version_label']);
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 150);
            $table->string('disk', 30)->default('products');
            $table->string('path', 300);
            $table->string('original_name', 255);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('download_limit')->nullable(); // null = store default
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['bundle_product_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
    }
};
