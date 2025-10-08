<?php

use App\Models\Establishment;
use App\Models\NoProfType;
use App\Models\Profession;
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
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('password_temp', 15)->nullable()->after('password');
            $table->string('phone', 30)->nullable()->after('password_temp');
            $table->string('country_code', 3)->nullable()->after('phone');
            $table->string('code_postal', 20)->nullable()->after('country_code');
            $table->string('city', 50)->nullable()->after('code_postal');
            $table->boolean('student_flg')->nullable()->after('city');
            
            $table->foreignIdFor(Establishment::class)->nullable()->after('student_flg');
            $table->string('other_establishment', 100)->nullable()->after('establishment_id');

            $table->foreignIdFor(Profession::class)->nullable()->after('other_establishment');
            $table->string('other_profession', 100)->nullable()->after('profession_id');

            $table->foreignIdFor(NoProfType::class)->nullable()->after('other_profession');
            $table->string('no_prof', 100)->nullable()->after('no_prof_type_id');

            $table->string('certificate')->nullable()->after('no_prof');

            $table->tinyInteger('status')->default(1)->after('certificate');
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
                'first_name',
                'last_name',
                'password_temp',
                'phone',
                'country_code',
                'code_postal',
                'city',
                'student_flg',
                'establishment_id',
                'other_establishment',
                'profession_id',
                'other_profession',
                'no_prof_type_id',
                'no_prof',
                'certificate',
                'status',
                'expired_at'
            ]);
        });
    }
};
