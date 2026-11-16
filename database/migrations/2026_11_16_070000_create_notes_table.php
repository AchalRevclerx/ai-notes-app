<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->text('summary')->nullable();
            $table->timestamp('summary_generated_at')->nullable();
            $table->vector('embedding', dimensions: config('ai.embedding_dimensions'))->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->timestamps();

            // Listing: WHERE user_id = ? ORDER BY updated_at DESC
            $table->index(['user_id', 'updated_at']);
            // Semantic search: HNSW index with cosine distance (vector_cosine_ops)
            $table->vectorIndex('embedding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
