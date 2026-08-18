<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('study_progress', function (Blueprint $table) {
            $table->decimal('average_score', 5, 2)->default(0)->change();
        });
    }

    public function down(): void {
        Schema::table('study_progress', function (Blueprint $table) {
            $table->integer('average_score')->default(0)->change();
        });
    }
};
