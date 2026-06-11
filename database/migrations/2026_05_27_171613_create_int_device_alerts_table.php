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
        Schema::create('int_device_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('device_serial')->index();
            $table->string('assigned_phone')->nullable();
            $table->string('alert_type');
            $table->text('message');
            $table->timestamp('detected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_device_alerts');
    }
};
