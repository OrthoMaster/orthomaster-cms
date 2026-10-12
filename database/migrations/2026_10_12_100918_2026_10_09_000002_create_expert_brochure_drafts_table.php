<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_brochure_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('honorific', 40)->nullable();
            $table->string('credentials', 200)->nullable();
            $table->text('lede')->nullable();
            $table->string('palette', 20)->default('turquoise');
            $table->string('model', 20)->default('spine');
            $table->json('data');                             // lieux, liens, titres, formation, parcours
            // NULL = modifications pas encore publiées dans experts
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_brochure_drafts');
    }
};