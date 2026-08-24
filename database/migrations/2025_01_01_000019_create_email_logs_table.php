<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->text('to');
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('mailable')->nullable();
            $table->string('status', 20)->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->nullableMorphs('related');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
