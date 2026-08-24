<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('logo_path')->nullable();
            $table->string('url')->nullable();
            $table->string('grupo', 30);
            $table->string('tamano', 20)->default('normal');
            $table->string('color', 40)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['grupo', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
