<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('workflow-conductor.database.table_prefix', 'workflow_');

        Schema::create($prefix . 'executions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained($prefix . 'workflows')
                ->cascadeOnDelete();
            $table->string('trigger_type');
            $table->json('trigger_data')->nullable();
            $table->string('status'); // pending, running, completed, failed, skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index('created_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('workflow-conductor.database.table_prefix', 'workflow_') . 'executions');
    }
};
