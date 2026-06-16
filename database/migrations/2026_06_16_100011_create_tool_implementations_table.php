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
        Schema::create('tool_implementations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tool_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('environment');
            $table->string('status');
            $table->string('handler_name')->nullable();
            $table->string('implemented_version')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            $table->unique(['tool_contract_id', 'application_id', 'environment'], 'tool_impl_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_implementations');
    }
};
