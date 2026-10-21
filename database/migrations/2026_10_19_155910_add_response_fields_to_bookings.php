<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réponse de l'expert : date de la réponse, motif et note en cas de refus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable()->after('requested_at');
            $table->unsignedBigInteger('decline_reason_id')->nullable()->after('responded_at');
            // Note libre transmise au demandeur, en complément du motif.
            $table->string('decline_note', 300)->nullable()->after('decline_reason_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['responded_at', 'decline_reason_id', 'decline_note']);
        });
    }
};