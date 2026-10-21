<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Délai minimum de réservation : des heures aux jours calendaires.
 * N jours = réservable à partir du N-ième jour suivant (dans le fuseau de l'expert),
 * dès la première demi-journée, quelle que soit l'heure de la demande.
 * Valeurs existantes arrondies au jour supérieur (24 h → 1 j, 36 h → 2 j).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_notice_days')->nullable()->after('timezone');
        });

        DB::table('expert_booking_settings')
            ->whereNotNull('min_notice_hours')
            ->update(['min_notice_days' => DB::raw('GREATEST(1, CEIL(min_notice_hours / 24))')]);

        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->dropColumn('min_notice_hours');
        });

        $hours = DB::table('settings')->where('key', 'booking.default_min_notice_hours')->value('value');
        $days  = is_numeric($hours) ? max(1, (int) ceil(((int) $hours) / 24)) : 1;

        DB::table('settings')
            ->where('key', 'booking.default_min_notice_hours')
            ->update([
                'key'          => 'booking.default_min_notice_days',
                'display_name' => 'Délai minimum de réservation (jours)',
                'value'        => (string) $days,
            ]);
    }

    public function down(): void
    {
        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_notice_hours')->nullable()->after('timezone');
        });

        DB::table('expert_booking_settings')
            ->whereNotNull('min_notice_days')
            ->update(['min_notice_hours' => DB::raw('min_notice_days * 24')]);

        Schema::table('expert_booking_settings', function (Blueprint $table) {
            $table->dropColumn('min_notice_days');
        });

        $days = DB::table('settings')->where('key', 'booking.default_min_notice_days')->value('value');

        DB::table('settings')
            ->where('key', 'booking.default_min_notice_days')
            ->update([
                'key'          => 'booking.default_min_notice_hours',
                'display_name' => 'Délai minimum de réservation (heures)',
                'value'        => (string) (is_numeric($days) ? ((int) $days) * 24 : 24),
            ]);
    }
};