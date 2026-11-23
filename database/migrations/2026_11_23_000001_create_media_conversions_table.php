<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_conversions', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->text('path');
            $table->unsignedInteger('audio_index')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('percent')->default(0);
            $table->boolean('cancel_requested')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_conversions');
    }
};
