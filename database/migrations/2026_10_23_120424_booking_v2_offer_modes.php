<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous v2 : offres en ligne / en présentiel, calendrier propre à chaque offre,
 * lieux de l'expert, politique par mode.
 *
 * Le prix quitte expert_offers : chaque mode a le sien, saisi TTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* --- Lieux de l'expert --- */
        Schema::create('expert_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id');
            // Nom court affiché là où la place manque (choix d'un créneau, carte, agenda).
            $table->string('label', 60);
            $table->string('address_line', 255);
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120);
            $table->char('country', 2)->default('FR');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('google_place_id', 255)->nullable();
            // Étage, digicode, stationnement : montré seulement une fois le rendez-vous accepté.
            $table->text('access_details')->nullable();
            // Archivé plutôt que supprimé : créneaux et rendez-vous passés y font référence.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['expert_id', 'archived_at']);
        });

        Schema::table('expert_availability_rules', function (Blueprint $table) {
            // Null : demi-journée sans adresse, réservable en ligne uniquement.
            $table->unsignedBigInteger('location_id')->nullable()->after('period');
            $table->index('location_id');
        });

        /* --- Modes d'une offre --- */
        Schema::create('expert_offer_modes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->enum('mode', ['online', 'onsite']);
            // TTC par demi-journée : HT et TVA sont déduits au moment de la réservation.
            $table->decimal('price', 10, 2);
            $table->enum('schedule_type', ['weekly_all', 'weekly_selected', 'dates'])->default('weekly_all');
            // Seulement pour « dates » : chaque date ferme N jours avant.
            $table->unsignedSmallInteger('book_ahead_days')->nullable();
            // Désactiver un mode garde sa configuration pour plus tard.
            $table->boolean('active_flg')->default(true);
            $table->timestamps();

            $table->unique(['offer_id', 'mode']);
        });

        Schema::create('expert_offer_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->enum('mode', ['online', 'onsite']);
            $table->unsignedTinyInteger('weekday');
            $table->enum('period', ['morning', 'afternoon']);
            $table->timestamps();

            $table->unique(['offer_id', 'mode', 'weekday', 'period'], 'expert_offer_slots_unique');
            // Contrôle 2b : retrouver les offres touchées quand l'expert retire une demi-journée.
            $table->index(['weekday', 'period']);
        });

        Schema::create('expert_offer_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->enum('mode', ['online', 'onsite']);
            $table->date('date');
            $table->enum('period', ['morning', 'afternoon']);
            $table->timestamps();

            $table->unique(['offer_id', 'mode', 'date', 'period'], 'expert_offer_dates_unique');
            $table->index(['date', 'period']);
        });

        // Données de dev : chaque offre existante devient une offre en ligne au même prix.
        DB::table('expert_offer_modes')->insertUsing(
            ['offer_id', 'mode', 'price', 'schedule_type', 'active_flg', 'created_at', 'updated_at'],
            DB::table('expert_offers')->selectRaw("id, 'online', price, 'weekly_all', 1, NOW(), NOW()")
        );

        Schema::table('expert_offers', function (Blueprint $table) {
            $table->dropColumn('price');
        });

        /* --- Réservations --- */
        Schema::table('bookings', function (Blueprint $table) {
            // Figé à la réservation : on ne change jamais de mode ensuite, même en déplaçant une séance.
            $table->enum('mode', ['online', 'onsite'])->default('online')->after('offer_id');
        });

        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('end_at');
            // Copie de l'adresse au moment de la réservation : modifier ou archiver le lieu
            // ne doit pas changer ce que le client a réservé.
            // {label, address_line, postal_code, city, country, lat, lng, google_place_id, access_details}
            $table->json('location')->nullable()->after('location_id');
        });

        /* --- Politique par mode --- */
        Schema::table('booking_policies', function (Blueprint $table) {
            $table->enum('mode', ['online', 'onsite'])->default('online')->after('effective_from');
            $table->dropUnique('booking_policies_effective_from_unique');
            $table->unique(['effective_from', 'mode']);
        });

        // Chaque mode doit toujours avoir une politique en vigueur : on part de celle en ligne.
        DB::table('booking_policies')->insertUsing(
            ['effective_from', 'mode', 'free_cancel_hours', 'late_cancel_penalty_pct', 'user_noshow_refund_pct', 'created_at', 'updated_at'],
            DB::table('booking_policies')
                ->where('mode', 'online')
                ->selectRaw("effective_from, 'onsite', free_cancel_hours, late_cancel_penalty_pct, user_noshow_refund_pct, NOW(), NOW()")
        );
    }

    public function down(): void
    {
        DB::table('booking_policies')->where('mode', 'onsite')->delete();
        Schema::table('booking_policies', function (Blueprint $table) {
            $table->dropUnique(['effective_from', 'mode']);
            $table->dropColumn('mode');
            $table->unique('effective_from');
        });

        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->dropColumn(['location_id', 'location']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('mode');
        });

        Schema::table('expert_offers', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('blocks_count');
        });
        DB::statement("UPDATE expert_offers o
            JOIN expert_offer_modes m ON m.offer_id = o.id AND m.mode = 'online'
            SET o.price = m.price");

        Schema::dropIfExists('expert_offer_dates');
        Schema::dropIfExists('expert_offer_slots');
        Schema::dropIfExists('expert_offer_modes');

        Schema::table('expert_availability_rules', function (Blueprint $table) {
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::dropIfExists('expert_locations');
    }
};