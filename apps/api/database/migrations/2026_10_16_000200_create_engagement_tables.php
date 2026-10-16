<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Announcement popup with analytics, contact messages and newsletter subscribers (DATABASE_DESIGN §2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('title', 120);
            $table->text('message');
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('audience', 20)->default('everyone'); // everyone, guests, members
            $table->string('placement', 20)->default('all'); // all, home
            $table->string('frequency', 20)->default('once'); // once, session, daily, always
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('campaign', 60)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('announcement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('visitor_hash', 64)->nullable(); // anonymous browser id, hashed
            $table->string('type', 20); // impression, click, dismiss
            $table->timestamp('created_at')->useCurrent();
            $table->index(['announcement_id', 'type']);
            $table->index('created_at');
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('subject', 150)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new'); // new, read, replied, spam, archived
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->string('page', 200)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('email', 254)->unique();
            $table->string('status', 20)->default('pending'); // pending, subscribed, unsubscribed
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_source', 100)->nullable();
            $table->string('consent_text', 500)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('announcement_events');
        Schema::dropIfExists('announcements');
    }
};
