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
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('model')->default('gpt-4o');
            $table->string('prompt_version')->default('v1');
            $table->integer('tokens_in')->default(0);
            $table->integer('tokens_out')->default(0);
            $table->decimal('cost_estimate', 10, 6)->default(0);
            $table->integer('latency_ms')->default(0);
            $table->boolean('ok')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
