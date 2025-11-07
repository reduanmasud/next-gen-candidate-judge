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
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('pre_script')->nullable()->after('docker_compose_yaml');
            $table->text('post_script')->nullable()->after('pre_script');
            $table->enum('judge_type',['none', 'AiJudge', 'QuizJudge', 'TextJudge', 'AutoJudge'])->nullable()->after('post_script');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['pre_script', 'post_script', 'judge_type']);
        });
    }
};
