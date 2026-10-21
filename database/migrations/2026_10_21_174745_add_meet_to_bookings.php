<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien Google Meet de chaque demi-journée (une salle par demi-journée).
 * meet_space : identifiant Google de la salle (« spaces/xxxx »), utile pour la fermer plus tard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->string('meet_url')->nullable()->after('end_at');
            $table->string('meet_space', 120)->nullable()->after('meet_url');
            $table->timestamp('meet_created_at')->nullable()->after('meet_space');
        });
    }

    public function down(): void
    {
        Schema::table('booking_blocks', function (Blueprint $table) {
            $table->dropColumn(['meet_url', 'meet_space', 'meet_created_at']);
        });
    }
};