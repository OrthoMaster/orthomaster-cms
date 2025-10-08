<?php

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
        Schema::create('professions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->tinyInteger('order_dsp')->default(0);
            $table->boolean('child_flg')->default(false);
            $table->foreignIdFor(Profession::class, 'parent_id')->nullable();
            $table->string('ext_api_code', 4)->nullable();
            $table->boolean('active_flg')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professions');
    }
};
