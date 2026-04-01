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
        Schema::create('sale_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'sale_id');
            $table->foreignIdFor(User::class, 'customer_id');
            $table->unique(['customer_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_assignments');
    }
};
