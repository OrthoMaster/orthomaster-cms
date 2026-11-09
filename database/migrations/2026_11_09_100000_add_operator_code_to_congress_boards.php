<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Code des intervenants : ouvre l'écran où ils suppriment et réordonnent les questions.
 * Distinct du code du public, affiché en grand dans la salle.
 */
return new class extends Migration
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('congress_boards', function (Blueprint $table) {
            $table->string('operator_code', 8)->nullable()->unique()->after('code');
        });

        foreach (DB::table('congress_boards')->whereNull('operator_code')->pluck('id') as $id) {
            do {
                $code = '';
                for ($i = 0; $i < 5; $i++) {
                    $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
            } while (DB::table('congress_boards')->where('code', $code)->orWhere('operator_code', $code)->exists());

            DB::table('congress_boards')->where('id', $id)->update(['operator_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('congress_boards', function (Blueprint $table) {
            $table->dropUnique(['operator_code']);
            $table->dropColumn('operator_code');
        });
    }
};
