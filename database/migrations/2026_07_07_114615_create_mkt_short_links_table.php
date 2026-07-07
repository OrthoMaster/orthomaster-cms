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
        Schema::create('mkt_short_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // XXXXX dans /sh/XXXXX
            $table->text('destination_url');                // lien final (avec params UTM déjà intégrés)
            $table->text('original_url')->nullable();       // lien de base saisi par l'utilisateur
            $table->string('title')->nullable();            // libellé interne
            $table->string('source_type', 20)->default('shortlink'); // shortlink | qrcode
            // paramètres UTM stockés séparément pour analyse
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->boolean('tracking_enabled')->default(true);
            $table->unsignedBigInteger('clicks_count')->default(0);  // compteur dénormalisé (rapide)
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->index('source_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mkt_short_links');
    }
};
