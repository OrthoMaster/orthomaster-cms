<?php

use App\Models\Training;
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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Training::class);
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('lesson_total')->nullable();
            $table->integer('duration')->nullable()->default(0);
            $table->string('image')->nullable();
            $table->smallInteger('order_dsp')->nullable()->default(0);
            $table->boolean('active_flg')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
