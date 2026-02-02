<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('runnable');
            $table->string('driver')->default('claude-code');
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('current_activity')->nullable();
            $table->json('process')->nullable();
            $table->json('composed_context')->nullable();
            $table->json('config')->nullable();
            $table->json('system_info')->nullable();
            $table->text('prompt')->nullable();
            $table->json('result')->nullable();
            $table->json('log_position')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index('status');
            $table->index(['runnable_type', 'runnable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
