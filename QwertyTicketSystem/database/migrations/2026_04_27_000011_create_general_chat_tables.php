<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_chat_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kicked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('kick_reason')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('kicked_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('general_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('general_chat_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('general_chat_messages')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_chat_reads');
        Schema::dropIfExists('general_chat_messages');
        Schema::dropIfExists('general_chat_members');
    }
};
