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
        Schema::create('phone_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->enum('source', ['unknown', 'user', 'company', 'manual', 'merged'])
                  ->default('unknown');
            $table->unsignedBigInteger('source_user_id')->nullable();
            $table->unsignedBigInteger('source_company_id')->nullable();
            $table->timestamps();

            $table->index('source_user_id');
            $table->index('source_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_contacts');
    }
};
