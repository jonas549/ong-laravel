<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['region_id', 'slug']);
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
