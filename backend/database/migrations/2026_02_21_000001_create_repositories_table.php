<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('git_url');
            $table->text('git_token')->nullable();
            $table->string('branch', 100)->default('main');
            $table->string('indexing_status', 50)->default('pending');
            $table->timestamp('indexing_started_at')->nullable();
            $table->timestamp('indexing_completed_at')->nullable();
            $table->text('indexing_error')->nullable();
            $table->integer('total_files')->nullable();
            $table->integer('indexed_files')->nullable();
            $table->integer('total_chunks')->nullable();
            $table->string('last_indexed_commit')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('indexing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
