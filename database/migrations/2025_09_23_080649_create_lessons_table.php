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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Training::class);
            $table->string('title');
            $table->string('image')->nullable();
            $table->string('duration', 50)->nullable();
            $table->smallInteger('order_dsp')->default(0);
            $table->string('tags')->nullable();
            $table->boolean('demo_flg')->nullable()->default(0);
            $table->integer('content_id')->nullable()->description('Link with previous version');
            $table->integer('odoo_id')->nullable()->description('Link with Odoo');
            $table->boolean('published_flg')->nullable()->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
