<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 80);
            $table->string('handle', 40)->nullable()->unique();
            // FK to media_assets is added in Milestone 2 when the media library exists.
            $table->unsignedBigInteger('avatar_media_id')->nullable();
            $table->string('bio', 1000)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('visibility', 16)->default('members');
            $table->boolean('show_on_leaderboard')->default(true);
            $table->timestamps();
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('profiles');
    }
};
