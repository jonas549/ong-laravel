<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('de_estado', 20)->nullable();
            $table->string('a_estado', 20);
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_status_logs');
    }
};
