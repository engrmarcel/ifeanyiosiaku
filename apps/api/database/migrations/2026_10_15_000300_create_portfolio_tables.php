<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('summary', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('role', 160)->nullable();
            $table->string('client', 160)->nullable();
            $table->string('period', 60)->nullable();
            $table->json('technologies')->nullable();
            $table->json('links')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured', 'sort_order']);
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('group', 20)->index(); // expertise | technology
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['group', 'name']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 120);
            $table->string('summary', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('experience_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('organisation', 160)->nullable();
            $table->string('location', 120)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('issuer', 160)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('credential_id', 120)->nullable();
            $table->string('credential_url', 500)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name', 120);
            $table->string('author_title', 160)->nullable();
            $table->text('quote');
            $table->foreignId('avatar_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            // Shown publicly only when the author's permission to publish has been confirmed.
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('experience_entries');
        Schema::dropIfExists('services');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('projects');
    }
};
