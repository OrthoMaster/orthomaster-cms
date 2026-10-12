<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 1 des rendez-vous experts : configuration uniquement.
 * Les tables de réservation (bookings, booking_blocks, incidents, logs) viendront
 * avec la phase 3, une fois le calcul des créneaux validé.
 *
 * Pas de clé étrangère vers `experts` : table gérée par Voyager dont le type de
 * clé primaire n'est pas garanti identique à unsignedBigInteger. Simple index.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* --- Réglages par expert --- */
        Schema::create('expert_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id')->unique();
            $table->boolean('enabled_flg')->default(false);
            // Les créneaux récurrents sont des heures murales : stockés en UTC, ils
            // glisseraient d'une heure à chaque changement d'heure été/hiver.
            $table->string('timezone', 64)->default('Europe/Paris');
            // NULL = valeur système (réglages Voyager), pour qu'un changement global
            // s'applique à tous les experts qui n'ont rien personnalisé.
            $table->unsignedSmallInteger('min_notice_hours')->nullable();
            $table->unsignedTinyInteger('max_advance_months')->nullable();
            $table->time('morning_start')->nullable();
            $table->time('morning_end')->nullable();
            $table->time('afternoon_start')->nullable();
            $table->time('afternoon_end')->nullable();
            $table->timestamps();
        });

        /* --- Offres de consultation --- */
        Schema::create('expert_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            // Nombre de demi-journées (1 = 0,5 jour) : un entier évite les arrondis d'un décimal.
            $table->unsignedTinyInteger('blocks_count');
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->boolean('active_flg')->default(true);
            $table->unsignedSmallInteger('order_dsp')->default(0);
            $table->timestamps();
            // Les réservations passées doivent pouvoir retrouver une offre retirée.
            $table->softDeletes();

            $table->index(['expert_id', 'active_flg']);
        });

        /* --- Disponibilités récurrentes --- */
        Schema::create('expert_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id');
            // Convention ISO-8601 (1 = lundi … 7 = dimanche), identique à Carbon::dayOfWeekIso.
            $table->unsignedTinyInteger('weekday');
            $table->enum('period', ['morning', 'afternoon']);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['expert_id', 'weekday']);
        });

        /* --- Exceptions ponctuelles --- */
        Schema::create('expert_availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expert_id');
            $table->date('date');
            // NULL = journée entière, pour bloquer un jour en une seule ligne.
            $table->enum('period', ['morning', 'afternoon'])->nullable();
            $table->enum('type', ['add', 'block']);
            $table->timestamps();

            $table->unique(['expert_id', 'date', 'period']);
        });

        /* --- Commission (historisée) --- */
        Schema::create('booking_commission_rates', function (Blueprint $table) {
            $table->id();
            // NULL = taux général ; une ligne avec expert_id prime sur le taux général.
            $table->unsignedBigInteger('expert_id')->nullable();
            // En pourcentage (20.00 = 20 %).
            $table->decimal('rate', 5, 2);
            // Historisé plutôt que modifié en place : chaque réservation copie le taux
            // en vigueur, et les factures passées restent justes après un changement.
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['expert_id', 'effective_from']);
        });

        /* --- Politique d'annulation et no-show (générale, historisée) --- */
        Schema::create('booking_policies', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->unsignedSmallInteger('free_cancel_hours');
            $table->decimal('late_cancel_penalty_pct', 5, 2);
            // Le no-show expert rembourse toujours 100 %, d'où un seul taux ici.
            $table->decimal('user_noshow_refund_pct', 5, 2);
            $table->timestamps();
        });

        /* --- Motifs de refus --- */
        Schema::create('booking_decline_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150);
            $table->unsignedSmallInteger('order_dsp')->default(0);
            $table->boolean('active_flg')->default(true);
            $table->timestamps();
        });

        /* --- Données initiales --- */
        $now   = now();
        $today = $now->toDateString();

        // Valeurs de départ uniquement, modifiables ensuite depuis le CMS.
        DB::table('booking_policies')->insert([
            'effective_from'          => $today,
            'free_cancel_hours'       => 72,
            'late_cancel_penalty_pct' => 50,
            'user_noshow_refund_pct'  => 0,
            'created_at'              => $now,
            'updated_at'              => $now,
        ]);

        DB::table('booking_commission_rates')->insert([
            'expert_id'      => null,
            'rate'           => 20,
            'effective_from' => $today,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $reasons = [
            'Indisponible à cette date',
            'Sujet hors de mon domaine d\'expertise',
            'Informations insuffisantes sur la demande',
        ];
        foreach ($reasons as $i => $label) {
            DB::table('booking_decline_reasons')->insert([
                'label'      => $label,
                'order_dsp'  => $i + 1,
                'active_flg' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /* --- Réglages système (Voyager) --- */
        // Le préfixe « booking. » correspond au groupe « Booking », comme le fait
        // Voyager lorsqu'un réglage est créé depuis son interface.
        $order    = (int) DB::table('settings')->max('order');
        $settings = [
            [
                'key'          => 'booking.payment_enabled',
                'display_name' => 'Paiement des rendez-vous activé',
                'value'        => '0',
                'details'      => '{"on":"Oui","off":"Non","checked":false}',
                'type'         => 'checkbox',
            ],
            ['key' => 'booking.default_morning_start',     'display_name' => 'Matin — début par défaut (HH:MM)',          'value' => '09:00'],
            ['key' => 'booking.default_morning_end',       'display_name' => 'Matin — fin par défaut (HH:MM)',            'value' => '12:00'],
            ['key' => 'booking.default_afternoon_start',   'display_name' => 'Après-midi — début par défaut (HH:MM)',     'value' => '14:00'],
            ['key' => 'booking.default_afternoon_end',     'display_name' => 'Après-midi — fin par défaut (HH:MM)',       'value' => '17:00'],
            ['key' => 'booking.default_min_notice_hours',  'display_name' => 'Délai minimum de réservation (heures)',     'value' => '24'],
            ['key' => 'booking.default_max_advance_months', 'display_name' => 'Réservation au plus tard dans (mois)',     'value' => '6'],
        ];

        foreach ($settings as $i => $setting) {
            DB::table('settings')->insertOrIgnore($setting + [
                'details' => null,
                'type'    => 'text',
                'order'   => $order + $i + 1,
                'group'   => 'Booking',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'like', 'booking.%')->delete();

        Schema::dropIfExists('booking_decline_reasons');
        Schema::dropIfExists('booking_policies');
        Schema::dropIfExists('booking_commission_rates');
        Schema::dropIfExists('expert_availability_exceptions');
        Schema::dropIfExists('expert_availability_rules');
        Schema::dropIfExists('expert_offers');
        Schema::dropIfExists('expert_booking_settings');
    }
};