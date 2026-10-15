<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('disk', 30);
            $table->string('directory', 100);
            $table->string('filename', 150);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100)->index();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title', 150)->nullable();
            $table->string('alt_text', 300)->nullable();
            $table->text('description')->nullable();
            $table->char('checksum_sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 20);
            $table->string('filename', 150);
            $table->string('mime', 100);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['media_asset_id', 'variant']);
        });

        // Where each asset is used, so editors can see dependencies and in-use files cannot be deleted.
        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->string('usable_type', 100);
            $table->unsignedBigInteger('usable_id');
            $table->string('field', 100);
            $table->timestamps();

            $table->unique(['media_asset_id', 'usable_type', 'usable_id', 'field'], 'media_usage_unique');
            $table->index(['usable_type', 'usable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media_assets');
    }
};
