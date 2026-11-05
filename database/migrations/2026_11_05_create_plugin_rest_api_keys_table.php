<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('plugin_rest_api_keys')) {
            Schema::create('plugin_rest_api_keys', function (Blueprint $table) {
                $table->id();
                // Exactly one key per user
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('key_prefix', 16);
                // SHA-256 (hex) of the key, the key itself is never stored
                $table->char('key_hash', 64)->unique();
                $table->dateTime('last_used_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_rest_api_keys');
    }
};
