<?php

use App\Models\User;
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
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->date('effective_from');
            $table->unsignedInteger('calls_target')->default(0);
            $table->unsignedInteger('appointments_target')->default(0);
            $table->unsignedInteger('attended_target')->default(0);
            $table->unsignedInteger('registrations_target')->default(0);
            $table->unsignedInteger('purchases_target')->default(0);
            $table->decimal('revenue_target', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_targets');
    }
};
