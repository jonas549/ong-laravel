<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participation_cards', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('nota')->nullable();
            $table->string('cta')->nullable();
            $table->string('href')->nullable();
            $table->string('color', 40)->nullable();
            $table->string('icono', 40)->nullable();
            $table->string('mask_path')->nullable();
            $table->string('art_path')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_cards');
    }
};
