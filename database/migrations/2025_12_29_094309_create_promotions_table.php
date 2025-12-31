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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount configuration
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // For percentage type
            $table->decimal('min_purchase_amount', 10, 2)->default(0);

            // Usage limits
            $table->integer('usage_limit')->nullable(); // Total usage limit
            $table->integer('usage_limit_per_user')->default(1);
            $table->integer('used_count')->default(0);

            // Applicability
            $table->string('applicable_to')->nullable()->default('all');
            $table->boolean('first_purchase_only')->default(false);
            $table->boolean('active_flg')->default(false);

            // Time constraints
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index(['start_date', 'end_date']);
            $table->index('active_flg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
