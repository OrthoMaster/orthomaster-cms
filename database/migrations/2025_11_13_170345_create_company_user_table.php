<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(Company::class);
            $table->boolean('manager_flg')->nullable()->default(false);
            $table->unique(['user_id', 'company_id']);
            $table->timestamps();
        });

        DB::table('users')
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id'    => $user->id,
                        'company_id' => $user->company_id,
                        'manager_flg' => $user->role_id == 4 ? true : false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($rows)) {
                    DB::table('company_user')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
