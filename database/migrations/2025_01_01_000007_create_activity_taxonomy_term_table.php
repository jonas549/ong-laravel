<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_taxonomy_term', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('taxonomy_term_id')->constrained()->cascadeOnDelete();

            $table->unique(['activity_id', 'taxonomy_term_id'], 'activity_term_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_taxonomy_term');
    }
};
