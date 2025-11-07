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
        Schema::create('user_task_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_task_attempt_id')->constrained('user_task_attempts')->onDelete('cascade');
            $table->json('answers')->nullable();
            $table->string('score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_task_attempt_answers');
    }
};
