<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('workflow-conductor.database.table_prefix', 'workflow_');

        Schema::table($prefix . 'actions', function (Blueprint $table) {
            $table->string('node_id')->nullable()->after('type');

            $table->index('node_id');
        });
    }

    public function down(): void
    {
        $prefix = config('workflow-conductor.database.table_prefix', 'workflow_');

        Schema::table($prefix . 'actions', function (Blueprint $table) {
            $table->dropIndex(['node_id']);
            $table->dropColumn('node_id');
        });
    }
};
