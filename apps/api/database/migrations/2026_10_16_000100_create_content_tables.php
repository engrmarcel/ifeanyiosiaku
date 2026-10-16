<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Articles (Markdown), publications with versions, shared categories and tags, and revision
 * history for content that is not a CMS page (DATABASE_DESIGN §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // article (later: product, course, event, ...)
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->morphs('categorizable');
            $table->primary(['category_id', 'categorizable_type', 'categorizable_id'], 'categorizables_primary');
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->primary(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_primary');
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug', 150)->unique();
            $table->string('title', 200);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body'); // Markdown
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->json('seo')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug', 150)->unique();
            $table->string('title', 200);
            $table->string('type', 20); // ebook, research, paper, technical
            $table->text('abstract')->nullable();
            $table->json('authors')->nullable();
            $table->string('doi', 120)->nullable();
            $table->string('isbn', 20)->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('seo')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('publication_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('version_label', 40);
            $table->text('changelog')->nullable();
            $table->foreignId('file_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->string('external_url', 500)->nullable();
            $table->date('released_at');
            $table->timestamps();
            $table->unique(['publication_id', 'version_label']);
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->unsignedInteger('number');
            $table->json('payload');
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['revisionable_type', 'revisionable_id', 'number'], 'content_revisions_unique_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('publication_versions');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('categorizables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
