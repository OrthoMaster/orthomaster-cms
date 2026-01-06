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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->string('fcm_token', 255)->unique();
            $table->string('device_id', 255)->nullable();
            $table->enum('device_type', ['ios', 'android', 'web']);
            $table->timestamp('last_used_at')->useCurrent();
            $table->timestamps();

            $table->index('user_id');
            $table->index('device_id');
            $table->unique(['user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
