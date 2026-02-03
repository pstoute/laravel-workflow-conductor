<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('workflows.database.table_prefix', 'workflow_');

        Schema::create($prefix . 'actions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained($prefix . 'workflows')
                ->cascadeOnDelete();
            $table->string('type'); // send_email, webhook, create_model, etc.
            $table->json('configuration');
            $table->integer('order')->default(0);
            $table->integer('delay')->default(0); // seconds to delay execution
            $table->boolean('continue_on_failure')->default(true);
            $table->timestamps();

            $table->index(['workflow_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('workflows.database.table_prefix', 'workflow_') . 'actions');
    }
};
