<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            // Données de démonstration (satisfaction:fake) : jamais envoyées, supprimables d'un coup.
            $table->boolean('is_fake')->default(false)->index()->after('id');
            // Les questionnaires fictifs ne viennent d'aucun panier ; NULL n'entre pas en conflit avec unique(cart_id, user_id).
            $table->unsignedBigInteger('cart_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('satisfaction_surveys')->where('is_fake', true)->delete();

        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            $table->dropColumn('is_fake');
            $table->unsignedBigInteger('cart_id')->nullable(false)->change();
        });
    }
};
