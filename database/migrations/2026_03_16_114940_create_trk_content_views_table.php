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
        Schema::create('trk_content_views', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->foreignId('content_id');
            $table->string('type', 10)->default('flash');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['content_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trk_content_views');
    }
};
