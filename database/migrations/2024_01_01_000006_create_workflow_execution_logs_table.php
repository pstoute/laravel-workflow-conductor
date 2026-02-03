<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('workflow-conductor.database.table_prefix', 'workflow_');

        Schema::create($prefix . 'execution_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('execution_id')
                ->constrained($prefix . 'executions')
                ->cascadeOnDelete();
            $table->foreignId('action_id')
                ->nullable()
                ->constrained($prefix . 'actions')
                ->nullOnDelete();
            $table->string('type'); // trigger, condition, action
            $table->string('status'); // success, failed, skipped
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index('execution_id');
            $table->index(['execution_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('workflow-conductor.database.table_prefix', 'workflow_') . 'execution_logs');
    }
};
