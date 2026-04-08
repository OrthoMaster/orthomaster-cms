<?php

use App\Models\Subscription;
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
        Schema::create('trk_plan_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(Subscription::class);
            $table->string('metric'); // tokens, api_calls, ...

            $table->unsignedBigInteger('used')->default(0);
            $table->unsignedBigInteger('limit_value')->default(0);

            $table->date('period_date');
            $table->timestamps();

            $table->index(['user_id', 'subscription_id', 'metric', 'period_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trk_plan_usages');
    }
};
