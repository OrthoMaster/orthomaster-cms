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
            // Envoi depuis le CMS à une adresse qui n'est pas un compte (test) : pas d'utilisateur, juste l'e-mail.
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('email')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        DB::table('satisfaction_surveys')->whereNull('user_id')->delete();

        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
