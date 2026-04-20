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
        Schema::create('kpi_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->date('log_date');
            $table->unsignedInteger('calls_count')->default(0);
            $table->unsignedInteger('appointments_count')->default(0);
            $table->unsignedInteger('attended_count')->default(0);

            $table->unsignedInteger('registrations_count')->default(0);
            $table->unsignedInteger('purchases_count')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_daily_logs');
    }
};
