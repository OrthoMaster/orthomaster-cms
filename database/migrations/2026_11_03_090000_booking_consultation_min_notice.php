<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation : délai minimum avant le rendez-vous propre à l'expert.
 * NULL = même délai que les Thèmes (min_notice_days de l'expert, ou valeur système).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('consultation_min_notice_days')->nullable()->after('consultation_max_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->dropColumn('consultation_min_notice_days');
        });
    }
};
