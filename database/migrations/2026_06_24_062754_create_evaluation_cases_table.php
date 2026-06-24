<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single case in a golden dataset: the input the agent is given, the workflow
 * it exercises (kind), the assertions its run must satisfy (expectations — text
 * the answer must contain, a tool that must be called, phrases that must not
 * appear, whether a citation is required, and cost/latency ceilings), and any
 * stubbed tool results the evaluation feeds back when the run pauses for a
 * client-side tool.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_dataset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('no_tool');
            $table->text('input');
            $table->json('expectations');
            $table->json('tool_stubs')->nullable();
            $table->unsignedInteger('ordinal')->default(0);
            $table->timestamps();

            $table->index('evaluation_dataset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_cases');
    }
};
