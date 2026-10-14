<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks first-party CAPTCHA challenges so each can be used exactly once (replay protection).
        Schema::create('captcha_challenges', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('purpose', 40);
            $table->unsignedTinyInteger('difficulty');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_challenges');
    }
};
