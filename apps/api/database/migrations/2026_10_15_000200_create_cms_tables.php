<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            // Working draft (what editors change). Visitors only ever see a published revision.
            $table->string('title', 200);
            $table->string('slug', 150)->unique();
            $table->string('template', 30)->default('default');
            $table->json('blocks')->nullable();
            $table->json('seo')->nullable();
            $table->boolean('is_homepage')->default(false)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('unpublish_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Immutable snapshots: every publish, every saved draft and every restore.
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('kind', 20);
            $table->string('title', 200);
            $table->string('slug', 150);
            $table->json('blocks')->nullable();
            $table->json('seo')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['page_id', 'number']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('published_revision_id')->references('id')->on('page_revisions')->nullOnDelete();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 255)->unique();
            $table->string('to_path', 255);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hits')->default(0);
            $table->boolean('is_automatic')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::table('pages', fn (Blueprint $table) => $table->dropForeign(['published_revision_id']));
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
    }
};
