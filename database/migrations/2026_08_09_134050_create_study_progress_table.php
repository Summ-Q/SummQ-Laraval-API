<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('study_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flashcard_id')->constrained()->onDelete('cascade');
            $table->integer('review_count')->unsigned()->default(0);
            $table->integer('average_score')->unsigned()->default(0);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('next_review_due_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('study_progress');
    }
};
