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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('lesson_total')->nullable();
            $table->string('duration', 30)->nullable();
            $table->string('image')->nullable();
            $table->boolean('gov_cert_flg')->default(false)->description('Has govement certification');
            $table->smallInteger('order_dsp')->default(0);
            $table->smallInteger('status')->default(0)->description('0: Disable; 1: Published; 2: Comming soon');
            $table->integer('odoo_product_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
