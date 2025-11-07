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
            $table->boolean('sandbox')->nullable()->default(false)->after('judge_type');
            $table->boolean('allowssh')->nullable()->default(false)->after('sandbox');
            $table->integer('timer')->nullable()->default(0)->after('allowssh');
            $table->integer('warrning_timer')->nullable()->default(0)->after('timer');
            $table->boolean('warning_timer_sound')->nullable()->default(false)->after('warrning_timer');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['sandbox', 'allowssh', 'timer', 'warrning_timer', 'warning_timer_sound']);
        });
    }
};
