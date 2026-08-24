<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('grupo', 40)->default('general');
            $table->string('clave')->unique();
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->default('string');
            $table->string('label')->nullable();
            $table->string('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['grupo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
