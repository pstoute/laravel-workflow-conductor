<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('workflow-conductor.database.table_prefix', 'workflow_');

        Schema::create($prefix . 'triggers', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained($prefix . 'workflows')
                ->cascadeOnDelete();
            $table->string('type'); // model_created, webhook, scheduled, etc.
            $table->json('configuration');
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('workflow-conductor.database.table_prefix', 'workflow_') . 'triggers');
    }
};
