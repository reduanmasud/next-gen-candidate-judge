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
        Schema::create('script_job_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->nullable()->index();
            $table->string('script_name');
            $table->string('script_path')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'terminated'])->default('pending');
            $table->longText('script_content')->nullable();
            $table->longText('output')->nullable();
            $table->longText('error_output')->nullable();
            $table->integer('exit_code')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('timed_out_at')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('server_id')->nullable()->constrained('servers')->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('cascade');
            $table->foreignId('attempt_id')->nullable()->constrained('user_task_attempts')->onDelete('cascade');

            $table->foreign('job_id')->nullable()->references('id')->on('jobs')->onDelete('cascade');

            $table->index(['status', 'started_at'], 'script_job_runs_status_started_at_index');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('script_job_runs');
    }
};
