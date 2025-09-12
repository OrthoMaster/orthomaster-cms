<?php

use App\Models\HealthcareProfessional;
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
        Schema::create('healthcare_professional_diplomes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(HealthcareProfessional::class);
            $table->string('code', 20)->nullable();
            $table->string('label')->nullable();
            $table->string('type_code', 20)->nullable();
            $table->string('type_label')->nullable();
            $table->string('authorization_type_code', 20)->nullable();
            $table->string('authorization_type_label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('healthcare_professional_diplomes');
    }
};
