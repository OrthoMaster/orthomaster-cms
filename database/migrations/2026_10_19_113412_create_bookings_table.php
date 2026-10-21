<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de rendez-vous et leurs demi-journées.
 * Le prix, la TVA, la commission et la politique sont copiés à la création :
 * une demande garde ses conditions même si l'expert ou l'administration les change ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('invoice_id')->nullable();

            // awaiting_payment → pending (chez l'expert) → accepted | declined | expired ; cancelled à tout moment.
            $table->string('status', 30)->default('awaiting_payment');

            $table->string('offer_title', 255);
            $table->unsignedSmallInteger('blocks_count');
            $table->string('currency', 10)->default('eur');
            $table->decimal('amount_ht', 10, 2);
            $table->decimal('tva_rate', 5, 2);
            $table->decimal('tva_amount', 10, 2);
            $table->decimal('amount_ttc', 10, 2);
            // Faux quand le paiement est désactivé : la demande part sans passer par Stripe.
            $table->boolean('payment_required')->default(true);
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('booking_policy_id')->nullable();

            $table->text('client_address');
            $table->string('message', 500)->nullable();

            // Date limite de réponse de l'expert, fixée quand la demande lui parvient.
            $table->timestamp('respond_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['expert_id', 'status']);
            $table->index(['status', 'respond_by']);
        });

        Schema::create('booking_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('expert_id');
            // Date et heures locales de l'expert, plus les instants UTC pour les rappels et Meet.
            $table->date('date');
            $table->string('period', 10);
            $table->string('start_local', 5);
            $table->string('end_local', 5);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            // « expert|date|période » tant que la demi-journée est retenue (en attente ou acceptée),
            // NULL sinon. L'index unique empêche deux demandes de retenir la même demi-journée,
            // même si elles sont validées au même instant.
            $table->string('hold_key', 40)->nullable()->unique();
            $table->timestamps();

            $table->index('booking_id');
            $table->index(['expert_id', 'date']);
        });

        $order    = (int) DB::table('settings')->max('order');
        $settings = [
            ['key' => 'booking.tva_rate',          'display_name' => 'Taux de TVA des rendez-vous (%)',          'value' => '20'],
            // Quand « 12 h avant la première demi-journée » est déjà dépassé (demande tardive pour le lendemain).
            ['key' => 'booking.min_respond_hours', 'display_name' => "Temps de réponse minimal de l'expert (heures)", 'value' => '2'],
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
        Schema::dropIfExists('booking_blocks');
        Schema::dropIfExists('bookings');
        DB::table('settings')->whereIn('key', ['booking.tva_rate', 'booking.min_respond_hours'])->delete();
    }
};