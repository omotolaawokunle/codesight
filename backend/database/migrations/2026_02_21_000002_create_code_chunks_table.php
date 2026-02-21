<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('vector_id')->nullable();
            $table->text('file_path');
            $table->string('chunk_type', 50)->nullable();
            $table->string('name')->nullable();
            $table->integer('start_line')->nullable();
            $table->integer('end_line')->nullable();
            $table->string('language', 50)->nullable();
            $table->text('signature')->nullable();
            $table->text('docstring')->nullable();
            $table->timestamps();

            $table->index('repository_id');
            $table->index('chunk_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_chunks');
    }
};
