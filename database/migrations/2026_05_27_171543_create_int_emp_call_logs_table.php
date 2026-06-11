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
        Schema::create('int_emp_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_serial')->index();
            $table->string('assigned_phone')->index();
            $table->string('partner_phone');
            $table->enum('call_type', ['incoming', 'outgoing']);
            $table->enum('status', ['answered', 'missed', 'completed']);
            $table->integer('duration_seconds');
            $table->double('timestamp_apple');
            $table->timestamp('call_time');
            $table->enum('customer_type', ['prospect', 'follow-up', 'member-care'])->nullable();
            $table->datetime('next_follow_up_at')->nullable();
            $table->timestamps();

            $table->unique(['device_serial', 'timestamp_apple'], 'unique_device_call');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_emp_call_logs');
    }
};
