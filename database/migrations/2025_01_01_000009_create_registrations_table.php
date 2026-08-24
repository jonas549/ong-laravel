<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('correo');
            $table->string('telefono')->nullable();
            $table->boolean('es_mayor_edad')->default(true);
            $table->string('estado', 20)->default('pendiente');
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'correo']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
