<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_run_id')
                ->constrained('agent_runs')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['agent_run_id', 'order']);
            $table->index(['agent_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_steps');
    }
};
