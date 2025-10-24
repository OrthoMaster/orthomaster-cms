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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->smallInteger('duration_total')->nullable()->default(0);
            $table->decimal('price_total', 10, 2)->nullable();
            $table->tinyInteger('payment_type')->nullable()->default(0);
            $table->tinyInteger('status')->nullable()->default(0)->description('0: Temp; 1: Submitted;');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
