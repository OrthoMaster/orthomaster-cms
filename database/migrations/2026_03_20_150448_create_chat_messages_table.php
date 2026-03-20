<?php

use App\Models\ChatConversation;
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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ChatConversation::class);
            $table->enum('role', ['user', 'bot']);
            $table->mediumText('content');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
 
            $table->index(['chat_conversation_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
