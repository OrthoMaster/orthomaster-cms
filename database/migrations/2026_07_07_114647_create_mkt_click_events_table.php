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
        Schema::create('mkt_click_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_link_id')->constrained('mkt_short_links')->cascadeOnDelete();
            $table->string('source_type', 20)->default('shortlink'); // shortlink | qrcode
            $table->ipAddress('ip_address')->nullable();
            $table->string('ip_hash', 64)->nullable();      // hash IP (RGPD-friendly)
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();  // mobile | tablet | desktop
            $table->string('browser', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('referer')->nullable();
            $table->string('country', 2)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamps();

            $table->index('short_link_id');
            $table->index('source_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mkt_click_events');
    }
};
