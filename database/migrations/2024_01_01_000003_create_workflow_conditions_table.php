<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('workflows.database.table_prefix', 'workflow_');

        Schema::create($prefix . 'conditions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained($prefix . 'workflows')
                ->cascadeOnDelete();
            $table->string('type'); // field, date, relation, custom
            $table->string('field')->nullable();
            $table->string('operator');
            $table->json('value')->nullable();
            $table->string('logic')->default('and'); // and, or
            $table->integer('group')->default(0); // for grouping conditions
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['workflow_id', 'group', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('workflows.database.table_prefix', 'workflow_') . 'conditions');
    }
};
