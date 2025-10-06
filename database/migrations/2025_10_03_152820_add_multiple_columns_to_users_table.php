<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable()->after('name');
            $table->string('password_temp', 15)->nullable()->after('password');
            $table->string('phone', 30)->nullable()->after('password_temp');
            $table->string('country_code', 3)->nullable()->after('phone');
            $table->string('code_postal', 20)->nullable()->after('country_code');
            $table->string('city', 50)->nullable()->after('code_postal');
            $table->boolean('student_flg')->nullable()->after('city');
            $table->boolean('active_flg')->default(true)->after('student_flg');
            $table->timestamp('expired_at')->nullable()->after('active_flg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'password_temp',
                'phone',
                'country_code',
                'code_postal',
                'city',
                'student_flg',
                'active_flg',
                'expired_at'
            ]);
        });
    }
};
