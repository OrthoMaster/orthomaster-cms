<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des rendez-vous depuis le CMS : journal des événements, annulation par
 * l'administration (au nom du demandeur ou de l'expert), remboursements, no-show.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* --- Journal : qui a fait quoi, quand, au nom de qui --- */
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            // created, submitted, accepted, declined, expired, cancelled, refunded,
            // noshow_reported, noshow_resolved, noshow_dismissed, note…
            $table->string('type', 30);
            // user | expert | admin | system
            $table->string('actor', 10);
            // Action faite par l'administration pour le compte d'une partie : user | expert.
            $table->string('on_behalf_of', 10)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['booking_id', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_by');
            $table->unsignedBigInteger('cancelled_by_admin_id')->nullable()->after('cancel_reason');
            // Total remboursé au demandeur (annulation, no-show), cumulé.
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('amount_ttc');
            $table->timestamp('refunded_at')->nullable()->after('refunded_amount');
        });

        Schema::table('booking_noshow_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable()->after('resolved_at');
            $table->string('resolution_note', 500)->nullable()->after('resolved_by_admin_id');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('resolution_note');
        });
    }

    public function down(): void
    {
        Schema::table('booking_noshow_reports', function (Blueprint $table) {
            $table->dropColumn(['resolved_by_admin_id', 'resolution_note', 'refund_amount']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'cancelled_by_admin_id', 'refunded_amount', 'refunded_at']);
        });

        Schema::dropIfExists('booking_events');
    }
};