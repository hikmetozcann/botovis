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
        Schema::create('botovis_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('title')->default('Yeni Sohbet');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // For session-based conversations (guest users)
            $table->string('session_id')->nullable()->index();
        });

        Schema::create('botovis_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            
            // Intent tracking for analytics
            $table->string('intent')->nullable();
            $table->string('action')->nullable();
            $table->string('table')->nullable();
            $table->json('parameters')->nullable();
            
            // Execution details
            $table->boolean('success')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('botovis_conversations')
                ->onDelete('cascade');

            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('botovis_messages');
        Schema::dropIfExists('botovis_conversations');
    }
};
