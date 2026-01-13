<?php

use App\Models\Cart;
use App\Models\Company;
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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('no')->unique();
            $table->foreignIdFor(Cart::class)->nullable();
            $table->foreignIdFor(Company::class)->nullable();
            $table->string('document');
            $table->string('type', 50)->nullable();
            $table->tinyInteger('status')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
