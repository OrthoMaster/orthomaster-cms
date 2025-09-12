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
        Schema::create('healthcare_professional_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(HealthcareProfessional::class);
            $table->string('code', 20)->nullable();
            $table->string('label')->nullable();
            $table->string('category_code', 20)->nullable();
            $table->string('category_label')->nullable();
            $table->string('civility_pro_code', 5)->nullable();
            $table->string('civility_pro_label', 20)->nullable();
            $table->string('civility_code', 5)->nullable();
            $table->string('civility_label', 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('healthcare_professional_activities');
    }
};
